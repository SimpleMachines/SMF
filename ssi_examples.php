<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

// Special thanks to Spaceman-Spiff for his contributions to this page.

/* Define $ssi_guest_access variable just before including SSI.php to handle guest access to your script.
	false: (default) fallback to forum setting
	true: allow guest access to the script regardless
*/
$ssi_guest_access = false;

// Include the SSI file.
require(dirname(__FILE__) . '/SSI.php');

// Viewing the homepage sample?
if (isset($_GET['view']) && $_GET['view'] == 'home1')
{
	template_homepage_sample1('output');
	exit;
}

// Load the main template.
template_ssi_above();
?>
			<h2>SMF SSI.php Functions</h2>
			<p><strong>Current Version:</strong> <?php echo SMF_VERSION; ?></p>
			<p>This file is used to demonstrate the capabilities of SSI.php using PHP include functions. The examples show the include tag, then the results of it.</p>

			<h2>Include Code</h2>
			<p>To use SSI.php in your page add at the very top of your page before the &lt;html&gt; tag on line 1 of your php file:</p>
			<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php require(&quot;<?php echo addslashes($user_info['is_admin'] ? realpath($boarddir . '/SSI.php') : 'SSI.php'); ?>&quot;); ?&gt;</code>

			<h2>Some notes on usage</h2>
			<p>All the functions have an output method parameter.  This can either be &quot;echo&quot; (the default) or &quot;array&quot;</p>
			<p>If it is &quot;echo&quot;, the function will act normally - otherwise, it will return an array containing information about the requested task. For example, it might return a list of topics for ssi_recentTopics.</p>
			<p onclick="if (getInnerHTML(this).indexOf('Bird') == -1) setInnerHTML(this, getInnerHTML(this) + '<br><img src=&quot;https://www.simplemachines.org/images/chocobo.jpg&quot; title=&quot;Bird-san&quot; alt=&quot;Chocobo!&quot;>'); return false;">This functionality can be used to allow you to present the information in any way you wish.</p>

			<h2>Additional Guides &amp; FAQ</h2>
			<p>Need more information on using SSI.php? Check out <a href="https://wiki.simplemachines.org/smf/Category:SSI">Using SSI.php article</a>.</p>

			<div id="sidenav" class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<h2 id="functionlist">Function List</h2>
					<h3>Recent Items</h3>
					<ul>
						<li><a href="#" onclick="showSSIBlock('ssi_recentTopics'); return false;">Recent Topics</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_recentPosts'); return false;">Recent Posts</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_recentPoll'); return false;">Recent Poll</a></li>
					</ul>
					<h3>Top Items</h3>
					<ul>
						<li><a href="#" onclick="showSSIBlock('ssi_topBoards'); return false;">Top Boards</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_topTopicsViews'); return false;">Top Topics</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_topPoll'); return false;">Top Poll</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_topPoster'); return false;">Top Poster</a></li>
					</ul>
					<h3>Members</h3>
					<ul>
						<li><a href="#" onclick="showSSIBlock('ssi_latestMember'); return false;">Latest Member Function</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_randomMember'); return false;">Member of the Day</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_whosOnline'); return false;">Who's Online</a></li>
					</ul>
					<h3>Authentication</h3>
					<ul>
						<li><a href="#" onclick="showSSIBlock('ssi_login'); return false;">Welcome, Login &amp; Logout</a></li>
					</ul>
					<h3>Calendar</h3>
					<ul>
						<li><a href="#" onclick="showSSIBlock('ssi_todaysCalendar'); return false;">Today's Events</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_recentEvents'); return false;">Recent Events</a></li>
					</ul>
					<h3>Miscellaneous</h3>
					<ul>
						<li><a href="#" onclick="showSSIBlock('ssi_boardStats'); return false;">Forum Stats</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_news'); return false;">News</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_boardNews'); return false;">Board News</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_menubar'); return false;">Menubar</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_quickSearch'); return false;">Quick Search Box</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_recentAttachments'); return false;">Recent Attachments</a></li>
					</ul>
					<?php if ($user_info['is_admin']) { ?>
					<h3>Advanced Functions <img class="help" title="Functions that require additional tweaking, not just copy and paste." src="<?php echo $settings['images_url']; ?>/helptopics.png" alt=""></h3>
					<ul>
						<li><a href="#" onclick="showSSIBlock('ssi_showPoll'); return false;">Show Single Poll</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_fetchPosts'); return false;">Show Single Post</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_fetchMember'); return false;">Show Single Member</a></li>
						<li><a href="#" onclick="showSSIBlock('ssi_fetchGroupMembers'); return false;">Show Group Members</a></li>
					</ul>
					<?php } ?>
					<h3>Website Samples</h3>
					<ul>
						<li><a href="#" onclick="showSSIBlock('htmlhome')">Sample 1</a></li>
					</ul>
					<h2 id="other">Other</h2>
					<ul>
						<li><a href="#" onclick="toggleVisibleByClass('ssi_preview', false); return false;">Show all examples</a></li>
						<li><a href="#" onclick="toggleVisibleByClass('ssi_preview', true); return false;">Hide all examples</a></li>
					</ul>
				</div>
				<span class="botslice"><span></span></span>
			</div>

	<div id="preview" class="windowbg">
		<span class="topslice"><span></span></span>
		<div class="content">

<!-- RECENT ITEMS -->
			<div class="ssi_preview" id="ssi_recentTopics">
				<h2>Recent Topics Function</h2>
				<h3>Code (simple mode)</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_recentTopics(); ?&gt;</code>
				<h3>Code (advanced mode)</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_recentTopics($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo'); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_recentTopics(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_recentPosts">
				<h2>Recent Posts Function</h2>
				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_recentPosts(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_recentPosts(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_recentPoll">
				<h2>Recent Poll Function</h2>
				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_recentPoll(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_recentPoll(); flush(); ?></div>
			</div>

<!-- TOP ITEMS -->
			<div class="ssi_preview" id="ssi_topBoards">
				<h2>Top Boards Function</h2>
				<p>Shows top boards by the number of posts.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_topBoards(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_topBoards(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_topTopicsViews">
				<h2>Top Topics</h2>
				<p>Shows top topics by the number of replies or views.</p>

				<h3>Code (show by number of views)</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_topTopicsViews(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_topTopicsViews(); flush(); ?></div>

				<h3>Code (show by number of replies)</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_topTopicsReplies(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_topTopicsReplies(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_topPoll">
				<h2>Top Poll Function</h2>
				<p>Shows the most-voted-in poll.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_topPoll(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_topPoll(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_topPoster">
				<h2>Top Poster Function</h2>
				Shows the top poster's name and profile link.

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_topPoster(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_topPoster(); flush(); ?></div>
			</div>

<!-- MEMBERS -->
			<div class="ssi_preview" id="ssi_latestMember">
				<h2>Latest Member Function</h2>
				<p>Shows the latest member's name and profile link.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_latestMember(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_latestMember(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_randomMember">
				<h2>Member of the Day</h2>
				<p>Shows one random member of the day. This changes once a day.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_randomMember('day'); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_randomMember('day'); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_whosOnline">
				<h2>Who's Online Function</h2>
				<p>This function shows who are online inside the forum.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_whosOnline(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_whosOnline(); flush(); ?></div>

				<h2>Log Online Presence</h2>
				<p>This function logs the SSI page's visitor, then shows the Who's Online list. In other words, this function shows who are online inside and outside the forum.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_logOnline(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_logOnline(); flush(); ?></div>
			</div>

<!-- WELCOME, LOGIN AND LOGOUT -->
			<div class="ssi_preview" id="ssi_login">
				<h2>Login Function</h2>
				<p>Shows a login box only when user is not logged in.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_login(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_login(); flush(); ?></div>

				<h2>Logout Function</h2>
				<p>Shows a logout link only when user is logged in.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_logout(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_logout(); flush(); ?></div>

				<h2>Welcome Function</h2>
				<p>Greets users or guests, also shows user's messages if logged in.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_welcome(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_welcome(); flush(); ?></div>
			</div>

<!-- CALENDAR -->
			<div class="ssi_preview" id="ssi_todaysCalendar">
				<h2>Today's Calendar Function</h2>
				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_todaysCalendar(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_todaysCalendar(); flush(); ?></div>

				<h2>Today's Birthdays Function</h2>
				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_todaysBirthdays(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_todaysBirthdays(); flush(); ?></div>

				<h2>Today's Holidays Function</h2>
				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_todaysHolidays(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_todaysHolidays(); flush(); ?></div>

				<h2>Today's Events Function</h2>
				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_todaysEvents(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_todaysEvents(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_recentEvents">
				<h2>Recent Calendar Events Function</h2>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_recentEvents(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_recentEvents(); flush(); ?></div>
			</div>

<!-- MISCELLANEOUS -->
			<div class="ssi_preview" id="ssi_boardStats">
				<h2>Forum Stats</h2>
				<p>Shows some basic forum stats: total members, posts, topics, boards, etc.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_boardStats(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_boardStats(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_news">
				<h2>News Function</h2>
				<p>Shows random forum news.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_news(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_news(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_boardNews">
				<h2>Board News Function</h2>
				<p>Shows the latest posts from read only boards, or a specific board.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_boardNews(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_boardNews(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_menubar">
				<h2>Menubar Function</h2>
				<p>Displays a menu bar, like one displayed at the top of the forum.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_menubar(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_menubar(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_quickSearch">
				<h2>Quick Search Function</h2>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_quickSearch(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_quickSearch(); flush(); ?></div>
			</div>

			<div class="ssi_preview" id="ssi_recentAttachments">
				<h2>Recent Attachments Function</h2>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_recentAttachments(); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><?php ssi_recentAttachments(); flush(); ?></div>
			</div>

<!-- ADVANCED FUNCTIONS -->
			<div class="ssi_preview" id="ssi_showPoll">
				<h2>Show Single Poll</h2>
				<p>Shows a poll in the specified topic.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_showPoll($topicID); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><i>Not shown because it needs specific topic ID that contains a poll.</i></div>
			</div>

			<div class="ssi_preview" id="ssi_fetchPosts">
				<h2>Show Single Post</h2>
				<p>Fetches a post with a particular IDs. By default will only show if you have permission to the see the board in question. This can be overridden by passing the 2nd parameter as true.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_fetchPosts($postIDs, $isOverride); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><i>Not shown because it needs a specific post ID.</i></div>
			</div>

			<div class="ssi_preview" id="ssi_fetchMember">
				<h2>Show Single Member</h2>
				<p>Shows the specified member's name and profile link.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_fetchMember($memberIDs); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><i>Not shown because it needs a specific member ID.</i></div>
			</div>

			<div class="ssi_preview" id="ssi_fetchGroupMembers">
				<h2>Show Group Members</h2>
				<p>Shows all members in a specified group.</p>

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">[Select]</a></div><code class="bbc_code">&lt;?php ssi_fetchGroupMembers($groupIDs); ?&gt;</code>
				<h3>Result</h3>
				<div class="ssi_result"><i>Not shown because it needs specific membergroup IDs.</i></div>
			</div>

			<div class="ssi_preview" id="htmlhome">
				<h2>Home Page Sample</h2>
				This sample uses the following features: ssi_recentTopics(), ssi_logOnline(), ssi_welcome(), and ssi_boardNews().
				ssi_recentTopics() is fetched using the array method, to allow further customizations on the output.

				<h3>Code</h3>
				<div class="codeheader">Code: <a href="javascript:void(0);" onclick="return smfSelectText(this);" class="codeoperation">Select</a></div><code class="bbc_code"><?php echo htmlspecialchars(template_homepage_sample1('source')); ?></code>
				<h3>Result</h3>
				<iframe src="?view=home1" style="width:99%; height:300px;"></iframe>
			</div>
		</div>
		<span class="botslice"><span></span></span>
	</div>

<?php

template_ssi_below();

function template_ssi_above()
{
	global $settings, $context, $scripturl;

	echo '<!DOCTYPE html>
<html>
	<head>
		<title>', SMF_FULL_VERSION, ' SSI.php Examples</title>
		<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/index.css">
		<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<style>
			#wrapper {
				width: 90%;
			}
			#upper_section .user {
				height: 4em;
			}
			#upper_section .news {
				height: 80px;
			}
			#content_section {
				position: relative;
			}
			#main_content_section h2 {
				font-size: 1.5em;
				border-bottom: solid 1px #d05800;
				line-height: 1.5em;
				margin: 0.5em 0;
				color: #d05800;
			}
			#liftup {
				top: -70px;
				padding: 1em 2em 1em 1em;
				line-height: 1.6em;
			}
			#footer {
				position: relative;
			}
			#sidenav {
				width: 210px;
				float: left;
				margin-right: 20px;
			}
			#sidenav ul {
				margin: 0 0 0 15px;
				padding: 0;
				list-style: none;
				font-size: 90%;
			}
			#preview {
				margin-left: 230px;
			}
			.ssi_preview {
				margin-bottom: 1.5em;
			}
			.ssi_preview h3 {
				margin: 1em 0 0.5em 0;
			}
			.ssi_result {
				background-color: #fff;
				border: 1px solid #99a;
				padding: 10px;
				overflow: hidden;
			}
		</style>
		<script>
			var smf_scripturl = "', $scripturl, '";
			var smf_iso_case_folding = ', $context['server']['iso_case_folding'] ? 'true' : 'false', ';
			var smf_charset = "', $context['character_set'], '";

			// Sets all ssi_preview class to hidden, then shows the one requested.
			function showSSIBlock(elementID)
			{
				toggleVisibleByClass("ssi_preview", true);
				document.getElementById(elementID).style.display = "block";
			}

			// Toggle visibility of all sections.
			function toggleVisibleByClass(sClassName, bHide)
			{
				var oSections = document.getElementsByTagName("div");
				for (var i = 0; i < oSections.length; i++)
				{
					if (oSections[i].className === null || oSections[i].className.indexOf(sClassName) == -1)
						continue;

					oSections[i].style.display = bHide ? "none" : "block";
				}
			}
		</script>
	</head>
	<body>
		<div id="header">
			<h1 class="forumtitle">', SMF_FULL_VERSION, ' SSI.php Examples</h1>
			<img id="smflogo" src="Themes/default/images/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">
		</div>
		<div id="wrapper">
			<div id="upper_section"><p><br></p></div>
			<div id="content_section">
				<div id="main_content_section">
					<div id="liftup" class="flow_auto">';
}

function template_ssi_below()
{
	echo '
						<script>
							showSSIBlock("ssi_recentTopics");
						</script>
					</div>
				</div>
			</div>
		</div>
			<div id="footer">
				<a href="#header" id="bot" class="go_up"></a>
				<ul>
					<li class="copyright">
						<span class="smalltext">', ssi_copyright(), '</span>
					</li>
				</ul>
			</div>
	</body>
</html>';
}

function template_homepage_sample1($method = 'source')
{
	global $user_info, $boarddir;

	$header = '<!DOCTYPE html>
<html>
<head>
	<title>SSI.php example for home page</title>
	<style>
		body { font-family: Arial, Tahoma, sans-serif; font-size: 80%; background: #DFDFDF; color: #FFFFFF; margin: 0 }
		ul,ol { padding-left: 19px; margin: 0; }
		li { font-size: 11px; }
		h1,h2,h3 { margin: 0; padding: 0; }
		h3 { font-size: 15px; }
		a:link,a:visited { color: #FF9000; text-decoration: none; }
		a:hover { text-decoration: underline; }
		#container { background: #52514E; width: 100%; border: 1px solid midnightblue; line-height: 150%; margin: 0; }
		#header,#footer { color: lightgray; background-color: #2A2825; clear: both; padding: .5em; }
		#leftbar { background: #DF7E00; float: left; width: 160px; margin: 0; padding: 1em; }
		#leftbar a { color: #000000; text-decoration: underline; }
		#content { margin-left: 190px; padding: 1em; }
		#navigation { float: right; }
		#navigation a:link,#navigation a:visited { color: #FF9000; }
	</style>
</head>
<body>
<div id="container">
	<div id="header">
		<div id="navigation">
			<a href="#">Link</a> | <a href="#">Link</a> | <a href="#">Link</a> | <a href="#">Link</a> | <a href="#">Link</a>
		</div>
		<h1 class="header">YourWebsite.com</h1>
	</div>
	<div id="leftbar">
		<h3>Recent Forum Topics</h3>
		<ul>';

	$footer = '
	<div id="footer">
		<a target="_blank" rel="noopener" rel="license" href="https://creativecommons.org/licenses/publicdomain/"><img alt="Creative Commons License" style="border-width:0" src="https://i.creativecommons.org/l/publicdomain/88x31.png"></a>
		This sample website layout is dedicated to the <a target="_blank" rel="noopener" rel="license" href="https://creativecommons.org/licenses/publicdomain/">Public Domain</a>.
	</div>
</div>
</body>
</html>';

	if ($method == 'source')
	{
		$header = '<?php require("' . ($user_info['is_admin'] ? addslashes(realpath($boarddir . '/SSI.php')) : 'SSI.php') . '"); ?>' . "\n" . $header;
		return $header . template_homepage_sample1_html() . $footer;
	}
	else
	{
		echo $header;
		template_homepage_sample1_php();
		echo $footer;
	}

}

function template_homepage_sample1_php()
{
	global $txt;

	$topics = ssi_recentTopics(8, null, null, 'array');

	foreach ($topics as $topic)
		echo '
			<li><a href="', $topic['href'], '">', $topic['subject'], '</a> ', $txt['by'], ' ', $topic['poster']['link'], '</li>';

	unset($topics);

	echo '

		</ul><br>

		<h3>Online Users</h3>';
		ssi_logOnline();

	echo '
	</div>

	<div id="content">';

		ssi_welcome();
	echo '
		<br><br>

		<h2>News</h2>';

		ssi_boardNews();

	echo '
	</div>';

}

function template_homepage_sample1_html()
{
	$result = '
<?php
// Using array method to show shorter display style.
$topics = ssi_recentTopics(8, null, null, \'array\');

foreach ($topics as $topic)
{
	// Uncomment the following code to get a listing of array elements that SMF provides for this function.
	// echo \'<pre>\', print_r($topic), \'</pre>\';

	echo \'
			<li><a href=\"\', $topic[\'href\'], \'\">\', $topic[\'subject\'], \'</a> \', $txt[\'by\'], \' \', $topics[$i][\'poster\'][\'link\'], \'</li>\';
}

unset($topics);
?>
		</ul><br>
		<h3>Online Users</h3>
		<?php ssi_logOnline(); ?>
	</div>
	<div id="content">
		<?php ssi_welcome(); ?><br><br>
		<h2>News</h2>
		<?php ssi_boardNews(); ?>
	</div>';

	return $result;
}

?>