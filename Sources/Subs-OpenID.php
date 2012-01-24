<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file handles all of the OpenID interfacing and communications.
	void smf_openID_validate(string openid_url, bool allow_immediate_validation = true)
		- openid_uri is the URI given by the user
		- Validates the URI and changes it to a fully canonicalize URL
		- Determines the IDP server and delegation
		- optional array of fields to restore when validation complete.
		- Redirects the user to the IDP for validation
*/

function smf_openID_validate($openid_uri, $return = false, $save_fields = array(), $return_action = null)
{
	global $sourcedir, $scripturl, $boardurl, $modSettings;

	$openid_url = smf_openID_canonize($openid_uri);

	$response_data = smf_openID_getServerInfo($openid_url);
	if ($response_data === false)
		return 'no_data';

	if (($assoc = smf_openID_getAssociation($response_data['server'])) == null)
		$assoc = smf_openID_makeAssociation($response_data['server']);

	// Before we go wherever it is we are going, store the GET and POST data, because it might be useful when we get back.
	$request_time = time();
	// Just in case they are doing something else at this time.
	while (isset($_SESSION['openid']['saved_data'][$request_time]))
		$request_time = md5($request_time);

	$_SESSION['openid']['saved_data'][$request_time] = array(
		'get' => $_GET,
		'post' => $_POST,
		'openid_uri' => $openid_url,
		'cookieTime' => $modSettings['cookieTime'],
	);

	$parameters = array(
		'openid.mode=checkid_setup',
		'openid.trust_root=' . urlencode($scripturl),
		'openid.identity=' . urlencode(empty($response_data['delegate']) ? $openid_url : $response_data['delegate']),
		'openid.assoc_handle=' . urlencode($assoc['handle']),
		'openid.return_to=' . urlencode($scripturl . '?action=openidreturn&sa=' . (!empty($return_action) ? $return_action : $_REQUEST['action']) . '&t=' . $request_time . (!empty($save_fields) ? '&sf=' . base64_encode(serialize($save_fields)) : '')),
	);

	// If they are logging in but don't yet have an account or they are registering, let's request some additional information
	if (($_REQUEST['action'] == 'login2' && !smf_openid_member_exists($openid_url)) || ($_REQUEST['action'] == 'register' || $_REQUEST['action'] == 'register2'))
	{
		// Email is required.
		$parameters[] = 'openid.sreg.required=email';
		// The rest is just optional.
		$parameters[] = 'openid.sreg.optional=nickname,dob,gender';
	}

	$redir_url = $response_data['server'] . '?' . implode('&', $parameters);

	if ($return)
		return $redir_url;
	else
		redirectexit($redir_url);
}

// Revalidate a user using OpenID. Note that this function will not return when authentication is required.
function smf_openID_revalidate()
{
	global $user_settings;

	if (isset($_SESSION['openid_revalidate_time']) && $_SESSION['openid_revalidate_time'] > time() - 60)
	{
		unset($_SESSION['openid_revalidate_time']);
		return true;
	}
	else
		smf_openID_validate($user_settings['openid_uri'], false, null, 'revalidate');

	// We shouldn't get here.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

function smf_openID_getAssociation($server, $handle = null, $no_delete = false)
{
	global $smcFunc;

	if (!$no_delete)
	{
		// Delete the already expired associations.
		$smcFunc['db_query']('openid_delete_assoc_old', '
			DELETE FROM {db_prefix}openid_assoc
			WHERE expires <= {int:current_time}',
			array(
				'current_time' => time(),
			)
		);
	}

	// Get the association that has the longest lifetime from now.
	$request = $smcFunc['db_query']('openid_select_assoc', '
		SELECT server_url, handle, secret, issued, expires, assoc_type
		FROM {db_prefix}openid_assoc
		WHERE server_url = {string:server_url}' . ($handle === null ? '' : '
			AND handle = {string:handle}') . '
		ORDER BY expires DESC',
		array(
			'server_url' => $server,
			'handle' => $handle,
		)
	);

	if ($smcFunc['db_num_rows']($request) == 0)
		return null;

	$return = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $return;
}

function smf_openID_makeAssociation($server)
{
	global $smcFunc, $modSettings, $p;

	$parameters = array(
		'openid.mode=associate',
	);

	// We'll need to get our keys for the Diffie-Hellman key exchange.
	$dh_keys = smf_openID_setup_DH();

	// If we don't support DH we'll have to see if the provider will accept no encryption.
	if ($dh_keys === false)
		$parameters[] = 'openid.session_type=';
	else
	{
		$parameters[] = 'openid.session_type=DH-SHA1';
		$parameters[] = 'openid.dh_consumer_public=' . urlencode(base64_encode(long_to_binary($dh_keys['public'])));
		$parameters[] = 'openid.assoc_type=HMAC-SHA1';
	}

	// The data to post to the server.
	$post_data = implode('&', $parameters);
	$data = fetch_web_data($server, $post_data);

	// Parse the data given.
	preg_match_all('~^([^:]+):(.+)$~m', $data, $matches);
	$assoc_data = array();

	foreach ($matches[1] as $key => $match)
		$assoc_data[$match] = $matches[2][$key];

	if (!isset($assoc_data['assoc_type']) || (empty($assoc_data['mac_key']) && empty($assoc_data['enc_mac_key'])))
		fatal_lang_error('openid_server_bad_response');

	// Clean things up a bit.
	$handle = isset($assoc_data['assoc_handle']) ? $assoc_data['assoc_handle'] : '';
	$issued = time();
	$expires = $issued + min((int)$assoc_data['expires_in'], 60);
	$assoc_type = isset($assoc_data['assoc_type']) ? $assoc_data['assoc_type'] : '';

	// !!! Is this really needed?
	foreach (array('dh_server_public', 'enc_mac_key') as $key)
		if (isset($assoc_data[$key]))
			$assoc_data[$key] = str_replace(' ', '+', $assoc_data[$key]);

	// Figure out the Diffie-Hellman secret.
	if (!empty($assoc_data['enc_mac_key']))
	{
		$dh_secret = bcpowmod(binary_to_long(base64_decode($assoc_data['dh_server_public'])), $dh_keys['private'], $p);
		$secret = base64_encode(binary_xor(sha1_raw(long_to_binary($dh_secret)), base64_decode($assoc_data['enc_mac_key'])));
	}
	else
		$secret = $assoc_data['mac_key'];

	// Store the data
	$smcFunc['db_insert']('replace',
		'{db_prefix}openid_assoc',
		array('server_url' => 'string', 'handle' => 'string', 'secret' => 'string', 'issued' => 'int', 'expires' => 'int', 'assoc_type' => 'string'),
		array($server, $handle, $secret, $issued, $expires, $assoc_type),
		array('server_url', 'handle')
	);

	return array(
		'server' => $server,
		'handle' => $assoc_data['assoc_handle'],
		'secret' => $secret,
		'issued' => $issued,
		'expires' => $expires,
		'assoc_type' => $assoc_data['assoc_type'],
	);
}

function smf_openID_removeAssociation($handle)
{
	global $smcFunc;

	$smcFunc['db_query']('openid_remove_association', '
		DELETE FROM {db_prefix}openid_assoc
		WHERE handle = {string:handle}',
		array(
			'handle' => $handle,
		)
	);
}

function smf_openID_return()
{
	global $smcFunc, $user_info, $user_profile, $sourcedir, $modSettings, $context, $sc, $user_settings;

	// Is OpenID even enabled?
	if (empty($modSettings['enableOpenID']))
		fatal_lang_error('no_access', false);

	if (!isset($_GET['openid_mode']))
		fatal_lang_error('openid_return_no_mode', false);

	// !!! Check for error status!
	if ($_GET['openid_mode'] != 'id_res')
		fatal_lang_error('openid_not_resolved');

	// SMF has this annoying habit of removing the + from the base64 encoding.  So lets put them back.
	foreach (array('openid_assoc_handle', 'openid_invalidate_handle', 'openid_sig', 'sf') as $key)
		if (isset($_GET[$key]))
			$_GET[$key] = str_replace(' ', '+', $_GET[$key]);

	// Did they tell us to remove any associations?
	if (!empty($_GET['openid_invalidate_handle']))
		smf_openid_removeAssociation($_GET['openid_invalidate_handle']);

	$server_info = smf_openid_getServerInfo($_GET['openid_identity']);

	// Get the association data.
	$assoc = smf_openID_getAssociation($server_info['server'], $_GET['openid_assoc_handle'], true);
	if ($assoc === null)
		fatal_lang_error('openid_no_assoc');

	$secret = base64_decode($assoc['secret']);

	$signed = explode(',', $_GET['openid_signed']);
	$verify_str = '';
	foreach ($signed as $sign)
	{
		$verify_str .= $sign . ':' . strtr($_GET['openid_' . str_replace('.', '_', $sign)], array('&amp;' => '&')) . "\n";
	}

	$verify_str = base64_encode(sha1_hmac($verify_str, $secret));

	if ($verify_str != $_GET['openid_sig'])
	{
		fatal_lang_error('openid_sig_invalid', 'critical');
	}

	if (!isset($_SESSION['openid']['saved_data'][$_GET['t']]))
		fatal_lang_error('openid_load_data');

	$openid_uri = $_SESSION['openid']['saved_data'][$_GET['t']]['openid_uri'];
	$modSettings['cookieTime'] = $_SESSION['openid']['saved_data'][$_GET['t']]['cookieTime'];

	if (empty($openid_uri))
		fatal_lang_error('openid_load_data');

	// Any save fields to restore?
	$context['openid_save_fields'] = isset($_GET['sf']) ? unserialize(base64_decode($_GET['sf'])) : array();

	// Is there a user with this OpenID_uri?
	$result = $smcFunc['db_query']('', '
		SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
			openid_uri
		FROM {db_prefix}members
		WHERE openid_uri = {string:openid_uri}',
		array(
			'openid_uri' => $openid_uri,
		)
	);

	$member_found = $smcFunc['db_num_rows']($result);

	if (!$member_found && isset($_GET['sa']) && $_GET['sa'] == 'change_uri' && !empty($_SESSION['new_openid_uri']) && $_SESSION['new_openid_uri'] == $openid_uri)
	{
		// Update the member.
		updateMemberData($user_settings['id_member'], array('openid_uri' => $openid_uri));

		unset($_SESSION['new_openid_uri']);
		$_SESSION['openid'] = array(
			'verified' => true,
			'openid_uri' => $openid_uri,
		);

		// Send them back to profile.
		redirectexit('action=profile;area=authentication;updated');
	}
	elseif (!$member_found)
	{
		// Store the received openid info for the user when returned to the registration page.
		$_SESSION['openid'] = array(
			'verified' => true,
			'openid_uri' => $openid_uri,
		);
		if (isset($_GET['openid_sreg_nickname']))
			$_SESSION['openid']['nickname'] = $_GET['openid_sreg_nickname'];
		if (isset($_GET['openid_sreg_email']))
			$_SESSION['openid']['email'] = $_GET['openid_sreg_email'];
		if (isset($_GET['openid_sreg_dob']))
			$_SESSION['openid']['dob'] = $_GET['openid_sreg_dob'];
		if (isset($_GET['openid_sreg_gender']))
			$_SESSION['openid']['gender'] = $_GET['openid_sreg_gender'];

		// Were we just verifying the registration state?
		if (isset($_GET['sa']) && $_GET['sa'] == 'register2')
		{
			require_once($sourcedir . '/Register.php');
			return Register2(true);
		}
		else
			redirectexit('action=register');
	}
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'revalidate' && $user_settings['openid_uri'] == $openid_uri)
	{
		$_SESSION['openid_revalidate_time'] = time();

		// Restore the get data.
		require_once($sourcedir . '/Subs-Auth.php');
		$_SESSION['openid']['saved_data'][$_GET['t']]['get']['openid_restore_post'] = $_GET['t'];
		$query_string = construct_query_string($_SESSION['openid']['saved_data'][$_GET['t']]['get']);

		redirectexit($query_string);
	}
	else
	{
		$user_settings = $smcFunc['db_fetch_assoc']($result);
		$smcFunc['db_free_result']($result);

		$user_settings['passwd'] = sha1(strtolower($user_settings['member_name']) . $secret);
		$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);

		updateMemberData($user_settings['id_member'], array('passwd' => $user_settings['passwd'], 'password_salt' => $user_settings['password_salt']));

		// Cleanup on Aisle 5.
		$_SESSION['openid'] = array(
			'verified' => true,
			'openid_uri' => $openid_uri,
		);

		require_once($sourcedir . '/LogInOut.php');

		if (!checkActivation())
			return;

		DoLogin();
	}
}

function smf_openID_canonize($uri)
{
	// !!! Add in discovery.

	if (strpos($uri, 'http://') !== 0 && strpos($uri, 'https://') !== 0)
		$uri = 'http://' . $uri;

	if (strpos(substr($uri, strpos($uri, '://') + 3), '/') === false)
		$uri .= '/';

	return $uri;
}

function smf_openid_member_exists($url)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('openid_member_exists', '
		SELECT mem.id_member, mem.member_name
		FROM {db_prefix}members AS mem
		WHERE mem.openid_uri = {string:openid_uri}',
		array(
			'openid_uri' => $url,
		)
	);
	$member = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $member;
}

// Prepare for a Diffie-Hellman key exchange.
function smf_openID_setup_DH($regenerate = false)
{
	global $p, $g;

	// First off, do we have BC Math available?
	if (!function_exists('bcpow'))
		return false;

	// Defined in OpenID spec.
	$p = '155172898181473697471232257763715539915724801966915404479707795314057629378541917580651227423698188993727816152646631438561595825688188889951272158842675419950341258706556549803580104870537681476726513255747040765857479291291572334510643245094715007229621094194349783925984760375594985848253359305585439638443';
	$g = '2';

	// Make sure the scale is set.
	bcscale(0);

	return smf_openID_get_keys($regenerate);
}

function smf_openID_get_keys($regenerate)
{
	global $modSettings, $p, $g;

	// Ok lets take the easy way out, are their any keys already defined for us? They are changed in the daily maintenance scheduled task.
	if (!empty($modSettings['dh_keys']) && !$regenerate)
	{
		// Sweeeet!
		list ($public, $private) = explode("\n", $modSettings['dh_keys']);
		return array(
			'public' => base64_decode($public),
			'private' => base64_decode($private),
		);
	}

	// Dang it, now I have to do math.  And it's not just ordinary math, its the evil big interger math.  This will take a few seconds.
	$private = smf_openid_generate_private_key();
	$public = bcpowmod($g, $private, $p);

	// Now that we did all that work, lets save it so we don't have to keep doing it.
	$keys = array('dh_keys' => base64_encode($public) . "\n" . base64_encode($private));
	updateSettings($keys);

	return array(
		'public' => $public,
		'private' => $private,
	);
}

function smf_openid_generate_private_key()
{
	global $p;
	static $cache = array();

	$byte_string = long_to_binary($p);

	if (isset($cache[$byte_string]))
		list ($dup, $num_bytes) = $cache[$byte_string];
	else
	{
		$num_bytes = strlen($byte_string) - ($byte_string[0] == "\x00" ? 1 : 0);

		$max_rand = bcpow(256, $num_bytes);

		$dup = bcmod($max_rand, $num_bytes);

		$cache[$byte_string] = array($dup, $num_bytes);
	}

	do
	{
		$str = '';
		for ($i = 0; $i < $num_bytes; $i += 4)
			$str .= pack('L', mt_rand());

		$bytes = "\x00" . $str;

		$num = binary_to_long($bytes);
	} while (bccomp($num, $dup) < 0);

	return bcadd(bcmod($num, $p), 1);
}

function smf_openID_getServerInfo($openid_url)
{
	global $sourcedir;

	require_once($sourcedir . '/Subs-Package.php');

	// Get the html and parse it for the openid variable which will tell us where to go.
	$webdata = fetch_web_data($openid_url);

	if (empty($webdata))
		return false;

	$response_data = array();

	// Some OpenID servers have strange but still valid HTML which makes our job hard.
	if (preg_match_all('~<link([\s\S]*?)/?>~i', $webdata, $link_matches) == 0)
		fatal_lang_error('openid_server_bad_response');

	foreach ($link_matches[1] as $link_match)
	{
		if (preg_match('~rel="([\s\S]*?)"~i', $link_match, $rel_match) == 0 || preg_match('~href="([\s\S]*?)"~i', $link_match, $href_match) == 0)
			continue;

		$rels = preg_split('~\s+~', $rel_match[1]);
		foreach ($rels as $rel)
			if (preg_match('~openid2?\.(server|delegate|provider)~i', $rel, $match) != 0)
				$response_data[$match[1]] = $href_match[1];
	}

	if (empty($response_data['server']))
		if (empty($response_data['provider']))
			fatal_lang_error('openid_server_bad_response');
		else
			$response_data['server'] = $response_data['provider'];

	return $response_data;
}

function sha1_hmac($data, $key)
{

	if (strlen($key) > 64)
		$key = sha1_raw($key);

	// Pad the key if need be.
	$key = str_pad($key, 64, chr(0x00));
	$ipad = str_repeat(chr(0x36), 64);
	$opad = str_repeat(chr(0x5c), 64);
	$hash1 = sha1_raw(($key ^ $ipad) . $data);
	$hmac = sha1_raw(($key ^ $opad) . $hash1);
	return $hmac;
}

function sha1_raw($text)
{
	if (version_compare(PHP_VERSION, '5.0.0') >= 0)
		return sha1($text, true);

	$hex = sha1($text);
	$raw = '';
	for ($i = 0; $i < 40; $i += 2)
	{
		$hexcode = substr($hex, $i, 2);
		$charcode = (int) base_convert($hexcode, 16, 10);
		$raw .= chr($charcode);
	}

	return $raw;
}

function binary_to_long($str)
{
	$bytes = array_merge(unpack('C*', $str));

	$n = 0;

	foreach ($bytes as $byte)
	{
		$n = bcmul($n, 256);
		$n = bcadd($n, $byte);
	}

	return $n;
}

function long_to_binary($value)
{
	$cmp = bccomp($value, 0);
	if ($cmp < 0)
		fatal_error('Only non-negative integers allowed.');

	if ($cmp == 0)
		return "\x00";

	$bytes = array();

	while (bccomp($value, 0) > 0)
	{
		array_unshift($bytes, bcmod($value, 256));
		$value = bcdiv($value, 256);
	}

	if ($bytes && ($bytes[0] > 127))
		array_unshift($bytes, 0);

	$return = '';
	foreach ($bytes as $byte)
		$return .= pack('C', $byte);

	return $return;
}

function binary_xor($num1, $num2)
{
	$return = '';

	for ($i = 0; $i < strlen($num2); $i++)
		$return .= $num1[$i] ^ $num2[$i];

	return $return;
}

// PHP 4 didn't have bcpowmod.
if (!function_exists('bcpowmod') && function_exists('bcpow'))
{
	function bcpowmod($num1, $num2, $num3)
	{
		return bcmod(bcpow($num1, $num2), $num3);
	}
}

?>