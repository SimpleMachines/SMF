<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.2
 */

namespace SMF\Cache\APIs;

use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;
use Redis;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Redis Cache API class
 *
 * @package CacheAPI
 */
class RedisCache extends CacheApi implements CacheApiInterface
{
	/**
	 * @var boolean Pooling
	 */
	private $pooling = null;

	private $url = null;
	private $port = null;
	private $timeout = null;

	private $unixSocket = null;
	private $unixSocketData = null;

	private $auth = null;
	private $username = null;
	private $password = null;

	private $dbIndex = null; 

	/**
	 * @var Redis
	 */
	private $cacheDB = null;

	public function __construct()
	{
		parent::__construct();

		global
			$pooling_rediscache,
			$url_rediscache, $port_rediscache, $timeout_rediscache,
			$unixSocket_rediscache, $unixSocketData_rediscache,
			$auth_rediscache, $username_rediscache, $password_rediscache,
			$dbIndex_rediscache;

			$this->pooling = isset($pooling_rediscache) ? $pooling_rediscache : false;
			$this->url = isset($url_rediscache) ? $url_rediscache : null;
			$this->port = isset($port_rediscache) ? $port_rediscache : null;
			$this->timeout = isset($timeout_rediscache) ? $timeout_rediscache : null;
			$this->unixSocket = isset($unixSocket_rediscache) ? $unixSocket_rediscache : false;
			$this->unixSocketData = isset($unixSocketData_rediscache) ? $unixSocketData_rediscache : null;
			$this->auth = isset($auth_rediscache) ? $auth_rediscache : false;
			$this->username = isset($username_rediscache) ? $username_rediscache : null;
			$this->password = isset($password_rediscache) ? $password_rediscache : null;
			$this->dbIndex = isset($dbIndex_rediscache) ? $dbIndex_rediscache : null;
		
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		$this->cacheDB = new Redis();
		$success = false;

		try{
			if($this->unixSocket && !empty($this->unixSocketData))
				if($this->pooling)
					$success = $this->cacheDB->pconnect($this->unixSocketData);
				else
					$success = $this->cacheDB->connect($this->unixSocketData);
			else
				if($this->pooling)
					$success = $this->cacheDB->pconnect($this->url, $this->port, $this->timeout);
				else
					$success = $this->cacheDB->connect($this->url, $this->port, $this->timeout);

			if($success && $this->auth && !empty($this->password))
				if(!empty($this->username))
					$success = $this->cacheDB->auth([$this->username, $this->password]);
				else
					$success = $this->cacheDB->auth([$this->password]);
			
			if($success && !empty($this->dbIndex))
				$success = $this->cacheDB->select($this->dbIndex);
		} catch(\RedisException $ex)
		{
			$success = false;
		}

		return $success;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = class_exists("Redis");

		if ($test)
			return $supported;

		return parent::isSupported() && $supported;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$value = $this->cacheDB->get($key);

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$result = $this->cacheDB->set($key,$value,$ttl);;

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		$result = $this->cacheDB->flushDb();

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$class_name = $this->getImplementationClassKeyName();
		$class_name_txt_key = strtolower($class_name);

		$config_vars[] = $txt['cache_'. $class_name_txt_key .'_settings'];
		$config_vars[] = array(
			'pooling_'. $class_name_txt_key,
			$txt['pooling_'. $class_name_txt_key],
			'file',
			'check',
			36,
			'cache_'. $class_name_txt_key .'_pooling',
		);
		$config_vars[] = array(
			'unixsocket_'. $class_name_txt_key,
			$txt['unixsocket_'. $class_name_txt_key],
			'file',
			'check',
			36,
			'cache_'. $class_name_txt_key .'_unixsocket',
		);
		$config_vars[] = array(
			'unixsocketdata_'. $class_name_txt_key,
			$txt['unixsocketdata_'. $class_name_txt_key],
			'file',
			'text',
			36,
			'cache_'. $class_name_txt_key .'_unixsocketdata',
		);
		$config_vars[] = array(
			'url_'. $class_name_txt_key,
			$txt['url_'. $class_name_txt_key],
			'file',
			'text',
			36,
			'cache_'. $class_name_txt_key .'_url',
		);
		$config_vars[] = array(
			'port_'. $class_name_txt_key,
			$txt['port_'. $class_name_txt_key],
			'file',
			'int',
			6,
			'cache_'. $class_name_txt_key .'_port',
		);
		$config_vars[] = array(
			'timeout_'. $class_name_txt_key,
			$txt['timeout_'. $class_name_txt_key],
			'file',
			'int',
			36,
			'cache_'. $class_name_txt_key .'_timeout',
		);
		$config_vars[] = array(
			'auth_'. $class_name_txt_key,
			$txt['auth_'. $class_name_txt_key],
			'file',
			'check',
			36,
			'cache_'. $class_name_txt_key .'_auth',
		);
		$config_vars[] = array(
			'username_'. $class_name_txt_key,
			$txt['username_'. $class_name_txt_key],
			'file',
			'text',
			36,
			'cache_'. $class_name_txt_key .'_username',
		);
		$config_vars[] = array(
			'password_'. $class_name_txt_key,
			$txt['password_'. $class_name_txt_key],
			'file',
			'text',
			36,
			'cache_'. $class_name_txt_key .'_password',
		);
		$config_vars[] = array(
			'dbindex_'. $class_name_txt_key,
			$txt['dbindex_'. $class_name_txt_key],
			'file',
			'int',
			3,
			'cache_'. $class_name_txt_key .'_dbindex',
		);


		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		if (empty($context['settings_not_writable']))
			$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#pooling_'. $class_name_txt_key .'").prop("disabled", cache_type != "'. $class_name .'");
				$("#unixsocket_'. $class_name_txt_key .'").prop("disabled", cache_type != "'. $class_name .'");
				$("#auth_'. $class_name_txt_key .'").prop("disabled", cache_type != "'. $class_name .'");
				$("#unixsocket_'. $class_name_txt_key .'").change();
				$("#auth_'. $class_name_txt_key .'").change();
				$("#dbindex_'. $class_name_txt_key .'").prop("disabled", cache_type != "'. $class_name .'");
			});';
			$context['settings_post_javascript'] .= '
			$("#unixsocket_'. $class_name_txt_key . '").change(function (e) {
				var unixsocket = e.currentTarget.checked;
				unixsocket = e.currentTarget.disabled ? null : unixsocket;
				$("#unixsocketdata_'. $class_name_txt_key .'").prop("disabled", unixsocket !== true);
				$("#url_'. $class_name_txt_key .'").prop("disabled", unixsocket !== false);
				$("#port_'. $class_name_txt_key .'").prop("disabled", unixsocket !== false);
				$("#timeout_'. $class_name_txt_key .'").prop("disabled", unixsocket !== false);
			});';
			$context['settings_post_javascript'] .= '
			$("#auth_'. $class_name_txt_key . '").change(function (e) {
				var auth = e.currentTarget.checked;
				auth = e.currentTarget.disabled ? null : auth;
				$("#username_'. $class_name_txt_key .'").prop("disabled", auth !== true);
				$("#password_'. $class_name_txt_key .'").prop("disabled", auth !== true);
			});';
			
	}

	/**
	 * Sets the $pooling
	 *
	 * @access public
	 *
	 * @param boolean $pooling true = on
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setPooling(bool $pooling = false)
	{
		require_once($sourcedir . '/Subs-Admin.php');
		$success = updateSettingsFile(array('pooling_rediscache' => $pooling));

		if ($success)
			$this->pooling = $pooling;

		return $success;
	}

	/**
	 * Sets the network connection info
	 *
	 * @access public
	 *
	 * @param string $url hostname or tls://
	 * 
	 * @param int $port default 6379
	 * 
	 * @param int $timeout default 1
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setConnectingData(string $url, int $port = 6379, int $timeout = 1)
	{
		require_once($sourcedir . '/Subs-Admin.php');
		$success = updateSettingsFile(array(
				'url_rediscache' => $url, 
				'port_rediscache' => $port, 
				'timeout_rediscache' => $timeout
			));

		if ($success) 
		{
			$this->url = $url;
			$this->port = $port;
			$this->timeout = $timeout;
		}

		return $success;
	}

	/**
	 * Sets the unix socket info
	 *
	 * @access public
	 *
	 * @param string $unixSocketData socketname
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setUnixSocketData(string $unixSocketData)
	{
		require_once($sourcedir . '/Subs-Admin.php');
		$success = updateSettingsFile(array('unixSocketData_rediscache' => $unixSocketData));

		if ($success) 
			$this->unixSocketData = $unixSocketData;

		return $success;
	}

	/**
	 * Sets the unix socket mode
	 *
	 * @access public
	 *
	 * @param boolean $unixSocket enable or disable
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setUnixSocket(bool $unixSocket = false)
	{
		require_once($sourcedir . '/Subs-Admin.php');
		$success = updateSettingsFile(array('unixSocket_rediscache' => $unixSocket));

		if ($success) 
			$this->unixSocket = $unixSocket;

		return $success;
	}

		/**
	 * Sets the unix socket info
	 *
	 * @access public
	 *
	 * @param string $unixSocketData socketname
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setAuthData(string $password, string $username = null)
	{
		require_once($sourcedir . '/Subs-Admin.php');
		$success = updateSettingsFile(array(
			'username_rediscache' => $username, 
			'password_rediscache' => $password
		));

		if ($success) 
		{
			$this->username = $username;
			$this->password = $password;
		}

		return $success;
	}

	/**
	 * Sets the auth mode
	 *
	 * @access public
	 *
	 * @param boolean $auth enable or disable
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setAuth(bool $auth = false)
	{
		require_once($sourcedir . '/Subs-Admin.php');
		$success = updateSettingsFile(array('auth_rediscache' => $auth));

		if ($success) 
			$this->auth = $auth;

		return $success;
	}

	/**
	 * Sets the auth mode
	 *
	 * @access public
	 *
	 * @param integer $dbIndex database number
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setDbIndex(int $dbIndex = 0)
	{
		require_once($sourcedir . '/Subs-Admin.php');
		$success = updateSettingsFile(array('dbIndex_rediscache' => $dbIndex));

		if ($success) 
			$this->dbIndex = $dbIndex;

		return $success;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		if (null == $this->cacheDB)
			$this->connect();

		return $this->cacheDB->info("SERVER")['redis_version'];
	}

}

?>