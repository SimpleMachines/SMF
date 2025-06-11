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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class NewSettings extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Reorganizing configuration settings';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$smf_version = str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0'));

		$request = $this->query(
			'SELECT COUNT(*)
			FROM {db_prefix}members',
		);
		list($total_members) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Config::updateModSettings([
			'totalMembers' => $total_members,
		]);

		// Renaming a bunch of settings.
		if (isset(Config::$modSettings['notify_on_new_registration'])) {
			Config::updateModSettings([
				'notify_new_registration' => Config::$modSettings['notify_on_new_registration'],
				'notify_on_new_registration' => null,
			]);
		}

		if (isset(Config::$modSettings['maxwidth'])) {
			Config::updateModSettings([
				'max_image_width' => Config::$modSettings['maxwidth'],
				'maxwidth' => null,
			]);
		}

		if (isset(Config::$modSettings['maxheight'])) {
			Config::updateModSettings([
				'max_image_height' => Config::$modSettings['maxheight'],
				'maxheight' => null,
			]);
		}

		if (isset(Config::$modSettings['search_match_complete_words'])) {
			Config::updateModSettings([
				'search_method' => Config::$modSettings['search_match_complete_words'],
				'search_match_complete_words' => null,
			]);
		}

		if (isset(Config::$modSettings['notifyAnncmnts_UserDisable'])) {
			Config::updateModSettings([
				'allow_disableAnnounce' => Config::$modSettings['notifyAnncmnts_UserDisable'],
				'notifyAnncmnts_UserDisable' => null,
			]);
		}

		// Replacing various values.
		Config::updateModSettings([
			'mail_type' => \in_array(Config::$modSettings['mail_type'] ?? 0, ['sendmail', 0]) ? 0 : 1,
		]);

		// Adding new settings.
		Config::updateModSettings([
			'oldTopicDays' => '120',
			'cal_showeventsoncalendar' => '1',
			'cal_showbdaysoncalendar' => '1',
			'cal_showholidaysoncalendar' => '1',
			'allow_disableAnnounce' => '1',
			'attachmentThumbnails' => '1',
			'attachmentThumbWidth' => '150',
			'attachmentThumbHeight' => '150',
			'max_pm_recipients' => '10',
		]);

		// Hopefully 90 days is enough?
		if (version_compare($smf_version, '1.1', '<')) {
			Config::updateModSettings([
				'disableHashTime' => time() + 7776000,
			]);
		}

		// Enable the buddy list for those used to it.
		if (version_compare($smf_version, '1.1.beta.4', '<=')) {
			Config::updateModSettings([
				'enable_buddylist' => 1,
			]);
		}

		// Adding PM spam protection settings.
		if (empty(Config::$modSettings['pm_spam_settings'])) {
			if (isset(Config::$modSettings['max_pm_recipients'])) {
				$pm_spam_settings = (int) Config::$modSettings['max_pm_recipients'] . ',5,20';
			} else {
				$pm_spam_settings = '10,5,20';
			}

			Config::updateModSettings([
				'pm_spam_settings' => $pm_spam_settings,
			]);
		}

		// Converting search settings.
		if (!empty(Config::$modSettings['search_method'])) {
			$search_settings['search_match_words'] = 1;

			if (Config::$modSettings['search_method'] > 1) {
				$search_settings['search_index'] = 'fulltext';
			}

			if (Config::$modSettings['search_method'] == 3) {
				$search_settings['search_force_index'] = 1;
			}
		}

		// Removing obsolete settings.
		$to_delete = [
			'max_pm_recipients' => null,
			'totalMessag' => null,
			'redirectMetaRefresh' => null,
			'memberCount' => null,
			'cal_today_u' => null,
			'approve_registration' => null,
			'registration_disabled' => null,
			'requireRegistrationVerification' => null,
			'returnToPost' => null,
			'send_validation' => null,
			'search_max_cached_results' => null,
			'disableTemporaryTables' => null,
			'search_cache_size' => null,
			'enableReportToMod' => null,
			'search_method' => null,
		];

		foreach (
			[
				'modlog_enabled',
				'localCookies',
				'globalCookies',
				'send_welcomeEmail',
				'notify_new_registration',
				'removeNestedQuotes',
				'smiley_enable',
				'smiley_sets_enable',
				'allow_guestAccess',
				'userLanguage',
				'allow_editDisplayName',
				'allow_hideOnline',
				'allow_hideEmail',
				'guest_hideContacts',
				'titlesEnable',
				'search_match_complete_words',
				'cal_allowspan',
				'hitStats',
				'queryless_urls',
				'disableHostnameLookup',
				'messageIcons_enable',
				'disallow_sendBody',
				'censorWholeWord',
			] as $var
		) {
			if (empty(Config::$modSettings[$var])) {
				$to_delete[$var] = null;
			}
		}

		Config::updateModSettings($to_delete);

		// Encoding SMTP password.
		if (
			version_compare($smf_version, '1.1.rc.1', '<=')
			&& empty(Config::$modSettings['dont_repeat_smtp'])
		) {
			if (!empty(Config::$modSettings['smtp_password'])) {
				Config::updateModSettings([
					'smtp_password' => base64_encode(Config::$modSettings['smtp_password']),
				]);
			}

			// Don't let this run twice!
			Config::updateModSettings([
				'dont_repeat_smtp' => 1,
			]);
		}

		$this->handleTimeout();

		return true;
	}
}
