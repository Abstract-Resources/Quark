<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\quark\Quark;
use bitrule\quark\registry\GrantRegistry;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class PlayerJoinListener implements Listener {

    /**
     * @param PlayerJoinEvent $ev
     *
     * @priority MONITOR
     */
    public function onPlayerJoinEvent(PlayerJoinEvent $ev): void {
        $player = $ev->getPlayer();
        if (!$player->isOnline()) {
            throw new RuntimeException('Player is not online');
        }

        $localStorage = GrantRegistry::getInstance()->getLocalStorage($player->getXuid());
        if ($localStorage !== null) return;

        $player->setNoClientPredictions();

        Quark::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($player): void {
                $player->kick(TextFormat::RED . 'You are not authorized to join this server');
                $player->setNoClientPredictions(false);
            }),
            20
        );
    }
}