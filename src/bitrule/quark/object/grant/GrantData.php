<?php

declare(strict_types=1);

namespace bitrule\quark\object\grant;

use bitrule\quark\Quark;
use Ramsey\Uuid\Uuid;

final class GrantData {

    /**
     * @param string      $id
     * @param string      $groupId
     * @param string      $createdAt
     * @param string|null $expiresAt
     * @param string|null $revokedAt
     * @param string      $whoGranted
     * @param string|null $whoRevoked
     * @param string[]       $scopes
     */
    public function __construct(
        private readonly string $id,
        private readonly string $groupId,
        private readonly string $createdAt,
        private readonly ?string $expiresAt,
        private ?string $revokedAt,
        private readonly string $whoGranted,
        private ?string $whoRevoked,
        private array $scopes = []
    ) {}

    /**
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getGroupId(): string {
        return $this->groupId;
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string {
        return $this->createdAt;
    }

    /**
     * @return string|null
     */
    public function getExpiresAt(): ?string {
        return $this->expiresAt;
    }

    /**
     * @return string|null
     */
    public function getRevokedAt(): ?string {
        return $this->revokedAt;
    }

    /**
     * @param string $revokedAt
     */
    public function setRevokedAt(string $revokedAt): void {
        $this->revokedAt = $revokedAt;
    }

    /**
     * @return string
     */
    public function getWhoGranted(): string {
        return $this->whoGranted;
    }

    /**
     * @return string|null
     */
    public function getWhoRevoked(): ?string {
        return $this->whoRevoked;
    }

    /**
     * @param string $whoRevoked
     */
    public function setWhoRevoked(string $whoRevoked): void {
        $this->whoRevoked = $whoRevoked;
    }

    /**
     * @return array
     */
    public function getScopes(): array {
        return $this->scopes;
    }

    /**
     * @param string $scope
     */
    public function addScope(string $scope): void {
        $this->scopes[] = $scope;
    }

    /**
     * @param string $scope
     *
     * @return bool
     */
    public function hasScope(string $scope): bool {
        $split = self::refactor($scope);
        if (count($split) === 0) return false;

        foreach ($this->scopes as $grantScope) {
            $grantSplit = self::refactor($grantScope);
            if (count($grantSplit) === 0) continue;

            foreach ($split as $splitScope) {
                if (in_array($splitScope, $grantSplit, true)) return true;
            }
        }

        return false;
    }

    private static function refactor(string $scope): array {
        return explode(',', str_replace(['g=', 's=', ' '], '', $scope));
    }

    /**
     * @param string|null $expiresAt
     * @param string      $groupId
     * @param string      $whoGranted
     *
     * @return GrantData
     */
    public static function empty(?string $expiresAt, string $groupId, string $whoGranted): GrantData {
        return new self (
            Uuid::uuid4()->toString(),
            $groupId,
            Quark::now(),
            $expiresAt,
            null,
            $whoGranted,
            null,
            []
        );
    }

    /**
     * Wraps the data into a GrantData object
     *
     * @param array $data
     *
     * @return GrantData
     */
    public static function wrap(array $data): GrantData {
        return new GrantData(
            $data['_id'],
            $data['group_id'],
            $data['created_at'],
            $data['expires_at'] ?? null,
            $data['revoked_at'] ?? null,
            $data['who_granted'],
            $data['who_revoked'] ?? null,
            $data['scopes'] ?? []
        );
    }
}