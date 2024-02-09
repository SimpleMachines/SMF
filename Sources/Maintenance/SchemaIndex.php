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

class SchemaIndex
{
    public const TYPE_PRIMARY = 'primary';
    public const TYPE_UNIQUE = 'unique';
    public const TYPE_KEY = 'KEY';

    private string $name;
    private string $type;
    /** @var \SMF\Maintenance\SchemaIndexColumn[] */
    private array $columns;

    public function __construct(
        string $name,
        string $type,
        array $columns
        )
    {
        $this->name = $name;
        $this->type = $type;
        $this->columns = $columns;
    }

    public function getName(): string
    {
        return $this->name;
    }
    public function getType(): string
    {
        return $this->type;
    }
    /**
     * 
     * @return \SMF\Maintenance\SchemaIndexColumn[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
}