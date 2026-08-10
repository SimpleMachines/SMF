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

declare(strict_types=1);

namespace SMF\WebAuthn;

/**
 * Thrown when a passkey ceremony cannot be completed.
 *
 * SMF throws plain exceptions nearly everywhere, and this is deliberately not
 * one of those: verifying a passkey means a few dozen separate checks, and the
 * caller has to be able to catch every one of them without also catching a
 * database failure or a bug. The message says exactly which check failed and is
 * meant for the error log, never for the member -- telling somebody signing in
 * which part of their evidence was wrong helps an attacker far more than it
 * helps them.
 */
class WebAuthnException extends \Exception {}
