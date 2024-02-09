<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance;

class SchemaColumn
{
    public const TYPE_VARCHAR = 'varchar';
    public const TYPE_TEXT = 'text';
    public const TYPE_INT = 'int';
    public const TYPE_TINYINT = 'tinyint';
    public const TYPE_SMALLINT = 'smallint';
    public const TYPE_MEDIUMINT = 'mediumint';
    public const TYPE_BIGINT = 'bigint';

    private string  $name;
    private string $type;
    private ?int $size = null;
    private string|int|float|null|false $default = false;
    private bool $auto = false;
    private bool $null = false;
    private bool $unsigned = false;

    public function __construct(
        string $name,
        string $type,
        ?int $size = null,
        bool $unsigned = false,
        bool $null = false,
        string|int|float|null|false $default = false,
        bool $auto = false
        )
    {
        $this->name = $name;
        $this->type = $type;
        $this->size = $size;
        $this->unsigned = $unsigned;
        $this->null = $null;
        $this->default = $default;
        $this->auto = $auto;
    }

    public function getName(): string
    {
        return $this->name;
    }
    public function getType(): ?string
    {
        return $this->type;
    }
    public function getSize(): ?int
    {
        return $this->size;
    }
    public function getUnsigned(): bool
    {
        return $this->unsigned;
    }
    public function getNullable(): bool
    {
        return $this->null;
    }
    public function getAutoIncrement(): bool
    {
        return $this->auto;
    }
    public function getDefault(): string|int|float|null|false
    {
        return $this->default;
    }
}