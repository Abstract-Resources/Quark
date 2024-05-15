<?php

declare(strict_types=1);

namespace bitrule\quark\object;

use bitrule\quark\object\grant\GrantData;
use bitrule\quark\object\group\Group;
use bitrule\quark\registry\GroupRegistry;

final class LocalStorage {

    /**
     * @param string      $xuid
     * @param string      $state
     * @param GrantData[] $activeGrants
     * @param GrantData[] $expiredGrants
     */
    public function __construct(
        private readonly string $xuid,
        private readonly string $state,
        private array $activeGrants = [],
        private array $expiredGrants = []
    ) {}

    /**
     * @return string
     */
    public function getXuid(): string {
        return $this->xuid;
    }

    /**
     * @return string
     */
    public function getState(): string {
        return $this->state;
    }

    /**
     * @return array
     */
    public function getActiveGrants(): array {
        return $this->activeGrants;
    }

    /**
     * @param GrantData $grantData
     */
    public function addActiveGrant(GrantData $grantData): void {
        $this->activeGrants[] = $grantData;
    }

    /**
     * @return array
     */
    public function getExpiredGrants(): array {
        return $this->expiredGrants;
    }

    /**
     * @param GrantData $grantData
     */
    public function triggerUpdateGrant(GrantData $grantData): void {
        foreach ($this->activeGrants as $index => $grant) {
            if ($grant->getId() !== $grantData->getId()) continue;

            $this->activeGrants[$index] = $grantData;
        }

        $this->expiredGrants[] = $grantData;
    }

    /**
     * @return GrantData|null
     */
    public function getHighestGrant(): ?GrantData {
        /** @var GrantData|null $highestGrant */
        $highestGrant = null;
        foreach ($this->activeGrants as $index => $grant) {
            if ($highestGrant === null) {
                $highestGrant = $grant;

                continue;
            }

            $highestGroup = GroupRegistry::getInstance()->getGroupById($highestGrant->getGroupId());
            if ($highestGroup === null) continue;

            $group = GroupRegistry::getInstance()->getGroupById($grant->getGroupId());
            if ($group === null) {
                unset($this->activeGrants[$index]);

                continue;
            }

            if ($group->getPriority() <= $highestGroup->getPriority()) continue;

            $highestGrant = $grant;
        }

        return $highestGrant;
    }

    /**
     * Find the highest group
     *
     * @return Group|null
     */
    public function getHighestGroup(): ?Group {
        $highestGrant = $this->getHighestGrant();
        if ($highestGrant === null) return null;

        return GroupRegistry::getInstance()->getGroupById($highestGrant->getGroupId());

    }
}