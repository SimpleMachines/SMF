<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Avatar;
use SMF\Config;

/**
 * Covers what SMF\Avatar settles on when it can find no image at all.
 *
 * The constructor only asks the database who owns an attachment, so handing it
 * an id_member keeps the whole thing on this side of the line.
 */
#[CoversClass(Avatar::class)]
class AvatarFallbackTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array The settings this class reads, as they were before the test.
	 */
	private array $backup = [];

	/**
	 * @var string Config::$boardurl as it was before the test.
	 */
	private string $boardurl = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 * The constructor tries each way of finding an image in turn, and stops
	 * when it has a URL that validates. The last of those ways is a 1x1
	 * transparent GIF as a data URI, which is there so that there is always an
	 * answer - but a data URI is not something Url::isValid() accepts, so it
	 * never satisfied the condition that ends the search and the search went
	 * round again, and again, until the request was killed.
	 *
	 * Reaching it takes nothing exotic: the step before only produces a URL
	 * when avatar_url is set, so a forum where that setting was never written
	 * hung on any member with an avatar.
	 */
	public function testAnAvatarThatCannotBeFoundEndsAsATransparentGif(): void
	{
		Config::$boardurl = 'https://example.com';
		Config::$modSettings['gravatarEnabled'] = false;
		unset(Config::$modSettings['avatar_url']);

		$avatar = new Avatar(url: 'Oxygen/beagle.png', id_member: 1);

		$this->assertStringStartsWith('data:image/gif;base64,', (string) $avatar->url);
	}

	/**
	 * The control, and the reason the loop exists: when there is somewhere to
	 * look, it is looked in, and the search ends long before the last resort.
	 */
	public function testAnAvatarThatCanBeFoundIsStillFound(): void
	{
		Config::$boardurl = 'https://example.com';
		Config::$modSettings['gravatarEnabled'] = false;
		Config::$modSettings['avatar_url'] = 'https://example.com/avatars';

		$avatar = new Avatar(url: 'Oxygen/beagle.png', id_member: 1);

		$this->assertSame('https://example.com/avatars/Oxygen/beagle.png', (string) $avatar->url);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		$this->boardurl = Config::$boardurl ?? '';

		foreach (['avatar_url', 'gravatarEnabled'] as $key) {
			if (isset(Config::$modSettings[$key])) {
				$this->backup[$key] = Config::$modSettings[$key];
			}
		}
	}

	/**
	 * PHPUnit does not reset SMF's statics between tests, so a setting left
	 * behind here would leak into every test that follows.
	 */
	protected function tearDown(): void
	{
		Config::$boardurl = $this->boardurl;

		foreach (['avatar_url', 'gravatarEnabled'] as $key) {
			unset(Config::$modSettings[$key]);

			if (isset($this->backup[$key])) {
				Config::$modSettings[$key] = $this->backup[$key];
			}
		}

		$this->backup = [];
	}
}
