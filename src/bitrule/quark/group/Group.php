<?php

declare(strict_types=1);

namespace bitrule\quark\group;

final class Group {

    /**
     * @param string      $id
     * @param string      $name
     * @param string|null $displayName
     * @param string|null $prefix
     * @param string|null $suffix
     * @param string|null $color
     */
    public function __construct(
        private readonly string $id,
        private string $name,
        private int $priority,
        private ?string $displayName,
        private ?string $prefix,
        private ?string $suffix,
        private ?string $color,
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
    public function getName(): string {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getPriority(): int {
        return $this->priority;
    }

    /**
     * @param int $priority
     */
    public function setPriority(int $priority): void {
        $this->priority = $priority;
    }

    /**
     * @return string|null
     */
    public function getDisplayName(): ?string {
        return $this->displayName;
    }

    /**
     * @param string|null $displayName
     */
    public function setDisplayName(?string $displayName): void {
        $this->displayName = $displayName;
    }

    /**
     * @return string|null
     */
    public function getPrefix(): ?string {
        return $this->prefix;
    }

    /**
     * @param string|null $prefix
     */
    public function setPrefix(?string $prefix): void {
        $this->prefix = $prefix;
    }

    /**
     * @return string|null
     */
    public function getSuffix(): ?string {
        return $this->suffix;
    }

    /**
     * @param string|null $suffix
     */
    public function setSuffix(?string $suffix): void {
        $this->suffix = $suffix;
    }

    /**
     * @return string|null
     */
    public function getColor(): ?string {
        return $this->color;
    }

    /**
     * @param string|null $color
     */
    public function setColor(?string $color): void {
        $this->color = $color;
    }
}