<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\quark\registry\LocalStorageRegistry;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat;

final class PlayerJoinListener implements Listener {

    /**
     * @param PlayerJoinEvent $ev
     *
     * @priority MONITOR
     */
    public function onPlayerJoinEvent(PlayerJoinEvent $ev): void {
        $player = $ev->getPlayer();
        if (!$player->isOnline()) {
            throw new \RuntimeException('Player is not online');
        }

        $localStorage = LocalStorageRegistry::getInstance()->getLocalStorage($player->getXuid());
        if ($localStorage === null && !LocalStorageRegistry::getInstance()->hasPendingRequest($player->getXuid())) {
            $player->kick(TextFormat::RED . 'Failed to fetch data from the API');

            return;
        }

        if ($localStorage !== null) return;

        $player->sendMessage(TextFormat::GREEN . 'Your data is being fetched from the API, please wait');
    }
}