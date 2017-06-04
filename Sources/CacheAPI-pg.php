<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * PostgreSQL Cache API class
 * @package cacheAPI
 */
class pg_cache extends cache_api
{

	public function __construct()
	{
		parent::__construct();

	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		global $db_prefix, $smcFunc, $db_connection;
		
		pg_prepare($db_connection, '', 'SELECT 1 
			FROM   pg_tables
			WHERE  schemaname = $1
			AND    tablename = $2');
			
		$result = pg_execute($db_connection, '', array('public', $db_prefix . 'cache'));

		if(pg_affected_rows($result) === 0)
			pg_query($db_connection, 'CREATE TABLE {db_prefix}cache (key text, value text, ttl bigint, PRIMARY KEY (key))');			
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		global $smcFunc;
		
		if ($smcFunc['db_title'] === 'PostgreSQL')
			return true;
		else
			return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		global $db_prefix, $smcFunc, $db_connection;
		
		$ttl = time() - $ttl;
		pg_prepare($db_connection, '', 'SELECT value FROM ' . $db_prefix . 'cache WHERE key = $1 AND ttl >= $2 LIMIT 1');
			
		$result = pg_execute($db_connection, '', array($key, $ttl));
		
		if(pg_affected_rows($result) === 0)
			return null;
		
		$res = pg_fetch_assoc($result);
		
		return $res['value'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		global  $db_prefix, $smcFunc, $db_connection;
		
		if(!isset($value))
			$value = '';
                
		$ttl = time() + $ttl;
		
		pg_prepare($db_connection, '',
			'INSERT INTO ' . $db_prefix . 'cache(key,value,ttl) VALUES($1,$2,$3)
			ON CONFLICT(key) DO UPDATE SET value = excluded.value, ttl = excluded.ttl'
		);
			
		$result = pg_execute($db_connection, '', array($key, $value, $ttl));
		
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
		
		$smcFunc['db_query']('',
				'TRUNCATE TABLE {db_prefix}cache',
				array()
			);

		return true;
	}
}

?>