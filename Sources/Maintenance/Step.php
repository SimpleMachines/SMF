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

class Step
{
    private int $id;
    private string $name;
    private ?string $title = null;
    private string $function;
    private int $progress;

    public function __construct(int $id, string $name, string $function, int $progres, ?string $title = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->title = $title;
        $this->function = $function;
        $this->progress = $progres;
    }

    public function getID(): int
    {
        return $this->id;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function getFunction(): string
    {
        return $this->function;
    }
    public function getProgress(): int
    {
        return $this->progress;
    }
}