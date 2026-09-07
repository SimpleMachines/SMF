<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * Lists the identity providers that have been set up.
 */
function template_authentication_list()
{
	echo '
		<div id="admincenter">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('authentication_providers', file: 'ManageSettings'), '</h3>
			</div>
			<div class="information">
				', Lang::getTxt('authentication_providers_desc', file: 'ManageSettings'), '
			</div>';

	if (empty(Utils::$context['providers'])) {
		echo '
			<div class="windowbg">
				<p>', Lang::getTxt('authentication_no_providers', file: 'ManageSettings'), '</p>
			</div>';
	} else {
		echo '
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th class="lefttext">', Lang::getTxt('authentication_title', file: 'ManageSettings'), '</th>
						<th class="lefttext">', Lang::getTxt('authentication_issuer', file: 'ManageSettings'), '</th>
						<th>', Lang::getTxt('authentication_enabled', file: 'ManageSettings'), '</th>
						<th></th>
					</tr>
				</thead>
				<tbody>';

		foreach (Utils::$context['providers'] as $provider) {
			echo '
					<tr class="windowbg">
						<td>', Utils::htmlspecialchars($provider->title), '</td>
						<td>', Utils::htmlspecialchars($provider->issuer), '</td>
						<td class="centertext">', $provider->enabled ? Lang::getTxt('yes', file: 'General') : Lang::getTxt('no', file: 'General'), '</td>
						<td class="righttext">
							<a class="button" href="', Config::$scripturl, '?action=admin;area=authentication;sa=edit;provider=', $provider->id, '">', Lang::getTxt('modify', file: 'General'), '</a>
							<a class="button" href="', Config::$scripturl, '?action=admin;area=authentication;sa=test;provider=', $provider->id, ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::getTxt('authentication_test', file: 'ManageSettings'), '</a>
							<a class="button" href="', Config::$scripturl, '?action=admin;area=authentication;sa=delete;provider=', $provider->id, ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" data-confirm="', Lang::getTxt('authentication_delete_confirm', file: 'ManageSettings'), '">', Lang::getTxt('delete', file: 'General'), '</a>
						</td>
					</tr>';
		}

		echo '
				</tbody>
			</table>';
	}

	echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('authentication_add', file: 'ManageSettings'), '</h3>
			</div>
			<div class="windowbg">
				<p>
					<a class="button" href="', Config::$scripturl, '?action=admin;area=authentication;sa=edit">', Lang::getTxt('authentication_add_generic', file: 'ManageSettings'), '</a>';

	foreach (Utils::$context['presets'] as $key => $preset) {
		echo '
					<a class="button" href="', Config::$scripturl, '?action=admin;area=authentication;sa=edit;preset=', $key, '">', Utils::htmlspecialchars($preset['title']), '</a>';
	}

	echo '
				</p>
			</div>
		</div><!-- #admincenter -->';
}

/**
 * The form for one identity provider.
 */
function template_authentication_edit()
{
	$provider = Utils::$context['provider'];

	echo '
		<div id="admincenter">
			<form action="', Config::$scripturl, '?action=admin;area=authentication;sa=save;provider=', $provider->id, '" method="post" accept-charset="UTF-8">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::getTxt('authentication_provider', file: 'ManageSettings'), '</h3>
				</div>
				<div class="windowbg">
					<dl class="settings">
						<dt>
							<strong>', Lang::getTxt('authentication_title', file: 'ManageSettings'), '</strong><br>
							<span class="smalltext">', Lang::getTxt('authentication_title_desc', file: 'ManageSettings'), '</span>
						</dt>
						<dd>
							<input type="text" name="title" size="40" value="', Utils::htmlspecialchars($provider->title), '" required>
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_issuer', file: 'ManageSettings'), '</strong><br>
							<span class="smalltext">', Lang::getTxt('authentication_issuer_desc', file: 'ManageSettings'), '</span>
						</dt>
						<dd>
							<input type="text" name="issuer" size="60" value="', Utils::htmlspecialchars($provider->issuer), '" required>
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_client_id', file: 'ManageSettings'), '</strong>
						</dt>
						<dd>
							<input type="text" name="client_id" size="60" value="', Utils::htmlspecialchars($provider->client_id), '">
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_client_secret', file: 'ManageSettings'), '</strong><br>
							<span class="smalltext">', Lang::getTxt('authentication_client_secret_desc', file: 'ManageSettings'), '</span>
						</dt>
						<dd>
							<input type="password" name="client_secret" size="60" value="" autocomplete="new-password">
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_scopes', file: 'ManageSettings'), '</strong><br>
							<span class="smalltext">', Lang::getTxt('authentication_scopes_desc', file: 'ManageSettings'), '</span>
						</dt>
						<dd>
							<input type="text" name="scopes" size="40" value="', Utils::htmlspecialchars($provider->scopes), '">
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_redirect_uri', file: 'ManageSettings'), '</strong><br>
							<span class="smalltext">', Lang::getTxt('authentication_redirect_uri_desc', file: 'ManageSettings'), '</span>
						</dt>
						<dd>
							<code>', Utils::htmlspecialchars(Utils::$context['redirect_uri']), '</code>
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_enabled', file: 'ManageSettings'), '</strong>
						</dt>
						<dd>
							<input type="checkbox" name="enabled"', $provider->enabled ? ' checked' : '', '>
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_order', file: 'ManageSettings'), '</strong>
						</dt>
						<dd>
							<input type="number" name="provider_order" size="4" value="', $provider->order, '">
						</dd>
					</dl>
					<div class="cat_bar">
						<h3 class="catbg">', Lang::getTxt('authentication_policy', file: 'ManageSettings'), '</h3>
					</div>
					<dl class="settings">
						<dt>
							<strong>', Lang::getTxt('authentication_allow_registration', file: 'ManageSettings'), '</strong><br>
							<span class="smalltext">', Lang::getTxt('authentication_allow_registration_desc', file: 'ManageSettings'), '</span>
						</dt>
						<dd>
							<input type="checkbox" name="allow_registration"', !empty($provider->settings['allow_registration']) ? ' checked' : '', '>
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_link_by_email', file: 'ManageSettings'), '</strong><br>
							<span class="smalltext alert">', Lang::getTxt('authentication_link_by_email_desc', file: 'ManageSettings'), '</span>
						</dt>
						<dd>
							<input type="checkbox" name="link_by_verified_email"', !empty($provider->settings['link_by_verified_email']) ? ' checked' : '', '>
						</dd>
						<dt>
							<strong>', Lang::getTxt('authentication_allow_private_host', file: 'ManageSettings'), '</strong><br>
							<span class="smalltext">', Lang::getTxt('authentication_allow_private_host_desc', file: 'ManageSettings'), '</span>
						</dt>
						<dd>
							<input type="checkbox" name="allow_private_host"', !empty($provider->settings['allow_private_host']) ? ' checked' : '', '>
						</dd>
					</dl>
					<div class="righttext">
						<input type="submit" value="', Lang::getTxt('save', file: 'General'), '" class="button">
					</div>
				</div><!-- .windowbg -->
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-authp_token_var'], '" value="', Utils::$context['admin-authp_token'], '">
			</form>
		</div><!-- #admincenter -->';
}

/**
 * The result of asking a provider to describe itself.
 */
function template_authentication_test()
{
	echo '
		<div id="admincenter">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('authentication_test', file: 'ManageSettings'), ' &mdash; ', Utils::htmlspecialchars(Utils::$context['provider']->title), '</h3>
			</div>
			<div class="windowbg">';

	if (empty(Utils::$context['test_endpoints'])) {
		echo '
				<div class="errorbox">
					', Lang::getTxt('authentication_test_failed', file: 'ManageSettings'), '
					<br><code>', Utils::htmlspecialchars(Utils::$context['test_error']), '</code>
				</div>';
	} else {
		echo '
				<div class="infobox">', Lang::getTxt('authentication_test_ok', file: 'ManageSettings'), '</div>
				<dl class="settings">';

		foreach (Utils::$context['test_endpoints'] as $name => $url) {
			echo '
					<dt><strong>', $name, '</strong></dt>
					<dd><code>', Utils::htmlspecialchars($url), '</code></dd>';
		}

		echo '
				</dl>';
	}

	echo '
				<p><a class="button" href="', Config::$scripturl, '?action=admin;area=authentication">', Lang::getTxt('back', file: 'General'), '</a></p>
			</div><!-- .windowbg -->
		</div><!-- #admincenter -->';
}
