<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Tools;

use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Table;
use SMF\IP;
use SMF\Lang;
use SMF\Maintenance\Cleanup;
use SMF\Maintenance\GenericSubStep;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration;
use SMF\Maintenance\Step;
use SMF\Maintenance\Utf8ConverterStep;
use SMF\QueryString;
use SMF\Sapi;
use SMF\SecurityToken;
use SMF\Session;
use SMF\Themes\default\MaintenanceTemplate;
use SMF\Time;
use SMF\User;
use SMF\UserDataset;
use SMF\Utils;

/**
 * Upgrade tool.
 */
class Upgrade extends ToolsBase implements ToolsInterface
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var array
	 *
	 * Indicates which migration steps need to be performed in order to get the
	 * current version of SMF fully up to date.
	 *
	 * Keys are *upper* bounds on the version, and values are step namespaces.
	 *
	 * For example, '3.0.99' => 'v3_0' means that if the current version of SMF
	 * is less than or equal to 3.0.99, run the v3_0 steps.
	 *
	 * This is a bit counter-intuitive, since one might think that the steps for
	 * upgrading to SMF 3.0 should only be run if the current version is less
	 * that 3.0. However, the upgrader also needs to work for upgrading between
	 * patch releases (e.g. 3.0.1 --> 3.0.5), so the boundary actually needs to
	 * be the highest version that the steps could apply to, not the lowest.
	 */
	public const VERSION_MAP = [
		'2.1.99' => 'v2_1',
		'3.0.99' => 'v3_0',
	];

	/**
	 * @var array
	 *
	 * Migration substeps to perform, listed in order.
	 *
	 * Note that additional substeps will be automatically appended to the list
	 * to ensure that all tables are structured correctly.
	 */
	public const MIGRATIONS = [
		// Migration steps for 2.0 -> 2.1
		'v2_1' => [
			Migration\v2_1\PostgreSqlSequences::class,
			Migration\v2_1\PostgreSqlFindInSet::class,
			Migration\v2_1\PostgreSqlTime::class,
			Migration\v2_1\SettingsUpdate::class,
			Migration\v2_1\RemoveKarma::class,
			Migration\v2_1\FixDates::class,
			Migration\v2_1\CreateMemberLogins::class,
			Migration\v2_1\CollapsedCategories::class,
			Migration\v2_1\AttachmentDirectory::class,
			Migration\v2_1\LegacyAttachments::class,
			Migration\v2_1\AttachmentSizes::class,
			Migration\v2_1\CreateLogGroupRequests::class,
			Migration\v2_1\PackageManager::class,
			Migration\v2_1\ValidationServers::class,
			Migration\v2_1\SessionIDs::class,
			Migration\v2_1\MovedTopics::class,
			Migration\v2_1\ScheduledTasks::class,
			Migration\v2_1\CreateBackgroundTasks::class,
			Migration\v2_1\CategoryDescrptions::class,
			Migration\v2_1\CreateAlerts::class,
			Migration\v2_1\AutoNotify::class,
			Migration\v2_1\AlertsWatchedTopics::class,
			Migration\v2_1\AlertsWatchedBoards::class,
			Migration\v2_1\AlertsObsolete::class,
			Migration\v2_1\TopicUnwatch::class,
			Migration\v2_1\MailQueue::class,
			Migration\v2_1\MembergroupIcon::class,
			Migration\v2_1\ThemeSettings::class,
			Migration\v2_1\CustomFieldsPart1::class,
			Migration\v2_1\CustomFieldsPart2::class,
			Migration\v2_1\CustomFieldsPart3::class,
			Migration\v2_1\UserDrafts::class,
			Migration\v2_1\Likes::class,
			Migration\v2_1\Mentions::class,
			Migration\v2_1\ModeratorGroups::class,
			Migration\v2_1\AdminInfoFiles::class,
			Migration\v2_1\VerificationQuestions::class,
			Migration\v2_1\Permissions::class,
			Migration\v2_1\PersonalMessageLabels::class,
			Migration\v2_1\MessagesModifiedReason::class,
			Migration\v2_1\MembersTimezone::class,
			Migration\v2_1\MembersHideEmail::class,
			Migration\v2_1\LogReportedCommentsEmail::class,
			Migration\v2_1\MembersOpenID::class,
			Migration\v2_1\OpenID::class,
			Migration\v2_1\LogSpiderHitsURL::class,
			Migration\v2_1\LogOnlineURL::class,
			Migration\v2_1\MembersTfaSecret::class,
			Migration\v2_1\MembersTfaBackup::class,
			Migration\v2_1\PostgreSqlUnlogged::class,
			Migration\v2_1\PostgreSqlIPv6Helper::class,
			Migration\v2_1\Ipv6BanItem::class,
			Migration\v2_1\Ipv6LogAction::class,
			Migration\v2_1\Ipv6LogBanned::class,
			Migration\v2_1\Ipv6LogErrors::class,
			Migration\v2_1\Ipv6MembersIP::class,
			Migration\v2_1\Ipv6MembersIP2::class,
			Migration\v2_1\Ipv6Messages::class,
			Migration\v2_1\Ipv6LogFloodControl::class,
			Migration\v2_1\Ipv6LogOnline::class,
			Migration\v2_1\Ipv6LogReportedComments::class,
			Migration\v2_1\Ipv6MemberLogins::class,
			Migration\v2_1\PersonalMessageNotification::class,
			Migration\v2_1\CalendarEvents::class,
			Migration\v2_1\IdxMessages::class,
			Migration\v2_1\IdxTopics::class,
			Migration\v2_1\IdxMembers::class,
			Migration\v2_1\IdxLogActivity::class,
			Migration\v2_1\IdxLogPackages::class,
			Migration\v2_1\IdxScheduledTasks::class,
			Migration\v2_1\IdxAdminInfo::class,
			Migration\v2_1\IdxBoards::class,
			Migration\v2_1\IdxLogComments::class,
			Migration\v2_1\MysqlLegacyData::class,
			Migration\v2_1\Smileys::class,
			Migration\v2_1\BoardDescriptions::class,
			Migration\v2_1\LogErrorsBacktrace::class,
			Migration\v2_1\BoardPermissionsView::class,
			Migration\v2_1\PostgreSqlSchemaDiff::class,
			Migration\v2_1\CalendarUpdates::class,
			Migration\v2_1\MysqlModFixes::class,
		],
		// Migration steps for 2.1 -> 3.0
		'v3_0' => [
			Migration\v3_0\PostgreSqlFunctions::class,
			Migration\v3_0\ConvertToInnoDb::class,
			Migration\v3_0\LanguageDirectory::class,
			Migration\v3_0\ErrorLogSession::class,
			Migration\v3_0\EditHistory::class,
			Migration\v3_0\MessageVersion::class,
			Migration\v3_0\PackageVersion::class,
			Migration\v3_0\RecurringEvents::class,
			Migration\v3_0\HolidaysToEvents::class,
			Migration\v3_0\EventUids::class,
			Migration\v3_0\DropModPrefs::class,
			Migration\v3_0\DropTimeOffset::class,
			Migration\v3_0\SpoofDetector::class,
			Migration\v3_0\EmailAddressCi::class,
			Migration\v3_0\NormalizeMemberEmailAddresses::class,
			Migration\v3_0\NormalizeBannedEmailAddresses::class,
			Migration\v3_0\SearchResultsPrimaryKey::class,
			Migration\v3_0\MailType::class,
			Migration\v3_0\RemoveCookieTime::class,
			Migration\v3_0\PermissionChanges::class,
			Migration\v3_0\BoardPostsCount::class,
			Migration\v3_0\ValidationCodeLength::class,
		],
	];

	/**
	 * @var array
	 *
	 * Cleanups that do not require database maintenance tasks.
	 */
	public const CLEANUPS = [
		// Cleanup steps for 2.0 -> 2.1
		'v2_1' => [
			Cleanup\v2_1\OldFiles::class,
		],
		// Cleanup steps for 2.1 -> 3.0
		'v3_0' => [
			Cleanup\v3_0\TasksDirCase::class,
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * Whether we can continue.
	 *
	 * When false the continue button is removed.
	 */
	public bool $continue = true;

	/**
	 * @var bool
	 *
	 * Whether we can skip the current step.
	 *
	 * If false, no skip option will be shown.
	 */
	public bool $skip = false;

	/**
	 * @var string
	 *
	 * The name of the script this tool uses.
	 *
	 * This is used by various actions and links.
	 */
	public string $script_file = 'upgrade.php';

	/**
	 * @var string
	 *
	 * HTML element ID for the submission form in this tool's HTML templates.
	 */
	public string $form_id = 'upgrade_form';

	/**
	 * @var int
	 *
	 * The time we last updated the upgrade, populated by upgrade itself.
	 */
	public int $time_updated = 0;

	/**
	 * @var bool
	 *
	 * Debugging the upgrade.
	 */
	public bool $debug = false;

	/**
	 * @var array
	 *
	 * User performing upgrade.
	 */
	public array $user = [
		'id' => 0,
		'name' => 'Guest',
		'maint' => 0,
	];

	/**
	 * @var array
	 *
	 * Migrations we skipped.
	 */
	public array $skipped_migrations = [];

	/**
	 * @var int
	 *
	 * The amount of seconds allowed between logins.
	 *
	 * If the first user to login is inactive for this amount of seconds,
	 * a second login is allowed.
	 */
	public int $inactive_timeout = 10;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Upgrade data stored in our Settings.php as we progress through the upgrade.
	 */
	protected array $maintenance_tool_progress = [];

	/**
	 * @var int
	 *
	 * The time we started the upgrade, populated by upgrade itself.
	 */
	protected int $time_started = 0;

	/**
	 * @var string
	 *
	 * English is the default language.
	 */
	protected string $default_language = 'en_US';

	/**
	 * @var array
	 *
	 * Maps old cache accelerator settings to new ones.
	 */
	protected array $cache_migration = [
		'smf' => 'FileBase',
		'apc' => 'FileBase',
		'apcu' => 'Apcu',
		'memcache' => 'MemcacheImplementation',
		'memcached' => 'MemcachedImplementation',
		'postgres' => 'Postgres',
		'sqlite' => 'Sqlite',
		'xcache' => 'FileBase',
		'zend' => 'Zend',
	];

	/**
	 * @var string
	 *
	 * SMF Version we started on.
	 */
	protected string $start_smf_version = '';

	/**
	 * @var null|string
	 *
	 * Custom page title, otherwise we send the defaults.
	 */
	private ?string $page_title = null;

	/**
	 * @var bool
	 *
	 * Additional safety measures for timeout protection are done for large forums.
	 */
	private bool $is_large_forum = false;

	/**
	 * @var ?Step
	 *
	 * Which step is currently being performed.
	 *
	 * This is set by $this->setStep() and retrieved by $this->getStep().
	 */
	private ?Step $current_step;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		Maintenance::$languages = $this->detectLanguages(['General', 'Maintenance']);

		if (empty(Maintenance::$languages)) {
			if (!Sapi::isCLI()) {
				MaintenanceTemplate::missingLanguages();
			}

			throw new \Exception('This script was unable to find this tools\'s language file or files.');
		} else {
			$requested_lang = Maintenance::getRequestedLanguage();

			// Ensure SMF\Lang knows the path to the language directory.
			Lang::addDirs(Config::$languagesdir);

			// And now load the language file.
			Lang::load('General+Maintenance+Errors', $requested_lang);

			// Assume that the admin likes that language.
			if ($requested_lang !== $this->default_language) {
				Config::$language = $requested_lang;
			}
		}

		// Secure some resources.
		try {
			if (Config::$db_type == MYSQL_TITLE) {
				@ini_set('mysql.connect_timeout', '-1');
			}

			@ini_set('default_socket_timeout', '900');
			Sapi::setTimeLimit(600);
			Sapi::setMemoryLimit('512M');

			// Better to upgrade cleanly and fall apart than to screw everything up if things take too long.
			ignore_user_abort(true);
		} catch (\Throwable $e) {
		}

		// SMF\Config, and SMF\Utils.
		Config::load();
		Utils::load();
		Session::load();

		$this->prepareUpgrade();

		// If they don't have the file, they're going to get a warning anyway so we won't need to clean request vars.
		if (class_exists(QueryString::class)) {
			QueryString::cleanRequest();
		}

		// Is this a large (and old) forum? We may do special logic then.
		Utils::$context['is_large_forum'] = $this->is_large_forum = (
			version_compare(
				str_replace(' ', '.', strtolower($this->start_smf_version)),
				'1.1.rc.1',
				'<=',
			)
			&& !empty(Config::$modSettings['totalMessages'])
			&& Config::$modSettings['totalMessages'] > 75000
		);

		// Should we check that they are logged in?
		if (Maintenance::getCurrentSubStep() > 0 && !isset($_SESSION['is_logged'])) {
			Maintenance::setCurrentSubStep(0);
		}
	}

	/**
	 *
	 */
	public function getScriptName(): string
	{
		return Lang::getTxt('smf_upgrade', file: 'Maintenance');
	}

	/**
	 * Gets our page title to be sent to the template.
	 *
	 * Selection is in the following order:
	 *  1. A custom page title.
	 *  2. Step has provided a title.
	 *  3. The value of $this->getScriptName().
	 *
	 * @return string The title for the page.
	 */
	public function getPageTitle(): string
	{
		return $this->page_title ?? $this->getStep()->getTitle() ?? $this->getScriptName();
	}

	/**
	 * If a tool does not contain steps, this should be false, true otherwise.
	 *
	 * @return bool Whether or not a tool has steps.
	 */
	public function hasSteps(): bool
	{
		return true;
	}

	/**
	 * Upgrade Steps
	 *
	 * @return \SMF\Maintenance\Step[]
	 */
	public function getSteps(): array
	{
		return [
			new Step(
				id: 1,
				name: Lang::getTxt('upgrade_step_login', file: 'Maintenance'),
				function: 'welcomeLogin',
				template: 'welcomeLogin',
				progress: 2,
			),
			new Step(
				id: 2,
				name: Lang::getTxt('upgrade_step_options', file: 'Maintenance'),
				function: 'upgradeOptions',
				template: 'upgradeOptions',
				progress: 3,
			),
			new Step(
				id: 3,
				name: Lang::getTxt('upgrade_step_backup', file: 'Maintenance'),
				function: 'backupDatabase',
				template: 'backupDatabase',
				progress: 10,
			),
			new Step(
				id: 4,
				name: Lang::getTxt('upgrade_step_migration', file: 'Maintenance'),
				function: 'migrations',
				template: 'migrations',
				progress: 45,
			),
			new Utf8ConverterStep(
				// Note: Utf8ConverterStep does not take a function argument.
				id: 5,
				name: Lang::getTxt('upgrade_step_convertutf8', file: 'Maintenance'),
				template: 'convertUtf8',
				progress: 30,
			),
			new Step(
				id: 6,
				name: Lang::getTxt('upgrade_step_cleanup', file: 'Maintenance'),
				function: 'cleanup',
				template: 'cleanup',
				progress: 10,
			),
			new Step(
				id: 7,
				name: Lang::getTxt('upgrade_step_finalize', file: 'Maintenance'),
				function: 'finalize',
				template: 'finalize',
				progress: 0,
			),
		];
	}

	/**
	 * Gets the title for the step we are performing
	 *
	 * @return string
	 */
	public function getStepTitle(): string
	{
		return $this->getStep()->getName();
	}

	/**
	 * Welcome action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function welcomeLogin(): bool
	{
		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		if (!empty($_SESSION['is_logged'])) {
			return true;
		}

		// Needs to at least meet our minium version.
		if (version_compare(Maintenance::PHP_MIN_VERSION, PHP_VERSION, '>=')) {
			Maintenance::$fatal_error = Lang::getTxt('error_php_too_low', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Only 64-bit builds are supported.
		if (PHP_INT_SIZE < 8) {
			Maintenance::$fatal_error = Lang::getTxt('error_php_32_bit', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Form submitted, but no javascript support.
		if (isset($_POST['contbutt']) && !isset($_POST['js_support'])) {
			Maintenance::$fatal_error = Lang::getTxt('error_no_javascript', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Check for some key files.
		$check = (
			@file_exists(Maintenance::$theme_dir . '/index.template.php')
			&& @file_exists(Config::$sourcedir . '/Forum.php')
			&& @file_exists(Config::$sourcedir . '/QueryString.php')
			&& @file_exists(Config::$sourcedir . '/Db/APIs/' . Db::getClass(Config::$db_type) . '.php')
		);

		try {
			foreach (self::VERSION_MAP as $search => $ns) {
				if (version_compare($this->start_smf_version, $search, '>')) {
					continue;
				}

				foreach (self::MIGRATIONS[$ns] as $class) {
					if (!class_exists($class)) {
						throw new \Exception("{$class} does not exist");
					}
				}

				foreach (self::CLEANUPS[$ns] as $class) {
					if (!class_exists($class)) {
						throw new \Exception("{$class} does not exist");
					}
				}
			}
		}
		// Developers, set break point here to figure out what you did wrong.
		 catch (\Exception $ex) {
			$check = false;
		}

		if (!$check) {
			// Don't tell them what files exactly because it's a spot check - just like teachers don't tell which problems they are spot checking, that's dumb.
			Maintenance::$fatal_error = Lang::getTxt('error_upgrade_files_missing', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		Db::load();

		if (
			version_compare(
				preg_replace('~^\D*|\-.+?$~', '', Db::$db->get_version()),
				Db::$db->getMinimumVersion(),
				'<',
			)
		) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_too_low', ['name' => Db::$db->getTitle(), 'min_version' => Db::$db->getMinimumVersion()]);
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Check that we have database permissions.
		// CREATE
		$create = Db::$db->create_table('{db_prefix}priv_check', [['name' => 'id_test', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true]], [['columns' => ['id_test'], 'type' => 'primary']], [], 'overwrite');

		// ALTER
		$alter = Db::$db->add_column('{db_prefix}priv_check', ['name' => 'txt', 'type' => 'varchar', 'size' => 4, 'null' => false, 'default' => '']);

		// DROP
		$drop = Db::$db->drop_table('{db_prefix}priv_check');

		// Sorry... we need CREATE, ALTER and DROP
		if (!$create || !$alter || !$drop) {
			Maintenance::$fatal_error = Lang::getTxt('error_db_privileges', ['name' => Config::$db_type]);
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Do a quick version spot check.
		$temp = substr(@implode('', @file(Config::$boarddir . '/index.php')), 0, 4096);
		preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $temp, $match);

		if (empty($match[1]) || (trim($match[1]) != SMF_VERSION)) {
			Maintenance::$fatal_error = Lang::getTxt('error_upgrade_old_files', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// What absolutely needs to be writable?
		$writable_files = [
			SMF_SETTINGS_FILE,
			SMF_SETTINGS_BACKUP_FILE,
		];

		// Try to make all the files writable. If we cannot, we will display a chmod page to attempt this with additional permissions.
		if (!$this->makeFilesWritable($writable_files)) {
			Utils::$context['chmod']['files'] = $writable_files;

			return false;
		}

		// Do we need to add this setting?
		$need_settings_update = empty(Config::$modSettings['custom_avatar_dir']);

		$custom_av_dir = !empty(Config::$modSettings['custom_avatar_dir']) ? Config::$modSettings['custom_avatar_dir'] : Config::$boarddir . '/custom_avatar';
		$custom_av_url = !empty(Config::$modSettings['custom_avatar_url']) ? Config::$modSettings['custom_avatar_url'] : Config::$boardurl . '/custom_avatar';

		$writable_files = [$custom_av_dir];
		$this->makeFilesWritable($writable_files);

		// Are we good now?
		if (!is_writable($custom_av_dir)) {
			Maintenance::$fatal_error = Lang::getTxt('error_dir_not_writable', ['dir' => $custom_av_dir]);
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		if ($need_settings_update) {
			$this->updateModSettings(['custom_avatar_dir' => $custom_av_dir]);
			$this->updateModSettings(['custom_avatar_url' => $custom_av_url]);
		}

		// Check the cache directory.
		$cache_dir_temp = empty(Config::$cachedir) ? Config::$boarddir . '/cache' : Config::$cachedir;

		if (!file_exists($cache_dir_temp)) {
			@mkdir($cache_dir_temp);
		}

		if (!file_exists($cache_dir_temp)) {
			Maintenance::$fatal_error = Lang::getTxt('error_cache_not_found', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		$writable_files = [$cache_dir_temp . '/db_last_error.php'];
		$this->makeFilesWritable($writable_files);

		if (!is_writable($cache_dir_temp . '/db_last_error.php')) {
			Maintenance::$fatal_error = Lang::getTxt('error_dir_not_writable', ['dir' => $cache_dir_temp]);
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Do we need to update our Settings file with the new language locale?
		$current_language = Config::$language;
		$new_locale = Lang::getLocaleFromLanguageName($current_language);

		if ($new_locale !== null && $new_locale != Config::$language) {
			$this->updateSettingsFile(['language' => $new_locale]);
		}

		if (empty(Config::$languagesdir)) {
			$this->updateSettingsFile(['languagesdir' => Config::$boarddir . '/Languages']);
		}

		// Check agreement.txt. It may not exist, in which case $boarddir must be writable.
		if (
			isset(Config::$modSettings['agreement'])
			&& (
				!is_writable(Config::$languagesdir)
				|| file_exists(Config::$languagesdir . '/' . $this->default_language . '/agreement.txt')
			)
			&& !is_writable(Config::$languagesdir . '/' . $this->default_language . '/agreement.txt')
		) {
			Maintenance::$fatal_error = Lang::getTxt('error_agreement_not_writable', file: 'Maintenance');
			$this->logProgress(Maintenance::$fatal_error);

			return false;
		}

		// Confirm mbstring is loaded...
		if (!\extension_loaded('mbstring')) {
			Maintenance::$errors[] = Lang::getTxt('install_no_mbstring', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('install_no_mbstring', file: 'Maintenance'));
		}

		// Confirm fileinfo is loaded...
		if (!\extension_loaded('fileinfo')) {
			Maintenance::$errors[] = Lang::getTxt('install_no_fileinfo', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('install_no_fileinfo', file: 'Maintenance'));
		}

		// Check for https stream support.
		$supported_streams = stream_get_wrappers();

		if (!\in_array('https', $supported_streams)) {
			Maintenance::$warnings[] = Lang::getTxt('install_no_https', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('install_no_https', file: 'Maintenance'));
		}

		// First, check the avatar directory...
		// Note it wasn't specified in YabbSE, but there was no smfVersion either.
		if (!empty(Config::$modSettings['smfVersion']) && !is_dir(Config::$modSettings['avatar_directory'])) {
			Maintenance::$warnings[] = Lang::getTxt('warning_av_missing', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('warning_av_missing', file: 'Maintenance'));
		}

		// Next, check the custom avatar directory...  Note this is optional in 2.0.
		if (!empty(Config::$modSettings['custom_avatar_dir']) && !is_dir(Config::$modSettings['custom_avatar_dir'])) {
				Maintenance::$warnings[] = Lang::getTxt('warning_custom_av_missing', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('warning_custom_av_missing', file: 'Maintenance'));
		}

		// Ensure we have a valid attachment directory.
		if ($this->attachmentDirectoryIsValid()) {
			Maintenance::$warnings[] = Lang::getTxt('warning_att_dir_missing', file: 'Maintenance');
			$this->logProgress(Lang::getTxt('warning_att_dir_missing', file: 'Maintenance'));
		}

		if (Sapi::isCLI()) {
			return true;
		}

		// Attempting to login.
		if (
			empty(Maintenance::$errors)
			&& isset($_POST['contbutt'])
			&& (
				!empty($_POST['db_pass'])
				|| (
					!empty($_POST['user'])
					&& !empty($_POST['passwrd'])
				)
			)
		) {
			if (!SecurityToken::validate('login', 'post', false)) {
				Maintenance::$errors[] = Lang::getTxt('token_verify_fail', file: 'Errors');
				SecurityToken::create('login');

				return false;
			}

			// Let them login, if they know the database password.
			if (
				!empty($_POST['db_pass'])
				&& Maintenance::loginWithDatabasePassword((string) $_POST['db_pass'])
			) {
				$this->user['id'] = 0;
				$this->user['name'] = 'Database Admin';

				$_SESSION['is_logged'] = true;

				return true;
			}

			$use_old_hashing = version_compare(str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')), '2.1.dev.0', '<');

			if (($id = Maintenance::loginAdmin((string) $_POST['user'], (string) $_POST['passwrd'], $use_old_hashing)) > 0) {
				$this->user['id'] = $id;
				$this->user['name'] = (string) $_POST['user'];

				$_SESSION['is_logged'] = true;

				return true;
			}
		} elseif (empty(Maintenance::$errors)) {
			Utils::$context['continue'] = true;
		}

		SecurityToken::create('login');

		return false;
	}

	/**
	 * Allow the administrator to select options for the upgrade.
	 *
	 * @return bool True if we are continuing, false we are presenting upgrade options.
	 */
	public function upgradeOptions(): bool
	{
		$member_columns = Db::$db->list_columns('{db_prefix}members');

		Utils::$context['karma_installed'] = [
			'good' => \in_array('karma_good', $member_columns),
			'bad' => \in_array('karma_bad', $member_columns),
		];

		unset($member_columns);

		// Figure out a couple of recommendations.
		Utils::$context['backup_recommended'] = $this->backupRecommended();

		Utils::$context['migrate_settings_recommended'] = (
			empty(Config::$modSettings['smfVersion'])
			|| version_compare(
				str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'])),
				preg_replace('/^(\d+\.\d+).*/', '$1.dev.0', SMF_VERSION),
				'<',
			)
		);

		Utils::$context['db_prefix'] = Config::$db_prefix;

		Utils::$context['message_title'] = htmlspecialchars(Config::$mtitle);
		Utils::$context['message_body'] = htmlspecialchars(Config::$mmessage);

		Utils::$context['attachment_conversion'] = isset(Config::$modSettings['attachments_21_done']);

		Utils::$context['sm_stats_configured'] = !empty(Config::$modSettings['allow_sm_stats']) || !empty(Config::$modSettings['enable_sm_stats']);

		// If we've not submitted then we're done.
		if (!Sapi::isCLI() && empty($_POST['upcont'])) {
			Utils::$context['continue'] = true;

			return false;
		}

		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		Db::load();
		Db::$db->setSqlMode('strict');

		$file_settings = [];
		$db_settings = [];

		// Firstly, if they're enabling SM stat collection just do it.
		$this->toggleSmStats($db_settings);

		// Deleting old karma stuff?
		$_SESSION['delete_karma'] = !empty($_POST['delete_karma']);

		// Emptying the error log?
		$_SESSION['empty_error'] = !empty($_POST['empty_error']);

		// Reprocessing attachments?
		$_SESSION['reprocess_attachments'] = !empty($_POST['reprocess_attachments']);

		// Add proxy settings.
		if (!isset(Config::$image_proxy_secret) || Config::$image_proxy_secret == 'smfisawesome') {
			$file_settings['image_proxy_secret'] = bin2hex(random_bytes(10));
		}

		if (!isset(Config::$image_proxy_maxsize)) {
			$file_settings['image_proxy_maxsize'] = 5190;
		}

		if (!isset(Config::$image_proxy_enabled)) {
			$file_settings['image_proxy_enabled'] = false;
		}

		if (stripos(Config::$boardurl, 'https://') !== false && !isset(Config::$modSettings['force_ssl'])) {
			$db_settings['force_ssl'] = 1;
		}

		// If we're overriding the language follow it through.
		// @todo This gets overwritten below.
		if (Maintenance::getRequestedLanguage() != Config::$language) {
			$file_settings['language'] = Maintenance::getRequestedLanguage();
		}

		// Put the forum into maintenance mode.
		if (!empty($_POST['maint'])) {
			$file_settings['maintenance'] = 2;

			// Remember what it was...
			$this->user['maint'] = Config::$maintenance;

			if (!empty($_POST['maintitle'])) {
				$file_settings['mtitle'] = $_POST['maintitle'];
				$file_settings['mmessage'] = $_POST['mainmessage'];
			} else {
				$file_settings['mtitle'] = Lang::getTxt('mtitle', file: 'Maintenance');
				$file_settings['mmessage'] = Lang::getTxt('mmessage', file: 'Maintenance');
			}
		}

		// Fix some old paths.
		if (substr(Config::$boarddir, 0, 1) == '.') {
			$file_settings['boarddir'] = $this->fixRelativePath(Config::$boarddir);
		}

		if (substr(Config::$sourcedir, 0, 1) == '.') {
			$file_settings['sourcedir'] = $this->fixRelativePath(Config::$sourcedir);
		}

		if (empty(Config::$cachedir) || substr(Config::$cachedir, 0, 1) == '.') {
			$file_settings['cachedir'] = $this->fixRelativePath(Config::$boarddir) . '/cache';
		}

		// Maybe we haven't had this option yet?
		if (empty(Config::$packagesdir)) {
			$file_settings['packagesdir'] = $this->fixRelativePath(Config::$boarddir) . '/Packages';
		}

		// Languages have moved!
		if (empty(Config::$languagesdir)) {
			$file_settings['languagesdir'] = $this->fixRelativePath(Config::$boarddir) . '/Languages';
		}

		// Make sure we fix the language as well.
		if (stristr(Config::$language, '-utf8')) {
			$file_settings['language'] = str_ireplace('-utf8', '', Config::$language);
		}

		// Maybe we are on the old language naming? User settings will get fixed up later.
		if (isset(Lang::LANG_TO_LOCALE[Config::$language])) {
			$file_settings['language'] = Lang::LANG_TO_LOCALE[Config::$language];
		}

		// Migrate cache settings.
		// Accelerator setting didn't exist previously; use 'smf' file based caching as default if caching had been enabled.
		if (!isset(Config::$cache_enable)) {
			$file_settings += [
				'cache_accelerator' => $this->cache_migration[Config::$cache_accelerator] ?? Config::$cache_accelerator,
				'cache_enable' => !empty(Config::$modSettings['cache_enable']) ? Config::$modSettings['cache_enable'] : 0,
				'cache_memcached' => !empty(Config::$modSettings['cache_memcached']) ? Config::$modSettings['cache_memcached'] : '',
			];
		}

		// If they have a "host:port" setup for the host, split that into separate values
		// You should never have a : in the hostname if you're not on MySQL, but better safe than sorry
		if (strpos(Config::$db_server, ':') !== false) {
			list(Config::$db_server, $db_port) = explode(':', Config::$db_server);
			Config::$db_port = (int) $db_port;

			$file_settings['db_server'] = Config::$db_server;
			$file_settings['db_port'] = Config::$db_port;
		}

		// If db_port is set and is the same as the default, set it to 0.
		if (!empty(Config::$db_port) && Config::$db_port == Db::$db->getDefaultPort()) {
			$file_settings['db_port'] = 0;
		}

		// Update the database with new settings.
		$this->updateModSettings($db_settings);

		// Update Settings.php with the new settings, and rebuild if they selected that option.
		$this->updateSettingsFile($file_settings, false, !empty($_POST['migrateSettings']));

		// Empty our error log.
		if (!empty($_POST['empty_error'])) {
			Db::$db->query(
				'TRUNCATE {db_prefix}log_errors',
				[],
				identifier: 'truncate_table',
			);
		}

		// Are we doing debug?
		if (isset($_POST['debug'])) {
			$this->debug = true;
		}

		// If we've got here then let's proceed to the next step!
		return true;
	}

	/**
	 * Backup our database.
	 *
	 * @return bool True if we are done backing up or skipped.  False otherwise.
	 */
	public function backupDatabase(): bool
	{
		// Done it already - js wise?
		if (!empty($_POST['backup_done'])) {
			return true;
		}

		// If we're not backing up then jump one.
		if (!Maintenance::isJson() && empty($_POST['backup'])) {
			return true;
		}

		Db::load();
		Db::$db->setSqlMode('default');

		// Get all the table names.
		$filter = str_replace('_', '\_', preg_match('~^`(.+?)`\.(.+?)$~', Config::$db_prefix, $match) != 0 ? $match[2] : Config::$db_prefix) . '%';

		$db = preg_match('~^`(.+?)`\.(.+?)$~', Config::$db_prefix, $match) != 0 ? strtr($match[1], ['`' => '']) : false;

		$tables = Db::$db->list_tables($db, $filter);

		// Filter out backup tables.
		$table_names = array_filter($tables, function ($table) {
			return !str_starts_with($table, 'backup_');
		});

		Maintenance::$total_substeps = \count($table_names);

		// Template things.
		Utils::$context['cur_table_name'] = $table_names[Maintenance::getCurrentSubStep()];
		Utils::$context['continue'] = true;

		// We are set up for backing up.
		if (!Sapi::isCLI() && !Maintenance::isJson()) {
			return false;
		}

		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		// Back up each table!
		$substeps = [];

		foreach ($table_names as $table_name) {
			$substeps[] = new GenericSubStep(
				name: Lang::getTxt('log_table_backup', ['table' => $table_name], file: 'Maintenance'),
				exec: [$this, 'doBackupTable'],
				exec_args: [$table_name],
			);
		}

		return $this->performSubsteps($substeps);
	}

	/**
	 * Perform database migration actions.
	 *
	 * - This performs steps as required to make changes safely to the database.
	 * - Each migration is tracked as a substep.
	 * - We check if the migration is a candidate, if it is not, we skip the
	 *   substep.
	 * - The migration may loop over multiple times, returning false. In such
	 *   cases, it will use the start to check its offset.
	 *
	 * @return bool True if we are done, false if we need to time out and wait.
	 */
	public function migrations(): bool
	{
		// Have we just completed this?
		if (!empty($_POST['database_done'])) {
			return true;
		}

		$substeps = [];

		foreach (self::VERSION_MAP as $search => $ns) {
			if (version_compare($this->start_smf_version, $search, '>')) {
				continue;
			}

			foreach (self::MIGRATIONS[$ns] as $class) {
				$substeps[] = new $class();
			}

			// Ensure the tables are structured correctly.
			foreach (Table::getAll($ns) as $table) {
				$substeps[] = new GenericSubStep(
					name: Lang::getTxt('upgrade_normalizing_table', ['table' => Config::$db_prefix . $table->name], file: 'Maintenance'),
					exec: [$table, 'normalize'],
				);
			}
		}

		if (!$this->performSubsteps($substeps)) {
			return false;
		}

		return Sapi::isCLI();
	}

	/**
	 * Perform cleanup actions.
	 *
	 * - This operates similarly to migrations, but is designed for operations
	 *   against the file system to optimize the installation.
	 * - Each cleanup is tracked as a substep.
	 * - We check if the cleanup is a candidate. If not, we skip the substep.
	 * - The cleanup may loop over multiple times, returning false. In such
	 *   cases, it will use the start to check its offset.
	 *
	 * @return bool True if we are done, false if we need to timeout and wait.
	 */
	public function cleanup(): bool
	{
		// Have we just completed this?
		if (!empty($_POST['cleanup_done'])) {
			return true;
		}

		$substeps = [];

		foreach (self::VERSION_MAP as $search => $ns) {
			if (version_compare($this->start_smf_version, $search, '>')) {
				continue;
			}

			foreach (self::CLEANUPS[$ns] as $class) {
				$substeps[] = new $class();
			}
		}

		if (!$this->performSubsteps($substeps)) {
			return false;
		}

		return Sapi::isCLI();
	}

	/**
	 * Upgrade is completed, offer help if things went wrong, or congrats if
	 * everything upgraded. Offers a option to delete the upgrade file.
	 *
	 * @return bool
	 */
	public function finalize(): bool
	{
		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		Utils::$context['form_action'] = Config::$boardurl . '/index.php';

		// Update the database with the new SMF version.
		$this->updateModSettings(['smfVersion' => SMF_VERSION]);

		// Clean any old cache files away.
		CacheApi::load();
		CacheApi::clean();

		// Queue up some background tasks that we want to run soon after upgrading.
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			[
				[
					'SMF\\Tasks\\FetchSMfiles',
					'',
					0,
				],
			],
			['id_task'],
		);

		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			[
				[
					'SMF\\Tasks\\UpdateSpoofDetectorNames',
					json_encode(['last_member_id' => 0]),
					0,
				],
			],
			['id_task'],
		);

		// Log the action manually, so CLI still works.
		Db::$db->insert(
			'',
			'{db_prefix}log_actions',
			[
				'log_time' => 'int',
				'id_log' => 'int',
				'id_member' => 'int',
				'ip' => 'inet',
				'action' => 'string',
				'id_board' => 'int',
				'id_topic' => 'int',
				'id_msg' => 'int',
				'extra' => 'string-65534',
			],
			[
				[
					time(),
					3,
					$this->user['id'],
					IP::getUserIP(),
					'upgrade',
					0,
					0,
					0,
					json_encode([
						'version' => SMF_FULL_VERSION,
						'member_acted' => $this->user['name'],
					]),
				],
			],
			['id_action'],
		);

		// Finalize some settings in the settings file.
		$file_settings = [
			'maintenance' => $this->user['maint'] ?? 0,
		];

		// Delete all the obsolete settings.
		foreach (Config::getSettingsDefs() as $var => $setting_def) {
			if (\is_string($var) && ($setting_def['auto_delete'] ?? null) === 3) {
				$file_settings[$var] = $setting_def['default'];
			}
		}

		$this->updateSettingsFile($file_settings);

		// We're done!
		$this->logProgress(Lang::getTxt('log_upgrade_complete', file: 'Maintenance'));
		Maintenance::$overall_percent = 100;
		Maintenance::setCurrentSubStep(0);

		// Wipe this out...
		$this->user = [];

		if (!Sapi::isCLI()) {
			// Can we delete the file?
			Utils::$context['can_delete_script'] = $this->canDeleteTool();

			// Show Upgrade time in debug mode when we completed the upgrade process totally
			if ($this->isDebug()) {
				$active = time() - (int) $this->time_started;

				Utils::$context['upgrade_completed_time'] = Lang::getTxt(
					$active >= 3600 ? 'upgrade_completed_time_hms' : ($active >= 60 ? 'upgrade_completed_time_ms' : 'upgrade_completed_time_s'),
					[
						'h' => (int) ($active / 3600),
						'm' => (int) ((int) ($active / 60) % 60),
						's' => (int) ($active % 60),
					],
					file: 'Maintenance',
				);

				Utils::$context['log_contents'] = file_get_contents($this->log_file);
			}
		}

		$this->finalizeLog();

		return Sapi::isCLI();
	}

	/**
	 * Write out our current information to our settings file to track the
	 * upgrade progress.
	 */
	public function preExit(): void
	{
		$this->saveProgress();
	}

	/**
	 * Figures out whether to make "yes" or "no" the default value for the
	 * option to create backups before upgrading.
	 *
	 * In nearly all situations the admin should be encouraged to make a backup.
	 * However, if the admin is re-running the upgrader and a recent backup
	 * already exists, we shouldn't overwrite it unless the admin intentionally
	 * tells us to do so.
	 *
	 * @return bool Whether creating a backup is recommended in this case.
	 */
	public function backupRecommended(): bool
	{
		$tables = Db::$db->list_tables();

		// Filter out backup tables.
		$table_names = array_filter($tables, function ($table) {
			return !str_starts_with($table, 'backup_');
		});

		// If there is no existing backup, recommend that they make one now.
		if ($tables === $table_names) {
			return true;
		}

		return (Config::$modSettings['smfVersion'] ?? null) !== SMF_VERSION;
	}

	/**
	 * Actually backup a table.
	 *
	 * @param mixed $table_name Name of the table to be backed up.
	 * @return bool True if successful, false otherwise.
	 */
	public function doBackupTable($table): bool
	{
		return Db::$db->backup_table($table, 'backup_' . $table);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Prepare the configuration to handle support with some older installs.
	 */
	private function prepareUpgrade(): void
	{
		// SMF 2.1: We don't use "-utf8" anymore...  Tweak the entry that may have been loaded by Settings.php
		if (isset(Config::$language)) {
			Config::$language = str_ireplace('-utf8', '', basename(Config::$language, '.lng'));
		}

		// SMF 1.x didn't support multiple database types.
		// SMF 2.0 used 'mysqli' for a short time.
		if (empty(Config::$db_type) || Config::$db_type == 'mysqli') {
			Config::$db_type = 'mysql';
			// If overriding Config::$db_type, need to set its Settings.php entry, too.
			$this->updateSettingsFile(['db_type' => 'mysql']);
		}

		try {
			Maintenance::loadDatabase();
			Maintenance::loadModSettings();
			Maintenance::setThemeData();
		} catch (\Throwable $e) {
			die($e->getMessage());
		}


		$this->getProgress();

		// Template needs to know about this.
		Utils::$context['started'] = &$this->time_started;
		Utils::$context['updated'] = &$this->time_updated;
		Utils::$context['user'] = &$this->user;
	}

	/**
	 * Get our upgrade data.
	 */
	private function getProgress(): void
	{
		try {
			$data = isset(Config::$custom['maintenance_tool_progress']) ? Utils::jsonDecode(base64_decode(Config::$custom['maintenance_tool_progress']), true) : [];
		} catch (\Throwable $e) {
			$data = [];
		}

		$this->time_started = (int) ($data['started'] ?? time());
		$this->time_updated = (int) ($data['updated'] ?? time());
		$this->debug = !empty($data['debug']);
		$this->skipped_migrations = (array) ($data['skipped'] ?? []);
		$this->user['id'] = (int) ($data['user_id'] ?? 0);
		$this->user['name'] = (string) ($data['user_name'] ?? '');
		$this->user['maint'] = (int) ($data['maint'] ?? Config::$maintenance);
		$this->start_smf_version = str_replace(' ', '.', strtolower($data['smf_version'] ?? Config::$modSettings['smfVersion'] ?? '0.0.dev.0'));
	}

	/**
	 * Save our data.
	 *
	 * @return bool True if we could update our settings file, false otherwise.
	 */
	private function saveProgress(): bool
	{
		if (Maintenance::$overall_percent < 100) {
			$data = base64_encode(json_encode([
				'started' => $this->time_started,
				'updated' => $this->time_updated,
				'debug' => $this->debug,
				'skipped' => $this->skipped_migrations,
				'user_id' => $this->user['id'],
				'user_name' => $this->user['name'],
				'maint' => $this->user['maint'] ?? 0,
				'smf_version' => $this->start_smf_version,
			]));
		} else {
			$data = '';
		}

		return $this->updateSettingsFile(['maintenance_tool_progress' => $data]);
	}

	/**
	 * Verify that the attachment directory is valid during the upgrade.
	 *
	 * This function safely checks both a serialized and json encoded attachment
	 * directory information.
	 *
	 * When multiple attachment directories exist, all are checked.
	 *
	 * @return bool True if no errors found, false otherwise.
	 */
	private function attachmentDirectoryIsValid(): bool
	{
		// A bit more complex, since it may be json or serialized, and it may be
		// an array or just a string...

		// PHP 8.0-8.2 has a terrible handling with unserialize in which
		// errors are fatal and not catch-able. Lets borrow some code from the
		// RFC that intends to fix this:
		// https://wiki.php.net/rfc/improve_unserialize_error_handling
		try {
			set_error_handler(static function ($severity, $message, $file, $line) {
				throw new \ErrorException($message, 0, $severity, $file, $line);
			});
			$ser_test = @unserialize(Config::$modSettings['attachmentUploadDir']);
		} catch (\Throwable $e) {
			$ser_test = false;
		} finally {
			restore_error_handler();
		}

		// Json is simple, it can be caught.
		try {
			$json_test = @json_decode(Config::$modSettings['attachmentUploadDir'], true);
		} catch (\Throwable $e) {
			$json_test = null;
		}

		$attach_directory_problem_found = false;

		// String?
		if (
			!empty(Config::$modSettings['attachmentUploadDir'])
			&& \is_string(Config::$modSettings['attachmentUploadDir'])
			&& is_dir(Config::$modSettings['attachmentUploadDir'])
		) {
			// OK...
		}
		// An array already?
		elseif (\is_array(Config::$modSettings['attachmentUploadDir'])) {
			foreach (Config::$modSettings['attachmentUploadDir'] as $dir) {
				if (!empty($dir) && !is_dir($dir)) {
					$attach_directory_problem_found = true;
				}
			}
		}
		// Serialized?
		elseif ($ser_test !== false) {
			if (\is_array($ser_test)) {
				foreach ($ser_test as $dir) {
					if (!empty($dir) && !is_dir($dir)) {
						$attach_directory_problem_found = true;
					}
				}
			} else {
				if (!empty($ser_test) && !is_dir($ser_test)) {
					$attach_directory_problem_found = true;
				}
			}
		}
		// JSON?  Note the test returns null if encoding was unsuccessful.
		elseif ($json_test !== null) {
			if (\is_array($json_test)) {
				foreach ($json_test as $dir) {
					if (!is_dir($dir)) {
						$attach_directory_problem_found = true;
					}
				}
			} else {
				if (!is_dir($json_test)) {
					$attach_directory_problem_found = true;
				}
			}
		}
		// Unclear, needs a look...
		else {
			$attach_directory_problem_found = true;
		}

		return $attach_directory_problem_found;
	}

	/**
	 * Determine if we need to enable or disable (during upgrades) SMF stat collection.
	 *
	 * @param array $settings Settings array, passed by reference.
	 */
	private function toggleSmStats(array &$settings): void
	{
		if (
			!empty($_POST['stats'])
			&& substr(Config::$boardurl, 0, 16) != 'http://localhost'
			&& empty(Config::$modSettings['allow_sm_stats'])
			&& empty(Config::$modSettings['enable_sm_stats'])
		) {
			Utils::$context['allow_sm_stats'] = true;

			// Attempt to register the site etc.
			$fp = @fsockopen('www.simplemachines.org', 443, $errno, $errstr);

			if (!$fp) {
				$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
			}

			if (!$fp) {
				return;
			}

			$out = 'GET /smf/stats/register_stats.php?site=' . base64_encode(Config::$boardurl) . ' HTTP/1.1' . "\r\n";
			$out .= 'Host: www.simplemachines.org' . "\r\n";
			$out .= 'Connection: Close' . "\r\n\r\n";
			fwrite($fp, $out);

			$return_data = '';

			while (!feof($fp)) {
				$return_data .= fgets($fp, 128);
			}

			fclose($fp);

			// Get the unique site ID.
			preg_match('~SITE-ID:\s(\w{10})~', $return_data, $ID);

			if (!empty($ID[1])) {
				$settings['sm_stats_key'] = $ID[1];
				$settings['enable_sm_stats'] = 1;
			}
		}
		// Don't remove stat collection unless we unchecked the box for real, not from the loop.
		elseif (empty($_POST['stats']) && empty(Utils::$context['allow_sm_stats'])) {
			$settings['enable_sm_stats'] = null;
		}
	}

	/**
	 * Performs a series of substeps.
	 *
	 * @param array $substeps All substep objects that we are running.
	 * @return bool True if we are done, false if we need to timeout and wait.
	 */
	private function performSubsteps(array $substeps): bool
	{
		Maintenance::$total_substeps = \count($substeps);

		// We are preparing for templating.
		if (!Sapi::isCLI() && !Maintenance::isJson()) {
			Utils::$context['continue'] = true;
			Utils::$context['current_substep'] = $substeps[Maintenance::getCurrentSubStep()]->name ?? '';

			return false;
		}

		// Load up the current user safely.
		if (!isset(User::$me)) {
			User::load($this->user['id'], dataset: UserDataset::Minimal);
			User::setMe($this->user['id']);

			if ($this->user['id'] === 0 && $this->user['name'] === 'Database Admin') {
				User::$me->username = User::$me->name = $this->user['name'];
			}
		}

		if (Maintenance::$total_substeps === 0) {
			Maintenance::jsonResponse([
				'name' => '',
				'skipped' => true,
				'substep' => 0,
				'start' => 0,
				'total' => 0,
				'debug' => [
					'call' => '',
				],
			]);

			return true;
		}

		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			$this->logProgress(Lang::getTxt('log_starting_step', ['num' => $this->getStep()->getId(), 'step' => $this->getStep()->getName()]));
		}

		/*
		 * When SKIP occurs, note it in JS and continue to next step.
		 * When success occurs, ensure it moves to next stesp.
		 * When error occurs, ensure we properly show the error.
		 */
		while (Maintenance::getCurrentSubStep() < Maintenance::$total_substeps) {
			$substep = $substeps[Maintenance::getCurrentSubStep()];

			$this->logProgress(' +++ ' . $substep->name, true);

			// If this is not a canidate for us to execute, skip it.
			try {
				if (!$substep->isCandidate()) {
					Maintenance::setCurrentSubStep();

					$this->logProgress(Lang::getTxt('log_skipped', file: 'Maintenance'));

					Maintenance::jsonResponse([
						'name' => $substep->name,
						'next' => $substeps[Maintenance::getCurrentSubStep()]->name ?? '',
						'skipped' => true,
						'substep' => Maintenance::getCurrentSubStep(),
						'start' => Maintenance::getCurrentStart(),
						'total' => Maintenance::$total_substeps,
						'debug' => [
							'call' => $substep::class,
						],
					]);

					continue;
				}
			} catch (\Throwable $e) {
				$this->logProgress(Lang::getTxt('log_failed_with_error', ['error' => $e->getMessage()], file: 'Maintenance'));

				Maintenance::jsonResponse([
					'name' => $substep->name,
					'failed' => true,
					'substep' => Maintenance::getCurrentSubStep(),
					'start' => Maintenance::getCurrentStart(),
					'total' => Maintenance::$total_substeps,
					'debug' => [
						'call' => $substep::class,
						'msg' => $e->getMessage(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					],
				]);

				return false;
			}

			try {
				if (!$substep->execute()) {
					$this->logProgress(Lang::getTxt('log_failed', file: 'Maintenance'));

					Maintenance::jsonResponse([
						'name' => $substep->name,
						'completed' => false,
						'substep' => Maintenance::getCurrentSubStep(),
						'start' => Maintenance::getCurrentStart(),
						'total' => Maintenance::$total_substeps,
						'debug' => [
							'call' => $substep::class,
						],
					]);

					return false;
				}
			} catch (\Throwable $e) {
				$this->logProgress(Lang::getTxt('log_failed_with_error', ['error' => $e->getMessage()], file: 'Maintenance'));

				Maintenance::jsonResponse([
					'name' => $substep->name,
					'failed' => true,
					'substep' => Maintenance::getCurrentSubStep(),
					'start' => Maintenance::getCurrentStart(),
					'total' => Maintenance::$total_substeps,
					'debug' => [
						'call' => $substep::class,
						'msg' => $e->getMessage(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					],
				]);

				return false;
			}

			$this->logProgress(Lang::getTxt('log_done', file: 'Maintenance'));

			// Increase our current substep by 1.
			Maintenance::setCurrentSubStep();
			Maintenance::setCurrentStart(0);

			// If this is JSON to keep it nice for the user do one table at a time anyway!
			if (Maintenance::isJson()) {
				Maintenance::jsonResponse([
					'name' => $substep->name,
					'next' => $substeps[Maintenance::getCurrentSubStep()]->name ?? '',
					'completed' => true,
					'substep' => Maintenance::getCurrentSubStep(),
					'start' => Maintenance::getCurrentStart(),
					'total' => Maintenance::$total_substeps,
					'debug' => [
						'call' => $substep::class,
					],
				]);
			}
		}

		return true;
	}
}
