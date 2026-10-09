<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\Theme;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This shows any deferred JavaScript and closes out the HTML
 */
?><?php /* Load in any javascript that could be deferred to the end of the page */ ?><?php Theme::template_javascript(true); ?>

</body>
</html>