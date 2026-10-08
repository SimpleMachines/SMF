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
	 * An avatar that cannot be found ends as a transparent GIF.
	 *
	 * Reaching the last resort takes nothing exotic: the step before it only
	 * produces a URL when avatar_url is set, so a forum where that setting was
	 * never written reaches it for any member with an avatar.
	 *
	 * Expected: new Avatar(url: 'Oxygen/beagle.png', id_member: 1) with no
	 *           avatar_url setting and Gravatar off has a url starting with
	 *           data:image/gif;base64,.
	 * Guards:   the constructor tries each way of finding an image in a loop
	 *           that runs until it holds a URL that Url::isValid() accepts. The
	 *           last of those ways is a data URI, which is not one, so the
	 *           search went round again and again until the request was killed.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/399ceedb4 Introduced by "Implements SMF\Avatar"
	 * @link https://github.com/SimpleMachines/SMF/pull/9587
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
	 * An avatar that can be found is still found.
	 *
	 * The control: when there is somewhere to look, it is looked in, and the
	 * search ends long before the last resort.
	 *
	 * Expected: new Avatar(url: 'Oxygen/beagle.png', id_member: 1) with
	 *           avatar_url set to https://example.com/avatars has the url
	 *           https://example.com/avatars/Oxygen/beagle.png.
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
