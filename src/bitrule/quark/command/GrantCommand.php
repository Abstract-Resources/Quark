<?php

declare(strict_types=1);

namespace bitrule\quark\command;

use bitrule\quark\object\grant\GrantData;
use bitrule\quark\object\GrantsInfo;
use bitrule\quark\Quark;
use bitrule\quark\service\GrantsService;
use bitrule\quark\service\GroupService;
use bitrule\quark\service\response\EmptyResponse;
use bitrule\quark\service\response\PongResponse;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class GrantCommand extends Command {

    public function __construct() {
        parent::__construct('grant', 'Grant a player a group', '/grant <player> <group> [1d/1w/1m/1y]');
        $this->setPermission('quark.command.grant');
    }

    /**
     * @param CommandSender $sender
     * @param string        $commandLabel
     * @param string[]         $args
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$this->testPermission($sender)) return;

        if (count($args) < 2) {
            $sender->sendMessage(TextFormat::RED . 'Usage: /grant <player> <group> [1d/1w/1m/1y]');

            return;
        }

        $group = GroupService::getInstance()->getGroupByName($args[1]);
        if ($group === null) {
            $sender->sendMessage(TextFormat::RED . 'Group not found');

            return;
        }

        $lastArg = $args[count($args) - 1] ?? null;
        if ($lastArg === null) {
            $sender->sendMessage(TextFormat::RED . 'Invalid duration');

            return;
        }

        $scope = null;
        if (str_starts_with($lastArg, 'g=') || str_starts_with($lastArg, 's=')) {
            $scope = $lastArg;
        }

        $timestamp = isset($args[2]) ? Quark::parseFromInput($args[2]) : null;

        GrantsService::getInstance()->requestGrants(
            GrantsService::createQueryByName($args[0], false, 'active'),
            function (GrantsInfo $grantsInfo) use ($timestamp, $scope, $sender, $group): void {
                $activeGrantData = $grantsInfo->getActiveGrantByGroup($group->getId());
                if ($scope !== null && $activeGrantData !== null && $activeGrantData->hasScope($scope)) {
                    $sender->sendMessage(TextFormat::RED . $grantsInfo->getKnownName() . ' already has this group!');

                    return;
                }

                if ($activeGrantData === null) {
                    $activeGrantData = GrantData::empty(
                        $timestamp,
                        $group->getId(),
                        $sender instanceof Player ? $sender->getXuid() : '000'
                    );
                }

                if ($scope !== null) $activeGrantData->addScope($scope);

                $grantsInfo->addActiveGrant($activeGrantData);

                GrantsService::getInstance()->postGrant(
                    $grantsInfo,
                    $activeGrantData,
                    function (PongResponse $pong) use ($sender, $grantsInfo, $group): void {
                        $sender->sendMessage(TextFormat::GREEN . 'Granted ' . $grantsInfo->getKnownName() . ' the group ' . $group->getName());
                    },
                    function (EmptyResponse $response) use ($sender): void {
                        $sender->sendMessage(TextFormat::RED . $response->getMessage());
                    }
                );
            },
            function (EmptyResponse $response) use ($sender): void {
                $sender->sendMessage(TextFormat::RED . $response->getMessage());
            }
        );
    }
}