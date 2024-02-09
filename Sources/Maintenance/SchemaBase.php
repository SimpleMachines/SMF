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

class SchemaBase
{
    protected string $name;

    /** @var \SMF\Maintenance\SchemaColumn[] */
    protected array $columns;

    /** @var \SMF\Maintenance\SchemaIndex[] */
    protected array $indexes;

    public function __construct(string $name, array $columns, ?array $indexes = [])
    {
        $this->name = $name;
        $this->columns = $columns;
        $this->indexes = $indexes;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 
     * @return \SMF\Maintenance\SchemaColumn[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * 
     * @return \SMF\Maintenance\SchemaIndex[]
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getDefaultData(): array
    {
        return [];
    }

    final public function getColumnsForCreateTable(): array
    {
        $columns = [];

        foreach ($this->columns as $col) {
            $rt = [
                'name' => $col->getName(),
                'type' => $col->getType(),

                'auto' => $col->getAutoIncrement(),
                'null' => $col->getNullable(),
                'unsigned' => $col->getUnsigned(),
            ];

            if ($col->getDefault() !== false) {
                $rt['default'] = $col->getDefault();
            }
            if ($col->getSize() !== null) {
                $rt['size'] = $col->getSize();
            }

            $columns[] = $rt;
        }

        return $columns;
    }

    final public function getIndexesForCreateTable(): array
    {
        $indexes = [];

        foreach ($this->indexes as $col) {
            $rt = [
                'name' => $col->getName(),
                'type' => $col->getType(),
                'columns' => [],
            ];

            foreach ($col->getColumns() as $col) {
                if ($col->getSize() !== null) {
                    $rt['columns'][] = [
                        'name' => $col->getName(),
                        'size' => $col->getSize()
                    ];
                } else {
                    $rt['columns'][] = [
                        'name' => $col->getName(),
                    ];
                }
            }

            $indexes[] = $rt;
        }

        return $indexes;
    }
}