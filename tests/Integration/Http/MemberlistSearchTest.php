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

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Searching the member list.
 */
#[CoversNothing]
class MemberlistSearchTest extends HttpTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * A search finds a member whatever case it is typed in.
	 *
	 * MySQL compares without regard to case anyway, so on MySQL this passes
	 * with or without the bug.
	 *
	 * Expected: each case in spellings() searches the member list for the
	 *           administrator's name, in capitals or capitalised, and finds
	 *           the administrator among the results, with nothing logged.
	 * Guards:   on a case sensitive database - PostgreSQL - Memberlist::search()
	 *           folded the columns it searches to lower case, and compared
	 *           them with the search as typed. So any search with a capital
	 *           letter in it matched nobody.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/7ee5156e2 Introduced by "Uses correct param when searching by email in memberlist"
	 * @link https://github.com/SimpleMachines/SMF/pull/9716
	 */
	#[DataProvider('spellings')]
	public function testASearchIgnoresCase(string $how): void
	{
		$this->signInAsAdmin();

		$name = self::adminName();
		$search = $how === 'upper' ? strtoupper($name) : ucfirst(strtolower($name));

		$results = $this->fetch('?action=mlist;sa=search;fields=name;search=' . urlencode($search));

		$this->assertGreaterThan(
			0,
			// In the results, not the menu, which links to the same profile.
			$results->xpath('//*[@id="mlist"]//table//a[contains(@href, "action=profile;u=' . $this->adminId() . '")]')->length,
			'searching the member list for "' . $search . '" did not find "' . $name . '"',
		);

		$this->assertNoErrorsLogged('searching the member list logged something.' . "\n");
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * How to spell the administrator's name.
	 *
	 * @return array The cases.
	 */
	public static function spellings(): array
	{
		return [
			'in capitals' => ['upper'],
			'capitalised' => ['ucfirst'],
		];
	}
}
