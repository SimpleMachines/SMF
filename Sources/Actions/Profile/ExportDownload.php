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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * Downloads exported profile data file.
 */
class ExportDownload implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'download_export_file',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Path to the exports directory, with a trailing slash.
	 */
	public string $export_dir_slash;

	/**
	 * @var int
	 *
	 * The requested part of the overall export.
	 */
	public int $part;

	/**
	 * @var string
	 *
	 * File extension for the downloadable file.
	 */
	public string $extension;

	/**
	 * @var string
	 *
	 * Unique hash to anonymously identify the member whose profile this is.
	 */
	public string $idhash;

	/**
	 * @var string
	 *
	 * Unique download token for the member whose profile this is.
	 */
	public string $dltoken;

	/**
	 * @var string
	 *
	 * Path to the requested export file on disk.
	 */
	public string $path;

	/**
	 * @var string
	 *
	 * Path to the JSON file that tracks our progress exporting this profile.
	 */
	public string $progressfile;

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
	 * Does the job.
	 */
	public function execute(): void
	{
		$formats = Export::getFormats();

		// No access in strict maintenance mode.
		if (!empty(Config::$maintenance) && Config::$maintenance == 2) {
			Utils::sendHttpStatus(404);

			exit;
		}

		// We can't give them anything without these.
		if (empty($_GET['t']) || empty($_GET['format']) || !isset($formats[$_GET['format']])) {
			Utils::sendHttpStatus(400);

			exit;
		}

		// Make sure they gave the correct authentication token.
		// We use these tokens so the user can download without logging in, as required by the GDPR.
		if ($_GET['t'] !== $this->dltoken) {
			Utils::sendHttpStatus(403);

			exit;
		}

		// Obviously we can't give what we don't have.
		if (empty(Config::$modSettings['export_dir']) || !file_exists($this->path)) {
			Utils::sendHttpStatus(404);

			exit;
		}

		$file = [
			'path' => $this->path,
			'filename' => $this->buildFilename(),
			'mtime' => filemtime($this->path),
			'size' => filesize($this->path),
			'mime_type' => $formats[$_GET['format']]['mime'],
		];

		$file['etag'] = md5(implode(' ', [$file['filename'], $file['size'], $file['mtime']]));

		// If it hasn't been modified since the last time it was retrieved, there's no need to serve it again.
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);

			if (strtotime($modified_since) >= $file['mtime']) {
				ob_end_clean();
				header_remove('content-encoding');

				// Answer the question - no, it hasn't been modified ;).
				Utils::sendHttpStatus(304);

				exit;
			}
		}

		// Check whether the ETag was sent back, and cache based on that...
		if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $file['etag']) !== false) {
			ob_end_clean();
			header_remove('content-encoding');

			Utils::sendHttpStatus(304);

			exit;
		}

		// Send the file.
		Utils::emitFile($file);
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
		$formats = Export::getFormats();

		$this->export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;

		$this->idhash = hash_hmac('sha1', Utils::$context['id_member'], Config::getAuthSecret());
		$this->dltoken = hash_hmac('sha1', $this->idhash, Config::getAuthSecret());

		$this->part = isset($_GET['part']) ? (int) $_GET['part'] : 1;
		$this->extension = $formats[$_GET['format']]['extension'];

		$this->path = $this->export_dir_slash . $this->part . '_' . $this->idhash . '.' . $this->extension;

		$this->progressfile = $this->export_dir_slash . $this->idhash . '.' . $this->extension . '.progress.json';
	}

	/**
	 * Figure out the filename we'll tell the browser.
	 */
	protected function buildFilename(): string
	{
		$datatypes = file_exists($this->progressfile) ? array_keys(Utils::jsonDecode(file_get_contents($this->progressfile), true)) : ['profile'];

		$included_desc = array_map(
			function ($datatype) {
				return Lang::$txt[$datatype];
			},
			$datatypes,
		);

		$dlfilename = array_merge([Utils::$context['forum_name'], Utils::$context['member']['username']], $included_desc);
		$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', Utils::htmlspecialcharsDecode(strip_tags(implode('_', $dlfilename)))));

		$suffix = ($this->part > 1 || file_exists($this->export_dir_slash . '2_' . $this->idhash . '.' . $this->extension)) ? '_' . $this->part : '';

		return $dlfilename . $suffix . '.' . $this->extension;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ExportDownload::exportStatic')) {
	ExportDownload::exportStatic();
}

?>