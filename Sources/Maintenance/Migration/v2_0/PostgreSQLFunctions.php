<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class PostgreSQLFunctions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating PostgreSQL functions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return Db::$db->title === POSTGRE_TITLE;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Changing inet_aton function to use bigint instead of int...
		$this->query(
			'CREATE OR REPLACE FUNCTION INET_ATON(text) RETURNS bigint AS
				{string:func_text}
			LANGUAGE {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT
					CASE WHEN
						$1 !~ {string:regex} THEN 0
					ELSE
						split_part($1, {string:dot}, 1)::int8 * (256 * 256 * 256) +
						split_part($1, {string:dot}, 2)::int8 * (256 * 256) +
						split_part($1, {string:dot}, 3)::int8 * 256 +
						split_part($1, {string:dot}, 4)::int8
					END AS result',
					[
						'regex' => '^[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?$',
						'dot' => '.',
					],
				),
				'lang' => 'sql',
			],
		);

		// Adding an IFNULL to handle 8-bit integers returned by inet_aton
		$this->query(
			'CREATE OR REPLACE FUNCTION IFNULL(int8, int8) RETURNS int8 AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT COALESCE($1, $2) AS result',
					[],
				),
				'lang' => 'sql',
			],
		);

		// Adding instr()
		$this->query(
			'DROP FUNCTION IF EXISTS INSTR(text, text)',
			[],
		);

		$this->query(
			'CREATE OR REPLACE FUNCTION INSTR(text, text) RETURNS integer AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT POSITION($2 IN $1) AS result',
					[],
				),
				'lang' => 'sql',
			],
		);

		// Adding date_format()
		$this->query(
			'CREATE OR REPLACE FUNCTION DATE_FORMAT(timestamp, text) RETURNS text AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT REPLACE(REPLACE($2, {string:m}, to_char($1, {string:mm})), {string:d}, to_char($1, {string:dd})) AS result',
					[
						'm' => '%m',
						'mm' => 'MM',
						'd' => '%d',
						'dd' => 'DD',
					],
				),
				'lang' => 'sql',
			],
		);

		// Adding day()
		$this->query(
			'CREATE OR REPLACE FUNCTION day(date) RETURNS integer AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT EXTRACT(DAY FROM DATE($1))::integer AS result',
					[],
				),
				'lang' => 'sql',
			],
		);

		// Adding IFNULL(varying, varying)
		$this->query(
			'CREATE OR REPLACE FUNCTION IFNULL (character varying, character varying) RETURNS character varying AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT COALESCE($1, $2) AS result',
					[],
				),
				'lang' => 'sql',
			],
		);

		// Adding IFNULL(varying, bool)
		$this->query(
			'CREATE OR REPLACE FUNCTION IFNULL(character varying, boolean) RETURNS character varying AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT COALESCE($1, CAST(CAST($2 AS int) AS varchar)) AS result',
					[],
				),
				'lang' => 'sql',
			],
		);

		// Adding IFNULL(int, bool)
		$this->query(
			'CREATE OR REPLACE FUNCTION IFNULL(int, boolean) RETURNS int AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT COALESCE($1, CAST($2 AS int)) AS result',
					[],
				),
				'lang' => 'sql',
			],
		);

		// Adding bool_not_eq_int()
		$this->query(
			'CREATE OR REPLACE FUNCTION bool_not_eq_int (boolean, integer) RETURNS boolean AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT CAST($1 AS integer) != $2 AS result',
					[],
				),
				'lang' => 'sql',
			],
		);

		// Creating operator bool_not_eq_int()
		$result = $this->query(
			'SELECT oprname
			FROM pg_operator
			WHERE oprcode = {string:code}::regproc',
			[
				'code' => 'bool_not_eq_int',
			],
		);

		if (Db::$db->num_rows($result) == 0) {
			$this->query(
				'CREATE OPERATOR != (PROCEDURE = bool_not_eq_int, LEFTARG = boolean, RIGHTARG = integer)',
				[],
			);
		}

		// Recreating function FIND_IN_SET()
		$this->query(
			'DROP FUNCTION IF EXISTS FIND_IN_SET(text, text)',
			[],
		);

		$this->query(
			'DROP FUNCTION IF EXISTS FIND_IN_SET(integer, character varying)',
			[],
		);

		$this->query(
			'CREATE OR REPLACE FUNCTION FIND_IN_SET(needle text, haystack text) RETURNS integer AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT i AS result
					FROM generate_series(1, array_upper(string_to_array($2, {string:comma}), 1)) AS g(i)
					WHERE (string_to_array($2, {string:comma}))[i] = $1
						UNION ALL
					SELECT 0
					LIMIT 1',
					[
						'comma' => ',',
					],
				),
				'lang' => 'sql',
			],
		);

		$this->query(
			'CREATE OR REPLACE FUNCTION FIND_IN_SET(needle integer, haystack text) RETURNS integer AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT i AS result
					FROM generate_series(1, array_upper(string_to_array($2, {string:comma}), 1)) AS g(i)
					WHERE (string_to_array($2, {string:comma}))[i] = CAST($1 AS text)
						UNION ALL
					SELECT 0
					LIMIT 1',
					[
						'comma' => ',',
					],
				),
				'lang' => 'sql',
			],
		);

		// Updating TO_DAYS()
		$this->query(
			'CREATE OR REPLACE FUNCTION TO_DAYS (timestamp) RETURNS integer AS
				{string:func_text}
			LANGUAGE  {string:lang}',
			[
				'func_text' => Db::$db->quote(
					'SELECT DATE_PART({string:day}, $1 - {string:day_zero})::integer AS result',
					[
						'day' => 'DAY',
						'day_zero' => '0001-01-01bc',
					],
				),
				'lang' => 'sql',
			],
		);

		return true;
	}
}
