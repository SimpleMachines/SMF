<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\PackageManager;

use SMF\PackageManager\FileSystem\APIs\Ftp;
use SMF\PackageManager\FileSystem\APIs\FtpSSL;

/**
 * Class FtpConnection
 * Simple FTP protocol implementation.
 *
 * @see https://tools.ietf.org/html/rfc959
 * @deprecated Use Ftp File system handler.
 */
class FtpConnection
{
	/*******************
	 * Public properties
	 *******************/

	// /*******************
	//  * Public properties
	//  *******************/

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
	 * @var array{ip: string, port: int} Contains information about passive mode if used.
	 */
	public array $pasv = [];

	/*********************
	 * Internal properties
	 *********************/

	private Ftp|FtpSSL $ftp;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Create a new FTP connection...
	 *
	 * @param ?string $ftp_server The server to connect to
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
			$this->ftp = new Ftp();
			$this->connect($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
		}

		$this->error = &$this->ftp->legacy_error;
		$this->last_message = &$this->ftp->legacy_last_message;
		$this->pasv = &$this->ftp->legacy_pasv;
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
		if (str_starts_with($ftp_server, 'ftps://') || str_starts_with($ftp_server, 'ssl://')) {
			$this->ftp = new FtpSSL();
		}

		// Simply connect, ignore the response.
		$this->ftp->connect($ftp_server, $ftp_user, $ftp_pass, $ftp_port);
	}

	/**
	 * Changes to a directory (chdir) via the ftp connection
	 *
	 * @param string $ftp_path The path to the directory we want to change to
	 * @return bool Whether or not the operation was successful
	 */
	public function chdir(string $ftp_path): bool
	{
		if (!\is_resource($this->ftp)) {
			return false;
		}

		return $this->ftp->changeDirectory($ftp_path);
	}

	/**
	 * Changes a files attributes (chmod)
	 * Do not attempt to check is_dir, is_writable, etc.  $ftp_file contains the local FTP path.
	 *
	 * @param string $ftp_file The file to CHMOD
	 * @param int|string $chmod The value for the CHMOD operation
	 * @return bool Whether or not the operation was successful
	 */
	public function chmod(string $ftp_file, int|string $chmod): bool
	{
		if (!\is_resource($this->ftp)) {
			return false;
		}

		return $this->ftp->changePermissions($ftp_file, $chmod);
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
		if (!\is_resource($this->ftp)) {
			return false;
		}

		// The old way just deleted a file and when failed, deleted as a directory.
		return $this->ftp->deleteFile($ftp_file);
	}

	/**
	 * Reads the response to the command from the server
	 *
	 * @param int|string|array $desired The desired response
	 * @return bool Whether or not we got the desired response
	 */
	public function check_response(int|string|array $desired): bool
	{
		if (!\is_resource($this->ftp)) {
			return false;
		}

		// Was the desired response returned?
		return \is_array($desired) ? \in_array(substr($this->last_message, 0, 3), $desired) : substr($this->last_message, 0, 3) == $desired;
	}

	/**
	 * Used to create a passive connection
	 *
	 * @return bool Whether the passive connection was created successfully
	 */
	public function passive(): bool
	{
		if (!\is_resource($this->ftp)) {
			return false;
		}

		return $this->ftp->enterPassiveMode();
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
		if (!\is_resource($this->ftp)) {
			return false;
		}

		return $this->ftp->createFile($ftp_file);
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
		if (!\is_resource($this->ftp)) {
			return false;
		}

		return $this->ftp->listDirectory($ftp_path, $search);
	}

	/**
	 * Determines the current directory we are in
	 *
	 * @param string $file The name of a file
	 * @param null|string $listing A directory listing or null to generate one
	 * @return string|bool The name of the file or false if it wasn't found
	 */
	public function locate(string $file, ?string $listing = null): string|bool
	{
		if (!\is_resource($this->ftp)) {
			return false;
		}

		return $this->ftp->locate($file, $listing);
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
		if (!\is_resource($this->ftp)) {
			return false;
		}

		return $this->ftp->createDirectory($ftp_dir);
	}

	/**
	 * Close the ftp connection
	 *
	 * @return bool Always returns true
	 */
	public function close(): bool
	{
		if (!\is_resource($this->ftp)) {
			return false;
		}

		return $this->ftp->disconnect();
	}
}
