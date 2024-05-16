<?php

declare(strict_types=1);

namespace bitrule\quark\command\group;

use abstractplugin\command\Argument;
use bitrule\quark\object\group\Group;
use bitrule\quark\Quark;
use bitrule\quark\service\GroupService;
use bitrule\quark\service\response\EmptyResponse;
use bitrule\quark\service\response\GroupCreateResponse;
use bitrule\quark\service\response\PongResponse;
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

        if (GroupService::getInstance()->getGroupByName($name) !== null) {
            $sender->sendMessage(Quark::prefix() . TextFormat::RED . 'Group ' . $name . ' already exists');

            return;
        }

        GroupService::getInstance()->postCreate(
            $group = new Group(Uuid::uuid4()->toString(), $name),
            function (PongResponse $pong) use ($group, $sender): void {
                $sender->sendMessage(Quark::prefix() . TextFormat::GREEN . 'Group ' . $group->getName() . ' created in ' . round($pong->getResponseTimestamp() - $pong->getInitialTimestamp(), 2) . 'ms');

                GroupService::getInstance()->registerNewGroup($group);
            },
            function (EmptyResponse $response) use ($sender): void {
                $sender->sendMessage(Quark::prefix() . $response->getMessage());

                Quark::getInstance()->getLogger()->error('[Status Code: ' . $response->getStatusCode() . '] => ' . $response->getMessage());
            }
        );
    }
}