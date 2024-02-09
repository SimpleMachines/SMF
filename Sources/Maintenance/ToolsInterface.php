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

namespace SMF\Maintenance;
 
interface ToolsInterface
{
    public function getPageTitle(): string;

    public function hasSteps(): bool;

    /**
     * 
     * @return \SMF\Maintenance\Step[]
     */
    public function getSteps(): array;

    public function getStepTitle(): string;
}