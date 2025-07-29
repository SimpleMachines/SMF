<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Db\Schema;

use SMF\Db\DatabaseApi as Db;

/**
 * Represents a generated column in a database table.
 *
 * @see https://dev.mysql.com/doc/refman/en/create-table-generated-columns.html
 * @see https://www.postgresql.org/docs/current/ddl-generated-columns.html
 */
class GeneratedColumn extends Column
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The expression used to generate the value.
	 */
	public string $generation_expression;

	/**
	 * @var bool
	 *
	 * Whether the generated value should be stored.
	 *
	 * Will always be true for PostgreSQL databases, because PostgreSQL does not
	 * support virtual generated columns.
	 */
	public bool $stored;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $name Name of the column.
	 * @param string $type Data type of the column.
	 * @param string $generation_expression The expression used to generate the
	 *    value.
	 * @param bool $stored Whether the generated value should be stored.
	 *    For PostgreSQL databases, this value will always be forced to true.
	 * @param ?int $size Size of the column.
	 *    Only applicable to some data types.
	 * @param ?bool $unsigned Whether the column uses unsigned numerical values.
	 *    Only used by MySQL.
	 * @param ?string $charset The character set for string data.
	 *    Only applicable to string types. If null, will be set automatically.
	 */
	public function __construct(
		string $name,
		string $type,
		string $generation_expression,
		bool $stored,
		?int $size = null,
		?bool $unsigned = null,
		?string $charset = null,
	) {
		parent::__construct(
			name: $name,
			type: $type,
			size: $size,
			unsigned: $unsigned,
			charset: $charset,
		);

		$this->generation_expression = $generation_expression;
		$this->stored = Db::$db->title === POSTGRE_TITLE ? true : $stored;
	}
}
