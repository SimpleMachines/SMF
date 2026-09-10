<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Config;
use SMF\Msg;
use SMF\Topic;

/**
 * The print view of a topic, over HTTP.
 *
 * What is worth proving here is what a single request is willing to do. The
 * print view renders whole posts with nothing else on the page, so a request for
 * a long topic parses every post in it at once, and a visitor needs no account
 * to ask for one. Only a real request shows how many posts come back, since the
 * limit is applied in the action and the page is built by the template.
 */
#[CoversNothing]
class TopicPrintTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * How many posts to put in the test topic.
	 */
	private const POSTS = 5;

	/**
	 * How many of them a print page is allowed to show.
	 */
	private const PER_PAGE = 2;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Topics this test created, to be removed afterwards.
	 */
	private array $created_topics = [];

	/**
	 * @var ?string The forum's own maximum topic size for the "All" view.
	 */
	private ?string $previous_max = null;

	/****************
	 * Public methods
	 ****************/

	public function testAPrintPageStopsAtOnePageOfPosts(): void
	{
		$topic_id = $this->seedTopic();

		$first = $this->fetch('?action=printpage;topic=' . $topic_id . '.0');

		$this->assertSame(
			self::PER_PAGE,
			$first->xpath('//div[@class="postheader"]')->length,
			'the print page rendered the whole topic instead of one page of it',
		);

		$this->assertStringNotContainsString(
			$this->bodyOfPost(self::POSTS),
			$first->text(),
			'the last post of the topic is on the first print page',
		);

		$this->assertNoErrorsLogged('printing a topic logged something.' . "\n");
	}

	public function testTheRestOfTheTopicIsOnTheFollowingPages(): void
	{
		$topic_id = $this->seedTopic();

		$last = $this->fetch('?action=printpage;topic=' . $topic_id . '.' . (self::POSTS - 1));

		$this->assertStringContainsString(
			$this->bodyOfPost(self::POSTS),
			$last->text(),
			'the last post of the topic cannot be printed at all',
		);

		$this->assertNoErrorsLogged('printing the last page logged something.' . "\n");
	}

	/**
	 * Without these the rest of a long topic is unreachable, since the print
	 * page is the only thing that links to itself.
	 */
	public function testAPrintPageLinksToTheOtherPages(): void
	{
		$topic_id = $this->seedTopic();

		$first = $this->fetch('?action=printpage;topic=' . $topic_id . '.0');

		$this->assertGreaterThan(
			0,
			$first->xpath(
				'//a[contains(@href, "action=printpage")][contains(@href, "topic=' . $topic_id . '.' . self::PER_PAGE . '")]',
			)->length,
			'the print page does not link to its second page',
		);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		// A print page shows no more posts than the "All" view is allowed to,
		// so this is what decides where it stops.
		$this->previous_max = $this->rawSetting('enableAllMessages');

		Config::updateModSettings(['enableAllMessages' => self::PER_PAGE]);
	}

	protected function tearDown(): void
	{
		// Before the parent runs, while the connection is still ours.
		Config::updateModSettings(['enableAllMessages' => (int) $this->previous_max]);

		if ($this->created_topics !== []) {
			Topic::remove($this->created_topics);

			$this->created_topics = [];
		}

		parent::tearDown();
	}

	/**
	 * Starts a topic holding more posts than one print page may show.
	 *
	 * @return int The new topic's id.
	 */
	private function seedTopic(): int
	{
		$this->actingAs($this->adminId());

		$topic_id = 0;

		for ($i = 1; $i <= self::POSTS; $i++) {
			$msgOptions = [
				'subject' => ($i === 1 ? '' : 'Re: ') . 'Print pagination',
				'body' => $this->bodyOfPost($i),
				'send_notifications' => false,
			];
			$topicOptions = [
				'id' => $topic_id,
				'board' => 1,
			];
			$posterOptions = [
				'id' => $this->adminId(),
			];

			Msg::create($msgOptions, $topicOptions, $posterOptions);

			$topic_id = (int) $topicOptions['id'];

			if ($this->created_topics === []) {
				$this->created_topics[] = $topic_id;
			}
		}

		return $topic_id;
	}

	/**
	 * The body of one of the seeded posts.
	 *
	 * @param int $number Which post.
	 * @return string Its body.
	 */
	private function bodyOfPost(int $number): string
	{
		return 'Print pagination post number ' . $number . '.';
	}
}
