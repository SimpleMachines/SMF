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
use SMF\Avatar;
use SMF\Config;

/**
 * Covers how SMF\Avatar turns a stored avatar value into a URL.
 *
 * The constructor only asks the database who owns an attachment, so handing it
 * an id_member keeps the whole thing on this side of the line. The avatars it
 * looks for here are the ones the repository ships in avatars/.
 */
#[CoversClass(Avatar::class)]
class AvatarTest extends TestCase
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
	 * A remote avatar URL is left alone.
	 *
	 * The control. Something with a scheme on it is an address to fetch from,
	 * and is left as one.
	 *
	 * Expected: new Avatar(url: 'https://example.org/pictures/me.png',
	 *           id_member: 1) has that same url.
	 */
	public function testARemoteAvatarUrlIsLeftAlone(): void
	{
		$this->setUpForum('https://example.com/forum');

		$avatar = new Avatar(url: 'https://example.org/pictures/me.png', id_member: 1);

		$this->assertSame('https://example.org/pictures/me.png', (string) $avatar->url);
	}

	/**
	 * A gallery avatar is found under the avatars directory.
	 *
	 * An avatar chosen from the gallery is stored as a path under the avatars
	 * directory, not as a URL, so there is no scheme on it and no host in it.
	 * Saying so up front means the file is looked for by name.
	 *
	 * Expected: each case in boardUrls() resolves new Avatar(url:
	 *           'Oxygen/beagle.png', id_member: 1) to the url
	 *           <boardurl>/avatars/Oxygen/beagle.png, with the filename
	 *           Oxygen/beagle.png.
	 * Guards:   the stored path was read as a URL, and the file was worked back
	 *           to from that URL's path, which lands outside the avatar
	 *           directories and finds nothing. On a forum at the root of its
	 *           domain that path is null, and stripping the board URL off it
	 *           threw a TypeError on every page the member appeared on.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/399ceedb4 Introduced by "Implements SMF\Avatar"
	 * @link https://github.com/SimpleMachines/SMF/pull/9442
	 */
	#[DataProvider('boardUrls')]
	public function testAGalleryAvatarIsFoundUnderTheAvatarsDirectory(string $boardurl): void
	{
		$this->setUpForum($boardurl);

		$avatar = new Avatar(url: 'Oxygen/beagle.png', id_member: 1);

		$this->assertSame($boardurl . '/avatars/Oxygen/beagle.png', (string) $avatar->url);
		$this->assertSame('Oxygen/beagle.png', $avatar->filename);
	}

	/**
	 * A gallery avatar in the root of the gallery is found too.
	 *
	 * Expected: each case in boardUrls() resolves new Avatar(url:
	 *           'default.png', id_member: 1) to the url
	 *           <boardurl>/avatars/default.png, with the filename default.png.
	 * Guards:   the stored path was read as a URL and worked back to a file
	 *           from that URL's path, which finds nothing outside a path prefix
	 *           and threw a TypeError on a forum at the root of its domain.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/399ceedb4 Introduced by "Implements SMF\Avatar"
	 * @link https://github.com/SimpleMachines/SMF/pull/9442
	 */
	#[DataProvider('boardUrls')]
	public function testAGalleryAvatarInTheRootOfTheGalleryIsFoundToo(string $boardurl): void
	{
		$this->setUpForum($boardurl);

		$avatar = new Avatar(url: 'default.png', id_member: 1);

		$this->assertSame($boardurl . '/avatars/default.png', (string) $avatar->url);
		$this->assertSame('default.png', $avatar->filename);
	}

	/**
	 * A gallery avatar that is not there falls back to the default image.
	 *
	 * It does not produce a URL pointing at nothing.
	 *
	 * Expected: new Avatar(url: 'Oxygen/no_such_avatar.png', id_member: 1) has
	 *           the url https://example.com/avatars/default.png.
	 */
	public function testAGalleryAvatarThatIsNotThereFallsBackToTheDefault(): void
	{
		$this->setUpForum('https://example.com');

		$avatar = new Avatar(url: 'Oxygen/no_such_avatar.png', id_member: 1);

		$this->assertSame('https://example.com/avatars/default.png', (string) $avatar->url);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string}>
	 */
	public static function boardUrls(): array
	{
		return [
			'a forum at the root of its domain' => ['https://example.com'],
			'a forum in a subdirectory' => ['https://example.com/forum'],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		$this->boardurl = Config::$boardurl ?? '';

		foreach (['avatar_url', 'avatar_directory', 'gravatarEnabled'] as $key) {
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

		foreach (['avatar_url', 'avatar_directory', 'gravatarEnabled'] as $key) {
			unset(Config::$modSettings[$key]);

			if (isset($this->backup[$key])) {
				Config::$modSettings[$key] = $this->backup[$key];
			}
		}

		$this->backup = [];
	}

	/**
	 * Points the avatar settings at the gallery the repository ships.
	 */
	private function setUpForum(string $boardurl): void
	{
		Config::$boardurl = $boardurl;
		Config::$modSettings['avatar_url'] = $boardurl . '/avatars';
		Config::$modSettings['avatar_directory'] = Config::$boarddir . '/avatars';
		Config::$modSettings['gravatarEnabled'] = false;
	}
}
