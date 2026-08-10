<?php

// Version: 3.0 Alpha 4; ManageSettings

// argument(s): theme_id, session_id, session_var, Config::$scripturl
$txt['modSettings_desc'] = 'This page allows you to change the settings of features and basic options in your forum. Please see the <a href="{scripturl}?action=admin;area=theme;sa=list;th={theme_id};{session_var}={session_id}">theme settings</a> for more options. Click the help icons for more information about a setting.';
$txt['modification_settings_desc'] = 'This page contains settings added by any modifications to your forum';

$txt['modification_no_misc_settings'] = 'There are no modifications installed that have added any settings to this area yet.';

$txt['pollMode'] = 'Poll mode';
$txt['disable_polls'] = 'Disable polls';
$txt['enable_polls'] = 'Enable polls';
$txt['polls_as_topics'] = 'Show existing polls as topics';
$txt['allow_guestAccess'] = 'Allow guests to browse the forum';
$txt['userLanguage'] = 'Enable user-selectable language support';
$txt['allow_hideOnline'] = 'Allow non-administrators to hide their online status';
$txt['titlesEnable'] = 'Enable custom titles';
$txt['enable_buddylist'] = 'Enable buddy/ignore lists';
$txt['default_personal_text'] = 'Default personal text';
$txt['default_personal_text_note'] = 'Personal text to assign to newly registered members.';
$txt['time_format'] = 'Default time format';
$txt['setting_time_offset'] = 'Overall time offset';
$txt['setting_default_timezone'] = 'Forum default timezone';
$txt['setting_timezone_priority_countries'] = 'Show time zones from these countries first';
$txt['setting_timezone_priority_countries_note'] = 'A comma separated list of two character ISO country codes.';
$txt['failed_login_threshold'] = 'Failed login threshold';
$txt['loginHistoryDays'] = 'Days to keep login history';
$txt['lastActive'] = 'User online time threshold';
$txt['trackStats'] = 'Track statistics';
$txt['hitStats'] = 'Track daily page views (statistics tracking must be enabled)';
$txt['enableCompressedOutput'] = 'Enable compressed output';
$txt['databaseSession_enable'] = 'Use database driven sessions';
$txt['databaseSession_loose'] = 'Allow browsers to go back to cached pages';
$txt['databaseSession_lifetime'] = 'Seconds before an unused session timeout';
$txt['error_log_desc'] = 'The error log, if enabled, will log every error encountered by users using your forum. This can be an invaluable aid to identifying forum problems.';
$txt['enableErrorLogging'] = 'Enable error logging';
$txt['enableErrorQueryLogging'] = 'Include database query in the error log';
$txt['markread_title'] = 'Read Logs';
$txt['mark_read_desc'] = 'The following options govern how long before automatically marking boards and topics as read and how long before purging this information.';
$txt['mark_read_beyond'] = 'Automatically mark boards as read for users who have been inactive after this many days';
$txt['mark_read_delete_beyond'] = 'Automatically purge information about boards and topics visited after this many days';
$txt['mark_read_max_users'] = 'Maximum users to process at a time';
$txt['pruningOptions'] = 'Enable pruning of log entries';
$txt['pruneErrorLog'] = 'Remove error log entries older than';
$txt['pruneModLog'] = 'Remove moderation log entries older than';
$txt['pruneBanLog'] = 'Remove ban hit log entries older than';
$txt['pruneReportLog'] = 'Remove report to moderator log entries older than';
$txt['pruneScheduledTaskLog'] = 'Remove scheduled task log entries older than';
$txt['pruneSpiderHitLog'] = 'Remove search engine hit logs older than';
$txt['localCookies'] = 'Enable local storage of cookies';
$txt['globalCookies'] = 'Use subdomain independent cookies';
$txt['globalCookiesDomain'] = 'Main domain used for subdomain independent cookies';
$txt['invalid_cookie_domain'] = 'The domain introduced seems to be invalid, please check it and save again.';
$txt['secureCookies'] = 'Force cookies to be secure';
$txt['httponlyCookies'] = 'Force cookies to be made accessible only through the HTTP protocol';
$txt['samesiteCookies'] = 'Force cookies to be sent only to first parties';
$txt['samesiteNone'] = 'None';
$txt['samesiteLax'] = 'Lax';
$txt['samesiteStrict'] = 'Strict';
$txt['samesiteSecureRequired'] = 'If SameSite Cookies is set to "None", cookies must be secure.';
$txt['securityDisable'] = 'Disable administration security';
$txt['securityDisable_moderate'] = 'Disable moderation security';
$txt['send_validation_onChange'] = 'Require reactivation after email change';
$txt['approveAccountDeletion'] = 'Require admin approval when member deletes account';
$txt['autoFixDatabase'] = 'Automatically fix broken tables';
$txt['disallow_sendBody'] = 'Do not allow post text in notifications';
$txt['enable_ajax_alerts'] = 'Allow AJAX desktop notifications for alerts';
$txt['alerts_auto_purge'] = 'Automatically delete read alerts';
$txt['alerts_auto_purge_7'] = 'After 1 week';
$txt['alerts_auto_purge_30'] = 'After 1 month';
$txt['alerts_auto_purge_90'] = 'After 3 months';
$txt['alerts_auto_purge_0'] = 'Never';
$txt['alerts_per_page'] = 'Alerts Per Page';
$txt['jquery_source'] = 'Source for the jQuery Library';
$txt['jquery_custom'] = 'Custom URL to the jQuery Library';
$txt['fontawesome_source'] = 'Source for the FontAwesome Library';
$txt['fontawesome_custom'] = 'Custom URL to the FontAwesome Library';
$txt['cdn_custom_label'] = 'Custom';
$txt['local_cdn'] = 'Local';
$txt['google_cdn'] = 'Google CDN';
$txt['jquery_cdn'] = 'jQuery CDN';
$txt['microsoft_cdn'] = 'Microsoft CDN';
$txt['cloudflare_cdn'] = 'Cloudflare CDN';
$txt['fontawesome_cdn'] = 'FontAwesome CDN';
$txt['minimize_files'] = 'Minimize CSS and JavaScript files';
$txt['queryless_urls'] = 'Use friendly URLs';
$txt['queryless_urls_note'] = 'Supported on Apache, Lighttpd, and LiteSpeed only';
$txt['hide_index_php'] = 'Hide index.php in URLs';
$txt['hide_index_php_manual'] = 'You must manually configure your server software to support this option before enabling it.';
$txt['queryless_hidden_index_htaccess'] = 'In order to enable both the option to use friendly URLs and the option to hide index.php in forum URLs at the same time, SMF needs to make changes to your .htaccess file, but the file is not writable. Please check its file permissions and try again.';
$txt['use_ascii_slugs'] = 'Transliterate non-ASCII characters in friendly URLs.';
$txt['enableReportPM'] = 'Enable reporting of personal messages';
$txt['max_pm_recipients'] = 'Maximum number of recipients allowed in a personal message';
$txt['max_pm_recipients_note'] = '(0 for no limit, admins are exempt)';
$txt['pm_posts_verification'] = 'Post count under which users must pass verification when sending personal messages';
$txt['pm_posts_verification_note'] = '(0 for no limit, admins are exempt)';
$txt['pm_posts_per_hour'] = 'Number of personal messages a user may send in an hour';
$txt['pm_posts_per_hour_note'] = '(0 for no limit, moderators are exempt)';
$txt['compactTopicPagesEnable'] = 'Limit number of displayed page links';
$txt['contiguous_page_display'] = 'Contiguous pages to display';
$txt['to_display'] = 'to display';
$txt['todayMod'] = 'Enable shorthand date display';
$txt['today_disabled'] = 'Disabled';
$txt['today_only'] = 'Only Today';
$txt['yesterday_today'] = 'Yesterday, Today, &amp; Tomorrow';
$txt['onlineEnable'] = 'Show online/offline status in posts and PMs';
$txt['defaultMaxMembers'] = 'Members per page in member list';
$txt['timeLoadPageEnable'] = 'Display time taken to create every page';
$txt['disableHostnameLookup'] = 'Disable hostname lookups';
$txt['who_enabled'] = 'Enable who’s online list';
$txt['no_guest_logging'] = 'Do not include guests or bots in Users Online stats';
$txt['no_guest_views'] = 'Disable counting views for guests and bots';
$txt['settings_error'] = 'Warning: Updating of Settings.php failed, the settings cannot be saved.';
$txt['image_proxy_enabled'] = 'Enable Image Proxy';
$txt['image_proxy_secret'] = 'Image Proxy Secret';
$txt['image_proxy_maxsize'] = 'Maximum file size of images to cache (in KB)';
$txt['force_ssl'] = 'Forum SSL mode';
$txt['force_ssl_off'] = 'Disable SSL';
$txt['force_ssl_complete'] = 'Force SSL throughout the forum';
$txt['search_language'] = 'Fulltext Search Language';

// Like settings.
$txt['enable_likes'] = 'Enable Likes';

// Mention settings.
$txt['enable_mentions'] = 'Enable Mentions';

$txt['caching_information'] = 'SMF supports caching through the use of accelerators. The currently supported accelerators include:
<ul class="normallist">
	<li>APCu</li>
	<li>Memcached</li>
	<li>SQLite3</li>
	<li>PostgreSQL</li>
	<li>Zend Platform/Performance Suite (Not Zend Optimizer)</li>
</ul>
Caching will work best if you have PHP compiled with one of the above optimizers, or have memcached available. If you do not have any optimizer installed SMF will do file based caching.';
$txt['detected_no_caching'] = 'SMF has not been able to detect a compatible accelerator on your server. File based caching can be used instead.';
$txt['detected_accelerators'] = 'SMF has detected the following accelerators: {list}';

$txt['cache_enable'] = 'Caching Level';
$txt['cache_off'] = 'No caching';
$txt['cache_level1'] = 'Level 1 Caching (Recommended)';
$txt['cache_level2'] = 'Level 2 Caching';
$txt['cache_level3'] = 'Level 3 Caching (Not Recommended)';
$txt['cache_accelerator'] = 'Caching Accelerator';
$txt['filebased_cache'] = 'SMF file based caching';
$txt['sqlite_cache'] = 'SQLite3 database based caching';
$txt['postgres_cache'] = 'PostgreSQL caching';
$txt['cachedir_sqlite'] = 'SQLite3 database cache directory';
$txt['apcu_cache'] = 'APCu';
$txt['memcacheimplementation_cache'] = 'Memcache';
$txt['memcachedimplementation_cache'] = 'Memcached';
$txt['zend_cache'] = 'Zend Platform/Performance Suite';
$txt['cache_filebased_settings'] = 'SMF file based caching settings';
$txt['cache_sqlite_settings'] = 'SQLite3 database caching settings';
$txt['cache_memcached_settings'] = 'Memcache/Memcached settings';
$txt['cache_memcached_servers'] = 'Memcache/Memcached servers';
$txt['cache_memcached_servers_subtext'] = 'Example: 127.0.0.1:11211,127.0.0.2';
$txt['cache_sqlite_wal'] = 'Enable SQLite3 WAL';
$txt['cache_sqlite_wal_subtext'] = 'Read <a href="https://www.sqlite.org/wal.html">Write-Ahead Logging</a> documentation prior to using. If you enable then disable this setting, the database will become readonly.';

$txt['loadavg_warning'] = 'Please note: the settings below are to be edited with care. Setting any of them too low may render your forum <strong>unusable</strong>!';
$txt['loadavg_enable'] = 'Enable load balancing by load averages';
$txt['loadavg_auto_opt'] = 'Threshold to disabling automatic database optimization';
$txt['loadavg_search'] = 'Threshold to disabling search';
$txt['loadavg_allunread'] = 'Threshold to disabling all unread topics';
$txt['loadavg_unreadreplies'] = 'Threshold to disabling unread replies';
$txt['loadavg_show_posts'] = 'Threshold to disabling showing user posts';
$txt['loadavg_userstats'] = 'Threshold to disabling showing user statistics';
$txt['loadavg_bbc'] = 'Threshold to disabling BBC formatting when showing posts';
$txt['loadavg_forum'] = 'Threshold to disabling the forum <strong>completely</strong>';
$txt['loadavg_disabled_windows'] = 'Load balancing support is not available on Windows.';
$txt['loadavg_disabled_conf'] = 'Load balancing support is disabled by your host configuration.';
$txt['loadavg_current_load'] = 'The current load average is <strong>{load_average, number, :: percent}</strong>';
$txt['loadavg_processors']  = 'CPU(s) detected: {cpu_count, number}';

$txt['setting_password_strength'] = 'Required strength for user passwords';
$txt['setting_password_strength_low'] = 'Low';
$txt['setting_password_strength_medium'] = 'Medium';
$txt['setting_password_strength_high'] = 'High';
$txt['setting_enable_password_conversion'] = 'Allow password hash conversion';

$txt['antispam_Settings'] = 'Anti-Spam Verification';
$txt['antispam_Settings_desc'] = 'This section allows you to setup verification checks to ensure the user is a human (and not a bot), and tweak how and where these apply.';
$txt['setting_reg_verification'] = 'Require verification on registration page';
$txt['posts_require_captcha'] = 'Post count under which users must pass verification to make a post';
$txt['posts_require_captcha_desc'] = '(0 for no limit, moderators are exempt)';
$txt['search_enable_captcha'] = 'Require verification on all guest searches';
$txt['setting_guests_require_captcha'] = 'Guests must pass verification when making a post';
$txt['setting_guests_require_captcha_desc'] = '(Automatically set if you specify a minimum post count below)';
$txt['question_not_defined'] = 'You need to add a question and answer for your forum’s default language ({name}) otherwise users will not be able to fill in a CAPTCHA, meaning no registration.';

$txt['configure_verification_means'] = 'Configure Verification methods';
$txt['setting_qa_verification_number'] = 'Number of verification questions user must answer';
$txt['setting_qa_verification_number_desc'] = '(0 to disable; questions are set below)';
$txt['configure_verification_means_desc'] = '<span class="smalltext">Below you can set which anti-spam features you wish to have enabled whenever a user needs to verify they are a human. Note that the user will have to pass <em>all</em> verification so if you enable both a verification image and a question/answer test they need to complete both to proceed.</span>';
$txt['setting_visual_verification_type'] = 'Visual verification image to display';
$txt['setting_visual_verification_type_desc'] = 'The more complex the image the harder it is for bots to bypass';
$txt['setting_image_verification_off'] = 'None';
$txt['setting_image_verification_vsimple'] = 'Very Simple - Plain text on image';
$txt['setting_image_verification_simple'] = 'Simple - Overlapping colored letters, no noise';
$txt['setting_image_verification_medium'] = 'Medium - Overlapping colored letters, with noise/lines';
$txt['setting_image_verification_high'] = 'High - Angled letters, considerable noise/lines';
$txt['setting_image_verification_extreme'] = 'Extreme - Angled letters, noise, lines and blocks';
$txt['setting_image_verification_sample'] = 'Sample';

// reCAPTCHA
$txt['recaptcha_configure'] = 'reCAPTCHA Verification System';
$txt['recaptcha_configure_desc'] = 'Configure the reCAPTCHA Verification System. Don’t have a key for reCAPTCHA? <a href="https://www.google.com/recaptcha/admin">Get your reCAPTCHA key here</a>.';
$txt['recaptcha_enabled'] = 'Use reCAPTCHA Verification System';
$txt['recaptcha_enable_desc'] = 'This augments the built-in visual verification';
$txt['recaptcha_theme'] = 'reCAPTCHA Theme';
$txt['recaptcha_theme_light'] = 'Light';
$txt['recaptcha_theme_dark'] = 'Dark';
$txt['recaptcha_site_key'] = 'Site Key';
$txt['recaptcha_site_key_desc'] = 'This will be set in the HTML code your site serves to users.';
$txt['recaptcha_secret_key'] = 'Secret Key';
$txt['recaptcha_secret_key_desc'] = 'This is for communication between your site and Google. Be sure to keep it a secret.';
$txt['recaptcha_no_key_question'] = 'Don’t have a key for reCAPTCHA?';
$txt['recaptcha_get_key'] = 'Get your reCAPTCHA key here.';
$txt['languages_recaptcha'] = 'ReCAPTCHA language';

$txt['setting_image_verification_nogd'] = '<strong>Note:</strong> as this server does not have the GD library installed the different complexity settings will have no effect.';
$txt['setup_verification_questions'] = 'Verification Questions';
$txt['setup_verification_questions_desc'] = '<span class="smalltext">If you want users to answer verification questions in order to stop spam bots, you should setup a number of questions in the table below. You should choose questions which relate to the subject of your forum. Genuine users will be able to answer these questions, while spam bots will not. Answers are not case sensitive. You may use BBC in the questions for formatting. To remove a question simply delete the contents of that line.</span>';
$txt['setup_verification_question'] = 'Question';
$txt['setup_verification_answer'] = 'Answer';
$txt['setup_verification_add_more'] = 'Add another question';
$txt['setup_verification_add_answer'] = 'Add another answer';

$txt['moderation_settings'] = 'Moderation Settings';
$txt['setting_warning_enable'] = 'Enable User Warning system';
$txt['setting_warning_watch'] = 'Warning level for user watch';
$txt['setting_warning_watch_note'] = 'The user warning level after which a user watch is put in place.';
$txt['setting_warning_moderate'] = 'Warning level for post moderation';
$txt['setting_warning_moderate_note'] = 'The user warning level after which a user has all posts moderated.';
$txt['setting_warning_mute'] = 'Warning level for user muting';
$txt['setting_warning_mute_note'] = 'The user warning level after which a user cannot post any further.';
$txt['setting_user_limit'] = 'Maximum user warning points per day';
$txt['setting_user_limit_note'] = 'This value is the maximum amount of warning points a single moderator can assign to a user in a 24 hour period - 0 for no limit.';
$txt['setting_warning_decrement'] = 'Warning points that are decreased every 24 hours';
$txt['setting_warning_decrement_note'] = 'Only applies to users not warned within last 24 hours.';
$txt['setting_view_warning_any'] = 'Users who can see any warning status';
$txt['setting_view_warning_own'] = 'Users who can see their own warning status';

$txt['signature_settings'] = 'Signature Settings';
$txt['signature_settings_desc'] = 'Use the settings on this page to decide how member signatures should be treated in SMF.';
// argument(s): session_id, session_var, scripturl
$txt['signature_settings_warning'] = 'Note that settings are not applied to existing signatures by default. <a href="{scripturl}?action=admin;area=featuresettings;sa=sig;apply;{session_var}={sessions_id}">Apply changes now</a>';
$txt['signature_settings_applied'] = 'The updated rules have been applied to the existing signatures.';
$txt['signature_enable'] = 'Enable signatures';
$txt['signature_max_length'] = 'Maximum allowed characters';
$txt['signature_max_lines'] = 'Maximum amount of lines';
$txt['signature_max_images'] = 'Maximum image count';
$txt['signature_max_images_note'] = '(0 for no max - excludes smileys)';
$txt['signature_allow_smileys'] = 'Allow smileys in signatures';
$txt['signature_max_smileys'] = 'Maximum smiley count';
$txt['signature_max_image_width'] = 'Maximum width of signature images (pixels)';
$txt['signature_max_image_height'] = 'Maximum height of signature images (pixels)';
$txt['signature_max_font_size'] = 'Maximum font size allowed in signatures (pixels)';
$txt['signature_bbc'] = 'Enabled BBC tags';

$txt['custom_profile_title'] = 'Custom Profile Fields';
$txt['custom_profile_desc'] = 'From this page you can create your own custom profile fields that fit in with your own forum’s requirements';
$txt['custom_profile_active'] = 'Active';
$txt['custom_profile_fieldname'] = 'Field Name';
$txt['custom_profile_fieldtype'] = 'Field Type';
$txt['custom_profile_fieldorder'] = 'Field Order';
$txt['custom_profile_make_new'] = 'New Field';
$txt['custom_profile_none'] = 'You have not created any custom profile fields yet!';
$txt['custom_profile_icon'] = 'Icon';

$txt['custom_profile_type_text'] = 'Text';
$txt['custom_profile_type_textarea'] = 'Large Text';
$txt['custom_profile_type_select'] = 'Select Box';
$txt['custom_profile_type_radio'] = 'Radio Button';
$txt['custom_profile_type_check'] = 'Checkbox';

$txt['custom_add_title'] = 'Add Profile field';
$txt['custom_edit_title'] = 'Edit Profile field';
$txt['custom_edit_general'] = 'Display Settings';
$txt['custom_edit_input'] = 'Input Settings';
$txt['custom_edit_advanced'] = 'Advanced Settings';
$txt['custom_edit_name'] = 'Name';
$txt['custom_edit_name_desc'] = 'You can use translatable tokens in this field.';
$txt['custom_edit_desc'] = 'Description';
$txt['custom_edit_profile'] = 'Profile Section';
$txt['custom_edit_profile_desc'] = 'Section of profile the users will be able to edit this in.';
$txt['custom_edit_profile_none'] = 'None';
$txt['custom_edit_registration'] = 'Show on Registration';
$txt['custom_edit_registration_disable'] = 'No';
$txt['custom_edit_registration_allow'] = 'Yes';
$txt['custom_edit_registration_require'] = 'Yes, and require entry';
$txt['custom_edit_display'] = 'Show on Topic View';
$txt['custom_edit_mlist'] = 'Show on memberlist';
$txt['custom_edit_picktype'] = 'Field Type';

$txt['custom_edit_max_length'] = 'Maximum Length';
$txt['custom_edit_max_length_desc'] = '(0 for no limit)';
$txt['custom_edit_dimension'] = 'Dimensions';
$txt['custom_edit_dimension_row'] = 'Rows';
$txt['custom_edit_dimension_col'] = 'Columns';
$txt['custom_edit_bbc'] = 'Allow BBC';
$txt['custom_edit_options'] = 'Options';
$txt['custom_edit_options_desc'] = 'Leave option box blank to remove. Radio button selects default option.';
$txt['custom_edit_options_more'] = 'More';
$txt['custom_edit_default'] = 'Default State';
$txt['custom_edit_active'] = 'Active';
$txt['custom_edit_active_desc'] = 'If not selected this field will not be shown to anyone.';
$txt['custom_edit_privacy'] = 'Privacy';
$txt['custom_edit_privacy_desc'] = 'Who can see and edit this field.';
$txt['custom_edit_privacy_all'] = 'Users can see this field; owner can edit it';
$txt['custom_edit_privacy_see'] = 'Users can see this field; only admins can edit it';
$txt['custom_edit_privacy_owner'] = 'Users cannot see this field; owner and admins can edit it.';
$txt['custom_edit_privacy_none'] = 'This field is only visible to admins';
$txt['custom_edit_can_search'] = 'Searchable';
$txt['custom_edit_can_search_desc'] = 'Can this field be searched from the members list.';
$txt['custom_edit_mask'] = 'Input Mask';
$txt['custom_edit_mask_desc'] = 'For text fields an input mask can be selected to validate the data.';
$txt['custom_edit_mask_email'] = 'Valid Email';
$txt['custom_edit_mask_number'] = 'Numeric';
$txt['custom_edit_mask_nohtml'] = 'No HTML';
$txt['custom_edit_mask_regex'] = 'Regex (Advanced)';
$txt['custom_edit_enclose'] = 'Show Enclosed Within Text (Optional)';
$txt['custom_edit_enclose_desc'] = 'We <strong>strongly</strong> recommend to use an input mask to validate the input supplied by the user.';

$txt['custom_edit_order_move_up'] = 'Move Up';
$txt['custom_edit_order_move_down'] = 'Move Down';
$txt['custom_edit_placement'] = 'Choose Placement';
$txt['custom_profile_placement'] = 'Placement';
$txt['custom_profile_placement_standard'] = 'Standard (with title)';
$txt['custom_profile_placement_icons'] = 'With Icons';
$txt['custom_profile_placement_above_signature'] = 'Above Signature';
$txt['custom_profile_placement_below_signature'] = 'Below Signature';
$txt['custom_profile_placement_below_avatar'] = 'Below Avatar';
$txt['custom_profile_placement_above_member'] = 'Above Username';
$txt['custom_profile_placement_bottom_poster'] = 'Bottom poster info';
$txt['custom_profile_placement_before_member'] = 'Before Username';
$txt['custom_profile_placement_after_member'] = 'After Username';

// Use numeric entities in the string below!
$txt['custom_edit_delete_sure'] = 'Are you sure you wish to delete this field - all related user data will be lost!';

$txt['standard_profile_title'] = 'Standard Profile Fields';
$txt['standard_profile_field'] = 'Field';
$txt['standard_profile_field_timezone'] = 'Timezone';

$txt['languages_lang_name'] = 'Language Name';
$txt['languages_native_name'] = 'Native Name of Language';
$txt['languages_locale'] = 'Locale';
$txt['languages_default'] = 'Default';
$txt['languages_character_set'] = 'Character Set';
$txt['languages_users'] = 'Users';
$txt['language_settings_writable'] = 'Warning: Settings.php is not writable so the default language setting cannot be saved.';
$txt['edit_languages'] = 'Edit Languages';
$txt['lang_file_not_writable'] = '<strong>Warning:</strong> The primary language file ({file}) is not writable. You must make this writable before you can make any changes.';
$txt['lang_entries_not_writable'] = '<strong>Warning:</strong> The language file you wish to edit ({file}) is not writable. You must make this writable before you can make any changes.';
$txt['languages_ltr'] = 'Right to Left';

$txt['add_language'] = 'Add Language';
$txt['add_language_smf'] = 'Download from Simple Machines';
$txt['add_language_smf_browse'] = 'Type name of language to search for or leave blank to search for all.';
$txt['add_language_smf_install'] = 'Install';
$txt['add_language_found_title'] = 'Found Languages';
$txt['add_language_smf_found'] = 'The following languages were found. Click the install link next to the language you wish to install. You will then be taken to the package manager to install.';
$txt['add_language_error_no_response'] = 'The Simple Machines site is not responding. Please try again later.';
$txt['add_language_error_no_files'] = 'No files could be found.';
$txt['add_language_smf_desc'] = 'Description';
$txt['add_language_smf_utf8'] = 'UTF-8';
$txt['add_language_smf_version'] = 'Version';

$txt['edit_language_entries_primary'] = 'Below are the primary language settings for this language pack.';
$txt['edit_language_entries'] = 'Edit Language Entries';
$txt['edit_language_entries_desc'] = 'You can customize the individual text entries for this language. Select a file to load its entries, and then edit them below.<br><br>When you edit (or remove) an entry, a commented out version of the original is preserved in the file. If you ever need to restore your edited strings to their original state, browse to the file you are looking for, and then edit it directly.';
$txt['edit_language_entries_file'] = 'Select entries to edit';
$txt['edit_language_entries_add'] = 'Add another item';
$txt['languages_dictionary'] = 'Dictionary';
$txt['languages_rtl'] = 'Enable &quot;Right to Left&quot; Mode';

$txt['lang_file_desc_Admin'] = 'Admin';
$txt['lang_file_desc_Agreement'] = 'Agreement';
$txt['lang_file_desc_Alerts'] = 'Alerts';
$txt['lang_file_desc_Drafts'] = 'Drafts';
$txt['lang_file_desc_Editor'] = 'Editor';
$txt['lang_file_desc_EmailTemplates'] = 'Email Templates';
$txt['lang_file_desc_Errors'] = 'Errors';
$txt['lang_file_desc_General'] = 'General';
$txt['lang_file_desc_Help'] = 'Help';
$txt['lang_file_desc_Install'] = 'Install';
$txt['lang_file_desc_Login'] = 'Login';
$txt['lang_file_desc_ManageBoards'] = 'Manage Boards';
$txt['lang_file_desc_ManageCalendar'] = 'Manage Calendar';
$txt['lang_file_desc_ManageMail'] = 'Manage Mail';
$txt['lang_file_desc_ManageMaintenance'] = 'Manage Maintenance';
$txt['lang_file_desc_ManageMembers'] = 'Manage Members';
$txt['lang_file_desc_ManagePaid'] = 'Manage Paid';
$txt['lang_file_desc_ManagePermissions'] = 'Manage Permissions';
$txt['lang_file_desc_ManageScheduledTasks'] = 'Manage Scheduled Tasks';
$txt['lang_file_desc_ManageSettings'] = 'Manage Settings';
$txt['lang_file_desc_ManageSmileys'] = 'Manage Smileys';
$txt['lang_file_desc_Manual'] = 'Manual';
$txt['lang_file_desc_ModerationCenter'] = 'Moderation Center';
$txt['lang_file_desc_Modifications'] = 'Modifications';
$txt['lang_file_desc_Modlog'] = 'Modlog';
$txt['lang_file_desc_Packages'] = 'Packages';
$txt['lang_file_desc_PersonalMessage'] = 'Personal Message';
$txt['lang_file_desc_Post'] = 'Post';
$txt['lang_file_desc_Profile'] = 'Profile';
$txt['lang_file_desc_Reports'] = 'Reports';
$txt['lang_file_desc_Search'] = 'Search';
$txt['lang_file_desc_Stats'] = 'Stats';
$txt['lang_file_desc_Themes'] = 'Themes';
$txt['lang_file_desc_Timezones'] = 'Timezones';
$txt['lang_file_desc_Who'] = 'Who';
$txt['lang_file_desc_ThemeStrings'] = 'Theme Strings';

$txt['languages_download'] = 'Download Language Pack';
$txt['languages_download_note'] = 'This page lists all the files that are contained within the language pack and some useful information about each one. All files that have their associated check box marked will be copied.';
$txt['languages_download_info'] = '<strong>Note:</strong>
	<ul class="normallist">
		<li>Files which have the status &quot;Not Writable&quot; means SMF will not be able to copy this file to the directory at the present and you must make the destination writable either using an FTP client or by filling in your details at the bottom of the page.</li>
		<li>The Version information for a file displays the last SMF version which it was updated for. If it is indicated in green then this is a newer version than you have at current. If amber this indicates it is the same version number as at current, red indicates you have a newer version installed than contained in the pack.</li>
		<li>Where a file already exists on your forum the &quot;Already Exists&quot; column will have one of two values. &quot;Identical&quot; indicates that the file already exists in an identical form and need not be overwritten. &quot;Different&quot; means that the contents vary in some way and overwriting is probably the optimum solution.</li>
	</ul>';

$txt['languages_download_main_files'] = 'Primary Files';
$txt['languages_download_filename'] = 'File Name';
$txt['languages_download_dest'] = 'Destination: {destination}';
$txt['languages_download_writable'] = 'Writable';
$txt['languages_download_version'] = 'Version';
$txt['languages_download_older'] = 'You have a newer version of this file installed. Overwriting is not recommended.';
$txt['languages_download_exists'] = 'Already Exists';
$txt['languages_download_exists_same'] = 'Identical';
$txt['languages_download_exists_different'] = 'Different';
$txt['languages_download_overwrite'] = 'Overwrite';
$txt['languages_download_not_chmod'] = 'You cannot proceed with the installation until all files selected to be copied are writable.';
$txt['languages_download_illegal_paths'] = 'Package contains illegal paths - please contact Simple Machines';
$txt['languages_download_complete'] = 'Installation Complete';
$txt['languages_download_complete_desc'] = 'Language pack installed successfully. Please click <a href="{url}">here</a> to return to the languages page';
$txt['languages_delete_confirm'] = 'Are you sure you want to delete this language?';
$txt['languages_max_inputs_warning'] = '{0, plural,
	one {You can only save # edit at a time.}
	other {You can only save # edits at a time.}
} Please click the Save button now, and then continue editing once this page has reloaded.';
$txt['languages_txt'] = 'Standard text strings';
$txt['languages_helptxt'] = 'Help text';
$txt['languages_editortxt'] = 'User interface for the editor';
$txt['languages_tztxt'] = 'Time zone descriptions';
$txt['languages_txt_for_timezones'] = 'Place names';
$txt['languages_txt_for_email_templates'] = 'Email message templates';
$txt['languages_enter_key'] = 'Enter a variable name for this text string';
$txt['languages_invalid_key'] = 'Sorry, but this variable name is invalid: ';

$txt['allow_cors'] = 'Allow CORS (Cross Origin Resource Sharing)';
$txt['allow_cors_credentials'] = 'Allow sending credentials over CORS';
$txt['cors_domains'] = 'Additional CORS domains';
$txt['cors_headers'] = 'Additional CORS headers';

$txt['setting_frame_security'] = 'Frame Security Options';
$txt['setting_frame_security_SAMEORIGIN'] = 'Allow Same Origin';
$txt['setting_frame_security_DENY'] = 'Deny all frames';
$txt['setting_frame_security_DISABLE'] = 'Disabled';

$txt['setting_proxy_ip_header'] = 'Reverse Proxy IP Header';
$txt['setting_proxy_ip_header_disabled'] = 'Do not allow any Proxy IP Headers';
$txt['setting_proxy_ip_header_autodetect'] = 'Auto-detect Proxy IP header';
$txt['setting_proxy_ip_servers'] = 'Reverse Proxy Servers IPs';

$txt['select_boards_from_list'] = 'Select boards which apply';

$txt['topic_move_any'] = 'Allow moving of topics to read-only boards';

$txt['defaultMaxListItems'] = 'Maximum number of items per page in lists';

$txt['tfa_mode'] = 'Two-Factor Authentication';
$txt['tfa_mode_forced'] = 'Force on selected membergroups';
$txt['tfa_mode_forcedall'] = 'Force for ALL users';
$txt['tfa_mode_forced_help'] = 'Please enable 2FA in your account in order to be able to force 2FA on other users!';
$txt['tfa_mode_enabled'] = 'Enabled';
$txt['tfa_mode_disabled'] = 'Disabled';
$txt['tfa_mode_subtext'] = 'Allows users to have a second layer of security while logging in, users would need an app like Google Authenticator paired with their account';

$txt['export_settings_description'] = 'Members can export copies of their profile data, optionally including their posts and personal messages.<br>To avoid overtaxing server resources, SMF slowly compiles the exported data to a downloadable file stored in a secured directory.';
$txt['export_dir'] = 'Directory for profile data export files';
$txt['export_expiry'] = 'Automatically delete profile data export files after';
$txt['export_min_diskspace_pct'] = 'Pause exports if free space on disk is less than';
$txt['export_rate'] = 'Rate at which to process posts & personal messages for export';
$txt['export_rate_desc'] = 'Higher values will compile exports more quickly, but could affect forum performance.';

// External authentication providers.
$txt['authentication_providers'] = 'Sign in providers';
$txt['authentication_providers_desc'] = 'Lets members sign in with an external account instead of a password. Each provider has to be registered with them first, which is where the client ID and secret come from.';
$txt['authentication_no_providers'] = 'No providers have been set up yet.';
$txt['authentication_add'] = 'Add a provider';
$txt['authentication_add_generic'] = 'Any OpenID Connect provider';
$txt['authentication_provider'] = 'Provider';
$txt['authentication_title'] = 'Name';
$txt['authentication_title_desc'] = 'What the button on the login page says.';
$txt['authentication_issuer'] = 'Issuer URL';
$txt['authentication_issuer_desc'] = 'The provider\'s base URL. Everything else is read from its discovery document.';
$txt['authentication_client_id'] = 'Client ID';
$txt['authentication_client_secret'] = 'Client secret';
$txt['authentication_client_secret_desc'] = 'Leave blank to keep the one already saved.';
$txt['authentication_scopes'] = 'Scopes';
$txt['authentication_scopes_desc'] = 'Space separated. Must include openid.';
$txt['authentication_redirect_uri'] = 'Redirect URI';
$txt['authentication_redirect_uri_desc'] = 'Give this to the provider when registering the forum. It has to match exactly.';
$txt['authentication_redirect_uri_pending'] = 'Available once this provider has been saved.';
$txt['authentication_enabled'] = 'Enabled';
$txt['authentication_order'] = 'Sort order';
$txt['authentication_policy'] = 'What a sign in may do';
$txt['authentication_allow_registration'] = 'Allow new accounts';
$txt['authentication_allow_registration_desc'] = 'Someone signing in with no account here is sent to the sign up form, still subject to the agreement, approval and age rules.';
$txt['authentication_link_by_email'] = 'Claim accounts by matching email';
$txt['authentication_link_by_email_desc'] = 'Only turn this on if you trust the provider to verify email addresses. Anyone who can get an address issued there could otherwise take over the account that uses it here.';
$txt['authentication_allow_private_host'] = 'Allow a provider on a private address';
$txt['authentication_allow_private_host_desc'] = 'Needed for a provider running on your own network. Leave off for anything on the internet.';
$txt['authentication_test'] = 'Test';
$txt['authentication_test_ok'] = 'The provider answered and its endpoints look usable.';
$txt['authentication_test_failed'] = 'Could not read anything usable from this provider.';
$txt['authentication_delete_confirm'] = 'Remove this provider? Anyone who signs in with it will have to use their password instead.';
$txt['authentication_needs_title_and_issuer'] = 'A provider needs at least a name and an issuer URL.';

// Passkeys.
$txt['authentication_area'] = 'Authentication';
$txt['passkey_settings'] = 'Passkeys';
$txt['passkey_settings_desc'] = 'Passkeys let members sign in with the fingerprint reader, face scan, PIN or security key that unlocks their device, instead of a password.';
$txt['passkey_settings_rp_id'] = 'Passkeys registered here will be tied to <strong>{rp_id}</strong>, taken from the forum URL. Changing the forum\'s domain later will stop every passkey working, and there is no way to move them.';
$txt['passkey_settings_no_openssl'] = 'Passkeys need PHP\'s openssl extension, which is not installed. Nothing on this page will have any effect until it is.';
$txt['webauthn_enabled'] = 'Allow passkeys';
$txt['webauthn_enabled_subtext'] = 'Members can add passkeys from their profile, and sign in with one instead of a password.';
$txt['webauthn_allow_unverified'] = 'Accept devices that do not check who is using them';
$txt['webauthn_allow_unverified_subtext'] = 'By default a passkey only counts once the device has asked for a PIN, fingerprint or face scan. Turning this on accepts mere possession of the device instead, which means anyone holding it can sign in.';
