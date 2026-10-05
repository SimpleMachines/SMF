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
use SMF\Localization\MessageFormatter;

/**
 * Covers SMF\Localization\MessageFormatter::formatMessage().
 *
 * It reads one language string of its own, 'lang_locale', which comes off disk
 * like any other, so the whole class is reachable without a forum behind it.
 */
#[CoversClass(MessageFormatter::class)]
class MessageFormatterTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * A Stringable argument is used for its string value.
	 *
	 * Several of SMF's own value objects are Stringable, among them Url,
	 * IP, TimeInterval and PageIndex, and a caller may hand any of them to
	 * Lang::getTxt().
	 *
	 * Expected: formatMessage('Hello {name}!', ['name' =>
	 *           <Stringable 'Bob'>]) returns 'Hello Bob!'.
	 * Guards:   the class skipped any argument that was not
	 *           already a string and handed the intl formatter
	 *           only the scalar ones, so an object reached
	 *           neither and its placeholder was printed to the
	 *           member as written.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/f500ca99c Introduced by "Implements MessageFormat support"
	 * @link https://github.com/SimpleMachines/SMF/pull/9409
	 */
	public function testAStringableArgumentIsUsedForItsStringValue(): void
	{
		$this->assertSame(
			'Hello Bob!',
			MessageFormatter::formatMessage('Hello {name}!', ['name' => $this->stringable('Bob')]),
		);
	}

	/**
	 * MessageFormat syntax in a Stringable value is not interpreted.
	 *
	 * The braces and apostrophes in an argument are swapped for private use
	 * characters before the message is formatted and swapped back
	 * afterwards, so that a value cannot be read as MessageFormat syntax. A
	 * Stringable is flattened early enough to go through that too.
	 *
	 * Expected: formatMessage('Hello {name}!', ['name' =>
	 *           <Stringable "it's {here}">]) returns "Hello it's
	 *           {here}!".
	 * Guards:   a Stringable argument never reached the formatter
	 *           at all, so its placeholder was printed as
	 *           written.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/f500ca99c Introduced by "Implements MessageFormat support"
	 * @link https://github.com/SimpleMachines/SMF/pull/9409
	 */
	public function testMessageFormatSyntaxInAStringableValueIsNotInterpreted(): void
	{
		$this->assertSame(
			"Hello it's {here}!",
			MessageFormatter::formatMessage('Hello {name}!', ['name' => $this->stringable("it's {here}")]),
		);
	}

	/**
	 * The same Stringable argument can be used twice in one message.
	 *
	 * Expected: formatMessage('{name} and {name}', ['name' =>
	 *           <Stringable 'Bob'>]) returns 'Bob and Bob'.
	 * Guards:   a Stringable argument never reached the formatter
	 *           at all, so both placeholders were printed as
	 *           written.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/f500ca99c Introduced by "Implements MessageFormat support"
	 * @link https://github.com/SimpleMachines/SMF/pull/9409
	 */
	public function testTheSameStringableCanBeUsedTwiceInOneMessage(): void
	{
		$this->assertSame(
			'Bob and Bob',
			MessageFormatter::formatMessage('{name} and {name}', ['name' => $this->stringable('Bob')]),
		);
	}

	/**
	 * A plain string argument is unaffected.
	 *
	 * Expected: formatMessage('Hello {name}!', ['name' => 'Ann'])
	 *           returns 'Hello Ann!'.
	 */
	public function testAPlainStringArgumentIsUnaffected(): void
	{
		$this->assertSame(
			'Hello Ann!',
			MessageFormatter::formatMessage('Hello {name}!', ['name' => 'Ann']),
		);
	}

	/**
	 * A number argument is still formatted as a number.
	 *
	 * Expected: formatMessage('{count, plural, one {# post} other
	 *           {# posts}}', ['count' => 2]) returns '2 posts'.
	 */
	public function testANumberArgumentIsStillFormattedAsANumber(): void
	{
		$this->assertSame(
			'2 posts',
			MessageFormatter::formatMessage('{count, plural, one {# post} other {# posts}}', ['count' => 2]),
		);
	}

	/**
	 * A message with no placeholders comes back unchanged.
	 *
	 * Expected: formatMessage('Nothing to substitute', ['name' =>
	 *           <Stringable 'Bob'>]) returns 'Nothing to
	 *           substitute'.
	 */
	public function testAMessageWithNoPlaceholdersComesBackUnchanged(): void
	{
		$this->assertSame(
			'Nothing to substitute',
			MessageFormatter::formatMessage('Nothing to substitute', ['name' => $this->stringable('Bob')]),
		);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The simplest thing that is a string without being one.
	 */
	private function stringable(string $value): \Stringable
	{
		return new class ($value) implements \Stringable {
			public function __construct(private string $value) {}

			public function __toString(): string
			{
				return $this->value;
			}
		};
	}
}
