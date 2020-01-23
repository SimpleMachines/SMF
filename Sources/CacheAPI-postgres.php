<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * PostgreSQL Cache API class
 *
 * @package cacheAPI
 */
class postgres_cache extends cache_api
{
	/** @var string */
	private $db_prefix;

	/** @var resource result of pg_connect. */
	private $db_connection;

	public function __construct()
	{
		global $db_prefix, $db_connection;

		$this->db_prefix = $db_prefix;
		$this->db_connection = $db_connection;

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		global $db_prefix, $db_connection;

		pg_prepare($db_connection, '', 'SELECT 1
			FROM   pg_tables
			WHERE  schemaname = $1
			AND    tablename = $2');

		$result = pg_execute($db_connection, '', array('public', $db_prefix . 'cache'));

		if (pg_affected_rows($result) === 0)
			pg_query($db_connection, 'CREATE UNLOGGED TABLE ' . $db_prefix . 'cache (key text, value text, ttl bigint, PRIMARY KEY (key))');
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		global $smcFunc;

		if ($smcFunc['db_title'] !== POSTGRE_TITLE)
			return false;

		$result = pg_query($this->db_connection, 'SHOW server_version_num');
		$res = pg_fetch_assoc($result);

		if ($res['server_version_num'] < 90500)
			return false;

		return $test ? true : parent::isSupported();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		global $db_prefix, $db_connection, $db_persist;

		$ttl = time() - $ttl;

		if (empty($this->pg_get_data_prep))
		{
			if (empty($db_persist))
				$this->pg_get_data_prep = pg_prepare($db_connection, 'smf_cache_get_data', 'SELECT value FROM ' . $db_prefix . 'cache WHERE key = $1 AND ttl >= $2 LIMIT 1');
			else
			{
				@pg_prepare($db_connection, 'smf_cache_get_data', 'SELECT value FROM ' . $db_prefix . 'cache WHERE key = $1 AND ttl >= $2 LIMIT 1');
				$this->pg_get_data_prep == true;
			}
		}

		$result = pg_execute($db_connection, 'smf_cache_get_data', array($key, $ttl));

		if (pg_affected_rows($result) === 0)
			return null;

		$res = pg_fetch_assoc($result);

		return $res['value'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$ttl = time() + (int) ($ttl !== null ? $ttl : $this->ttl);

		if (!isset($value))
			$value = '';


		if (empty($this->pg_put_data_prep))
		{
			if (empty($db_persist))
				$this->pg_put_data_prep = pg_prepare($db_connection, 'smf_cache_put_data',
					'INSERT INTO ' . $db_prefix . 'cache(key,value,ttl) VALUES($1,$2,$3)
					ON CONFLICT(key) DO UPDATE SET value = excluded.value, ttl = excluded.ttl'
				);
			else
			{
				@pg_prepare($db_connection, 'smf_cache_put_data',
					'INSERT INTO ' . $db_prefix . 'cache(key,value,ttl) VALUES($1,$2,$3)
					ON CONFLICT(key) DO UPDATE SET value = excluded.value, ttl = excluded.ttl'
				);
				$this->pg_put_data_prep = true;
			}
		}

		$result = pg_execute($db_connection, 'smf_cache_put_data', array($key, $value, $ttl));

		if (pg_affected_rows($result) > 0)
			return true;
		else
			return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		global $smcFunc;

		$smcFunc['db_query']('', '
			TRUNCATE TABLE {db_prefix}cache',
			array()
		);

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		global $smcFunc;

		return $smcFunc['db_server_info']();
	}

	/**
	 * {@inheritDoc}
	 */
	public function housekeeping()
	{
		$this->createTempTable();
		$this->cleanCache();
		$this->retrieveData();
		$this->deleteTempTable();
	}

	/**
	 * Create the temp table of valid data.
	 *
	 * @return void
	 */
	private function createTempTable()
	{
		pg_query($this->db_connection, 'CREATE LOCAL TEMP TABLE IF NOT EXISTS ' . $this->db_prefix . 'cache_tmp AS SELECT * FROM ' . $this->db_prefix . 'cache WHERE ttl >= ' . time());
	}

	/**
	 * Delete the temp table.
	 *
	 * @return void
	 */
	private function deleteTempTable()
	{
		pg_query($this->db_connection, 'DROP TABLE IF EXISTS ' . $this->db_prefix . 'cache_tmp');
	}

	/**
	 * Retrieve the valid data from temp table.
	 *
	 * @return void
	 */
	private function retrieveData()
	{
		pg_query($this->db_connection, 'INSERT INTO ' . $this->db_prefix . 'cache SELECT * FROM ' . $this->db_prefix . 'cache_tmp ON CONFLICT DO NOTHING');
	}
}

?>