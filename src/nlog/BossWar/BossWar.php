<?php

namespace nlog\BossWar;

use nlog\BossWar\commands\SelectBossSpawnCommand;
use nlog\BossWar\tasks\BossSpawnTask;
use nlog\BossWar\tasks\UIRegisterTask;
use nlog\BossWar\ui\EnterBossWarUI;
use nlog\SmartUI\SmartUI;
use pocketmine\entity\Entity;
use pocketmine\event\level\ChunkUnloadEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Server;
use pocketmine\utils\Config;

class BossWar extends PluginBase implements Listener {

    /** @var null|BossWar */
    private static $instance = null;

    public static function getInstance(): ?BossWar {
        return self::$instance;
    }

    /** @var string */
    public static $prefix = "§d[ §f레이드 §d] §f";

    /** @var Config */
    public $data;

    /** @var Entity|null */
    public $Boss = null;

    /** @var Position */
    private $BossSpawn;

    /**
     * @param Position $BossSpawn
     * @return Position
     */
    public function setBossSpawn(Position $BossSpawn): Position {
        $this->BossSpawn = $BossSpawn;
        return $this->BossSpawn;
    }

    /**
     * @return Position
     */
    public function getBossSpawn(): Position {
        if ($this->BossSpawn->level === null) {
            $this->BossSpawn = Position::fromObject($this->BossSpawn->asVector3(), Server::getInstance()->getLevelByName($this->data->get('boss-spawn', 'boss')));
        }
        return $this->BossSpawn;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        Entity::registerEntity(BossEntity::class, true);

        $this->data = new Config($this->getDataFolder() . "data.yml", Config::YAML, ['boss-spawn' => '0:0:0:boss']);
        $e = explode(":", $this->data->get('boss-spawn', '0:0:0:boss'));
        $level = $this->getServer()->getLevelByName($e[3]);
        if (!$level instanceof Level) {
            $this->getServer()->loadLevel($e[3]);
        }
        $this->BossSpawn = new Position((float) $e[0], (float) $e[1], (float) $e[2], $this->getServer()->getLevelByName($e[3]));

        $this->getScheduler()->scheduleDelayedTask(new BossSpawnTask($this, BossSpawnTask::STEP_2), 20 * 60 * 5);
        $this->getServer()->broadcastMessage(self::$prefix . "10분 뒤 보스가 소환됩니다!");

        $this->getServer()->getCommandMap()->register('boss', new SelectBossSpawnCommand($this));
    }

    public function onDisable() {
        $name = ":boss";
        $this->getBossSpawn();
        if ($this->BossSpawn->level !== null) {
            $name = ":" . $this->BossSpawn->level->getFolderName();
			
			
            foreach ($this->getServer()->getLevels() as $level) {
                foreach ($level->getEntities() as $target) {
                    if ($target instanceof BossEntity) {
                        $target->kill(true);
                        $level->removeEntity($target);
                    }
                }
            }
        }
        $this->data->set('boss-spawn', $this->BossSpawn->x . ":" . $this->BossSpawn->y . ":" . $this->BossSpawn->z . $name);
        $this->data->save();
    }

    public function onUnloadChunk(ChunkUnloadEvent $ev) {
        if ($ev->getLevel()->getFolderName() === 'boss') {
            $ev->setCancelled(true);
        }
    }

    public function spawnBoss() {
        /*if ($this->Boss instanceof Entity) {
            return false;
        }*/
        $this->getBossSpawn();
        if ($this->BossSpawn->getLevel() !== null) {
            $loadedChunk = true;
            if (!$this->BossSpawn->level->isChunkLoaded($this->BossSpawn->x, $this->BossSpawn->z) && count($this->getServer()->getOnlinePlayers()) < 1) {
                $loadedChunk = false;
            }
            $this->BossSpawn->level->loadChunk($this->BossSpawn->x, $this->BossSpawn->z);
            $entity = Entity::createEntity("BossEntity", $this->BossSpawn->level, Entity::createBaseNBT($this->BossSpawn));
            if ($entity !== null) {
                //$this->Boss = $entity;
                $entity->spawnToAll();
                if (!$loadedChunk) {
                    $entity->kill();
                    $this->BossSpawn->level->removeEntity($entity);
                }
                return true;
            }
        }
        return false;
    }
}
