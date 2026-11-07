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
	 * A sub-action stated in the route survives parsing.
	 *
	 * Expected: MarkRead::parseRoute(['markasread', 'all']) returns
	 *           ['action' => 'markasread', 'sa' => 'all'], and the same
	 *           with 'unreadreplies'.
	 * Guards:   '??' binds tighter than '?:', so the line that restored the implied
	 *           sub-action read as
	 *           '($params['sa'] ?? isset($params['topic'])) ? ...'.
	 *           A sub-action the route did state is a non-empty string, which is
	 *           truthy, so it was replaced with 'topic'. Marking everything
	 *           read then ran the per-topic branch with no topic loaded, which
	 *           is a database error and a 500.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/33ce3cf28 Introduced by "Improves routing for SMF\Actions\MarkRead"
	 * @link https://github.com/SimpleMachines/SMF/pull/9577
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

	/**
	 * A markasread route with a sub-action parses the same through QueryString.
	 *
	 * Expected: QueryString::parseRoute('/markasread/all', []) returns
	 *           ['action' => 'markasread', 'sa' => 'all'].
	 * Guards:   '/markasread/all' was parsed with the sub-action 'topic', so
	 *           "Mark all messages as read" did not work on any forum with
	 *           friendly URLs turned on.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/33ce3cf28 Introduced by "Improves routing for SMF\Actions\MarkRead"
	 * @link https://github.com/SimpleMachines/SMF/pull/9577
	 */
	public function testTheSameThingThroughQueryString(): void
	{
		$this->assertSame(
			['action' => 'markasread', 'sa' => 'all'],
			QueryString::parseRoute('/markasread/all', []),
		);
	}

	/**
	 * A sub-action implied by the topic or board is restored when parsing.
	 *
	 * buildRoute() leaves the sub-action out when the topic or the board it
	 * is hanging off already implies it, so parsing has to put it back.
	 *
	 * Expected: MarkRead::parseRoute(['markasread'], ['topic' => '1']) adds 'sa' =>
	 *           'topic', and with ['board' => '1'] it adds 'sa' => 'board'.
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
	 * A route with nothing to imply a sub-action gets none.
	 *
	 * Expected: MarkRead::parseRoute(['markasread']) returns
	 *           ['action' => 'markasread'], with no 'sa' key.
	 * Guards:   The sub-action was set to null, which put a valueless 'sa'
	 *           into $_GET.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/33ce3cf28 Introduced by "Improves routing for SMF\Actions\MarkRead"
	 * @link https://github.com/SimpleMachines/SMF/pull/9577
	 */
	public function testNothingImpliesNothing(): void
	{
		$this->assertSame(
			['action' => 'markasread'],
			MarkRead::parseRoute(['markasread']),
		);
	}
}
