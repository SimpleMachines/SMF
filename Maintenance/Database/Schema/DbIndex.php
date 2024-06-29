<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema;

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
	 * Values should be the names of columns, optionally with an index length
	 * appended in parentheses.
	 *
	 * Example: ['id_msg', 'member_groups(48)']
	 */
	public array $columns = [];

	/**
	 * @var ?string
	 *
	 * Allowed values: 'primary', 'unique', or null for a normal index.
	 */
	public ?string $type;

	/**
	 * @var ?string
	 *
	 * The name of the index.
	 */
	public ?string $name;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $columns Columns to include in the index.
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
		$this->columns = array_map('strtolower', $columns);

		$this->type = isset($type) ? strtolower((string) $type) : null;

		if (($this->type ?? null) !== 'primary') {
			$this->name = $name ?? 'idx_' . trim(implode('_', preg_replace(['/\s*/', '/\(\d+\)/'], ['', ''], $this->columns)));
		}
	}
}

?>