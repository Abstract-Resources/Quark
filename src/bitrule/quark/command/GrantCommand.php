<?php

declare(strict_types=1);

namespace bitrule\quark\command;

use bitrule\quark\object\grant\GrantData;
use bitrule\quark\object\LocalStorage;
use bitrule\quark\Quark;
use bitrule\quark\registry\GrantRegistry;
use bitrule\quark\service\GroupService;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

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

        GrantRegistry::getInstance()
            ->fetchByName($args[0])
            ->onCompletion(
                function (LocalStorage $localStorage) use($timestamp, $scope, $sender, $group, $args): void {
                    $originalGrantData = null;
                    foreach ($localStorage->getActiveGrants() as $grantData) {
                        if ($grantData->getGroupId() !== $group->getId()) continue;
                        if ($originalGrantData !== null) {
                            throw new CommandException('Player has multiple grants for the same group');
                        }

                        if ($scope === null || $grantData->hasScope($scope)) {
                            $sender->sendMessage(TextFormat::RED . $args[0] . ' already has this group!');

                            return;
                        }

                        $grantData->addScope($scope);
                        $originalGrantData = $grantData;
                    }

                    if ($originalGrantData === null) {
                        $originalGrantData = new GrantData(
                            Uuid::uuid4()->toString(),
                            $group->getId(),
                            Quark::now(),
                            $timestamp,
                            null,
                            $sender instanceof Player ? $sender->getXuid() : '000',
                            null,
                            $scope !== null ? [$scope] : []
                        );

                        $localStorage->addActiveGrant($originalGrantData);
                    }

                    $sender->sendMessage(TextFormat::GREEN . 'Successfully granted ' . $args[0] . ' the ' . $group->getName() . ' group');

                    GrantRegistry::getInstance()->postGrant(
                        $localStorage->getXuid(),
                        $localStorage->getState(),
                        $originalGrantData
                    );
                },
                fn() => $sender->sendMessage(TextFormat::RED . 'Player not found')
            );
    }
}