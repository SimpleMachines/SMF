<?php
// Version: 2.1 RC1; Help

global $helptxt, $scripturl;

$txt['close_window'] = 'Close window';

$helptxt['manage_boards'] = '
	<strong>Edit Boards</strong><br>
	On this page you can create/reorder/remove boards, and the categories
	above them. For example, if you had a wide-ranging
	site that offered information on &quot;Sports&quot; and &quot;Cars&quot; and &quot;Music&quot;, these
	would be the top-level Categories you\'d create. Under each of these
	categories you\'d likely want to create &quot;Boards&quot; for topics within each. It\'s a simple hierarchy, with this structure: <br>
	<ul class="normallist">
		<li>
			<strong>Sports</strong>
			&nbsp;- A &quot;category&quot;
			<ul class="normallist">
				<li>
					<strong>Baseball</strong>
					&nbsp;- A board under the category of &quot;Sports&quot;
					<ul class="normallist">
						<li>
							<strong>Stats</strong>
							&nbsp;- A sub-board under the board of &quot;Baseball&quot;
						</li>
					</ul>
				</li>
				<li><strong>Football</strong>
				&nbsp;- A board under the category of &quot;Sports&quot;</li>
			</ul>
		</li>
	</ul>
	Categories allow you to break down the boards into broad topics (&quot;Cars,
	Sports&quot;), and the &quot;Boards&quot; under them allow users to post actual topics.
	A user interested in Pintos	would post a message under &quot;Cars->Pinto&quot;.
	Administrative functions for this page are to create new boards
	under each category, to reorder them (put &quot;Pinto&quot; below &quot;Chevy&quot;), or
	to delete the board entirely.';

$helptxt['edit_news'] = '
	<ul class="normallist">
		<li>
			<strong>News</strong><br>
			This page allows you to set the text for news items displayed on the Board Index page.
			Add any item you want (e.g., &quot;Don\'t miss the conference this Tuesday&quot;). Each news item is displayed randomly and should be placed in a separate box.
		</li>
		<li>
			<strong>Newsletters</strong><br>
			This page allows you to send out newsletters to the members of the forum via personal message or email. First select the groups that you want to receive the newsletter, and those you don\'t want to receive the newsletter. If you wish, you can add additional members and email addresses that will receive the newsletter. Finally, input the message you want to send and select whether you want it to be sent to members as a personal message or as an email.
		</li>
		<li>
			<strong>Settings</strong><br>
				This page contains a few settings that relate to news and newsletters, including selecting which groups can edit forum news or send newsletters. There is also a setting to configure whether you want news feeds enabled on the forum, as well as a setting to configure the length (how many characters are displayed) for each new post that shows in the feed.
		</li>
	</ul>';

$helptxt['view_members'] = '
	<ul class="normallist">
		<li>
			<strong>View all Members</strong><br>
			View all members in the board. You are presented with a hyperlinked list of member names. You may click
			on any of the names to find details of the members (homepage, age, etc.), and as Administrator
			you are able to modify these parameters. You have complete control over members, including the
			ability to delete them from the forum.<br><br>
		</li>
		<li>
			<strong>Awaiting approval</strong><br>
			This page is only shown if you have enabled admin approval of all new registrations. Anyone who registers to join your
			forum will only become a full member once they have been approved by an admin. The page lists all those members who
			are still awaiting approval, along with their email and IP address. You can choose to either accept or reject (delete)
			any member on the list by checking the box next to the member and choosing the action from the drop-down box at the bottom
			of the page. When rejecting a member you can choose to delete the member either with or without notifying them of your decision.<br><br>
		</li>
		<li>
			<strong>Awaiting activation</strong><br>
			This page will only be visible if you have email activation of new member accounts enabled on the forum. This page will list all
			members who have still not activated their new accounts. From this page you can choose to either accept, reject or remind
			members with outstanding registrations. As above you can also choose to email the member to inform them of the
			action you have taken.<br><br>
		</li>
	</ul>';

$helptxt['ban_members'] = '<strong>Ban Members</strong><br>
	SMF provides the ability to &quot;ban&quot; users, which can be a useful course of action against users that have
	violated the trust of the forum	by spamming or trolling, etc. This allows you to exclude users that are detrimental
	to your forum. As an admin, when you view posts made on your forum, you can see the IP address that the user had at the time of posting.
	In the ban list, you simply type that IP address in, save, and they can no longer post from that location.<br>You can also
	ban people through their email address.';

$helptxt['featuresettings'] = '<strong>Features and Options</strong><br>
	There are several features in this page that can be changed to your preference.';

$helptxt['modsettings'] = '<strong>Modification Settings</strong><br>
	This page contains any settings added by modifications installed on your forum.';

$helptxt['time_format'] = '<strong>Time Format</strong><br>
	You have the ability to adjust how the time and date look for yourself. There are a lot of little letters, but it\'s quite simple.
	The conventions follow PHP\'s strftime function and are described as below (more details can be found at <a href="https://php.net/manual/function.strftime.php" target="_blank" rel="noopener">php.net</a>).<br>
	<br>
	The following characters are recognized in the format string: <br>
	<span class="smalltext">
	&nbsp;&nbsp;%a - abbreviated weekday name<br>
	&nbsp;&nbsp;%A - full weekday name<br>
	&nbsp;&nbsp;%b - abbreviated month name<br>
	&nbsp;&nbsp;%B - full month name<br>
	&nbsp;&nbsp;%d - day of the month (01 to 31) <br>
	&nbsp;&nbsp;%D<strong>*</strong> - same as %m/%d/%y <br>
	&nbsp;&nbsp;%e<strong>*</strong> - day of the month (1 to 31) <br>
	&nbsp;&nbsp;%H - hour using a 24-hour clock (range 00 to 23) <br>
	&nbsp;&nbsp;%I - hour using a 12-hour clock (range 01 to 12) <br>
	&nbsp;&nbsp;%m - month as a number (01 to 12) <br>
	&nbsp;&nbsp;%M - minute as a number <br>
	&nbsp;&nbsp;%p - either &quot;am&quot; or &quot;pm&quot; according to the given time<br>
	&nbsp;&nbsp;%R<strong>*</strong> - time in 24 hour notation <br>
	&nbsp;&nbsp;%S - second as a decimal number <br>
	&nbsp;&nbsp;%T<strong>*</strong> - current time, equal to %H:%M:%S <br>
	&nbsp;&nbsp;%y - 2 digit year (00 to 99) <br>
	&nbsp;&nbsp;%Y - 4 digit year<br>
	&nbsp;&nbsp;%% - a literal \'%\' character <br>
	<br>
	<em>* Does not work on Windows-based servers.</em></span>';

$helptxt['live_news'] = '<strong>Live announcements</strong><br>
	This box shows recently updated announcements from <a href="https://www.simplemachines.org/" target="_blank" rel="noopener">www.simplemachines.org</a>.
	You should check here every now and then for updates, new releases, and important information from Simple Machines.';

$helptxt['registrations'] = '<strong>Registration Management</strong><br>
	This page contains all the functions that could be necessary to manage new registrations on the forum. It contains up to four
	pages which are visible depending on your forum settings. These are:<br><br>
	<ul class="normallist">
		<li>
			<strong>Register new member</strong><br>
			From this page you can choose to register accounts for new members on their behalf. This can be useful in forums where registration is closed
			to new members, or in cases where the admin wishes to create a test account. If the setting to require activation of the account
			is selected the member will be emailed an activation link which must be clicked before they can use the account. Similarly you can
			select to email the user\'s new password to the stated email address.<br><br>
		</li>
		<li>
			<strong>Edit Registration Agreement</strong><br>
			This page allows you to set the text for the registration agreement displayed when members sign up for your forum.
			You can add or remove anything from the default registration agreement, which is included in SMF.<br><br>
		</li>
		<li>
			<strong>Set Reserved Names</strong><br>
			Using this page you can specify words or names which may not be used by your users.<br><br>
		</li>
		<li>
			<strong>Settings</strong><br>
			This page will only be visible if you have permission to administrate the forum. From this page you can decide on the registration method
			to use on your forum, as well as other registration related settings.
		</li>
	</ul>';

$helptxt['modlog'] = '<strong>Moderation Log</strong><br>
	This page allows members of the moderation team to track all the moderation actions that the forum moderators have performed. Note that
	clearing the log will be logged to prevent abuse.';
$helptxt['adminlog'] = '<strong>Administration Log</strong><br>
	This page allows members of the admin team to track some of the administrative actions that have occurred on the forum. Note that
	clearing the log will be logged to prevent abuse.';
$helptxt['userlog'] = '<strong>Profile Edits Log</strong><br>
	This page allows members of the admin team to view changes users make to their profiles, and is available from inside a user\'s profile area.';
$helptxt['warning_enable'] = '<strong>User Warning System</strong><br>
	This feature enables members of the admin and moderation team to issue warnings to members - and to use a members warning level to determine the
	actions available to them on the forum. Upon enabling this feature a permission will be available within the permissions page to define
	which groups may assign warnings to members. Warning levels can be adjusted from a member\'s profile.';
$helptxt['warning_watch'] = 'This setting defines the percentage warning level a member must reach to automatically assign a &quot;watch&quot; to the member. Any member who is being &quot;watched&quot; will appear in the watched members list in the moderation center.';
$helptxt['warning_moderate'] = 'Any member passing the value of this setting will find all their posts require moderator approval before they appear to the forum community. This will override any local board permissions which may exist related to post moderation.';
$helptxt['warning_mute'] = 'If this warning level is passed by a member they will find themselves under a post ban. The member will lose all posting rights.';
$helptxt['user_limit'] = 'This setting limits the amount of points a moderator may add/remove to any particular member in a twenty four hour period. This
			can be used to limit what a moderator can do in a small period of time. This can be disabled by setting it to a value of zero. Note that
			any members with administrator permissions are not affected by this value.';

$helptxt['error_log'] = '<strong>Error Log</strong><br>
	The error log tracks every serious error encountered by users of your forum. It lists all of these errors by date, which can be sorted
	by clicking the black arrow next to each date. Additionally you can filter the errors by clicking the picture next to each error statistic. This
	allows you to filter, for example, by member. When a filter is active the only results that will be displayed will be those that match that filter.';
$helptxt['theme_settings'] = '<strong>Theme Settings</strong><br>
	The settings page allows you to change settings specific to a theme. These settings include options such as the themes directory and URL information but
	also options that affect the layout of a theme on your forum. Most themes will have a variety of user configurable settings, allowing you to adapt a theme
	to suit your individual forum needs.';
$helptxt['smileys'] = '<strong>Smiley Center</strong><br>
	Here you can add and remove smileys, and smiley sets. If a smiley is present in one set, it should be present in all sets - otherwise, it might
	get confusing for your users using different sets.<br><br>

	You are also able to edit message icons from here, if you have them enabled on the settings page.';
$helptxt['calendar'] = '<strong>Manage Calendar</strong><br>
	Here you can modify the current calendar settings as well as add and remove holidays that appear on the calendar.';
$helptxt['cal_export'] = 'Exports a text file in the iCal format for importing into other calendar applications';
$helptxt['cal_highlight_events'] = 'This setting allows you to highlight events on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_highlight_holidays'] = 'This setting allows you to highlight holidays on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_highlight_birthdays'] = 'This setting allows you to highlight birthdays on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_disable_prev_next'] = 'If this setting is checked, the three month blocks on the left hand side of the page will be disabled.';
$helptxt['cal_display_type'] = 'This setting allows you to change the display type of the calendar.<br><br><strong>Comfortable:</strong> makes the rows of the calendar big.<br><strong>Compact:</strong> makes the rows of the calendar small.';
$helptxt['cal_week_links'] = 'If this setting is checked, links will be added alongside each week in the calendar.';
$helptxt['cal_prev_next_links'] = 'If this setting is checked, previous month and next month links will be added to the top of each month for easy navigation.';
$helptxt['cal_short_months'] = 'If this setting is checked, month names within the calendar will be shortened.<br><br><strong>Enabled:</strong> ' . $txt['months_short'][1] . ' 1<br><strong>Disabled:</strong> ' . $txt['months_titles'][1] . ' 1';
$helptxt['cal_short_days'] = 'If this setting is checked, day names within the calendar will be shortened.<br><br><strong>Enabled:</strong> ' . $txt['days_short'][1] . '<br><strong>Disabled:</strong> ' . $txt['days'][1];

$helptxt['serversettings'] = '<strong>Server Settings</strong><br>
	Here you can perform some core configuration for your forum. This page includes the database and url settings, as well as other
	important configuration items such as mail settings and caching. Think carefully whenever editing these settings as an error may
	render the forum inaccessible';
$helptxt['manage_files'] = '
	<ul class="normallist">
		<li>
			<strong>Browse Files</strong><br>
			Browse through all the attachments, avatars and thumbnails stored by SMF.<br><br>
		</li><li>
			<strong>Attachment Settings</strong><br>
			Configure where attachments are stored and set restrictions on the types of attachments.<br><br>
		</li><li>
			<strong>Avatar Settings</strong><br>
			Configure where avatars are stored and manage the resizing of avatars.<br><br>
		</li><li>
			<strong>File Maintenance</strong><br>
			Check and repair any error in the attachment directory and delete selected attachments.<br><br>
		</li>
	</ul>';

$helptxt['topicSummaryPosts'] = 'This allows you to set the number of previous posts shown in the topic summary on the reply page.';
$helptxt['enableAllMessages'] = 'Set this to the <em>maximum</em> number of posts a topic can have to show the <em>all</em> link. Setting this lower than &quot;Maximum messages to display in a topic page&quot; will simply mean it never gets shown, and setting it too high could slow down your forum.';
$helptxt['allow_guestAccess'] = 'Unchecking this box will stop guests from doing anything but very basic actions on your forum - login, register, password reminder, etc. - on your forum. This is not the same as disallowing guest access to boards.';
$helptxt['userLanguage'] = 'Turning this setting on will allow users to select which language file they use. It will not affect the
		default selection.';
$helptxt['trackStats'] = 'Stats:<br>This will allow users to see the latest posts and the most popular topics on your forum.
		It will also show several statistics, like the most members online, new members, and new topics.<hr>
		Page views:<br>Adds another column to the stats page with the number of pageviews on your forum.';
$helptxt['titlesEnable'] = 'Switching Custom Titles on will allow members with the relevant permission to create a special title for themselves.
		This will be shown underneath the name.<br><em>For example:</em><br>Jeff<br>Cool Guy';
$helptxt['onlineEnable'] = 'This will show an image to indicate whether the member is online or offline';
$helptxt['todayMod'] = 'This will show &quot;Today&quot; or &quot;Yesterday&quot; instead of the date.<br><br>
		<strong>Examples:</strong><br><br>
		<ul class="normallist">
			<li>
			<strong>Disabled</strong><br>
			October 3, 2009 at 12:59:18 am</li>
			<li><strong>Only Today</strong><br>
			Today at 12:59:18 am</li>
			<li><strong>Today &amp; Yesterday</strong><br>
			Yesterday at 09:36:55 pm</li>
		</ul>';
$helptxt['disableCustomPerPage'] = 'Check this setting to stop users from customizing the amount of messages and topics to display per page on the Message Index and Topic Display page respectively.';
$helptxt['enablePreviousNext'] = 'This will show a link to the next and previous topic.';
$helptxt['pollMode'] = 'This selects whether polls are enabled or not. If polls are disabled, any existing polls will be hidden
		from the topic listing. You can choose to continue to show the regular topic without their polls by selecting
		&quot;Show Existing Polls as Topics&quot;.<br><br>To choose who can post polls, view polls, and similar, you
		can allow and disallow those permissions. Remember this if polls are not working.';
$helptxt['enableCompressedOutput'] = 'This setting will compress output in order to lower bandwidth consumption, but it requires
		zlib to be installed.';
$helptxt['httponlyCookies'] = 'Cookies won\'t be accessible by scripting languages, such as JavaScript. This setting can help to reduce identity theft through XSS attacks. This can cause issues with third party scripts but should be on wherever possible.';
$helptxt['databaseSession_enable'] = 'This setting makes use of the database for session storage - it is best for load balanced servers, but helps with all timeout issues and can make the forum faster.';
$helptxt['databaseSession_loose'] = 'Turning this on will decrease the bandwidth your forum uses, and make it so clicking back will not reload the page - the downside is that the (new) icons won\'t update, among other things. (unless you click to that page instead of going back to it).';
$helptxt['databaseSession_lifetime'] = 'This is the number of seconds for sessions to last after they haven\'t been accessed. If a session is not accessed for too long, it is said to have &quot;timed out&quot;. Anything higher than 2400 is recommended.';
$helptxt['tfa_mode'] = 'You can add a second level of security to your forum by enabling <a href="https://en.wikipedia.org/wiki/Two_factor_authentication">Two Factor Authentication</a>. 2FA forces your users to add a enter a machine-generated code after the regular login. You need to configure 2FA to yourself before you are able to force it to other users!';
$helptxt['cache_enable'] = 'SMF performs caching at a variety of levels. The higher the level of caching enabled the more CPU time will be spent retrieving cached information. If caching is available on your machine it is recommended that you try caching at level 1 first.';
$helptxt['cache_memcached'] = 'If you are using memcached you need to provide the server details. This should be entered as a comma separated list as shown in the example below:<br><br>	&quot;server1,server2,server3:port,server4&quot;<br><br>Note that if no port is specified SMF will use port 11211 unless the host contains a slash, then it is assumed to be an alternative transport and the port will be set to 0. SMF will attempt to perform rough/random load balancing across the specified servers.';
$helptxt['cache_cachedir'] = 'This setting is only for the smf file-based cache system. It specifies the path to the cache directory. It is recommended that you place this in /tmp/ if you are going to use this, although it will work in any directory';
$helptxt['cache_sqlite_cachedir'] = 'This setting is only for the SQLite database cache system. It specifies the path to the cache directory. It is recommended that you place this in /tmp/ if you are going to use this, although it will work in any directory';
$helptxt['xcache_adminuser'] = 'This setting is only for the xcache based cache system. It specifies a username and password required to issue a clear command. If this is not set, clearing the cache will fail and the caching system will still force it to invalidate all cached data.<br>The username and password are in cleartext. The password is a unsalted MD5. It is recommended the username and password are set to random values.<br><br>The Cache API will attempt to set these values, however it is <a href="https://xcache.lighttpd.net/wiki/InstallAdministration">recommended these be set in your php.ini</a>';
$helptxt['enableErrorLogging'] = 'This will log any errors, like a failed login, so you can see what went wrong.';
$helptxt['enableErrorQueryLogging'] = 'This will include the full query sent to the database in the error log. It requires error logging to be turned on.<br><br><strong>Note:  This will affect the ability to filter the error log by the error message.</strong>';
$helptxt['log_ban_hits'] = 'If enabled, every time a banned user tries to access the site, this will be logged in the error log. If you do not care whether, or how often, banned users attempt to access the site, you can turn this off for a performance boost.';
$helptxt['allow_disableAnnounce'] = 'This will allow users to opt out of notification of topics you announce by checking the &quot;announce topic&quot; checkbox when posting.';
$helptxt['disallow_sendBody'] = 'This setting removes the option to receive the text of replies, posts, and personal messages in notification emails.<br><br>Often, members will reply to the notification email, which in most cases means the webmaster receives the reply.';
$helptxt['enable_ajax_alerts'] = 'This option allows your members to receive AJAX notifications. This means that members don\'t need to refresh the page to get new notifications.<br><strong>DO NOTE:</strong> This option might cause a severe load at your server with many users online.';
$helptxt['jquery_source'] = 'This will determine the source used to load the jQuery Library. <em>Auto</em> will use the CDN first and if not available fall back to the local source. <em>Local</em> will only use the local source. <em>CDN</em> will only load it from Google CDN network';
$helptxt['compactTopicPagesEnable'] = 'This will just show a selection of the number of pages.<br><em>Example:</em>
		&quot;3&quot; to display: 1 ... 4 [5] 6 ... 9 <br>
		&quot;5&quot; to display: 1 ... 3 4 [5] 6 7 ... 9';
$helptxt['timeLoadPageEnable'] = 'This will show the time in seconds SMF took to create the page at the bottom of the board.';
$helptxt['removeNestedQuotes'] = 'This will strip nested quotes from a post when citing the post in question via a quote link.';
$helptxt['max_image_width'] = 'This allows you to set a maximum size for posted pictures. Pictures smaller than the maximum will not be affected. This also determines how attached images are displayed when a thumbnail is clicked on.';
$helptxt['mail_type'] = 'This setting allows you to choose either PHP\'s default settings, or to override those settings with SMTP. PHP doesn\'t support using authentication with SMTP (which many hosts now require), so if you want that you should select SMTP. Please note that SMTP can be slower, and some servers will not accept usernames and passwords.<br><br>You don\'t need to fill in the SMTP settings if this is set to PHP\'s default.';
$helptxt['attachment_manager_settings'] = 'Attachments are files that members can upload and attach to a post. Different sites may use these in different ways, which you can configure here.';
$helptxt['attachmentCheckExtensions'] = 'For some communities, you may wish to limit the types of files that users can upload by checking the extension: e.g. myphoto.jpg has an extension of jpg.';
$helptxt['attachmentExtensions'] = 'If "check attachment\'s extension" above is ticked, these are the extensions that will be permitted for new attachments.';
$helptxt['attachmentUploadDir'] = 'The path to your attachment folder on the server<br>(example: /home/sites/yoursite/www/forum/attachments)';
$helptxt['attachmentDirSizeLimit'] = ' Select how large the attachment folder can be, including all files within it.';
$helptxt['attachmentPostLimit'] = ' Select the maximum filesize (in KB) of all attachments made per post. If this is lower than the per-attachment limit, this will be the limit.';
$helptxt['attachmentSizeLimit'] = 'Select the maximum filesize of each separate attachment.';
$helptxt['attachmentNumPerPostLimit'] = ' Select the number of attachments a person can make per post.';
$helptxt['attachmentShowImages'] = 'If the uploaded file is a picture, it will be displayed underneath the post.';
$helptxt['attachmentThumbnails'] = 'If the above setting is selected, this will save a separate (smaller) attachment for the thumbnail to decrease bandwidth.';
$helptxt['attachmentThumbWidth'] = 'Only used with the &quot;Resize images when showing under posts&quot; setting the maximum width to resize attachments down from. They will be resized proportionally.';
$helptxt['attachmentThumbHeight'] = 'Only used with the &quot;Resize images when showing under posts&quot; setting the maximum height to resize attachments down from. They will be resized proportionally.';
$helptxt['attachmentDirFileLimit'] = 'Max number of files per directory';
$helptxt['attachmentEnable'] = 'This setting enables you to configure how attachments can be made.<br><br>
	<ul class="normallist">
		<li>
			<strong>Disable all attachments</strong><br>
			All attachments are disabled. Existing attachments are not deleted, but they are hidden from view (even administrators cannot see them). New attachments cannot be made either, regardless of permissions.<br><br>
		</li>
		<li>
			<strong>Enable all attachments</strong><br>
			Everything behaves as normal, users who are permitted to view attachments can do so, users who are permitted to upload can do so.<br><br>
		</li>
		<li>
			<strong>Disable new attachments</strong><br>
			Existing attachments are still accessible, but no new attachments can be added, regardless of permission.
		</li>
	</ul>';
$helptxt['attachment_image_paranoid'] = 'Selecting this setting will enable very strict security checks on image attachments. <strong>Warning!</strong> These extensive checks can fail on valid images too. It is strongly recommended to only use this setting together with image re-encoding, in order to have SMF try to resample the images which fail the security checks: if successful, they will be sanitized and uploaded. Otherwise, if image re-encoding is not enabled, all attachments failing checks will be rejected.';
$helptxt['attachment_image_reencode'] = 'Selecting this setting will enable trying to re-encode the uploaded image attachments. Image re-encoding offers better security. Note however that image re-encoding also renders all animated images static. <br> This feature is only possible if the GD module is installed on your server.';
$helptxt['attachment_thumb_memory'] = 'The larger the source image (size & width x height), the higher the memory requirements are for the system to successfully create a thumbnail image.<br>Checking this setting, the system will estimate the required memory and will then request that amount. If successful, only then will it attempt to create the thumbnail.<br>This will result in fewer white page errors but may result in fewer thumbnails being created. Leaving this unchecked will always cause the system to try to create the thumbnail (with a fixed amount of memory). This may result in more white page errors.';
$helptxt['attachmentRecodeLineEndings'] = 'The script will re-code line endings according to your server.';
$helptxt['automanage_attachments'] = 'By default, SMF puts new attachments into a single folder. For most sites this is not a problem, but as a site grows it can be useful to have multiple folders to store attachments in.<br><br>This setting allows you to set whether you manage these folders yourself (e.g. creating a second folder and moving to it when you are ready) or whether you let SMF do it, based on criteria, such as when the current directory reaches a given size, or breaking down folders by years or even months on very busy sites.';
$helptxt['dont_show_attach_under_post'] = 'If enabled, the attachments embedded on the post won\'t appear under the post but are still subject to be deleted.';
$helptxt['use_subdirectories_for_attachments'] = 'Create new directories.';
$helptxt['max_image_height'] = 'As with the maximum width, this setting indicates the maximum height a posted image can be.';
$helptxt['avatar_paranoid'] = 'Selecting this setting will enable very strict security checks on avatars. <strong>Warning!</strong> These extensive checks can fail on valid images too. It is strongly recommended to only use this setting together with avatar re-encoding, in order to have SMF try to resample the images which fail the security checks: if successful, they will be sanitized and uploaded. Otherwise, if re-encoding of avatars is not enabled, all avatars failing checks will be rejected.';
$helptxt['avatar_reencode'] = 'Selecting this setting will enable trying to re-encode the uploaded avatars. Image re-encoding offers better security. Note, however, that image re-encoding also renders all animated images static. <br> This feature is only possible if the GD module is installed on your server.';
$helptxt['cal_enabled'] = 'The calendar can be used for showing birthdays, or for showing important events happening in your community. This setting enables the calendar and all its features.';
$helptxt['cal_link_postevent'] = 'This will allow members to post events for that day, when they click on that date';
$helptxt['cal_maxdays_advance'] = 'If this is set to 7, the next week\'s worth of events will be shown';
$helptxt['cal_allow_unlinkedevents'] = 'Allow members to post events without requiring them to be linked with a post in a board';
$helptxt['cal_min_year'] = 'Select the &quot;first&quot; year on the calendar list';
$helptxt['cal_max_year'] = 'Select the &quot;last&quot; year on the calendar list';
$helptxt['cal_maxevent_span'] = 'Select the maximum number of days that an event can span';
$helptxt['localCookies'] = 'SMF uses cookies to store login information on the client computer.
	Cookies can be stored globally (myserver.com) or locally (myserver.com/path/to/forum).<br><br>
	Try this setting if you\'re experiencing problems with users getting logged out automatically.<hr>
	Globally stored cookies are less secure when used on a shared webserver (like Tripod).<hr>
	Local cookies don\'t work outside the forum directory so, if your forum is stored at www.myserver.com/forum, pages like www.myserver.com/index.php cannot access the account information.<br><br>
	Global cookies are especially recommended when using SSI.php but work well for most sites on their own domain.';
$helptxt['enableBBC'] = 'Selecting this setting will allow your members to use Bulletin Board Code (BBC) throughout the forum, allowing users to format their posts with images, type formatting, and more.';
$helptxt['legacy_bbc'] = 'Legacy BBCodes are obsolete and cannot be used in new posts. However, they will be parsed in existing posts if enabled here.<br><br>Enabling legacy BBCodes is only useful if your forum was upgraded from a previous version of SMF.<br><br>Also, even when they are enabled, some legacy BBCodes will not be rendered in the same way that they once were. For example, the [flash] BBCode will simply show a link to the Flash content instead of embedding it.';
$helptxt['time_offset'] = 'Not all forum administrators want their forum to use the same time zone as the server upon which it is hosted. Use this setting to specify the time difference (in hours) between the server time and the time to be used for the forum. Negative and decimal values are permitted.';
$helptxt['default_timezone'] = 'The server time zone tells PHP where your server is located. You should ensure that this is set correctly, preferably to the country/city in which the server is located. You can find out more information on the <a href="https://php.net/manual/en/timezones.php" target="_blank" rel="noopener">PHP Site</a>.';
$helptxt['timezone_priority_countries'] = 'This setting lets you push the time zones for a certain country or countries to the top of the list of selectabled time zones that is shown when users are configuring their profiles, creating calendar events, etc.<br><br>For example, if many of your forum\'s members live in New Zealand or Fiji, you may enter "NZ,FJ" to make it easier for them to find the most relevant time zones quickly.<br><br>You can find the complete list of ISO country codes by searching the Internet for "<a href="//www.google.com/search?q=iso+3166-1+alpha-2" target="_blank" rel="noopener">ISO 3166-1 alpha-2</a>".';
$helptxt['spamWaitTime'] = 'Here you can select the amount of time that must pass between postings. This can be used to stop people from "spamming" your forum by limiting how often they can post.';

$helptxt['enablePostHTML'] = 'This will allow the posting of some basic HTML tags:
	<ul class="normallist">
		<li>&lt;b&gt;, &lt;u&gt;, &lt;i&gt;, &lt;s&gt;, &lt;em&gt;, &lt;strong&gt;, &lt;ins&gt;, &lt;del&gt;</li>
		<li>&lt;a href=&quot;&quot;&gt;</li>
		<li>&lt;img src=&quot;&quot; alt=&quot;&quot; /&gt;</li>
		<li>&lt;br&gt;, &lt;hr&gt;</li>
		<li>&lt;pre&gt;, &lt;blockquote&gt;</li>
	</ul>';

$helptxt['themes'] = 'Here you can select whether the default theme can be chosen, what theme guests will use,
	as well as other settings. Click on a theme to the right to change the settings for it.';
$helptxt['theme_install'] = 'This allows you to install new themes. You can do this from an existing directory, by uploading an archive for the theme, or by copying the default theme.<br><br>Note that the archive or directory must have a <pre>theme_info.xml</pre> definition file.';
$helptxt['xmlnews_enable'] = 'Allows people to link to <a href="%1$s?action=.xml;sa=news" target="_blank" rel="noopener">Recent news</a>
	and similar data. It is also recommended that you limit the size of recent posts/news because some clients expect the RSS data to be truncated for display.';
$helptxt['xmlnews_attachments'] = 'Allows links to attachments to be enclosed within your XML/RSS feeds. Turn this on if you want to use your forum as a podcasting, broadcatching, or similar content distribution platform.
	<br><br>
	The "view attachments" permission affects this setting. Members and guests will only see a post\'s attachments in the feed if they can also see the attachments on the post\'s web page. Keep in mind that most feed readers will access your feeds as a guest.
	<br><br>
	Some feed formats only allow one enclosed attachment per post. If a post has multiple attachments, SMF will enclose the largest one in these feeds.';
$helptxt['globalCookies'] = 'Makes log in cookies available across subdomains. For example, if...<br>
	your site is https://www.example.com/,<br>
	and your forum is https://forum.example.com/,<br>
	using this setting will allow you to access the forum\'s cookie on your site. Do not enable this if there are other subdomains (like hacker.example.com) not controlled by you.<br><br>
	This setting does not function when local cookies are enabled.';
$helptxt['globalCookiesDomain'] = 'When using subdomain independent cookies (global cookies), you can specify which domain should be used for them. This should, of course, be set to your main domain - for example, if you are using <em>forum.example.com</em> and <em>www.example.com</em>, the domain is <em>example.com</em> in this case. You should not put the <em>http://</em> or <em>https://</em> part in front of it.';
$helptxt['secureCookies'] = 'Enabling this setting will force the cookies created for users on your forum to be marked as secure. Only enable this setting if you are using HTTPS throughout your site, otherwise it will mean no-one will be able to log in properly!';
$helptxt['securityDisable'] = 'This <em>disables</em> the additional password check for the administration page. This is not recommended!';
$helptxt['securityDisable_why'] = 'This is your current password. (the same one you use to login.)<br><br>The requirement to enter this helps ensure that you want to do whatever administration you are doing, and that it is <strong>you</strong> doing it.';
$helptxt['securityDisable_moderate'] = 'This <em>disables</em> the additional password check for the moderation page. This is not recommended!';
$helptxt['securityDisable_moderate_why'] = 'This is your current password. (the same one you use to login).<br><br>The requirement to enter this helps ensure that you want to do whatever moderation you are doing, and that it is <strong>you</strong> doing it.';
$helptxt['frame_security'] = 'Modern browsers now understand a security header presented by servers called X-Frame-Options. By setting this option you specify how you want to allow your site to be framed inside a frameset or a iframe. Disable will not send any header and is the most unsecure, however allows the most freedom. Deny will prevent all frames completely and is the most restrictive and secure. Allowing the Same Origin will only allow your domain to issue any frames and provides a middle ground for the previous two options.';
$helptxt['proxy_ip_header'] = 'This is the server header that will be trusted by SMF for containing the actual users IP address. Changing this setting can cause unexpected IP results on members. Please check with your server administrator, CDN provider or proxy administrator prior to changing these settings. Most providers will understand and use HTTP_X_FORWARDED_FOR. You should fill out the list of Servers sending the reverse proxy headers for security to ensure these headers only come from valid sources.';
$helptxt['email_members'] = 'In this message you can use a few &quot;variables&quot;. These are:<br>
	{$board_url} - The URL to your forum.<br>
	{$current_time} - The current time.<br>
	{$member.email} - The current member\'s email.<br>
	{$member.link} - The current member\'s link.<br>
	{$member.id} - The current member\'s id.<br>
	{$member.name} - The current member\'s name. (for personalization).<br>
	{$latest_member.link} - The most recently registered member\'s link.<br>
	{$latest_member.id} - The most recently registered member\'s id.<br>
	{$latest_member.name} - The most recently registered member\'s name.';

$helptxt['failed_login_threshold'] = 'Set the number of failed login attempts before directing the user to the password reminder page.';
$helptxt['loginHistoryDays'] = 'The number of days to keep login history under user profile tracking. The default is 30 days.';
$helptxt['oldTopicDays'] = 'If this setting is enabled, a warning will be displayed to the user when attempting to reply to a topic which has not had any new replies for the amount of time, in days, specified by this setting. Set this setting to 0 to disable the feature.';
$helptxt['edit_wait_time'] = 'The number of seconds allowed for a post to be edited before logging the last edit date.';
$helptxt['edit_disable_time'] = 'The number of minutes allowed to pass before a user can no longer edit a post they have made. Set to 0 disable. <br><br><em>Note: This will not affect any user who has permission to edit other people\'s posts.</em>';
$helptxt['preview_characters'] = 'This setting sets the number of available characters for the first and last message topic preview.';
$helptxt['posts_require_captcha'] = 'This setting will force users to pass anti-spam bot verification each time they make a post to a board. Only users with a post count below the number set will need to enter the code - this should help combat automated spamming scripts.';
$helptxt['enableSpellChecking'] = 'Enable spell checking. You MUST have the pspell or enchant library installed on your server and your PHP configuration set up to use the installed library. Your server ' . ((function_exists('pspell_new') || function_exists('enchant_broker_init')) ? 'DOES' : 'DOES NOT') . ' appear to have this set up.';
$helptxt['disable_wysiwyg'] = 'This setting disallows all users from using the WYSIWYG (&quot;What You See Is What You Get&quot;) editor on the post page.';
$helptxt['lastActive'] = 'Set the number of minutes to show people are active in X number of minutes on the board index. Default is 15 minutes.';

$helptxt['customoptions'] = 'This defines the options that a user may choose from a drop down list. There are a few key points to note on this page:
	<ul class="normallist">
		<li><strong>Default setting:</strong> Whichever check box has the &quot;radio button&quot; next to it selected will be the default selection for the user when they enter their profile.</li>
		<li><strong>Removing Options:</strong> To remove an option simply empty the text box for that option - all users with that selected will have their option cleared.</li>
		<li><strong>Reordering Options:</strong> You can reorder the options by moving text around between the boxes. However - an important note - you must make sure you do <strong>not</strong> change the text when reordering options as otherwise user data will be lost.</li>
	</ul>';

$helptxt['autoFixDatabase'] = 'This will automatically fix broken tables and resume as if nothing happened. This can be useful, because the only way to fix it is to REPAIR the table, and this way your forum won\'t be down until you notice. It does email you when this happens.';
$helptxt['pgFulltextSearch'] = 'This setting defines the language to use for PostgreSQL\'s fulltext search. Choose the language that most closely matches the language your forum actually uses. If your forum\'s language is not listed, or if your forum is multi-lingual, choose the "simple" option. <br>
This setting is independent of your main SMF language settings and not related to the installed language for SMF. <br>
When you change this setting you need to [url=' . $scripturl . '?action=admin;area=managesearch;sa=method]rebuild the fulltext search index[/url].<br>
If a language you need is not listed, ask your database admin to install PostgreSQL language support for that language.';

$helptxt['enableParticipation'] = 'This shows a little icon on the topics the user has posted in.';

$helptxt['db_persist'] = 'This keeps the connection active to increase performance. If you aren\'t on a dedicated server, this may cause you problems with your host.<br>
If this option is disabled, you may need to enable the mysqli.allow_persistent or pgsql.allow_persistent setting in your php.ini file.';
$helptxt['ssi_db_user'] = 'Optional setting to use a different database user and password when you are using SSI.php.';

$helptxt['queryless_urls'] = 'This changes the format of URLs a little so search engines will like them better. They will look like index.php/topic,1.0.html.<br><br>This feature will ' . (isset($_SERVER['SERVER_SOFTWARE']) && (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false || strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false) ? '' : 'not') . ' work on your server.';
$helptxt['minimize_files'] = 'This option will minimize the CSS and JavaScript files used by your forum, reducing the request calls to your server and speeding up load times for your users.<br><br>You should normally leave this option enabled. The only time you might want to disable it temporarily is while you are in the process of altering the CSS or JavaScript files used on your site (for example, when editing your theme files or developing a modification).';
$helptxt['boardindex_max_depth'] = 'This setting controls how many sub-board levels SMF will check when looking for new posts in sub-boards. It also affects which boards are included when the "Count sub-board\'s posts in parent\'s totals" option is enabled.<br><br>For example, if this is set to 1, a sub-board will only be marked "new" on the index page if the sub-board itself contains a new post. If it is set to 2, the sub-board will be marked "new" if there is a new post in the sub-board or in a sub-sub-board. If it is set to 10, the sub-board will be marked "new" if there is a new post in the sub-board, a sub-sub-board, a sub-sub-sub-board, etc., up to 10 levels deep.<br><br>Large, active forums may want to set this value to a low number in order to conserve server resources.';
$helptxt['countChildPosts'] = 'Checking this setting means that posts and topics in a board\'s sub-board will count toward its totals on the index page.<br><br>This means that a parent board with no posts in it won\'t show \'0\' if there are any posts in its sub-boards.';
$helptxt['allow_ignore_boards'] = 'Checking this setting will allow users to select boards they wish to ignore.';
$helptxt['deny_boards_access'] = 'Checking this setting will allow you to deny access to certain boards based on membergroup access';

$helptxt['who_enabled'] = 'This setting allows you to turn on or off the ability for users to see who is browsing the forum and what they are doing.';

$helptxt['recycle_enable'] = '&quot;Recycles&quot; deleted topics and posts to the specified board.';

$helptxt['enableReportPM'] = 'This setting allows your users to report personal messages they receive to the administration team. This may be useful in helping to track down any abuse of the personal messaging system.';
$helptxt['max_pm_recipients'] = 'This setting allows you to set the maximum amount of recipients allowed in a single personal message sent by a forum member. This may be used to help stop spam abuse of the PM system. Note that users with permission to send newsletters are exempt from this restriction. Set to zero for no limit.';
$helptxt['pm_posts_verification'] = 'This setting will force users to enter a code shown on a verification image each time they are sending a personal message. Only users with a post count below the number set will need to enter the code - this should help combat automated spamming scripts.';
$helptxt['pm_posts_per_hour'] = 'This will limit the number of personal messages which may be sent by a user in a one hour period. This does not affect admins or moderators.';

$helptxt['default_personal_text'] = 'Sets the default text a new user will have as their &quot;personal text.&quot; This setting is not available when personal text is disabled, or when users can set personal text on registration for themselves.';

$helptxt['registration_method'] = 'This setting determines what method of registration is used for people wishing to join your forum. You can select from:<br><br>
	<ul class="normallist">
		<li>
			<strong>Registration Disabled</strong><br>
				Disables the registration process, which means that no new members can register to join your forum.<br>
		</li><li>
			<strong>Immediate Registration</strong><br>
				New members can login and post immediately after registering on your forum.<br>
		</li><li>
			<strong>Email Activation</strong><br>
				When this setting is enabled any members registering with the forum will have an activation link emailed to them which they must click before they can become full members.<br>
		</li><li>
			<strong>Admin Approval</strong><br>
				This setting will make it so all new members registering on your forum will need to be approved by the admin before they become members.
		</li>
	</ul>';

$helptxt['send_validation_onChange'] = 'When this setting is checked all members who change their email address in their profile will have to reactivate their account from an email sent to that address';
$helptxt['approveAccountDeletion'] = 'When this setting is checked, any user request to delete his own account has to be approved by an administrator';

$helptxt['send_welcomeEmail'] = 'When this setting is enabled all new members will be sent an email welcoming them to your community';
$helptxt['password_strength'] = 'This setting determines the strength required for passwords selected by your forum users. The stronger the password, the harder it should be to compromise member\'s accounts.
	Its possible settings are:
	<ul class="normallist">
		<li><strong>Low:</strong> The password must be at least four characters long.</li>
		<li><strong>Medium:</strong> The password must be at least eight characters long, and cannot be part of a user\'s name or email address.</li>
		<li><strong>High:</strong> As for medium, except the password must also contain a mixture of upper and lower case letters, and at least one number.</li>
	</ul>';
$helptxt['enable_password_conversion'] = 'By enabling this setting, SMF will attempt to detect passwords stored in other formats and convert them to the format SMF uses. Typically this is used for forums converted to SMF, but may have other uses as well. Disabling this prevents a user from logging in using their password after a conversion and they would need to reset their password.';

$helptxt['coppaAge'] = 'The value specified in this box will determine the minimum age that new members must be in order to be granted immediate access to the forums.
	On registration they will be prompted to confirm whether they are over this age, and if not will either have their application rejected or suspended awaiting parental approval - dependant on the type of restriction chosen.
	If a value of 0 is chosen for this setting then all other age restriction settings shall be ignored.';
$helptxt['coppaType'] = 'If age restrictions are enabled, then this setting will define what happens when a user below the minimum age attempts to register with your forum. There are two possible choices:
	<ul class="normallist">
		<li>
			<strong>Reject Their Registration:</strong><br>
				Any new member below the minimum age will have their registration rejected immediately.<br>
		</li><li>
			<strong>Require Parent/Guardian Approval</strong><br>
				Any new member who attempts to register and is below the minimum permitted age will have their account marked as awaiting approval, and will be presented with a form upon which their parents must give permission to become a member of the forum.
				They will also be presented with the forum contact details entered on the settings page, so that they can send the form to the administrator by mail or fax.
		</li>
	</ul>';
$helptxt['coppaPost'] = 'The contact boxes are required so that forms granting permission for underage registration can be sent to the forum administrator. These details will be shown to all new minors, and are required for parent/guardian approval. At the very least a postal address or fax number must be provided.';

$helptxt['allow_hideOnline'] = 'With this setting enabled all members will be able to hide their online status from other users (except administrators). Note that disabling this setting will not change any existing member\'s status - it just stops them from hiding themselves in the future.';
$helptxt['meta_keywords'] = 'These keywords are sent in the output of every page to indicate to search engines (etc) the key content of your site. They should be a comma separated list of words, and should not use HTML.';

$helptxt['latest_themes'] = 'This area shows a few of the latest and most popular themes from <a href="https://www.simplemachines.org/" target="_blank" rel="noopener">www.simplemachines.org</a>. It may not show up properly if your computer can\'t find <a href="https://www.simplemachines.org/" target="_blank" rel="noopener">www.simplemachines.org</a>, though.';

$helptxt['secret_why_blank'] = 'For your security, your password and the answer to your secret question are encrypted so that the SMF software will never tell you, or anyone else, what they are.';
$helptxt['moderator_why_missing'] = 'Since moderation is done on a board-by-board basis, you have to make members moderators from the <a href="%1$s?action=admin;area=manageboards" target="_blank" rel="noopener">board management interface</a>.';

$helptxt['permissions'] = 'Permissions are how you either allow groups to, or deny groups from, doing specific things.<br><br>You can modify multiple boards at once with the checkboxes, or look at the permissions for a specific group by clicking \'Modify.\'';
$helptxt['permissions_board'] = 'If a board is set to \'Global,\' it means that the board will not have any special permissions. \'Local\' means it will have its own permissions - separate from the global ones. This allows you to have a board that has more or fewer permissions than another, without requiring you to set them for each and every board.';
$helptxt['permissions_quickgroups'] = 'These allow you to use the &quot;default&quot; permission setups -  <em>standard</em> means \'nothing special\', <em>restrictive</em> means \'like a guest\', <em>moderator</em> means \'what a moderator has\', and lastly <em>maintenance</em> means \'permissions very close to those of an administrator\'.';
$helptxt['permissions_deny'] = 'Denying permissions can be useful when you want to take away permission from certain members. You can add a membergroup with a \'deny\'-permission to the members you wish to deny a permission.<br><br><strong>Use with care</strong>, a denied permission will stay denied no matter what other membergroups the member is in.';
$helptxt['permissions_postgroups'] = 'Enabling permissions for groups based on post count will allow you to attribute permissions to members that have posted a certain amount of messages. The permissions of the post count based groups are <em>added</em> to the permissions of the regular membergroups.';
$helptxt['membergroup_guests'] = 'The Guests membergroup is for all users that are not logged in.';
$helptxt['membergroup_regular_members'] = 'The Regular Members are all members that are logged in, but that have no primary membergroup assigned.';
$helptxt['membergroup_administrator'] = 'The administrator can, per definition, do anything and see any board. There are no permission settings for the administrator.';
$helptxt['membergroup_moderator'] = 'The Moderator membergroup is a special membergroup. Permissions and settings assigned to this group apply to moderators but only <em>on the boards they moderate</em>. Outside these boards they\'re just like any other member. Note that permissions for this group also apply to any group assigned to moderate a board.';
$helptxt['membergroups'] = 'In SMF there are two types of groups that your members can be part of. These are:
	<ul class="normallist">
		<li><strong>Regular Groups:</strong> A regular group is a group which members are not automatically placed. To assign a member to be in a group simply go to their profile and click &quot;Account Settings&quot;. From here you can assign them any number of regular groups to which they will be part.</li>
		<li><strong>Post Groups:</strong> Unlike regular groups post based groups cannot be assigned. Instead, members are automatically assigned to a post based group when they reach the minimum number of posts required to be in that group.</li>
	</ul>';

$helptxt['calendar_how_edit'] = 'You can edit these events by clicking on the red asterisk (*) next to their names.';

$helptxt['maintenance_backup'] = 'This area allows you to save a copy of all the posts, settings, members, and other information in your forum to a very large file.<br><br>It is recommended that you do this often, perhaps weekly, for safety and security.';
$helptxt['maintenance_rot'] = 'This allows you to <strong>completely</strong> and <strong>irrevocably</strong> remove old topics. It is recommended that you try to make a backup first, just in case you remove something you didn\'t mean to.<br><br><strong>Use this setting with care.</strong>';
$helptxt['maintenance_members'] = 'This allows you to <strong>completely</strong> and <strong>irrevocably</strong> remove member accounts from your forum. It is <strong>highly</strong> recommended that you try to make a backup first, just in case you remove something you didn\'t mean to.<br><br><strong>Use this setting with care.</strong>';

$helptxt['avatar_server_stored'] = 'This allows your members to pick from avatars stored on your server itself. They are, generally, in the same place as SMF under the avatars directory.<br>As a tip, if you create directories in that folder, you can make &quot;categories&quot; of avatars.';
$helptxt['avatar_external'] = 'With this enabled, your members can type in a URL to their own avatar. The downside of this is that, in some cases, they may use avatars that are overly large or portray images you don\'t want on your forum.';
$helptxt['avatar_download_external'] = 'With this setting enabled, the URL given by the user is accessed to download the avatar at that location. On success, the avatar will be treated as uploadable avatar.';
$helptxt['avatar_action_too_large'] = 'This setting therefore lets you reject images (from other sites) that are too big, or tells the user\'s browser to resize them, or to download them to your server.<br><br>If users put in very large images as their avatars and resize in the browser, it could cause very slow loading for your users - it does not actually resize the file, it just displays it smaller. So a digital photo, for example, would still be loaded in full and then resized only when displayed - so for users this could get quite slow and use a lot of bandwidth.<br><br>On the other hand, downloading them means using your bandwidth and server space, but you also ensure that images are smaller, so it should be faster for users. (Note: downloading and resizing requires either the GD library, or ImageMagick using either the Imagick or MagickWand extensions)';
$helptxt['avatar_upload'] = 'This setting is much like &quot;Allow members to select an external avatar&quot;, except that you have better control over the avatars, a better time resizing them, and your members do not have to have somewhere to put avatars.<br><br>However, the downside is that it can take a lot of space on your server.';
$helptxt['avatar_download_png'] = 'PNGs are larger, but offer better quality compression. If this is unchecked, JPEG will be used instead - which is often smaller, but also of lesser or blurry quality.';

$helptxt['disableHostnameLookup'] = 'This disables host name lookups, which on some servers are very slow. Note that this will make banning less effective.';

$helptxt['search_weight_frequency'] = 'Weight factors are used to determine the relevancy of a search result. Change these weight factors to match the things that are specifically important for your forum. For instance, a forum of a news site, might want a relatively high value for \'age of last matching message\'. All values are relative in relation to each other and should be positive integers.<br><br>This factor counts the amount of matching messages and divides them by the total number of messages within a topic.';
$helptxt['search_weight_age'] = 'Weight factors are used to determine the relevancy of a search result. Change these weight factors to match the things that are specifically important for your forum. For instance, a forum of a news site, might want a relatively high value for \'age of last matching message\'. All values are relative in relation to each other and should be positive integers.<br><br>This factor rates the age of the last matching message within a topic. The more recent this message is, the higher the score.';
$helptxt['search_weight_length'] = 'Weight factors are used to determine the relevancy of a search result. Change these weight factors to match the things that are specifically important for your forum. For instance, a forum of a news site, might want a relatively high value for \'age of last matching message\'. All values are relative in relation to each other and should be positive integers.<br><br>This factor is based on the topic size. The more messages are within the topic, the higher the score.';
$helptxt['search_weight_subject'] = 'Weight factors are used to determine the relevancy of a search result. Change these weight factors to match the things that are specifically important for your forum. For instance, a forum of a news site, might want a relatively high value for \'age of last matching message\'. All values are relative in relation to each other and should be positive integers.<br><br>This factor looks whether a search term can be found within the subject of a topic.';
$helptxt['search_weight_first_message'] = 'Weight factors are used to determine the relevancy of a search result. Change these weight factors to match the things that are specifically important for your forum. For instance, a forum of a news site, might want a relatively high value for \'age of last matching message\'. All values are relative in relation to each other and should be positive integers.<br><br>This factor looks whether a match can be found in the first message of a topic.';
$helptxt['search_weight_sticky'] = 'Weight factors are used to determine the relevancy of a search result. Change these weight factors to match the things that are specifically important for your forum. For instance, a forum of a news site, might want a relatively high value for \'age of last matching message\'. All values are relative in relation to each other and should be positive integers.<br><br>This factor looks whether a topic is sticky and increases the relevancy score if it is.';
$helptxt['search'] = 'Adjust all settings for the search function here.';
$helptxt['search_why_use_index'] = 'A search index can greatly improve the performance of searches on your forum. Especially when the number of messages on a forum grows bigger, searching without an index can take a long time and increase the pressure on your database. If your forum is bigger than 50.000 messages, you might want to consider creating a search index to assure peak performance of your forum.<br><br>Note that a search index can take up quite some space. A fulltext index is a built-in index of MySQL. It\'s relatively compact (approximately the same size as the message table), but a lot of words aren\'t indexed and it can, in some search queries, turn out to be very slow. The custom index is often bigger (depending on your configuration it can be up to 3 times the size of the messages table) but it\'s performance is better than fulltext and relatively stable.';

$helptxt['see_admin_ip'] = 'IP addresses are shown to administrators and moderators to facilitate moderation and to make it easier to track people up to no good. Remember that IP addresses may not always be identifying, and most people\'s IP addresses change periodically.<br><br>Members are also allowed to see their own IPs.';
$helptxt['see_member_ip'] = 'Your IP address is shown only to you and moderators. Remember that this information is not identifying, and that most IPs change periodically.<br><br>You cannot see other members\' IP addresses, and they cannot see yours.';
$helptxt['whytwoip'] = 'SMF uses various methods to detect user IP addresses. Usually these two methods result in the same address but in some cases more than one address may be detected. In this case SMF logs both addresses, and uses them both for ban checks (etc). You can click on either address to track that IP and ban if necessary.';

$helptxt['ban_cannot_post'] = 'The \'cannot post\' restriction turns the forum into read-only mode for the banned user. The user cannot create new topics, or reply to existing ones, send personal messages or vote in polls. The banned user can however still read personal messages and topics.<br><br>A warning message is shown to the users that are banned this way.';

$helptxt['posts_and_topics'] = '
	<ul class="normallist">
		<li>
			<strong>Post Settings</strong><br>
			Modify the settings related to the posting of messages and the way messages are shown. You can also enable the spell check here.
		</li><li>
			<strong>Bulletin Board Code</strong><br>
			Enable the code that shows forum messages in the right layout. Also adjust which codes are allowed and which aren\'t.
		</li><li>
			<strong>Censored Words</strong>
			In order to keep the language on your forum under control, you can censor certain words. This function allows you to convert forbidden words into innocent versions.
		</li><li>
			<strong>Topic Settings</strong>
			Modify the settings related to topics. The number of topics per page, whether the print-page view is enabled, etc.
		</li>
	</ul>';

$helptxt['spider_mode'] = 'Sets the logging level.<br>
Standard - Logs minimal spider activity.<br>
Moderate - Provides more accurate statistics.<br>
Aggressive - Same as &quot;Moderate&quot;, but logs data about each page visited.';

$helptxt['spider_group'] = 'By selecting a restrictive group, when a guest is detected as a search crawler it will automatically be assigned any &quot;deny&quot; deny permissions of this group in addition to the normal permissions of a guest. You can use this to provide lesser access to a search engine than you would a normal guest. You might for example wish to create a new group called &quot;Spiders&quot; and select that here. You could then deny permission for that group to view profiles to stop spiders indexing your members profiles.<br>Note: Spider detection is not perfect and can be simulated by users so this feature is not guaranteed to restrict content only to those search engines you have added.';
$helptxt['show_spider_online'] = 'This setting allows you to select whether spiders should be listed in the &quot;Who\'s Online&quot; list on the board index and &quot;Who\'s Online&quot; page. The options are:
	<ul class="normallist">
		<li>
			<strong>Not at All</strong><br>
			Spiders will simply appear as guests to all users.
		</li><li>
			<strong>Show Spider Quantity</strong><br>
			The Board Index will display the number of spiders currently visiting the forum.
		</li><li>
			<strong>Show Spider Names</strong><br>
			Each spider name will be revealed, so users can see how many of each spider is currently visiting the forum - this takes effect in both the Board Index and Who\'s Online page.
		</li><li>
			<strong>Show Spider Names - Admin Only</strong><br>
			As above except only Administrators can see spider status - to all other users spiders appear as guests.
		</li>
	</ul>';

$helptxt['birthday_email'] = 'Choose the index of the birthday email message to use. A preview will be shown in the Email Subject and Email Body fields.<br><strong>Note:</strong> Selecting this setting does not automatically enable birthday emails. To enable birthday emails use the <a href="%1$s?action=admin;area=scheduledtasks;%3$s=%2$s" target="_blank" rel="noopener">Scheduled Tasks</a> page and enable the birthday email task.';
$helptxt['pm_bcc'] = 'When sending a personal message you can choose to add a recipient as BCC (Blind Carbon Copy). BCC recipients do not have their identities revealed to the other recipients of the message.';

$helptxt['move_topics_maintenance'] = 'This will allow you to move all the posts from one board to another board.';
$helptxt['maintain_reattribute_posts'] = 'You can use this function to attribute guest posts on your board to a registered member. This is useful if, for example, a user deleted their account and changed their mind and wished to have their old posts associated with their account.';
$helptxt['chmod_flags'] = 'You can manually set the permissions you wish to set the selected files to. To do this enter the chmod value as a numeric (octet) value. Note - these flags will have no effect on Microsoft Windows operating systems.';

$helptxt['postmod'] = 'This page allows members of the moderation team (with sufficient permissions) to approve any posts and topics before they are shown.';

$helptxt['field_show_enclosed'] = 'Encloses the user input between some text or html. This will allow you to add more instant message providers, images or an embed etc. For example:<br><br>
		&lt;a href="https://example.com/{INPUT}"&gt;&lt;img src="{DEFAULT_IMAGES_URL}/icon.png" alt="{INPUT}" /&gt;&lt;/a&gt;<br><br>
		Note that you can use the following variables:<br>
		<ul class="normallist">
			<li>{INPUT} - The input specified by the user.</li>
			<li>{SCRIPTURL} - Web address of forum.</li>
			<li>{IMAGES_URL} - Url to images directory in the users current theme.</li>
			<li>{DEFAULT_IMAGES_URL} - Url to the images directory in the default theme.</li>
			<li>{KEY} - Used when a field has multiple options, is the unique numeric ID used to identify it.</li>
		</ul>';

$helptxt['custom_mask'] = 'The input mask is important for your forum\'s security. Validating the input from a user can help ensure that data is not used in a way you do not expect. We have provided some simple regular expressions as hints.<br><br>
    Delimiters marking the beginning and end of the pattern are required! They are tildes (~) in the examples below.<br><br>
	<div class="smalltext" style="margin: 0 2em">
		&quot;~[A-Za-z]+~&quot; - Match all upper and lower case alphabet characters.<br>
		&quot;~[0-9]+~&quot; - Match all numeric characters.<br>
		&quot;~[A-Za-z0-9]{7}~&quot; - Match all upper and lower case alphabet and numeric characters seven times.<br>
		&quot;~[^0-9]?~&quot; - Forbid any number from being matched.<br>
		&quot;~^([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$~&quot; - Only allow 3 or 6 character hexcodes.<br>
	</div><br><br>
	Additionally, special metacharacters ?+*^$ and {xx} can be defined.
	<div class="smalltext" style="margin: 0 2em">
		? - None or one match of previous expression.<br>
		+ - One or more of previous expression.<br>
		* - None or more of previous expression.<br>
		{xx} - An exact number from previous expression.<br>
		{xx,} - An exact number or more from previous expression.<br>
		{,xx} - An exact number or less from previous expression.<br>
		{xx,yy} - An exact match between the two numbers from previous expression.<br>
		^ - Start of string.<br>
		$ - End of string.<br>
		\ - Escapes the next character.<br>
	</div><br><br>
	More information and advanced techniques may be found on the Internet.';

$helptxt['topic_move_any'] = 'If checked, users will be allowed to move topics to any board they can see. Otherwise, they will only be able to move them to boards where they can post new topics.';

$helptxt['alert_pm_new'] = 'Notifications of new personal messages do not appear in the Alerts pane, but appear in the "My Messages" list instead.';
$helptxt['alert_event_new'] = 'This will send out an alert or email as requested if there is a new calendar event added. However, if that event is posted and a topic is added, you will not get an alert for the event if you\'re already following that board - the alert from following the board would cover this.';

$helptxt['force_ssl'] = '<strong>Test SSL and HTTPS on your server properly before enabling this, it may cause your forum to become inaccessible.</strong> Enable maintenance mode if you are unable to access the forum after enabling this.<br><br><strong>Changing this setting will update your forum\'s primary URL, as well as the URLs for your theme files and images, smileys, and avatars, setting them to either http: or https: based on your selection. Customized URLs will not be affected.</strong>';
$helptxt['image_proxy_enabled'] = 'Required for embedding external images when in full SSL';
$helptxt['image_proxy_secret'] = 'Keep this a secret, protects your forum from hotlinking images. Change it in order to render current hotlinked images useless';
$helptxt['image_proxy_maxsize'] = 'Maximum image size that the SSL image proxy will cache: bigger images will be not be cached. Cached images are stored in your SMF cache folder, so make sure you have enough free space.';

$helptxt['enable_sm_stats'] = 'If enabled, this will allow Simple Machines to visit your site once a month to collect basic statistics. This will help us make decisions as to which configurations to optimize the software for. For more information please visit our <a href="https://www.simplemachines.org/about/stats.php" target="_blank" rel="noopener">info page</a>.';

$helptxt['field_reg_require'] = 'If this field is required during registration, it will also be required on profile changes.';

$helptxt['cron_is_real_cron'] = 'By default, SMF adds to every page a small bit of JavaScript that prompts the user\'s browser to trigger SMF\'s cron.php in the background. This approach allows SMF to execute its scheduled tasks on a regular basis without requiring the admin to do any extra server configuration. However, some admins might prefer to configure their servers to directly execute cron.php on a regular basis. If you have done this, you can disable the JavaScript method.<br><br><strong>Note:</strong> Configuring your server to run scheduled tasks this way cannot be done from within SMF. You will need to use your server configuration software to do so. Typical tools for this purpose include <code>cron</code> (for Linux servers), <code>schtasks.exe</code> (for Windows servers), and <code>launchd</code> (for macOS servers). Most web hosting companies provide some sort of control panel interface to allow their customers to access these tools.<br><br>The path and URL for your forum\'s cron.php are:<br>Path: ' . $boarddir . '/cron.php<br>URL: ' . $boardurl . '/cron.php';

$helptxt['languages_txt'] = 'These language entries are used throughout SMF to define localized versions of text for different languages.<br><br>If you see a value like <code>&#37;1$s</code> in an entry, it is a placeholder that SMF fills in with a real value at runtime. Make sure to keep such placeholders in your customized text.';
$helptxt['languages_helptxt'] = 'These language entries are used to show floating help text popups (like this one).';
$helptxt['languages_editortxt'] = 'These language entries are used for user interface elements in the post editor.';
$helptxt['languages_tztxt'] = 'These language entries define custom descriptions for entire time zones.<br><br>Each of these language entries uses a specific location, such as <code>Africa/Khartoum</code> or <code>America/New_York</code>, to represent all locations that share its time zone, and then defines a custom description to use for that time zone.<br><br>If no description is set for a given time zone, SMF will generate one using a list of locations within that time zone. If a location is set for a given time zone, but the description is empty, SMF will generate a description in the same way, but will make sure to put the defined location first in the list.';
$helptxt['languages_txt_for_timezones'] = 'These language entries allow you to define custom names for individual locations anywhere they might be used in a context related to time zones.<br><br>For example, you can tell SMF to show "Greenland" for the location "America/Godthab" instead of "Godthab".';
$helptxt['languages_native_name'] = 'The language\'s name for itself, represented in its own script.';
$helptxt['languages_character_set'] = 'The character encoding used for this language. As of SMF 2.1, this should always be "UTF-8".';
$helptxt['languages_locale'] = 'The locale code is used to determine various formatting conventions, etc.<br><br>The locale code typically takes the form of a two character language code, optionally followed by an underscore and a two character country code. For example, <code>en</code> would identify generic English, while <code>en_AU</code> would identify Australian English in particular.';
$helptxt['languages_rtl'] = 'Check this box if this language reads from right to left (e.g. Hebrew, Arabic).';
$helptxt['languages_dictionary'] = 'This defines the main language dictionary that will be used by the <a href="https://php.net/function.pspell-new" target="_blank" rel="noopener">pSpell</a> spellchecker (if installed).';
$helptxt['languages_spelling'] = 'This defines the language variant that will be used by the <a href="https://php.net/function.pspell-new" target="_blank" rel="noopener">pSpell</a> spellchecker. For many languages this is not applicable.';
$helptxt['languages_recaptcha'] = 'The language code to use for the reCAPTCHA verification widget.<br><br>See Google\'s reCAPTCHA documentation for its <a href="https://developers.google.com/recaptcha/docs/language" target="_blank" class="bbc_link">list of supported languages</a>.';

?>