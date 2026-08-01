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

namespace SMF;

/**
 * The possible data sets that can be loaded in SMF\User.
 *
 * The five cases in ascending order are None, Minimal, Basic, Normal, and
 * Profile. Each subsequent case includes all the data in the previous cases.
 * Several methods exist to compare different cases.
 */
enum UserDataset: string
{
	/************
	 * Enum cases
	 ************/

	// The order of the cases matters. Don't change it.
	case None = 'none';
	case Minimal = 'minimal';
	case Basic = 'basic';
	case Normal = 'normal';
	case Profile = 'profile';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Whether this dataset includes the data of the passed dataset.
	 *
	 * @return bool
	 */
	public function includes(self $case): bool
	{
		return $this->compare($case) >= 0;
	}

	/**
	 * Whether this dataset includes more data than the passed dataset.
	 *
	 * @return bool
	 */
	public function exceeds(self $case): bool
	{
		return $this->compare($case) === 1;
	}

	/**
	 * Compares this dataset to the passed dataset to see which includes more
	 * data.
	 *
	 * @return int -1 if this case includes less than the passed case, 1 if this
	 *    case includes more than the passed case, or 0 if they are equal.
	 */
	public function compare(self $case): int
	{
		return array_search($this, self::cases()) <=> array_search($case, self::cases());
	}
}
