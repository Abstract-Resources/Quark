<?php

declare(strict_types=1);

namespace bitrule\quark\object\grant;

final class GrantData {

    /**
     * @param string $id
     * @param string $groupId
     * @param string $createdAt
     * @param string $expiresAt
     * @param string $revokedAt
     * @param string $whoGranted
     * @param string $whoRevoked
     */
    public function __construct(
        private readonly string $id,
        private readonly string $groupId,
        private readonly string $createdAt,
        private readonly string $expiresAt,
        private string $revokedAt,
        private readonly string $whoGranted,
        private string $whoRevoked,
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
     * @return string
     */
    public function getExpiresAt(): string {
        return $this->expiresAt;
    }

    /**
     * @return string
     */
    public function getRevokedAt(): string {
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
     * @return string
     */
    public function getWhoRevoked(): string {
        return $this->whoRevoked;
    }

    /**
     * @param string $whoRevoked
     */
    public function setWhoRevoked(string $whoRevoked): void {
        $this->whoRevoked = $whoRevoked;
    }

    public static function wrap(array $data): GrantData {
        return new GrantData(
            $data['id'],
            $data['group_id'],
            $data['created_at'],
            $data['expires_at'],
            $data['revoked_at'],
            $data['who_granted'],
            $data['who_revoked']
        );
    }
}