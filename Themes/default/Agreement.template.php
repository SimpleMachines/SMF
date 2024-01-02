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

use SMF\Config;
use SMF\Lang;
use SMF\Time;
use SMF\Utils;

// The main sub template - show the agreement and/or privacy policy
function template_main()
{
	if (!empty(Utils::$context['accept_doc']))
		echo '
	<form action="', Config::$scripturl, '?action=acceptagreement;doc=', Utils::$context['accept_doc'], '" method="post">';

	if (!empty(Utils::$context['agreement']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['agreement' . (!empty(Utils::$context['can_accept_agreement']) ? '_updated' : '')], '</h3>
		</div>';

		if (!empty(Utils::$context['can_accept_agreement']))
		{
			echo '
		<div class="information noup">
			', Lang::$txt['agreement_updated_desc'], '
		</div>';
		}
		elseif (!empty(Utils::$context['agreement_accepted_date']))
		{
			echo '
		<div class="information noup">
			', sprintf(Lang::$txt['agreement_accepted'], Time::create('@' . Utils::$context['agreement_accepted_date'])->format(null, false)), '
		</div>';
		}

		echo '
		<div class="windowbg noup">
			', Utils::$context['agreement'], '
		</div>';
	}

	if (!empty(Utils::$context['privacy_policy']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['privacy_policy' . (!empty(Utils::$context['can_accept_privacy_policy']) ? '_updated' : '')], '</h3>
		</div>';

		if (!empty(Utils::$context['can_accept_privacy_policy']))
		{
			echo '
		<div class="information noup">
			', Lang::$txt['privacy_policy_updated_desc'], '
		</div>';
		}
		elseif (!empty(Utils::$context['privacy_policy_accepted_date']))
		{
			echo '
		<div class="information noup">
			', sprintf(Lang::$txt['privacy_policy_accepted'], Time::create('@' . Utils::$context['privacy_policy_accepted_date'])->format(null, false)), '
		</div>';
		}

		echo '
		<div class="windowbg noup">
			', Utils::$context['privacy_policy'], '
		</div>';
	}

	if (!empty(Utils::$context['accept_doc']))
		echo '
		<div id="confirm_buttons">
			<input type="submit" value="', Lang::$txt['agree'], '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
		</div>
	</form>';
}

?>