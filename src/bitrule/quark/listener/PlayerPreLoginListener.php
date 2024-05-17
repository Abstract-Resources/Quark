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

        GrantsService::getInstance()->requestGrants(
            Service::createQueryByXuid($xuid, PlayerState::ONLINE) . GrantsService::QUERY_TYPE . 'active',
            function (GrantsInfo $grantsInfo): void {
                GrantsService::getInstance()->cache($grantsInfo);
            },
            function (EmptyResponse $emptyResponse) use ($xuid): void {
                if ($emptyResponse->getMessage() === Service::PLAYER_NOT_FOUND_RESPONSE) return;

                GrantsService::getInstance()->addFailedRequest($xuid);

                Quark::getInstance()->getLogger()->error('Failed to load grants for ' . $xuid . ' - ' . date('Y-m-d H:i:s'));
                Quark::getInstance()->getLogger()->error('Message: ' . $emptyResponse->getMessage());
            }
        );
    }
}