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
 * Covers which directory SMF\Avatar looks in for a gallery avatar.
 *
 * The gallery lives wherever Config::$modSettings['avatar_directory'] says it
 * does. Themes/default/images stands in for an admin who moved it: it is full
 * of images, it is not the directory SMF ships the gallery in, and nothing has
 * to be written to disk to use it.
 */
#[CoversClass(Avatar::class)]
class AvatarGalleryDirectoryTest extends TestCase
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
	 * A gallery avatar is looked for in the configured directory.
	 *
	 * The admin can move the avatar gallery, and the admin panel offers the
	 * setting and warns when the directory it names is not there. Profile lists
	 * the gallery out of that directory and refuses to save a choice from
	 * anywhere else, so it is the directory a stored avatar is a path into, and
	 * it is where the file has to be looked for.
	 *
	 * Expected: with avatar_directory pointing at Themes/default/images, new
	 *           Avatar(url: 'cake.png', id_member: 1) has the url
	 *           https://example.com/gallery/cake.png.
	 * Guards:   the file was looked for under the board directory's avatars
	 *           directory whatever the setting said, while the address was
	 *           built from avatar_url. A forum whose admin had moved the
	 *           gallery showed the default image for every member.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/399ceedb4 Introduced by "Implements SMF\Avatar"
	 * @link https://github.com/SimpleMachines/SMF/pull/9588
	 */
	public function testAGalleryAvatarIsLookedForInTheConfiguredDirectory(): void
	{
		$this->setUpForum(Config::$boarddir . '/Themes/default/images');

		$avatar = new Avatar(url: 'cake.png', id_member: 1);

		$this->assertSame('https://example.com/gallery/cake.png', (string) $avatar->url);
	}

	/**
	 * A file in a subdirectory of the configured gallery is found as well.
	 *
	 * Expected: with avatar_directory pointing at Themes/default/images, new
	 *           Avatar(url: 'icons/bell.png', id_member: 1) has the url
	 *           https://example.com/gallery/icons/bell.png.
	 * Guards:   the file was looked for under the board directory's avatars
	 *           directory whatever the setting said, so a gallery that had been
	 *           moved was never searched, subdirectories included.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/399ceedb4 Introduced by "Implements SMF\Avatar"
	 * @link https://github.com/SimpleMachines/SMF/pull/9588
	 */
	public function testTheSameForAFileInASubdirectoryOfTheGallery(): void
	{
		$this->setUpForum(Config::$boarddir . '/Themes/default/images');

		$avatar = new Avatar(url: 'icons/bell.png', id_member: 1);

		$this->assertSame('https://example.com/gallery/icons/bell.png', (string) $avatar->url);
	}

	/**
	 * The shipped gallery is used when nothing says otherwise.
	 *
	 * A forum whose admin never touched the setting keeps the gallery SMF
	 * ships, so the value the installer would have written is the fallback.
	 *
	 * Expected: with no avatar_directory setting, new Avatar(url:
	 *           'Oxygen/beagle.png', id_member: 1) has the url
	 *           https://example.com/gallery/Oxygen/beagle.png.
	 */
	public function testTheShippedGalleryIsUsedWhenNothingSaysOtherwise(): void
	{
		$this->setUpForum(null);

		$avatar = new Avatar(url: 'Oxygen/beagle.png', id_member: 1);

		$this->assertSame('https://example.com/gallery/Oxygen/beagle.png', (string) $avatar->url);
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
	 * Puts the gallery in $directory, or leaves the setting off entirely when
	 * it is null. The URL is deliberately not the shipped one, so that an
	 * address built from it cannot be mistaken for a lucky guess.
	 */
	private function setUpForum(?string $directory): void
	{
		Config::$boardurl = 'https://example.com';
		Config::$modSettings['avatar_url'] = 'https://example.com/gallery';
		Config::$modSettings['gravatarEnabled'] = false;

		if ($directory === null) {
			unset(Config::$modSettings['avatar_directory']);
		} else {
			Config::$modSettings['avatar_directory'] = $directory;
		}
	}
}
