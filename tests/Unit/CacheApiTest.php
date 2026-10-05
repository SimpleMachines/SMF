<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Cache\APIs\Apcu;
use SMF\Cache\APIs\FileBased;
use SMF\Cache\APIs\MemcachedImplementation;
use SMF\Cache\APIs\Sqlite;
use SMF\Cache\CacheApi;
use SMF\Config;

/**
 * Covers the accelerators that need nothing but a writable directory, which is
 * FileBased, Sqlite and, where the extension is installed, APCu. The memcached
 * and PostgreSQL accelerators want a server and a database connection, so only
 * the part of memcached that runs without one is reached from here.
 */
#[CoversClass(CacheApi::class)]
#[CoversClass(Apcu::class)]
#[CoversClass(FileBased::class)]
#[CoversClass(MemcachedImplementation::class)]
#[CoversClass(Sqlite::class)]
final class CacheApiTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string A directory of this test's own, thrown away afterwards.
	 */
	private string $cachedir;

	/**
	 * @var array The Config values this test overwrites.
	 */
	private array $config = [];

	/****************
	 * Public methods
	 ****************/

	public function setUp(): void
	{
		$this->cachedir = sys_get_temp_dir() . '/smf_cache_test_' . bin2hex(random_bytes(8));
		mkdir($this->cachedir, 0777, true);

		// The prefix is derived from this file's mtime, so it has to exist.
		touch($this->cachedir . '/index.php');

		foreach (['cachedir', 'cachedir_sqlite', 'cache_sqlite_wal', 'cache_memcached', 'db_persist'] as $key) {
			$this->config[$key] = Config::${$key} ?? null;
		}

		Config::$cachedir = $this->cachedir;
		Config::$cachedir_sqlite = $this->cachedir;
		Config::$cache_sqlite_wal = false;
		Config::$cache_memcached = '';
		Config::$db_persist = false;

		CacheApi::$enable = 3;
	}

	public function tearDown(): void
	{
		if (\is_object(CacheApi::$loadedApi)) {
			CacheApi::$loadedApi->quit();
		}

		CacheApi::$loadedApi = null;

		// Static properties survive between tests, so a level left behind here
		// would silently switch caching on for everything that follows.
		CacheApi::$enable = 0;

		Config::$cachedir = $this->config['cachedir'] ?? $this->cachedir;
		Config::$cachedir_sqlite = Config::$cachedir;
		Config::$cache_sqlite_wal = $this->config['cache_sqlite_wal'] ?? false;
		Config::$cache_memcached = $this->config['cache_memcached'] ?? '';
		Config::$db_persist = $this->config['db_persist'] ?? false;

		foreach (glob($this->cachedir . '/*') ?: [] as $file) {
			unlink($file);
		}

		rmdir($this->cachedir);
	}

	/**
	 * An accelerator returns what was put into it.
	 *
	 * Expected: after put() of each value in cacheableValues(),
	 *           get() returns an identical value, for every
	 *           accelerator in accelerators().
	 */
	#[DataProvider('accelerators')]
	public function testAnAcceleratorReturnsWhatWasPutIntoIt(string $class): void
	{
		$this->loadApi($class);

		foreach (self::cacheableValues() as $label => $value) {
			CacheApi::put('round_trip', $value, 120);

			$this->assertSame($value, CacheApi::get('round_trip', 120), $label . ' did not survive ' . $class);
		}
	}

	/**
	 * An object comes back as an equal object.
	 *
	 * Expected: after put() of a stdClass, get() returns an
	 *           object equal to it, for every accelerator in
	 *           accelerators().
	 */
	#[DataProvider('accelerators')]
	public function testAnObjectComesBackAsAnEqualObject(string $class): void
	{
		$this->loadApi($class);

		$object = new \stdClass();
		$object->greeting = 'hello';

		CacheApi::put('an_object', $object, 120);

		$this->assertEquals($object, CacheApi::get('an_object', 120));
	}

	/**
	 * Putting null removes the entry.
	 *
	 * Expected: after put() of a value and then of null under the
	 *           same key, get() returns null, for every
	 *           accelerator in accelerators().
	 */
	#[DataProvider('accelerators')]
	public function testPuttingNullRemovesTheEntry(string $class): void
	{
		$this->loadApi($class);

		CacheApi::put('to_be_removed', 'here', 120);
		CacheApi::put('to_be_removed', null, 120);

		$this->assertNull(CacheApi::get('to_be_removed', 120));
	}

	/**
	 * A key that was never stored is a miss.
	 *
	 * Expected: get() of a key nothing was put under returns
	 *           null, for every accelerator in accelerators().
	 */
	#[DataProvider('accelerators')]
	public function testAKeyThatWasNeverStoredIsAMiss(string $class): void
	{
		$this->loadApi($class);

		$this->assertNull(CacheApi::get('never_stored_' . bin2hex(random_bytes(4)), 120));
	}

	/**
	 * Cleaning the cache empties it.
	 *
	 * Expected: after put() and then clean(), get() returns null,
	 *           for every accelerator in accelerators().
	 */
	#[DataProvider('accelerators')]
	public function testCleaningTheCacheEmptiesIt(string $class): void
	{
		$this->loadApi($class);

		CacheApi::put('survives_a_clean', 'here', 120);
		CacheApi::clean();

		$this->assertNull(CacheApi::get('survives_a_clean', 120));
	}

	/**
	 * An entry is gone once its time to live has passed.
	 *
	 * APCu is left out because it reads a time to live of zero or less as
	 * "never expires" rather than as a moment already past.
	 *
	 * Expected: after put() with a time to live of -1, get()
	 *           returns null, for every accelerator in
	 *           acceleratorsWithARelativeTtl().
	 */
	#[DataProvider('acceleratorsWithARelativeTtl')]
	public function testAnEntryIsGoneOnceItsTimeToLiveHasPassed(string $class): void
	{
		$this->loadApi($class);

		CacheApi::put('already_stale', 'here', -1);

		$this->assertNull(CacheApi::get('already_stale', 120));
	}

	/**
	 * The SQLite cache stores values with write ahead logging on.
	 *
	 * Expected: with Config::$cache_sqlite_wal set, put() of ['a'
	 *           => 1] followed by get() returns ['a' => 1].
	 * Guards:   the pragma writes a header, so the check that
	 *           decided whether to create the table, which
	 *           looked for an empty file, never passed on a
	 *           fresh database. The accelerator had no table,
	 *           every read missed and every write threw.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/48f3d7bcb Introduced by "[3.0] Add support for WAL in Sqlite3 caching"
	 * @link https://github.com/SimpleMachines/SMF/pull/9627
	 */
	public function testTheSqliteCacheStoresValuesWithWriteAheadLoggingOn(): void
	{
		Config::$cache_sqlite_wal = true;

		$this->loadApi(Sqlite::class);

		CacheApi::put('written_ahead', ['a' => 1], 120);

		$this->assertSame(['a' => 1], CacheApi::get('written_ahead', 120));
	}

	/**
	 * A value the file cache cannot encode is a miss rather than a fatal
	 * error.
	 *
	 * The file cache stores JSON, and json_encode() returns false rather
	 * than a string for anything that is not valid UTF-8.
	 *
	 * Expected: put() of an array holding invalid UTF-8 does not
	 *           throw, and get() returns null.
	 * Guards:   the false from json_encode() was handed on to the
	 *           writer, which took the request down with a
	 *           TypeError.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/8b7ef30ce Introduced by "Start adding types and enforcing strict typing"
	 * @link https://github.com/SimpleMachines/SMF/pull/9627
	 */
	public function testAValueTheFileCacheCannotEncodeIsAMissRatherThanAFatal(): void
	{
		$this->loadApi(FileBased::class);

		CacheApi::put('not_utf8', ['blob' => "\x80\xFE\xFF"], 120);

		$this->assertNull(CacheApi::get('not_utf8', 120));
	}

	/**
	 * An entry that will not unserialise reads as a miss.
	 *
	 * A truncated entry unserialises to false, which is also what a cached
	 * false looks like. Telling them apart is the difference between a miss
	 * and handing the caller a value nobody ever stored.
	 *
	 * Expected: get() of a file cache entry whose value is not
	 *           serialised data returns null.
	 * Guards:   the false from unserialize() was returned as the
	 *           cached value.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/17fdb6e07 Introduced by "Uses serialization rather than JSON to cache. Needed for objects."
	 * @link https://github.com/SimpleMachines/SMF/pull/9627
	 */
	public function testAnEntryThatWillNotUnserialiseReadsAsAMiss(): void
	{
		/** @var \SMF\Cache\APIs\FileBased $api */
		$api = $this->loadApi(FileBased::class);

		file_put_contents(
			\sprintf('%s/data_%s.cache', $api->getCachedir(), $api->getPrefix() . 'corrupt'),
			(string) json_encode(['expiration' => time() + 120, 'value' => 'not serialised at all']),
		);

		$this->assertNull(CacheApi::get('corrupt', 120));
	}

	/**
	 * A cached false is still read back as false.
	 *
	 * This is the other side of
	 * testAnEntryThatWillNotUnserialiseReadsAsAMiss(): a miss must not be
	 * mistaken for a stored false either.
	 *
	 * Expected: after put() of false, get() returns false.
	 */
	public function testACachedFalseIsStillReadBackAsFalse(): void
	{
		$this->loadApi(FileBased::class);

		CacheApi::put('a_cached_false', false, 120);

		$this->assertFalse(CacheApi::get('a_cached_false', 120));
	}

	/**
	 * The memcached accelerator connects.
	 *
	 * No server is needed to see this, since addServer() only records where
	 * the server is meant to be.
	 *
	 * Expected: connect() returns true with
	 *           Config::$cache_memcached set to
	 *           '127.0.0.1:11211'; the test is skipped when the
	 *           memcached extension is not installed.
	 * Guards:   adding a server or-ed a bool into a bool, which
	 *           PHP hands back as an int, and the method
	 *           declares a bool. Every page load died with a
	 *           TypeError the moment memcached was the chosen
	 *           accelerator.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/8b7ef30ce Introduced by "Start adding types and enforcing strict typing"
	 * @link https://github.com/SimpleMachines/SMF/pull/9627
	 */
	public function testTheMemcachedAcceleratorConnects(): void
	{
		if (!class_exists('Memcached')) {
			$this->markTestSkipped('The memcached extension is not installed.');
		}

		Config::$cache_memcached = '127.0.0.1:11211';

		$this->assertTrue((new MemcachedImplementation())->connect());
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function accelerators(): array
	{
		return [
			'file based' => [FileBased::class],
			'sqlite' => [Sqlite::class],
			'apcu' => [Apcu::class],
		];
	}

	public static function acceleratorsWithARelativeTtl(): array
	{
		return [
			'file based' => [FileBased::class],
			'sqlite' => [Sqlite::class],
		];
	}

	/**
	 * The shapes SMF puts in the cache, plus the values that are easy to confuse
	 * with a miss.
	 */
	public static function cacheableValues(): array
	{
		return [
			'an array' => ['a' => 1, 'b' => [2, 3]],
			'a string' => 'hello world',
			'a numeric string' => '0123',
			'the number zero' => 0,
			'a float' => 1.5,
			'false' => false,
			'an empty string' => '',
			'an empty array' => [],
			'multibyte text' => ['t' => 'Grüße 日本語'],
			'a quarter of a megabyte' => str_repeat('x', 262144),
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Puts an accelerator behind the CacheApi facade, or skips the test when it
	 * cannot run here.
	 */
	private function loadApi(string $class): CacheApi
	{
		// APCu stores nothing at all unless it is switched on for the SAPI in use.
		if ($class === Apcu::class && \PHP_SAPI === 'cli' && !filter_var(\ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN)) {
			$this->markTestSkipped('APCu is not enabled for the CLI SAPI.');
		}

		/** @var \SMF\Cache\CacheApiInterface&CacheApi $api */
		$api = new $class();

		if (!$api->isSupported(true)) {
			$this->markTestSkipped($class . ' is not supported in this environment.');
		}

		if ($api->connect() === false) {
			$this->markTestSkipped($class . ' could not connect.');
		}

		CacheApi::$loadedApi = $api;

		return $api;
	}
}
