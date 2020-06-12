<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/*
	This template file is unusual. Instead of echoing HTML to send to a user's
	browser, these functions return components for XSLT stylesheets that will be
	used to transform XML data.

	The format for function names is different from typical SMF template files.
	This is intentional. The format has two variants:

	1. xslt_generic_{descriptive name}

		For XSLT elements that can be used in any XSLT stylesheet. They must be
		completely agnostic about the data they handle.

	2. xslt_{source type}_{result type}_{descriptive name}

		For XSLT templates that transform a specific type of XML source document
		into a specific type of result document. For example, XSLT templates
		that transfrorm the XML of a SMF profile export document into HTML use
		'xslt_export_html_' as their function name prefix.
 */


/**
 * Returns the opening tag for the XSLT stylesheet element + optional preamble.
 *
 * Attributes are set using the following from $context['xslt_settings']:
 * - no_xml_declaration
 * - preamble
 * - version
 * - namespaces
 * - exclude-result-prefixes
 * - extension-element-prefixes
 * - id
 */
function xslt_generic_header()
{
	global $context;

	$header = '';

	// Include the XML declaration unless explicitly told not to.
	if (empty($context['xslt_settings']['no_xml_declaration']))
		$header .= '<' . '?xml version="1.0" encoding="' . $context['character_set'] . '"?' . '>' . "\n";

	// Is there a preamble (e.g. Document Type Definition, XML processing instructions, etc.) to include?
	if (!empty($context['xslt_settings']['preamble']))
	{
		$header .= implode("\n", (array) $context['xslt_settings']['preamble']);

		// Append line break only if preamble didn't specify its own trailing whitespace.
		if ($header === rtrim($header))
			$header .= "\n";
	}

	// The main thing...
	$header .= '<xsl:stylesheet version="' . (!empty($context['xslt_settings']['version']) ? $context['xslt_settings']['version'] : '1.0') . '"';

	// XSL's own namespace declaration is required no matter what.
	$context['xslt_settings']['namespaces']['xsl'] = 'htt'.'p:/'.'/ww'.'w.w3.o'.'rg/1999/XSL/Transform';

	foreach ($context['xslt_settings']['namespaces'] as $ns_prefix => $ns_uri)
	{
		if (empty($ns_prefix) || ctype_digit(substr($ns_prefix, 0, 1)))
			continue;

		$header .= ' xmlns:' . $ns_prefix . '="' . trim($ns_uri) . '"';
	}

	foreach (array('exclude-result-prefixes', 'extension-element-prefixes', 'id') as $attribute)
	{
		if (in_array(gettype(@$context['xslt_settings'][$attribute]), array('string', 'array')))
			$header .= ' ' . $attribute . '="' . trim(implode(' ', (array) $context['xslt_settings'][$attribute])) . '"';
	}

	$header .= '>';

	return $header;
}

/**
 * Returns the closing tag for the XSLT stylesheet element itself.
 */
function xslt_generic_footer()
{
	global $context;

	return (!empty($context['xslt_settings']['before_footer']) ? (string) $context['xslt_settings']['before_footer'] : "\n") . '</xsl:stylesheet>';
}

/**
 * Returns an XSLT output control element (<xsl:output>) based on the settings
 * defined in $context['xslt_settings']['output'].
 */
function xslt_generic_output_control()
{
	global $context;

	$output_control = '
		<xsl:output';

	foreach ($context['xslt_settings']['output'] as $attribute => $value)
	{
		if (!in_array($attribute, array('method', 'version', 'encoding', 'omit-xml-declaration', 'standalone', 'doctype-public', 'doctype-system', 'cdata-section-elements', 'indent', 'media-type')))
			continue;

		elseif (is_bool($value))
			$output_control .= ' ' . $attribute . '="' . (empty($value) ? 'no' : 'yes') . '"';

		elseif (!empty($value))
			$output_control .= ' ' . $attribute . '="' . trim(implode(' ', (array) $value)) . '"';
	}

	if (empty($context['xslt_settings']['output']['encoding']))
		$output_control .= ' encoding="' . $context['character_set'] . '"';

	$output_control .= '/>';

	return $output_control;
}

/**
 * Returns white space control elements (<xsl:strip-space>/<xsl:preserve-space>)
 * based on the settings defined in $context['xslt_settings']['whitespace'].
 */
function xslt_generic_whitespace_control()
{
	global $context;

	$whitespace_control = '';

	foreach ($context['xslt_settings']['whitespace'] as $strip_preserve => $elements)
	{
		if (!in_array($strip_preserve, array('strip', 'preserve')) || empty($elements))
			continue;

		$whitespace_control .= '
		<xsl:' . $strip_preserve . '-space elements="' . ($elements === true ? '*' : trim(implode(' ', (array) $elements))) . '"/>';
	}

	return $whitespace_control;
}

/**
 * Returns global variable and/or param elements (<xsl:variable>/<xsl:param>)
 * based on the values defined in $context['xslt_settings']['variables'].
 */
function xslt_generic_global_variables()
{
	global $context;

	$variables = '';

	foreach ($context['xslt_settings']['variables'] as $name => $var)
	{
		$element = !empty($var['param']) ? 'param' : 'variable';

		$variables .= '
		<xsl:' . $element . ' name="' . $name . '"';

		if (isset($var['xpath']))
			$variables .= ' select="' . $var['value'] . '"/>';
		else
			$variables .= '>' . (!empty($var['no_cdata_parse']) ? $var['value'] : cdata_parse($var['value'])) . '</xsl:' . $element . '>';
	}

	return $variables;
}

/**
 * Assembles and returns the content of $context['xslt_settings']['custom'].
 */
function xslt_generic_custom()
{
	global $context;

	$custom = '';

	if (!empty($context['xslt_settings']['custom']))
		$custom .= "\n\t\t" . implode("\n\t\t", (array) $context['xslt_settings']['custom']);

	return $custom;
}

/**
 * The root template for a profile export. Creates the shell of the HTML document.
 */
function xslt_export_html_root()
{
	return '
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
											<xsl:value-of select="concat($scripturl, \'?action=help\')"/>
										</xsl:attribute>
										<xsl:value-of select="$txt_help"/>
									</a>
									<xsl:text> | </xsl:text>
									<a>
										<xsl:attribute name="href">
											<xsl:value-of select="concat($scripturl, \'?action=help;sa=rules\')"/>
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
		</xsl:template>';
}

/**
 * Template to show the unique content of the export file.
 */
function xslt_export_html_content_section()
{
	return '
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
		</xsl:template>';
}

/**
 * Template for user profile summary in an export file.
 */
function xslt_export_html_summary()
{
	return '
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
		</xsl:template>';
}

/**
 * Default helper template for listing details inside the profile summary.
 */
function xslt_export_html_detail_default()
{
	return '
		<xsl:template match="*" mode="detailedinfo">
			<xsl:apply-templates select="@label" mode="detailedinfo"/>
			<dd>
				<xsl:value-of select="." disable-output-escaping="yes"/>
			</dd>
		</xsl:template>';
}

/**
 * Helper template to skip things that shouldn't be included in the profile summary.
 */
function xslt_export_html_detail_not_included()
{
	return '
		<xsl:template match="name|link|avatar|online|member_post|personal_message" mode="detailedinfo"/>';
}

/**
 * Helper template to show the label for a given profile detail.
 */
function xslt_export_html_detail_label()
{
	return '
		<xsl:template match="@label" mode="detailedinfo">
			<dt>
				<xsl:value-of select="." disable-output-escaping="yes"/>
				<xsl:text>:</xsl:text>
			</dt>
		</xsl:template>';
}

/**
 * Helper template for listing an email address inside the profile summary.
 */
function xslt_export_html_detail_email()
{
	return '
		<xsl:template match="email" mode="detailedinfo">
			<xsl:apply-templates select="@label" mode="detailedinfo"/>
			<dd>
				<a>
					<xsl:attribute name="href">
						<xsl:text>mailto:</xsl:text>
						<xsl:value-of select="."/>
					</xsl:attribute>
					<xsl:value-of select="."/>
				</a>
			</dd>
		</xsl:template>';
}

/**
 * Helper template for listing a website inside the profile summary.
 */
function xslt_export_html_detail_website()
{
	return '
		<xsl:template match="website" mode="detailedinfo">
			<xsl:apply-templates select="@label" mode="detailedinfo"/>
			<dd>
				<a>
					<xsl:attribute name="href">
						<xsl:value-of select="link"/>
					</xsl:attribute>
					<xsl:value-of select="title"/>
				</a>
			</dd>
		</xsl:template>';
}

/**
 * Helper template for listing IP addresses inside the profile summary.
 */
function xslt_export_html_detail_ip()
{
	return '
		<xsl:template match="ip_addresses" mode="detailedinfo">
			<xsl:apply-templates select="@label" mode="detailedinfo"/>
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
		</xsl:template>';
}

/**
 * Template for displaying a single post in an export file.
 */
function xslt_export_html_member_post()
{
	return '
		<xsl:template match="member_post" mode="posts">
			<div>
				<xsl:attribute name="id">
					<xsl:value-of select="concat(\'member_post_\', id)"/>
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
								<xsl:value-of select="concat(poster/ip/@label, \': \')"/>
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
									<span class="page_number floatright">
										<a>
											<xsl:attribute name="href">
												<xsl:value-of select="link"/>
											</xsl:attribute>
											<xsl:value-of select="concat($txt_id, \': \', id)"/>
										</a>
									</span>
								</h5>
								<span class="smalltext"><xsl:value-of select="time"/></span>
								<xsl:if test="modified_time">
									<span class="smalltext modified floatright mvisible em">
										<xsl:attribute name="id">
											<xsl:value-of select="concat(\'modified_\', id)"/>
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
										<xsl:when test="contains(body/text(), \'[html]\')">
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
												<xsl:value-of select="concat(\'msg_\', id, \'_likes\')"/>
											</xsl:attribute>
											<span><span class="main_icons like"></span> <xsl:value-of select="likes"/></span>
										</li>
									</xsl:if>
								</ul>
								<xsl:call-template name="quickbuttons">
									<xsl:with-param name="toggle_target" select="concat(\'member_post_\', id)"/>
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
		</xsl:template>';
}

/**
 * Template for displaying a single PM in an export file.
 */
function xslt_export_html_personal_message()
{
	return '
		<xsl:template match="personal_message" mode="pms">
			<div class="windowbg">
				<xsl:attribute name="id">
					<xsl:value-of select="concat(\'personal_message_\', id)"/>
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
										<xsl:value-of select="concat(\'subject_\', id)"/>
									</xsl:attribute>
									<xsl:value-of select="subject"/>
									<span class="page_number floatright">
										<a>
											<xsl:attribute name="href">
												<xsl:value-of select="link"/>
											</xsl:attribute>
											<xsl:value-of select="concat($txt_id, \': \', id)"/>
										</a>
									</span>
								</h5>
								<span class="smalltext">
									<strong>
										<xsl:value-of select="concat(recipient[1]/@label, \': \')"/>
									</strong>
									<xsl:apply-templates select="recipient"/>
								</span>
								<br/>
								<span class="smalltext">
									<strong>
										<xsl:value-of select="concat(sent_date/@label, \': \')"/>
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
									<xsl:with-param name="toggle_target" select="concat(\'personal_message_\', id)"/>
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
		</xsl:template>';
}

/**
 * A couple of templates to handle attachments in an export file.
 */
function xslt_export_html_attachments()
{
	return '
		<xsl:template match="attachments">
			<xsl:param name="post_id"/>
			<xsl:if test="attachment">
				<div class="attachments">
					<xsl:attribute name="id">
						<xsl:value-of select="concat(\'msg_\', $post_id, \'_footer\')"/>
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
							<xsl:value-of select="concat(id, \' - \', name)"/>
						</xsl:attribute>
						<img class="centericon" alt="*">
							<xsl:attribute name="src">
								<xsl:value-of select="concat($themeurl, \'/images/icons/clip.png\')"/>
							</xsl:attribute>
						</img>
						<xsl:text> </xsl:text>
						<xsl:value-of select="name"/>
					</a>
					<br/>
					<xsl:text>(</xsl:text>
					<a class="bbc_link">
						<xsl:attribute name="href">
							<xsl:value-of select="concat($scripturl, \'?action=profile;area=dlattach;u=\', $member_id, \';attach=\', id, \';t=\', $dltoken)"/>
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
		</xsl:template>';
}

/**
 * Helper template for printing the user's own info next to the post or personal message.
 */
function xslt_export_html_own_user_info()
{
	return '
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
				<xsl:value-of select="concat(/*/posts/@label, \': \')"/>
				<xsl:value-of select="/*/posts"/>
			</li>
			<xsl:if test="/*/blurb">
				<li class="blurb">
					<xsl:value-of select="/*/blurb"/>
				</li>
			</xsl:if>
		</xsl:template>';
}

/**
 * Helper template for printing the quickbuttons used in the export file
 */
function xslt_export_html_quickbuttons()
{
	return '
		<xsl:template name="quickbuttons">
			<xsl:param name="toggle_target"/>
			<ul class="quickbuttons quickbuttons_post sf-js-enabled sf-arrows" style="touch-action: pan-y;">
				<li>
					<a>
						<xsl:attribute name="onclick">
							<xsl:text>$(\'#</xsl:text>
							<xsl:value-of select="$toggle_target"/>
							<xsl:text> .inner\').toggle();</xsl:text>
						</xsl:attribute>
						<xsl:value-of select="$txt_view_source_button"/>
					</a>
				</li>
			</ul>
		</xsl:template>';
}

/**
 * Helper template for printing a signature
 */
function xslt_export_html_signature()
{
	return '
		<xsl:template name="signature">
			<xsl:if test="/*/signature">
				<div class="signature">
					<xsl:value-of select="/*/signature" disable-output-escaping="yes"/>
				</div>
			</xsl:if>
		</xsl:template>';
}

/**
 * Helper template for printing a list of PM recipients
 */
function xslt_export_html_recipient()
{
	return '
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
		</xsl:template>';
}

/**
 * Helper template for special handling of the contents of the [html] BBCode
 */
function xslt_export_html_bbc_html_splitter()
{
	return '
		<xsl:template name="bbc_html_splitter">
			<xsl:param name="bbc_string"/>
			<xsl:param name="inside_outside" select="outside"/>
			<xsl:choose>
				<xsl:when test="$inside_outside = \'outside\'">
					<xsl:choose>
						<xsl:when test="contains($bbc_string, \'[html]\')">
							<xsl:variable name="following_string">
								<xsl:value-of select="substring-after($bbc_string, \'[html]\')" disable-output-escaping="yes"/>
							</xsl:variable>
							<xsl:value-of select="substring-before($bbc_string, \'[html]\')" disable-output-escaping="yes"/>
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
						<xsl:when test="contains($bbc_string, \'[/html]\')">
							<xsl:variable name="following_string">
								<xsl:value-of select="substring-after($bbc_string, \'[/html]\')" disable-output-escaping="yes"/>
							</xsl:variable>
							<xsl:value-of select="substring-before($bbc_string, \'[/html]\')" disable-output-escaping="no"/>
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
		</xsl:template>';
}

/**
 * Helper templates to build a page index in the export file
 */
function xslt_export_html_page_index()
{
	return '
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
								<xsl:value-of select="concat($dlfilename, \'_\', $prev_page, \'.\', $ext)"/>
							</xsl:attribute>
							<span class="main_icons previous_page"></span>
						</a>
					</xsl:if>

					<xsl:call-template name="page_links"/>

					<xsl:if test="$current_page &lt; $last_page">
						<a class="nav_page">
							<xsl:attribute name="href">
								<xsl:value-of select="concat($dlfilename, \'_\', $next_page, \'.\', $ext)"/>
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
							<xsl:value-of select="concat($dlfilename, \'_\', $page_num, \'.\', $ext)"/>
						</xsl:attribute>
						<xsl:value-of select="$page_num"/>
					</a>
				</xsl:when>
				<xsl:when test="$page_num = 2 or $page_num = ($current_page + 2)">
					<span class="expand_pages" onclick="$(\'.nav_page\').removeClass(\'hidden\'); $(\'.expand_pages\').hide();"> ... </span>
					<a class="nav_page hidden">
						<xsl:attribute name="href">
							<xsl:value-of select="concat($dlfilename, \'_\', $page_num, \'.\', $ext)"/>
						</xsl:attribute>
						<xsl:value-of select="$page_num"/>
					</a>
				</xsl:when>
				<xsl:otherwise>
					<a class="nav_page hidden">
						<xsl:attribute name="href">
							<xsl:value-of select="concat($dlfilename, \'_\', $page_num, \'.\', $ext)"/>
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
		</xsl:template>';
}

/**
 * Template to insert CSS and JavaScript into an export file
 */
function xslt_export_html_css_js()
{
	global $context;

	$template = '
		<xsl:template name="css_js">';

	if (!empty($context['export_css_files']))
	{
		foreach ($context['export_css_files'] as $css_file)
		{
			$template .= '
			<link rel="stylesheet">
					<xsl:attribute name="href">
						<xsl:text>' . $css_file['fileUrl'] . '</xsl:text>
					</xsl:attribute>';

			if (!empty($css_file['options']['attributes']))
			{
				foreach ($css_file['options']['attributes'] as $key => $value)
					$template .= '
					<xsl:attribute name="' . $key . '">
						<xsl:text>' . (is_bool($value) ? $key : $value) . '</xsl:text>
					</xsl:attribute>';
			}

			$template .= '
				</link>';
		}
	}

	if (!empty($context['export_css_header']))
	{
		$template .=  '
			<style><![CDATA[' . "\n" . implode("\n", $context['export_css_header']) . "\n" . ']]>
			</style>';
	}

	if (!empty($context['export_javascript_vars']))
	{
		$template .=  '
			<script><![CDATA[';

		foreach ($context['export_javascript_vars'] as $var => $val)
			$template .= "\nvar " . $var . (!empty($val) ? ' = ' . $val : '') . ';';

		$template .= "\n" . ']]>
			</script>';
	}

	if (!empty($context['export_javascript_files']))
	{
		foreach ($context['export_javascript_files'] as $js_file)
		{
			$template .= '
				<script>
					<xsl:attribute name="src">
						<xsl:text>' . $js_file['fileUrl'] . '</xsl:text>
					</xsl:attribute>';

			if (!empty($js_file['options']['attributes']))
			{
				foreach ($js_file['options']['attributes'] as $key => $value)
					$template .= '
					<xsl:attribute name="' . $key . '">
						<xsl:text>' . (is_bool($value) ? $key : $value) . '</xsl:text>
					</xsl:attribute>';
			}

			$template .= '
				</script>';
		}
	}

	if (!empty($context['export_javascript_inline']['standard']))
	{
		$template .=  '
			<script><![CDATA[' . "\n" . implode("\n", $context['export_javascript_inline']['standard']) . "\n" . ']]>
			</script>';
	}

	if (!empty($context['export_javascript_inline']['defer']))
	{
		$template .= '
			<script><![CDATA[' . "\n" . 'window.addEventListener("DOMContentLoaded", function() {';

		$template .= "\n\t" . str_replace("\n", "\n\t", implode("\n", $context['export_javascript_inline']['defer']));

		$template .= "\n" . '});'. "\n" . ']]>
			</script>';
	}

	$template .= '
		</xsl:template>';

	return $template;
}

?>