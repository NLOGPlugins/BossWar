<?php
/**
 * Created by PhpStorm.
 * User: NLOG
 * Date: 2018-04-28
 * Time: 오후 12:44
 */

namespace nlog\BossWar\tasks;


use nlog\BossWar\BossWar;
use pocketmine\scheduler\PluginTask;

class BossSpawnTask extends PluginTask {

    //3(10분) -> 2(5분) -> 1(1분) -> 0(보스소환)
    const STEP_3 = 3;
    const STEP_2 = 2;
    const STEP_1 = 1;
    const STEP_0 = 0;

    /** @var BossWar */
    protected $owner;

    /** @var int */
    private $step;

    public function __construct(BossWar $owner, int $type = self::STEP_3) {
        parent::__construct($owner);
        $this->step = $type;
    }

    public function onRun(int $currentTick) {
        if ($this->step === self::STEP_3) {
            $this->owner->getServer()->broadcastMessage(BossWar::$prefix . "보스가 10분 뒤 소환됩니다.");
            $this->owner->getServer()->getScheduler()->scheduleDelayedTask(new BossSpawnTask($this->owner, --$this->step), 20 * 60 * 5);
        }elseif ($this->step === self::STEP_2) {
            $this->owner->getServer()->broadcastMessage(BossWar::$prefix . "보스가 5분 뒤 소환됩니다.");
            $this->owner->getServer()->getScheduler()->scheduleDelayedTask(new BossSpawnTask($this->owner, --$this->step), 20 * 60 * 4);
        }elseif ($this->step === self::STEP_1) {
            $this->owner->getServer()->broadcastMessage(BossWar::$prefix . "보스가 잠시 후 소환됩니다.");
            $this->owner->getServer()->getScheduler()->scheduleDelayedTask(new BossSpawnTask($this->owner, --$this->step), 20 * 60 * 1);
        }elseif ($this->step === self::STEP_0 && $this->owner->spawnBoss()) {
            $this->owner->getServer()->broadcastMessage(BossWar::$prefix . "보스가 소환되었습니다! 지금 당장 가서 §a처치 §f하십시오!");
        }
    }

}