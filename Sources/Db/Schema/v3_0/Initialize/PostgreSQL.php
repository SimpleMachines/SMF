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

namespace SMF\Db\Schema\v3_0\Initialize;

class PostgreSQL extends Base
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * SMF was first developed on MySQL and still relies heavily on non TSQL functions.
	 * Additionaly, some TSQL functions don't exist in MySQL and however the MySQL variant is used.
	 *
	 * @return string[]
	 */
	public function functions(): array
	{
		$functions = [];

		// PostgreSQL changed how we can do some aggregation functions in 14 and beyond
		// Idea for the GROUP_CONCAT from: https://dba.stackexchange.com/questions/24984/is-it-possible-to-wrap-aggregate-functions-in-postgres
		if (version_compare($this->version, '14', '>')) {
			// This isn't even my final form.
			$functions[] = '
            CREATE OR REPLACE FUNCTION GROUP_CONCAT_FINAL(anycompatiblearray)
            RETURNS text LANGUAGE SQL AS
            $func$SELECT array_to_string($1, \',\')$func$;';

			// PostgreSQL is in the technical right that we should use string_agg, but MySQL doesn't support it yet.
			$functions[] = '
            CREATE OR REPLACE AGGREGATE GROUP_CONCAT (anycompatible) (
				SFUNC = array_append,
				STYPE = anycompatiblearray,
				INITCOND = \'{}\',
				FINALFUNC = GROUP_CONCAT_FINAL
            );';
		} else {
			// Only difference is anycompatiblearray > anyarray
			$functions[] = '
            CREATE OR REPLACE FUNCTION GROUP_CONCAT_FINAL(anyarray)
            RETURNS text LANGUAGE SQL AS
            $func$SELECT array_to_string($1, \',\')$func$;';

			// Only difference is anycompatiblearray > anyelement and anycompatiblearray > anyarray
			$functions[] = '
            CREATE OR REPLACE AGGREGATE GROUP_CONCAT (anyelement) (
				SFUNC = array_append,
				STYPE = anyarray,
				INITCOND = \'{}\',
				FINALFUNC = GROUP_CONCAT_FINAL
            );';
		}

		$functions[] = '
            CREATE OR REPLACE FUNCTION FROM_UNIXTIME(bigint) RETURNS timestamp AS
                \'SELECT timestamp \'\'epoch\'\' + $1 * interval \'\'1 second\'\' AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION FIND_IN_SET(needle text, haystack text) RETURNS integer AS \'
                SELECT i AS result
                FROM generate_series(1, array_upper(string_to_array($2,\'\',\'\'), 1)) AS g(i)
                WHERE  (string_to_array($2,\'\',\'\'))[i] = $1
                    UNION ALL
                SELECT 0
                LIMIT 1\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION FIND_IN_SET(needle integer, haystack text) RETURNS integer AS \'
                SELECT i AS result
                FROM generate_series(1, array_upper(string_to_array($2,\'\',\'\'), 1)) AS g(i)
                WHERE  (string_to_array($2,\'\',\'\'))[i] = CAST($1 AS text)
                    UNION ALL
                SELECT 0
                LIMIT 1\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION FIND_IN_SET(needle smallint, haystack text) RETURNS integer AS \'
                SELECT i AS result
                FROM generate_series(1, array_upper(string_to_array($2,\'\',\'\'), 1)) AS g(i)
                WHERE  (string_to_array($2,\'\',\'\'))[i] = CAST($1 AS text)
                    UNION ALL
                SELECT 0
                LIMIT 1\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION add_num_text (text, integer) RETURNS text AS
                \'SELECT CAST ((CAST($1 AS integer) + $2) AS text) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION YEAR (timestamp) RETURNS integer AS
                \'SELECT CAST (EXTRACT(YEAR FROM $1) AS integer) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION MONTH (timestamp) RETURNS integer AS
                \'SELECT CAST (EXTRACT(MONTH FROM $1) AS integer) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION MONTH (bigint) RETURNS integer AS
                \'SELECT CAST (EXTRACT(MONTH FROM TO_TIMESTAMP($1)) AS integer) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION day(date) RETURNS integer AS
                \'SELECT EXTRACT(DAY FROM DATE($1))::integer AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION DAYOFMONTH (timestamp) RETURNS integer AS
                \'SELECT CAST (EXTRACT(DAY FROM $1) AS integer) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION DAYOFMONTH (bigint) RETURNS integer AS
                \'SELECT CAST (EXTRACT(DAY FROM TO_TIMESTAMP($1)) AS integer) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION HOUR (timestamp) RETURNS integer AS
                \'SELECT CAST (EXTRACT(HOUR FROM $1) AS integer) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION DATE_FORMAT (timestamp, text) RETURNS text AS \'
                SELECT
                REPLACE(
                    REPLACE($2, \'\'%m\'\', to_char($1, \'\'MM\'\')),
                    \'\'%d\'\', to_char($1, \'\'DD\'\')) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION TO_DAYS (timestamp) RETURNS integer AS
                \'SELECT DATE_PART(\'\'DAY\'\', $1 - \'\'0001-01-01bc\'\')::integer AS result\'
            LANGUAGE \'sql\'';

		$functions[] = '
            CREATE OR REPLACE FUNCTION INSTR (text, text) RETURNS integer AS
                \'SELECT POSITION($2 in $1) AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION bool_not_eq_int (boolean, integer) RETURNS boolean AS
                \'SELECT CAST($1 AS integer) != $2 AS result\'
            LANGUAGE \'sql\';';

		$functions[] = '
            CREATE OR REPLACE FUNCTION indexable_month_day(date) RETURNS TEXT as \'
                    SELECT to_char($1, \'\'MM-DD\'\');\'
            LANGUAGE \'sql\' IMMUTABLE STRICT;';

		return $functions;
	}

	/**
	 * We need some additional copmarision operators.
	 * @return string[]
	 */
	public function operators(): array
	{
		return [
			'CREATE OPERATOR + (PROCEDURE = add_num_text, LEFTARG = text, RIGHTARG = integer);',
			'CREATE OPERATOR != (PROCEDURE = bool_not_eq_int, LEFTARG = boolean, RIGHTARG = integer);',
		];
	}
}
