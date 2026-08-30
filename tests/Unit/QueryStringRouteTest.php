<?php

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

	public function testAPlainTopicRouteIsTheDisplayAction(): void
	{
		$this->assertSame(
			['action' => 'display', 'topic' => '1'],
			QueryString::parseRoute('/topics/1', []),
		);
	}

	public function testAPlainBoardRouteIsTheMessageIndexAction(): void
	{
		$this->assertSame(
			['action' => 'messageindex', 'board' => '2'],
			QueryString::parseRoute('/boards/2', []),
		);
	}

	/**
	 * Topic::parseRoute() and Board::parseRoute() used to look for the action
	 * suffix in QueryString::$route_parsers directly. That list only ever holds
	 * the handful of content parsers it is declared with, because the parser for
	 * an action is added to it on demand by QueryString::getRouteParser(). So
	 * the suffix was never recognised and fell through to the branch that treats
	 * whatever is left as a start value: '/topics/1/post' parsed as the display
	 * action with a start of 'post', and every posting, voting, poll, print and
	 * mark-as-read link on a topic or board silently reloaded the page instead.
	 */
	#[DataProvider('actionSuffixProvider')]
	public function testAnActionSuffixOnATopicOrBoardRouteIsTheAction(string $path, array $expected): void
	{
		$this->assertSame($expected, QueryString::parseRoute($path, []));
	}

	/**
	 * A start value and an action suffix can both be present, because
	 * Topic::buildRoute() and Board::buildRoute() put the start value into the
	 * route before the action is appended to it. Parsing only ever consumed one
	 * of the two, so replying from any page of a topic but the first lost the
	 * action as well.
	 */
	#[DataProvider('startValueProvider')]
	public function testAStartValueIsKeptAlongsideTheAction(string $path, array $expected): void
	{
		$this->assertSame($expected, QueryString::parseRoute($path, []));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Each case uses a different topic or board id on purpose: Slug::setRequested()
	 * asks the Slug it builds to redirect when the same item is requested twice by
	 * different names, and there is nowhere to redirect to in a unit test.
	 */
	public static function actionSuffixProvider(): array
	{
		return [
			'reply to a topic' => [
				'/topics/10/post',
				['action' => 'post', 'topic' => '10'],
			],
			'submit a reply to a topic' => [
				'/topics/11/post2',
				['action' => 'post2', 'topic' => '11'],
			],
			'vote in a topic poll' => [
				'/topics/12/vote',
				['action' => 'vote', 'topic' => '12'],
			],
			'edit a topic poll' => [
				'/topics/13/editpoll',
				['action' => 'editpoll', 'topic' => '13'],
			],
			'print a topic' => [
				'/topics/14/printpage',
				['action' => 'printpage', 'topic' => '14'],
			],
			'post a new topic in a board' => [
				'/boards/15/post',
				['action' => 'post', 'board' => '15'],
			],
			'submit a new topic in a board' => [
				'/boards/16/post2',
				['action' => 'post2', 'board' => '16'],
			],
			// A slug in front of the id is the normal shape of these URLs.
			'a slug in front of the id changes nothing' => [
				'/topics/welcome-to-smf-17/post',
				['action' => 'post', 'topic' => '17'],
			],
		];
	}

	public static function startValueProvider(): array
	{
		return [
			'a start value on its own' => [
				'/topics/20/20',
				['action' => 'display', 'topic' => '20', 'start' => '20'],
			],
			'a symbolic start value on its own' => [
				'/topics/21/new',
				['action' => 'display', 'topic' => '21', 'start' => 'new'],
			],
			'a start value and an action' => [
				'/topics/22/20/post',
				['action' => 'post', 'topic' => '22', 'start' => '20'],
			],
			'a start value and an action on a board' => [
				'/boards/23/20/post2',
				['action' => 'post2', 'board' => '23', 'start' => '20'],
			],
		];
	}
}
