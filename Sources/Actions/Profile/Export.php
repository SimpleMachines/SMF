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
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Security;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class contains functions to export a member's profile data to a
 * downloadable file.
 *
 * @todo Add CSV, JSON as other possible export formats besides XML and HTML?
 */
class Export implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'export_profile_data',
			'createDir' => 'create_export_dir',
			'getFormats' => 'get_export_formats',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * ID of the member whose profile is being viewed.
	 */
	public int $id;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Types of exportable data, and instructions on how to get it.
	 * This is populated by the constructor.
	 */
	protected array $datatypes;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Supported formats for the export files.
	 * Protected to force access via Export::getFormats()
	 */
	protected static $formats = [
		'XML_XSLT' => [
			'extension' => 'styled.xml',
			'mime' => 'text/xml',
			'description' => 'export_format_xml_xslt',
			'per_page' => 500,
		],
		'HTML' => [
			'extension' => 'html',
			'mime' => 'text/html',
			'description' => 'export_format_html',
			'per_page' => 500,
		],
		'XML' => [
			'extension' => 'xml',
			'mime' => 'text/xml',
			'description' => 'export_format_xml',
			'per_page' => 2000,
		],
		// 'CSV' => array(
		// 	'extension' => 'csv',
		// 	'mime' => 'text/csv',
		// 	'description' => 'export_format_csv',
		//	'per_page' => 2000,
		// ),
		// 'JSON' => array(
		// 	'extension' => 'json',
		// 	'mime' => 'application/json',
		// 	'description' => 'export_format_json',
		//	'per_page' => 2000,
		// ),
	];

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
		if (empty(Config::$modSettings['export_dir']) || !is_dir(Config::$modSettings['export_dir']) || !Utils::makeWritable(Config::$modSettings['export_dir'])) {
			self::createDir();
		}

		$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;

		$idhash = hash_hmac('sha1', Utils::$context['id_member'], Config::getAuthSecret());
		$dltoken = hash_hmac('sha1', $idhash, Config::getAuthSecret());

		Utils::$context['completed_exports'] = [];
		Utils::$context['active_exports'] = [];
		$existing_export_formats = [];
		$latest = [];

		foreach (self::$formats as $format => $format_settings) {
			$idhash_ext = $idhash . '.' . $format_settings['extension'];

			$done = null;
			Utils::$context['outdated_exports'][$idhash_ext] = [];

			// $realfile needs to be the highest numbered one, or 1_*** if none exist.
			$filenum = 1;
			$realfile = $export_dir_slash . $filenum . '_' . $idhash_ext;

			while (file_exists($export_dir_slash . ($filenum + 1) . '_' . $idhash_ext)) {
				$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;
			}

			$tempfile = $export_dir_slash . $idhash_ext . '.tmp';
			$progressfile = $export_dir_slash . $idhash_ext . '.progress.json';

			// If requested by the user, delete any existing export files and background tasks.
			if (isset($_POST['delete'], $_POST['format'])   && $_POST['format'] === $format && isset($_POST['t']) && $_POST['t'] === $dltoken) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}background_tasks
					WHERE task_class = {string:class}
						AND task_data LIKE {string:details}',
					[
						'class' => 'SMF\\Tasks\\ExportProfileData',
						'details' => substr(Utils::jsonEncode(['format' => $format, 'uid' => Utils::$context['id_member']]), 0, -1) . ',%',
					],
				);

				foreach (glob($export_dir_slash . '*' . $idhash_ext . '*') as $fpath) {
					@unlink($fpath);
				}

				if (empty($_POST['export_begin'])) {
					Utils::redirectexit('action=profile;area=getprofiledata;u=' . Utils::$context['id_member']);
				}
			}

			$progress = file_exists($progressfile) ? Utils::jsonDecode(file_get_contents($progressfile), true) : [];

			if (!empty($progress)) {
				$included = array_keys($progress);
			} else {
				$included = array_intersect(array_keys($this->datatypes), array_keys($_POST));
			}

			// If we're starting a new export in this format, we're done here.
			if (!empty($_POST['export_begin']) && isset($_POST['format']) && $_POST['format'] === $format) {
				break;
			}

			// The rest of this loop deals with current exports, if any.

			$included_desc = [];

			foreach ($included as $datatype) {
				$included_desc[] = Lang::$txt[$datatype];
			}

			$dlfilename = array_merge([Utils::$context['forum_name'], Utils::$context['member']['username']], $included_desc);

			$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', Utils::htmlspecialcharsDecode(strip_tags(implode('_', $dlfilename)))));

			if (file_exists($tempfile) && file_exists($progressfile)) {
				$done = false;
			} elseif (file_exists($realfile)) {
				// It looks like we're done.
				$done = true;

				// But let's check whether it's outdated.
				foreach ($this->datatypes as $datatype => $datatype_settings) {
					if (!isset($progress[$datatype])) {
						continue;
					}

					if (!isset($latest[$datatype])) {
						$latest[$datatype] = is_callable($datatype_settings['latest']) ? $datatype_settings['latest'](Utils::$context['id_member']) : $datatype_settings['latest'];
					}

					if ($latest[$datatype] > $progress[$datatype]) {
						Utils::$context['outdated_exports'][$idhash_ext][] = $datatype;
					}
				}
			}

			if ($done === true) {
				$exportfilepaths = glob($export_dir_slash . '*_' . $idhash_ext);

				foreach ($exportfilepaths as $exportfilepath) {
					$exportbasename = basename($exportfilepath);

					$part = substr($exportbasename, 0, strcspn($exportbasename, '_'));
					$suffix = count($exportfilepaths) == 1 ? '' : '_' . $part;

					$size = filesize($exportfilepath) / 1024;
					$units = ['KB', 'MB', 'GB', 'TB'];
					$unitkey = 0;

					while ($size > 1024) {
						$size = $size / 1024;
						$unitkey++;
					}

					$size = round($size, 2) . $units[$unitkey];

					Utils::$context['completed_exports'][$idhash_ext][$part] = [
						'realname' => $exportbasename,
						'dlbasename' => $dlfilename . $suffix . '.' . $format_settings['extension'],
						'dltoken' => $dltoken,
						'included' => $included,
						'included_desc' => Lang::sentenceList($included_desc),
						'format' => $format,
						'mtime' => Time::create('@' . filemtime($exportfilepath))->format(),
						'size' => $size,
					];
				}

				ksort(Utils::$context['completed_exports'][$idhash_ext], SORT_NUMERIC);

				$existing_export_formats[] = $format;
			} elseif ($done === false) {
				Utils::$context['active_exports'][$idhash_ext] = [
					'dltoken' => $dltoken,
					'included' => $included,
					'included_desc' => Lang::sentenceList($included_desc),
					'format' => $format,
				];

				$existing_export_formats[] = $format;
			}
		}

		if (!empty($_POST['export_begin'])) {
			User::$me->checkSession();
			SecurityToken::validate(Utils::$context['token_check'], 'post');

			$format = isset($_POST['format']) && isset(self::$formats[$_POST['format']]) ? $_POST['format'] : 'XML';

			$included = [];
			$included_desc = [];

			foreach ($this->datatypes as $datatype => $datatype_settings) {
				if ($datatype == 'profile' || !empty($_POST[$datatype])) {
					$included[$datatype] = $datatype_settings[$format];

					$included_desc[] = Lang::$txt[$datatype];

					$start[$datatype] = !empty($start[$datatype]) ? $start[$datatype] : 0;

					if (!isset($latest[$datatype])) {
						$latest[$datatype] = is_callable($datatype_settings['latest']) ? $datatype_settings['latest'](Utils::$context['id_member']) : $datatype_settings['latest'];
					}

					if (!isset($total[$datatype])) {
						$total[$datatype] = is_callable($datatype_settings['total']) ? $datatype_settings['total'](Utils::$context['id_member']) : $datatype_settings['total'];
					}
				}
			}

			$dlfilename = array_merge([Utils::$context['forum_name'], Utils::$context['member']['username']], $included_desc);

			$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', Utils::htmlspecialcharsDecode(strip_tags(implode('_', $dlfilename)))));

			$last_page = ceil(array_sum($total) / self::$formats[$format]['per_page']);

			$data = Utils::jsonEncode([
				'format' => $format,
				'uid' => Utils::$context['id_member'],
				'lang' => Utils::$context['member']['language'],
				'included' => $included,
				'start' => $start,
				'latest' => $latest,
				'datatype' => $current_datatype ?? key($included),
				'format_settings' => self::$formats[$format],
				'last_page' => $last_page,
				'dlfilename' => $dlfilename,
			]);

			Db::$db->insert(
				'insert',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string-255',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\ExportProfileData',
					$data,
					0,
				],
				[],
			);

			// So the user can see that we've started.
			if (!file_exists($tempfile)) {
				touch($tempfile);
			}

			if (!file_exists($progressfile)) {
				file_put_contents($progressfile, Utils::jsonEncode(array_fill_keys(array_keys($included), 0)));
			}

			Utils::redirectexit('action=profile;area=getprofiledata;u=' . Utils::$context['id_member']);
		}

		SecurityToken::create(Utils::$context['token_check'], 'post');

		Utils::$context['page_title'] = Lang::$txt['export_profile_data'];

		if (empty(Config::$modSettings['export_expiry'])) {
			unset(Lang::$txt['export_profile_data_desc_list']['expiry']);
		} else {
			Lang::$txt['export_profile_data_desc_list']['expiry'] = sprintf(Lang::$txt['export_profile_data_desc_list']['expiry'], Config::$modSettings['export_expiry']);
		}

		Utils::$context['export_profile_data_desc'] = sprintf(Lang::$txt['export_profile_data_desc'], '<li>' . implode('</li><li>', Lang::$txt['export_profile_data_desc_list']) . '</li>');

		Theme::addJavaScriptVar('completed_formats', '[\'' . implode('\', \'', array_unique($existing_export_formats)) . '\']', false);
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

	/**
	 * Returns the path to a secure directory for storing exported profile data.
	 *
	 * The directory is created if it does not yet exist, and is secured using the
	 * same method that we use to secure attachment directories. Files in this
	 * directory can only be downloaded via the download_export_file() function.
	 *
	 * @return string|bool The path to the directory, or false on error.
	 */
	public static function createDir($fallback = ''): string|bool
	{
		// No supplied fallback, so use the default location.
		if (empty($fallback)) {
			$fallback = Config::$boarddir . DIRECTORY_SEPARATOR . 'exports';
		}

		// Automatically set it to the fallback if it is missing.
		if (empty(Config::$modSettings['export_dir'])) {
			Config::updateModSettings(['export_dir' => $fallback]);
		}

		// Make sure the directory exists.
		if (!file_exists(Config::$modSettings['export_dir'])) {
			@mkdir(Config::$modSettings['export_dir'], null, true);
		}

		// Make sure the directory has the correct permissions.
		if (!is_dir(Config::$modSettings['export_dir']) || !Utils::makeWritable(Config::$modSettings['export_dir'])) {
			Lang::load('Errors');

			// Try again at the fallback location.
			if (Config::$modSettings['export_dir'] != $fallback) {
				ErrorHandler::log(sprintf(Lang::$txt['export_dir_forced_change'], Config::$modSettings['export_dir'], $fallback));

				Config::updateModSettings(['export_dir' => $fallback]);

				// Secondary fallback will be the default location, so no parameter this time.
				self::createDir();
			}
			// Uh-oh. Even the default location failed.
			else {
				ErrorHandler::log(Lang::$txt['export_dir_not_writable']);

				return false;
			}
		}

		return Security::secureDirectory([Config::$modSettings['export_dir']], true);
	}

	/**
	 * Helper function that defines data export formats in a single location.
	 *
	 * @return array Information about supported data formats for profile exports.
	 */
	public static function getFormats()
	{
		static $finalized = false;

		// Finalize various string values.
		if (!$finalized) {
			array_walk_recursive(
				self::$formats,
				function (&$value, $key) {
					if ($key === 'description') {
						$value = Lang::$txt[$value] ?? $value;
					}
				},
			);

			$finalized = true;
		}

		// If these are missing, we can't transform the XML on the server.
		if (!class_exists('DOMDocument') || !class_exists('XSLTProcessor')) {
			unset(self::$formats['HTML']);
		}

		return self::$formats;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Utils::$context['token_check'])) {
			Utils::$context['token_check'] = 'profile-ex' . Utils::$context['id_member'];
		}

		self::getFormats();

		if (!isset($_POST['format']) || !isset(self::$formats[$_POST['format']])) {
			unset($_POST['format'], $_POST['delete'], $_POST['export_begin']);
		}

		// This lists the types of data we can export and info for doing so.
		$this->datatypes = [
			'profile' => [
				'label' => null,
				'total' => 0,
				'latest' => 1,
				// Instructions to pass to ExportProfileData background task:
				'XML' => [
					'func' => 'getXmlProfile',
					'langfile' => 'Profile',
				],
				'HTML' => [
					'func' => 'getXmlProfile',
					'langfile' => 'Profile',
				],
				'XML_XSLT' => [
					'func' => 'getXmlProfile',
					'langfile' => 'Profile',
				],
				// 'CSV' => array(),
				// 'JSON' => array(),
			],
			'posts' => [
				'label' => Lang::$txt['export_include_posts'],
				'total' => Utils::$context['member']['real_posts'],
				'latest' => function ($uid) {
					static $latest_post;

					if (isset($latest_post)) {
						return $latest_post;
					}

					$query_this_board = !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? 'b.id_board != ' . Config::$modSettings['recycle_board'] : '1=1';

					$request = Db::$db->query(
						'',
						'SELECT m.id_msg
						FROM {db_prefix}messages as m
							INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
						WHERE id_member = {int:uid}
							AND ' . $query_this_board . '
						ORDER BY id_msg DESC
						LIMIT {int:limit}',
						[
							'limit' => 1,
							'uid' => $uid,
						],
					);
					list($latest_post) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					return $latest_post;
				},
				// Instructions to pass to ExportProfileData background task:
				'XML' => [
					'func' => 'getXmlPosts',
					'langfile' => 'Post',
				],
				'HTML' => [
					'func' => 'getXmlPosts',
					'langfile' => 'Post',
				],
				'XML_XSLT' => [
					'func' => 'getXmlPosts',
					'langfile' => 'Post',
				],
				// 'CSV' => array(),
				// 'JSON' => array(),
			],
			'personal_messages' => [
				'label' => Lang::$txt['export_include_personal_messages'],
				'total' => function ($uid) {
					static $total_pms;

					if (isset($total_pms)) {
						return $total_pms;
					}

					$request = Db::$db->query(
						'',
						'SELECT COUNT(*)
						FROM {db_prefix}personal_messages AS pm
							INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)
						WHERE (pm.id_member_from = {int:uid} AND pm.deleted_by_sender = {int:not_deleted})
							OR (pmr.id_member = {int:uid} AND pmr.deleted = {int:not_deleted})',
						[
							'uid' => $uid,
							'not_deleted' => 0,
						],
					);
					list($total_pms) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					return $total_pms;
				},
				'latest' => function ($uid) {
					static $latest_pm;

					if (isset($latest_pm)) {
						return $latest_pm;
					}

					$request = Db::$db->query(
						'',
						'SELECT pm.id_pm
						FROM {db_prefix}personal_messages AS pm
							INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)
						WHERE (pm.id_member_from = {int:uid} AND pm.deleted_by_sender = {int:not_deleted})
							OR (pmr.id_member = {int:uid} AND pmr.deleted = {int:not_deleted})
						ORDER BY pm.id_pm DESC
						LIMIT {int:limit}',
						[
							'limit' => 1,
							'uid' => $uid,
							'not_deleted' => 0,
						],
					);
					list($latest_pm) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					return $latest_pm;
				},
				// Instructions to pass to ExportProfileData background task:
				'XML' => [
					'func' => 'getXmlPMs',
					'langfile' => 'PersonalMessage',
				],
				'HTML' => [
					'func' => 'getXmlPMs',
					'langfile' => 'PersonalMessage',
				],
				'XML_XSLT' => [
					'func' => 'getXmlPMs',
					'langfile' => 'PersonalMessage',
				],
				// 'CSV' => array(),
				// 'JSON' => array(),
			],
		];

		Utils::$context['export_datatypes'] = $this->datatypes;
		Utils::$context['export_formats'] = self::$formats;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Export::exportStatic')) {
	Export::exportStatic();
}

?>