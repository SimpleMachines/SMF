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

namespace SMF\Db\Schema\v2_0;

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
			'variable' => 'admin_features',
			'value' => '',
		],
		[
			'variable' => 'allow_disableAnnounce',
			'value' => 1,
		],
		[
			'variable' => 'allow_editDisplayName',
			'value' => 1,
		],
		[
			'variable' => 'allow_guestAccess',
			'value' => 1,
		],
		[
			'variable' => 'allow_hideOnline',
			'value' => 1,
		],
		[
			'variable' => 'attachmentCheckExtensions',
			'value' => 0,
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
			'value' => 'doc,gif,jpg,mpg,pdf,png,txt,zip',
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
			'variable' => 'attachmentThumbHeight',
			'value' => 150,
		],
		[
			'variable' => 'attachmentThumbWidth',
			'value' => 150,
		],
		[
			'variable' => 'attachmentThumbnails',
			'value' => 1,
		],
		[
			'variable' => 'attachmentUploadDir',
			'value' => '{$boarddir}/attachments',
		],
		[
			'variable' => 'attachment_image_paranoid',
			'value' => 0,
		],
		[
			'variable' => 'attachment_image_reencode',
			'value' => 1,
		],
		[
			'variable' => 'attachment_thumb_png',
			'value' => 1,
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
			'variable' => 'autoOptMaxOnline',
			'value' => 0,
		],
		[
			'variable' => 'avatar_action_too_large',
			'value' => 'option_html_resize',
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
			'variable' => 'avatar_paranoid',
			'value' => 0,
		],
		[
			'variable' => 'avatar_reencode',
			'value' => 1,
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
			'variable' => 'birthday_email',
			'value' => 'happy_birthday',
		],
		[
			'variable' => 'cache_enable',
			'value' => 1,
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
			'variable' => 'cal_maxspan',
			'value' => 7,
		],
		[
			'variable' => 'cal_maxyear',
			'value' => 2030,
		],
		[
			'variable' => 'cal_minyear',
			'value' => 2008,
		],
		[
			'variable' => 'cal_showbdays',
			'value' => 1,
		],
		[
			'variable' => 'cal_showevents',
			'value' => 1,
		],
		[
			'variable' => 'cal_showholidays',
			'value' => 1,
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
			'variable' => 'default_personal_text',
			'value' => '',
		],
		[
			'variable' => 'dont_repeat_buddylists',
			'value' => 1,
		],
		[
			'variable' => 'dont_repeat_smileys_20',
			'value' => 1,
		],
		[
			'variable' => 'dont_repeat_theme_core',
			'value' => 1,
		],
		[
			'variable' => 'edit_disable_time',
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
			'variable' => 'enableStickyTopics',
			'value' => 1,
		],
		[
			'variable' => 'enableVBStyleLogin',
			'value' => 1,
		],
		[
			'variable' => 'enable_buddylist',
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
			'variable' => 'guest_hideContacts',
			'value' => 1,
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
			'value' => '{$default_karmaApplaudLabel}',
		],
		[
			'variable' => 'karmaLabel',
			'value' => '{$default_karmaLabel}',
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
			'value' => '{$default_karmaSmiteLabel}',
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
			'value' => '1,2,3',
		],
		[
			'variable' => 'lastActive',
			'value' => 15,
		],
		[
			'variable' => 'last_mod_report_action',
			'value' => 0,
		],
		[
			'variable' => 'mail_next_send',
			'value' => 0,
		],
		[
			'variable' => 'mail_recent',
			'value' => '0000000000|0',
		],
		[
			'variable' => 'mail_type',
			'value' => 0,
		],
		[
			'variable' => 'maxMsgID',
			'value' => 1,
		],
		[
			'variable' => 'max_image_height',
			'value' => 0,
		],
		[
			'variable' => 'max_image_width',
			'value' => 0,
		],
		[
			'variable' => 'max_messageLength',
			'value' => 20000,
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
			'value' => '{$default_news}',
		],
		[
			'variable' => 'next_task_time',
			'value' => 1,
		],
		[
			'variable' => 'number_format',
			'value' => '1234.00',
		],
		[
			'variable' => 'oldTopicDays',
			'value' => 120,
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
			'variable' => 'permission_enable_deny',
			'value' => 0,
		],
		[
			'variable' => 'permission_enable_postgroups',
			'value' => 0,
		],
		[
			'variable' => 'pm_spam_settings',
			'value' => '10,5,20',
		],
		[
			'variable' => 'pollMode',
			'value' => 1,
		],
		[
			'variable' => 'pruningOptions',
			'value' => '30,180,180,180,30,0',
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
			'variable' => 'reg_verification',
			'value' => 1,
		],
		[
			'variable' => 'registration_method',
			'value' => 0,
		],
		[
			'variable' => 'requireAgreement',
			'value' => 1,
		],
		[
			'variable' => 'requirePolicyAgreement',
			'value' => 0,
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
			'value' => '{$default_reserved_names}',
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
			'variable' => 'search_floodcontrol_time',
			'value' => 5,
		],
		[
			'variable' => 'search_max_results',
			'value' => 1200,
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
			'variable' => 'settings_updated',
			'value' => 0,
		],
		[
			'variable' => 'signature_settings',
			'value' => '1,300,0,0,0,0,0,0:',
		],
		[
			'variable' => 'simpleSearch',
			'value' => 0,
		],
		[
			'variable' => 'smfVersion',
			'value' => '{$smf_version}',
		],
		[
			'variable' => 'smiley_sets_default',
			'value' => 'default',
		],
		[
			'variable' => 'smiley_sets_known',
			'value' => 'default,aaron,akyhne',
		],
		[
			'variable' => 'smiley_sets_names',
			'value' => '{$default_smileyset_name}' . "\n" . '{$default_aaron_smileyset_name}' . "\n" . '{$default_akyhne_smileyset_name}',
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
			'variable' => 'topicSummaryPosts',
			'value' => 15,
		],
		[
			'variable' => 'totalMembers',
			'value' => 0,
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
			'variable' => 'visual_verification_type',
			'value' => 3,
		],
		[
			'variable' => 'warning_moderate',
			'value' => 35,
		],
		[
			'variable' => 'warning_mute',
			'value' => 60,
		],
		[
			'variable' => 'warning_settings',
			'value' => '1,20,0',
		],
		[
			'variable' => 'warning_watch',
			'value' => 10,
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
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'value' => new Column(
				name: 'value',
				type: 'text',
				not_null: true,
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
