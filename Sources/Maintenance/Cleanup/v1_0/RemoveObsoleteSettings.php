<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Cleanup\v1_0;

use SMF\IntegrationHook;
use SMF\Maintenance\Cleanup\CleanupBase;
use SMF\Utils;

class RemoveObsoleteSettings extends CleanupBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Name of the cleanup task.
	 */
	public string $name = 'Removing obsolete settings';

	/**
	 * @var array
	 *
	 * Settings to delete.
	 */
	public array $obsolete_settings_defs = [
		'guestaccess' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'yyForceIIS' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'yyblankpageIIS' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'Cookie_Length' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'RegAgree' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'emailpassword' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'emailnewpass' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'emailwelcome' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'mailprog' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'smtp_server' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'mailtype' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'facesdir' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'facesurl' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'imagesdir' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'ubbcjspath' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'faderpath' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'helpfile' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'MenuType' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'curposlinks' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'profilebutton' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'timeformatstring' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'allow_hide_email' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'showlatestmember' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'shownewsfader' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'Show_RecentBar' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'Show_MemberBar' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'showmarkread' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'showmodify' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'ShowBDescrip' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'showuserpic' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'showusertext' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'showgenderimage' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'showyabbcbutt' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'enable_ubbc' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'enable_news' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'allowpics' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'enable_guestposting' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'enable_notification' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'autolinkurls' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'timeoffset' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'TopAmmount' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'MembersPerPage' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'maxdisplay' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'maxmessagedisplay' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'MaxMessLen' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'MaxSigLen' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'ClickLogTime' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'max_log_days_old' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'fadertime' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'timeout' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'JrPostNum' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'FullPostNum' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'SrPostNum' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'GodPostNum' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'userpic_width' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'userpic_height' => [
			'default' => 0,
			'auto_delete' => 3,
			'type' => 'integer',
		],
		'userpic_limits' => [
			'default' => '',
			'auto_delete' => 3,
			'type' => 'string',
		],
		'color' => [
			'default' => null,
			'auto_delete' => 3,
			'type' => ['NULL', 'array'],
			// Special search pattern needed because YaBB's settings file used a
			// separate statement for each element of this array.
			'search_pattern' => '/^\$color(\s*=\s*array\s*(?P<parentheses_a>\((?' . '>[^()]|(?P>parentheses_a))*\))|(?P<brackets>\[(?' . '>[^\[\]]|(?P>brackets))*\])|\[(?P<quote_k>["\'])\w+(?P>quote_k)\]\s*=\s*(?P<quote_v>["\'])(?:.(?!(?P>quote_v))|\\\(?=(?P>quote_v)))*.?(?P>quote_v))\s*;\h*$/m',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return (bool) preg_match(
			'/\$' . Utils::buildRegex(array_keys(self::$obsolete_settings_defs), '/') . '\b/',
			file_get_contents(SMF_SETTINGS_FILE),
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Add the obsolete settings to the list of defined settings.
		IntegrationHook::add('integrate_update_settings_file', __CLASS__ . '::addSettingsDefs', false);

		// Delete all the obsolete settings and rebuild the file.
		Maintenance::$tool->updateSettingsFile(
			config_vars: array_combine(
				array_keys(self::$obsolete_settings_defs),
				array_map(fn($def) => $def['default'], self::$obsolete_settings_defs),
			),
			rebuild: true,
		);

		// Remove the obsolete settings from the list of defined settings.
		IntegrationHook::remove('integrate_update_settings_file', __CLASS__ . '::addSettingsDefs', false);

		return true;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Adds the obsolete YaBB SE settings to the list of defined settings.
	 *
	 * @param array &$settings_defs A reference to Config::$settings_defs.
	 */
	public static function addSettingsDefs(array &$settings_defs): void
	{
		$settings_defs += self::$obsolete_settings_defs;
	}
}
