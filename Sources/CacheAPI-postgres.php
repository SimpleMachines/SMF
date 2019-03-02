<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
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
	/**
	 * @var false|resource of the pg_prepare from get_data.
	 */
	private $pg_get_data_prep;

	/**
	 * @var false|resource of the pg_prepare from put_data.
	 */
	private $pg_put_data_prep;

	public function __construct()
	{
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
		global $smcFunc, $db_connection;

		if ($smcFunc['db_title'] !== 'PostgreSQL')
			return false;

		$result = pg_query($db_connection, 'SHOW server_version_num');
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
		global $db_prefix, $db_connection;

		$ttl = time() - $ttl;

		if (empty($this->pg_get_data_prep))
			$this->pg_get_data_prep = pg_prepare($db_connection, 'smf_cache_get_data', 'SELECT value FROM ' . $db_prefix . 'cache WHERE key = $1 AND ttl >= $2 LIMIT 1');

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
		global $db_prefix, $db_connection;

		if (!isset($value))
			$value = '';

		$ttl = time() + $ttl;

		if (empty($this->pg_put_data_prep))
			$this->pg_put_data_prep = pg_prepare($db_connection, 'smf_cache_put_data',
				'INSERT INTO ' . $db_prefix . 'cache(key,value,ttl) VALUES($1,$2,$3)
				ON CONFLICT(key) DO UPDATE SET value = excluded.value, ttl = excluded.ttl'
			);

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
		global $db_connection, $db_prefix;

		pg_query($db_connection, 'CREATE LOCAL TEMP TABLE IF NOT EXISTS ' . $db_prefix . 'cache_tmp AS SELECT * FROM ' . $db_prefix . 'cache WHERE ttl >= ' . time());
	}

	/**
	 * Delete the temp table.
	 *
	 * @return void
	 */
	private function deleteTempTable()
	{
		global $db_connection, $db_prefix;

		pg_query($db_connection, 'DROP TABLE IF EXISTS ' . $db_prefix . 'cache_tmp');
	}

	/**
	 * Retrieve the valid data from temp table.
	 *
	 * @return void
	 */
	private function retrieveData()
	{
		global $db_connection, $db_prefix;

		pg_query($db_connection, 'INSERT INTO ' . $db_prefix . 'cache SELECT * FROM ' . $db_prefix . 'cache_tmp ON CONFLICT DO NOTHING');
	}
}

?>