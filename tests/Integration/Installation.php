<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Infrastructure\Container;

/**
 * Finds the forum the integration suite runs against.
 *
 * The unit bootstrap deliberately stops short of a database. This carries on
 * from where it left off - reads Settings.php, connects, loads $modSettings -
 * but only when an integration test actually asks, so `composer test` on a
 * machine with no forum still costs nothing.
 *
 * It never throws. When there is nothing to test against it says why, and
 * IntegrationTestCase turns that into a skip rather than a failure.
 *
 * To get a forum: .docker/install-forum.sh --engine mysql
 */
final class Installation
{
	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var string|null Why the forum is unusable, '' when it is fine, null
	 *     before anything has looked.
	 */
	private static ?string $unavailable = null;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Connects, once per process.
	 *
	 * @return string Empty when there is a forum to test against, otherwise the
	 *     reason there is not.
	 */
	public static function unavailableReason(): string
	{
		// Db::load() hands back the connection it already made and there is no
		// way to drop it, so this can only usefully run once anyway.
		if (self::$unavailable === null) {
			self::$unavailable = self::connect();
		}

		return self::$unavailable;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Does the actual work of unavailableReason().
	 *
	 * @return string Empty on success, otherwise the reason.
	 */
	private static function connect(): string
	{
		if (!is_file(SMF_SETTINGS_FILE) || filesize(SMF_SETTINGS_FILE) === 0) {
			return 'there is no Settings.php';
		}

		try {
			Config::load();
		} catch (\Throwable $e) {
			return 'Settings.php could not be loaded: ' . $e->getMessage();
		}

		if (empty(Config::$db_type) || empty(Config::$db_name)) {
			return 'Settings.php names no database';
		}

		// index.php builds this before anything can ask for a service.
		Container::init();

		try {
			// non_fatal, or a refused connection ends the process with SMF's own
			// database error page instead of letting us report it here.
			Db::load(['non_fatal' => true]);
		} catch (\Throwable $e) {
			return 'could not connect to ' . Config::$db_type . ': ' . $e->getMessage();
		}

		if (!isset(Db::$db->connection)) {
			return 'could not connect to ' . Config::$db_type . ' as ' . Config::$db_user;
		}

		// Connecting is not the same as finding a forum: the dev environment
		// writes a Settings.php long before anything is installed behind it.
		try {
			Config::reloadModSettings();
		} catch (\Throwable $e) {
			return 'the database holds no forum: ' . $e->getMessage();
		}

		if (empty(Config::$modSettings['smfVersion'])) {
			return 'the database holds no forum (no smfVersion in ' . Config::$db_prefix . 'settings)';
		}

		return '';
	}
}
