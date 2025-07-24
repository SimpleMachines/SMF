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
use SMF\PackageManager\FtpConnection;

/**
 * Connects to our FTP server.
 *
 * @see https://tools.ietf.org/html/rfc959
 */
class Ftp extends FileSystem implements FileSystemInterface
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Timeout protection for commands that require waiting.
	 * @var int
	 */
	private const RESPONSE_TIMEOUT = 5;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * Do not rely on these, they are for handling deprecated support of FtpConnection.
	 * @var array
	 */
	public array $legacy_pasv = [];

	/**
	 * Do not rely on these, they are for handling deprecated support of FtpConnection.
	 * @var
	 */
	public $legacy_error = null;

	/**
	 * Do not rely on these, they are for handling deprecated support of FtpConnection.
	 * @var
	 */
	public $legacy_last_message = null;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * Address of the server.
	 * @var ?string
	 */
	private ?string $server_addr = null;

	/**
	 * Resource for FTP connection from fsocket
	 * @var ?resource
	 */
	private $connection = null;

	/**
	 * @var array{ip: string, port: int} Contains information about passive mode if used.
	 */
	private array $pasv = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
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
	 * Connects to a FTP server
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

		$this->server_addr = $this->getServerAddress($server);

		if ($root !== null) {
			$this->forum_root = $root;
		}

		// Connect to the FTP server.
		$this->connection = @fsockopen($this->server_addr, $port, $err, $errMsg, self::RESPONSE_TIMEOUT);
		$this->last_message = $errMsg;

		if (!$this->connection) {
			$this->error = 'bad_server';

			return false;
		}

		// Get the welcome message...
		if (!$this->checkResponse(220)) {
			$this->error = 'bad_response';

			return false;
		}

		// Send the username, it should ask for a password.
		fwrite($this->connection, 'USER ' . $username . "\r\n");

		if (!$this->checkResponse(331)) {
			$this->error = 'bad_credentials';

			return false;
		}

		// Now send the password... and hope it goes okay.
		fwrite($this->connection, 'PASS ' . $password . "\r\n");

		if (!$this->checkResponse(230)) {
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
		if (is_resource($this->connection)) {
			fwrite($this->connection, 'QUIT' . "\r\n");
			fclose($this->connection);
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
		$directory = $this->normalizeFilename($directory);

		if (!is_resource($this->connection)) {
			return false;
		}

		// No slash on the end, please...
		$directory = rtrim($directory, '/');

		$directory = $this->normalizeFilename($directory);

		// If we are trying to chdir to the same directory, we most likely meant the forum root.
		if ($directory === '.') {
			$directory = $this->forum_root;
		}

		fwrite($this->connection, 'CWD ' . $directory . "\r\n");

		if (!$this->checkResponse(250)) {
			$this->error = 'bad_path';

			return false;
		}

		return true;
	}

	/**
	 * Changes a files attributes (chmod)
	 *
	 * @param int|string $chmod The value for the CHMOD operation
	 * @param string $ftp_file The file to CHMOD
	 * @return bool Whether or not the operation was successful
	 */
	public function changePermissions(string $filename, string $chmod): bool
	{
		if (!is_resource($this->connection)) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		// Convert the chmod value from octal (0777) to text ("777").
		fwrite($this->connection, 'SITE CHMOD ' . decoct((int) $chmod) . ' ' . $filename . "\r\n");

		if (!$this->checkResponse(200)) {
			$this->error = 'bad_file';

			return false;
		}

		return true;
	}

	/**
	 * Creates a new directory on the server
	 *
	 * @param string $directory The name of the directory to create
	 * @return bool Whether or not the operation was successful
	 */
	public function createDirectory(string $directory): bool
	{
		if (!is_resource($this->connection)) {
			return false;
		}

		$directory = $this->normalizeFilename($directory);

		// Make this new beautiful directory!
		fwrite($this->connection, 'MKD ' . $directory . "\r\n");

		if (!$this->checkResponse(257)) {
			$this->error = 'bad_file';

			return false;
		}

		return true;
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
		if (!is_resource($this->connection)) {
			return false;
		}

		$directory = $this->normalizeFilename($directory);

		// Delete directory.
		fwrite($this->connection, 'RMD ' . $directory . "\r\n");

		// If this failed, its possible they passed a file, not a directory.
		if (!$this->checkResponse(250)) {
		fwrite($this->connection, 'DELE ' . $directory . "\r\n");

			// Still no love?
			if (!$this->checkResponse(250)) {
				$this->error = 'bad_file';

				return false;
			}
				ErrorHandler::log(Lang::getTxt('filesystem_error_delete_filedirectory', [], 'Packages'));

		}

		return true;
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
		if (!is_resource($this->connection)) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		// Delete file X.
		fwrite($this->connection, 'DELE ' . $filename . "\r\n");

		// If this failed, its possible they passed a directory, not a file.
		if (!$this->checkResponse(250)) {
		fwrite($this->connection, 'RMD ' . $filename . "\r\n");

			// Still no love?
			if (!$this->checkResponse(250)) {
				$this->error = 'bad_file';

				return false;
			}
				ErrorHandler::log(Lang::getTxt('filesystem_error_delete_directoryfile', [], 'Packages'));

		}

		return true;
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
		if (!is_resource($this->connection)) {
			return false;
		}

		if (!$this->enterPassiveMode()) {
			return false;
		}

		$filename = $this->normalizeFilename($filename);

		// Seems logical enough, so far...
		fwrite($this->connection, 'STOR ' . $filename . "\r\n");

		// Okay, now we connect to the data port.  If it doesn't work out, it's probably "file already exists", etc.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $errMsg, 5);
		$this->last_message = $errMsg;

		if (!is_resource($fp) || !$this->checkResponse(150)) {
			$this->error = 'bad_file';

			if (is_resource($fp)) {
				fclose($fp);
			}

			return false;
		}

		// Write in chunks.
		if ($contents !== null) {
			$pieces = str_split($contents, 1024 * 4);

			foreach ($pieces as $piece) {
				fwrite($fp, $piece, strlen($piece));
			}
		}
		fclose($fp);

		if (!$this->checkResponse(226)) {
			$this->error = 'bad_response';

			return false;
		}

		return true;
	}

	public function detectForumPath(string $directory, ?string $lookup_file = null): array
	{
		// Start of with the base logic.
		[$username, $path] = parent::detectForumPath($directory, $lookup_file);

		if ($this->connection === null) {
			return [$username, $path, false];
		}

		// Determine if we can find this.
		$found_path = false;

		if ($this->listDirectory($path) == '') {
			$data = $this->listDirectory('', true);

			if ($lookup_file === null) {
				$lookup_file = $_SERVER['PHP_SELF'];
			}

			$found_path = dirname($this->locate('*' . basename(dirname($lookup_file)) . '/' . basename($lookup_file), $data));

			if ($found_path == false) {
				$found_path = dirname($this->locate(basename($lookup_file)));
			}

			if ($found_path != false) {
				$path = $found_path;
			}
		} elseif (is_resource($this->connection)) {
			$found_path = true;
		}

		return [$username, $path, $found_path];
	}

	/**
	 * For our deprecated FtpConnection class, returns the passive mode data.
	 *
	 * @return array{ip: string, port: int}
	 */
	public function getPassiveMode(): array
	{
		return $this->pasv;
	}

	/**
	 * Currently this just handles some support for legacy FtpConnection.
	 */
	public function __construct()
	{
		$this->legacy_pasv = &$this->pasv;
		$this->legacy_error = &$this->error;
		$this->legacy_last_message = &$this->last_message;
	}

	/**
	 * If we destroy this class, ensure we are disconnected.
	 */
	public function __destruct()
	{
		$this->disconnect();
	}

	/**
	 * Determine the server address by cleansing of anything we don't like from the url.
	 * This is unable to use parse_url as we don't pass enough of a url looking string.
	 *
	 * @param string $server_addr
	 * @return string
	 */
	public function getServerAddress(string $server_addr): string
	{
		$server_addr = preg_replace('~^((ft|htt)ps?|ssl)?://~i', '', $server_addr);
		$server_addr = strtr($server_addr, ['/' => '', ':' => '', '@' => '']);

		return $server_addr;
	}

	/**
	 * Used to create a passive connection.
	 * Attempts to use Extended Passive mode for IPv6 support.
	 * Falls back to PASV, which fails with IPv6 connections.
	 *
	 * @todo When Deprecated class FtpConnection is removed, this can become protected.
	 * @return bool Whether the passive connection was created successfully
	 */
	public function enterPassiveMode(): bool
	{
		if (!is_resource($this->connection)) {
			$this->error = 'no_connection';

			return false;
		}

		// Lets try EPSV, it supports IPv6.
		@fwrite($this->connection, 'EPSV' . "\r\n");
		$time = time();

		do {
			$response = fgets($this->connection, 1024);
		} while (strpos($response, ' ', 3) !== 3 && time() - $time < 5);

		// If it's not 229, we weren't given an port, which means it failed.
		if (str_starts_with($response, '229 ')) {
			// Snatch the port information.
			if (preg_match('~\(\|{3}(\d+)\|\)~', $response, $match) !== 0) {
				// EPSV assumes that the IP is the address you connected to.
				$this->pasv = ['ip' => $this->server_addr, 'port' => (int) $match[1]];

				return true;
			}
		}

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		@fwrite($this->connection, 'PASV' . "\r\n");
		$time = time();

		do {
			$response = fgets($this->connection, 1024);
		} while (strpos($response, ' ', 3) !== 3 && time() - $time < self::RESPONSE_TIMEOUT);

		// If it's not 227, we weren't given an IP and port, which means it failed.
		if (!str_starts_with($response, '227 ')) {
			$this->error = 'bad_response';

			return false;
		}

		// Snatch the IP and port information, or die horribly trying...
		if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $response, $match) == 0) {
			$this->error = 'bad_response';

			return false;
		}

		// This is pretty simple - store it for later use ;).
		$this->pasv = ['ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4], 'port' => $match[5] * 256 + $match[6]];

		return true;
	}

	/**
	 * Generates a directory listing for the current directory
	 *
	 * @todo When Deprecated class FtpConnection is removed, this can become private.
	 * @param string $ftp_path The path to the directory
	 * @param bool $search Whether or not to get a recursive directory listing
	 * @return string|bool The results of the command or false if unsuccessful
	 */
	public function listDirectory(string $ftp_path = '', bool $search = false): string|bool
	{
		if (!is_resource($this->connection)) {
			return false;
		}

		// Passive... non-aggressive...
		if (!$this->enterPassiveMode()) {
			return false;
		}

		// Get the listing!
		fwrite($this->connection, 'LIST -1' . ($search ? 'R' : '') . ($ftp_path == '' ? '' : ' ' . $ftp_path) . "\r\n");

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err_msg, 5);

		if (!empty($err_msg)) {
			$this->last_message = $err_msg;

			return false;
		}

		if (!is_resource($fp) || !$this->checkResponse([150, 125])) {
			$this->error = 'bad_response';

			if (is_resource($fp)) {
				fclose($fp);
			}

			return false;
		}

		// Read in the file listing.
		$data = '';

		while (!feof($fp)) {
			$data .= fread($fp, 4096);
		}
		fclose($fp);

		// Everything go okay?
		if (!$this->checkResponse(226)) {
			$this->error = 'bad_response';

			return false;
		}

		return $data;
	}

	/**
	 * Determines the current directory we are in
	 *
	 * @todo When Deprecated class FtpConnection is removed, this can become private.
	 * @param string $file The name of a file
	 * @param null|string $listing A directory listing or null to generate one
	 * @return string|bool The name of the file or false if it wasn't found
	 */
	public function locate(string $file, ?string $listing = null): string|bool
	{
		if ($listing === null) {
			$listing = $this->listDirectory('', true);
		}
		$listing = explode("\n", $listing);

		@fwrite($this->connection, 'PWD' . "\r\n");
		$time = time();

		do {
			$response = fgets($this->connection, 1024);
		} while ($response[3] != ' ' && time() - $time < 5);

		// Check for 257!
		if (preg_match('~^257 "(.+?)" ~', $response, $match) != 0) {
			$current_dir = strtr($match[1], ['""' => '"']);
		} else {
			$current_dir = '';
		}

		for ($i = 0, $n = count($listing); $i < $n; $i++) {
			if (trim($listing[$i]) == '' && isset($listing[$i + 1])) {
				$current_dir = substr(trim($listing[++$i]), 0, -1);
				$i++;
			}

			// Okay, this file's name is:
			$listing[$i] = $current_dir . '/' . trim(strlen($listing[$i]) > 30 ? strrchr($listing[$i], ' ') : $listing[$i]);

			if ($file[0] == '*' && substr($listing[$i], -(strlen($file) - 1)) == substr($file, 1)) {
				return $listing[$i];
			}

			if (str_ends_with($file, '*') && substr($listing[$i], 0, strlen($file) - 1) == substr($file, 0, -1)) {
				return $listing[$i];
			}

			if (basename($listing[$i]) == $file || $listing[$i] == $file) {
				return $listing[$i];
			}
		}

		return false;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Reads the response to the command from the server
	 *
	 * @param int|string|array $desired The desired response
	 * @return bool Whether or not we got the desired response
	 */
	protected function checkResponse(int|string|array $desired): bool
	{
		// Wait for a response that isn't continued with -, but don't wait too long.
		$time = time();

		do {
			$this->last_message = fgets($this->connection, 1024);

			if ($this->last_message === false) {
				return false;
			}
		} while ((strlen($this->last_message) < 4 || str_starts_with($this->last_message, ' ') || strpos($this->last_message, ' ', 3) !== 3) && time() - $time < self::RESPONSE_TIMEOUT);

		// Was the desired response returned?
		return is_array($desired) ? in_array(substr($this->last_message, 0, 3), $desired) : substr($this->last_message, 0, 3) == $desired;
	}
}
