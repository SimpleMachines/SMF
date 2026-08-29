<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Actions\MarkRead;
use SMF\QueryString;

#[CoversClass(MarkRead::class)]
class MarkReadRouteTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * '??' binds tighter than '?:', so the line that restored the implied
	 * sub-action read as '($params['sa'] ?? isset($params['topic'])) ? ...'.
	 * A sub-action that the route did state is a non-empty string, which is
	 * truthy, so it was thrown away and replaced with 'topic'. Marking
	 * everything read then ran the per-topic branch with no topic loaded,
	 * which is a database error and a 500.
	 */
	public function testASubActionInTheRouteSurvives(): void
	{
		$this->assertSame(
			['action' => 'markasread', 'sa' => 'all'],
			MarkRead::parseRoute(['markasread', 'all']),
		);

		$this->assertSame(
			['action' => 'markasread', 'sa' => 'unreadreplies'],
			MarkRead::parseRoute(['markasread', 'unreadreplies']),
		);
	}

	public function testTheSameThingThroughQueryString(): void
	{
		$this->assertSame(
			['action' => 'markasread', 'sa' => 'all'],
			QueryString::parseRoute('/markasread/all', []),
		);
	}

	/**
	 * buildRoute() leaves the sub-action out when the topic or the board it is
	 * hanging off already implies it, so parsing has to put it back.
	 */
	public function testAnImpliedSubActionIsRestored(): void
	{
		$this->assertSame(
			['topic' => '1', 'action' => 'markasread', 'sa' => 'topic'],
			MarkRead::parseRoute(['markasread'], ['topic' => '1']),
		);

		$this->assertSame(
			['board' => '1', 'action' => 'markasread', 'sa' => 'board'],
			MarkRead::parseRoute(['markasread'], ['board' => '1']),
		);
	}

	/**
	 * With nothing to imply one, there is no sub-action to add. It used to be
	 * set to null, which put a valueless 'sa' into $_GET.
	 */
	public function testNothingImpliesNothing(): void
	{
		$this->assertSame(
			['action' => 'markasread'],
			MarkRead::parseRoute(['markasread']),
		);
	}
}
