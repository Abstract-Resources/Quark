<?php

declare(strict_types=1);

namespace bitrule\quark\command\group;

use abstractplugin\command\Argument;
use bitrule\quark\Pong;
use bitrule\quark\Quark;
use bitrule\quark\registry\GroupRegistry;
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

        $group = GroupRegistry::getInstance()->getGroupByName($groupName);
        if ($group === null) {
            $sender->sendMessage(TextFormat::RED . 'Group ' . $groupName . ' does not exist');

            return;
        }

        $group->setPriority((int) $priority);

        GroupRegistry::getInstance()
            ->postCreate($group)
            ->onCompletion(
                function (Pong $pong) use ($sender, $groupName, $priority): void {
                    $sender->sendMessage(Quark::prefix() . TextFormat::GREEN . 'Group ' . $groupName . ' priority set to ' . $priority . ' in ' . round($pong->getResponseTimestamp() - $pong->getInitialTimestamp(), 2) . 'ms');
                },
                fn() => $sender->sendMessage(TextFormat::RED . 'Failed to set group ' . $groupName . ' priority')
            );
    }
}