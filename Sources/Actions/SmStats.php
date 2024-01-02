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

namespace SMF\Actions;

use SMF\Actions\Admin\ACP;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\User;

/**
 * Lets simplemachines.org gather statistics if, and only if, the admin allows.
 */
class SmStats implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'SMStats',
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Sends stats to simplemachines.org when requested, but only if enabled!
	 *
	 * - Called by simplemachines.org.
	 * - Only returns anything if stats was enabled during installation.
	 * - Can also be accessed by the admin, to show what info is collected.
	 * - Does not return any data directly to simplemachines.org in response to
	 *   the incoming request. Instead, for security, starts a new, separate
	 *   request back to simplemachines.org.
	 *
	 * See https://www.simplemachines.org/about/stats.php for more info.
	 */
	public function execute(): void
	{
		// First, is it disabled?
		if (empty(Config::$modSettings['enable_sm_stats']) || empty(Config::$modSettings['sm_stats_key'])) {
			die();
		}

		// Are we saying who we are, and are we right? (OR an admin)
		if (!User::$me->is_admin && (!isset($_GET['sid']) || $_GET['sid'] != Config::$modSettings['sm_stats_key'])) {
			die();
		}

		// Verify the referer...
		if (!User::$me->is_admin && (!isset($_SERVER['HTTP_REFERER']) || md5($_SERVER['HTTP_REFERER']) != '746cb59a1a0d5cf4bd240e5a67c73085')) {
			die();
		}

		// Get some server versions.
		$checkFor = [
			'php',
			'db_server',
		];
		$serverVersions = ACP::getServerVersions($checkFor);

		// Get the actual stats.
		$stats_to_send = [
			'UID' => Config::$modSettings['sm_stats_key'],
			'time_added' => time(),
			'members' => Config::$modSettings['totalMembers'],
			'messages' => Config::$modSettings['totalMessages'],
			'topics' => Config::$modSettings['totalTopics'],
			'boards' => 0,
			'php_version' => $serverVersions['php']['version'],
			'database_type' => strtolower($serverVersions['db_engine']['version']),
			'database_version' => $serverVersions['db_server']['version'],
			'smf_version' => SMF_FULL_VERSION,
			'smfd_version' => Config::$modSettings['smfVersion'],
		];

		// Encode all the data, for security.
		foreach ($stats_to_send as $k => $v) {
			$stats_to_send[$k] = urlencode($k) . '=' . urlencode($v);
		}

		// Turn this into the query string!
		$stats_to_send = implode('&', $stats_to_send);

		// If we're an admin, just plonk them out.
		if (User::$me->is_admin) {
			echo $stats_to_send;
		} else {
			// Connect to the collection script.
			$fp = @fsockopen('www.simplemachines.org', 443, $errno, $errstr);

			if (!$fp) {
				$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
			}

			if ($fp) {
				$length = strlen($stats_to_send);

				$out = 'POST /smf/stats/collect_stats.php HTTP/1.1' . "\r\n";
				$out .= 'Host: www.simplemachines.org' . "\r\n";
				$out .= 'user-agent: ' . SMF_USER_AGENT . "\r\n";
				$out .= 'content-type: application/x-www-form-urlencoded' . "\r\n";
				$out .= 'connection: Close' . "\r\n";
				$out .= 'content-length: ' . $length . "\r\n\r\n";
				$out .= $stats_to_send . "\r\n";
				fwrite($fp, $out);
				fclose($fp);
			}
		}

		// Die.
		die('OK');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\SmStats::exportStatic')) {
	SmStats::exportStatic();
}

?>