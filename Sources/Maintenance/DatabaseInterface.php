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
use SMF\Db\DatabaseApi as Db;

interface DatabaseInterface
{
    public function getTitle(): string;

    public function getMinimumVersion(): string;

    public function getServerVersion(): bool|string;

    public function isSupported(): bool;

    public function SkipSelectDatabase(): bool;

    public function getDefaultUser(): string;

    public function getDefaultPassword(): string;

    public function getDefaultHost(): string;

    public function getDefaultPort(): int;

    public function getDefaultName(): string;

    public function checkConfiguration(): bool;

    public function hasPermissions(): bool;

    public function validatePrefix(&$string): bool;

    public function utf8Configured(): bool;
}