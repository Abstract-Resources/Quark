<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\quark\service\GrantsService;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;

final class PlayerQuitListener implements Listener {

    /**
     * @param PlayerQuitEvent $ev
     *
     * @priority NORMAL
     */
    public function onPlayerQuitEvent(PlayerQuitEvent $ev): void {
        GrantsService::getInstance()->unload($ev->getPlayer()->getXuid());
    }
}