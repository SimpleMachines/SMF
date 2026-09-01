<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v1_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Settings extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'variable' => 'allow_editDisplayName',
			'value' => 1,
		],
		[
			'variable' => 'allow_guestAccess',
			'value' => 1,
		],
		[
			'variable' => 'allow_hideEmail',
			'value' => 1,
		],
		[
			'variable' => 'allow_hideOnline',
			'value' => 1,
		],
		[
			'variable' => 'attachmentCheckExtensions',
			'value' => 1,
		],
		[
			'variable' => 'attachmentDirSizeLimit',
			'value' => 10240,
		],
		[
			'variable' => 'attachmentEnable',
			'value' => 1,
		],
		[
			'variable' => 'attachmentEncryptFilenames',
			'value' => 1,
		],
		[
			'variable' => 'attachmentExtensions',
			'value' => 'txt,doc,pdf,jpg,gif,mpg,png',
		],
		[
			'variable' => 'attachmentNumPerPostLimit',
			'value' => 4,
		],
		[
			'variable' => 'attachmentPostLimit',
			'value' => 192,
		],
		[
			'variable' => 'attachmentShowImages',
			'value' => 1,
		],
		[
			'variable' => 'attachmentSizeLimit',
			'value' => 128,
		],
		[
			'variable' => 'attachmentUploadDir',
			'value' => '{$boarddir}/attachments',
		],
		[
			'variable' => 'autoFixDatabase',
			'value' => 1,
		],
		[
			'variable' => 'autoLinkUrls',
			'value' => 1,
		],
		[
			'variable' => 'autoOptDatabase',
			'value' => 7,
		],
		[
			'variable' => 'autoOptLastOpt',
			'value' => 0,
		],
		[
			'variable' => 'autoOptMaxOnline',
			'value' => 0,
		],
		[
			'variable' => 'avatar_action_too_large',
			'value' => 'option_html_resize',
		],
		[
			'variable' => 'avatar_allow_external_url',
			'value' => 1,
		],
		[
			'variable' => 'avatar_allow_server_stored',
			'value' => 1,
		],
		[
			'variable' => 'avatar_allow_upload',
			'value' => 0,
		],
		[
			'variable' => 'avatar_check_size',
			'value' => 0,
		],
		[
			'variable' => 'avatar_directory',
			'value' => '{$boarddir}/avatars',
		],
		[
			'variable' => 'avatar_download_png',
			'value' => 1,
		],
		[
			'variable' => 'avatar_max_height_external',
			'value' => 65,
		],
		[
			'variable' => 'avatar_max_height_upload',
			'value' => 65,
		],
		[
			'variable' => 'avatar_max_width_external',
			'value' => 65,
		],
		[
			'variable' => 'avatar_max_width_upload',
			'value' => 65,
		],
		[
			'variable' => 'avatar_resize_upload',
			'value' => 1,
		],
		[
			'variable' => 'avatar_url',
			'value' => '{$boardurl}/avatars',
		],
		[
			'variable' => 'banLastUpdated',
			'value' => 0,
		],
		[
			'variable' => 'cal_allowspan',
			'value' => 0,
		],
		[
			'variable' => 'cal_bdaycolor',
			'value' => '920AC4',
		],
		[
			'variable' => 'cal_days_for_index',
			'value' => 7,
		],
		[
			'variable' => 'cal_daysaslink',
			'value' => 0,
		],
		[
			'variable' => 'cal_defaultboard',
			'value' => '',
		],
		[
			'variable' => 'cal_enabled',
			'value' => 0,
		],
		[
			'variable' => 'cal_eventcolor',
			'value' => '078907',
		],
		[
			'variable' => 'cal_holidaycolor',
			'value' => '000080',
		],
		[
			'variable' => 'cal_maxspan',
			'value' => 7,
		],
		[
			'variable' => 'cal_maxyear',
			'value' => 2010,
		],
		[
			'variable' => 'cal_minyear',
			'value' => 2002,
		],
		[
			'variable' => 'cal_showbdaysonindex',
			'value' => 0,
		],
		[
			'variable' => 'cal_showeventsonindex',
			'value' => 0,
		],
		[
			'variable' => 'cal_showholidaysonindex',
			'value' => 0,
		],
		[
			'variable' => 'cal_showweeknum',
			'value' => 0,
		],
		[
			'variable' => 'censorIgnoreCase',
			'value' => 1,
		],
		[
			'variable' => 'censorWholeWord',
			'value' => 0,
		],
		[
			'variable' => 'censor_proper',
			'value' => '',
		],
		[
			'variable' => 'censor_vulgar',
			'value' => '',
		],
		[
			'variable' => 'compactTopicPagesContiguous',
			'value' => 5,
		],
		[
			'variable' => 'compactTopicPagesEnable',
			'value' => 1,
		],
		[
			'variable' => 'cookieTime',
			'value' => 60,
		],
		[
			'variable' => 'databaseSession_enable',
			'value' => '{$databaseSession_enable}',
		],
		[
			'variable' => 'databaseSession_lifetime',
			'value' => 2880,
		],
		[
			'variable' => 'databaseSession_loose',
			'value' => 1,
		],
		[
			'variable' => 'defaultMaxMembers',
			'value' => 30,
		],
		[
			'variable' => 'defaultMaxMessages',
			'value' => 15,
		],
		[
			'variable' => 'defaultMaxTopics',
			'value' => 20,
		],
		[
			'variable' => 'default_personalText',
			'value' => '',
		],
		[
			'variable' => 'disableTemporaryTables',
			'value' => 0,
		],
		[
			'variable' => 'edit_wait_time',
			'value' => 90,
		],
		[
			'variable' => 'enableAllMessages',
			'value' => 0,
		],
		[
			'variable' => 'enableBBC',
			'value' => 1,
		],
		[
			'variable' => 'enableCompressedOutput',
			'value' => '{$enableCompressedOutput}',
		],
		[
			'variable' => 'enableEmbeddedFlash',
			'value' => 0,
		],
		[
			'variable' => 'enableErrorLogging',
			'value' => 1,
		],
		[
			'variable' => 'enableNewReplyWarning',
			'value' => 1,
		],
		[
			'variable' => 'enableParticipation',
			'value' => 1,
		],
		[
			'variable' => 'enablePostHTML',
			'value' => 0,
		],
		[
			'variable' => 'enablePreviousNext',
			'value' => 1,
		],
		[
			'variable' => 'enableReportToMod',
			'value' => 1,
		],
		[
			'variable' => 'enableSpellChecking',
			'value' => 1,
		],
		[
			'variable' => 'enableStickyTopics',
			'value' => 1,
		],
		[
			'variable' => 'enableVBStyleLogin',
			'value' => 1,
		],
		[
			'variable' => 'failed_login_threshold',
			'value' => 3,
		],
		[
			'variable' => 'fixLongWords',
			'value' => 0,
		],
		[
			'variable' => 'globalCookies',
			'value' => 0,
		],
		[
			'variable' => 'guest_hideContacts',
			'value' => 0,
		],
		[
			'variable' => 'hitStats',
			'value' => 0,
		],
		[
			'variable' => 'hotTopicPosts',
			'value' => 15,
		],
		[
			'variable' => 'hotTopicVeryPosts',
			'value' => 25,
		],
		[
			'variable' => 'karmaApplaudLabel',
			'value' => '[applaud]',
		],
		[
			'variable' => 'karmaLabel',
			'value' => 'Karma:',
		],
		[
			'variable' => 'karmaMinPosts',
			'value' => 0,
		],
		[
			'variable' => 'karmaMode',
			'value' => 0,
		],
		[
			'variable' => 'karmaSmiteLabel',
			'value' => '[smite]',
		],
		[
			'variable' => 'karmaTimeRestrictAdmins',
			'value' => 1,
		],
		[
			'variable' => 'karmaWaitTime',
			'value' => 1,
		],
		[
			'variable' => 'knownThemes',
			'value' => '1,2',
		],
		[
			'variable' => 'lastActive',
			'value' => 15,
		],
		[
			'variable' => 'localCookies',
			'value' => 0,
		],
		[
			'variable' => 'mail_type',
			'value' => 'sendmail',
		],
		[
			'variable' => 'maxMsgID',
			'value' => 1,
		],
		[
			'variable' => 'max_messageLength',
			'value' => 20000,
		],
		[
			'variable' => 'max_signatureLength',
			'value' => 300,
		],
		[
			'variable' => 'maxheight',
			'value' => 0,
		],
		[
			'variable' => 'maxwidth',
			'value' => 0,
		],
		[
			'variable' => 'modlog_enabled',
			'value' => 0,
		],
		[
			'variable' => 'mostDate',
			'value' => '{$current_time}',
		],
		[
			'variable' => 'mostOnline',
			'value' => 1,
		],
		[
			'variable' => 'mostOnlineToday',
			'value' => 1,
		],
		[
			'variable' => 'news',
			'value' => 'SMF - Just Installed',
		],
		[
			'variable' => 'notifyAnncmnts_UserDisable',
			'value' => 1,
		],
		[
			'variable' => 'number_format',
			'value' => '1234.00',
		],
		[
			'variable' => 'onlineEnable',
			'value' => 0,
		],
		[
			'variable' => 'package_make_backups',
			'value' => 1,
		],
		[
			'variable' => 'pollMode',
			'value' => 1,
		],
		[
			'variable' => 'queryless_urls',
			'value' => 0,
		],
		[
			'variable' => 'recycle_board',
			'value' => 0,
		],
		[
			'variable' => 'recycle_enable',
			'value' => 0,
		],
		[
			'variable' => 'registration_method',
			'value' => 0,
		],
		[
			'variable' => 'removeNestedQuotes',
			'value' => 0,
		],
		[
			'variable' => 'requireAgreement',
			'value' => 1,
		],
		[
			'variable' => 'reserveCase',
			'value' => 1,
		],
		[
			'variable' => 'reserveName',
			'value' => 1,
		],
		[
			'variable' => 'reserveNames',
			'value' => 'Admin' . "\n" . 'Webmaster' . "\n" . 'Guest',
		],
		[
			'variable' => 'reserveUser',
			'value' => 1,
		],
		[
			'variable' => 'reserveWord',
			'value' => 0,
		],
		[
			'variable' => 'search_cache_size',
			'value' => 50,
		],
		[
			'variable' => 'search_match_complete_words',
			'value' => 0,
		],
		[
			'variable' => 'search_results_per_page',
			'value' => 30,
		],
		[
			'variable' => 'search_weight_age',
			'value' => 25,
		],
		[
			'variable' => 'search_weight_first_message',
			'value' => 10,
		],
		[
			'variable' => 'search_weight_frequency',
			'value' => 30,
		],
		[
			'variable' => 'search_weight_length',
			'value' => 20,
		],
		[
			'variable' => 'search_weight_subject',
			'value' => 15,
		],
		[
			'variable' => 'send_validation_onChange',
			'value' => 0,
		],
		[
			'variable' => 'send_welcomeEmail',
			'value' => 1,
		],
		[
			'variable' => 'simpleSearch',
			'value' => 0,
		],
		[
			'variable' => 'smfVersion',
			'value' => '1.0.17',
		],
		[
			'variable' => 'smiley_enable',
			'value' => 1,
		],
		[
			'variable' => 'smiley_sets_default',
			'value' => 'default',
		],
		[
			'variable' => 'smiley_sets_enable',
			'value' => 0,
		],
		[
			'variable' => 'smiley_sets_known',
			'value' => 'default,classic',
		],
		[
			'variable' => 'smiley_sets_names',
			'value' => 'Default' . "\n" . 'Classic',
		],
		[
			'variable' => 'smileys_dir',
			'value' => '{$boarddir}/Smileys',
		],
		[
			'variable' => 'smileys_url',
			'value' => '{$boardurl}/Smileys',
		],
		[
			'variable' => 'smtp_host',
			'value' => '',
		],
		[
			'variable' => 'smtp_password',
			'value' => '',
		],
		[
			'variable' => 'smtp_port',
			'value' => 25,
		],
		[
			'variable' => 'smtp_username',
			'value' => '',
		],
		[
			'variable' => 'spamWaitTime',
			'value' => 5,
		],
		[
			'variable' => 'theme_allow',
			'value' => 1,
		],
		[
			'variable' => 'theme_default',
			'value' => 1,
		],
		[
			'variable' => 'theme_guests',
			'value' => 1,
		],
		[
			'variable' => 'timeLoadPageEnable',
			'value' => 0,
		],
		[
			'variable' => 'time_format',
			'value' => '{$default_time_format}',
		],
		[
			'variable' => 'time_offset',
			'value' => 0,
		],
		[
			'variable' => 'titlesEnable',
			'value' => 1,
		],
		[
			'variable' => 'todayMod',
			'value' => 1,
		],
		[
			'variable' => 'topbottomEnable',
			'value' => 0,
		],
		[
			'variable' => 'topicSummaryPosts',
			'value' => 15,
		],
		[
			'variable' => 'totalMessages',
			'value' => 1,
		],
		[
			'variable' => 'totalTopics',
			'value' => 1,
		],
		[
			'variable' => 'trackStats',
			'value' => 1,
		],
		[
			'variable' => 'unapprovedMembers',
			'value' => 0,
		],
		[
			'variable' => 'userLanguage',
			'value' => 1,
		],
		[
			'variable' => 'who_enabled',
			'value' => 1,
		],
		[
			'variable' => 'xmlnews_enable',
			'value' => 1,
		],
		[
			'variable' => 'xmlnews_maxlen',
			'value' => 255,
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'settings';

		$this->columns = [
			'variable' => new Column(
				name: 'variable',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'value' => new Column(
				name: 'value',
				type: 'text',
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'variable',
						'size' => 30,
					],
				],
			),
		];

		parent::__construct();
	}
}
