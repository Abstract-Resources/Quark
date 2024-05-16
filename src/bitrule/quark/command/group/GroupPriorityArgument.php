<?php

declare(strict_types=1);

namespace bitrule\quark\command\group;

use abstractplugin\command\Argument;
use bitrule\quark\Quark;
use bitrule\quark\service\GroupService;
use bitrule\quark\service\response\EmptyResponse;
use bitrule\quark\service\response\GroupCreateResponse;
use bitrule\quark\service\response\PongResponse;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

final class GroupPriorityArgument extends Argument {

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

        $priority = array_shift($args);
        if (!is_numeric($priority)) {
            $sender->sendMessage(TextFormat::RED . 'Priority must be a number');

            return;
        }

        $group = GroupService::getInstance()->getGroupByName($groupName);
        if ($group === null) {
            $sender->sendMessage(TextFormat::RED . 'Group ' . $groupName . ' does not exist');

            return;
        }

        $group->setPriority((int) $priority);

        GroupService::getInstance()->postCreate(
            $group,
            function (PongResponse $pong) use ($priority, $group, $sender): void {
                $sender->sendMessage(sprintf(
                    Quark::prefix() . TextFormat::colorize('&aThe priority of the group %s has been set to \'&b%s&a\' in %.2fms'),
                    $group->getName(),
                    $priority,
                    round($pong->getResponseTimestamp() - $pong->getInitialTimestamp(), 2)
                ));
            },
            function (EmptyResponse $response) use ($sender): void {
                $sender->sendMessage(Quark::prefix() . $response->getMessage());

                Quark::getInstance()->getLogger()->error('[Status Code: ' . $response->getStatusCode() . '] => ' . $response->getMessage());
            }
        );
    }
}