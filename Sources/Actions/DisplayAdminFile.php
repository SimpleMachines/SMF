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

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Utils;

/**
 * Get one of the admin information files from Simple Machines.
 */
class DisplayAdminFile implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'DisplayAdminFile',
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
	 * Do the job.
	 */
	public function execute(): void
	{
		Config::setMemoryLimit('32M');

		if (empty($_REQUEST['filename']) || !is_string($_REQUEST['filename'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Strip off the forum cache part or we won't find it...
		$_REQUEST['filename'] = str_replace(Utils::$context['browser_cache'], '', $_REQUEST['filename']);

		$request = Db::$db->query(
			'',
			'SELECT data, filetype
			FROM {db_prefix}admin_info_files
			WHERE filename = {string:current_filename}
			LIMIT 1',
			[
				'current_filename' => $_REQUEST['filename'],
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('admin_file_not_found', true, [$_REQUEST['filename']], 404);
		}
		list($file_data, $filetype) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// @todo Temp
		// Figure out if sesc is still being used.
		if (strpos($file_data, ';sesc=') !== false && $filetype == 'text/javascript') {
			$file_data = "\n" . 'if (!(\'smfForum_sessionvar\' in window))' . "\n\t" . 'window.smfForum_sessionvar = \'sesc\';' . "\n" . strtr($file_data, [';sesc=' => ';\' + window.smfForum_sessionvar + \'=']);
		}

		Utils::$context['template_layers'] = [];

		// Lets make sure we aren't going to output anything nasty.
		@ob_end_clean();

		if (!empty(Config::$modSettings['enableCompressedOutput'])) {
			@ob_start('ob_gzhandler');
		} else {
			@ob_start();
		}

		// Make sure they know what type of file we are.
		header('content-type: ' . $filetype);
		echo $file_data;
		Utils::obExit(false);
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
if (is_callable(__NAMESPACE__ . '\\DisplayAdminFile::exportStatic')) {
	DisplayAdminFile::exportStatic();
}

?>