<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\gorufus\object\query\PlayerState;
use bitrule\quark\object\GrantsInfo;
use bitrule\quark\Quark;
use bitrule\quark\service\GrantsService;
use bitrule\services\response\EmptyResponse;
use bitrule\services\Service;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\player\XboxLivePlayerInfo;

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

        $xuid = $playerInfo->getXuid();

        GrantsService::getInstance()->lookup(
            $xuid,
            'xuid',
            PlayerState::ONLINE,
            function (GrantsInfo $grantsInfo): void {
                GrantsService::getInstance()->cache($grantsInfo);
            },
            function (EmptyResponse $emptyResponse) use ($xuid): void {
                if ($emptyResponse->getMessage() === Service::PLAYER_NOT_FOUND_RESPONSE) return;

                Quark::getInstance()->getLogger()->error('An error occurred while requesting grants for player ' . $xuid);
                Quark::getInstance()->getLogger()->error('Exception: ' . $emptyResponse->getMessage());

                Service::getInstance()->addFailedRequest($xuid);
            }
        );
    }
}