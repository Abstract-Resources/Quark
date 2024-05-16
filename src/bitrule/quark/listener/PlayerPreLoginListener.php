<?php

declare(strict_types=1);

namespace bitrule\quark\listener;

use bitrule\quark\object\GrantsInfo;
use bitrule\quark\service\GrantsService;
use bitrule\quark\service\response\EmptyResponse;
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

        GrantsService::getInstance()->requestGrants(
            GrantsService::createQueryByXuid($xuid, true, 'active'),
            function (GrantsInfo $grantsInfo): void {
                GrantsService::getInstance()->cache($grantsInfo);
            },
            function (EmptyResponse $emptyResponse) use ($xuid): void {
                if ($emptyResponse->getMessage() === GrantsService::PLAYER_NOT_FOUND_RESPONSE) return;

                GrantsService::getInstance()->getFailedRequests()->add($xuid);
            }
        );
    }
}