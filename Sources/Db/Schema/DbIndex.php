<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema;

/**
 * Represents an index in a database table.
 */
class DbIndex
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Columns to include in the index.
	 *
	 * Values are sub-arrays containing 'name' elements and optional 'size'
	 * and/or 'opclass' elements. The 'size' element is used by MySQL, and the
	 * 'opclass' element is used by PostgreSQL.
	 *
	 * Example: [
	 * 		['name' => 'id_msg'],
	 * 		['name' => 'member_groups', 'size' => 48],
	 * ]
	 */
	public array $columns = [];

	/**
	 * @var ?string
	 *
	 * Allowed values: 'primary', 'unique', or null for a normal index.
	 */
	public ?string $type = null;

	/**
	 * @var string
	 *
	 * The name of the index.
	 */
	public string $name;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $columns Columns to include in the index.
	 *    Values can be simple strings or arrays containing a 'name' element and
	 *    optional 'size' or 'opclass' elements. The 'size' element is used by
	 *    MySQL, and the 'opclass' element is used by PostgreSQL.
	 * @param ?string $type The type of index. Either 'primary' for the PRIMARY
	 *    KEY index, 'unique' for a UNIQUE index, or null for a normal index.
	 * @param ?string $name The name of the index. If this is left null, a name
	 *    will be generated automatically.
	 */
	public function __construct(
		array $columns,
		?string $type = null,
		?string $name = null,
	) {
		foreach ($columns as $key => $column) {
			if (\is_string($column)) {
				$this->columns[$key]['name'] = strtolower($column);
			} elseif (\is_array($column) && isset($column['name'])) {
				$column['name'] = strtolower($column['name']);
				$this->columns[$key] = $column;
			}
		}

		$this->type = isset($type) ? strtolower((string) $type) : null;

		if ($this->type !== 'primary') {
			$this->name = $name ?? 'idx_' . trim(implode('_', preg_replace(['/\s*/', '/\(\d+\)/'], ['', ''], $this->columns)));
		} else {
			$this->name = $name ?? 'primary';
		}
	}
}
