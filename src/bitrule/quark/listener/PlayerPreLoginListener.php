<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\quark\registry\GrantRegistry;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\utils\TextFormat;

final class PlayerPreLoginListener implements Listener {

    /**
     * @param PlayerPreLoginEvent $ev
     *
     * @priority MONITOR
     */
    public function onPlayerPreLoginEvent(PlayerPreLoginEvent $ev): void {
        $playerInfo = $ev->getPlayerInfo();
        if (!$playerInfo instanceof XboxLivePlayerInfo) {
            throw new \RuntimeException('PlayerInfo is not XboxLivePlayerInfo');
        }

        if (!$ev->isAllowed()) return;

        if (GrantRegistry::getInstance()->fetchByXuid($playerInfo->getXuid())) return;

        $ev->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_PLUGIN, TextFormat::RED . 'Failed to fetch data from the API');
    }
}