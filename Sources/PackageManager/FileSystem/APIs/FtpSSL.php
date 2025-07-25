<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

namespace SMF\PackageManager\FileSystem\APIs;

use SMF\ErrorHandler;
use SMF\Lang;
use SMF\PackageManager\FileSystem\FileSystem;
use SMF\PackageManager\FileSystem\FileSystemInterface;

/**
 * Connects to our FTP explicit SSL-FTP.
 */
class FtpSSL extends FileSystem implements FileSystemInterface
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Timeout protection for commands that require waiting.
	 * @var int
	 */
	private const RESPONSE_TIMEOUT = 5;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * Resource for the FTP connetion.
	 *
	 * @var \Ftp\Connection|resource
	 */
	private $connection;

	/****************
	 * Public methods
	 ****************/

	public function isSupported(): bool
	{
		return function_exists('ftp_ssl_connect');
	}

	/**
	 *
	 */
	public function isConfigured(): bool
	{
		return true;
	}

	/**
	 * Connects to a FTPs server
	 *
	 * @param string $server The address of the server
	 * @param string $username The username
	 * @param string $password The password
	 * @param ?int $port The port, if 0 or null, defaults to 21
	 * @param ?string  The root of the forum, if null we won't attempt transforming paths.
	 */
	public function connect(
		string $server,
		string $username,
		#[\SensitiveParameter]
		string $password,
		?string $port = null,
		?string $root = null,
	): bool {
		if (empty($port)) {
			$port = 21;
		}

		if ($root !== null) {
			$this->forum_root = $root;
		}

		$this->connection =  ftp_ssl_connect(
			$this->getServerAddress($server),
			$port,
			self::RESPONSE_TIMEOUT,
		);

		if (($this->connection ?? false) === false) {
			$this->error = 'bad_server';

			return false;
		}

		// login with username and password
		if (!$this->tryCall('login', $this->connection, $username, $password)) {
			$this->connection = null;
			$this->error = 'bad_credentials';

			return false;
		}

		return true;
	}

	/**
	 * Close the ftp connection
	 *
	 * @return bool Always returns true
	 */
	public function disconnect(): bool
	{
		if ($this->connection ?? false !== false) {
			$this->tryCall('close', $this->connection);
		}

		$this->connection = null;
		$this->error = null;
		$this->last_message = null;

		return true;
	}

	/**
	 * Determines if we have a connection to the ftp server.
	 *
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return $this->connection !== null && empty($this->error);
	}

	/**
	 * Changes to a directory (chdir) via the ftp connection
	 *
	 * @param string $directory The path to the directory we want to change to
	 * @return bool Whether or not the operation was successful
	 */
	public function changeDirectory(string $directory): bool
	{
		if (($this->connection ?? false) === false) {
			return false;
		}

		$directory = $this->normalizeFilename($directory);

		return $this->tryCall('chdir', $this->connection, $directory);
	}

	/**
	 * Changes a files attributes (chmod)
	 *
	 * @param string $$filename The file to CHMOD
	 * @param int|string $chmod The value for the CHMOD operation
	 * @return bool Whether or not the operation was successful
	 */
	public function changePermissions(string $filename, string $chmod): bool
	{
		if (($this->connection ?? false) === false) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		return $this->tryCall('chmod', $this->connection, decoct((int) $chmod), $filename);
	}

	/**
	 * Creates a new directory on the server
	 *
	 * @param string $directory The name of the directory to create
	 * @return bool Whether or not the operation was successful
	 */
	public function createDirectory(string $directory): bool
	{
		if (($this->connection ?? false) === false) {
			return false;
		}

		$directory = $this->normalizeFilename($directory);

		return $this->tryCall('mkdir', $this->connection, $directory);
	}

	/**
	 * Deletes a directory.
	 * If this fails, we attempt to delete as a file.
	 *
	 * @param string $directory The directory to delete
	 * @return bool Whether or not the operation was successful
	 */
	public function deleteDirectory(string $directory): bool
	{
		if (($this->connection ?? false) === false) {
			return false;
		}

		$directory = $this->normalizeFilename($directory);

		if ($this->tryCall('rmdir', $this->connection, $directory)) {
			return true;
		}

		// Try to just unlink it, maybe we sent a file.
		if ($this->tryCall('delete', $this->connection, $directory)) {
			ErrorHandler::log(Lang::getTxt('filesystem_error_delete_filedirectory', [], 'Packages'));

			return true;
		}

			return false;

	}

	/**
	 * Creates a new file on the server
	 *
	 * @param string $filename The file to create
	 * @return bool Whether or not the file was created successfully
	 */
	public function createFile(string $filename): bool
	{
		return $this->writeFile($filename, null);
	}

	/**
	 * Deletes a file
	 * If this fails, we attempt to delete as a directory
	 *
	 * @param string $filename The file to delete
	 * @return bool Whether or not the operation was successful
	 */
	public function deleteFile(string $filename): bool
	{
		if (($this->connection ?? false) === false) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		if ($this->tryCall('delete', $this->connection, $filename)) {
			return true;
		}

		// It failed, its possible this is a directory.
		if ($this->tryCall('rmdir', $this->connection, $filename)) {
			ErrorHandler::log(Lang::getTxt('filesystem_error_delete_directoryfile', [], 'Packages'));

			return true;
		}

			return false;

	}

	/**
	 * Writes contents to a file.
	 * If file does not exist, we create it first.
	 *
	 * @param string $filename The file to create
	 * @return bool Whether or not the file was created successfully
	 */
	public function writeFile(string $filename, ?string $contents): bool
	{
		if (($this->connection ?? false) === false) {
			return false;
		}

		if (!$this->tryCall('pasv', $this->connection, true)) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		$stream = fopen('data://text/plain,' . $contents, 'r');

		$result = $this->tryCall('fput', $this->connection, $filename, $stream, FTP_BINARY);

		$this->tryCall('pasv', $this->connection, false);

		return $result;
	}

	public function detectForumPath(string $directory, ?string $lookup_file = null): array
	{
		// Start of with the base logic.
		[$username, $path] = parent::detectForumPath($directory, $lookup_file);

		if (($this->connection ?? false) === false) {
			return [$username, $path, false];
		}

		$lookup_file ??= $_SERVER['PHP_SELF'];
		$files = $this->tryCall('mlsd', $this->connection, $directory);

		$found_path = array_search($lookup_file, array_column($files, 'name')) !== false;

		return [$username, $path, $found_path];
	}

	/**
	 * If we destroy this class, ensure we are disconnected.
	 */
	public function __destruct()
	{
		$this->disconnect();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Determine the server address by cleansing of anything we don't like from the url.
	 * This is unable to use parse_url as we don't pass enough of a url looking string.
	 *
	 * @param string $server_addr
	 * @return string
	 */
	private function getServerAddress(string $server_addr): string
	{
		$server_addr = preg_replace('~^((ft|htt)ps?|ssl)?://~i', '', $server_addr);
		$server_addr = strtr($server_addr, ['/' => '', ':' => '', '@' => '']);

		return $server_addr;
	}

	/**
	 * Wraps the ftp functions, capturing the errors and rethrowing them.
	 * The errors are then captured and put into the error and last mesaage handlers.
	 *
	 * @param mixed $method
	 * @param array $args
	 * @return bool|array
	 */
	private function tryCall(
		$method,
		#[\SensitiveParameter]
		...$args,
	): bool|array {
		try {
			set_error_handler(static function ($severity, $message, $file, $line) {
				throw new \ErrorException($message, 0, $severity, $file, $line);
			});

			return call_user_func('ftp_' . $method, ...$args);
		} catch (\Throwable $e) {
			$this->error = $e->getCode();
			$this->last_message = $e->getMessage();

			return false;
		} finally {
			restore_error_handler();
		}
	}
}
