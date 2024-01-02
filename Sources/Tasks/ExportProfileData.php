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

namespace SMF\Tasks;

use SMF\Actions\Feed;
use SMF\Actions\Profile\Export;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\TaskRunner;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * @todo Find a way to throttle the export rate dynamically when dealing with
 * truly enormous amounts of data. Specifically, if the dataset contains lots
 * of posts that are ridiculously large, one or another part of the system
 * might choke.
 */

/**
 * This class contains code used to incrementally export a member's profile data
 * to one or more downloadable files.
 */
class ExportProfileData extends BackgroundTask
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * An array of XML namespaces.
	 *
	 * Do NOT change any of these to HTTPS addresses! Not even the SMF one.
	 *
	 * Why? Because XML namespace names must be both unique and invariant
	 * once defined. They look like URLs merely because that's a convenient
	 * way to ensure uniqueness, but they are not used as URLs. They are
	 * used as case-sensitive identifier strings. If the string changes in
	 * any way, XML processing software (including PHP's own XML functions)
	 * will interpret the two versions of the string as entirely different
	 * namespaces, which could cause it to mangle the XML horrifically
	 * during processing.
	 *
	 * These strings have been broken up and concatenated to help prevent any
	 * automatic search and replace attempts from changing them.
	 */
	public const XML_NAMESPACES = [
		'smf' => 'htt' . 'p:/' . '/ww' . 'w.simple' . 'machines.o' . 'rg/xml/profile',
		'xsl' => 'htt' . 'p:/' . '/ww' . 'w.w3.o' . 'rg/1999/XSL/Transform',
		'html' => 'htt' . 'p:/' . '/ww' . 'w.w3.o' . 'rg/1999/xhtml',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Info to create a follow-up background task, if necessary.
	 */
	private array $next_task = [];

	/**
	 * @var int Used to ensure we exit long running tasks cleanly.
	 */
	private int $time_limit = 30;

	/**
	 * @var array The XSLT stylesheet, broken up into logical parts.
	 */
	private array $xslt_stylesheet = [
		// Header for the stylesheet. Default value assumes that the stylesheet
		// will be a separate file. This will be changed at runtime if the
		// output format is set to XML_XSLT.
		'header' => '<?xml version="1.0" encoding="UTF-8"?' . '>' . "\n" . '<xsl:stylesheet version="1.0" xmlns:xsl="' . self::XML_NAMESPACES['xsl'] . '" xmlns:html="' . self::XML_NAMESPACES['html'] . '" xmlns:smf="' . self::XML_NAMESPACES['smf'] . '" exclude-result-prefixes="smf html">',

		// Controls output formatting and handline of special characters.
		// Do not change this.
		'output_control' => <<<'END'
				<xsl:output method="html" encoding="utf-8" indent="yes"/>
				<xsl:strip-space elements="*"/>
			END,

		// XSLT variables. This is set at runtime.
		'variables' => '',

		// The top-level template. Creates the shell of the HTML document.
		'html' => <<<'END'
				<xsl:template match="/*">
					<xsl:text disable-output-escaping="yes">&lt;!DOCTYPE html&gt;</xsl:text>
					<html>
						<head>
							<title>
								<xsl:value-of select="@title"/>
							</title>
							<xsl:call-template name="css_js"/>
						</head>
						<body>
							<div id="footerfix">
								<div id="header">
									<h1 class="forumtitle">
										<a id="top">
											<xsl:attribute name="href">
												<xsl:value-of select="$scripturl"/>
											</xsl:attribute>
											<xsl:value-of select="@forum-name"/>
										</a>
									</h1>
								</div>
								<div id="wrapper">
									<div id="upper_section">
										<div id="inner_section">
											<div id="inner_wrap">
												<div class="user">
													<time>
														<xsl:attribute name="datetime">
															<xsl:value-of select="@generated-date-UTC"/>
														</xsl:attribute>
														<xsl:value-of select="@generated-date-localized"/>
													</time>
												</div>
												<hr class="clear"/>
											</div>
										</div>
									</div>

									<xsl:call-template name="content_section"/>

								</div>
							</div>
							<div id="footer">
								<div class="inner_wrap">
									<ul>
										<li class="floatright">
											<a>
												<xsl:attribute name="href">
													<xsl:value-of select="concat($scripturl, '?action=help')"/>
												</xsl:attribute>
												<xsl:value-of select="$txt_help"/>
											</a>
											<xsl:text> | </xsl:text>
											<a>
												<xsl:attribute name="href">
													<xsl:value-of select="concat($scripturl, '?action=help;sa=rules')"/>
												</xsl:attribute>
												<xsl:value-of select="$txt_terms_rules"/>
											</a>
											<xsl:text> | </xsl:text>
											<a href="#top">
												<xsl:value-of select="$txt_go_up"/>
												<xsl:text> &#9650;</xsl:text>
											</a>
										</li>
										<li class="copyright">
											<xsl:value-of select="$forum_copyright" disable-output-escaping="yes"/>
										</li>
									</ul>
								</div>
							</div>
						</body>
					</html>
				</xsl:template>
			END,

		// Template to show the content of the export file.
		'content_section' => <<<'END'
				<xsl:template name="content_section">
					<div id="content_section">
						<div id="main_content_section">

							<div class="cat_bar">
								<h3 class="catbg">
									<xsl:value-of select="@title"/>
								</h3>
							</div>
							<div class="information">
								<h2 class="display_title">
									<xsl:value-of select="@description"/>
								</h2>
							</div>

							<xsl:if test="username">
								<div class="cat_bar">
									<h3 class="catbg">
										<xsl:value-of select="$txt_summary_heading"/>
									</h3>
								</div>
								<div id="profileview" class="roundframe flow_auto noup">
									<xsl:call-template name="summary"/>
								</div>
							</xsl:if>

							<xsl:call-template name="page_index"/>

							<xsl:if test="member_post">
								<div class="cat_bar">
									<h3 class="catbg">
										<xsl:value-of select="$txt_posts_heading"/>
									</h3>
								</div>
								<div id="posts" class="roundframe flow_auto noup">
									<xsl:apply-templates select="member_post" mode="posts"/>
								</div>
							</xsl:if>

							<xsl:if test="personal_message">
								<div class="cat_bar">
									<h3 class="catbg">
										<xsl:value-of select="$txt_personal_messages_heading"/>
									</h3>
								</div>
								<div id="personal_messages" class="roundframe flow_auto noup">
									<xsl:apply-templates select="personal_message" mode="pms"/>
								</div>
							</xsl:if>

							<xsl:call-template name="page_index"/>

						</div>
					</div>
				</xsl:template>
			END,

		// Template for user profile summary.
		'summary' => <<<'END'
				<xsl:template name="summary">
					<div id="basicinfo">
						<div class="username clear">
							<h4>
								<a>
									<xsl:attribute name="href">
										<xsl:value-of select="link"/>
									</xsl:attribute>
									<xsl:value-of select="name"/>
								</a>
								<xsl:text> </xsl:text>
								<span class="position">
									<xsl:choose>
										<xsl:when test="position">
											<xsl:value-of select="position"/>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="post_group"/>
										</xsl:otherwise>
									</xsl:choose>
								</span>
							</h4>
						</div>
						<img class="avatar">
							<xsl:attribute name="src">
								<xsl:value-of select="avatar"/>
							</xsl:attribute>
						</img>
					</div>

					<div id="detailedinfo">
						<dl class="settings noborder">
							<xsl:apply-templates mode="detailedinfo"/>
						</dl>
					</div>
				</xsl:template>
			END,

		// Some helper templates for details inside the summary.
		'detail_default' => <<<'END'
				<xsl:template match="*" mode="detailedinfo">
					<dt>
						<xsl:value-of select="concat(@label, ':')"/>
					</dt>
					<dd>
						<xsl:value-of select="." disable-output-escaping="yes"/>
					</dd>
				</xsl:template>
			END,

		'detail_email' => <<<'END'
				<xsl:template match="email" mode="detailedinfo">
					<dt>
						<xsl:value-of select="concat(@label, ':')"/>
					</dt>
					<dd>
						<a>
							<xsl:attribute name="href">
								<xsl:text>mailto:</xsl:text>
								<xsl:value-of select="."/>
							</xsl:attribute>
							<xsl:value-of select="."/>
						</a>
					</dd>
				</xsl:template>
			END,

		'detail_website' => <<<'END'
				<xsl:template match="website" mode="detailedinfo">
					<dt>
						<xsl:value-of select="concat(@label, ':')"/>
					</dt>
					<dd>
						<a>
							<xsl:attribute name="href">
								<xsl:value-of select="link"/>
							</xsl:attribute>
							<xsl:value-of select="title"/>
						</a>
					</dd>
				</xsl:template>
			END,

		'detail_ip' => <<<'END'
				<xsl:template match="ip_addresses" mode="detailedinfo">
					<dt>
						<xsl:value-of select="concat(@label, ':')"/>
					</dt>
					<dd>
						<ul class="nolist">
							<xsl:apply-templates mode="ip_address"/>
						</ul>
					</dd>
				</xsl:template>
				<xsl:template match="*" mode="ip_address">
					<li>
						<xsl:value-of select="."/>
						<xsl:if test="@label and following-sibling">
							<xsl:text> </xsl:text>
							<span>(<xsl:value-of select="@label"/>)</span>
						</xsl:if>
					</li>
				</xsl:template>
			END,

		'detail_not_included' => <<<'END'
				<xsl:template match="name|link|avatar|online|member_post|personal_message" mode="detailedinfo"/>
			END,

		// Template for printing a single post.
		'member_post' => <<<'END'
				<xsl:template match="member_post" mode="posts">
					<div>
						<xsl:attribute name="id">
							<xsl:value-of select="concat('member_post_', id)"/>
						</xsl:attribute>
						<xsl:attribute name="class">
							<xsl:choose>
								<xsl:when test="approval_status = 1">
									<xsl:text>windowbg</xsl:text>
								</xsl:when>
								<xsl:otherwise>
									<xsl:text>approvebg</xsl:text>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:attribute>

						<div class="post_wrapper">
							<div class="poster">
								<h4>
									<a>
										<xsl:attribute name="href">
											<xsl:value-of select="poster/link"/>
										</xsl:attribute>
										<xsl:value-of select="poster/name"/>
									</a>
								</h4>
								<ul class="user_info">
									<xsl:if test="poster/id = $member_id">
										<xsl:call-template name="own_user_info"/>
									</xsl:if>
									<li>
										<xsl:value-of select="poster/email"/>
									</li>
									<li class="poster_ip">
										<xsl:value-of select="concat(poster/ip/@label, ': ')"/>
										<xsl:value-of select="poster/ip"/>
									</li>
								</ul>
							</div>

							<div class="postarea">
								<div class="flow_hidden">

									<div class="keyinfo">
										<h5>
											<strong>
												<a>
													<xsl:attribute name="href">
														<xsl:value-of select="board/link"/>
													</xsl:attribute>
													<xsl:value-of select="board/name"/>
												</a>
												<xsl:text> / </xsl:text>
												<a>
													<xsl:attribute name="href">
														<xsl:value-of select="link"/>
													</xsl:attribute>
													<xsl:value-of select="subject"/>
												</a>
											</strong>
										</h5>
										<span class="smalltext"><xsl:value-of select="time"/></span>
										<xsl:if test="modified_time">
											<span class="smalltext modified floatright mvisible em">
												<xsl:attribute name="id">
													<xsl:value-of select="concat('modified_', id)"/>
												</xsl:attribute>
												<span class="lastedit">
													<xsl:value-of select="modified_time/@label"/>
												</span>
												<xsl:text>: </xsl:text>
												<xsl:value-of select="modified_time"/>
												<xsl:text>. </xsl:text>
												<xsl:value-of select="modified_by/@label"/>
												<xsl:text>: </xsl:text>
												<xsl:value-of select="modified_by"/>
												<xsl:text>. </xsl:text>
											</span>
										</xsl:if>
									</div>

									<div class="post">
										<div class="inner">
											<xsl:value-of select="body_html" disable-output-escaping="yes"/>
										</div>
										<div class="inner monospace" style="display:none;">
											<xsl:choose>
												<xsl:when test="contains(body/text(), '[html]')">
													<xsl:call-template name="bbc_html_splitter">
														<xsl:with-param name="bbc_string" select="body/text()"/>
													</xsl:call-template>
												</xsl:when>
												<xsl:otherwise>
													<xsl:value-of select="body" disable-output-escaping="yes"/>
												</xsl:otherwise>
											</xsl:choose>
										</div>
									</div>

									<xsl:apply-templates select="attachments">
										<xsl:with-param name="post_id" select="id"/>
									</xsl:apply-templates>

									<div class="under_message">
										<ul class="floatleft">
											<xsl:if test="likes > 0">
												<li class="smflikebutton">
													<xsl:attribute name="id">
														<xsl:value-of select="concat('msg_', id, '_likes')"/>
													</xsl:attribute>
													<span><span class="main_icons like"></span> <xsl:value-of select="likes"/></span>
												</li>
											</xsl:if>
										</ul>
										<xsl:call-template name="quickbuttons">
											<xsl:with-param name="toggle_target" select="concat('member_post_', id)"/>
										</xsl:call-template>
									</div>

								</div>
							</div>

							<div class="moderatorbar">
								<xsl:if test="poster/id = $member_id">
									<xsl:call-template name="signature"/>
								</xsl:if>
							</div>

						</div>
					</div>
				</xsl:template>
			END,

		// Template for printing a single PM.
		'personal_message' => <<<'END'
				<xsl:template match="personal_message" mode="pms">
					<div class="windowbg">
						<xsl:attribute name="id">
							<xsl:value-of select="concat('personal_message_', id)"/>
						</xsl:attribute>

						<div class="post_wrapper">
							<div class="poster">
								<h4>
									<a>
										<xsl:attribute name="href">
											<xsl:value-of select="sender/link"/>
										</xsl:attribute>
										<xsl:value-of select="sender/name"/>
									</a>
								</h4>
								<ul class="user_info">
									<xsl:if test="sender/id = $member_id">
										<xsl:call-template name="own_user_info"/>
									</xsl:if>
								</ul>
							</div>

							<div class="postarea">
								<div class="flow_hidden">

									<div class="keyinfo">
										<h5>
											<xsl:attribute name="id">
												<xsl:value-of select="concat('subject_', id)"/>
											</xsl:attribute>
											<xsl:value-of select="subject"/>
										</h5>
										<span class="smalltext">
											<strong>
												<xsl:value-of select="concat(recipient[1]/@label, ': ')"/>
											</strong>
											<xsl:apply-templates select="recipient"/>
										</span>
										<br/>
										<span class="smalltext">
											<strong>
												<xsl:value-of select="concat(sent_date/@label, ': ')"/>
											</strong>
											<time>
												<xsl:attribute name="datetime">
													<xsl:value-of select="sent_date/@UTC"/>
												</xsl:attribute>
												<xsl:value-of select="normalize-space(sent_date)"/>
											</time>
										</span>
									</div>

									<div class="post">
										<div class="inner">
											<xsl:value-of select="body_html" disable-output-escaping="yes"/>
										</div>
										<div class="inner monospace" style="display:none;">
											<xsl:call-template name="bbc_html_splitter">
												<xsl:with-param name="bbc_string" select="body/text()"/>
											</xsl:call-template>
										</div>
									</div>

									<div class="under_message">
										<xsl:call-template name="quickbuttons">
											<xsl:with-param name="toggle_target" select="concat('personal_message_', id)"/>
										</xsl:call-template>
									</div>

								</div>
							</div>

							<div class="moderatorbar">
								<xsl:if test="sender/id = $member_id">
									<xsl:call-template name="signature"/>
								</xsl:if>
							</div>

						</div>
					</div>
				</xsl:template>
			END,

		// A couple of templates to handle attachments.
		'attachments' => <<<'END'
				<xsl:template match="attachments">
					<xsl:param name="post_id"/>
					<xsl:if test="attachment">
						<div class="attachments">
							<xsl:attribute name="id">
								<xsl:value-of select="concat('msg_', $post_id, '_footer')"/>
							</xsl:attribute>
							<xsl:apply-templates/>
						</div>
					</xsl:if>
				</xsl:template>
				<xsl:template match="attachment">
					<div class="attached">
						<div class="attachments_bot">
							<a>
								<xsl:attribute name="href">
									<xsl:value-of select="concat(id, ' - ', name)"/>
								</xsl:attribute>
								<img class="centericon" alt="*">
									<xsl:attribute name="src">
										<xsl:value-of select="concat($themeurl, '/images/icons/clip.png')"/>
									</xsl:attribute>
								</img>
								<xsl:text> </xsl:text>
								<xsl:value-of select="name"/>
							</a>
							<br/>
							<xsl:text>(</xsl:text>
							<a class="bbc_link">
								<xsl:attribute name="href">
									<xsl:value-of select="concat($scripturl, '?action=profile;area=dlattach;u=', $member_id, ';attach=', id, ';t=', $dltoken)"/>
								</xsl:attribute>
								<xsl:value-of select="$txt_download_original"/>
							</a>
							<xsl:text>)</xsl:text>
							<br/>
							<xsl:value-of select="size/@label"/>
							<xsl:text>: </xsl:text>
							<xsl:value-of select="size"/>
							<br/>
							<xsl:value-of select="downloads/@label"/>
							<xsl:text>: </xsl:text>
							<xsl:value-of select="downloads"/>
						</div>
					</div>
				</xsl:template>
			END,

		// Helper template for printing the user's own info next to the post or personal message.
		'own_user_info' => <<<'END'
				<xsl:template name="own_user_info">
					<xsl:if test="/*/avatar">
						<li class="avatar">
							<a>
								<xsl:attribute name="href">
									<xsl:value-of select="/*/link"/>
								</xsl:attribute>
								<img class="avatar">
									<xsl:attribute name="src">
										<xsl:value-of select="/*/avatar"/>
									</xsl:attribute>
								</img>
							</a>
						</li>
					</xsl:if>
					<li class="membergroup">
						<xsl:value-of select="/*/position"/>
					</li>
					<xsl:if test="/*/title">
						<li class="title">
							<xsl:value-of select="/*/title"/>
						</li>
					</xsl:if>
					<li class="postgroup">
						<xsl:value-of select="/*/post_group"/>
					</li>
					<li class="postcount">
						<xsl:value-of select="concat(/*/posts/@label, ': ')"/>
						<xsl:value-of select="/*/posts"/>
					</li>
					<xsl:if test="/*/blurb">
						<li class="blurb">
							<xsl:value-of select="/*/blurb"/>
						</li>
					</xsl:if>
				</xsl:template>
			END,

		// Helper template for printing the quickbuttons.
		'quickbuttons' => <<<'END'
				<xsl:template name="quickbuttons">
					<xsl:param name="toggle_target"/>
					<ul class="quickbuttons quickbuttons_post sf-js-enabled sf-arrows" style="touch-action: pan-y;">
						<li>
							<a>
								<xsl:attribute name="onclick">
									<xsl:text>$('#</xsl:text>
									<xsl:value-of select="$toggle_target"/>
									<xsl:text> .inner').toggle();</xsl:text>
								</xsl:attribute>
								<xsl:value-of select="$txt_view_source_button"/>
							</a>
						</li>
					</ul>
				</xsl:template>
			END,

		// Helper template for printing a signature/
		'signature' => <<<'END'
				<xsl:template name="signature">
					<xsl:if test="/*/signature">
						<div class="signature">
							<xsl:value-of select="/*/signature" disable-output-escaping="yes"/>
						</div>
					</xsl:if>
				</xsl:template>
			END,

		// Helper template for printing a list of PM recipients.
		'recipient' => <<<'END'
				<xsl:template match="recipient">
					<a>
						<xsl:attribute name="href">
							<xsl:value-of select="link"/>
						</xsl:attribute>
						<xsl:value-of select="name"/>
					</a>
					<xsl:choose>
						<xsl:when test="following-sibling::recipient">
							<xsl:text>, </xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text>. </xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:template>
			END,

		// Helper template for special handling of the contents of the [html] BBCode.
		'bbc_html' => <<<'END'
				<xsl:template name="bbc_html_splitter">
					<xsl:param name="bbc_string"/>
					<xsl:param name="inside_outside" select="outside"/>
					<xsl:choose>
						<xsl:when test="$inside_outside = 'outside'">
							<xsl:choose>
								<xsl:when test="contains($bbc_string, '[html]')">
									<xsl:variable name="following_string">
										<xsl:value-of select="substring-after($bbc_string, '[html]')" disable-output-escaping="yes"/>
									</xsl:variable>
									<xsl:value-of select="substring-before($bbc_string, '[html]')" disable-output-escaping="yes"/>
									<xsl:text>[html]</xsl:text>
									<xsl:call-template name="bbc_html_splitter">
										<xsl:with-param name="bbc_string" select="$following_string"/>
										<xsl:with-param name="inside_outside" select="inside"/>
									</xsl:call-template>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="$bbc_string" disable-output-escaping="yes"/>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>
						<xsl:otherwise>
							<xsl:choose>
								<xsl:when test="contains($bbc_string, '[/html]')">
									<xsl:variable name="following_string">
										<xsl:value-of select="substring-after($bbc_string, '[/html]')" disable-output-escaping="yes"/>
									</xsl:variable>
									<xsl:value-of select="substring-before($bbc_string, '[/html]')" disable-output-escaping="no"/>
									<xsl:text>[/html]</xsl:text>
									<xsl:call-template name="bbc_html_splitter">
										<xsl:with-param name="bbc_string" select="$following_string"/>
										<xsl:with-param name="inside_outside" select="outside"/>
									</xsl:call-template>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="$bbc_string" disable-output-escaping="no"/>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:template>
			END,

		// Helper templates to build a page index.
		'page_index' => <<<'END'
				<xsl:template name="page_index">
					<xsl:variable name="current_page" select="/*/@page"/>
					<xsl:variable name="prev_page" select="/*/@page - 1"/>
					<xsl:variable name="next_page" select="/*/@page + 1"/>

					<div class="pagesection">
						<div class="pagelinks floatleft">

							<span class="pages">
								<xsl:value-of select="$txt_pages"/>
							</span>

							<xsl:if test="$current_page &gt; 1">
								<a class="nav_page">
									<xsl:attribute name="href">
										<xsl:value-of select="concat($dlfilename, '_', $prev_page, '.', $ext)"/>
									</xsl:attribute>
									<span class="main_icons previous_page"></span>
								</a>
							</xsl:if>

							<xsl:call-template name="page_links"/>

							<xsl:if test="$current_page &lt; $last_page">
								<a class="nav_page">
									<xsl:attribute name="href">
										<xsl:value-of select="concat($dlfilename, '_', $next_page, '.', $ext)"/>
									</xsl:attribute>
									<span class="main_icons next_page"></span>
								</a>
							</xsl:if>
						</div>
					</div>
				</xsl:template>

				<xsl:template name="page_links">
					<xsl:param name="page_num" select="1"/>
					<xsl:variable name="current_page" select="/*/@page"/>
					<xsl:variable name="prev_page" select="/*/@page - 1"/>
					<xsl:variable name="next_page" select="/*/@page + 1"/>

					<xsl:choose>
						<xsl:when test="$page_num = $current_page">
							<span class="current_page">
								<xsl:value-of select="$page_num"/>
							</span>
						</xsl:when>
						<xsl:when test="$page_num = 1 or $page_num = ($current_page - 1) or $page_num = ($current_page + 1) or $page_num = $last_page">
							<a class="nav_page">
								<xsl:attribute name="href">
									<xsl:value-of select="concat($dlfilename, '_', $page_num, '.', $ext)"/>
								</xsl:attribute>
								<xsl:value-of select="$page_num"/>
							</a>
						</xsl:when>
						<xsl:when test="$page_num = 2 or $page_num = ($current_page + 2)">
							<span class="expand_pages" onclick="$('.nav_page').removeClass('hidden'); $('.expand_pages').hide();"> ... </span>
							<a class="nav_page hidden">
								<xsl:attribute name="href">
									<xsl:value-of select="concat($dlfilename, '_', $page_num, '.', $ext)"/>
								</xsl:attribute>
								<xsl:value-of select="$page_num"/>
							</a>
						</xsl:when>
						<xsl:otherwise>
							<a class="nav_page hidden">
								<xsl:attribute name="href">
									<xsl:value-of select="concat($dlfilename, '_', $page_num, '.', $ext)"/>
								</xsl:attribute>
								<xsl:value-of select="$page_num"/>
							</a>
						</xsl:otherwise>
					</xsl:choose>

					<xsl:text> </xsl:text>

					<xsl:if test="$page_num &lt; $last_page">
						<xsl:call-template name="page_links">
							<xsl:with-param name="page_num" select="$page_num + 1"/>
						</xsl:call-template>
					</xsl:if>
				</xsl:template>
			END,

		// Template to insert CSS and JavaScript. This is set at runtime.
		'css_js' => '',

		// End of the XSLT stylesheet.
		'footer' => '</xsl:stylesheet>',
	];

	/**
	 * @var string The XSLT stylesheet as a single string.
	 */
	private string $stylesheet;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array Temporary backup of the Config::$modSettings array
	 */
	private static $real_modSettings = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * This is the main dispatcher for the class.
	 * It calls the correct private function based on the information stored in
	 * the task details.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		if (!defined('EXPORTING')) {
			define('EXPORTING', 1);
		}

		// Avoid leaving files in an inconsistent state.
		ignore_user_abort(true);

		$this->time_limit = (ini_get('safe_mode') === false && @set_time_limit(Taskrunner::MAX_CLAIM_THRESHOLD) !== false) ? Taskrunner::MAX_CLAIM_THRESHOLD : ini_get('max_execution_time');

		// This could happen if the user manually changed the URL params of the export request.
		if ($this->_details['format'] == 'HTML' && (!class_exists('DOMDocument') || !class_exists('XSLTProcessor'))) {
			$export_formats = Export::getFormats();

			$this->_details['format'] = 'XML_XSLT';
			$this->_details['format_settings'] = $export_formats['XML_XSLT'];
		}

		// TaskRunner class doesn't create a User::$me, but this job needs one.
		User::load($this->_details['uid'], User::LOAD_BY_ID, 'profile');
		User::setMe($this->_details['uid']);

		// For exports only, members can always see their own posts, even in boards that they can no longer access.
		User::$me->buddies = [];
		User::$me->query_see_board = '1=1';
		User::$me->query_see_message_board = '1=1';
		User::$me->query_see_topic_board = '1=1';
		User::$me->query_wanna_see_board = '1=1';
		User::$me->query_wanna_see_message_board = '1=1';
		User::$me->query_wanna_see_topic_board = '1=1';

		// Use some temporary integration hooks to manipulate BBC parsing during export.
		$hook_methods = [
			'pre_parsebbc' => in_array($this->_details['format'], ['HTML', 'XML_XSLT']) ? 'pre_parsebbc_html' : 'pre_parsebbc_xml',
			'post_parsebbc' => 'post_parsebbc',
			'bbc_codes' => 'bbc_codes',
			'post_parseAttachBBC' => 'post_parseAttachBBC',
			'attach_bbc_validate' => 'attach_bbc_validate',
		];

		foreach ($hook_methods as $hook => $method) {
			IntegrationHook::add('integrate_' . $hook, __CLASS__ . '::' . $method, false);
		}

		// Perform the export.
		if ($this->_details['format'] == 'XML') {
			$this->exportXml();
		} elseif ($this->_details['format'] == 'HTML') {
			$this->exportHtml();
		} elseif ($this->_details['format'] == 'XML_XSLT') {
			$this->exportXmlXslt();
		}

		// If necessary, create a new background task to continue the export process.
		if (!empty($this->next_task)) {
			Db::$db->insert(
				'insert',
				'{db_prefix}background_tasks',
				['task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'],
				$this->next_task,
				[],
			);
		}

		ignore_user_abort(false);

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The workhorse of this class. Compiles profile data to XML files.
	 */
	protected function exportXml()
	{
		// For convenience...
		$uid = $this->_details['uid'];
		$lang = $this->_details['lang'];
		$included = $this->_details['included'];
		$start = $this->_details['start'];
		$latest = $this->_details['latest'];
		$datatype = $this->_details['datatype'];

		if (!isset($included[$datatype]['func']) || !isset($included[$datatype]['langfile'])) {
			return;
		}

		// Setup.
		$done = false;
		$delay = 0;
		$datatypes = array_keys($included);

		$feed = new Feed($datatype, $uid);
		$feed->format = 'smf';
		$feed->ascending = true;
		$feed->limit = !empty(Config::$modSettings['export_rate']) ? Config::$modSettings['export_rate'] : 250;
		$feed->start_after = $start[$datatype];

		Theme::loadEssential();
		Theme::$current->settings['actual_theme_dir'] = Theme::$current->settings['theme_dir'];
		User::$me->language = $lang;
		Lang::load(implode('+', array_unique(['index', 'Modifications', 'Stats', 'Profile', $included[$datatype]['langfile']])), $lang);

		// @todo Ask lawyers whether the GDPR requires us to include posts in the recycle bin.
		$feed->query_this_board = '{query_see_message_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? ' AND m.id_board != ' . Config::$modSettings['recycle_board'] : '');

		// We need a valid export directory.
		if (empty(Config::$modSettings['export_dir']) || !is_dir(Config::$modSettings['export_dir']) || !Utils::makeWritable(Config::$modSettings['export_dir'])) {
			if (Export::createDir() === false) {
				return;
			}
		}

		$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;

		$idhash = hash_hmac('sha1', $uid, Config::getAuthSecret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

		// Increment the file number until we reach one that doesn't exist.
		$filenum = 1;
		$realfile = $export_dir_slash . $filenum . '_' . $idhash_ext;

		while (file_exists($realfile)) {
			$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;
		}

		$tempfile = $export_dir_slash . $idhash_ext . '.tmp';
		$progressfile = $export_dir_slash . $idhash_ext . '.progress.json';

		$feed->metadata = [
			'title' => sprintf(Lang::$txt['profile_of_username'], User::$me->name),
			'desc' => Lang::sentenceList(array_map(
				function ($datatype) {
					return Lang::$txt[$datatype];
				},
				array_keys($included),
			)),
			'author' => Config::$mbname,
			'source' => Config::$scripturl . '?action=profile;u=' . $uid,
			'language' => !empty(Lang::$txt['lang_locale']) ? str_replace('_', '-', substr(Lang::$txt['lang_locale'], 0, strcspn(Lang::$txt['lang_locale'], '.'))) : 'en',
			'self' => '', // Unused, but can't be null.
			'page' => &$filenum,
		];

		// Some paranoid hosts disable or hamstring the disk space functions in an attempt at security via obscurity.
		$check_diskspace = !empty(Config::$modSettings['export_min_diskspace_pct']) && function_exists('disk_free_space') && function_exists('disk_total_space') && intval(@disk_total_space(Config::$modSettings['export_dir']) >= 1440);
		$minspace = $check_diskspace ? ceil(disk_total_space(Config::$modSettings['export_dir']) * Config::$modSettings['export_min_diskspace_pct'] / 100) : 0;

		// If a necessary file is missing, we need to start over.
		if (!file_exists($tempfile) || !file_exists($progressfile) || filesize($progressfile) == 0) {
			foreach (array_merge([$tempfile, $progressfile], glob($export_dir_slash . '*_' . $idhash_ext)) as $fpath) {
				@unlink($fpath);
			}

			$filenum = 1;
			$realfile = $export_dir_slash . $filenum . '_' . $idhash_ext;

			Feed::build('smf', [], $feed->metadata, 'profile');
			file_put_contents($tempfile, implode('', Utils::$context['feed']), LOCK_EX);

			$progress = array_fill_keys($datatypes, 0);
			file_put_contents($progressfile, Utils::jsonEncode($progress));
		} else {
			$progress = Utils::jsonDecode(file_get_contents($progressfile), true);
		}

		// Get the data.
		$xml_data = call_user_func([$feed, $included[$datatype]['func']]);

		// No data retrieved? Just move on then.
		if (empty($xml_data)) {
			$datatype_done = true;
		}
		// Basic profile data is quick and easy.
		elseif ($datatype == 'profile') {
			Feed::build('smf', $xml_data, $feed->metadata, 'profile');
			file_put_contents($tempfile, implode('', Utils::$context['feed']), LOCK_EX);

			$progress[$datatype] = time();
			$datatype_done = true;

			// Cache for subsequent reuse.
			$profile_basic_items = Utils::$context['feed']['items'];

			CacheApi::put('export_profile_basic-' . $uid, $profile_basic_items, Taskrunner::MAX_CLAIM_THRESHOLD);
		}
		// Posts and PMs...
		else {
			// We need the basic profile data in every export file.
			$profile_basic_items = CacheApi::get('export_profile_basic-' . $uid, Taskrunner::MAX_CLAIM_THRESHOLD);

			if (empty($profile_basic_items)) {
				$profile_data = call_user_func([$feed, $included['profile']['func']]);

				Feed::build('smf', $profile_data, $feed->metadata, 'profile');

				$profile_basic_items = Utils::$context['feed']['items'];

				CacheApi::put('export_profile_basic-' . $uid, $profile_basic_items, Taskrunner::MAX_CLAIM_THRESHOLD);

				unset(Utils::$context['feed']);
			}

			$per_page = $this->_details['format_settings']['per_page'];
			$prev_item_count = empty($this->_details['item_count']) ? 0 : $this->_details['item_count'];

			// If the temp file has grown enormous, save it so we can start a new one.
			clearstatcache();

			if (file_exists($tempfile) && filesize($tempfile) >= 1024 * 1024 * 250) {
				rename($tempfile, $realfile);
				$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;

				if (empty(Utils::$context['feed']['header'])) {
					Feed::build('smf', [], $feed->metadata, 'profile');
				}

				file_put_contents($tempfile, implode('', [Utils::$context['feed']['header'], $profile_basic_items, Utils::$context['feed']['footer']]), LOCK_EX);

				$prev_item_count = 0;
			}

			// Split $xml_data into reasonably sized chunks.
			if (empty($prev_item_count)) {
				$xml_data = array_chunk($xml_data, $per_page);
			} else {
				$first_chunk = array_splice($xml_data, 0, $per_page - $prev_item_count);
				$xml_data = array_merge([$first_chunk], array_chunk($xml_data, $per_page));
				unset($first_chunk);
			}

			foreach ($xml_data as $chunk => $items) {
				unset($new_item_count, $last_id);

				// Remember the last item so we know where to start next time.
				$last_item = end($items);

				if (isset($last_item['content'][0]['content']) && $last_item['content'][0]['tag'] === 'id') {
					$last_id = $last_item['content'][0]['content'];
				}

				// Build the XML string from the data.
				Feed::build('smf', $items, $feed->metadata, 'profile');

				// If disk space is insufficient, pause for a day so the admin can fix it.
				if ($check_diskspace && disk_free_space(Config::$modSettings['export_dir']) - $minspace <= strlen(implode('', Utils::$context['feed']) . ($this->stylesheet ?? ''))) {
					Lang::load('Errors');

					ErrorHandler::log(sprintf(Lang::$txt['export_low_diskspace'], Config::$modSettings['export_min_diskspace_pct']));

					$delay = 86400;
				} else {
					// We need a file to write to, of course.
					if (!file_exists($tempfile)) {
						file_put_contents($tempfile, implode('', [Utils::$context['feed']['header'], $profile_basic_items, Utils::$context['feed']['footer']]), LOCK_EX);
					}

					// Insert the new data before the feed footer.
					$handle = fopen($tempfile, 'r+');

					if (is_resource($handle)) {
						flock($handle, LOCK_EX);

						fseek($handle, strlen(Utils::$context['feed']['footer']) * -1, SEEK_END);

						$bytes_written = fwrite($handle, Utils::$context['feed']['items'] . Utils::$context['feed']['footer']);

						// If we couldn't write everything, revert the changes and consider the write to have failed.
						if ($bytes_written > 0 && $bytes_written < strlen(Utils::$context['feed']['items'] . Utils::$context['feed']['footer'])) {
							fseek($handle, $bytes_written * -1, SEEK_END);
							$pointer_pos = ftell($handle);
							ftruncate($handle, $pointer_pos);
							rewind($handle);
							fseek($handle, 0, SEEK_END);
							fwrite($handle, Utils::$context['feed']['footer']);

							$bytes_written = false;
						}

						flock($handle, LOCK_UN);
						fclose($handle);
					}

					// Write failed. We'll try again next time.
					if (empty($bytes_written)) {
						$delay = Taskrunner::MAX_CLAIM_THRESHOLD;
						break;
					}

					// All went well.
					// Track progress by ID where appropriate, and by time otherwise.
					$progress[$datatype] = !isset($last_id) ? time() : $last_id;
					file_put_contents($progressfile, Utils::jsonEncode($progress));

					// Are we done with this datatype yet?
					if (!isset($last_id) || (count($items) < $per_page && $last_id >= $latest[$datatype])) {
						$datatype_done = true;
					}

					// Finished the file for this chunk, so move on to the next one.
					if (count($items) >= $per_page - $prev_item_count) {
						rename($tempfile, $realfile);
						$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;
						$prev_item_count = $new_item_count = 0;
					}
					// This was the last chunk.
					else {
						// Should we append more items to this file next time?
						$new_item_count = isset($last_id) ? $prev_item_count + count($items) : 0;
					}
				}
			}
		}

		if (!empty($datatype_done)) {
			$datatype_key = array_search($datatype, $datatypes);
			$done = !isset($datatypes[$datatype_key + 1]);

			if (!$done) {
				$datatype = $datatypes[$datatype_key + 1];
			}
		}

		// Remove the .tmp extension from the final tempfile so the system knows it's done.
		if (!empty($done)) {
			rename($tempfile, $realfile);
		}
		// Oops. Apparently some sneaky monkey cancelled the export while we weren't looking.
		elseif (!file_exists($progressfile)) {
			@unlink($tempfile);

			return;
		}
		// We have more work to do again later.
		else {
			$start[$datatype] = $progress[$datatype];

			$new_details = [
				'format' => $this->_details['format'],
				'uid' => $uid,
				'lang' => $lang,
				'included' => $included,
				'start' => $start,
				'latest' => $latest,
				'datatype' => $datatype,
				'format_settings' => $this->_details['format_settings'],
				'last_page' => $this->_details['last_page'],
				'dlfilename' => $this->_details['dlfilename'],
			];

			if (!empty($new_item_count)) {
				$new_details['item_count'] = $new_item_count;
			}

			$this->next_task = [__FILE__, __CLASS__, Utils::jsonEncode($new_details), time() - Taskrunner::MAX_CLAIM_THRESHOLD + $delay];

			if (!file_exists($tempfile)) {
				Feed::build('smf', [], $feed->metadata, 'profile');

				file_put_contents($tempfile, implode('', [Utils::$context['feed']['header'], !empty($profile_basic_items) ? $profile_basic_items : '', Utils::$context['feed']['footer']]), LOCK_EX);
			}
		}

		file_put_contents($progressfile, Utils::jsonEncode($progress));
	}

	/**
	 * Compiles profile data to HTML.
	 *
	 * Internally calls exportXml() and then uses an XSLT stylesheet to
	 * transform the XML files into HTML.
	 */
	protected function exportHtml()
	{
		Utils::$context['export_last_page'] = $this->_details['last_page'];
		Utils::$context['export_dlfilename'] = $this->_details['dlfilename'];

		// Get the XSLT stylesheet.
		$this->buildStylesheet();

		// Perform the export to XML.
		$this->exportXml();

		// Determine which files, if any, are ready to be transformed.
		$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;
		$idhash = hash_hmac('sha1', $this->_details['uid'], Config::getAuthSecret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

		$new_exportfiles = [];

		foreach (glob($export_dir_slash . '*_' . $idhash_ext) as $completed_file) {
			if (file_get_contents($completed_file, false, null, 0, 6) == '<?xml ') {
				$new_exportfiles[] = $completed_file;
			}
		}

		if (empty($new_exportfiles)) {
			return;
		}

		// Set up the XSLT processor.
		$xslt = new DOMDocument();
		$xslt->loadXML($this->stylesheet);
		$xsltproc = new XSLTProcessor();
		$xsltproc->importStylesheet($xslt);

		$libxml_options = 0;

		foreach (['LIBXML_COMPACT', 'LIBXML_PARSEHUGE', 'LIBXML_BIGLINES'] as $libxml_option) {
			if (defined($libxml_option)) {
				$libxml_options = $libxml_options | constant($libxml_option);
			}
		}

		// Transform the files to HTML.
		$i = 0;
		$num_files = count($new_exportfiles);
		$max_transform_time = 0;

		$xmldoc = new DOMDocument();

		foreach ($new_exportfiles as $exportfile) {
			if (function_exists('apache_reset_timeout')) {
				@apache_reset_timeout();
			}

			$started = microtime(true);
			$xmldoc->load($exportfile, $libxml_options);
			$xsltproc->transformToURI($xmldoc, $exportfile);
			$finished = microtime(true);

			$max_transform_time = max($max_transform_time, $finished - $started);

			// When deadlines loom, sometimes the best solution is procrastination.
			if (++$i < $num_files && TIME_START + $this->time_limit < $finished + $max_transform_time * 2) {
				// After all, there's always next time.
				if (empty($this->next_task)) {
					$progressfile = $export_dir_slash . $idhash_ext . '.progress.json';

					$new_details = $this->_details;
					$new_details['start'] = Utils::jsonDecode(file_get_contents($progressfile), true);

					$this->next_task = [__FILE__, __CLASS__, Utils::jsonEncode($new_details), time() - Taskrunner::MAX_CLAIM_THRESHOLD];
				}

				// So let's just relax and take a well deserved...
				break;
			}
		}
	}

	/**
	 * Compiles profile data to XML with embedded XSLT.
	 *
	 * Internally calls exportXml() and then embeds an XSLT stylesheet into
	 * the XML so that it can be processed by the client.
	 */
	protected function exportXmlXslt()
	{
		Utils::$context['export_last_page'] = $this->_details['last_page'];
		Utils::$context['export_dlfilename'] = $this->_details['dlfilename'];

		// Embedded XSLT requires adding a special DTD and processing instruction in the main XML document.
		IntegrationHook::add('integrate_xml_data', __CLASS__ . '::add_dtd', false);

		// Make sure the stylesheet is set.
		$this->buildStylesheet();

		// Perform the export to XML.
		$this->exportXml();

		// Just in case...
		if (empty(Utils::$context['feed']['footer'])) {
			Feed::build('smf', [], array_fill_keys(['title', 'desc', 'source', 'self'], ''), 'profile');
		}

		// Find any completed files that don't yet have the stylesheet embedded in them.
		$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;
		$idhash = hash_hmac('sha1', $this->_details['uid'], Config::getAuthSecret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

		$test_length = strlen($this->stylesheet . Utils::$context['feed']['footer']);

		$new_exportfiles = [];

		clearstatcache();

		foreach (glob($export_dir_slash . '*_' . $idhash_ext) as $completed_file) {
			if (filesize($completed_file) < $test_length || file_get_contents($completed_file, false, null, $test_length * -1) !== $this->stylesheet . Utils::$context['feed']['footer']) {
				$new_exportfiles[] = $completed_file;
			}
		}

		if (empty($new_exportfiles)) {
			return;
		}

		// Embedding the XSLT means writing to the file yet again.
		foreach ($new_exportfiles as $exportfile) {
			$handle = fopen($exportfile, 'r+');

			if (is_resource($handle)) {
				flock($handle, LOCK_EX);

				fseek($handle, strlen(Utils::$context['feed']['footer']) * -1, SEEK_END);

				$bytes_written = fwrite($handle, $this->stylesheet . Utils::$context['feed']['footer']);

				// If we couldn't write everything, revert the changes.
				if ($bytes_written > 0 && $bytes_written < strlen($this->stylesheet . Utils::$context['feed']['footer'])) {
					fseek($handle, $bytes_written * -1, SEEK_END);
					$pointer_pos = ftell($handle);
					ftruncate($handle, $pointer_pos);
					rewind($handle);
					fseek($handle, 0, SEEK_END);
					fwrite($handle, Utils::$context['feed']['footer']);
				}

				flock($handle, LOCK_UN);
				fclose($handle);
			}
		}
	}

	/**
	 * Finalizes the XSLT stylesheet used to transform an XML-based profile
	 * export file into the desired output format.
	 */
	protected function buildStylesheet(): void
	{
		$xslt_variables = [];

		if (in_array($this->_details['format'], ['HTML', 'XML_XSLT'])) {
			if (!class_exists('DOMDocument') || !class_exists('XSLTProcessor')) {
				$this->_details['format'] = 'XML_XSLT';
			}

			require_once Config::$sourcedir . '/Actions/Profile/Export.php';
			$export_formats = get_export_formats();

			Lang::load('Profile');

			/* Notes:
			 * 1. The 'value' can be one of the following:
			 *    - an integer or string
			 *    - an XPath expression
			 *    - raw XML, which may or not not include other XSLT statements.
			 *
			 * 2. Always set 'no_cdata_parse' to true when the value is raw XML.
			 *
			 * 3. Set 'xpath' to true if the value is an XPath expression. When this
			 *    is true, the value will be placed in the 'select' attribute of the
			 *    <xsl:variable> element rather than in a child node.
			 *
			 * 4. Set 'param' to true in order to create an <xsl:param> instead
			 *    of an <xsl:variable>.
			 *
			 * A word to PHP coders: Do not let the term "variable" mislead you.
			 * XSLT variables are roughly equivalent to PHP constants rather
			 * than PHP variables; once the value has been set, it is immutable.
			 * Keeping this in mind may spare you from some confusion and
			 * frustration while working with XSLT.
			 */
			$xslt_variables = [
				'scripturl' => [
					'value' => Config::$scripturl,
				],
				'themeurl' => [
					'value' => Theme::$current->settings['default_theme_url'],
				],
				'member_id' => [
					'value' => $this->_details['uid'],
				],
				'last_page' => [
					'param' => true,
					'value' => !empty(Utils::$context['export_last_page']) ? Utils::$context['export_last_page'] : 1,
					'xpath' => true,
				],
				'dlfilename' => [
					'param' => true,
					'value' => !empty(Utils::$context['export_dlfilename']) ? Utils::$context['export_dlfilename'] : '',
				],
				'ext' => [
					'value' => $export_formats[$this->_details['format']]['extension'],
				],
				'forum_copyright' => [
					'value' => sprintf(Lang::$forum_copyright, SMF_FULL_VERSION, SMF_SOFTWARE_YEAR, Config::$scripturl),
				],
				'txt_summary_heading' => [
					'value' => Lang::$txt['summary'],
				],
				'txt_posts_heading' => [
					'value' => Lang::$txt['posts'],
				],
				'txt_personal_messages_heading' => [
					'value' => Lang::$txt['personal_messages'],
				],
				'txt_view_source_button' => [
					'value' => Lang::$txt['export_view_source_button'],
				],
				'txt_download_original' => [
					'value' => Lang::$txt['export_download_original'],
				],
				'txt_help' => [
					'value' => Lang::$txt['help'],
				],
				'txt_terms_rules' => [
					'value' => Lang::$txt['terms_and_rules'],
				],
				'txt_go_up' => [
					'value' => Lang::$txt['go_up'],
				],
				'txt_pages' => [
					'value' => Lang::$txt['pages'],
				],
			];

			// Let mods adjust the XSLT variables.
			IntegrationHook::call('integrate_export_xslt_variables', [&$xslt_variables, $this->_details['format']]);

			$idhash = hash_hmac('sha1', $this->_details['uid'], Config::getAuthSecret());
			$xslt_variables['dltoken'] = [
				'value' => hash_hmac('sha1', $idhash, Config::getAuthSecret()),
			];

			if ($this->_details['format'] == 'XML_XSLT') {
				$this->xslt_stylesheet['header'] = "\n" . implode("\n", [
					'',
					'<xsl:stylesheet version="1.0" xmlns:xsl="' . self::XML_NAMESPACES['xsl'] . '" xmlns:html="' . self::XML_NAMESPACES['html'] . '" xmlns:smf="' . self::XML_NAMESPACES['smf'] . '" exclude-result-prefixes="smf html" id="stylesheet">',
					'',
					"\t" . '<xsl:template match="xsl:stylesheet"/>',
					"\t" . '<xsl:template match="xsl:stylesheet" mode="detailedinfo"/>',
				]);
			}

			// Insert the XSLT variables.
			foreach ($xslt_variables as $name => $var) {
				$element = !empty($var['param']) ? 'param' : 'variable';

				if (!empty($this->xslt_stylesheet['variables'])) {
					$this->xslt_stylesheet['variables'] .= "\n";
				}

				$this->xslt_stylesheet['variables'] .= "\t" . '<xsl:' . $element . ' name="' . $name . '"';

				if (isset($var['xpath'])) {
					$this->xslt_stylesheet['variables'] .= ' select="' . $var['value'] . '"/>';
				} else {
					$this->xslt_stylesheet['variables'] .= '>' . (!empty($var['no_cdata_parse']) ? $var['value'] : Feed::cdataParse($var['value'])) . '</xsl:' . $element . '>';
				}
			}

			// Template to insert CSS and JavaScript
			$this->xslt_stylesheet['css_js'] = "\t" . '<xsl:template name="css_js">';

			$this->loadCssJs();

			if (!empty(Utils::$context['export_css_files'])) {
				foreach (Utils::$context['export_css_files'] as $css_file) {
					$url = $css_file['fileUrl'];

					$this->xslt_stylesheet['css_js'] .= <<<END

								<link rel="stylesheet">
									<xsl:attribute name="href">
										<xsl:text>{$url}</xsl:text>
									</xsl:attribute>
						END;

					if (!empty($css_file['options']['attributes'])) {
						foreach ($css_file['options']['attributes'] as $key => $value) {
							if ($value === false) {
								continue;
							}

							$value = $value === true ? $key : $value;

							$this->xslt_stylesheet['css_js'] .= <<<END
											<xsl:attribute name="{$key}">
												<xsl:text>{$value}</xsl:text>
											</xsl:attribute>
								END;
						}
					}

					$this->xslt_stylesheet['css_js'] .= <<<END

								</link>
						END;
				}
			}

			if (!empty(Utils::$context['export_css_header'])) {
				$this->xslt_stylesheet['css_js'] .= "\n\t\t" . '<style><![CDATA[' . "\n" . implode("\n", Utils::$context['export_css_header']) . "\n" . ']]>' . "\n" . '</style>';
			}

			if (!empty(Utils::$context['export_javascript_vars'])) {
				$this->xslt_stylesheet['css_js'] .=  "\n\t\t" . '<script><![CDATA[';

				foreach (Utils::$context['export_javascript_vars'] as $var => $val) {
					$this->xslt_stylesheet['css_js'] .= "\nvar " . $var . (!empty($val) ? ' = ' . $val : '') . ';';
				}

				$this->xslt_stylesheet['css_js'] .= "\n" . ']]>' . "\n\t\t" . '</script>';
			}

			if (!empty(Utils::$context['export_javascript_files'])) {
				foreach (Utils::$context['export_javascript_files'] as $js_file) {
					$url = $js_file['fileUrl'];

					$this->xslt_stylesheet['css_js'] .= <<<END

								<script>
									<xsl:attribute name="src">
										<xsl:text>{$url}</xsl:text>
									</xsl:attribute>
						END;

					if (!empty($js_file['options']['attributes'])) {
						foreach ($js_file['options']['attributes'] as $key => $value) {
							if ($value === false) {
								continue;
							}

							$value = $value === true ? $key : $value;

							$this->xslt_stylesheet['css_js'] .= <<<END
											<xsl:attribute name="{$key}">
												<xsl:text>{$value}</xsl:text>
											</xsl:attribute>
								END;
						}
					}

					$this->xslt_stylesheet['css_js'] .= "\n\t\t" . '</script>';
				}
			}

			if (!empty(Utils::$context['export_javascript_inline']['standard'])) {
				$this->xslt_stylesheet['css_js'] .=  "\n\t\t" . '<script><![CDATA[' . "\n" . implode("\n", Utils::$context['export_javascript_inline']['standard']) . "\n" . ']]>' . "\n" . '</script>';
			}

			if (!empty(Utils::$context['export_javascript_inline']['defer'])) {
				$this->xslt_stylesheet['css_js'] .= "\n\t\t" . '<script><![CDATA[' . "\n" . 'window.addEventListener("DOMContentLoaded", function() {';

				$this->xslt_stylesheet['css_js'] .= "\n\t" . str_replace("\n", "\n\t", implode("\n", Utils::$context['export_javascript_inline']['defer']));

				$this->xslt_stylesheet['css_js'] .= "\n" . '});' . "\n" . ']]>' . "\n\t\t" . '</script>';
			}

			$this->xslt_stylesheet['css_js'] .= "\n\t" . '</xsl:template>';
		}

		// Let mods adjust the XSLT stylesheet.
		IntegrationHook::call('integrate_export_xslt_stylesheet', [&$this->xslt_stylesheet, $this->_details['format']]);

		$this->stylesheet = implode("\n\n", $this->xslt_stylesheet);

		if ($this->_details['format'] == 'XML_XSLT') {
			$placeholders = [];

			if (preg_match_all('/<!\[CDATA\[\X*?\]\]>/u', $this->stylesheet, $matches)) {
				foreach ($matches[0] as $cdata) {
					$placeholders[$cdata] = md5($cdata);
				}
			}

			$this->stylesheet = strtr($this->stylesheet, $placeholders);
			$this->stylesheet = preg_replace('/^(?!\n)/mu', "\t", $this->stylesheet);
			$this->stylesheet = strtr($this->stylesheet, array_flip($placeholders));
		}
	}

	/**
	 * Loads and prepares CSS and JavaScript for insertion into an XSLT stylesheet.
	 */
	protected function loadCssJs()
	{
		// If we're not running a background task, we need to preserve any existing CSS and JavaScript.
		if (SMF != 'BACKGROUND') {
			foreach (['css_files', 'css_header', 'javascript_vars', 'javascript_files', 'javascript_inline'] as $var) {
				if (isset(Utils::$context[$var])) {
					Utils::$context['real_' . $var] = Utils::$context[$var];
				}

				if ($var == 'javascript_inline') {
					foreach (Utils::$context[$var] as $key => $value) {
						Utils::$context[$var][$key] = [];
					}
				} else {
					Utils::$context[$var] = [];
				}
			}
		}
		// Autoloading is unavailable for background tasks, so we have to do things the hard way...
		else {
			if (!empty(Config::$modSettings['minimize_files']) && (!class_exists('MatthiasMullie\\Minify\\CSS') || !class_exists('MatthiasMullie\\Minify\\JS'))) {
				// Include, not require, because minimization is nice to have but not vital here.
				include_once implode(DIRECTORY_SEPARATOR, [Config::$sourcedir, 'minify', 'src', 'Exception.php']);

				include_once implode(DIRECTORY_SEPARATOR, [Config::$sourcedir, 'minify', 'src', 'Exceptions', 'BasicException.php']);

				include_once implode(DIRECTORY_SEPARATOR, [Config::$sourcedir, 'minify', 'src', 'Exceptions', 'FileImportException.php']);

				include_once implode(DIRECTORY_SEPARATOR, [Config::$sourcedir, 'minify', 'src', 'Exceptions', 'IOException.php']);

				include_once implode(DIRECTORY_SEPARATOR, [Config::$sourcedir, 'minify', 'src', 'Minify.php']);

				include_once implode(DIRECTORY_SEPARATOR, [Config::$sourcedir, 'minify', 'path-converter', 'src', 'Converter.php']);

				include_once implode(DIRECTORY_SEPARATOR, [Config::$sourcedir, 'minify', 'src', 'CSS.php']);

				include_once implode(DIRECTORY_SEPARATOR, [Config::$sourcedir, 'minify', 'src', 'JS.php']);

				if (!class_exists('MatthiasMullie\\Minify\\CSS') || !class_exists('MatthiasMullie\\Minify\\JS')) {
					Config::$modSettings['minimize_files'] = false;
				}
			}
		}

		// Load our standard CSS files.
		Theme::loadCSSFile('index.css', ['minimize' => true, 'order_pos' => 1], 'smf_index');
		Theme::loadCSSFile('responsive.css', ['force_current' => false, 'validate' => true, 'minimize' => true, 'order_pos' => 9000], 'smf_responsive');

		if (Utils::$context['right_to_left']) {
			Theme::loadCSSFile('rtl.css', ['order_pos' => 4000], 'smf_rtl');
		}

		// In case any mods added relevant CSS.
		IntegrationHook::call('integrate_pre_css_output');

		// This next chunk mimics some of Theme::template_css()
		$css_to_minify = [];
		$normal_css_files = [];

		usort(
			Utils::$context['css_files'],
			function ($a, $b) {
				return $a['options']['order_pos'] < $b['options']['order_pos'] ? -1 : ($a['options']['order_pos'] > $b['options']['order_pos'] ? 1 : 0);
			},
		);

		foreach (Utils::$context['css_files'] as $css_file) {
			if (!isset($css_file['options']['minimize'])) {
				$css_file['options']['minimize'] = true;
			}

			if (!empty($css_file['options']['minimize']) && !empty(Config::$modSettings['minimize_files'])) {
				$css_to_minify[] = $css_file;
			} else {
				$normal_css_files[] = $css_file;
			}
		}

		$minified_css_files = !empty($css_to_minify) ? Theme::custMinify($css_to_minify, 'css') : [];

		Utils::$context['css_files'] = [];

		foreach (array_merge($minified_css_files, $normal_css_files) as $css_file) {
			// Embed the CSS in a <style> element if possible, since exports are supposed to be standalone files.
			if (file_exists($css_file['filePath'])) {
				Utils::$context['css_header'][] = file_get_contents($css_file['filePath']);
			} elseif (!empty($css_file['fileUrl'])) {
				Utils::$context['css_files'][] = $css_file;
			}
		}

		// Next, we need to do for JavaScript what we just did for CSS.
		Theme::loadJavaScriptFile('https://ajax.googleapis.com/ajax/libs/jquery/' . JQUERY_VERSION . '/jquery.min.js', ['external' => true, 'seed' => false], 'smf_jquery');

		// There might be JavaScript that we need to add in order to support custom BBC or something.
		IntegrationHook::call('integrate_pre_javascript_output', [false]);
		IntegrationHook::call('integrate_pre_javascript_output', [true]);

		$js_to_minify = [];
		$all_js_files = [];

		foreach (Utils::$context['javascript_files'] as $js_file) {
			if (!empty($js_file['options']['minimize']) && !empty(Config::$modSettings['minimize_files'])) {
				if (!empty($js_file['options']['async'])) {
					$js_to_minify['async'][] = $js_file;
				} elseif (!empty($js_file['options']['defer'])) {
					$js_to_minify['defer'][] = $js_file;
				} else {
					$js_to_minify['standard'][] = $js_file;
				}
			} else {
				$all_js_files[] = $js_file;
			}
		}

		Utils::$context['javascript_files'] = [];

		foreach ($js_to_minify as $type => $js_files) {
			if (!empty($js_files)) {
				$minified_js_files = Theme::custMinify($js_files, 'js');
				$all_js_files = array_merge($all_js_files, $minified_js_files);
			}
		}

		foreach ($all_js_files as $js_file) {
			// As with the CSS, embed whatever JavaScript we can.
			if (file_exists($js_file['filePath'])) {
				Utils::$context['javascript_inline'][(!empty($js_file['options']['defer']) ? 'defer' : 'standard')][] = file_get_contents($js_file['filePath']);
			} elseif (!empty($js_file['fileUrl'])) {
				Utils::$context['javascript_files'][] = $js_file;
			}
		}

		// We need to embed the smiley images, too. To save space, we store the image data in JS variables.
		$smiley_mimetypes = [
			'gif' => 'image/gif',
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'tiff' => 'image/tiff',
			'svg' => 'image/svg+xml',
		];

		foreach (glob(implode(DIRECTORY_SEPARATOR, [Config::$modSettings['smileys_dir'], User::$me->smiley_set, '*.*'])) as $smiley_file) {
			$pathinfo = pathinfo($smiley_file);

			if (!isset($smiley_mimetypes[$pathinfo['extension']])) {
				continue;
			}

			$var = implode('_', ['smf', 'smiley', $pathinfo['filename'], $pathinfo['extension']]);

			if (!isset(Utils::$context['javascript_vars'][$var])) {
				Utils::$context['javascript_vars'][$var] = '\'data:' . $smiley_mimetypes[$pathinfo['extension']] . ';base64,' . base64_encode(file_get_contents($smiley_file)) . '\'';
			}
		}

		Utils::$context['javascript_inline']['defer'][] = implode("\n", [
			'$("img.smiley").each(function() {',
			'	var data_uri_var = $(this).attr("src").replace(/.*\/(\w+)\.(\w+)$/, "smf_smiley_$1_$2");',
			'	$(this).attr("src", window[data_uri_var]);',
			'});',
		]);

		// Now move everything to the special export version of these arrays.
		foreach (['css_files', 'css_header', 'javascript_vars', 'javascript_files', 'javascript_inline'] as $var) {
			if (isset(Utils::$context[$var])) {
				Utils::$context['export_' . $var] = Utils::$context[$var];
			}

			unset(Utils::$context[$var]);
		}

		// Finally, restore the real values.
		if (SMF !== 'BACKGROUND') {
			foreach (['css_files', 'css_header', 'javascript_vars', 'javascript_files', 'javascript_inline'] as $var) {
				if (isset(Utils::$context['real_' . $var])) {
					Utils::$context[$var] = Utils::$context['real_' . $var];
				}

				unset(Utils::$context['real_' . $var]);
			}
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Adds a custom DOCTYPE definition and an XSLT processing instruction to
	 * the main XML file's header. Only used for the XML_XSLT format.
	 */
	public static function add_dtd(&$xml_data, &$metadata, &$namespaces, &$extraFeedTags, &$forceCdataKeys, &$nsKeys, $xml_format, $subaction, &$doctype)
	{
		if (!isset(Lang::$txt['export_open_in_browser'])) {
			Lang::load('Profile');
		}

		$doctype = implode("\n", [
			'<!--',
			"\t" . Lang::$txt['export_open_in_browser'],
			'-->',
			'<?xml-stylesheet type="text/xsl" href="#stylesheet"?>',
			'<!DOCTYPE smf:xml-feed [',
			'<!ATTLIST xsl:stylesheet',
			'id ID #REQUIRED>',
			']>',
		]);
	}

	/**
	 * Adjusts some parse_bbc() parameters for the special case of HTML and
	 * XML_XSLT exports.
	 */
	public static function pre_parsebbc_html(&$message, &$smileys, &$cache_id, &$parse_tags, &$cache_key_extras)
	{
		$cache_id = '';

		$cache_key_extras[__CLASS__] = 1;

		foreach (['smileys_url', 'attachmentThumbnails'] as $var) {
			if (isset(Config::$modSettings[$var])) {
				self::$real_modSettings[$var] = Config::$modSettings[$var];
			}
		}

		Config::$modSettings['smileys_url'] = '.';
		Config::$modSettings['attachmentThumbnails'] = false;
	}

	/**
	 * Adjusts some parse_bbc() parameters for the special case of XML exports.
	 */
	public static function pre_parsebbc_xml(&$message, &$smileys, &$cache_id, &$parse_tags, &$cache_key_extras)
	{
		$cache_id = '';

		$cache_key_extras[__CLASS__] = 1;

		$smileys = false;

		if (!isset(Config::$modSettings['disabledBBC'])) {
			Config::$modSettings['disabledBBC'] = 'attach';
		} else {
			self::$real_modSettings['disabledBBC'] = Config::$modSettings['disabledBBC'];

			Config::$modSettings['disabledBBC'] = implode(',', array_unique(array_merge(array_filter(explode(',', Config::$modSettings['disabledBBC'])), ['attach'])));
		}
	}

	/**
	 * Reverses changes made by pre_parsebbc()
	 */
	public static function post_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		foreach (['disabledBBC', 'smileys_url', 'attachmentThumbnails'] as $var) {
			if (isset(self::$real_modSettings[$var])) {
				Config::$modSettings[$var] = self::$real_modSettings[$var];
			}
		}
	}

	/**
	 * Adjusts certain BBCodes for the special case of exports.
	 */
	public static function bbc_codes(&$codes, &$no_autolink_tags)
	{
		foreach ($codes as &$code) {
			// To make the "Select" link work we'd need to embed a bunch more JS. Not worth it.
			if ($code['tag'] === 'code') {
				$code['content'] = preg_replace('~<a class="codeoperation\b.*?</a>~', '', $code['content']);
			}
		}
	}

	/**
	 * Adjusts the attachment download URL for the special case of exports.
	 */
	public static function post_parseAttachBBC(&$attachContext)
	{
		static $dltokens;

		if (empty($dltokens[Utils::$context['xmlnews_uid']])) {
			$idhash = hash_hmac('sha1', Utils::$context['xmlnews_uid'], Config::getAuthSecret());

			$dltokens[Utils::$context['xmlnews_uid']] = hash_hmac('sha1', $idhash, Config::getAuthSecret());
		}

		$attachContext['orig_href'] = Config::$scripturl . '?action=profile;area=dlattach;u=' . Utils::$context['xmlnews_uid'] . ';attach=' . $attachContext['id'] . ';t=' . $dltokens[Utils::$context['xmlnews_uid']];

		$attachContext['href'] = rawurlencode($attachContext['id'] . ' - ' . html_entity_decode($attachContext['name']));
	}

	/**
	 * Adjusts the format of the HTML produced by the attach BBCode.
	 */
	public static function attach_bbc_validate(&$returnContext, $currentAttachment, $tag, $data, $disabled, $params)
	{
		$orig_link = '<a href="' . $currentAttachment['orig_href'] . '" class="bbc_link">' . Lang::$txt['export_download_original'] . '</a>';

		$hidden_orig_link = ' <a href="' . $currentAttachment['orig_href'] . '" class="bbc_link dlattach_' . $currentAttachment['id'] . '" style="display:none; flex: 1 0 auto; margin: auto;">' . Lang::$txt['export_download_original'] . '</a>';

		if ($params['{display}'] == 'link') {
			$returnContext .= ' (' . $orig_link . ')';
		} elseif (!empty($currentAttachment['is_image'])) {
			$returnContext = '<span style="display: inline-flex; justify-content: center; align-items: center; position: relative;">' . preg_replace(
				[
					'thumbnail_toggle' => '~</?a\b[^>]*>~',
					'src' => '~src="' . preg_quote($currentAttachment['href'], '~') . ';image"~',
				],
				[
					'thumbnail_toggle' => '',
					'src' => 'src="' . $currentAttachment['href'] . '" onerror="$(\'.dlattach_' . $currentAttachment['id'] . '\').show(); $(\'.dlattach_' . $currentAttachment['id'] . '\').css({\'position\': \'absolute\'});"',
				],
				$returnContext,
			) . $hidden_orig_link . '</span>';
		} elseif (strpos($currentAttachment['mime_type'], 'video/') === 0) {
			$returnContext = preg_replace(
				[
					'src' => '~src="' . preg_quote($currentAttachment['href'], '~') . '"~',
					'opening_tag' => '~^<div class="videocontainer"~',
					'closing_tag' => '~</div>$~',
				],
				[
					'src' => '$0 onerror="$(this).fadeTo(0, 0.2); $(\'.dlattach_' . $currentAttachment['id'] . '\').show(); $(\'.dlattach_' . $currentAttachment['id'] . '\').css({\'position\': \'absolute\'});"',
					'opening_tag' => '<div class="videocontainer" style="display: flex; justify-content: center; align-items: center; position: relative;"',
					'closing_tag' =>  $hidden_orig_link . '</div>',
				],
				$returnContext,
			);
		} elseif (strpos($currentAttachment['mime_type'], 'audio/') === 0) {
			$returnContext = '<span style="display: inline-flex; justify-content: center; align-items: center; position: relative;">' . preg_replace(
				[
					'opening_tag' => '~^<audio\b~',
				],
				[
					'opening_tag' => '<audio onerror="$(this).fadeTo(0, 0); $(\'.dlattach_' . $currentAttachment['id'] . '\').show(); $(\'.dlattach_' . $currentAttachment['id'] . '\').css({\'position\': \'absolute\'});"',
				],
				$returnContext,
			) . $hidden_orig_link . '</span>';
		} else {
			$returnContext = '<span style="display: inline-flex; justify-content: center; align-items: center; position: relative;">' . preg_replace(
				[
					'obj_opening' => '~^<object\b~',
					'link' => '~<a href="' . preg_quote($currentAttachment['href'], '~') . '" class="bbc_link">([^<]*)</a>~',
				],
				[
					'obj_opening' => '<object onerror="$(this).fadeTo(0, 0.2); $(\'.dlattach_' . $currentAttachment['id'] . '\').show(); $(\'.dlattach_' . $currentAttachment['id'] . '\').css({\'position\': \'absolute\'});"~',
					'link' => '$0 (' . $orig_link . ')',
				],
				$returnContext,
			) . $hidden_orig_link . '</span>';
		}
	}
}

?>