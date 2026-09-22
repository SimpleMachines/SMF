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

declare(strict_types=1);

namespace SMF\Infrastructure;

use Psr\Container\ContainerExceptionInterface;

/**
 * Thrown when something asks for a service it has no access to.
 *
 * The services a package may use are the ones it declared in its
 * package-info.xml and the administrator granted when installing it.
 */
class ServiceAccessException extends \RuntimeException implements ContainerExceptionInterface {}
