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

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when nothing provides the service that was asked for.
 *
 * Either no package provides it, or the one that did has had its access
 * withdrawn, which looks the same to whoever asked.
 */
class ServiceNotFoundException extends \RuntimeException implements NotFoundExceptionInterface {}
