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

declare(strict_types=1);

namespace SMF\PackageManager;

/**
 * Class FtpConnection
 * Simple FTP protocol implementation.
 *
 * @see https://tools.ietf.org/html/rfc959
 */
class FtpConnection
{
	/**
	 * @var resource Holds the connection response
	 */
	public $connection;

	/**
	 * @var string Holds any errors
	 */
	public $error;

	/**
	 * @var string Holds the last message from the server
	 */
	public $last_message;

	/**
	 * @var bool Whether or not this is a passive connection
	 */
	public $pasv;

	/**
	 * Create a new FTP connection...
	 *
	 * @param string $ftp_server The server to connect to
	 * @param int $ftp_port The port to connect to
	 * @param string $ftp_user The username
	 * @param string $ftp_pass The password
	 */
	public function __construct(
		?string $ftp_server,
		int $ftp_port = 21,
		string $ftp_user = 'anonymous',
		#[\SensitiveParameter]
		string $ftp_pass = 'ftpclient@simplemachines.org',
	) {
		// Initialize variables.
		$this->connection = 'no_connection';
		$this->error = false;
		$this->pasv = [];

		if ($ftp_server !== null) {
			$this->connect($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
		}
	}

	/**
	 * Connects to a server
	 *
	 * @param string $ftp_server The address of the server
	 * @param int $ftp_port The port
	 * @param string $ftp_user The username
	 * @param string $ftp_pass The password
	 */
	public function connect(
		string $ftp_server,
		int $ftp_port = 21,
		string $ftp_user = 'anonymous',
		#[\SensitiveParameter]
		string $ftp_pass = 'ftpclient@simplemachines.org',
	): void {
		if (strpos($ftp_server, 'ftp://') === 0) {
			$ftp_server = substr($ftp_server, 6);
		} elseif (strpos($ftp_server, 'ftps://') === 0) {
			$ftp_server = 'ssl://' . substr($ftp_server, 7);
		}

		if (strpos($ftp_server, 'http://') === 0) {
			$ftp_server = substr($ftp_server, 7);
		} elseif (strpos($ftp_server, 'https://') === 0) {
			$ftp_server = substr($ftp_server, 8);
		}
		$ftp_server = strtr($ftp_server, ['/' => '', ':' => '', '@' => '']);

		// Connect to the FTP server.
		$this->connection = @fsockopen($ftp_server, $ftp_port, $err, $err, 5);

		if (!$this->connection) {
			$this->error = 'bad_server';
			$this->last_message = 'Invalid Server';

			return;
		}

		// Get the welcome message...
		if (!$this->check_response(220)) {
			$this->error = 'bad_response';
			$this->last_message = 'Bad Response';

			return;
		}

		// Send the username, it should ask for a password.
		fwrite($this->connection, 'USER ' . $ftp_user . "\r\n");

		if (!$this->check_response(331)) {
			$this->error = 'bad_username';
			$this->last_message = 'Invalid Username';

			return;
		}

		// Now send the password... and hope it goes okay.

		fwrite($this->connection, 'PASS ' . $ftp_pass . "\r\n");

		if (!$this->check_response(230)) {
			$this->error = 'bad_password';
			$this->last_message = 'Invalid Password';

			return;
		}
	}

	/**
	 * Changes to a directory (chdir) via the ftp connection
	 *
	 * @param string $ftp_path The path to the directory we want to change to
	 * @return bool Whether or not the operation was successful
	 */
	public function chdir(string $ftp_path): bool
	{
		if (!is_resource($this->connection)) {
			return false;
		}

		// No slash on the end, please...
		if ($ftp_path !== '/' && substr($ftp_path, -1) === '/') {
			$ftp_path = substr($ftp_path, 0, -1);
		}

		fwrite($this->connection, 'CWD ' . $ftp_path . "\r\n");

		if (!$this->check_response(250)) {
			$this->error = 'bad_path';

			return false;
		}

		return true;
	}

	/**
	 * Changes a files attributes (chmod)
	 *
	 * @param string $ftp_file The file to CHMOD
	 * @param int|string $chmod The value for the CHMOD operation
	 * @return bool Whether or not the operation was successful
	 */
	public function chmod(string $ftp_file, int|string $chmod): bool
	{
		if (!is_resource($this->connection)) {
			return false;
		}

		if ($ftp_file == '') {
			$ftp_file = '.';
		}

		// Do we have a file or a dir?
		$is_dir = is_dir($ftp_file);
		$is_writable = false;

		// Set different modes.
		$chmod_values = $is_dir ? [0750, 0755, 0775, 0777] : [0644, 0664, 0666];

		foreach ($chmod_values as $val) {
			// If it's writable, break out of the loop.
			if (is_writable($ftp_file)) {
				$is_writable = true;
				break;
			}

			// Convert the chmod value from octal (0777) to text ("777").
			fwrite($this->connection, 'SITE CHMOD ' . decoct($val) . ' ' . $ftp_file . "\r\n");

			if (!$this->check_response(200)) {
				$this->error = 'bad_file';
				break;
			}
		}

		return $is_writable;
	}

	/**
	 * Deletes a file
	 *
	 * @param string $ftp_file The file to delete
	 * @return bool Whether or not the operation was successful
	 */
	public function unlink(string $ftp_file): bool
	{
		// We are actually connected, right?
		if (!is_resource($this->connection)) {
			return false;
		}

		// Delete file X.
		fwrite($this->connection, 'DELE ' . $ftp_file . "\r\n");

		if (!$this->check_response(250)) {
			fwrite($this->connection, 'RMD ' . $ftp_file . "\r\n");

			// Still no love?
			if (!$this->check_response(250)) {
				$this->error = 'bad_file';

				return false;
			}
		}

		return true;
	}

	/**
	 * Reads the response to the command from the server
	 *
	 * @param int|string|array $desired The desired response
	 * @return bool Whether or not we got the desired response
	 */
	public function check_response(int|string|array $desired): bool
	{
		// Wait for a response that isn't continued with -, but don't wait too long.
		$time = time();

		do {
			$this->last_message = fgets($this->connection, 1024);
		} while ((strlen($this->last_message) < 4 || strpos($this->last_message, ' ') === 0 || strpos($this->last_message, ' ', 3) !== 3) && time() - $time < 5);

		// Was the desired response returned?
		return is_array($desired) ? in_array(substr($this->last_message, 0, 3), $desired) : substr($this->last_message, 0, 3) == $desired;
	}

	/**
	 * Used to create a passive connection
	 *
	 * @return bool Whether the passive connection was created successfully
	 */
	public function passive(): bool
	{
		// We can't create a passive data connection without a primary one first being there.
		if (!is_resource($this->connection)) {
			$this->error = 'no_connection';

			return false;
		}

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		@fwrite($this->connection, 'PASV' . "\r\n");
		$time = time();

		do {
			$response = fgets($this->connection, 1024);
		} while (strpos($response, ' ', 3) !== 3 && time() - $time < 5);

		// If it's not 227, we weren't given an IP and port, which means it failed.
		if (strpos($response, '227 ') !== 0) {
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
	 * Creates a new file on the server
	 *
	 * @param string $ftp_file The file to create
	 * @return bool Whether or not the file was created successfully
	 */
	public function create_file(string $ftp_file): bool
	{
		// First, we have to be connected... very important.
		if (!is_resource($this->connection)) {
			return false;
		}

		// I'd like one passive mode, please!
		if (!$this->passive()) {
			return false;
		}

		// Seems logical enough, so far...
		fwrite($this->connection, 'STOR ' . $ftp_file . "\r\n");

		// Okay, now we connect to the data port.  If it doesn't work out, it's probably "file already exists", etc.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);

		if (!$fp || !$this->check_response(150)) {
			$this->error = 'bad_file';
			@fclose($fp);

			return false;
		}

		// This may look strange, but we're just closing it to indicate a zero-byte upload.
		fclose($fp);

		if (!$this->check_response(226)) {
			$this->error = 'bad_response';

			return false;
		}

		return true;
	}

	/**
	 * Generates a directory listing for the current directory
	 *
	 * @param string $ftp_path The path to the directory
	 * @param bool $search Whether or not to get a recursive directory listing
	 * @return string|bool The results of the command or false if unsuccessful
	 */
	public function list_dir(string $ftp_path = '', bool $search = false): string|bool
	{
		// Are we even connected...?
		if (!is_resource($this->connection)) {
			return false;
		}

		// Passive... non-aggressive...
		if (!$this->passive()) {
			return false;
		}

		// Get the listing!
		fwrite($this->connection, 'LIST -1' . ($search ? 'R' : '') . ($ftp_path == '' ? '' : ' ' . $ftp_path) . "\r\n");

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);

		if (!$fp || !$this->check_response([150, 125])) {
			$this->error = 'bad_response';
			@fclose($fp);

			return false;
		}

		// Read in the file listing.
		$data = '';

		while (!feof($fp)) {
			$data .= fread($fp, 4096);
		}
		fclose($fp);

		// Everything go okay?
		if (!$this->check_response(226)) {
			$this->error = 'bad_response';

			return false;
		}

		return $data;
	}

	/**
	 * Determines the current directory we are in
	 *
	 * @param string $file The name of a file
	 * @param string $listing A directory listing or null to generate one
	 * @return string|bool The name of the file or false if it wasn't found
	 */
	public function locate(string $file, ?string $listing = null): string|bool
	{
		if ($listing === null) {
			$listing = $this->list_dir('', true);
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

			if (substr($file, -1) == '*' && substr($listing[$i], 0, strlen($file) - 1) == substr($file, 0, -1)) {
				return $listing[$i];
			}

			if (basename($listing[$i]) == $file || $listing[$i] == $file) {
				return $listing[$i];
			}
		}

		return false;
	}

	/**
	 * Creates a new directory on the server
	 *
	 * @param string $ftp_dir The name of the directory to create
	 * @return bool Whether or not the operation was successful
	 */
	public function create_dir(string $ftp_dir): bool
	{
		// We must be connected to the server to do something.
		if (!is_resource($this->connection)) {
			return false;
		}

		// Make this new beautiful directory!
		fwrite($this->connection, 'MKD ' . $ftp_dir . "\r\n");

		if (!$this->check_response(257)) {
			$this->error = 'bad_file';

			return false;
		}

		return true;
	}

	/**
	 * Detects the current path
	 *
	 * @param string $filesystem_path The full path from the filesystem
	 * @param string $lookup_file The name of a file in the specified path
	 * @return array An array of detected info - username, path from FTP root and whether or not the current path was found
	 */
	public function detect_path(string $filesystem_path, ?string $lookup_file = null): array
	{
		$username = '';

		if (isset($_SERVER['DOCUMENT_ROOT'])) {
			if (preg_match('~^/home[2]?/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match)) {
				$username = $match[1];

				$path = strtr($_SERVER['DOCUMENT_ROOT'], ['/home/' . $match[1] . '/' => '', '/home2/' . $match[1] . '/' => '']);

				if (substr($path, -1) == '/') {
					$path = substr($path, 0, -1);
				}

				if (strlen(dirname($_SERVER['PHP_SELF'])) > 1) {
					$path .= dirname($_SERVER['PHP_SELF']);
				}
			} elseif (strpos($filesystem_path, '/var/www/') === 0) {
				$path = substr($filesystem_path, 8);
			} else {
				$path = strtr(strtr($filesystem_path, ['\\' => '/']), [$_SERVER['DOCUMENT_ROOT'] => '']);
			}
		} else {
			$path = '';
		}

		if (is_resource($this->connection) && $this->list_dir($path) == '') {
			$data = $this->list_dir('', true);

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

		return [$username, $path, isset($found_path)];
	}

	/**
	 * Close the ftp connection
	 *
	 * @return bool Always returns true
	 */
	public function close(): bool
	{
		// Goodbye!
		fwrite($this->connection, 'QUIT' . "\r\n");
		fclose($this->connection);

		return true;
	}
}

?>