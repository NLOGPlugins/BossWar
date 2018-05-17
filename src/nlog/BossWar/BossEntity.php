<?php
/**
 * Created by PhpStorm.
 * User: NLOG
 * Date: 2018-04-28
 * Time: 오후 1:22
 */

namespace nlog\BossWar;

use nlog\BossWar\tasks\BandPostTask;
use nlog\BossWar\tasks\BossSpawnTask;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use milk\pureentities\entity\monster\walking\Zombie;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\Explosion;
use pocketmine\level\Level;
use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Random;
use pocketmine\utils\Utils;

class BossEntity extends Zombie {

    private $tick, $time;

    public function __construct(Level $level, CompoundTag $nbt) {
        parent::__construct($level, $nbt);
        $this->tick = 0;
        $this->time = 0;
    }

    /*
    public function close() {
        parent::close();
        $this->level->removeEntity($this);
    }
    */

    public function attackEntity(Entity $player) {
        if ($this->attackDelay > 6 && $this->distanceSquared($player) < 4) {
            $this->attackDelay = 0;
            // maybe this needs some rework ... as it should be calculated within the event class and take
            // mob's weapon into account. for now, i just add the damage from the weapon the mob wears
            $damage = $this->getDamage();
            $ev = new EntityDamageByEntityEvent($this, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 100);
            $player->attack($ev);
        }
    }

    private function plustime() {
        $this->time = time();

        ++$this->tick;
        if ($this->tick === 10 || $this->tick === 25) {
            Server::getInstance()->broadcastMessage(BossWar::$prefix . "5초 후 보스가 스킬을 사용합니다.", $this->level->getPlayers());
        } elseif ($this->tick === 15) {
            $this->useSkillExplode();
        } elseif ($this->tick >= 30) {
            $this->useSkillLightning();
            $this->tick = 0;
        }
    }

    public function entityBaseTick(int $tickDiff = 1): bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        if (!$this->level->isInWorld($this->x, $this->y, $this->z)) {
            $this->teleport(BossWar::getInstance()->getBossSpawn());
        }

        if ($this->time <= 0) {
            $this->time = time();
        } elseif (time() - $this->time >= 1) {
            $this->plustime();
        }

        return $hasUpdate;
    }

    protected function useSkillExplode() {
        Server::getInstance()->broadcastMessage(BossWar::$prefix . "보스가 폭발 스킬을 사용 했습니다.", $this->level->getPlayers());
        $range = 5;
        $particle = new HugeExplodeSeedParticle($this);
        for ($i = 0; $i < 6; $i++) {
            $minus = mt_rand(0, 1) ? 1 : -1;
            $particle->add($minus * (new Random())->nextFloat(), -1 * $minus * (new Random())->nextFloat(), (new Random())->nextFloat());
            $this->level->addParticle($particle);
        }
        foreach ($this->level->getEntities() as $ent) {
            if ($dis = $ent->distance($this) <= $range) {
                if ($ent instanceof Player && $ent->isSurvival()) {
                    $health = (1 / $dis) * 20;
                    $ent->setHealth(($ent->getHealth() > 20 ? 20 : $ent->getHealth()) - $health);
                }
            }
        }
    }

    protected function useSkillLightning() {
        Server::getInstance()->broadcastMessage(BossWar::$prefix . "보스가 번개 스킬을 사용 했습니다.", $this->level->getPlayers());
        $ent = [];
        foreach ($this->level->getEntities() as $entity) {
            if ($entity->getId() === $this->getId()) {
                continue;
            }
            if ($entity instanceof Player && $entity->isSurvival() && $entity->distance($this) < 5) {
                $ent[] = $entity;
            }
        }
        if (($c = count($ent)) > 3) {
            $c = 3;
        }
        if ($c <= 0) {
            return;
        }
        $random_key = array_rand($ent, $c); //1~3마리 뽑음
        if (!is_array($random_key)) {
            $random_key = [$random_key];
        }
        foreach ($random_key as $key) {
            /** @var Entity $entity */
            $entity = $ent[$key];

            $pk = new AddEntityPacket();
            $eid[] = $pk->entityRuntimeId = Entity::$entityCount++;
            $pk->position = $entity;
            $pk->type = self::LIGHTNING_BOLT;

            foreach ($this->level->getPlayers() as $p) {
                $p->dataPacket($pk);
            }

            $entity->setHealth(0);
        }
        $pk = new AddEntityPacket();
        $pk->entityRuntimeId = Entity::$entityCount++;
        $pk->position = $this;
        $pk->type = self::LIGHTNING_BOLT;

        foreach ($this->level->getPlayers() as $p) {
            $p->dataPacket($pk);
        }

        $spk = new PlaySoundPacket();
        $spk->soundName = "ambient.weather.lightning.impact";
        $spk->x = $this->getX();
        $spk->y = $this->getY();
        $spk->z = $this->getZ();
        $spk->volume = 500;
        $spk->pitch = 1;

        foreach ($this->level->getPlayers() as $p) {
            $p->dataPacket($spk);
        }
    }

    public function getMaxHealth(): int {
        return 10000;
    }

    public function getName(): string {
        return "BossEntity";
    }

    private $damagers = [];

    public function resetDamage() {
        $this->damagers = [];
    }

    public function attack(EntityDamageEvent $source) {
        $result = parent::attack($source);

        if (!$source instanceof EntityDamageByEntityEvent || !$source->getDamager() instanceof Player) {
            return $result;
        }
        if ($source->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
            if (!isset($this->damagers[$source->getDamager()->getName()])) {
                $this->damagers[$source->getDamager()->getName()] = 0;
            }
            $this->damagers[$source->getDamager()->getName()] += $source->getDamage();
        }else{
            $source->setCancelled(true);
            $this->teleport(BossWar::getInstance()->getBossSpawn());
        }

        return $result;
    }

    public function getDrops(): array {
        return [];
    }

    public function kill(bool $plugin = false) {
        if ($plugin) {
            $this->resetDamage();
        }
        parent::kill();
    }

    protected function onDeath() {
        $all = [];
        foreach ($this->damagers as $damager => $damage) {
            if (Server::getInstance()->getPlayerExact($damager) instanceof Player) {
				$all[$damager] = $damage;
            }
        }
        arsort($all);
	$all = array_keys($all);
        if (empty($all)) {
            parent::onDeath();
            return;
        }
        $magmacream = ItemFactory::get(ItemIds::MAGMA_CREAM, 0);
        $player = Server::getInstance()->getPlayerExact($all[0]);
        if ($player instanceof Player) {
            $player->sendMessage(BossWar::$prefix . "보스를 처치하여 1등 보상으로 §a복주머니 3개§f를 받았습니다.");
            $player->getInventory()->addItem($magmacream->setCount(3));
        }

        $player = Server::getInstance()->getPlayerExact($all[1] ?? '');
        if ($player instanceof Player) {
            $player->sendMessage(BossWar::$prefix . "보스를 처치하여 2등 보상으로 §a복주머니 2개§f를 받았습니다.");
            $player->getInventory()->addItem($magmacream->setCount(2));
        }

        $player = Server::getInstance()->getPlayerExact($all[2] ?? '');
        if ($player instanceof Player) {
            $player->sendMessage(BossWar::$prefix . "보스를 처치히여 3등 보상으로 §a복주머니 1개§f를 받았습니다.");
            $player->getInventory()->addItem($magmacream->setCount(1));
        }

        foreach ($all as $rank => $name) {
            if ($rank < 3) {
                continue;
            }

            $player = Server::getInstance()->getPlayerExact($name);
            $player->sendMessage(BossWar::$prefix . "보스를 처치하여 참여 보상으로 §a게임머니 10,000원§f을 받았습니다.");
        }

        Server::getInstance()->broadcastMessage(BossWar::$prefix . "보스가 §a처치§f되었습니다!");
        Server::getInstance()->broadcastMessage(BossWar::$prefix . "20분 뒤 보스가 소환됩니다!");
        Server::getInstance()->getScheduler()->scheduleDelayedTask(new BossSpawnTask(BossWar::getInstance(), BossSpawnTask::STEP_3), 20 * 60 * 10);
        parent::onDeath();
    }

    public function spawnTo(Player $player) {
        parent::spawnTo($player);
        $this->propertyManager->setFloat(self::DATA_SCALE, 3);
    }

}
