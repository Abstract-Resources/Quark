<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\quark\object\QuarkChatFormatter;
use bitrule\quark\service\GrantsService;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use RuntimeException;

final class PlayerChatListener implements Listener {

    /**
     * @param PlayerChatEvent $ev
     *
     * @priority NORMAL
     */
    public function onPlayerChatEvent(PlayerChatEvent $ev): void {
        $player = $ev->getPlayer();
        if (!$player->isOnline()) {
            throw new RuntimeException('Player is not online');
        }

        $grantsInfo = GrantsService::getInstance()->getGrantsInfo($player->getXuid());
        if ($grantsInfo === null) {
            $ev->cancel();

            return;
        }

        $ev->setFormatter(new QuarkChatFormatter($grantsInfo));
    }
}