<?php

declare(strict_types=1);

namespace SMF;

use SMF\Infrastructure\Container;
use SMF\Services\ErrorHandlerService;

/**
 * SMF's error handler.
 *
 * Also provides methods for logging and/or dying when errors occur.
 *
 * This class acts as a facade/proxy to the ErrorHandlerService, providing
 * backward compatibility with the old static API while leveraging the
 * dependency injection container for proper service management.
 * @deprecated Use SMF\Services\ErrorHandlerService via Container
 */
class ErrorHandler
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * What types of categories do we have for logging errors?
	 *
	 * @deprecated Use ErrorHandlerService::$known_error_types instead.
	 *             Kept for backward compatibility.
	 */
	public static array $known_error_types = [
		'general',
		'critical',
		'database',
		'undefined_vars',
		'user',
		'ban',
		'template',
		'debug',
		'cron',
		'paidsubs',
		'backup',
		'login',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var ErrorHandlerService|null
	 *
	 * Cached service instance.
	 */
	protected static ?ErrorHandlerService $service = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $error_level A pre-defined error-handling constant (see {@link https://php.net/errorfunc.constants})
	 * @param string $error_string The error message
	 * @param string $file The file where the error occurred
	 * @param int $line The line where the error occurred
	 */
	public function __construct(int $error_level, string $error_string, string $file, int $line)
	{
		self::getService()->handleError($error_level, $error_string, $file, $line);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience method to create an instance of this class.
	 *
	 * @param int $error_level A pre-defined error-handling constant.
	 *    (see {@link https://php.net/errorfunc.constants})
	 * @param string $error_string The error message.
	 * @param string $file The file where the error occurred.
	 * @param int $line The line where the error occurred.
	 */
	public static function call(int $error_level, string $error_string, string $file, int $line): void
	{
		self::getService()->call($error_level, $error_string, $file, $line);
	}

	/**
	 * Generic handler for uncaught exceptions.
	 *
	 * Always ends execution.
	 *
	 * @param \Throwable $e The uncaught exception.
	 */
	public static function catch(\Throwable $e): void
	{
		self::getService()->catch($e);
	}

	/**
	 * Log an error, if the error logging is enabled.
	 *
	 * $file and $line should be __FILE__ and __LINE__, respectively.
	 *
	 * Example use:
	 *  die(ErrorHandler::log($msg));
	 *
	 * @param string $error_message The message to log.
	 * @param string|bool $error_type The type of error.
	 * @param string $file The name of the file where this error occurred.
	 * @param int $line The line where the error occurred.
	 * @return string The message that was logged.
	 */
	public static function log(string $error_message, string|bool $error_type = 'general', string $file = '', int $line = 0, ?array $backtrace = null): string
	{
		return self::getService()->log($error_message, $error_type, $file, $line, $backtrace);
	}

	/**
	 * Log an exception, if the error logging is enabled.
	 *
	 * $file and $line should be __FILE__ and __LINE__, respectively.
	 *
	 * Example use:
	 *  die(ErrorHandler::log($msg));
	 *
	 * @param \Exception $ex The message to log.
	 * @param string|bool|null $error_type The type of error.
	 */
	public static function logException(\Exception $ex, string|bool|null $error_type = null): string
	{
		return self::getService()->log($ex->getMessage(), $error_type ?? \get_class($ex), $ex->getFile(), $ex->getLine(), $ex->getTrace());
	}

	/**
	 * An unrecoverable error.
	 *
	 * This function stops execution and displays an error message.
	 * It logs the error message if $log is specified.
	 *
	 * @param string $error The error message
	 * @param string|bool $log What type of error to log this as. Set to false
	 *    to not log the error. Default: 'general'.
	 * @param int $status The HTTP status code associated with this error.
	 *    Default: 500.
	 */
	public static function fatal(string $error, string|bool $log = 'general', int $status = 500): void
	{
		self::getService()->fatal($error, $log, $status);
	}

	/**
	 * Shows a fatal error with a message stored in the language file.
	 *
	 * This function stops execution and displays an error message by key.
	 *  - uses the string with the error_message_key key.
	 *  - logs the error in the forum's default language while displaying the error
	 *    message in the user's language.
	 *  - uses Errors language file and applies the $sprintf information if specified.
	 *  - the information is logged if log is specified.
	 *
	 * @param string $error The error message.
	 * @param string|bool $log What type of error to log this as. Set to false
	 *    to not log the error. Default: 'general'.
	 * @param array $sprintf An array of data to be substituted into the specified message.
	 * @param int $status The HTTP status code associated with this error. Default: 403.
	 * @param string $file Language file that holds the localized error message string.
	 *    Default: 'Errors'.
	 */
	public static function fatalLang(string $error, string|bool $log = 'general', array $sprintf = [], int $status = 403, string $file = 'Errors'): void
	{
		self::getService()->fatalLang($error, $log, $sprintf, $status, $file);
	}

	/**
	 * Show a message for the (full block) maintenance mode.
	 *
	 * It shows a complete page independent of language files or themes.
	 * It is used only if $maintenance = 2 in Settings.php.
	 * It stops further execution of the script.
	 */
	public static function displayMaintenanceMessage(): void
	{
		self::getService()->displayMaintenanceMessage();
	}

	/**
	 * Show an error message for the connection problems.
	 *
	 * It shows a complete page independent of language files or themes.
	 * It is used only if there's no way to connect to the database.
	 * It stops further execution of the script.
	 */
	public static function displayDbError(): void
	{
		self::getService()->displayDbError();
	}

	/**
	 * Show an error message for load average blocking problems.
	 *
	 * It shows a complete page independent of language files or themes.
	 * It is used only if the load averages are too high to continue execution.
	 * It stops further execution of the script.
	 */
	public static function displayLoadAvgError(): void
	{
		self::getService()->displayLoadAvgError();
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Get the ErrorHandlerService instance from the container.
	 *
	 * This method provides lazy initialization and caching of the service instance.
	 * It first attempts to retrieve the service from the DI container, falling back
	 * to direct instantiation if the container is not available (e.g., during early
	 * bootstrap or error conditions).
	 *
	 * @return ErrorHandlerService The error handler service instance.
	 */
	protected static function getService(): ErrorHandlerService
	{
		// Return cached instance if available
		if (self::$service !== null) {
			return self::$service;
		}

		// Try to get the service from the container
		try {
			self::$service = Container::get(ErrorHandlerService::class);

			return self::$service;
		} catch (\Throwable $e) {
			// Container not available or service not registered
			// Fall through to manual instantiation
		}

		// Fallback: create instance directly
		// This ensures the error handler works even during early bootstrap
		// or when the container is not available
		self::$service = new ErrorHandlerService();

		return self::$service;
	}
}
