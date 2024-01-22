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

namespace SMF\MailAgent\APIs;

use SMF\MailAgent\MailAgentInterface;

/**
 * Sends mail via SMTP using TLS
 */
class SMTPTLS extends SMTP implements MailAgentInterface
{
	/**
	 * @var bool
	 *
	 * Enabled in this class and used in the parent class to enable TLS connections.
	 */
	public bool $useTLS = true;
}

?>