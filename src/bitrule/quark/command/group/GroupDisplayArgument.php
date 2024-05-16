<?php

declare(strict_types=1);

namespace bitrule\quark\command\group;

use abstractplugin\command\Argument;
use bitrule\quark\Quark;
use bitrule\quark\service\GroupService;
use bitrule\quark\service\response\EmptyResponse;
use bitrule\quark\service\response\PongResponse;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use function array_shift;
use function is_string;
use function round;

final class GroupDisplayArgument extends Argument {

    /**
     * @param CommandSender $sender
     * @param string        $commandLabel
     * @param array         $args
     */
    public function onConsoleExecute(CommandSender $sender, string $commandLabel, array $args): void {
        $groupName = array_shift($args);
        if (!is_string($groupName)) {
            $sender->sendMessage(TextFormat::RED . 'Group name must be a string');

            return;
        }

        $display = array_shift($args);
        if (!is_string($display)) {
            $sender->sendMessage(TextFormat::RED . 'Display must be a string');

            return;
        }

        $group = GroupService::getInstance()->getGroupByName($groupName);
        if ($group === null) {
            $sender->sendMessage(TextFormat::RED . 'Group ' . $groupName . ' does not exist');

            return;
        }

        $group->setDisplayName($display);

        GroupService::getInstance()->postCreate(
            $group,
            function (PongResponse $pong) use ($group, $sender): void {
                $sender->sendMessage(Quark::prefix() . TextFormat::GREEN . 'The display name of the group ' . $group->getName() . ' has been set in ' . round($pong->getResponseTimestamp() - $pong->getInitialTimestamp(), 2) . 'ms');
            },
            function (EmptyResponse $response) use ($sender): void {
                $sender->sendMessage(Quark::prefix() . $response->getMessage());

                Quark::getInstance()->getLogger()->error('[Status Code: ' . $response->getStatusCode() . '] => ' . $response->getMessage());
            }
        );
    }
}