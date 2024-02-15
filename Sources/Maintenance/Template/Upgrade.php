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

declare(strict_types=1);

namespace SMF\Maintenance\Template;

use SMF\Lang;
use SMF\Maintenance;
use SMF\Maintenance\Template;
use SMF\Maintenance\TemplateInterface;

/**
 * Template for Upgrader
 */
class Upgrade implements TemplateInterface
{
	/**
	 * Upper template for upgrader.
	 */
	public static function upper(): void
	{
		if (count(Maintenance::$tool->getSteps()) - 1 !== (int) Maintenance::getCurrentStep()) {
		echo '
        <form action="', Maintenance::getSelf(), (Maintenance::$query_string !== '' ? '?' . Maintenance::$query_string : ''), '" method="post">';
		}
	}

	/**
	 * Lower template for upgrader.
	 */
	public static function lower(): void
	{
		if (!empty(Maintenance::$context['continue']) || !empty(Maintenance::$context['skip'])) {
			echo '
                                <div class="floatright">';

			if (!empty(Maintenance::$context['continue'])) {
				echo '
                                    <input type="submit" id="contbutt" name="contbutt" value="', Lang::$txt['action_continue'], '" onclick="return submitThisOnce(this);" class="button">';
			}

			if (!empty(Maintenance::$context['skip'])) {
				echo '
                                    <input type="submit" id="skip" name="skip" value="', Lang::$txt['action_skip'], '" onclick="return submitThisOnce(this);" class="button">';
			}
			echo '
                                </div>';
		}

		// Show the closing form tag and other data only if not in the last step
		if (count(Maintenance::$tool->getSteps()) - 1 !== (int) Maintenance::getCurrentStep()) {
			echo '
        </form>';
		}
	}

	/**
	 * Welcome page for upgrader.
	 */
	public static function welcomeLogin(): void
	{
		echo '
        <div id="no_js_container">
            <div class="errorbox">
            <h3>', Lang::$txt['critical_error'], '</h3>
            ', Lang::$txt['error_no_javascript'], '
            </div>
        </div>
        <script>
            document.getElementById(\'no_js_container\').classList.add(\'hidden\');
        </script>';

	}
}

?>