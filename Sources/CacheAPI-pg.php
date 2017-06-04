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
		global $db_prefix, $smcFunc;

		$request = $smcFunc['db_query']('','
			SELECT 1 
			FROM   pg_tables
			WHERE  schemaname = {string:schema}
			AND    tablename = {string:cache}
			',
			array(
			'schema' => 'public',
			'cache' => $db_prefix . 'cache',
			)
		);

		if($smcFunc['db_num_rows']($request) === 0)		
			$smcFunc['db_query']('',
				'CREATE TABLE {db_prefix}cache (key text, value text, ttl bigint, PRIMARY KEY (key))',
				array()
			);
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
		global $smcFunc;
		
		$ttl = time() - $ttl;
		$request = $smcFunc['db_query']('','
			SELECT value FROM {db_prefix}cache WHERE key = {string:key} AND ttl >= {int:ttl} LIMIT 1
			',
			array(
				'key' => $key,
				'ttl' => $ttl,
			)
		);
		if($smcFunc['db_num_rows']($request) === 0)
			return null;
		
		$res = $smcFunc['db_fetch_assoc']($request);
		
		return $res['value'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		global $smcFunc;
		
                if(!isset($value))
                    $value = '';
                
		$ttl = time() + $ttl;
		$smcFunc['db_insert'](
			'replace',
			'{db_prefix}cache',
			array('key' => 'string', 'value' => 'string', 'ttl' => 'int'),
			array($key, $value, $ttl),
			array('key')
		);
		
		if ($smcFunc['db_affected_rows']() > 0)
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