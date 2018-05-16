<?php
/**
 * Created by PhpStorm.
 * User: NLOG
 * Date: 2018-04-28
 * Time: 오후 2:55
 */

namespace nlog\BossWar\commands;


use nlog\BossWar\BossWar;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;

class SelectBossSpawnCommand extends PluginCommand {

    public function __construct(BossWar $owner) {
        parent::__construct("보스스폰", $owner);
        $this->setDescription("보스 스폰 지역을 설정합니다.");
        $this->setPermission('op');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if (!$sender instanceof Player) {
            $sender->sendMessage(BossWar::$prefix . "인게임 내에서 실행하세요");
            return true;
        }
        if (!$sender->isOp()) {
            $sender->sendMessage(BossWar::$prefix . "권한이 부족합니다.");
            return true;
        }
        $this->getPlugin()->setBossSpawn($sender);
        $sender->sendMessage(BossWar::$prefix . "보스 스폰 지역을 바꿨습니다.");
        return true;
    }

}