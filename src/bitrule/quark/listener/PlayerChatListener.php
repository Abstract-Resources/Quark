<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\quark\object\QuarkChatFormatter;
use bitrule\quark\registry\GrantRegistry;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;

final class PlayerChatListener implements Listener {

    /**
     * @param PlayerChatEvent $ev
     *
     * @priority NORMAL
     */
    public function onPlayerChatEvent(PlayerChatEvent $ev): void {
        $player = $ev->getPlayer();
        if (!$player->isOnline()) {
            throw new \RuntimeException('Player is not online');
        }

        $localStorage = GrantRegistry::getInstance()->getLocalStorage($player->getXuid());
        if ($localStorage === null) {
            $ev->cancel();

            return;
        }

        $ev->setFormatter(new QuarkChatFormatter($localStorage));
    }
}