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

namespace SMF\Maintenance\Tools;

use ArrayIterator;
use Exception;
use SMF\Config;
use SMF\Cookie;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Logging;
use SMF\Maintenance;
use SMF\Maintenance\DatabaseInterface;
use SMF\Maintenance\Step;
use SMF\Maintenance\Template;
use SMF\Maintenance\ToolsBase;
use SMF\Maintenance\ToolsInterface;
use SMF\PackageManager\FtpConnection;
use SMF\QueryString;
use SMF\Sapi;
use SMF\Security;
use SMF\TaskRunner;
use SMF\Time;
use SMF\Url;
use SMF\User;
use SMF\Utils;

/**
 * Upgrade tool.
 */
class Upgrade extends ToolsBase implements ToolsInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * When true, we can continue, when false the continue button is removed.
	 */
	public bool $continue = true;

	/**
	 * @var bool
	 *
	 * When true, we can skip this step, otherwise false and no skip option.
	 */
	public bool $skip = false;

	/**
	 * @var string
	 *
	 * The name of the script this tool uses. This is used by various actions and links.
	 */
	public string $script_name = 'upgrade.php';

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
    ];

    /**
     * @var array
     * 
     * Migrations we skipped.
     */
    public array $skipped_migrations = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var null|string
	 *
	 * Custom page title, otherwise we send the defaults.
	 */
	private ?string $page_title = null;

	/**
	 * @var string
	 *
	 * SMF Schema we have selected for this tool.
	 */
	private string $schema_version = 'v3_0';

	/**
	 * @var int
	 *
	 * The maximum time a single migration may take, in seconds.
	 */
	protected int $migration_time_limit = 3;

    /**
	 * @var int
	 *
     * The amount of seconds allowed between logins.
     * If the first user to login is inactive for this amount of seconds, a second login is allowed.
	 */
	protected int $inactive_timeout = 10;

    /**
     * @var array
     * 
     * Upgrade data stored in our Settings.php as we progress through the upgrade.
     */
    protected array $upgradeData = [];

	/**
	 * @var int
	 *
	 * The time we started the upgrade, populated by upgrade itself.
	 */
	protected int $time_started = 0;

    /****************
	 * Public methods
	 ****************/

     public function __construct()
     {
		Maintenance::$languages = $this->detectLanguages(['General', 'Maintenance']);
 
         if (empty(Maintenance::$languages)) {
             if (!Sapi::isCLI()) {
                 Template::missingLanguages();
             }
 
             throw new Exception('This script was unable to find this tools\'s language file or files.');
         } else {
             $requested_lang = Maintenance::getRequestedLanguage();
 
             // Ensure SMF\Lang knows the path to the language directory.
             Lang::addDirs(Config::$languagesdir);
 
             // And now load the language file.
             Lang::load('Maintenance', $requested_lang);
 
             // Assume that the admin likes that language.
             if ($requested_lang !== 'en_US') {
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
        }
        catch (Exception $e) {
        }

        // SMF\Config, and SMF\Utils.
        Config::load();
        Utils::load();

        $this->PrepareUpgrade();

		try
		{
			Maintenance::loadDatabase();
		}
		catch (Exception $exception)
		{
			die(Lang::getTxt('error_sourcefile_missing', ['file' => 'Db/APIs/' . Db::getClass(Config::$db_type) . '.php']));
		}

		// If they don't have the file, they're going to get a warning anyway so we won't need to clean request vars.
		if (class_exists('SMF\\QueryString')) {
			QueryString::cleanRequest();
		}
	}

	/**
	 * Gets our page title to be sent to the template.
	 * Selection is in the following order:
	 *  1. A custom page title.
	 *  2. Step has provided a title.
	 *  3. Default for the installer tool.
	 *
	 * @return string Page Title
	 */
	public function getPageTitle(): string
	{
		return $this->page_title ?? $this->getSteps()[Maintenance::getCurrentStep()]->getTitle() ?? Lang::$txt['smf_upgrade'];
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
			0 => new Step(
				id: 1,
				name: Lang::$txt['upgrade_step_login'],
				function: 'WelcomeLogin',
				progress: 2,
			),
			1 => new Step(
				id: 2,
				name: Lang::$txt['upgrade_step_options'],
				function: 'UpgradeOptions',
				progress: 3,
			),
			2 => new Step(
				id: 3,
				name: Lang::$txt['upgrade_step_backup'],
				function: 'BackupDatabase',
				progress: 15,
			),
			3 => new Step(
				id: 4,
				name: Lang::$txt['upgrade_step_migration'],
				function: 'Migrations',
				progress: 50,
			),
			4 => new Step(
				id: 5,
				name: Lang::$txt['upgrade_step_cleanup'],
				function: 'Cleanup',
				progress: 30,
			),
			5 => new Step(
				id: 6,
				name: Lang::$txt['upgrade_step_delete'],
				function: 'DeleteUpgrade',
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
		return $this->getSteps()[Maintenance::getCurrentStep()]->getName();
	}

	/**
	 * Welcome action.
	 *
	 * @return bool True if we can continue, false otherwise.
	 */
	public function WelcomeLogin(): bool
    {
		// Needs to at least meet our miniumn version.
		if ((version_compare(Maintenance::getRequiredVersionForPHP(), PHP_VERSION, '>='))) {
			Maintenance::$fatal_error = Lang::$txt['error_php_too_low'];

			return false;
		}

		return false;
    }


    /**
     * Prepare the configuration to handle support with some older installs .
     * 
     * @return void 
     */
    private function PrepareUpgrade(): void
    {
        // SMF 2.1: We don't use "-utf8" anymore...  Tweak the entry that may have been loaded by Settings.php
        if (isset(Config::$language)) {
            Config::$language = str_ireplace('-utf8', '', basename(Config::$language, '.lng'));
        }

		// SMF 1.x didn't support mulitple database types.
		// SMF 2.0 used 'mysqli' for a short time.
		if (empty(Config::$db_type) || Config::$db_type == 'mysqli') {
			Config::$db_type = 'mysql';
			// If overriding Config::$db_type, need to set its settings.php entry too
			Config::updateSettingsFile(['db_type' => 'mysql']);
		}
	
        $this->getUpgradeData();

        $this->time_started = ((int) $this->upgradeData['started']) ?? time();
        $this->time_updated = ((int) $this->upgradeData['updated']) ?? time();
        $this->debug = ((bool) $this->upgradeData['debug']) ?? false;
        $this->skipped_migrations = ((array) $this->upgradeData['skipped']) ?? [];
        $this->user['id'] = ((int) $this->upgradeData['user_id']) ?? 0;
        $this->user['name'] = $this->upgradeData['user_name'] ?? '';
    }

    /**
     * Get our upgrade data.
     * 
     * @return array Upgrade data.
     */
    private function getUpgradeData(): array
    {
        $defined_vars = Config::getCurrentSettings();

        return $defined_vars['upgradeData'] ?? [];
    }

    /**
     * Save our data.
     * 
     * @return bool True if we could update our settings file, false otherwise.
     */
    private function saveUpgradeData(): bool
    {
        return Config::updateSettingsFile(['upgradeData' => json_encode([
            'started' => $this->time_started,
            'updated' => $this->upgradeData['updated'],
            'debug' => $this->debug,
            'skipped' => $this->skipped_migrations,
            'user_id' => $this->user['id'],
            'user_name' => $this->user['name']
        ])]);
    }
}