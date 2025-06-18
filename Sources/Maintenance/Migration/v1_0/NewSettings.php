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

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Config;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Sapi;

class NewSettings extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding new settings';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v1_0\Settings();
		$table->dropIndex('variable');

		// Renamed setting.
		if (isset(Config::$modSettings['guest_hideEmail'])) {
			Config::updateModSettings([
				'guest_hideContacts' => Config::$modSettings['guest_hideEmail'],
				'guest_hideEmail' => null,
			]);
		}

		// New settings.
		Config::updateModSettings([
			'news' => Config::$modSettings['news'] ?? '',
			'compactTopicPagesContiguous' => Config::$modSettings['compactTopicPagesContiguous'] ?? 5,
			'compactTopicPagesEnable' => Config::$modSettings['compactTopicPagesEnable'] ?? 1,
			'enableStickyTopics' => Config::$modSettings['enableStickyTopics'] ?? 1,
			'todayMod' => Config::$modSettings['todayMod'] ?? 1,
			'karmaMode' => Config::$modSettings['karmaMode'] ?? 0,
			'karmaTimeRestrictAdmins' => Config::$modSettings['karmaTimeRestrictAdmins'] ?? 1,
			'enablePreviousNext' => Config::$modSettings['enablePreviousNext'] ?? 1,
			'pollMode' => Config::$modSettings['pollMode'] ?? 1,
			'enableVBStyleLogin' => Config::$modSettings['enableVBStyleLogin'] ?? 1,
			'enableCompressedOutput' => Config::$modSettings['enableCompressedOutput'] ?? 1,
			'karmaWaitTime' => Config::$modSettings['karmaWaitTime'] ?? 1,
			'karmaMinPosts' => Config::$modSettings['karmaMinPosts'] ?? 0,
			'karmaLabel' => Config::$modSettings['karmaLabel'] ?? 'Karma:',
			'karmaSmiteLabel' => Config::$modSettings['karmaSmiteLabel'] ?? '[smite]',
			'karmaApplaudLabel' => Config::$modSettings['karmaApplaudLabel'] ?? '[applaud]',
			'attachmentSizeLimit' => Config::$modSettings['attachmentSizeLimit'] ?? 128,
			'attachmentPostLimit' => Config::$modSettings['attachmentPostLimit'] ?? 192,
			'attachmentNumPerPostLimit' => Config::$modSettings['attachmentNumPerPostLimit'] ?? 4,
			'attachmentDirSizeLimit' => Config::$modSettings['attachmentDirSizeLimit'] ?? 10240,
			'attachmentUploadDir' => Config::$modSettings['attachmentUploadDir'] ?? Config::$boarddir . DIRECTORY_SEPARATOR . 'attachments',
			'attachmentExtensions' => Config::$modSettings['attachmentExtensions'] ?? 'txt,doc,pdf,jpg,gif,mpg,png',
			'attachmentCheckExtensions' => Config::$modSettings['attachmentCheckExtensions'] ?? 1,
			'attachmentShowImages' => Config::$modSettings['attachmentShowImages'] ?? 1,
			'attachmentEnable' => Config::$modSettings['attachmentEnable'] ?? 1,
			'attachmentEncryptFilenames' => Config::$modSettings['attachmentEncryptFilenames'] ?? 1,
			'censorIgnoreCase' => Config::$modSettings['censorIgnoreCase'] ?? 1,
			'mostOnline' => Config::$modSettings['mostOnline'] ?? 1,
			'mostOnlineToday' => Config::$modSettings['mostOnlineToday'] ?? 1,
			'mostDate' => Config::$modSettings['mostDate'] ?? time(),
			'trackStats' => Config::$modSettings['trackStats'] ?? 1,
			'userLanguage' => Config::$modSettings['userLanguage'] ?? 1,
			'titlesEnable' => Config::$modSettings['titlesEnable'] ?? 1,
			'topicSummaryPosts' => Config::$modSettings['topicSummaryPosts'] ?? 15,
			'enableErrorLogging' => Config::$modSettings['enableErrorLogging'] ?? 1,
			'onlineEnable' => Config::$modSettings['onlineEnable'] ?? 0,
			'cal_holidaycolor' => Config::$modSettings['cal_holidaycolor'] ?? '000080',
			'cal_bdaycolor' => Config::$modSettings['cal_bdaycolor'] ?? '920AC4',
			'cal_eventcolor' => Config::$modSettings['cal_eventcolor'] ?? '078907',
			'cal_enabled' => Config::$modSettings['cal_enabled'] ?? 0,
			'cal_maxyear' => Config::$modSettings['cal_maxyear'] ?? 2010,
			'cal_minyear' => Config::$modSettings['cal_minyear'] ?? 2002,
			'cal_daysaslink' => Config::$modSettings['cal_daysaslink'] ?? 0,
			'cal_defaultboard' => Config::$modSettings['cal_defaultboard'] ?? '',
			'cal_showeventsonindex' => Config::$modSettings['cal_showeventsonindex'] ?? 0,
			'cal_showbdaysonindex' => Config::$modSettings['cal_showbdaysonindex'] ?? 0,
			'cal_showholidaysonindex' => Config::$modSettings['cal_showholidaysonindex'] ?? 0,
			'cal_showweeknum' => Config::$modSettings['cal_showweeknum'] ?? 0,
			'cal_maxspan' => Config::$modSettings['cal_maxspan'] ?? 7,
			'smtp_host' => Config::$modSettings['smtp_host'] ?? '',
			'smtp_username' => Config::$modSettings['smtp_username'] ?? '',
			'smtp_password' => Config::$modSettings['smtp_password'] ?? '',
			'mail_type' => Config::$modSettings['mail_type'] ?? 0,
			'timeLoadPageEnable' => Config::$modSettings['timeLoadPageEnable'] ?? 0,
			'totalTopics' => Config::$modSettings['totalTopics'] ?? 1,
			'totalMessages' => Config::$modSettings['totalMessages'] ?? 1,
			'simpleSearch' => Config::$modSettings['simpleSearch'] ?? 0,
			'censor_vulgar' => Config::$modSettings['censor_vulgar'] ?? '',
			'censor_proper' => Config::$modSettings['censor_proper'] ?? '',
			'mostOnlineToday' => Config::$modSettings['mostOnlineToday'] ?? 1,
			'enablePostHTML' => Config::$modSettings['enablePostHTML'] ?? 0,
			'theme_allow' => Config::$modSettings['theme_allow'] ?? 1,
			'theme_default' => Config::$modSettings['theme_default'] ?? 1,
			'theme_guests' => Config::$modSettings['theme_guests'] ?? 1,
			'xmlnews_enable' => Config::$modSettings['xmlnews_enable'] ?? 1,
			'xmlnews_maxlen' => Config::$modSettings['xmlnews_maxlen'] ?? 255,
			'hotTopicPosts' => Config::$modSettings['hotTopicPosts'] ?? 15,
			'hotTopicVeryPosts' => Config::$modSettings['hotTopicVeryPosts'] ?? 25,
			'allow_editDisplayName' => Config::$modSettings['allow_editDisplayName'] ?? 1,
			'number_format' => Config::$modSettings['number_format'] ?? '1234.00',
			'attachmentEncryptFilenames' => Config::$modSettings['attachmentEncryptFilenames'] ?? 1,
			'autoLinkUrls' => Config::$modSettings['autoLinkUrls'] ?? 1,
			'avatar_allow_server_stored' => Config::$modSettings['avatar_allow_server_stored'] ?? 1,
			'avatar_check_size' => Config::$modSettings['avatar_check_size'] ?? 0,
			'avatar_action_too_large' => Config::$modSettings['avatar_action_too_large'] ?? 'option_user_resize',
			'avatar_resize_upload' => Config::$modSettings['avatar_resize_upload'] ?? 1,
			'avatar_download_png' => Config::$modSettings['avatar_download_png'] ?? 1,
			'failed_login_threshold' => Config::$modSettings['failed_login_threshold'] ?? 3,
			'edit_wait_time' => Config::$modSettings['edit_wait_time'] ?? 90,
			'autoFixDatabase' => Config::$modSettings['autoFixDatabase'] ?? 1,
			'autoOptDatabase' => Config::$modSettings['autoOptDatabase'] ?? 7,
			'autoOptMaxOnline' => Config::$modSettings['autoOptMaxOnline'] ?? 0,
			'autoOptLastOpt' => Config::$modSettings['autoOptLastOpt'] ?? 0,
			'enableParticipation' => Config::$modSettings['enableParticipation'] ?? 1,
			'recycle_enable' => Config::$modSettings['recycle_enable'] ?? 0,
			'recycle_board' => Config::$modSettings['recycle_board'] ?? 0,
			'banLastUpdated' => Config::$modSettings['banLastUpdated'] ?? 0,
			'enableAllMessages' => Config::$modSettings['enableAllMessages'] ?? 0,
			'fixLongWords' => Config::$modSettings['fixLongWords'] ?? 0,
			'knownThemes' => Config::$modSettings['knownThemes'] ?? '1,2',
			'who_enabled' => Config::$modSettings['who_enabled'] ?? 1,
			'lastActive' => Config::$modSettings['lastActive'] ?? 15,
			'allow_hideOnline' => Config::$modSettings['allow_hideOnline'] ?? 1,
			'guest_hideContacts' => Config::$modSettings['guest_hideContacts'] ?? 0,
			'registration_method' => match (true) {
				!empty(Config::$modSettings['registration_disabled']) => 3,
				!empty(Config::$modSettings['approve_registration']) => 2,
				!empty($GLOBALS['emailpassword']) => 1,
				!empty(Config::$modSettings['send_validation']) => 1,
				default => 0,
			},
			'send_validation_onChange' => $GLOBALS['emailnewpass'] ?? 0,
			'send_welcomeEmail' => $GLOBALS['emailwelcome'] ?? 1,
			'allow_hideEmail' => $GLOBALS['allow_hide_email'] ?? 1,
			'allow_guestAccess' => $GLOBALS['guestaccess'] ?? 1,
			'time_format' => !empty($GLOBALS['timeformatstring']) ? $GLOBALS['timeformatstring'] : '%B %d, %Y, %I:%M:%S %p',
			'enableBBC' => $GLOBALS['enable_ubbc'] ?? 1,
			'max_messageLength' => $GLOBALS['MaxMessLen'] ?? 20000,
			'max_signatureLength' => $GLOBALS['MaxSigLen'] ?? 300,
			'spamWaitTime' => $GLOBALS['timeout'] ?? 5,
			'avatar_directory' => Sapi::canonicalPath($GLOBALS['facesdir'] ?? './avatars', Config::$boarddir),
			'avatar_url' => $GLOBALS['facesurl'] ?? Config::$boardurl . '/avatars',
			'avatar_max_height_external' => $GLOBALS['userpic_height'] ?? 65,
			'avatar_max_width_external' => $GLOBALS['userpic_width'] ?? 65,
			'avatar_max_height_upload' => $GLOBALS['userpic_height'] ?? 65,
			'avatar_max_width_upload' => $GLOBALS['userpic_width'] ?? 65,
			'defaultMaxMessages' => $GLOBALS['maxmessagedisplay'] ?? 15,
			'defaultMaxTopics' => $GLOBALS['maxdisplay'] ?? 20,
			'defaultMaxMembers' => $GLOBALS['MembersPerPage'] ?? 30,
			'time_offset' => $GLOBALS['timeoffset'] ?? 0,
			'cookieTime' => $GLOBALS['Cookie_Length'] ?? 60,
			'requireAgreement' => $GLOBALS['RegAgree'] ?? 1,
			'smileys_dir' => Config::$boarddir . DIRECTORY_SEPARATOR . 'Smileys',
			'smileys_url' => Config::$boardurl . '/Smileys',
			'smiley_sets_known' => 'default,classic',
			'smiley_sets_names' => 'Default' . "\n" . 'Classic',
			'smiley_sets_default' => 'default',
			'censorIgnoreCase' => 1,
			'cal_days_for_index' => 7,
			'unapprovedMembers' => 0,
			'default_personalText' => '',
			'attachmentPostLimit' => 192,
			'attachmentNumPerPostLimit' => 4,
			'package_make_backups' => 1,
			'databaseSession_loose' => 1,
			'databaseSession_lifetime' => 2880,
			'smtp_port' => 25,
			'search_cache_size' => 50,
			'search_results_per_page' => 30,
			'search_weight_frequency' => 30,
			'search_weight_age' => 25,
			'search_weight_length' => 20,
			'search_weight_subject' => 15,
			'search_weight_first_message' => 10,
			'agreement' => null,
			'cal_today_updated' => '00000000',
			'enable_password_conversion' => 1,
		]);

		$this->handleTimeout();

		return true;
	}
}
