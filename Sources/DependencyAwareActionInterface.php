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

namespace SMF;

/**
 * Defines an action whose service dependencies are resolved by the service container.
 *
 * Implementations declare the identifiers of the services they require via
 * getDependencyList(). SMF resolves those services via some PSR-11 implementation
 * and passes them to setDependencies() before the action is executed.
 */
interface DependencyAwareActionInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Gets the service identifiers required by this action.
	 *
	 * The returned identifiers are resolved by the service container before
	 * the action is executed. The resolved services are passed to
	 * setDependencies() in the same order as the identifiers returned here.
	 *
	 * @return array<int, string> The service identifiers required by this action.
	 */
	public function getDependencyList(): array;

	/**
	 * Sets the service dependencies resolved for this action.
	 *
	 * The dependencies are provided in the same order as the identifiers
	 * returned by getDependencyList().
	 *
	 * @param array<int, object> $dependencies The resolved service instances.
	 */
	public function setDependencies(array $dependencies): void;
}
