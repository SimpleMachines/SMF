<?php

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
	 * An object that can be a string is one of the things a caller may hand to
	 * Lang::getTxt(), and several of SMF's own value objects are exactly that:
	 * Url, IP, TimeInterval and PageIndex all implement \Stringable. The class
	 * skips any argument that is not already a string, and the intl formatter
	 * is handed only the scalar ones, so an object reached neither and its
	 * placeholder was printed to the member as it was written.
	 */
	public function testAStringableArgumentIsUsedForItsStringValue(): void
	{
		$this->assertSame(
			'Hello Bob!',
			MessageFormatter::formatMessage('Hello {name}!', ['name' => $this->stringable('Bob')]),
		);
	}

	/**
	 * The braces and apostrophes in an argument are swapped for private use
	 * characters before the message is formatted and swapped back afterwards,
	 * so that a value cannot be read as MessageFormat syntax. A \Stringable is
	 * flattened early enough to go through that too.
	 */
	public function testMessageFormatSyntaxInAStringableValueIsNotInterpreted(): void
	{
		$this->assertSame(
			"Hello it's {here}!",
			MessageFormatter::formatMessage('Hello {name}!', ['name' => $this->stringable("it's {here}")]),
		);
	}

	public function testTheSameStringableCanBeUsedTwiceInOneMessage(): void
	{
		$this->assertSame(
			'Bob and Bob',
			MessageFormatter::formatMessage('{name} and {name}', ['name' => $this->stringable('Bob')]),
		);
	}

	public function testAPlainStringArgumentIsUnaffected(): void
	{
		$this->assertSame(
			'Hello Ann!',
			MessageFormatter::formatMessage('Hello {name}!', ['name' => 'Ann']),
		);
	}

	public function testANumberArgumentIsStillFormattedAsANumber(): void
	{
		$this->assertSame(
			'2 posts',
			MessageFormatter::formatMessage('{count, plural, one {# post} other {# posts}}', ['count' => 2]),
		);
	}

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
