<?php

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
	 * On a case sensitive database - PostgreSQL - Memberlist::search() folds the
	 * columns it searches to lower case, and compared them with the search as
	 * typed. So any search with a capital letter in it matched nobody. MySQL
	 * compares without regard to case anyway, and passes either way.
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
