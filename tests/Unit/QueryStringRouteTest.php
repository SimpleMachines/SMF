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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Board;
use SMF\QueryString;
use SMF\Topic;

#[CoversClass(QueryString::class)]
#[CoversClass(Board::class)]
#[CoversClass(Topic::class)]
class QueryStringRouteTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * A plain topic route is parsed as the display action.
	 *
	 * Expected: parseRoute('/topics/1', []) returns
	 *           ['action' => 'display', 'topic' => '1'].
	 */
	public function testAPlainTopicRouteIsTheDisplayAction(): void
	{
		$this->assertSame(
			['action' => 'display', 'topic' => '1'],
			QueryString::parseRoute('/topics/1', []),
		);
	}

	/**
	 * A plain board route is parsed as the message index action.
	 *
	 * Expected: parseRoute('/boards/2', []) returns
	 *           ['action' => 'messageindex', 'board' => '2'].
	 */
	public function testAPlainBoardRouteIsTheMessageIndexAction(): void
	{
		$this->assertSame(
			['action' => 'messageindex', 'board' => '2'],
			QueryString::parseRoute('/boards/2', []),
		);
	}

	/**
	 * A parameter that already exists wins over the one in the route.
	 *
	 * Expected: parseRoute('/topics/42', ['topic' => '99']) returns the display
	 *           action with topic 99.
	 */
	public function testExistingParametersTakePrecedenceOverRoute(): void
	{
		$this->assertSame(
			[
				'action' => 'display',
				'topic' => '99',
			],
			QueryString::parseRoute('/topics/42', [
				'topic' => '99',
			]),
		);
	}

	/**
	 * A path that is not a route leaves the parameters unchanged.
	 *
	 * Expected: parseRoute('not/a/route', ['action' => 'foo']) returns
	 *           ['action' => 'foo'].
	 */
	public function testNonRoutePathLeavesParametersUnchanged(): void
	{
		$params = ['action' => 'foo'];

		$this->assertSame(
			$params,
			QueryString::parseRoute('not/a/route', $params),
		);
	}

	/**
	 * An unknown route is read in the legacy queryless format.
	 *
	 * Expected: parseRoute('/action,foo/bar,baz', []) returns
	 *           ['action' => 'foo', 'bar' => 'baz'].
	 */
	public function testUnknownRouteUsesLegacyQuerylessFormat(): void
	{
		$this->assertSame(
			[
				'action' => 'foo',
				'bar' => 'baz',
			],
			QueryString::parseRoute('/action,foo/bar,baz', []),
		);
	}

	/**
	 * The legacy .html and .htm route extensions are ignored.
	 *
	 * Expected: each path in routeExtensionProvider() parses as the display action
	 *           for topic 42.
	 */
	#[DataProvider('routeExtensionProvider')]
	public function testLegacyRouteExtensionsAreIgnored(
		string $path,
	): void {
		$this->assertSame(
			['action' => 'display', 'topic' => '42'],
			QueryString::parseRoute($path, []),
		);
	}

	/**
	 * An action suffix on a topic or board route is parsed as the action.
	 *
	 * The parser for an action is added to QueryString::$route_parsers on
	 * demand by QueryString::getRouteParser(), so the suffix has to be
	 * looked up through that method rather than in the list.
	 *
	 * Expected: each case in actionSuffixProvider() parses to the action named by
	 *           the suffix, with the topic or board kept.
	 * Guards:   Topic::parseRoute() and Board::parseRoute() looked for the suffix in
	 *           QueryString::$route_parsers directly. That list holds only the
	 *           content parsers, because the parser for an action is added to
	 *           it on demand by QueryString::getRouteParser(). So the suffix
	 *           was never recognised and '/topics/1/post' parsed as the
	 *           display action with a start of 'post'. Every posting, voting,
	 *           poll, print and mark-as-read link on a topic or board
	 *           silently reloaded the page instead.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/39ca0ee61 Introduced by "Implements QueryString::getRouteParser()"
	 * @link https://github.com/SimpleMachines/SMF/pull/9575
	 */
	#[DataProvider('actionSuffixProvider')]
	public function testAnActionSuffixOnATopicOrBoardRoute(string $path, array $expected): void
	{
		$this->assertSame($expected, QueryString::parseRoute($path, []));
	}

	/**
	 * A start value is kept alongside an action suffix.
	 *
	 * A start value and an action suffix can both be present, because
	 * Topic::buildRoute() and Board::buildRoute() put the start value into
	 * the route before the action is appended to it.
	 *
	 * Expected: each case in startValueProvider() parses to the topic or board, the
	 *           start value and, when there is one, the action.
	 * Guards:   Parsing took either the start value or the action suffix and never
	 *           both, so replying from any page of a topic but the first lost the
	 *           action as well.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/244da60f0 Introduced by "Implements URL routing (i.e. better queryless URLs)"
	 * @link https://github.com/SimpleMachines/SMF/pull/9575
	 */
	#[DataProvider('startValueProvider')]
	public function testAStartValueIsKeptAlongsideTheAction(string $path, array $expected): void
	{
		$this->assertSame($expected, QueryString::parseRoute($path, []));
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function routeExtensionProvider(): iterable
	{
		yield 'html' => ['/topics/42.html'];

		yield 'htm' => ['/topics/42.htm'];
	}

	/**
	 * Each case uses a different topic or board id on purpose: Slug::setRequested()
	 * asks the Slug it builds to redirect when the same item is requested twice by
	 * different names, and there is nowhere to redirect to in a unit test.
	 *
	 * @return iterable<string, array{
	 *     0: string,
	 *     1: array<string, string>,
	 * }>
	 */
	public static function actionSuffixProvider(): iterable
	{
		yield 'reply to a topic' => [
			'/topics/10/post',
			['action' => 'post', 'topic' => '10'],
		];

		yield 'submit a reply to a topic' => [
			'/topics/11/post2',
			['action' => 'post2', 'topic' => '11'],
		];

		yield 'vote in a topic poll' => [
			'/topics/12/vote',
			['action' => 'vote', 'topic' => '12'],
		];

		yield 'edit a topic poll' => [
			'/topics/13/editpoll',
			['action' => 'editpoll', 'topic' => '13'],
		];

		yield 'print a topic' => [
			'/topics/14/printpage',
			['action' => 'printpage', 'topic' => '14'],
		];

		yield 'post a new topic in a board' => [
			'/boards/15/post',
			['action' => 'post', 'board' => '15'],
		];

		yield 'submit a new topic in a board' => [
			'/boards/16/post2',
			['action' => 'post2', 'board' => '16'],
		];

		yield 'a slug in front of the id changes nothing' => [
			'/topics/welcome-to-smf-17/post',
			['action' => 'post', 'topic' => '17'],
		];
	}

	/**
	 * @return iterable<string, array{
	 *     0: string,
	 *     1: array<string, string>,
	 * }>
	 */
	public static function startValueProvider(): iterable
	{
		yield 'a start value on its own' => [
			'/topics/20/20',
			['action' => 'display', 'topic' => '20', 'start' => '20'],
		];

		yield 'a symbolic start value on its own' => [
			'/topics/21/new',
			['action' => 'display', 'topic' => '21', 'start' => 'new'],
		];

		yield 'a start value and an action' => [
			'/topics/22/20/post',
			['action' => 'post', 'topic' => '22', 'start' => '20'],
		];

		yield 'a start value and an action on a board' => [
			'/boards/23/20/post2',
			['action' => 'post2', 'board' => '23', 'start' => '20'],
		];
	}
}
