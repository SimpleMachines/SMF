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

namespace SMF\PackageManager\FileSystem;

interface FileSystemInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Checks if the requirements for the system is available.
	 *
	 * @return bool True if the system is supported, false otherwise.
	 */
	public function isSupported(): bool;

	/**
	 * Checks if the system has been configured for usage.
	 *
	 * @return bool True if the system is configured, false otherwise.
	 */
	public function isConfigured(): bool;

	/**
	 * Gets the Version of the Agent.
	 *
	 * @return string the value of $key.
	 */
	public function getVersion(): string;

	/**
	 * Gets the class identifier of the current file system implementation.
	 *
	 * @return string the unique identifier for the current class implementation.
	 */
	public function getImplementationClassKeyName(): string;

	/**
	 * Provides additional settings for the settings page.
	 *
	 * @param array $config_vars Current configuration settings, passed by reference.  Append to add more.
	 */
	public function getConfigVars(array &$config_vars): void;

	/**
	 * Connects to the file system.
	 *
	 * @return bool Whether or not the system method was connected to.
	 */
	public function connect(
		string $server,
		string $username,
		#[\SensitiveParameter]
		string $password,
		?string $port = null,
		?string $root = null,
	): bool;

	/**
	 * Disconnects to the file system.  Not all handlers will need to disconnect.
	 *
	 * @return bool Whether or not the file system method was connected to.
	 */
	public function disconnect(): bool;

	/**
	 * Determines if we have a connection to the file system.
	 *
	 * @return bool
	 */
	public function isConnected(): bool;

	/**
	 * Changes to a directory (chdir) via the file system
	 *
	 * @param string $directory The path to the directory we want to change to
	 * @return bool Whether or not the operation was successful
	 */
	public function changeDirectory(string $directory): bool;

	/**
	 * Changes a files attributes (chmod)
	 *
	 * @param int|string $chmod The value for the CHMOD operation
	 * @param string $ftp_file The file to CHMOD
	 * @return bool Whether or not the operation was successful
	 */
	public function changePermissions(string $filename, string $chmod): bool;

	/**
	 * Creates a new directory on the file system
	 *
	 * @param string $directory The name of the directory to create
	 * @return bool Whether or not the operation was successful
	 */
	public function createDirectory(string $directory): bool;

	/**
	 * Deletes a directory on the file system
	 *
	 * @param string $directory The directory to delete
	 * @return bool Whether or not the operation was successful
	 */
	public function deleteDirectory(string $directory): bool;

	/**
	 * Creates a new file on the file system
	 *
	 * @param string $filename The file to create
	 * @return bool Whether or not the file was created successfully
	 */
	public function createFile(string $filename): bool;

	/**
	 * Deletes a file on the file system
	 *
	 * @param string $filename The file to delete
	 * @return bool Whether or not the operation was successful
	 */
	public function deleteFile(string $filename): bool;

	/**
	 * Writes contents to a file.
	 *
	 * @param string $filename The file to create
	 * @return bool Whether or not the file was created successfully
	 */
	public function writeFile(string $filename, ?string $contents): bool;
}
