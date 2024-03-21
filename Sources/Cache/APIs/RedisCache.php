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

		$this->setPooling();
		$this->setUnixSocket();
		$this->setConnectingData();
		$this->setUnixSocketData();
		$this->setAuth();
		$this->setAuthData();
		$this->setDbIndex();	
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
		$result = $this->cacheDB->set($key, $value, $ttl);;

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
		);
		$config_vars[] = array(
			'password_'. $class_name_txt_key,
			$txt['password_'. $class_name_txt_key],
			'file',
			'text',
			36,
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
	 * @param boolean $pooling true = on default off
	 *
	 * @return void
	 */
	public function setPooling(bool $pooling = null)
	{
		global $sourcedir, $pooling_rediscache;

		if(empty($pooling))
			$pooling = !empty($pooling_rediscache) ? $pooling_rediscache : false;

		if($pooling <> $pooling_rediscache)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('pooling_rediscache' => $pooling));
		}

		$this->pooling = $pooling;
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
	 * @return void
	 */
	public function setConnectingData(string $url = null, int $port = 6379, int $timeout = 1)
	{
		global $sourcedir, $url_rediscache, $port_rediscache, $timeout_rediscache;

		if(empty($url))
			$url = !empty($url_rediscache) ? $url_rediscache : null;
		if(empty($port))
			$port = !empty($port_rediscache) ? $port_rediscache : 6379;
		if(empty($timeout))
			$timeout = !empty($timeout_rediscache) ? $timeout_rediscache : 1;

		if($url <> $url_rediscache || $port <> $port_rediscache || $timeout <> $timeout_rediscache)	
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array(
					'url_rediscache' => $url, 
					'port_rediscache' => $port, 
					'timeout_rediscache' => $timeout
				));
		}

		$this->url = $url;
		$this->port = $port;
		$this->timeout = $timeout;
	}

	/**
	 * Sets the unix socket info
	 *
	 * @access public
	 *
	 * @param string $unixSocketData socketname
	 *
	 * @return void
	 */
	public function setUnixSocketData(string $unixSocketData = null)
	{
		global $sourcedir, $unixSocketData_rediscache;

		if(empty($unixSocketData))
			$unixSocketData = !empty($unixSocketData_rediscache) ? $unixSocketData_rediscache : null;

		if($unixSocketData <> $unixSocketData_rediscache)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('unixSocketData_rediscache' => $unixSocketData));
		}

		$this->unixSocketData = $unixSocketData;
	}

	/**
	 * Sets the unix socket mode
	 *
	 * @access public
	 *
	 * @param boolean $unixSocket enable or disable
	 *
	 * @return void
	 */
	public function setUnixSocket(bool $unixSocket = null)
	{
		global $sourcedir, $unixSocket_rediscache;

		if(empty($unixSocket))
			$unixSocket = !empty($unixSocket_rediscache) ? $unixSocket_rediscache : false;

		if($unixSocket <> $unixSocket_rediscache)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('unixSocket_rediscache' => $unixSocket));
		}

		$this->unixSocket = $unixSocket;
	}

	/**
	 * Sets the auth data
	 *
	 * @access public
	 *
	 * @param string $password password
	 * 
	 * @param string $username username
	 *
	 * @return void
	 */
	public function setAuthData(string $password = null, string $username = null)
	{
		global $sourcedir, $username_rediscache, $password_rediscache;

		if(empty($password))
			$password = !empty($password_rediscache) ? $password_rediscache : null;

		if(empty($username))
			$username = !empty($username_rediscache) ? $username_rediscache : null;

		if($username <> $username_rediscache || $password <> $password_rediscache)	
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array(
				'username_rediscache' => $username, 
				'password_rediscache' => $password
			));
		}

		$this->password = $password;
		$this->username = $username;
	}

	/**
	 * Sets the auth mode
	 *
	 * @access public
	 *
	 * @param boolean $auth enable or disable
	 *
	 * @return void
	 */
	public function setAuth(bool $auth = null)
	{
		global $sourcedir, $auth_rediscache;

		if(empty($auth))
			$auth = !empty($auth_rediscache) ? $auth_rediscache : false;
	
		if ( $auth <> $auth_rediscache )
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('auth_rediscache' => $auth));
		}

		$this->auth = $auth;
	}

	/**
	 * Sets the auth mode
	 *
	 * @access public
	 *
	 * @param integer $dbIndex database number
	 *
	 * @return void
	 */
	public function setDbIndex(int $dbIndex = null)
	{
		global $sourcedir, $dbIndex_rediscache;

		if(empty($dbIndex))
			$dbIndex = !empty($dbIndex_rediscache) ? $dbIndex_rediscache : 0;
		
		if ( $dbIndex <> $dbIndex_rediscache )
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('dbIndex_rediscache' => $dbIndex));
		}

		$this->dbIndex = $dbIndex;
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