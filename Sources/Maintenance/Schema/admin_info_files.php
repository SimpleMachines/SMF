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

namespace SMF\Maintenance\Schema;

use SMF\Maintenance\{Schema, SchemaBase, SchemaInterface, SchemaColumn, SchemaIndex, SchemaIndexColumn};

class admin_info_files extends SchemaBase implements SchemaInterface
{
    public function __construct()
    {
        $this->name = 'admin_info_files';
        $this->columns = [
            new SchemaColumn(
                name: 'id_file',
                type: SchemaColumn::TYPE_TINYINT,
                unsigned: true,
                auto: true
            ),
            new SchemaColumn(
                name: 'filename',
                type: SchemaColumn::TYPE_VARCHAR,
                size: 255,
                null: false,
                default: ''
            ),
            new SchemaColumn(
                name: 'path',
                type: SchemaColumn::TYPE_VARCHAR,
                size: 255,
                null: false,
                default: ''
            ),
            new SchemaColumn(
                name: 'parameters',
                type: SchemaColumn::TYPE_VARCHAR,
                size: 255,
                null: false,
                default: ''
            ),
            new SchemaColumn(
                name: 'data',
                type: SchemaColumn::TYPE_TEXT,
                null: false,
            ),
            new SchemaColumn(
                name: 'filetype',
                type: SchemaColumn::TYPE_VARCHAR,
                size: 255,
                null: false,
                default: ''
            ),
        ];

        $this->indexes = [
            new SchemaIndex(
                name: 'PRIMARY',
                type: SchemaIndex::TYPE_PRIMARY,
                columns: [
                    new SchemaIndexColumn('id_file')
                ]
            )
        ];
    }
}