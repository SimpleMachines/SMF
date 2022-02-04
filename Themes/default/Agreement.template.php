<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

// The main sub template - show the agreement and/or privacy policy
function template_main()
{
	global $context, $scripturl, $txt;

	if (!empty($context['accept_doc']))
		echo '
	<form action="', $scripturl, '?action=acceptagreement;doc=', $context['accept_doc'], '" method="post">';

	if (!empty($context['agreement']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['agreement' . (!empty($context['can_accept_agreement']) ? '_updated' : '')], '</h3>
		</div>';

		if (!empty($context['can_accept_agreement']))
		{
			echo '
		<div class="information noup">
			', $txt['agreement_updated_desc'], '
		</div>';
		}
		elseif (!empty($context['agreement_accepted_date']))
		{
			echo '
		<div class="information noup">
			', sprintf($txt['agreement_accepted'], timeformat($context['agreement_accepted_date'], false)), '
		</div>';
		}

		echo '
		<div class="windowbg noup">
			', $context['agreement'], '
		</div>';
	}

	if (!empty($context['privacy_policy']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['privacy_policy' . (!empty($context['can_accept_privacy_policy']) ? '_updated' : '')], '</h3>
		</div>';

		if (!empty($context['can_accept_privacy_policy']))
		{
			echo '
		<div class="information noup">
			', $txt['privacy_policy_updated_desc'], '
		</div>';
		}
		elseif (!empty($context['privacy_policy_accepted_date']))
		{
			echo '
		<div class="information noup">
			', sprintf($txt['privacy_policy_accepted'], timeformat($context['privacy_policy_accepted_date'], false)), '
		</div>';
		}

		echo '
		<div class="windowbg noup">
			', $context['privacy_policy'], '
		</div>';
	}

	if (!empty($context['accept_doc']))
		echo '
		<div id="confirm_buttons">
			<input type="submit" value="', $txt['agree'], '" class="button">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</div>
	</form>';
}

?>