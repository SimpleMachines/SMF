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

namespace SMF\Maintenance\Database;

use SMF\Lang;
use SMF\Maintenance\DatabaseInterface;
use SMF\Db\DatabaseApi as Db;

class PostgreSQL implements DatabaseInterface
{
    public function getTitle(): string
    {
        return POSTGRE_TITLE;
    }

    public function getMinimumVersion(): string
    {
        return '12.17';
    }

    public function getServerVersion(): bool|string
    {
        $request = pg_query(Db::$db->connection, 'SELECT version()');
        list($version) = pg_fetch_row($request);
        list($pgl, $version) = explode(' ', $version);

        return $version;
    }

    public function isSupported(): bool
    {
        return function_exists('pg_connect');
    }

    public function SkipSelectDatabase(): bool
    {
        return true;
    }

    public function getDefaultUser(): string
    {
        return '';
    }

    public function getDefaultPassword(): string
    {
        return '';
    }

    public function getDefaultHost(): string
    {
        return '';
    }

    public function getDefaultPort(): int
    {
        return 5432;
    }

    public function getDefaultName(): string
    {
        return 'smf_';
    }

    public function checkConfiguration(): bool
    {
		$result = Db::$db->query(
			'',
			'show standard_conforming_strings',
			[
				'db_error_skip' => true,
			],
		);

		if ($result !== false) {
			$row = Db::$db->fetch_assoc($result);

			if ($row['standard_conforming_strings'] !== 'on') {
                throw new \Exception(Lang::$txt['error_pg_scs']);
            }
			Db::$db->free_result($result);
		}

        return true;
    }


    public function hasPermissions(): bool
    {
        return true;
    }

    public function validatePrefix(&$value): bool
    {
        $value = preg_replace('~[^A-Za-z0-9_\$]~', '', $value);

        // Is it reserved?
        if ($value == 'pg_') {
            throw new \Exception(Lang::$txt['error_db_prefix_reserved']);
        }
    
        // Is the prefix numeric?
        if (preg_match('~^\d~', $value)) {
            throw new \Exception(Lang::$txt['error_db_prefix_numeric']);
        }
    
        return true;
    }

    public function utf8Configured(): bool
    {
        $request = pg_query(Db::$db->connection, 'SHOW SERVER_ENCODING');

        list($charcode) = pg_fetch_row($request);

        if ($charcode == 'UTF8') {
            return true;
        }

        throw new \Exception(sprintf(Lang::$txt['error_utf8_version'], $this->getMinimumVersion()));       
    }

}