<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\quark\Quark;
use bitrule\quark\service\GrantsService;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\utils\TextFormat;

final class PlayerLoginListener implements Listener {

    /**
     * @param PlayerLoginEvent $ev
     *
     * @priority NORMAL
     */
    public function onPlayerLoginEvent(PlayerLoginEvent $ev): void {
        $player = $ev->getPlayer();
        if (GrantsService::getInstance()->hasFailedRequest($player->getXuid())) {
            $ev->setKickMessage(TextFormat::RED . 'Failed to load your grants, please try again later');
            $ev->cancel();

            GrantsService::getInstance()->removeFailedRequest($player->getXuid());

            return;
        }

        if (GrantsService::getInstance()->getGrantsInfo($player->getXuid()) !== null) return;

        Quark::getInstance()->getLogger()->warning('Oups! Our system is not working properly, it takes too long to load grants for ' . $player->getName());
    }
}