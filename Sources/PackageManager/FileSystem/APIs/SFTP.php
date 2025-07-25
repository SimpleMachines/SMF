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

use phpseclib3\Net\SFTP as PhpSFTP;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\PackageManager\FileSystem\FileSystem;
use SMF\PackageManager\FileSystem\FileSystemInterface;

/**
 * Connects to our SSH enabled FTP.
 */
class SFTP extends FileSystem implements FileSystemInterface
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
	 * Resource for the SFTP connetion.
	 *
	 * @var PhpSFTP
	 */
	private ?PhpSFTP $connection = null;

	/****************
	 * Public methods
	 ****************/

	public function isSupported(): bool
	{
		return true;
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
			$port = 22;
		}

		if ($root !== null) {
			$this->forum_root = $root;
		}

		try {
			$this->connection = new PhpSFTP($this->getServerAddress($server), $port, self::RESPONSE_TIMEOUT);

			if ($this->connection->getLastSFTPError() !== '') {
				$this->error = 'bad_server';
				$this->last_message = $this->connection->getLastSFTPError();

				return false;
			}
		} catch (\Exception $e) {
			$this->error = 'bad_server';
			$this->last_message = $e->getMessage();
		}

		try {
			// login with username and password
			if (!$this->connection->login($username, $password)) {
				$this->connection = null;
				$this->error = 'bad_credentials';
				$this->last_message = $this->connection->getLastSFTPError();

				return false;
			}
		} catch (\Exception $e) {
				$this->error = 'bad_credentials';
			$this->last_message = $e->getMessage();
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
		if ($this->connection instanceof PhpSFTP) {
			$this->connection->disconnect();
		}

		$this->connection = null;
		$this->error = null;
		$this->last_message = null;

		return true;
	}

	/**
	 * Determines if we have a connection to the sftp server.
	 *
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return $this->connection instanceof PhpSFTP && empty($this->error);
	}

	/**
	 * Changes to a directory (chdir) via the ftp connection
	 *
	 * @param string $directory The path to the directory we want to change to
	 * @return bool Whether or not the operation was successful
	 */
	public function changeDirectory(string $directory): bool
	{
		if (!$this->connection instanceof PhpSFTP || !$this->connection->isAuthenticated()) {
			return false;
		}

		$directory = $this->normalizeFilename($directory);

		try {
			return $this->connection->chdir($directory);
		} catch (\Exception $e) {
			$this->error = 'bad_path';
			$this->last_message = $e->getMessage();
		}

		return false;
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
		if (!$this->connection instanceof PhpSFTP || !$this->connection->isAuthenticated()) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		try {
			return $this->connection->chmod($chmod, $filename) !== false;
		} catch (\Exception $e) {
			$this->error = 'bad_file';
			$this->last_message = $e->getMessage();
		}

		return false;
	}

	/**
	 * Creates a new directory on the server
	 *
	 * @param string $directory The name of the directory to create
	 * @return bool Whether or not the operation was successful
	 */
	public function createDirectory(string $directory): bool
	{
		if (!$this->connection instanceof PhpSFTP || !$this->connection->isAuthenticated()) {
			return false;
		}

		$directory = $this->normalizeFilename($directory);

		try {
			return $this->connection->mkdir($directory);
		} catch (\Exception $e) {
			$this->error = 'bad_path';
			$this->last_message = $e->getMessage();
		}

		return false;
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
		if (!$this->connection instanceof PhpSFTP || !$this->connection->isAuthenticated()) {
			return false;
		}

		$directory = $this->normalizeFilename($directory);

		try {
			if ($this->connection->rmdir($directory)) {
				return true;
			}

			// Try to just unlink it, maybe we sent a file.
			if ($this->connection->delete($directory)) {
				ErrorHandler::log(Lang::getTxt('filesystem_error_delete_filedirectory', [], 'Packages'));

				return true;
			}

				return false;

		} catch (\Exception $e) {
			$this->error = 'bad_path';
			$this->last_message = $e->getMessage();
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
		if (!$this->connection instanceof PhpSFTP || !$this->connection->isAuthenticated()) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		try {
			if ($this->connection->delete($filename)) {
				return true;
			}

			// It failed, its possible this is a directory.
			if ($this->connection->rmdir($filename)) {
				ErrorHandler::log(Lang::getTxt('filesystem_error_delete_directoryfile', [], 'Packages'));

				return true;
			}

				return false;

		} catch (\Exception $e) {
			$this->error = 'bad_file';
			$this->last_message = $e->getMessage();
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
		if (!$this->connection instanceof PhpSFTP || !$this->connection->isAuthenticated()) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		try {
			return $this->connection->put($filename, $contents);
		} catch (\Exception $e) {
			$this->error = 'bad_file';
			$this->last_message = $e->getMessage();
		}

		return false;
	}

	public function detectForumPath(string $directory, ?string $lookup_file = null): array
	{
		// Start of with the base logic.
		[$username, $path] = parent::detectForumPath($directory, $lookup_file);
		$found_path = false;

		if (!$this->connection instanceof PhpSFTP || !$this->connection->isAuthenticated()) {
			return [$username, $path, false];
		}

		try {
			$lookup_file ??= $_SERVER['PHP_SELF'];
			$files = $this->connection->nlist($directory);

			if (is_array($files)) {
				$found_path = array_search($lookup_file, $files) !== false;
			}
		} catch (\Exception $e) {
			$this->error = 'bad_response';
			$this->last_message = $e->getMessage();
		}

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
		$server_addr = preg_replace('~^(s(ftp|s[h|l]))?://~i', '', $server_addr);
		$server_addr = strtr($server_addr, ['/' => '', ':' => '', '@' => '']);

		return $server_addr;
	}
}
