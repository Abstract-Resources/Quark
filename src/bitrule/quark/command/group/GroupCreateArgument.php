<?php

declare(strict_types=1);

namespace bitrule\quark\command\group;

use abstractplugin\command\Argument;
use bitrule\quark\object\group\Group;
use bitrule\quark\Pong;
use bitrule\quark\Quark;
use bitrule\quark\registry\GroupRegistry;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

final class GroupCreateArgument extends Argument {

    /**
     * @param CommandSender $sender
     * @param string        $commandLabel
     * @param array         $args
     */
    public function onConsoleExecute(CommandSender $sender, string $commandLabel, array $args): void {
        $name = array_shift($args);
        if (!is_string($name)) {
            $sender->sendMessage(TextFormat::RED . 'Name must be a string');

            return;
        }

        if (GroupRegistry::getInstance()->getGroupByName($name) !== null) {
            $sender->sendMessage(Quark::prefix() . TextFormat::RED . 'Group ' . $name . ' already exists');

            return;
        }

        GroupRegistry::getInstance()
            ->postCreate($group = new Group(Uuid::uuid4()->toString(), $name))
            ->onCompletion(
                function (Pong $pong) use ($group, $sender, $name): void {
                    if ($pong->getStatusCode() !== Quark::CODE_OK) {
                        $sender->sendMessage(Quark::prefix() . TextFormat::RED . 'Failed to create group ' . $name . ' (status ' . $pong->getStatusCode() . ')');

                        return;
                    }

                    $sender->sendMessage(Quark::prefix() . TextFormat::GREEN . 'Group ' . $name . ' created in ' . round($pong->getResponseTimestamp() - $pong->getInitialTimestamp(), 2) . 'ms');

                    GroupRegistry::getInstance()->registerNewGroup($group);
                },
                fn() => $sender->sendMessage(Quark::prefix() . TextFormat::RED . 'Failed to create group ' . $name)
            );
    }
}