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
use SMF\Config;
use SMF\Lang;
use SMF\Parser;
use SMF\Parsers\MarkdownParser;
use SMF\Parsers\SmileyParser;
use SMF\Url;
use SMF\User;
use SMF\UserDataset;
use SMF\Utils;

/**
 * Covers how SMF\Parser parses BBCode, Markdown, and smileys.
 *
 * In general, the best way to add tests to this class is to add new testable
 * strings to SMF\Tests\Generators\ParserTestGenerator::$strings and then
 * run that file on the command line.
 */
#[CoversClass(Parser::class)]
class ParserTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Backup of Config::$modSettings.
	 */
	private array $modSettings = [];

	/**
	 * @var ?int
	 *
	 * Backup of User::$me->id.
	 */
	private ?int $my_id;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Expected: For each combination of $input, $input_types, $output_type,
	 *           and $options, the result of parsing is as $expected.
	 */
	#[DataProvider('basicBbcodeStringProvider')]
	public function testBasicBbcodeParsing(
		string $input,
		int $input_types,
		int $output_type,
		array $options,
		string $expected,
	): void {
		$this->assertSame(
			$expected,
			Parser::transform($input, $input_types, $output_type, $options),
		);
	}

	/**
	 * Expected: For each combination of $input, $input_types, $output_type,
	 *           and $options, the result of parsing is as $expected.
	 */
	#[DataProvider('basicMarkdownStringProvider')]
	public function testBasicMarkdownParsing(
		string $input,
		int $input_types,
		int $output_type,
		array $options,
		string $expected,
	): void {
		$this->assertSame(
			$expected,
			Parser::transform($input, $input_types, $output_type, $options),
		);
	}

	/**
	 * Expected: For each combination of $input, $input_types, $output_type,
	 *           and $options, the result of parsing is as $expected.
	 */
	#[DataProvider('complexStringProvider')]
	public function testComplexParsing(
		string $input,
		int $input_types,
		int $output_type,
		array $options,
		string $expected,
	): void {
		$this->assertSame(
			$expected,
			Parser::transform($input, $input_types, $output_type, $options),
		);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array
	 */
	public static function basicBbcodeStringProvider(): array
	{
		return [
			0 => [
				'Lorem ipsum :)',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum <img src="' . Config::$modSettings['smileys_url'] . '/' . Config::$modSettings['smiley_sets_default'] . '/smiley.png" alt="&#58;&#41;" title="Smiley" class="smiley"></p>' . "\n",
			],
			1 => [
				'Lorem ipsum :)',
				Parser::INPUT_BBC | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum <img src="' . Config::$modSettings['smileys_url'] . '/' . Config::$modSettings['smiley_sets_default'] . '/smiley.png" alt="&#58;&#41;" title="Smiley" class="smiley">',
			],
			2 => [
				'Lorem ipsum :)',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum :)</p>' . "\n",
			],
			3 => [
				'Lorem ipsum :)',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum :)',
			],
			4 => [
				'Lorem [abbr=ipsum]dolor[/abbr] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <abbr title="ipsum">dolor</abbr> sit amet.</p>' . "\n",
			],
			5 => [
				'Lorem [abbr=ipsum]dolor[/abbr] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [abbr=ipsum]dolor[/abbr] sit amet.</p>' . "\n",
			],
			6 => [
				'Lorem [abbr=ipsum]dolor[/abbr] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <abbr title="ipsum">dolor</abbr> sit amet.',
			],
			7 => [
				'Lorem [anchor=ipsum]ipsum[/anchor] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span id="post_ipsum">ipsum</span> dolor sit amet.</p>' . "\n",
			],
			8 => [
				'Lorem [anchor=ipsum]ipsum[/anchor] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [anchor=ipsum]ipsum[/anchor] dolor sit amet.</p>' . "\n",
			],
			9 => [
				'Lorem [anchor=ipsum]ipsum[/anchor] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span id="post_ipsum">ipsum</span> dolor sit amet.',
			],
			10 => [
				'Lorem [b]ipsum dolor[/b] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <strong>ipsum dolor</strong> sit amet.</p>' . "\n",
			],
			11 => [
				'Lorem [b]ipsum dolor[/b] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [b]ipsum dolor[/b] sit amet.</p>' . "\n",
			],
			12 => [
				'Lorem [b]ipsum dolor[/b] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <strong>ipsum dolor</strong> sit amet.',
			],
			13 => [
				'[bdo=ltr]Lorem ipsum dolor sit amet.[/bdo]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><bdo dir="ltr">Lorem ipsum dolor sit amet.</bdo></p>' . "\n",
			],
			14 => [
				'[bdo=ltr]Lorem ipsum dolor sit amet.[/bdo]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[bdo=ltr]Lorem ipsum dolor sit amet.[/bdo]</p>' . "\n",
			],
			15 => [
				'[bdo=ltr]Lorem ipsum dolor sit amet.[/bdo]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<bdo dir="ltr">Lorem ipsum dolor sit amet.</bdo>',
			],
			16 => [
				'Lorem [black]ipsum dolor[/black] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="color: black;" class="bbc_color">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			17 => [
				'Lorem [black]ipsum dolor[/black] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			18 => [
				'Lorem [black]ipsum dolor[/black] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [black]ipsum dolor[/black] sit amet.</p>' . "\n",
			],
			19 => [
				'Lorem [black]ipsum dolor[/black] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="color: black;" class="bbc_color">ipsum dolor</span> sit amet.',
			],
			20 => [
				'Lorem [black]ipsum dolor[/black] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			21 => [
				'Lorem [blue]ipsum dolor[/blue] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="color: blue;" class="bbc_color">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			22 => [
				'Lorem [blue]ipsum dolor[/blue] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			23 => [
				'Lorem [blue]ipsum dolor[/blue] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [blue]ipsum dolor[/blue] sit amet.</p>' . "\n",
			],
			24 => [
				'Lorem [blue]ipsum dolor[/blue] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="color: blue;" class="bbc_color">ipsum dolor</span> sit amet.',
			],
			25 => [
				'Lorem [blue]ipsum dolor[/blue] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			26 => [
				'Lorem [br]ipsum dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <br class="bbc_br">ipsum dolor sit amet.</p>' . "\n",
			],
			27 => [
				'Lorem [br]ipsum dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [br]ipsum dolor sit amet.</p>' . "\n",
			],
			28 => [
				'Lorem [br]ipsum dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <br class="bbc_br">ipsum dolor sit amet.',
			],
			29 => [
				'[center]Lorem ipsum dolor sit amet.[/center]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="centertext">' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</div>' . "\n",
			],
			30 => [
				'[center]Lorem ipsum dolor sit amet.[/center]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[center]Lorem ipsum dolor sit amet.[/center]</p>' . "\n",
			],
			31 => [
				'[center]Lorem ipsum dolor sit amet.[/center]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="centertext">Lorem ipsum dolor sit amet.</div>',
			],
			32 => [
				'[code]Lorem ipsum dolor sit amet.[/code]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code' . "\n" . '</div>' . "\n" . '<pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>Lorem ipsum dolor sit amet.</code></pre>' . "\n",
			],
			33 => [
				'[code]Lorem ipsum dolor sit amet.[/code]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[code]Lorem ipsum dolor sit amet.[/code]</p>' . "\n",
			],
			34 => [
				'[code]Lorem ipsum dolor sit amet.[/code]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>Lorem ipsum dolor sit amet.</code></pre>',
			],
			35 => [
				'[code=foobar]Lorem ipsum dolor sit amet.[/code]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code (foobar)' . "\n" . '</div>' . "\n" . '<pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>Lorem ipsum dolor sit amet.</code></pre>' . "\n",
			],
			36 => [
				'[code=foobar]Lorem ipsum dolor sit amet.[/code]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[code=foobar]Lorem ipsum dolor sit amet.[/code]</p>' . "\n",
			],
			37 => [
				'[code=foobar]Lorem ipsum dolor sit amet.[/code]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code (foobar)</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>Lorem ipsum dolor sit amet.</code></pre>',
			],
			38 => [
				'Lorem [color=red]ipsum dolor[/color] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="color: red;" class="bbc_color">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			39 => [
				'Lorem [color=red]ipsum dolor[/color] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			40 => [
				'Lorem [color=red]ipsum dolor[/color] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [color=red]ipsum dolor[/color] sit amet.</p>' . "\n",
			],
			41 => [
				'Lorem [color=red]ipsum dolor[/color] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="color: red;" class="bbc_color">ipsum dolor</span> sit amet.',
			],
			42 => [
				'Lorem [color=red]ipsum dolor[/color] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			43 => [
				'[details]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">Details</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n" . '</details>' . "\n",
			],
			44 => [
				'[details]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">Details</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n\n\n" . '</details>' . "\n",
			],
			45 => [
				'[details]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[details]Lorem ipsum dolor sit amet.[/details]</p>' . "\n",
			],
			46 => [
				'[details]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">Details</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.</div></details>',
			],
			47 => [
				'[details summary=unquoted summary]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">unquoted summary</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n" . '</details>' . "\n",
			],
			48 => [
				'[details summary=unquoted summary]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">unquoted summary</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n\n\n" . '</details>' . "\n",
			],
			49 => [
				'[details summary=unquoted summary]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[details summary=unquoted summary]Lorem ipsum dolor sit amet.[/details]</p>' . "\n",
			],
			50 => [
				'[details summary=unquoted summary]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">unquoted summary</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.</div></details>',
			],
			51 => [
				'[details summary="quoted summary"]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">quoted summary</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n" . '</details>' . "\n",
			],
			52 => [
				'[details summary="quoted summary"]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">quoted summary</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n\n\n" . '</details>' . "\n",
			],
			53 => [
				'[details summary="quoted summary"]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[details summary=&quot;quoted summary&quot;]Lorem ipsum dolor sit amet.[/details]</p>' . "\n",
			],
			54 => [
				'[details summary="quoted summary"]Lorem ipsum dolor sit amet.[/details]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">quoted summary</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.</div></details>',
			],
			55 => [
				'Lorem [email]ipsum@email.invalid[/email] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <a href="mailto:ipsum@email.invalid" class="bbc_email">ipsum@email.invalid</a> dolor sit amet.</p>' . "\n",
			],
			56 => [
				'Lorem [email]ipsum@email.invalid[/email] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum@email.invalid dolor sit amet.</p>' . "\n",
			],
			57 => [
				'Lorem [email]ipsum@email.invalid[/email] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [email]ipsum@email.invalid[/email] dolor sit amet.</p>' . "\n",
			],
			58 => [
				'Lorem [email]ipsum@email.invalid[/email] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <a href="mailto:ipsum@email.invalid" class="bbc_email">ipsum@email.invalid</a> dolor sit amet.',
			],
			59 => [
				'Lorem [email]ipsum@email.invalid[/email] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum@email.invalid dolor sit amet.',
			],
			60 => [
				'Lorem [email=ipsum@email.invalid]dolor[/email] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <a href="mailto:ipsum@email.invalid" class="bbc_email">dolor</a> sit amet.</p>' . "\n",
			],
			61 => [
				'Lorem [email=ipsum@email.invalid]dolor[/email] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem dolor (ipsum@email.invalid) sit amet.</p>' . "\n",
			],
			62 => [
				'Lorem [email=ipsum@email.invalid]dolor[/email] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [email=ipsum@email.invalid]dolor[/email] sit amet.</p>' . "\n",
			],
			63 => [
				'Lorem [email=ipsum@email.invalid]dolor[/email] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <a href="mailto:ipsum@email.invalid" class="bbc_email">dolor</a> sit amet.',
			],
			64 => [
				'Lorem [email=ipsum@email.invalid]dolor[/email] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem dolor (ipsum@email.invalid) sit amet.',
			],
			65 => [
				'Lorem [flash=1,2]http://example.com/ipsum.swf[/flash] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <a href="http://example.com/ipsum.swf" target="_blank" rel="noopener">http://example.com/ipsum.swf</a> dolor sit amet.</p>' . "\n",
			],
			66 => [
				'Lorem [flash=1,2]http://example.com/ipsum.swf[/flash] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem http://example.com/ipsum.swf dolor sit amet.</p>' . "\n",
			],
			67 => [
				'Lorem [flash=1,2]http://example.com/ipsum.swf[/flash] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [flash=1,2]http://example.com/ipsum.swf[/flash] dolor sit amet.</p>' . "\n",
			],
			68 => [
				'Lorem [flash=1,2]http://example.com/ipsum.swf[/flash] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <a href="http://example.com/ipsum.swf" target="_blank" rel="noopener">http://example.com/ipsum.swf</a> dolor sit amet.',
			],
			69 => [
				'Lorem [flash=1,2]http://example.com/ipsum.swf[/flash] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem http://example.com/ipsum.swf dolor sit amet.',
			],
			70 => [
				'[float=left]Lorem ipsum dolor sit amet.[/float]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="bbc_float floatleft">' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</div>' . "\n",
			],
			71 => [
				'[float=left]Lorem ipsum dolor sit amet.[/float]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[float=left]Lorem ipsum dolor sit amet.[/float]</p>' . "\n",
			],
			72 => [
				'[float=left]Lorem ipsum dolor sit amet.[/float]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="bbc_float floatleft">Lorem ipsum dolor sit amet.</div>',
			],
			73 => [
				'Lorem [font=Arial]ipsum dolor[/font] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="font-family: Arial;" class="bbc_font">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			74 => [
				'Lorem [font=Arial]ipsum dolor[/font] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [font=Arial]ipsum dolor[/font] sit amet.</p>' . "\n",
			],
			75 => [
				'Lorem [font=Arial]ipsum dolor[/font] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="font-family: Arial;" class="bbc_font">ipsum dolor</span> sit amet.',
			],
			76 => [
				'Lorem [glow=#fff,123]ipsum dolor[/glow] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="text-shadow: #fff 1px 1px 1px">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			77 => [
				'Lorem [glow=#fff,123]ipsum dolor[/glow] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			78 => [
				'Lorem [glow=#fff,123]ipsum dolor[/glow] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [glow=#fff,123]ipsum dolor[/glow] sit amet.</p>' . "\n",
			],
			79 => [
				'Lorem [glow=#fff,123]ipsum dolor[/glow] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="text-shadow: #fff 1px 1px 1px">ipsum dolor</span> sit amet.',
			],
			80 => [
				'Lorem [glow=#fff,123]ipsum dolor[/glow] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			81 => [
				'Lorem [green]ipsum dolor[/green] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="color: green;" class="bbc_color">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			82 => [
				'Lorem [green]ipsum dolor[/green] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			83 => [
				'Lorem [green]ipsum dolor[/green] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [green]ipsum dolor[/green] sit amet.</p>' . "\n",
			],
			84 => [
				'Lorem [green]ipsum dolor[/green] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="color: green;" class="bbc_color">ipsum dolor</span> sit amet.',
			],
			85 => [
				'Lorem [green]ipsum dolor[/green] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			86 => [
				'[h1]Lorem ipsum dolor sit amet.[/h1]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h1 class="bbc_h1">Lorem ipsum dolor sit amet.' . "\n" . '</h1>' . "\n",
			],
			87 => [
				'[h1]Lorem ipsum dolor sit amet.[/h1]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[h1]Lorem ipsum dolor sit amet.[/h1]</p>' . "\n",
			],
			88 => [
				'[h1]Lorem ipsum dolor sit amet.[/h1]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h1 class="bbc_h1">Lorem ipsum dolor sit amet.</h1>',
			],
			89 => [
				'[h2]Lorem ipsum dolor sit amet.[/h2]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Lorem ipsum dolor sit amet.' . "\n" . '</h2>' . "\n",
			],
			90 => [
				'[h2]Lorem ipsum dolor sit amet.[/h2]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[h2]Lorem ipsum dolor sit amet.[/h2]</p>' . "\n",
			],
			91 => [
				'[h2]Lorem ipsum dolor sit amet.[/h2]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Lorem ipsum dolor sit amet.</h2>',
			],
			92 => [
				'[h3]Lorem ipsum dolor sit amet.[/h3]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h3 class="bbc_h3">Lorem ipsum dolor sit amet.' . "\n" . '</h3>' . "\n",
			],
			93 => [
				'[h3]Lorem ipsum dolor sit amet.[/h3]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[h3]Lorem ipsum dolor sit amet.[/h3]</p>' . "\n",
			],
			94 => [
				'[h3]Lorem ipsum dolor sit amet.[/h3]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h3 class="bbc_h3">Lorem ipsum dolor sit amet.</h3>',
			],
			95 => [
				'[h4]Lorem ipsum dolor sit amet.[/h4]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h4 class="bbc_h4">Lorem ipsum dolor sit amet.' . "\n" . '</h4>' . "\n",
			],
			96 => [
				'[h4]Lorem ipsum dolor sit amet.[/h4]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[h4]Lorem ipsum dolor sit amet.[/h4]</p>' . "\n",
			],
			97 => [
				'[h4]Lorem ipsum dolor sit amet.[/h4]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h4 class="bbc_h4">Lorem ipsum dolor sit amet.</h4>',
			],
			98 => [
				'[h5]Lorem ipsum dolor sit amet.[/h5]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h5 class="bbc_h5">Lorem ipsum dolor sit amet.' . "\n" . '</h5>' . "\n",
			],
			99 => [
				'[h5]Lorem ipsum dolor sit amet.[/h5]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[h5]Lorem ipsum dolor sit amet.[/h5]</p>' . "\n",
			],
			100 => [
				'[h5]Lorem ipsum dolor sit amet.[/h5]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h5 class="bbc_h5">Lorem ipsum dolor sit amet.</h5>',
			],
			101 => [
				'[h6]Lorem ipsum dolor sit amet.[/h6]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h6 class="bbc_h6">Lorem ipsum dolor sit amet.' . "\n" . '</h6>' . "\n",
			],
			102 => [
				'[h6]Lorem ipsum dolor sit amet.[/h6]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[h6]Lorem ipsum dolor sit amet.[/h6]</p>' . "\n",
			],
			103 => [
				'[h6]Lorem ipsum dolor sit amet.[/h6]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h6 class="bbc_h6">Lorem ipsum dolor sit amet.</h6>',
			],
			104 => [
				'Lorem ipsum [hr]dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum</p>' . "\n" . '<hr class="bbc_hr">' . "\n" . '<p>dolor sit amet.</p>' . "\n",
			],
			105 => [
				'Lorem ipsum [hr]dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum ' . "\n" . '[hr]' . "\n" . 'dolor sit amet.',
			],
			106 => [
				'Lorem ipsum [hr]dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum [hr]dolor sit amet.</p>' . "\n",
			],
			107 => [
				'Lorem ipsum [hr]dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum ' . "\n" . '<hr class="bbc_hr">' . "\n" . 'dolor sit amet.',
			],
			108 => [
				'Lorem ipsum' . "\n" . '[hr]' . "\n" . 'dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum</p>' . "\n" . '<hr class="bbc_hr">' . "\n" . '<p>dolor sit amet.</p>' . "\n",
			],
			109 => [
				'Lorem ipsum' . "\n" . '[hr]' . "\n" . 'dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum ' . "\n" . '[hr]' . "\n" . ' dolor sit amet.',
			],
			110 => [
				'Lorem ipsum' . "\n" . '[hr]' . "\n" . 'dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum' . "\n" . '[hr]' . "\n" . 'dolor sit amet.</p>' . "\n",
			],
			111 => [
				'Lorem ipsum' . "\n" . '[hr]' . "\n" . 'dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>Lorem ipsum<br>' . "\n" . '[hr]<br>' . "\n" . 'dolor sit amet.</p>' . "\n",
			],
			112 => [
				'Lorem ipsum' . "\n" . '[hr]' . "\n" . 'dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum<br>' . "\n" . '<hr class="bbc_hr">' . "\n" . 'dolor sit amet.',
			],
			113 => [
				'Lorem [i]ipsum dolor[/i] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <em>ipsum dolor</em> sit amet.</p>' . "\n",
			],
			114 => [
				'Lorem [i]ipsum dolor[/i] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [i]ipsum dolor[/i] sit amet.</p>' . "\n",
			],
			115 => [
				'Lorem [i]ipsum dolor[/i] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <em>ipsum dolor</em> sit amet.',
			],
			116 => [
				'Lorem ipsum [img]http://example.com/path/to/file.png[/img] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum <img src="http://example.com/path/to/file.png" alt="" class="bbc_img" loading="lazy"> dolor sit amet.</p>' . "\n",
			],
			117 => [
				'Lorem ipsum [img]http://example.com/path/to/file.png[/img] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum (http://example.com/path/to/file.png) dolor sit amet.</p>' . "\n",
			],
			118 => [
				'Lorem ipsum [img]http://example.com/path/to/file.png[/img] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum [img]http://example.com/path/to/file.png[/img] dolor sit amet.</p>' . "\n",
			],
			119 => [
				'Lorem ipsum [img]http://example.com/path/to/file.png[/img] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum <img src="http://example.com/path/to/file.png" alt="" class="bbc_img" loading="lazy"> dolor sit amet.',
			],
			120 => [
				'Lorem ipsum [img]http://example.com/path/to/file.png[/img] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum (http://example.com/path/to/file.png) dolor sit amet.',
			],
			121 => [
				'Lorem [iurl]http://example.com/path/to/page.html[/iurl] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <a href="http://example.com/path/to/page.html" class="bbc_link">http://example.com/path/to/page.html</a> dolor sit amet.</p>' . "\n",
			],
			122 => [
				'Lorem [iurl]http://example.com/path/to/page.html[/iurl] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem http://example.com/path/to/page.html dolor sit amet.</p>' . "\n",
			],
			123 => [
				'Lorem [iurl]http://example.com/path/to/page.html[/iurl] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [iurl]http://example.com/path/to/page.html[/iurl] dolor sit amet.</p>' . "\n",
			],
			124 => [
				'Lorem [iurl]http://example.com/path/to/page.html[/iurl] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <a href="http://example.com/path/to/page.html" class="bbc_link">http://example.com/path/to/page.html</a> dolor sit amet.',
			],
			125 => [
				'Lorem [iurl]http://example.com/path/to/page.html[/iurl] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem http://example.com/path/to/page.html dolor sit amet.',
			],
			126 => [
				'Lorem ipsum [iurl=http://example.com/path/to/page.html]dolor[/iurl] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum <a href="http://example.com/path/to/page.html" class="bbc_link">dolor</a> sit amet.</p>' . "\n",
			],
			127 => [
				'Lorem ipsum [iurl=http://example.com/path/to/page.html]dolor[/iurl] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor (http://example.com/path/to/page.html) sit amet.</p>' . "\n",
			],
			128 => [
				'Lorem ipsum [iurl=http://example.com/path/to/page.html]dolor[/iurl] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum [iurl=http://example.com/path/to/page.html]dolor[/iurl] sit amet.</p>' . "\n",
			],
			129 => [
				'Lorem ipsum [iurl=http://example.com/path/to/page.html]dolor[/iurl] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum <a href="http://example.com/path/to/page.html" class="bbc_link">dolor</a> sit amet.',
			],
			130 => [
				'Lorem ipsum [iurl=http://example.com/path/to/page.html]dolor[/iurl] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor (http://example.com/path/to/page.html) sit amet.',
			],
			131 => [
				'Lorem ipsum [iurl="http://example.com/path/to/page.html"]dolor[/iurl] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum <a href="http://example.com/path/to/page.html" class="bbc_link">dolor</a> sit amet.</p>' . "\n",
			],
			132 => [
				'Lorem ipsum [iurl="http://example.com/path/to/page.html"]dolor[/iurl] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor (http://example.com/path/to/page.html) sit amet.</p>' . "\n",
			],
			133 => [
				'Lorem ipsum [iurl="http://example.com/path/to/page.html"]dolor[/iurl] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum [iurl=&quot;http://example.com/path/to/page.html&quot;]dolor[/iurl] sit amet.</p>' . "\n",
			],
			134 => [
				'Lorem ipsum [iurl="http://example.com/path/to/page.html"]dolor[/iurl] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum <a href="http://example.com/path/to/page.html" class="bbc_link">dolor</a> sit amet.',
			],
			135 => [
				'Lorem ipsum [iurl="http://example.com/path/to/page.html"]dolor[/iurl] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor (http://example.com/path/to/page.html) sit amet.',
			],
			136 => [
				'[justify]Lorem ipsum dolor sit amet.[/justify]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="justifytext">' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</div>' . "\n",
			],
			137 => [
				'[justify]Lorem ipsum dolor sit amet.[/justify]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[justify]Lorem ipsum dolor sit amet.[/justify]</p>' . "\n",
			],
			138 => [
				'[justify]Lorem ipsum dolor sit amet.[/justify]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="justifytext">Lorem ipsum dolor sit amet.</div>',
			],
			139 => [
				'[right]Lorem ipsum dolor sit amet.[/right]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="righttext">' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</div>' . "\n",
			],
			140 => [
				'[right]Lorem ipsum dolor sit amet.[/right]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[right]Lorem ipsum dolor sit amet.[/right]</p>' . "\n",
			],
			141 => [
				'[right]Lorem ipsum dolor sit amet.[/right]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="righttext">Lorem ipsum dolor sit amet.</div>',
			],
			142 => [
				'[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</li>' . "\n" . '</ul>' . "\n",
			],
			143 => [
				'[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_LINES,
					'for_print' => false,
				],
				'<ul class="bbc_list">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<br>' . "\n" . '</li>' . "\n" . '</ul>' . "\n",
			],
			144 => [
				'[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<ul class="bbc_list">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n\n\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</li>' . "\n\n\n" . '</ul>' . "\n",
			],
			145 => [
				'[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list] [li]Lorem ipsum dolor sit amet.[/li] [li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li] [/list]',
			],
			146 => [
				'[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]</p>' . "\n",
			],
			147 => [
				'[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>[list]<br>' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]<br>' . "\n" . '[li]Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.[/li]<br>' . "\n" . '[/list]</p>' . "\n",
			],
			148 => [
				'[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list">' . "\n" . '<li>Lorem ipsum dolor sit amet.</li>' . "\n" . '<li>Lorem ipsum dolor sit amet.<br><br>Lorem ipsum dolor sit amet.</li></ul>',
			],
			149 => [
				'[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</li>' . "\n" . '</ul>' . "\n",
			],
			150 => [
				'[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_LINES,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<br>' . "\n" . '</li>' . "\n" . '</ul>' . "\n",
			],
			151 => [
				'[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n\n\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</li>' . "\n\n\n" . '</ul>' . "\n",
			],
			152 => [
				'[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list type=disc] [li]Lorem ipsum dolor sit amet.[/li] [li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li] [/list]',
			],
			153 => [
				'[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]</p>' . "\n",
			],
			154 => [
				'[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>[list type=disc]<br>' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]<br>' . "\n" . '[li]Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.[/li]<br>' . "\n" . '[/list]</p>' . "\n",
			],
			155 => [
				'[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>Lorem ipsum dolor sit amet.</li>' . "\n" . '<li>Lorem ipsum dolor sit amet.<br><br>Lorem ipsum dolor sit amet.</li></ul>',
			],
			156 => [
				'[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</li>' . "\n" . '</ol>' . "\n",
			],
			157 => [
				'[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_LINES,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<br>' . "\n" . '</li>' . "\n" . '</ol>' . "\n",
			],
			158 => [
				'[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>Lorem ipsum dolor sit amet.' . "\n" . '</li>' . "\n\n\n" . '<li>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</li>' . "\n\n\n" . '</ol>' . "\n",
			],
			159 => [
				'[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list type=decimal] [li]Lorem ipsum dolor sit amet.[/li] [li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li] [/list]',
			],
			160 => [
				'[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]</p>' . "\n",
			],
			161 => [
				'[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>[list type=decimal]<br>' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]<br>' . "\n" . '[li]Lorem ipsum dolor sit amet.</p>' . "\n" . '<p>Lorem ipsum dolor sit amet.[/li]<br>' . "\n" . '[/list]</p>' . "\n",
			],
			162 => [
				'[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>Lorem ipsum dolor sit amet.</li>' . "\n" . '<li>Lorem ipsum dolor sit amet.<br><br>Lorem ipsum dolor sit amet.</li></ol>',
			],
			163 => [
				'[ltr]Lorem ipsum dolor sit amet.[/ltr]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><bdo dir="ltr">Lorem ipsum dolor sit amet.</bdo></p>' . "\n",
			],
			164 => [
				'[ltr]Lorem ipsum dolor sit amet.[/ltr]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[ltr]Lorem ipsum dolor sit amet.[/ltr]</p>' . "\n",
			],
			165 => [
				'[ltr]Lorem ipsum dolor sit amet.[/ltr]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<bdo dir="ltr">Lorem ipsum dolor sit amet.</bdo>',
			],
			166 => [
				'[me=foobar]Lorem ipsum dolor sit amet.[/me]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="meaction">&#42; foobar Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n",
			],
			167 => [
				'[me=foobar]Lorem ipsum dolor sit amet.[/me]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>/me Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			168 => [
				'[me=foobar]Lorem ipsum dolor sit amet.[/me]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[me=foobar]Lorem ipsum dolor sit amet.[/me]</p>' . "\n",
			],
			169 => [
				'[me=foobar]Lorem ipsum dolor sit amet.[/me]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="meaction">* foobar Lorem ipsum dolor sit amet.</div>',
			],
			170 => [
				'[me=foobar]Lorem ipsum dolor sit amet.[/me]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'/me Lorem ipsum dolor sit amet.<br>',
			],
			171 => [
				'Lorem [member=123]dolor[/member] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <a href="' . Config::$scripturl . '?action=profile;u=123" class="mention" data-mention="123">@dolor</a> sit amet.</p>' . "\n",
			],
			172 => [
				'Lorem [member=123]dolor[/member] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [member=123]dolor[/member] sit amet.</p>' . "\n",
			],
			173 => [
				'Lorem [member=123]dolor[/member] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <a href="' . Config::$scripturl . '?action=profile;u=123" class="mention" data-mention="123">@dolor</a> sit amet.',
			],
			174 => [
				'[move]Lorem ipsum dolor sit amet.[/move]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<marquee>Lorem ipsum dolor sit amet.' . "\n" . '</marquee>' . "\n",
			],
			175 => [
				'[move]Lorem ipsum dolor sit amet.[/move]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<div>Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n",
			],
			176 => [
				'[move]Lorem ipsum dolor sit amet.[/move]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[move]Lorem ipsum dolor sit amet.[/move]</p>' . "\n",
			],
			177 => [
				'[move]Lorem ipsum dolor sit amet.[/move]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<marquee>Lorem ipsum dolor sit amet.</marquee>',
			],
			178 => [
				'[move]Lorem ipsum dolor sit amet.[/move]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<div>Lorem ipsum dolor sit amet.</div>',
			],
			179 => [
				'Lorem [nobbc][b]ipsum[/b][/nobbc] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [b]ipsum[/b] dolor sit amet.</p>' . "\n",
			],
			180 => [
				'Lorem [nobbc][b]ipsum[/b][/nobbc] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem [nobbc]&#91;b&#93;ipsum&#91;/b&#93;[/nobbc] dolor sit amet.',
			],
			181 => [
				'Lorem [nobbc][b]ipsum[/b][/nobbc] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [nobbc][b]ipsum[/b][/nobbc] dolor sit amet.</p>' . "\n",
			],
			182 => [
				'Lorem [nobbc][b]ipsum[/b][/nobbc] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem [b]ipsum[/b] dolor sit amet.',
			],
			183 => [
				'Lorem [nolink]http://example.com/path/to/page.html[/nolink] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem http://example.com/path/to/page.html sit amet.</p>' . "\n",
			],
			184 => [
				'Lorem [nolink]http://example.com/path/to/page.html[/nolink] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [nolink]http://example.com/path/to/page.html[/nolink] sit amet.</p>' . "\n",
			],
			185 => [
				'Lorem [nolink]http://example.com/path/to/page.html[/nolink] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem http://example.com/path/to/page.html sit amet.',
			],
			186 => [
				'Lorem [php]ipsum[/php] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <code class="phpcode"><span style="color: #0000BB">ipsum</span></code> dolor sit amet.</p>' . "\n",
			],
			187 => [
				'Lorem [php]ipsum[/php] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			188 => [
				'Lorem [php]ipsum[/php] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [php]ipsum[/php] dolor sit amet.</p>' . "\n",
			],
			189 => [
				'Lorem [php]ipsum[/php] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <code class="phpcode"><span style="color: #0000BB">ipsum</span></code> dolor sit amet.',
			],
			190 => [
				'Lorem [php]ipsum[/php] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			191 => [
				'Lorem [pre]ipsum dolor[/pre] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <pre>ipsum dolor</pre> sit amet.</p>' . "\n",
			],
			192 => [
				'Lorem [pre]ipsum dolor[/pre] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [pre]ipsum dolor[/pre] sit amet.</p>' . "\n",
			],
			193 => [
				'Lorem [pre]ipsum dolor[/pre] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <pre>ipsum dolor</pre> sit amet.',
			],
			194 => [
				'[quote]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			195 => [
				'[quote]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[quote]Lorem ipsum dolor sit amet.[/quote]</p>' . "\n",
			],
			196 => [
				'[quote]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			197 => [
				'[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: <a href="http://example.com" class="bbc_link" target="_blank" rel="noopener">http://example.com</a></cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			198 => [
				'[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: http://example.com</cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			199 => [
				'[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.',
			],
			200 => [
				'[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]</p>' . "\n",
			],
			201 => [
				'[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: <a href="http://example.com" class="bbc_link" target="_blank" rel="noopener">http://example.com</a></cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			202 => [
				'[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: http://example.com</cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			203 => [
				'[quote="[url]http://example.com[/url]"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: <a href="http://example.com" class="bbc_link" target="_blank" rel="noopener">http://example.com</a></cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			204 => [
				'[quote="[url]http://example.com[/url]"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: http://example.com</cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			205 => [
				'[quote="[url]http://example.com[/url]"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote="[url]http://example.com[/url]"]Lorem ipsum dolor sit amet.',
			],
			206 => [
				'[quote="[url]http://example.com[/url]"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]</p>' . "\n",
			],
			207 => [
				'[quote="[url]http://example.com[/url]"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: <a href="http://example.com" class="bbc_link" target="_blank" rel="noopener">http://example.com</a></cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			208 => [
				'[quote="[url]http://example.com[/url]"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: http://example.com</cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			209 => [
				'[quote="foobar"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: foobar</cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			210 => [
				'[quote="foobar"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[quote=&quot;foobar&quot;]Lorem ipsum dolor sit amet.[/quote]</p>' . "\n",
			],
			211 => [
				'[quote="foobar"]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: foobar</cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			212 => [
				'[quote=foobar]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: foobar</cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			213 => [
				'[quote=foobar]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[quote=foobar]Lorem ipsum dolor sit amet.[/quote]</p>' . "\n",
			],
			214 => [
				'[quote=foobar]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: foobar</cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			215 => [
				'[quote author=foobar]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: foobar</cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			216 => [
				'[quote author=foobar]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[quote author=foobar]Lorem ipsum dolor sit amet.[/quote]</p>' . "\n",
			],
			217 => [
				'[quote author=foobar]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote from: foobar</cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			218 => [
				'[quote author=foobar link=msg=123 date=1793143433]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite><a href="' . Config::$scripturl . '?msg=123">Quote from: foobar on Oct 27, 2026, 11:23 PM</a></cite>' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</blockquote>' . "\n",
			],
			219 => [
				'[quote author=foobar link=msg=123 date=1793143433]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[quote author=foobar link=msg=123 date=1793143433]Lorem ipsum dolor sit amet.[/quote]</p>' . "\n",
			],
			220 => [
				'[quote author=foobar link=msg=123 date=1793143433]Lorem ipsum dolor sit amet.[/quote]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite><a href="' . Config::$scripturl . '?msg=123">Quote from: foobar on Oct 27, 2026, 11:23 PM</a></cite>Lorem ipsum dolor sit amet.</blockquote>',
			],
			221 => [
				'Lorem [red]ipsum dolor[/red] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="color: red;" class="bbc_color">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			222 => [
				'Lorem [red]ipsum dolor[/red] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			223 => [
				'Lorem [red]ipsum dolor[/red] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [red]ipsum dolor[/red] sit amet.</p>' . "\n",
			],
			224 => [
				'Lorem [red]ipsum dolor[/red] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="color: red;" class="bbc_color">ipsum dolor</span> sit amet.',
			],
			225 => [
				'Lorem [red]ipsum dolor[/red] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			226 => [
				'[right]Lorem ipsum dolor sit amet.[/right]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="righttext">' . "\n" . '<p>Lorem ipsum dolor sit amet.</p>' . "\n" . '</div>' . "\n",
			],
			227 => [
				'[right]Lorem ipsum dolor sit amet.[/right]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[right]Lorem ipsum dolor sit amet.[/right]</p>' . "\n",
			],
			228 => [
				'[right]Lorem ipsum dolor sit amet.[/right]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="righttext">Lorem ipsum dolor sit amet.</div>',
			],
			229 => [
				'[rtl]Lorem ipsum dolor sit amet.[/rtl]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><bdo dir="rtl">Lorem ipsum dolor sit amet.</bdo></p>' . "\n",
			],
			230 => [
				'[rtl]Lorem ipsum dolor sit amet.[/rtl]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[rtl]Lorem ipsum dolor sit amet.[/rtl]</p>' . "\n",
			],
			231 => [
				'[rtl]Lorem ipsum dolor sit amet.[/rtl]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<bdo dir="rtl">Lorem ipsum dolor sit amet.</bdo>',
			],
			232 => [
				'Lorem [s]ipsum dolor[/s] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <s>ipsum dolor</s> sit amet.</p>' . "\n",
			],
			233 => [
				'Lorem [s]ipsum dolor[/s] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [s]ipsum dolor[/s] sit amet.</p>' . "\n",
			],
			234 => [
				'Lorem [s]ipsum dolor[/s] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <s>ipsum dolor</s> sit amet.',
			],
			235 => [
				'Lorem [shadow=#000,bottom]ipsum dolor[/shadow] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="text-shadow: #000 0 2px 1px">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			236 => [
				'Lorem [shadow=#000,bottom]ipsum dolor[/shadow] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			237 => [
				'Lorem [shadow=#000,bottom]ipsum dolor[/shadow] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [shadow=#000,bottom]ipsum dolor[/shadow] sit amet.</p>' . "\n",
			],
			238 => [
				'Lorem [shadow=#000,bottom]ipsum dolor[/shadow] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="text-shadow: #000 0 2px 1px">ipsum dolor</span> sit amet.',
			],
			239 => [
				'Lorem [shadow=#000,bottom]ipsum dolor[/shadow] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			240 => [
				'Lorem [size=large]dolor[/size] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="font-size: large;" class="bbc_size">dolor</span> sit amet.</p>' . "\n",
			],
			241 => [
				'Lorem [size=large]dolor[/size] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [size=large]dolor[/size] sit amet.</p>' . "\n",
			],
			242 => [
				'Lorem [size=large]dolor[/size] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="font-size: large;" class="bbc_size">dolor</span> sit amet.',
			],
			243 => [
				'Lorem [size=4]dolor[/size] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="font-size: 1.45em;" class="bbc_size">dolor</span> sit amet.</p>' . "\n",
			],
			244 => [
				'Lorem [size=4]dolor[/size] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [size=4]dolor[/size] sit amet.</p>' . "\n",
			],
			245 => [
				'Lorem [size=4]dolor[/size] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="font-size: 1.45em;" class="bbc_size">dolor</span> sit amet.',
			],
			246 => [
				'Lorem [spoiler]ipsum dolor[/spoiler] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span class="bbc_inline_spoiler" title="Show or hide spoiler content"><button type="button" title="Show or hide spoiler content" tabindex="0"></button><span>ipsum dolor</span></span> sit amet.</p>' . "\n",
			],
			247 => [
				'Lorem [spoiler]ipsum dolor[/spoiler] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [spoiler]ipsum dolor[/spoiler] sit amet.</p>' . "\n",
			],
			248 => [
				'Lorem [spoiler]ipsum dolor[/spoiler] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span class="bbc_inline_spoiler" title="Show or hide spoiler content"><button type="button" title="Show or hide spoiler content" tabindex="0"></button><span>ipsum dolor</span></span> sit amet.',
			],
			249 => [
				'[spoiler=foobar]Lorem ipsum dolor sit amet.[/spoiler]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">foobar</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n" . '</details>' . "\n",
			],
			250 => [
				'[spoiler=foobar]Lorem ipsum dolor sit amet.[/spoiler]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">foobar</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.' . "\n" . '</div>' . "\n\n\n" . '</details>' . "\n",
			],
			251 => [
				'[spoiler=foobar]Lorem ipsum dolor sit amet.[/spoiler]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[spoiler=foobar]Lorem ipsum dolor sit amet.[/spoiler]</p>' . "\n",
			],
			252 => [
				'[spoiler=foobar]Lorem ipsum dolor sit amet.[/spoiler]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<details class="bbc_details"><summary class="bbc_summary">foobar</summary><div class="bbc_details_content">Lorem ipsum dolor sit amet.</div></details>',
			],
			253 => [
				'Lorem [sub]ipsum dolor[/sub] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <sub>ipsum dolor</sub> sit amet.</p>' . "\n",
			],
			254 => [
				'Lorem [sub]ipsum dolor[/sub] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [sub]ipsum dolor[/sub] sit amet.</p>' . "\n",
			],
			255 => [
				'Lorem [sub]ipsum dolor[/sub] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <sub>ipsum dolor</sub> sit amet.',
			],
			256 => [
				'Lorem [sup]ipsum dolor[/sup] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <sup>ipsum dolor</sup> sit amet.</p>' . "\n",
			],
			257 => [
				'Lorem [sup]ipsum dolor[/sup] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [sup]ipsum dolor[/sup] sit amet.</p>' . "\n",
			],
			258 => [
				'Lorem [sup]ipsum dolor[/sup] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <sup>ipsum dolor</sup> sit amet.',
			],
			259 => [
				'[table][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][/table]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<table class="bbc_table">' . "\n" . '<tr>' . "\n" . '<td>Lorem ipsum dolor sit amet.' . "\n" . '</td>' . "\n" . '<td>Lorem ipsum dolor sit amet.' . "\n" . '</td></tr>' . "\n" . '<tr>' . "\n" . '<td>Lorem ipsum dolor sit amet.' . "\n" . '</td>' . "\n" . '<td>Lorem ipsum dolor sit amet.' . "\n" . '</td></tr></table>' . "\n",
			],
			260 => [
				'[table][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][/table]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[table][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][/table]</p>' . "\n",
			],
			261 => [
				'[table][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][/table]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<table class="bbc_table">' . "\n" . '<tr>' . "\n" . '<td>Lorem ipsum dolor sit amet.</td>' . "\n" . '<td>Lorem ipsum dolor sit amet.</td></tr>' . "\n" . '<tr>' . "\n" . '<td>Lorem ipsum dolor sit amet.</td>' . "\n" . '<td>Lorem ipsum dolor sit amet.</td></tr></table>',
			],
			262 => [
				'Lorem [time=2026-10-27T23:23:53+00:00]dolor[/time] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <time class="bbc_time" datetime="2026-10-27T23:23:53+00:00">dolor</time> sit amet.</p>' . "\n",
			],
			263 => [
				'Lorem [time=2026-10-27T23:23:53+00:00]dolor[/time] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [time=2026-10-27T23:23:53+00:00]dolor[/time] sit amet.</p>' . "\n",
			],
			264 => [
				'Lorem [time=2026-10-27T23:23:53+00:00]dolor[/time] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <time class="bbc_time" datetime="2026-10-27T23:23:53+00:00">dolor</time> sit amet.',
			],
			265 => [
				'Lorem ipsum [time]2026-10-27T23:23:53+00:00[/time] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum <time class="bbc_time" datetime="2026-10-27T23:23:53+00:00">2026-10-27T23:23:53+00:00</time> dolor sit amet.</p>' . "\n",
			],
			266 => [
				'Lorem ipsum [time]2026-10-27T23:23:53+00:00[/time] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum [time]2026-10-27T23:23:53+00:00[/time] dolor sit amet.</p>' . "\n",
			],
			267 => [
				'Lorem ipsum [time]2026-10-27T23:23:53+00:00[/time] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum <time class="bbc_time" datetime="2026-10-27T23:23:53+00:00">2026-10-27T23:23:53+00:00</time> dolor sit amet.',
			],
			268 => [
				'Lorem [tt]ipsum[/tt] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <code class="bbc_tt">ipsum</code> dolor sit amet.</p>' . "\n",
			],
			269 => [
				'Lorem [tt]ipsum[/tt] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [tt]ipsum[/tt] dolor sit amet.</p>' . "\n",
			],
			270 => [
				'Lorem [tt]ipsum[/tt] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <code class="bbc_tt">ipsum</code> dolor sit amet.',
			],
			271 => [
				'Lorem [u]ipsum dolor[/u] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <u>ipsum dolor</u> sit amet.</p>' . "\n",
			],
			272 => [
				'Lorem [u]ipsum dolor[/u] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [u]ipsum dolor[/u] sit amet.</p>' . "\n",
			],
			273 => [
				'Lorem [u]ipsum dolor[/u] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <u>ipsum dolor</u> sit amet.',
			],
			274 => [
				'Lorem ipsum [url]http://example.com/path/to/page.html[/url] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum <a href="http://example.com/path/to/page.html" class="bbc_link" target="_blank" rel="noopener">http://example.com/path/to/page.html</a> dolor sit amet.</p>' . "\n",
			],
			275 => [
				'Lorem ipsum [url]http://example.com/path/to/page.html[/url] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum http://example.com/path/to/page.html dolor sit amet.</p>' . "\n",
			],
			276 => [
				'Lorem ipsum [url]http://example.com/path/to/page.html[/url] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum [url]http://example.com/path/to/page.html[/url] dolor sit amet.</p>' . "\n",
			],
			277 => [
				'Lorem ipsum [url]http://example.com/path/to/page.html[/url] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum <a href="http://example.com/path/to/page.html" class="bbc_link" target="_blank" rel="noopener">http://example.com/path/to/page.html</a> dolor sit amet.',
			],
			278 => [
				'Lorem ipsum [url]http://example.com/path/to/page.html[/url] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum http://example.com/path/to/page.html dolor sit amet.',
			],
			279 => [
				'Lorem [url=http://example.com/path/to/page.html]ipsum[/url] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <a href="http://example.com/path/to/page.html" class="bbc_link" target="_blank" rel="noopener">ipsum</a> dolor sit amet.</p>' . "\n",
			],
			280 => [
				'Lorem [url=http://example.com/path/to/page.html]ipsum[/url] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum (http://example.com/path/to/page.html) dolor sit amet.</p>' . "\n",
			],
			281 => [
				'Lorem [url=http://example.com/path/to/page.html]ipsum[/url] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [url=http://example.com/path/to/page.html]ipsum[/url] dolor sit amet.</p>' . "\n",
			],
			282 => [
				'Lorem [url=http://example.com/path/to/page.html]ipsum[/url] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <a href="http://example.com/path/to/page.html" class="bbc_link" target="_blank" rel="noopener">ipsum</a> dolor sit amet.',
			],
			283 => [
				'Lorem [url=http://example.com/path/to/page.html]ipsum[/url] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum (http://example.com/path/to/page.html) dolor sit amet.',
			],
			284 => [
				'Lorem [url="http://example.com/path/to/page.html"]ipsum[/url] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <a href="http://example.com/path/to/page.html" class="bbc_link" target="_blank" rel="noopener">ipsum</a> dolor sit amet.</p>' . "\n",
			],
			285 => [
				'Lorem [url="http://example.com/path/to/page.html"]ipsum[/url] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum (http://example.com/path/to/page.html) dolor sit amet.</p>' . "\n",
			],
			286 => [
				'Lorem [url="http://example.com/path/to/page.html"]ipsum[/url] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [url=&quot;http://example.com/path/to/page.html&quot;]ipsum[/url] dolor sit amet.</p>' . "\n",
			],
			287 => [
				'Lorem [url="http://example.com/path/to/page.html"]ipsum[/url] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <a href="http://example.com/path/to/page.html" class="bbc_link" target="_blank" rel="noopener">ipsum</a> dolor sit amet.',
			],
			288 => [
				'Lorem [url="http://example.com/path/to/page.html"]ipsum[/url] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum (http://example.com/path/to/page.html) dolor sit amet.',
			],
			289 => [
				'Lorem [white]ipsum dolor[/white] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem <span style="color: white;" class="bbc_color">ipsum dolor</span> sit amet.</p>' . "\n",
			],
			290 => [
				'Lorem [white]ipsum dolor[/white] sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum dolor sit amet.</p>' . "\n",
			],
			291 => [
				'Lorem [white]ipsum dolor[/white] sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem [white]ipsum dolor[/white] sit amet.</p>' . "\n",
			],
			292 => [
				'Lorem [white]ipsum dolor[/white] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem <span style="color: white;" class="bbc_color">ipsum dolor</span> sit amet.',
			],
			293 => [
				'Lorem [white]ipsum dolor[/white] sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum dolor sit amet.',
			],
			294 => [
				'Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum</p>' . "\n" . '<div class="videocontainer"><div><iframe frameborder="0" src="https://www.youtube.com/embed/76Gopgo-4PY?origin=://&wmode=opaque&start=0" data-youtube-id="76Gopgo-4PY" allowfullscreen loading="lazy"></iframe>' . "\n" . '</div>' . "\n" . '</div>' . "\n" . '<p>dolor sit amet.</p>' . "\n",
			],
			295 => [
				'Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'<p>Lorem ipsum ' . "\n" . '<a href="https://www.youtube.com/watch?v=76Gopgo-4PY&t={start}s" target="_blank" rel="noopener">https://www.youtube.com/watch?v=76Gopgo-4PY&amp;t={start}s</a>' . "\n" . 'dolor sit amet.</p>' . "\n",
			],
			296 => [
				'Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_LINES,
					'for_print' => false,
				],
				'<p>Lorem ipsum</p>' . "\n" . '<div class="videocontainer"><div><iframe frameborder="0" src="https://www.youtube.com/embed/76Gopgo-4PY?origin=://&wmode=opaque&start=0" data-youtube-id="76Gopgo-4PY" allowfullscreen loading="lazy"></iframe>' . "\n\n\n\n\n" . '</div>' . "\n\n\n\n\n" . '</div>' . "\n" . '<p>dolor sit amet.</p>' . "\n",
			],
			297 => [
				'Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => true,
				],
				'<p>Lorem ipsum <br>' . "\n" . '<a href="https://www.youtube.com/watch?v=76Gopgo-4PY&t={start}s" target="_blank" rel="noopener">https://www.youtube.com/watch?v=76Gopgo-4PY&amp;t={start}s</a><br>' . "\n" . 'dolor sit amet.</p>' . "\n",
			],
			298 => [
				'Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.</p>' . "\n",
			],
			299 => [
				'Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Lorem ipsum ' . "\n" . '<div class="videocontainer"><div><iframe frameborder="0" src="https://www.youtube.com/embed/76Gopgo-4PY?origin=://&wmode=opaque&start=0" data-youtube-id="76Gopgo-4PY" allowfullscreen loading="lazy"></iframe></div></div>' . "\n" . ' dolor sit amet.',
			],
			300 => [
				'Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => true,
				],
				'Lorem ipsum ' . "\n" . '<a href="https://www.youtube.com/watch?v=76Gopgo-4PY&t={start}s" target="_blank" rel="noopener">https://www.youtube.com/watch?v=76Gopgo-4PY&t={start}s</a>' . "\n" . ' dolor sit amet.',
			],
			301 => [
				'Implied 12 hour postmeridian time: [time]Nov 3, 3033, at 3:33[/time]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Implied 12 hour postmeridian time: <time class="bbc_time" datetime="3033-11-03T15:33:00">Nov 3, 3033, at 3:33</time></p>' . "\n",
			],
			302 => [
				'Implied 12 hour postmeridian time: [time]Nov 3, 3033, at 3:33[/time]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Implied 12 hour postmeridian time: [time]Nov 3, 3033, at 3:33[/time]</p>' . "\n",
			],
			303 => [
				'Implied 12 hour postmeridian time: [time]Nov 3, 3033, at 3:33[/time]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Implied 12 hour postmeridian time: <time class="bbc_time" datetime="3033-11-03T15:33:00">Nov 3, 3033, at 3:33</time>',
			],
			304 => [
				'[time=Nov 3, 3033, at 3:33]Implied 12 hour postmeridian time[/time]',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><time class="bbc_time" datetime="3033-11-03T15:33:00">Implied 12 hour postmeridian time</time></p>' . "\n",
			],
			305 => [
				'[time=Nov 3, 3033, at 3:33]Implied 12 hour postmeridian time[/time]',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[time=Nov 3, 3033, at 3:33]Implied 12 hour postmeridian time[/time]</p>' . "\n",
			],
			306 => [
				'[time=Nov 3, 3033, at 3:33]Implied 12 hour postmeridian time[/time]',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<time class="bbc_time" datetime="3033-11-03T15:33:00">Implied 12 hour postmeridian time</time>',
			],
		];
	}

	/**
	 * @return array
	 */
	public static function basicMarkdownStringProvider(): array
	{
		return [
			0 => [
				'paragraph' . "\n" . '    code?' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>paragraph' . "\n" . 'code?</p>' . "\n",
			],
			1 => [
				'paragraph' . "\n" . '    code?' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>paragraph<br>' . "\n" . 'code?</p>' . "\n",
			],
			2 => [
				'paragraph' . "\n" . '    code?' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'paragraph code?',
			],
			3 => [
				'paragraph' . "\n" . '    code?' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'paragraph' . "\n" . 'code?',
			],
			4 => [
				'paragraph' . "\n" . '    code?' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'paragraph<br>&nbsp; &nbsp; code?<br>',
			],
			5 => [
				'paragraph' . "\n" . '    code?' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'paragraph' . "\n" . '    code?',
			],
			6 => [
				'1. one' . "\n\n" . '2. two' . "\n" . '3. three' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>one</li>' . "\n" . '<li>two</li>' . "\n" . '<li>three</li>' . "\n" . '</ol>' . "\n",
			],
			7 => [
				'1. one' . "\n\n" . '2. two' . "\n" . '3. three' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list type=decimal]' . "\n" . '[li]one[/li]' . "\n" . '[li]two[/li]' . "\n" . '[li]three[/li]' . "\n" . '[/list]',
			],
			8 => [
				'1. one' . "\n\n" . '2. two' . "\n" . '3. three' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'1. one<br><br>2. two<br>3. three<br>',
			],
			9 => [
				'1. one' . "\n\n" . '2. two' . "\n" . '3. three' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'1. one' . "\n\n" . '2. two' . "\n" . '3. three',
			],
			10 => [
				'1.  one' . "\n" . '    - a' . "\n\n" . '    - b' . "\n" . '2.  two' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>' . "\n" . '<p>one</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: circle;">' . "\n" . '<li>a</li>' . "\n" . '<li>b</li>' . "\n" . '</ul>' . "\n" . '</li>' . "\n" . '<li>two</li>' . "\n" . '</ol>' . "\n",
			],
			11 => [
				'1.  one' . "\n" . '    - a' . "\n\n" . '    - b' . "\n" . '2.  two' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list type=decimal]' . "\n" . '[li]one' . "\n" . '[list]' . "\n" . '[li]a[/li]' . "\n" . '[li]b[/li]' . "\n" . '[/list][/li]' . "\n" . '[li]two[/li]' . "\n" . '[/list]',
			],
			12 => [
				'1.  one' . "\n" . '    - a' . "\n\n" . '    - b' . "\n" . '2.  two' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'1. &nbsp;one<br>&nbsp; &nbsp; - a<br><br>&nbsp; &nbsp; - b<br>2. &nbsp;two<br>',
			],
			13 => [
				'1.  one' . "\n" . '    - a' . "\n\n" . '    - b' . "\n" . '2.  two' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'1.  one' . "\n" . '    - a' . "\n\n" . '    - b' . "\n" . '2.  two',
			],
			14 => [
				' 8. item 1' . "\n" . ' 9. item 2' . "\n" . '10. item 2a' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>item 1</li>' . "\n" . '<li>item 2</li>' . "\n" . '<li>item 2a</li>' . "\n" . '</ol>' . "\n",
			],
			15 => [
				' 8. item 1' . "\n" . ' 9. item 2' . "\n" . '10. item 2a' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list type=decimal]' . "\n" . '[li]item 1[/li]' . "\n" . '[li]item 2[/li]' . "\n" . '[li]item 2a[/li]' . "\n" . '[/list]',
			],
			16 => [
				' 8. item 1' . "\n" . ' 9. item 2' . "\n" . '10. item 2a' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&nbsp;8. item 1<br>&nbsp;9. item 2<br>10. item 2a<br>',
			],
			17 => [
				' 8. item 1' . "\n" . ' 9. item 2' . "\n" . '10. item 2a' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				' 8. item 1' . "\n" . ' 9. item 2' . "\n" . '10. item 2a',
			],
			18 => [
				'* a' . "\n" . '* * * * *' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>a</li>' . "\n" . '</ul>' . "\n" . '<hr class="bbc_hr">' . "\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>b</li>' . "\n" . '</ul>' . "\n",
			],
			19 => [
				'* a' . "\n" . '* * * * *' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]a[/li]' . "\n" . '[/list]' . "\n" . '[hr]' . "\n" . '[list]' . "\n" . '[li]b[/li]' . "\n" . '[/list]',
			],
			20 => [
				'* a' . "\n" . '* * * * *' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'* a<br>* * * * *<br>* b<br>',
			],
			21 => [
				'* a' . "\n" . '* * * * *' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'* a' . "\n" . '* * * * *' . "\n" . '* b',
			],
			22 => [
				'1. fee' . "\n" . '2. fie' . "\n" . '-  foe' . "\n" . '-  fum' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>fee</li>' . "\n" . '<li>fie</li>' . "\n" . '</ol>' . "\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>foe</li>' . "\n" . '<li>fum</li>' . "\n" . '</ul>' . "\n",
			],
			23 => [
				'1. fee' . "\n" . '2. fie' . "\n" . '-  foe' . "\n" . '-  fum' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list type=decimal]' . "\n" . '[li]fee[/li]' . "\n" . '[li]fie[/li]' . "\n" . '[/list]' . "\n" . '[list]' . "\n" . '[li]foe[/li]' . "\n" . '[li]fum[/li]' . "\n" . '[/list]',
			],
			24 => [
				'1. fee' . "\n" . '2. fie' . "\n" . '-  foe' . "\n" . '-  fum' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'1. fee<br>2. fie<br>- &nbsp;foe<br>- &nbsp;fum<br>',
			],
			25 => [
				'1. fee' . "\n" . '2. fie' . "\n" . '-  foe' . "\n" . '-  fum' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'1. fee' . "\n" . '2. fie' . "\n" . '-  foe' . "\n" . '-  fum',
			],
			26 => [
				'[a backtick (`)](/url) and [another backtick (`)](/url).' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[a backtick (<code class="bbc_tt">)](/url) and [another backtick (</code>)](/url).</p>' . "\n",
			],
			27 => [
				'[a backtick (`)](/url) and [another backtick (`)](/url).' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[a backtick ([tt])](/url) and [another backtick ([/tt])](/url).',
			],
			28 => [
				'[a backtick (`)](/url) and [another backtick (`)](/url).' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[a backtick (`)](/url) and [another backtick (`)](/url).<br>',
			],
			29 => [
				'[a backtick (`)](/url) and [another backtick (`)](/url).' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[a backtick (`)](/url) and [another backtick (`)](/url).',
			],
			30 => [
				'*foo *bar* baz*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo <em>bar</em> baz</em></p>' . "\n",
			],
			31 => [
				'*foo *bar* baz*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo bar baz[/i]',
			],
			32 => [
				'*foo *bar* baz*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo *bar* baz*<br>',
			],
			33 => [
				'*foo *bar* baz*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo *bar* baz*',
			],
			34 => [
				'- `a long code span can contain a hyphen like this' . "\n" . '  - and it can screw things up`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>' . "\n" . '<p>a long code span can contain a hyphen like this</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: circle;">' . "\n" . '<li>and it can screw things up</li>' . "\n" . '</ul>' . "\n" . '</li>' . "\n" . '</ul>' . "\n",
			],
			35 => [
				'- `a long code span can contain a hyphen like this' . "\n" . '  - and it can screw things up`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]a long code span can contain a hyphen like this' . "\n" . '[list]' . "\n" . '[li]and it can screw things up[/li]' . "\n" . '[/list][/li]' . "\n" . '[/list]',
			],
			36 => [
				'- `a long code span can contain a hyphen like this' . "\n" . '  - and it can screw things up`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'- `a long code span can contain a hyphen like this<br>&nbsp; - and it can screw things up`<br>',
			],
			37 => [
				'- `a long code span can contain a hyphen like this' . "\n" . '  - and it can screw things up`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'- `a long code span can contain a hyphen like this' . "\n" . '  - and it can screw things up`',
			],
			38 => [
				'- # Heading' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>' . "\n" . '<h1 class="bbc_h1">Heading</h1>' . "\n\n" . '</li>' . "\n" . '</ul>' . "\n",
			],
			39 => [
				'- # Heading' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li][h1]Heading[/h1][/li]' . "\n" . '[/list]',
			],
			40 => [
				'- # Heading' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'- # Heading<br>',
			],
			41 => [
				'- # Heading' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'- # Heading',
			],
			42 => [
				'* a' . "\n" . '*' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>a' . "\n" . '*</li>' . "\n" . '<li>b</li>' . "\n" . '</ul>' . "\n",
			],
			43 => [
				'* a' . "\n" . '*' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>a<br>' . "\n" . '*</li>' . "\n" . '<li>b</li>' . "\n" . '</ul>' . "\n",
			],
			44 => [
				'* a' . "\n" . '*' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]a *[/li]' . "\n" . '[li]b[/li]' . "\n" . '[/list]',
			],
			45 => [
				'* a' . "\n" . '*' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]a' . "\n" . '*[/li]' . "\n" . '[li]b[/li]' . "\n" . '[/list]',
			],
			46 => [
				'* a' . "\n" . '*' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'* a<br>*<br>* b<br>',
			],
			47 => [
				'* a' . "\n" . '*' . "\n" . '* b' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'* a' . "\n" . '*' . "\n" . '* b',
			],
			48 => [
				'&gt; Blockquote [foo].' . "\n" . '&gt;' . "\n" . '&gt; [foo]: /url' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<p>Blockquote <a href="/url" class="bbc_link" target="_blank" rel="noopener">foo</a>.</p>' . "\n" . '</blockquote>' . "\n",
			],
			49 => [
				'&gt; Blockquote [foo].' . "\n" . '&gt;' . "\n" . '&gt; [foo]: /url' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . 'Blockquote [url="/url"]foo[/url].' . "\n\n\n" . '[/quote]',
			],
			50 => [
				'&gt; Blockquote [foo].' . "\n" . '&gt;' . "\n" . '&gt; [foo]: /url' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; Blockquote [foo].<br>&gt;<br>&gt; [foo]: /url<br>',
			],
			51 => [
				'&gt; Blockquote [foo].' . "\n" . '&gt;' . "\n" . '&gt; [foo]: /url' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; Blockquote [foo].' . "\n" . '&gt;' . "\n" . '&gt; [foo]: /url',
			],
			52 => [
				'[foo]: /url1' . "\n" . '[foo]: /url2' . "\n\n" . '[foo][]' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[foo][]</p>' . "\n",
			],
			53 => [
				'[foo]: /url1' . "\n" . '[foo]: /url2' . "\n\n" . '[foo][]' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				"\n" . '[foo][]',
			],
			54 => [
				'[foo]: /url1' . "\n" . '[foo]: /url2' . "\n\n" . '[foo][]' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo]: /url1<br>[foo]: /url2<br><br>[foo][]<br>',
			],
			55 => [
				'[foo]: /url1' . "\n" . '[foo]: /url2' . "\n\n" . '[foo][]' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo]: /url1' . "\n" . '[foo]: /url2' . "\n\n" . '[foo][]',
			],
			56 => [
				"\t" . 'foo' . "\t" . 'baz' . "\t\t" . 'bim' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>foo' . Utils::TAB_SUBSTITUTE . 'baz' . Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE . 'bim</code></pre>' . "\n",
			],
			57 => [
				"\t" . 'foo' . "\t" . 'baz' . "\t\t" . 'bim' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[code]foo' . "\t" . 'baz' . "\t\t" . 'bim[/code]',
			],
			58 => [
				"\t" . 'foo' . "\t" . 'baz' . "\t\t" . 'bim' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				Utils::TAB_SUBSTITUTE . 'foo' . Utils::TAB_SUBSTITUTE . 'baz' . Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE . 'bim<br>',
			],
			59 => [
				"\t" . 'foo' . "\t" . 'baz' . "\t\t" . 'bim' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				"\t" . 'foo' . "\t" . 'baz' . "\t\t" . 'bim',
			],
			60 => [
				'&gt;' . "\t\t" . 'foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>    foo' . "\n" . '</code></pre>' . "\n" . '</blockquote>' . "\n",
			],
			61 => [
				'&gt;' . "\t\t" . 'foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . '[code]    foo' . "\n" . '[/code]' . "\n" . '[/quote]',
			],
			62 => [
				'&gt;' . "\t\t" . 'foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt;' . Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE . 'foo<br>',
			],
			63 => [
				'&gt;' . "\t\t" . 'foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt;' . "\t\t" . 'foo',
			],
			64 => [
				'-' . "\t\t" . 'foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>foo</li>' . "\n" . '</ul>' . "\n",
			],
			65 => [
				'-' . "\t\t" . 'foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]foo[/li]' . "\n" . '[/list]',
			],
			66 => [
				'-' . "\t\t" . 'foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'-' . Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE . 'foo<br>',
			],
			67 => [
				'-' . "\t\t" . 'foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'-' . "\t\t" . 'foo',
			],
			68 => [
				'#' . "\t" . 'Foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h1 class="bbc_h1">Foo</h1>' . "\n\n",
			],
			69 => [
				'#' . "\t" . 'Foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[h1]Foo[/h1]',
			],
			70 => [
				'#' . "\t" . 'Foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'#' . Utils::TAB_SUBSTITUTE . 'Foo<br>',
			],
			71 => [
				'#' . "\t" . 'Foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'#' . "\t" . 'Foo',
			],
			72 => [
				'*' . "\t" . '*' . "\t" . '*' . "\t\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<hr class="bbc_hr">' . "\n",
			],
			73 => [
				'*' . "\t" . '*' . "\t" . '*' . "\t\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[hr]',
			],
			74 => [
				'*' . "\t" . '*' . "\t" . '*' . "\t\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*' . Utils::TAB_SUBSTITUTE . '*' . Utils::TAB_SUBSTITUTE . '*' . Utils::TAB_SUBSTITUTE . '<br>',
			],
			75 => [
				'*' . "\t" . '*' . "\t" . '*' . "\t\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*' . "\t" . '*' . "\t" . '*',
			],
			76 => [
				'***' . "\n" . '---' . "\n" . '___' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<hr class="bbc_hr">' . "\n" . '<hr class="bbc_hr">' . "\n" . '<hr class="bbc_hr">' . "\n",
			],
			77 => [
				'***' . "\n" . '---' . "\n" . '___' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[hr]' . "\n" . '[hr]' . "\n" . '[hr]',
			],
			78 => [
				'***' . "\n" . '---' . "\n" . '___' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***<br>---<br>___<br>',
			],
			79 => [
				'***' . "\n" . '---' . "\n" . '___' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***' . "\n" . '---' . "\n" . '___',
			],
			80 => [
				'+++' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>+++</p>' . "\n",
			],
			81 => [
				'+++' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'+++',
			],
			82 => [
				'+++' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'+++<br>',
			],
			83 => [
				'===' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>===</p>' . "\n",
			],
			84 => [
				'===' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'===',
			],
			85 => [
				'===' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'===<br>',
			],
			86 => [
				'--' . "\n" . '**' . "\n" . '__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>--' . "\n" . '**' . "\n" . '__</p>' . "\n",
			],
			87 => [
				'--' . "\n" . '**' . "\n" . '__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>--<br>' . "\n" . '**<br>' . "\n" . '__</p>' . "\n",
			],
			88 => [
				'--' . "\n" . '**' . "\n" . '__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'-- ** __',
			],
			89 => [
				'--' . "\n" . '**' . "\n" . '__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'--' . "\n" . '**' . "\n" . '__',
			],
			90 => [
				'--' . "\n" . '**' . "\n" . '__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'--<br>**<br>__<br>',
			],
			91 => [
				'_____________________________________' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<hr class="bbc_hr">' . "\n",
			],
			92 => [
				'_____________________________________' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[hr]',
			],
			93 => [
				'_____________________________________' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_____________________________________<br>',
			],
			94 => [
				'_____________________________________' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_____________________________________',
			],
			95 => [
				'Foo' . "\n" . '***' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Foo</p>' . "\n" . '<hr class="bbc_hr">' . "\n" . '<p>bar</p>' . "\n",
			],
			96 => [
				'Foo' . "\n" . '***' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n" . '[hr]' . "\n" . 'bar',
			],
			97 => [
				'Foo' . "\n" . '***' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br>***<br>bar<br>',
			],
			98 => [
				'Foo' . "\n" . '***' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n" . '***' . "\n" . 'bar',
			],
			99 => [
				'Foo' . "\n" . '---' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Foo</h2>' . "\n\n" . '<p>bar</p>' . "\n",
			],
			100 => [
				'Foo' . "\n" . '---' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[h2]Foo[/h2]' . "\n" . 'bar',
			],
			101 => [
				'Foo' . "\n" . '---' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br>---<br>bar<br>',
			],
			102 => [
				'Foo' . "\n" . '---' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n" . '---' . "\n" . 'bar',
			],
			103 => [
				'Foo' . "\n" . '-------------------------' . "\n\n" . 'Foo' . "\n" . '=' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Foo</h2>' . "\n\n" . '<h1 class="bbc_h1">Foo</h1>' . "\n\n",
			],
			104 => [
				'Foo' . "\n" . '-------------------------' . "\n\n" . 'Foo' . "\n" . '=' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[h2]Foo[/h2]' . "\n\n" . '[h1]Foo[/h1]',
			],
			105 => [
				'Foo' . "\n" . '-------------------------' . "\n\n" . 'Foo' . "\n" . '=' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br>-------------------------<br><br>Foo<br>=<br>',
			],
			106 => [
				'Foo' . "\n" . '-------------------------' . "\n\n" . 'Foo' . "\n" . '=' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n" . '-------------------------' . "\n\n" . 'Foo' . "\n" . '=',
			],
			107 => [
				'Foo\\' . "\n" . '----' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Foo</h2>' . "\n\n",
			],
			108 => [
				'Foo\\' . "\n" . '----' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[h2]Foo[/h2]',
			],
			109 => [
				'Foo\\' . "\n" . '----' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo\\<br>----<br>',
			],
			110 => [
				'Foo\\' . "\n" . '----' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo\\' . "\n" . '----',
			],
			111 => [
				'Foo' . "\n" . 'Bar' . "\n" . '---' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Foo' . "\n" . 'Bar</h2>' . "\n\n",
			],
			112 => [
				'Foo' . "\n" . 'Bar' . "\n" . '---' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Foo<br>' . "\n" . 'Bar</h2>' . "\n\n",
			],
			113 => [
				'Foo' . "\n" . 'Bar' . "\n" . '---' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[h2]Foo Bar[/h2]',
			],
			114 => [
				'Foo' . "\n" . 'Bar' . "\n" . '---' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[h2]Foo' . "\n" . 'Bar[/h2]',
			],
			115 => [
				'Foo' . "\n" . 'Bar' . "\n" . '---' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br>Bar<br>---<br>',
			],
			116 => [
				'Foo' . "\n" . 'Bar' . "\n" . '---' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n" . 'Bar' . "\n" . '---',
			],
			117 => [
				'---' . "\n" . 'Foo' . "\n" . '---' . "\n" . 'Bar' . "\n" . '---' . "\n" . 'Baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<hr class="bbc_hr">' . "\n" . '<h2 class="bbc_h2">Foo</h2>' . "\n\n" . '<h2 class="bbc_h2">Bar</h2>' . "\n\n" . '<p>Baz</p>' . "\n",
			],
			118 => [
				'---' . "\n" . 'Foo' . "\n" . '---' . "\n" . 'Bar' . "\n" . '---' . "\n" . 'Baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[hr]' . "\n" . '[h2]Foo[/h2]' . "\n" . '[h2]Bar[/h2]' . "\n" . 'Baz',
			],
			119 => [
				'---' . "\n" . 'Foo' . "\n" . '---' . "\n" . 'Bar' . "\n" . '---' . "\n" . 'Baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'---<br>Foo<br>---<br>Bar<br>---<br>Baz<br>',
			],
			120 => [
				'---' . "\n" . 'Foo' . "\n" . '---' . "\n" . 'Bar' . "\n" . '---' . "\n" . 'Baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'---' . "\n" . 'Foo' . "\n" . '---' . "\n" . 'Bar' . "\n" . '---' . "\n" . 'Baz',
			],
			121 => [
				"\n" . '====' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>====</p>' . "\n",
			],
			122 => [
				"\n" . '====' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'====',
			],
			123 => [
				"\n" . '====' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<br>====<br>',
			],
			124 => [
				"\n" . '====' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				"\n" . '====',
			],
			125 => [
				'---' . "\n" . '---' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<hr class="bbc_hr">' . "\n" . '<hr class="bbc_hr">' . "\n",
			],
			126 => [
				'---' . "\n" . '---' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[hr]' . "\n" . '[hr]',
			],
			127 => [
				'---' . "\n" . '---' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'---<br>---<br>',
			],
			128 => [
				'---' . "\n" . '---' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'---' . "\n" . '---',
			],
			129 => [
				'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Foo' . "\n" . 'bar</h2>' . "\n\n" . '<p>baz</p>' . "\n",
			],
			130 => [
				'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<h2 class="bbc_h2">Foo<br>' . "\n" . 'bar</h2>' . "\n\n" . '<p>baz</p>' . "\n",
			],
			131 => [
				'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[h2]Foo bar[/h2]' . "\n" . 'baz',
			],
			132 => [
				'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[h2]Foo' . "\n" . 'bar[/h2]' . "\n" . 'baz',
			],
			133 => [
				'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br>bar<br>---<br>baz<br>',
			],
			134 => [
				'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz',
			],
			135 => [
				'Foo' . "\n\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Foo</p>' . "\n" . '<h2 class="bbc_h2">bar</h2>' . "\n\n" . '<p>baz</p>' . "\n",
			],
			136 => [
				'Foo' . "\n\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n\n" . '[h2]bar[/h2]' . "\n" . 'baz',
			],
			137 => [
				'Foo' . "\n\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br><br>bar<br>---<br>baz<br>',
			],
			138 => [
				'Foo' . "\n\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n\n" . 'bar' . "\n" . '---' . "\n" . 'baz',
			],
			139 => [
				'Foo' . "\n" . 'bar' . "\n\n" . '---' . "\n\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Foo' . "\n" . 'bar</p>' . "\n" . '<hr class="bbc_hr">' . "\n" . '<p>baz</p>' . "\n",
			],
			140 => [
				'Foo' . "\n" . 'bar' . "\n\n" . '---' . "\n\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>Foo<br>' . "\n" . 'bar</p>' . "\n" . '<hr class="bbc_hr">' . "\n" . '<p>baz</p>' . "\n",
			],
			141 => [
				'Foo' . "\n" . 'bar' . "\n\n" . '---' . "\n\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo bar' . "\n\n" . '[hr]' . "\n\n" . 'baz',
			],
			142 => [
				'Foo' . "\n" . 'bar' . "\n\n" . '---' . "\n\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'Foo' . "\n" . 'bar' . "\n\n" . '[hr]' . "\n\n" . 'baz',
			],
			143 => [
				'Foo' . "\n" . 'bar' . "\n\n" . '---' . "\n\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br>bar<br><br>---<br><br>baz<br>',
			],
			144 => [
				'Foo' . "\n" . 'bar' . "\n\n" . '---' . "\n\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n" . 'bar' . "\n\n" . '---' . "\n\n" . 'baz',
			],
			145 => [
				'Foo' . "\n" . 'bar' . "\n" . '\\---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz</p>' . "\n",
			],
			146 => [
				'Foo' . "\n" . 'bar' . "\n" . '\\---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>Foo<br>' . "\n" . 'bar<br>' . "\n" . '---<br>' . "\n" . 'baz</p>' . "\n",
			],
			147 => [
				'Foo' . "\n" . 'bar' . "\n" . '\\---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo bar --- baz',
			],
			148 => [
				'Foo' . "\n" . 'bar' . "\n" . '\\---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz',
			],
			149 => [
				'Foo' . "\n" . 'bar' . "\n" . '\\---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br>bar<br>\\---<br>baz<br>',
			],
			150 => [
				'Foo' . "\n" . 'bar' . "\n" . '\\---' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo' . "\n" . 'bar' . "\n" . '\\---' . "\n" . 'baz',
			],
			151 => [
				'``' . "\n" . 'foo' . "\n" . '``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">' . "\n" . 'foo' . "\n" . '</code></p>' . "\n",
			],
			152 => [
				'``' . "\n" . 'foo' . "\n" . '``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><code class="bbc_tt"><br>' . "\n" . 'foo<br>' . "\n" . '</code></p>' . "\n",
			],
			153 => [
				'``' . "\n" . 'foo' . "\n" . '``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt] foo [/tt]',
			],
			154 => [
				'``' . "\n" . 'foo' . "\n" . '``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[tt]' . "\n" . 'foo' . "\n" . '[/tt]',
			],
			155 => [
				'``' . "\n" . 'foo' . "\n" . '``' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'``<br>foo<br>``<br>',
			],
			156 => [
				'``' . "\n" . 'foo' . "\n" . '``' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'``' . "\n" . 'foo' . "\n" . '``',
			],
			157 => [
				'```' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '```' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>aaa' . "\n" . '~~~</code></pre>' . "\n",
			],
			158 => [
				'```' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '```' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[code]aaa' . "\n" . '~~~[/code]',
			],
			159 => [
				'```' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '```' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'```<br>aaa<br>~~~<br>```<br>',
			],
			160 => [
				'```' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '```' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'```' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '```',
			],
			161 => [
				'~~~' . "\n" . 'aaa' . "\n" . '```' . "\n" . '~~~' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>aaa' . "\n" . '```</code></pre>' . "\n",
			],
			162 => [
				'~~~' . "\n" . 'aaa' . "\n" . '```' . "\n" . '~~~' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[code]aaa' . "\n" . '```[/code]',
			],
			163 => [
				'~~~' . "\n" . 'aaa' . "\n" . '```' . "\n" . '~~~' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'~~~<br>aaa<br>```<br>~~~<br>',
			],
			164 => [
				'~~~' . "\n" . 'aaa' . "\n" . '```' . "\n" . '~~~' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'~~~' . "\n" . 'aaa' . "\n" . '```' . "\n" . '~~~',
			],
			165 => [
				'````' . "\n" . 'aaa' . "\n" . '```' . "\n" . '``````' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>aaa' . "\n\n" . '```</code></pre>' . "\n",
			],
			166 => [
				'````' . "\n" . 'aaa' . "\n" . '```' . "\n" . '``````' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[code]aaa' . "\n" . '```[/code]',
			],
			167 => [
				'````' . "\n" . 'aaa' . "\n" . '```' . "\n" . '``````' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'````<br>aaa<br>```<br>``````<br>',
			],
			168 => [
				'````' . "\n" . 'aaa' . "\n" . '```' . "\n" . '``````' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'````' . "\n" . 'aaa' . "\n" . '```' . "\n" . '``````',
			],
			169 => [
				'~~~~' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '~~~~' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>aaa' . "\n" . '~~~</code></pre>' . "\n",
			],
			170 => [
				'~~~~' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '~~~~' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[code]aaa' . "\n" . '~~~[/code]',
			],
			171 => [
				'~~~~' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '~~~~' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'~~~~<br>aaa<br>~~~<br>~~~~<br>',
			],
			172 => [
				'~~~~' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '~~~~' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'~~~~' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '~~~~',
			],
			173 => [
				'```' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'',
			],
			174 => [
				'```' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'```<br>',
			],
			175 => [
				'```' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'```',
			],
			176 => [
				'`````' . "\n\n" . '```' . "\n" . 'aaa' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>' . "\n\n" . 'aaa</code></pre>' . "\n",
			],
			177 => [
				'`````' . "\n\n" . '```' . "\n" . 'aaa' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[code]' . "\n" . '```' . "\n" . 'aaa[/code]',
			],
			178 => [
				'`````' . "\n\n" . '```' . "\n" . 'aaa' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`````<br><br>```<br>aaa<br>',
			],
			179 => [
				'`````' . "\n\n" . '```' . "\n" . 'aaa' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`````' . "\n\n" . '```' . "\n" . 'aaa',
			],
			180 => [
				'```' . "\n" . '```' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'',
			],
			181 => [
				'```' . "\n" . '```' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'```<br>```<br>',
			],
			182 => [
				'```' . "\n" . '```' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'```' . "\n" . '```',
			],
			183 => [
				'foo' . "\n" . '```' . "\n" . 'bar' . "\n" . '```' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo</p>' . "\n" . '<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>bar' . "\n\n" . 'baz</code></pre>' . "\n",
			],
			184 => [
				'foo' . "\n" . '```' . "\n" . 'bar' . "\n" . '```' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo' . "\n" . '[code]bar[/code]' . "\n" . 'baz',
			],
			185 => [
				'foo' . "\n" . '```' . "\n" . 'bar' . "\n" . '```' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo<br>```<br>bar<br>```<br>baz<br>',
			],
			186 => [
				'foo' . "\n" . '```' . "\n" . 'bar' . "\n" . '```' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo' . "\n" . '```' . "\n" . 'bar' . "\n" . '```' . "\n" . 'baz',
			],
			187 => [
				'````;' . "\n" . '````' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'',
			],
			188 => [
				'````;' . "\n" . '````' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'````;<br>````<br>',
			],
			189 => [
				'````;' . "\n" . '````' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'````;' . "\n" . '````',
			],
			190 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;table&gt;&lt;tr&gt;&lt;td&gt;</p>' . "\n" . '<pre><br>**Hello**,<br><br>_world_.<br></pre>' . "\n" . '<p>&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;</p>' . "\n",
			],
			191 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt; &lt;pre&gt; [b]Hello[/b],' . "\n\n" . '[i]world[/i]. &lt;/pre&gt; &lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;',
			],
			192 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '[b]Hello[/b],' . "\n\n" . '[i]world[/i].' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;',
			],
			193 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '<strong>Hello</strong>,</p>' . "\n" . '<p><em>world</em>.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;</p>' . "\n",
			],
			194 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;table&gt;&lt;tr&gt;&lt;td&gt;<br>' . "\n" . '&lt;pre&gt;<br>' . "\n" . '<strong>Hello</strong>,</p>' . "\n" . '<p><em>world</em>.<br>' . "\n" . '&lt;/pre&gt;<br>' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;</p>' . "\n",
			],
			195 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;<br><pre><br>**Hello**,<br><br>_world_.<br></pre><br>&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;<br>',
			],
			196 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;',
			],
			197 => [
				'&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;/div&gt;' . "\n" . '<em>foo</em></p>' . "\n",
			],
			198 => [
				'&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;/div&gt;<br>' . "\n" . '<em>foo</em></p>' . "\n",
			],
			199 => [
				'&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;/div&gt; [i]foo[/i]',
			],
			200 => [
				'&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;/div&gt;' . "\n" . '[i]foo[/i]',
			],
			201 => [
				'&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;/div&gt;<br>*foo*<br>',
			],
			202 => [
				'&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;/div&gt;' . "\n" . '*foo*',
			],
			203 => [
				'&lt;div&gt;' . "\n" . '*foo*' . "\n\n" . '*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;div&gt;' . "\n" . '<em>foo</em></p>' . "\n" . '<p><em>bar</em></p>' . "\n",
			],
			204 => [
				'&lt;div&gt;' . "\n" . '*foo*' . "\n\n" . '*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;div&gt;<br>' . "\n" . '<em>foo</em></p>' . "\n" . '<p><em>bar</em></p>' . "\n",
			],
			205 => [
				'&lt;div&gt;' . "\n" . '*foo*' . "\n\n" . '*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;div&gt; [i]foo[/i]' . "\n\n" . '[i]bar[/i]',
			],
			206 => [
				'&lt;div&gt;' . "\n" . '*foo*' . "\n\n" . '*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;div&gt;' . "\n" . '[i]foo[/i]' . "\n\n" . '[i]bar[/i]',
			],
			207 => [
				'&lt;div&gt;' . "\n" . '*foo*' . "\n\n" . '*bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;div&gt;<br>*foo*<br><br>*bar*<br>',
			],
			208 => [
				'&lt;div&gt;' . "\n" . '*foo*' . "\n\n" . '*bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;div&gt;' . "\n" . '*foo*' . "\n\n" . '*bar*',
			],
			209 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . 'foo' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . 'foo' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;</p>' . "\n",
			],
			210 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . 'foo' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;table&gt;&lt;tr&gt;&lt;td&gt;<br>' . "\n" . 'foo<br>' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;</p>' . "\n",
			],
			211 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . 'foo' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt; foo &lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;',
			],
			212 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . 'foo' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . 'foo' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;',
			],
			213 => [
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . 'foo' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;table&gt;&lt;tr&gt;&lt;td&gt;<br>foo<br>&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;<br>',
			],
			214 => [
				'&lt;Warning&gt;' . "\n" . '*bar*' . "\n" . '&lt;/Warning&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;Warning&gt;' . "\n" . '<em>bar</em>' . "\n" . '&lt;/Warning&gt;</p>' . "\n",
			],
			215 => [
				'&lt;Warning&gt;' . "\n" . '*bar*' . "\n" . '&lt;/Warning&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;Warning&gt;<br>' . "\n" . '<em>bar</em><br>' . "\n" . '&lt;/Warning&gt;</p>' . "\n",
			],
			216 => [
				'&lt;Warning&gt;' . "\n" . '*bar*' . "\n" . '&lt;/Warning&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;Warning&gt; [i]bar[/i] &lt;/Warning&gt;',
			],
			217 => [
				'&lt;Warning&gt;' . "\n" . '*bar*' . "\n" . '&lt;/Warning&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;Warning&gt;' . "\n" . '[i]bar[/i]' . "\n" . '&lt;/Warning&gt;',
			],
			218 => [
				'&lt;Warning&gt;' . "\n" . '*bar*' . "\n" . '&lt;/Warning&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;Warning&gt;<br>*bar*<br>&lt;/Warning&gt;<br>',
			],
			219 => [
				'&lt;Warning&gt;' . "\n" . '*bar*' . "\n" . '&lt;/Warning&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;Warning&gt;' . "\n" . '*bar*' . "\n" . '&lt;/Warning&gt;',
			],
			220 => [
				'&lt;/ins&gt;' . "\n" . '*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'</ins>' . "\n" . '*bar*' . "\n",
			],
			221 => [
				'&lt;/ins&gt;' . "\n" . '*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;/ins&gt; [i]bar[/i]',
			],
			222 => [
				'&lt;/ins&gt;' . "\n" . '*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;/ins&gt;' . "\n" . '[i]bar[/i]',
			],
			223 => [
				'&lt;/ins&gt;' . "\n" . '*bar*' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;/ins&gt;' . "\n" . '<em>bar</em></p>' . "\n",
			],
			224 => [
				'&lt;/ins&gt;' . "\n" . '*bar*' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;/ins&gt;<br>' . "\n" . '<em>bar</em></p>' . "\n",
			],
			225 => [
				'&lt;/ins&gt;' . "\n" . '*bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'</ins><br>*bar*<br>',
			],
			226 => [
				'&lt;/ins&gt;' . "\n" . '*bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;/ins&gt;' . "\n" . '*bar*',
			],
			227 => [
				'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<del>' . "\n" . '*foo*' . "\n" . '</del>' . "\n",
			],
			228 => [
				'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;del&gt; [i]foo[/i] &lt;/del&gt;',
			],
			229 => [
				'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;del&gt;' . "\n" . '[i]foo[/i]' . "\n" . '&lt;/del&gt;',
			],
			230 => [
				'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;del&gt;' . "\n" . '<em>foo</em>' . "\n" . '&lt;/del&gt;</p>' . "\n",
			],
			231 => [
				'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;del&gt;<br>' . "\n" . '<em>foo</em><br>' . "\n" . '&lt;/del&gt;</p>' . "\n",
			],
			232 => [
				'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<del><br>*foo*<br></del><br>',
			],
			233 => [
				'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;',
			],
			234 => [
				'&lt;del&gt;' . "\n\n" . '*foo*' . "\n\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<del>' . "\n" . '<p><em>foo</em></p>' . "\n" . '</del>' . "\n",
			],
			235 => [
				'&lt;del&gt;' . "\n\n" . '*foo*' . "\n\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;del&gt;' . "\n\n" . '[i]foo[/i]' . "\n\n" . '&lt;/del&gt;',
			],
			236 => [
				'&lt;del&gt;' . "\n\n" . '*foo*' . "\n\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;del&gt;</p>' . "\n" . '<p><em>foo</em></p>' . "\n" . '<p>&lt;/del&gt;</p>' . "\n",
			],
			237 => [
				'&lt;del&gt;' . "\n\n" . '*foo*' . "\n\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<del><br><br>*foo*<br><br></del><br>',
			],
			238 => [
				'&lt;del&gt;' . "\n\n" . '*foo*' . "\n\n" . '&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;del&gt;' . "\n\n" . '*foo*' . "\n\n" . '&lt;/del&gt;',
			],
			239 => [
				'&lt;del&gt;*foo*&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><del><em>foo</em></del></p>' . "\n",
			],
			240 => [
				'&lt;del&gt;*foo*&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;del&gt;[i]foo[/i]&lt;/del&gt;',
			],
			241 => [
				'&lt;del&gt;*foo*&lt;/del&gt;' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;del&gt;<em>foo</em>&lt;/del&gt;</p>' . "\n",
			],
			242 => [
				'&lt;del&gt;*foo*&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<del>*foo*</del><br>',
			],
			243 => [
				'&lt;del&gt;*foo*&lt;/del&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;del&gt;*foo*&lt;/del&gt;',
			],
			244 => [
				'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '<em>foo</em></p>' . "\n",
			],
			245 => [
				'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;style&gt;p{color:red;}&lt;/style&gt;<br>' . "\n" . '<em>foo</em></p>' . "\n",
			],
			246 => [
				'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;style&gt;p{color:red;}&lt;/style&gt; [i]foo[/i]',
			],
			247 => [
				'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '[i]foo[/i]',
			],
			248 => [
				'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;style&gt;p{color:red;}&lt;/style&gt;<br>*foo*<br>',
			],
			249 => [
				'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '*foo*',
			],
			250 => [
				'Foo' . "\n" . '&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Foo' . "\n" . '&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;</p>' . "\n",
			],
			251 => [
				'Foo' . "\n" . '&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>Foo<br>' . "\n" . '&lt;div&gt;<br>' . "\n" . 'bar<br>' . "\n" . '&lt;/div&gt;</p>' . "\n",
			],
			252 => [
				'Foo' . "\n" . '&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo &lt;div&gt; bar &lt;/div&gt;',
			],
			253 => [
				'Foo' . "\n" . '&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'Foo' . "\n" . '&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;',
			],
			254 => [
				'Foo' . "\n" . '&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Foo<br>&lt;div&gt;<br>bar<br>&lt;/div&gt;<br>',
			],
			255 => [
				'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '<em>foo</em></p>' . "\n",
			],
			256 => [
				'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;div&gt;<br>' . "\n" . 'bar<br>' . "\n" . '&lt;/div&gt;<br>' . "\n" . '<em>foo</em></p>' . "\n",
			],
			257 => [
				'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;div&gt; bar &lt;/div&gt; [i]foo[/i]',
			],
			258 => [
				'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '[i]foo[/i]',
			],
			259 => [
				'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;div&gt;<br>bar<br>&lt;/div&gt;<br>*foo*<br>',
			],
			260 => [
				'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '*foo*',
			],
			261 => [
				'&lt;table&gt;' . "\n\n" . '&lt;tr&gt;' . "\n\n" . '&lt;td&gt;' . "\n" . 'Hi' . "\n" . '&lt;/td&gt;' . "\n\n" . '&lt;/tr&gt;' . "\n\n" . '&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;table&gt;</p>' . "\n" . '<p>&lt;tr&gt;</p>' . "\n" . '<p>&lt;td&gt;' . "\n" . 'Hi' . "\n" . '&lt;/td&gt;</p>' . "\n" . '<p>&lt;/tr&gt;</p>' . "\n" . '<p>&lt;/table&gt;</p>' . "\n",
			],
			262 => [
				'&lt;table&gt;' . "\n\n" . '&lt;tr&gt;' . "\n\n" . '&lt;td&gt;' . "\n" . 'Hi' . "\n" . '&lt;/td&gt;' . "\n\n" . '&lt;/tr&gt;' . "\n\n" . '&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&lt;table&gt;</p>' . "\n" . '<p>&lt;tr&gt;</p>' . "\n" . '<p>&lt;td&gt;<br>' . "\n" . 'Hi<br>' . "\n" . '&lt;/td&gt;</p>' . "\n" . '<p>&lt;/tr&gt;</p>' . "\n" . '<p>&lt;/table&gt;</p>' . "\n",
			],
			263 => [
				'&lt;table&gt;' . "\n\n" . '&lt;tr&gt;' . "\n\n" . '&lt;td&gt;' . "\n" . 'Hi' . "\n" . '&lt;/td&gt;' . "\n\n" . '&lt;/tr&gt;' . "\n\n" . '&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;table&gt;' . "\n\n" . '&lt;tr&gt;' . "\n\n" . '&lt;td&gt; Hi &lt;/td&gt;' . "\n\n" . '&lt;/tr&gt;' . "\n\n" . '&lt;/table&gt;',
			],
			264 => [
				'&lt;table&gt;' . "\n\n" . '&lt;tr&gt;' . "\n\n" . '&lt;td&gt;' . "\n" . 'Hi' . "\n" . '&lt;/td&gt;' . "\n\n" . '&lt;/tr&gt;' . "\n\n" . '&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&lt;table&gt;' . "\n\n" . '&lt;tr&gt;' . "\n\n" . '&lt;td&gt;' . "\n" . 'Hi' . "\n" . '&lt;/td&gt;' . "\n\n" . '&lt;/tr&gt;' . "\n\n" . '&lt;/table&gt;',
			],
			265 => [
				'&lt;table&gt;' . "\n\n" . '&lt;tr&gt;' . "\n\n" . '&lt;td&gt;' . "\n" . 'Hi' . "\n" . '&lt;/td&gt;' . "\n\n" . '&lt;/tr&gt;' . "\n\n" . '&lt;/table&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;table&gt;<br><br>&lt;tr&gt;<br><br>&lt;td&gt;<br>Hi<br>&lt;/td&gt;<br><br>&lt;/tr&gt;<br><br>&lt;/table&gt;<br>',
			],
			266 => [
				'[foo]:' . "\n" . '/url' . "\n\n" . '[foo]' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="/url" class="bbc_link" target="_blank" rel="noopener">foo</a></p>' . "\n",
			],
			267 => [
				'[foo]:' . "\n" . '/url' . "\n\n" . '[foo]' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				"\n" . '[url="/url"]foo[/url]',
			],
			268 => [
				'[foo]:' . "\n" . '/url' . "\n\n" . '[foo]' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo]:<br>/url<br><br>[foo]<br>',
			],
			269 => [
				'[foo]:' . "\n" . '/url' . "\n\n" . '[foo]' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo]:' . "\n" . '/url' . "\n\n" . '[foo]',
			],
			270 => [
				'[foo]:' . "\n\n" . '[foo]' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[foo]:</p>' . "\n" . '<p>[foo]</p>' . "\n",
			],
			271 => [
				'[foo]:' . "\n\n" . '[foo]' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo]:' . "\n\n" . '[foo]',
			],
			272 => [
				'[foo]:' . "\n\n" . '[foo]' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo]:<br><br>[foo]<br>',
			],
			273 => [
				'aaa' . "\n\n" . 'bbb' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>aaa</p>' . "\n" . '<p>bbb</p>' . "\n",
			],
			274 => [
				'aaa' . "\n\n" . 'bbb' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'aaa' . "\n\n" . 'bbb',
			],
			275 => [
				'aaa' . "\n\n" . 'bbb' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'aaa<br><br>bbb<br>',
			],
			276 => [
				'aaa' . "\n" . 'bbb' . "\n\n" . 'ccc' . "\n" . 'ddd' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>aaa' . "\n" . 'bbb</p>' . "\n" . '<p>ccc' . "\n" . 'ddd</p>' . "\n",
			],
			277 => [
				'aaa' . "\n" . 'bbb' . "\n\n" . 'ccc' . "\n" . 'ddd' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>aaa<br>' . "\n" . 'bbb</p>' . "\n" . '<p>ccc<br>' . "\n" . 'ddd</p>' . "\n",
			],
			278 => [
				'aaa' . "\n" . 'bbb' . "\n\n" . 'ccc' . "\n" . 'ddd' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'aaa bbb' . "\n\n" . 'ccc ddd',
			],
			279 => [
				'aaa' . "\n" . 'bbb' . "\n\n" . 'ccc' . "\n" . 'ddd' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'aaa' . "\n" . 'bbb' . "\n\n" . 'ccc' . "\n" . 'ddd',
			],
			280 => [
				'aaa' . "\n" . 'bbb' . "\n\n" . 'ccc' . "\n" . 'ddd' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'aaa<br>bbb<br><br>ccc<br>ddd<br>',
			],
			281 => [
				'aaa' . "\n\n\n" . 'bbb' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>aaa</p>' . "\n" . '<p>bbb</p>' . "\n",
			],
			282 => [
				'aaa' . "\n\n\n" . 'bbb' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_LINES,
					'for_print' => false,
				],
				'<p>aaa</p>' . "\n" . '<br>' . "\n" . '<p>bbb</p>' . "\n",
			],
			283 => [
				'aaa' . "\n\n\n" . 'bbb' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'aaa' . "\n\n\n" . 'bbb',
			],
			284 => [
				'aaa' . "\n\n\n" . 'bbb' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'aaa<br><br><br>bbb<br>',
			],
			285 => [
				'&gt; foo' . "\n" . '&gt; ---' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<h2 class="bbc_h2">foo</h2>' . "\n\n" . '</blockquote>' . "\n",
			],
			286 => [
				'&gt; foo' . "\n" . '&gt; ---' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . '[h2]foo[/h2]' . "\n\n" . '[/quote]',
			],
			287 => [
				'&gt; foo' . "\n" . '&gt; ---' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; foo<br>&gt; ---<br>',
			],
			288 => [
				'&gt; foo' . "\n" . '&gt; ---' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; foo' . "\n" . '&gt; ---',
			],
			289 => [
				'&gt; - foo' . "\n" . '&gt; - bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>foo</li>' . "\n" . '<li>bar</li>' . "\n" . '</ul>' . "\n" . '</blockquote>' . "\n",
			],
			290 => [
				'&gt; - foo' . "\n" . '&gt; - bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . '[list]' . "\n" . '[li]foo[/li]' . "\n" . '[li]bar[/li]' . "\n" . '[/list]' . "\n" . '[/quote]',
			],
			291 => [
				'&gt; - foo' . "\n" . '&gt; - bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; - foo<br>&gt; - bar<br>',
			],
			292 => [
				'&gt; - foo' . "\n" . '&gt; - bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; - foo' . "\n" . '&gt; - bar',
			],
			293 => [
				'&gt; foo' . "\n" . '&gt;     - bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<p>foo</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>bar</li>' . "\n" . '</ul>' . "\n" . '</blockquote>' . "\n",
			],
			294 => [
				'&gt; foo' . "\n" . '&gt;     - bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . 'foo' . "\n" . '[list]' . "\n" . '[li]bar[/li]' . "\n" . '[/list]' . "\n" . '[/quote]',
			],
			295 => [
				'&gt; foo' . "\n" . '&gt;     - bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; foo<br>&gt; &nbsp; &nbsp; - bar<br>',
			],
			296 => [
				'&gt; foo' . "\n" . '&gt;     - bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; foo' . "\n" . '&gt;     - bar',
			],
			297 => [
				'&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'',
			],
			298 => [
				'&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt;<br>',
			],
			299 => [
				'&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt;',
			],
			300 => [
				'-one' . "\n\n" . '2.two' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>-one</p>' . "\n" . '<p>2.two</p>' . "\n",
			],
			301 => [
				'-one' . "\n\n" . '2.two' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'-one' . "\n\n" . '2.two',
			],
			302 => [
				'-one' . "\n\n" . '2.two' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'-one<br><br>2.two<br>',
			],
			303 => [
				'*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>*</p>' . "\n",
			],
			304 => [
				'*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*',
			],
			305 => [
				'*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*<br>',
			],
			306 => [
				'foo' . "\n" . '*' . "\n\n" . 'foo' . "\n" . '1.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo' . "\n" . '*</p>' . "\n" . '<p>foo' . "\n" . '1.</p>' . "\n",
			],
			307 => [
				'foo' . "\n" . '*' . "\n\n" . 'foo' . "\n" . '1.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>foo<br>' . "\n" . '*</p>' . "\n" . '<p>foo<br>' . "\n" . '1.</p>' . "\n",
			],
			308 => [
				'foo' . "\n" . '*' . "\n\n" . 'foo' . "\n" . '1.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo *' . "\n\n" . 'foo 1.',
			],
			309 => [
				'foo' . "\n" . '*' . "\n\n" . 'foo' . "\n" . '1.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'foo' . "\n" . '*' . "\n\n" . 'foo' . "\n" . '1.',
			],
			310 => [
				'foo' . "\n" . '*' . "\n\n" . 'foo' . "\n" . '1.' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo<br>*<br><br>foo<br>1.<br>',
			],
			311 => [
				'- foo' . "\n\n" . '  bar' . "\n\n" . '  - baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>' . "\n" . '<p>foo</p>' . "\n" . '<p>bar</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: circle;">' . "\n" . '<li>baz</li>' . "\n" . '</ul>' . "\n" . '</li>' . "\n" . '</ul>' . "\n",
			],
			312 => [
				'- foo' . "\n\n" . '  bar' . "\n\n" . '  - baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]foo' . "\n\n" . 'bar' . "\n\n" . '[list]' . "\n" . '[li]baz[/li]' . "\n" . '[/list][/li]' . "\n" . '[/list]',
			],
			313 => [
				'- foo' . "\n\n" . '  bar' . "\n\n" . '  - baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'- foo<br><br>&nbsp; bar<br><br>&nbsp; - baz<br>',
			],
			314 => [
				'- foo' . "\n\n" . '  bar' . "\n\n" . '  - baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'- foo' . "\n\n" . '  bar' . "\n\n" . '  - baz',
			],
			315 => [
				'   - one' . "\n\n" . '  two' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>one</li>' . "\n" . '</ul>' . "\n" . '<p>two</p>' . "\n",
			],
			316 => [
				'   - one' . "\n\n" . '  two' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]one[/li]' . "\n" . '[/list]' . "\n" . 'two',
			],
			317 => [
				'   - one' . "\n\n" . '  two' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&nbsp; &nbsp;- one<br><br>&nbsp; two<br>',
			],
			318 => [
				'   - one' . "\n\n" . '  two' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'   - one' . "\n\n" . '  two',
			],
			319 => [
				'&gt;   - one' . "\n" . '&gt;' . "\n" . '&gt;  two' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>one</li>' . "\n" . '</ul>' . "\n" . '<p>two</p>' . "\n" . '</blockquote>' . "\n",
			],
			320 => [
				'&gt;   - one' . "\n" . '&gt;' . "\n" . '&gt;  two' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . '[list]' . "\n" . '[li]one[/li]' . "\n" . '[/list]' . "\n" . 'two' . "\n\n" . '[/quote]',
			],
			321 => [
				'&gt;   - one' . "\n" . '&gt;' . "\n" . '&gt;  two' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; &nbsp; - one<br>&gt;<br>&gt; &nbsp;two<br>',
			],
			322 => [
				'&gt;   - one' . "\n" . '&gt;' . "\n" . '&gt;  two' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt;   - one' . "\n" . '&gt;' . "\n" . '&gt;  two',
			],
			323 => [
				' 10. foo' . "\n\n" . '   bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>foo</li>' . "\n" . '</ol>' . "\n" . '<p>bar</p>' . "\n",
			],
			324 => [
				' 10. foo' . "\n\n" . '   bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list type=decimal]' . "\n" . '[li]foo[/li]' . "\n" . '[/list]' . "\n" . 'bar',
			],
			325 => [
				' 10. foo' . "\n\n" . '   bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&nbsp;10. foo<br><br>&nbsp; &nbsp;bar<br>',
			],
			326 => [
				' 10. foo' . "\n\n" . '   bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				' 10. foo' . "\n\n" . '   bar',
			],
			327 => [
				'1.  foo' . "\n\n" . '        indented code' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ol class="bbc_list" style="list-style-type: decimal;">' . "\n" . '<li>' . "\n" . '<p>foo</p>' . "\n" . '<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>indented code' . "\n" . '</code></pre>' . "\n" . '</li>' . "\n" . '</ol>' . "\n",
			],
			328 => [
				'1.  foo' . "\n\n" . '        indented code' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list type=decimal]' . "\n" . '[li]foo' . "\n\n" . '[code]indented code' . "\n" . '[/code][/li]' . "\n" . '[/list]',
			],
			329 => [
				'1.  foo' . "\n\n" . '        indented code' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'1. &nbsp;foo<br><br>&nbsp; &nbsp; &nbsp; &nbsp; indented code<br>',
			],
			330 => [
				'1.  foo' . "\n\n" . '        indented code' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'1.  foo' . "\n\n" . '        indented code',
			],
			331 => [
				'The number of windows in my house is' . "\n" . '14.  The number of doors is 6.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>The number of windows in my house is' . "\n" . '14.  The number of doors is 6.</p>' . "\n",
			],
			332 => [
				'The number of windows in my house is' . "\n" . '14.  The number of doors is 6.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>The number of windows in my house is<br>' . "\n" . '14.  The number of doors is 6.</p>' . "\n",
			],
			333 => [
				'The number of windows in my house is' . "\n" . '14.  The number of doors is 6.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'The number of windows in my house is 14.  The number of doors is 6.',
			],
			334 => [
				'The number of windows in my house is' . "\n" . '14.  The number of doors is 6.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'The number of windows in my house is' . "\n" . '14.  The number of doors is 6.',
			],
			335 => [
				'The number of windows in my house is' . "\n" . '14.  The number of doors is 6.' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'The number of windows in my house is<br>14. &nbsp;The number of doors is 6.<br>',
			],
			336 => [
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>I need to buy</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>new shoes</li>' . "\n" . '<li>a coat</li>' . "\n" . '<li>a plane ticket</li>' . "\n" . '</ul>' . "\n",
			],
			337 => [
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'I need to buy' . "\n" . '[list]' . "\n" . '[li]new shoes[/li]' . "\n" . '[li]a coat[/li]' . "\n" . '[li]a plane ticket[/li]' . "\n" . '[/list]',
			],
			338 => [
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'I need to buy<br>- new shoes<br>- a coat<br>- a plane ticket<br>',
			],
			339 => [
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket',
			],
			340 => [
				'  * I need to buy' . "\n" . '    - new shoes' . "\n" . '    - a coat' . "\n" . '    - a plane ticket' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>' . "\n" . '<p>I need to buy</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: circle;">' . "\n" . '<li>new shoes</li>' . "\n" . '<li>a coat</li>' . "\n" . '<li>a plane ticket</li>' . "\n" . '</ul>' . "\n" . '</li>' . "\n" . '</ul>' . "\n",
			],
			341 => [
				'  * I need to buy' . "\n" . '    - new shoes' . "\n" . '    - a coat' . "\n" . '    - a plane ticket' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]I need to buy' . "\n" . '[list]' . "\n" . '[li]new shoes[/li]' . "\n" . '[li]a coat[/li]' . "\n" . '[li]a plane ticket[/li]' . "\n" . '[/list][/li]' . "\n" . '[/list]',
			],
			342 => [
				'  * I need to buy' . "\n" . '    - new shoes' . "\n" . '    - a coat' . "\n" . '    - a plane ticket' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&nbsp; * I need to buy<br>&nbsp; &nbsp; - new shoes<br>&nbsp; &nbsp; - a coat<br>&nbsp; &nbsp; - a plane ticket<br>',
			],
			343 => [
				'  * I need to buy' . "\n" . '    - new shoes' . "\n" . '    - a coat' . "\n" . '    - a plane ticket' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'  * I need to buy' . "\n" . '    - new shoes' . "\n" . '    - a coat' . "\n" . '    - a plane ticket',
			],
			344 => [
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>I need to buy</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>new shoes</li>' . "\n" . '<li>a coat</li>' . "\n" . '<li>a plane ticket</li>' . "\n" . '</ul>' . "\n",
			],
			345 => [
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'I need to buy' . "\n" . '[list]' . "\n" . '[li]new shoes[/li]' . "\n" . '[li]a coat[/li]' . "\n" . '[li]a plane ticket[/li]' . "\n" . '[/list]',
			],
			346 => [
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'I need to buy<br>- new shoes<br>- a coat<br>- a plane ticket<br>',
			],
			347 => [
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket',
			],
			348 => [
				'`hi`lo`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">hi</code>lo</p>' . "\n",
			],
			349 => [
				'`hi`lo`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt]hi[/tt]lo',
			],
			350 => [
				'`hi`lo`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`hi`lo`<br>',
			],
			351 => [
				'`hi`lo`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`hi`lo`',
			],
			352 => [
				'\\!\\"\\#\\$\\%\\&amp;\'\\(\\)\\*\\+\\,\\-\\.\\/\\:\\;\\&lt;\\=\\&gt;\\?\\@\\[\\\\]\\^\\_\\`\\{\\|\\}\\~' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>!&quot;#$%&amp;&#39;()*+,-./:;&lt;=&gt;?@[]^_`{|}~</p>' . "\n",
			],
			353 => [
				'\\!\\"\\#\\$\\%\\&amp;\'\\(\\)\\*\\+\\,\\-\\.\\/\\:\\;\\&lt;\\=\\&gt;\\?\\@\\[\\\\]\\^\\_\\`\\{\\|\\}\\~' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'!"#$%&amp;\'()*+,-./:;&lt;=&gt;?@[]^_`{|}~',
			],
			354 => [
				'\\!\\"\\#\\$\\%\\&amp;\'\\(\\)\\*\\+\\,\\-\\.\\/\\:\\;\\&lt;\\=\\&gt;\\?\\@\\[\\\\]\\^\\_\\`\\{\\|\\}\\~' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'\\!\\"\\#\\$\\%\\&amp;\'\\(\\)\\*\\+\\,\\-\\.\\/\\:\\;\\&lt;\\=\\&gt;\\?\\@\\[\\\\]\\^\\_\\`\\{\\|\\}\\~<br>',
			],
			355 => [
				'\\!\\"\\#\\$\\%\\&amp;\'\\(\\)\\*\\+\\,\\-\\.\\/\\:\\;\\&lt;\\=\\&gt;\\?\\@\\[\\\\]\\^\\_\\`\\{\\|\\}\\~' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'\\!\\"\\#\\$\\%\\&amp;\'\\(\\)\\*\\+\\,\\-\\.\\/\\:\\;\\&lt;\\=\\&gt;\\?\\@\\[\\\\]\\^\\_\\`\\{\\|\\}\\~',
			],
			356 => [
				'\\*emphasis*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>emphasis</em></p>' . "\n",
			],
			357 => [
				'\\*emphasis*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]emphasis[/i]',
			],
			358 => [
				'\\*emphasis*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'\\*emphasis*<br>',
			],
			359 => [
				'\\*emphasis*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'\\*emphasis*',
			],
			360 => [
				'foo\\' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo<br>bar</p>' . "\n",
			],
			361 => [
				'foo\\' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo' . "\n" . 'bar',
			],
			362 => [
				'foo\\' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo\\<br>bar<br>',
			],
			363 => [
				'foo\\' . "\n" . 'bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo\\' . "\n" . 'bar',
			],
			364 => [
				'~~~' . "\n" . '\\[\\]' . "\n" . '~~~' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<div class="codeheader">Code</div><pre data-select-txt="Select" data-shrink-txt="Shrink" data-expand-txt="Expand" class="bbc_code"><code>\\[\\]</code></pre>' . "\n",
			],
			365 => [
				'~~~' . "\n" . '\\[\\]' . "\n" . '~~~' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[code]\\[\\][/code]',
			],
			366 => [
				'~~~' . "\n" . '\\[\\]' . "\n" . '~~~' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'~~~<br>\\[\\]<br>~~~<br>',
			],
			367 => [
				'~~~' . "\n" . '\\[\\]' . "\n" . '~~~' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'~~~' . "\n" . '\\[\\]' . "\n" . '~~~',
			],
			368 => [
				'&lt;http://example.com?find=\\*&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;http://example.com?find=*&gt;</p>' . "\n",
			],
			369 => [
				'&lt;http://example.com?find=\\*&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://example.com?find=*&gt;',
			],
			370 => [
				'&lt;http://example.com?find=\\*&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://example.com?find=\\*&gt;<br>',
			],
			371 => [
				'&lt;http://example.com?find=\\*&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://example.com?find=\\*&gt;',
			],
			372 => [
				'&amp;copy' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&amp;copy</p>' . "\n",
			],
			373 => [
				'&amp;copy' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;copy',
			],
			374 => [
				'&amp;copy' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;copy<br>',
			],
			375 => [
				'&amp;MadeUpEntity;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&amp;MadeUpEntity;</p>' . "\n",
			],
			376 => [
				'&amp;MadeUpEntity;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;MadeUpEntity;',
			],
			377 => [
				'&amp;MadeUpEntity;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;MadeUpEntity;<br>',
			],
			378 => [
				'`f&amp;ouml;&amp;ouml;`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">f&amp;ouml;&amp;ouml;</code></p>' . "\n",
			],
			379 => [
				'`f&amp;ouml;&amp;ouml;`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt]f&amp;ouml;&amp;ouml;[/tt]',
			],
			380 => [
				'`f&amp;ouml;&amp;ouml;`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`f&amp;ouml;&amp;ouml;`<br>',
			],
			381 => [
				'`f&amp;ouml;&amp;ouml;`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`f&amp;ouml;&amp;ouml;`',
			],
			382 => [
				'&amp;#42;foo&amp;#42;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&amp;#42;foo&amp;#42;' . "\n" . '<em>foo</em></p>' . "\n",
			],
			383 => [
				'&amp;#42;foo&amp;#42;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>&amp;#42;foo&amp;#42;<br>' . "\n" . '<em>foo</em></p>' . "\n",
			],
			384 => [
				'&amp;#42;foo&amp;#42;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;#42;foo&amp;#42; [i]foo[/i]',
			],
			385 => [
				'&amp;#42;foo&amp;#42;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'&amp;#42;foo&amp;#42;' . "\n" . '[i]foo[/i]',
			],
			386 => [
				'&amp;#42;foo&amp;#42;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;#42;foo&amp;#42;<br>*foo*<br>',
			],
			387 => [
				'&amp;#42;foo&amp;#42;' . "\n" . '*foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;#42;foo&amp;#42;' . "\n" . '*foo*',
			],
			388 => [
				'foo&amp;#10;&amp;#10;bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo&amp;#10;&amp;#10;bar</p>' . "\n",
			],
			389 => [
				'foo&amp;#10;&amp;#10;bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo&amp;#10;&amp;#10;bar',
			],
			390 => [
				'foo&amp;#10;&amp;#10;bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo&amp;#10;&amp;#10;bar<br>',
			],
			391 => [
				'&amp;#9;foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&amp;#9;foo</p>' . "\n",
			],
			392 => [
				'&amp;#9;foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;#9;foo',
			],
			393 => [
				'&amp;#9;foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&amp;#9;foo<br>',
			],
			394 => [
				'`foo`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">foo</code></p>' . "\n",
			],
			395 => [
				'`foo`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt]foo[/tt]',
			],
			396 => [
				'`foo`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`foo`<br>',
			],
			397 => [
				'`foo`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`foo`',
			],
			398 => [
				'`&nbsp;b&nbsp;`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt"> b </code></p>' . "\n",
			],
			399 => [
				'`&nbsp;b&nbsp;`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt] b [/tt]',
			],
			400 => [
				'`&nbsp;b&nbsp;`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`&nbsp;b&nbsp;`<br>',
			],
			401 => [
				'`&nbsp;b&nbsp;`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`&nbsp;b&nbsp;`',
			],
			402 => [
				'`foo\\`bar`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">foo`bar</code></p>' . "\n",
			],
			403 => [
				'`foo\\`bar`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt]foo`bar[/tt]',
			],
			404 => [
				'`foo\\`bar`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`foo\\`bar`<br>',
			],
			405 => [
				'`foo\\`bar`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`foo\\`bar`',
			],
			406 => [
				'``foo`bar``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">foobar</code></p>' . "\n",
			],
			407 => [
				'``foo`bar``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt]foobar[/tt]',
			],
			408 => [
				'``foo`bar``' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'``foo`bar``<br>',
			],
			409 => [
				'``foo`bar``' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'``foo`bar``',
			],
			410 => [
				'*foo`*`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>*foo<code class="bbc_tt">*</code></p>' . "\n",
			],
			411 => [
				'*foo`*`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo[tt]*[/tt]',
			],
			412 => [
				'*foo`*`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo`*`<br>',
			],
			413 => [
				'*foo`*`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo`*`',
			],
			414 => [
				'`&lt;http://foo.bar.`baz&gt;`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">&lt;http://foo.bar.</code>baz&gt;</p>' . "\n",
			],
			415 => [
				'`&lt;http://foo.bar.`baz&gt;`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt]&lt;http://foo.bar.[/tt]baz&gt;',
			],
			416 => [
				'`&lt;http://foo.bar.`baz&gt;`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`&lt;http://foo.bar.`baz&gt;`<br>',
			],
			417 => [
				'`&lt;http://foo.bar.`baz&gt;`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`&lt;http://foo.bar.`baz&gt;`',
			],
			418 => [
				'&lt;http://foo.bar.`baz&gt;`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;http://foo.bar.<code class="bbc_tt">baz&gt;</code></p>' . "\n",
			],
			419 => [
				'&lt;http://foo.bar.`baz&gt;`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://foo.bar.[tt]baz&gt;[/tt]',
			],
			420 => [
				'&lt;http://foo.bar.`baz&gt;`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://foo.bar.`baz&gt;`<br>',
			],
			421 => [
				'&lt;http://foo.bar.`baz&gt;`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://foo.bar.`baz&gt;`',
			],
			422 => [
				'```foo``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo</p>' . "\n",
			],
			423 => [
				'```foo``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo',
			],
			424 => [
				'```foo``' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'```foo``<br>',
			],
			425 => [
				'```foo``' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'```foo``',
			],
			426 => [
				'`foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo</p>' . "\n",
			],
			427 => [
				'`foo' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo',
			],
			428 => [
				'`foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`foo<br>',
			],
			429 => [
				'`foo' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`foo',
			],
			430 => [
				'`foo``bar``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">foo</code>bar</p>' . "\n",
			],
			431 => [
				'`foo``bar``' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt]foo[/tt]bar',
			],
			432 => [
				'`foo``bar``' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`foo``bar``<br>',
			],
			433 => [
				'`foo``bar``' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`foo``bar``',
			],
			434 => [
				'***strong emph***' . "\n" . '***strong** in emph*' . "\n" . '***emph* in strong**' . "\n" . '**in strong *emph***' . "\n" . '*in emph **strong***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em><strong>strong emph</strong></em>' . "\n" . '<em><strong>strong</strong> in emph</em>' . "\n" . '<strong><em>emph</em> in strong</strong>' . "\n" . '<strong>in strong <em>emph</em></strong>' . "\n" . '<em>in emph <strong>strong</strong></em></p>' . "\n",
			],
			435 => [
				'***strong emph***' . "\n" . '***strong** in emph*' . "\n" . '***emph* in strong**' . "\n" . '**in strong *emph***' . "\n" . '*in emph **strong***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><em><strong>strong emph</strong></em><br>' . "\n" . '<em><strong>strong</strong> in emph</em><br>' . "\n" . '<strong><em>emph</em> in strong</strong><br>' . "\n" . '<strong>in strong <em>emph</em></strong><br>' . "\n" . '<em>in emph <strong>strong</strong></em></p>' . "\n",
			],
			436 => [
				'***strong emph***' . "\n" . '***strong** in emph*' . "\n" . '***emph* in strong**' . "\n" . '**in strong *emph***' . "\n" . '*in emph **strong***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i][b]strong emph[/b][/i] [i][b]strong[/b] in emph[/i] [b][i]emph[/i] in strong[/b] [b]in strong [i]emph[/i][/b] [i]in emph [b]strong[/b][/i]',
			],
			437 => [
				'***strong emph***' . "\n" . '***strong** in emph*' . "\n" . '***emph* in strong**' . "\n" . '**in strong *emph***' . "\n" . '*in emph **strong***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[i][b]strong emph[/b][/i]' . "\n" . '[i][b]strong[/b] in emph[/i]' . "\n" . '[b][i]emph[/i] in strong[/b]' . "\n" . '[b]in strong [i]emph[/i][/b]' . "\n" . '[i]in emph [b]strong[/b][/i]',
			],
			438 => [
				'***strong emph***' . "\n" . '***strong** in emph*' . "\n" . '***emph* in strong**' . "\n" . '**in strong *emph***' . "\n" . '*in emph **strong***' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***strong emph***<br>***strong** in emph*<br>***emph* in strong**<br>**in strong *emph***<br>*in emph **strong***<br>',
			],
			439 => [
				'***strong emph***' . "\n" . '***strong** in emph*' . "\n" . '***emph* in strong**' . "\n" . '**in strong *emph***' . "\n" . '*in emph **strong***' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***strong emph***' . "\n" . '***strong** in emph*' . "\n" . '***emph* in strong**' . "\n" . '**in strong *emph***' . "\n" . '*in emph **strong***',
			],
			440 => [
				'*emph *with emph* in it*' . "\n" . '**strong **with strong** in it**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>emph <em>with emph</em> in it</em>' . "\n" . '<strong>strong <strong>with strong</strong> in it</strong></p>' . "\n",
			],
			441 => [
				'*emph *with emph* in it*' . "\n" . '**strong **with strong** in it**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><em>emph <em>with emph</em> in it</em><br>' . "\n" . '<strong>strong <strong>with strong</strong> in it</strong></p>' . "\n",
			],
			442 => [
				'*emph *with emph* in it*' . "\n" . '**strong **with strong** in it**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]emph with emph in it[/i] [b]strong with strong in it[/b]',
			],
			443 => [
				'*emph *with emph* in it*' . "\n" . '**strong **with strong** in it**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[i]emph with emph in it[/i]' . "\n" . '[b]strong with strong in it[/b]',
			],
			444 => [
				'*emph *with emph* in it*' . "\n" . '**strong **with strong** in it**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*emph *with emph* in it*<br>**strong **with strong** in it**<br>',
			],
			445 => [
				'*emph *with emph* in it*' . "\n" . '**strong **with strong** in it**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*emph *with emph* in it*' . "\n" . '**strong **with strong** in it**',
			],
			446 => [
				'internal emphasis: foo*bar*baz' . "\n" . 'no emphasis: foo_bar_baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>internal emphasis: foo<em>bar</em>baz' . "\n" . 'no emphasis: foo_bar_baz</p>' . "\n",
			],
			447 => [
				'internal emphasis: foo*bar*baz' . "\n" . 'no emphasis: foo_bar_baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>internal emphasis: foo<em>bar</em>baz<br>' . "\n" . 'no emphasis: foo_bar_baz</p>' . "\n",
			],
			448 => [
				'internal emphasis: foo*bar*baz' . "\n" . 'no emphasis: foo_bar_baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'internal emphasis: foo[i]bar[/i]baz no emphasis: foo_bar_baz',
			],
			449 => [
				'internal emphasis: foo*bar*baz' . "\n" . 'no emphasis: foo_bar_baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'internal emphasis: foo[i]bar[/i]baz' . "\n" . 'no emphasis: foo_bar_baz',
			],
			450 => [
				'internal emphasis: foo*bar*baz' . "\n" . 'no emphasis: foo_bar_baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'internal emphasis: foo*bar*baz<br>no emphasis: foo_bar_baz<br>',
			],
			451 => [
				'internal emphasis: foo*bar*baz' . "\n" . 'no emphasis: foo_bar_baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'internal emphasis: foo*bar*baz' . "\n" . 'no emphasis: foo_bar_baz',
			],
			452 => [
				'a*"foo"*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>a*&quot;foo&quot;*</p>' . "\n",
			],
			453 => [
				'a*"foo"*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a*"foo"*',
			],
			454 => [
				'a*"foo"*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a*"foo"*<br>',
			],
			455 => [
				'*&nbsp;a&nbsp;*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>a *</li>' . "\n" . '</ul>' . "\n",
			],
			456 => [
				'*&nbsp;a&nbsp;*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[list]' . "\n" . '[li]a *[/li]' . "\n" . '[/list]',
			],
			457 => [
				'*&nbsp;a&nbsp;*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*&nbsp;a&nbsp;*<br>',
			],
			458 => [
				'*&nbsp;a&nbsp;*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*&nbsp;a&nbsp;*',
			],
			459 => [
				'foo*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo<em>bar</em></p>' . "\n",
			],
			460 => [
				'foo*bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo[i]bar[/i]',
			],
			461 => [
				'foo*bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo*bar*<br>',
			],
			462 => [
				'foo*bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo*bar*',
			],
			463 => [
				'5*6*78' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>5<em>6</em>78</p>' . "\n",
			],
			464 => [
				'5*6*78' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'5[i]6[/i]78',
			],
			465 => [
				'5*6*78' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'5*6*78<br>',
			],
			466 => [
				'5*6*78' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'5*6*78',
			],
			467 => [
				'a_"foo"_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>a_&quot;foo&quot;_</p>' . "\n",
			],
			468 => [
				'a_"foo"_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a_"foo"_',
			],
			469 => [
				'a_"foo"_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a_"foo"_<br>',
			],
			470 => [
				'foo_bar_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo_bar_</p>' . "\n",
			],
			471 => [
				'foo_bar_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo_bar_',
			],
			472 => [
				'foo_bar_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo_bar_<br>',
			],
			473 => [
				'5_6_78' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>5_6_78</p>' . "\n",
			],
			474 => [
				'5_6_78' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'5_6_78',
			],
			475 => [
				'5_6_78' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'5_6_78<br>',
			],
			476 => [
				'пристаням_стремятся_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>пристаням_стремятся_</p>' . "\n",
			],
			477 => [
				'пристаням_стремятся_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'пристаням_стремятся_',
			],
			478 => [
				'пристаням_стремятся_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'пристаням_стремятся_<br>',
			],
			479 => [
				'aa_"bb"_cc' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>aa_&quot;bb&quot;_cc</p>' . "\n",
			],
			480 => [
				'aa_"bb"_cc' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'aa_"bb"_cc',
			],
			481 => [
				'aa_"bb"_cc' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'aa_"bb"_cc<br>',
			],
			482 => [
				'foo-_(bar)_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo-<em>(bar)</em></p>' . "\n",
			],
			483 => [
				'foo-_(bar)_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo-[i](bar)[/i]',
			],
			484 => [
				'foo-_(bar)_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo-_(bar)_<br>',
			],
			485 => [
				'foo-_(bar)_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo-_(bar)_',
			],
			486 => [
				'_foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>_foo*</p>' . "\n",
			],
			487 => [
				'_foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo*',
			],
			488 => [
				'_foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo*<br>',
			],
			489 => [
				'*(*foo)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>*(*foo)</p>' . "\n",
			],
			490 => [
				'*(*foo)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*(*foo)',
			],
			491 => [
				'*(*foo)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*(*foo)<br>',
			],
			492 => [
				'*(*foo*)*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>(<em>foo</em>)</em></p>' . "\n",
			],
			493 => [
				'*(*foo*)*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i](foo)[/i]',
			],
			494 => [
				'*(*foo*)*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*(*foo*)*<br>',
			],
			495 => [
				'*(*foo*)*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*(*foo*)*',
			],
			496 => [
				'*foo*bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo</em>bar</p>' . "\n",
			],
			497 => [
				'*foo*bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[/i]bar',
			],
			498 => [
				'*foo*bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo*bar<br>',
			],
			499 => [
				'*foo*bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo*bar',
			],
			500 => [
				'_(_foo)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>_(_foo)</p>' . "\n",
			],
			501 => [
				'_(_foo)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_(_foo)',
			],
			502 => [
				'_(_foo)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_(_foo)<br>',
			],
			503 => [
				'_(_foo_)_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>(<em>foo</em>)</em></p>' . "\n",
			],
			504 => [
				'_(_foo_)_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i](foo)[/i]',
			],
			505 => [
				'_(_foo_)_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_(_foo_)_<br>',
			],
			506 => [
				'_(_foo_)_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_(_foo_)_',
			],
			507 => [
				'_foo_bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>_foo_bar</p>' . "\n",
			],
			508 => [
				'_foo_bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo_bar',
			],
			509 => [
				'_foo_bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo_bar<br>',
			],
			510 => [
				'_пристаням_стремятся' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>_пристаням_стремятся</p>' . "\n",
			],
			511 => [
				'_пристаням_стремятся' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_пристаням_стремятся',
			],
			512 => [
				'_пристаням_стремятся' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_пристаням_стремятся<br>',
			],
			513 => [
				'_foo_bar_baz_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo_bar_baz</em></p>' . "\n",
			],
			514 => [
				'_foo_bar_baz_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo_bar_baz[/i]',
			],
			515 => [
				'_foo_bar_baz_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo_bar_baz_<br>',
			],
			516 => [
				'_foo_bar_baz_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo_bar_baz_',
			],
			517 => [
				'_(bar)_.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>(bar)</em>.</p>' . "\n",
			],
			518 => [
				'_(bar)_.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i](bar)[/i].',
			],
			519 => [
				'_(bar)_.' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_(bar)_.<br>',
			],
			520 => [
				'_(bar)_.' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_(bar)_.',
			],
			521 => [
				'a**"foo"**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>a**&quot;foo&quot;**</p>' . "\n",
			],
			522 => [
				'a**"foo"**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a**"foo"**',
			],
			523 => [
				'a**"foo"**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a**"foo"**<br>',
			],
			524 => [
				'foo**bar**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo<strong>bar</strong></p>' . "\n",
			],
			525 => [
				'foo**bar**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo[b]bar[/b]',
			],
			526 => [
				'foo**bar**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo**bar**<br>',
			],
			527 => [
				'foo**bar**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo**bar**',
			],
			528 => [
				'a__"foo"__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>a__&quot;foo&quot;__</p>' . "\n",
			],
			529 => [
				'a__"foo"__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a__"foo"__',
			],
			530 => [
				'a__"foo"__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a__"foo"__<br>',
			],
			531 => [
				'foo__bar__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo__bar__</p>' . "\n",
			],
			532 => [
				'foo__bar__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo__bar__',
			],
			533 => [
				'foo__bar__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo__bar__<br>',
			],
			534 => [
				'5__6__78' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>5__6__78</p>' . "\n",
			],
			535 => [
				'5__6__78' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'5__6__78',
			],
			536 => [
				'5__6__78' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'5__6__78<br>',
			],
			537 => [
				'пристаням__стремятся__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>пристаням__стремятся__</p>' . "\n",
			],
			538 => [
				'пристаням__стремятся__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'пристаням__стремятся__',
			],
			539 => [
				'пристаням__стремятся__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'пристаням__стремятся__<br>',
			],
			540 => [
				'foo-__(bar)__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo-<strong>(bar)</strong></p>' . "\n",
			],
			541 => [
				'foo-__(bar)__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo-[b](bar)[/b]',
			],
			542 => [
				'foo-__(bar)__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo-__(bar)__<br>',
			],
			543 => [
				'foo-__(bar)__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo-__(bar)__',
			],
			544 => [
				'**(**foo)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>**(**foo)</p>' . "\n",
			],
			545 => [
				'**(**foo)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**(**foo)',
			],
			546 => [
				'**(**foo)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**(**foo)<br>',
			],
			547 => [
				'*(**foo**)*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>(<strong>foo</strong>)</em></p>' . "\n",
			],
			548 => [
				'*(**foo**)*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]([b]foo[/b])[/i]',
			],
			549 => [
				'*(**foo**)*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*(**foo**)*<br>',
			],
			550 => [
				'*(**foo**)*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*(**foo**)*',
			],
			551 => [
				'**foo**bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>foo</strong>bar</p>' . "\n",
			],
			552 => [
				'**foo**bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[/b]bar',
			],
			553 => [
				'**foo**bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo**bar<br>',
			],
			554 => [
				'**foo**bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo**bar',
			],
			555 => [
				'__(__foo)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>__(__foo)</p>' . "\n",
			],
			556 => [
				'__(__foo)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__(__foo)',
			],
			557 => [
				'__(__foo)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__(__foo)<br>',
			],
			558 => [
				'_(__foo__)_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>(<strong>foo</strong>)</em></p>' . "\n",
			],
			559 => [
				'_(__foo__)_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]([b]foo[/b])[/i]',
			],
			560 => [
				'_(__foo__)_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_(__foo__)_<br>',
			],
			561 => [
				'_(__foo__)_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_(__foo__)_',
			],
			562 => [
				'__foo__bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>__foo__bar</p>' . "\n",
			],
			563 => [
				'__foo__bar' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo__bar',
			],
			564 => [
				'__foo__bar' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo__bar<br>',
			],
			565 => [
				'__пристаням__стремятся' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>__пристаням__стремятся</p>' . "\n",
			],
			566 => [
				'__пристаням__стремятся' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__пристаням__стремятся',
			],
			567 => [
				'__пристаням__стремятся' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__пристаням__стремятся<br>',
			],
			568 => [
				'__foo__bar__baz__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>foo__bar__baz</strong></p>' . "\n",
			],
			569 => [
				'__foo__bar__baz__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo__bar__baz[/b]',
			],
			570 => [
				'__foo__bar__baz__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo__bar__baz__<br>',
			],
			571 => [
				'__foo__bar__baz__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo__bar__baz__',
			],
			572 => [
				'__(bar)__.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>(bar)</strong>.</p>' . "\n",
			],
			573 => [
				'__(bar)__.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b](bar)[/b].',
			],
			574 => [
				'__(bar)__.' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__(bar)__.<br>',
			],
			575 => [
				'__(bar)__.' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__(bar)__.',
			],
			576 => [
				'*foo' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo' . "\n" . 'bar</em></p>' . "\n",
			],
			577 => [
				'*foo' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><em>foo<br>' . "\n" . 'bar</em></p>' . "\n",
			],
			578 => [
				'*foo' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo bar[/i]',
			],
			579 => [
				'*foo' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[i]foo' . "\n" . 'bar[/i]',
			],
			580 => [
				'*foo' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo<br>bar*<br>',
			],
			581 => [
				'*foo' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo' . "\n" . 'bar*',
			],
			582 => [
				'*foo**bar**baz*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo<strong>bar</strong>baz</em></p>' . "\n",
			],
			583 => [
				'*foo**bar**baz*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[b]bar[/b]baz[/i]',
			],
			584 => [
				'*foo**bar**baz*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo**bar**baz*<br>',
			],
			585 => [
				'*foo**bar**baz*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo**bar**baz*',
			],
			586 => [
				'&lt;p&gt;&lt;em&gt;foo&lt;/em&gt;&lt;em&gt;bar&lt;em&gt;&lt;/em&gt;baz&lt;/em&gt;&lt;/p&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;p&gt;<em>foo</em><em>bar<em></em>baz</em>&lt;/p&gt;</p>' . "\n",
			],
			587 => [
				'&lt;p&gt;&lt;em&gt;foo&lt;/em&gt;&lt;em&gt;bar&lt;em&gt;&lt;/em&gt;baz&lt;/em&gt;&lt;/p&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;p&gt;&lt;em&gt;foo&lt;/em&gt;&lt;em&gt;bar&lt;em&gt;&lt;/em&gt;baz&lt;/em&gt;&lt;/p&gt;',
			],
			588 => [
				'&lt;p&gt;&lt;em&gt;foo&lt;/em&gt;&lt;em&gt;bar&lt;em&gt;&lt;/em&gt;baz&lt;/em&gt;&lt;/p&gt;' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;p&gt;&lt;em&gt;foo&lt;/em&gt;&lt;em&gt;bar&lt;em&gt;&lt;/em&gt;baz&lt;/em&gt;&lt;/p&gt;</p>' . "\n",
			],
			589 => [
				'&lt;p&gt;&lt;em&gt;foo&lt;/em&gt;&lt;em&gt;bar&lt;em&gt;&lt;/em&gt;baz&lt;/em&gt;&lt;/p&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;p&gt;<em>foo</em><em>bar<em></em>baz</em>&lt;/p&gt;<br>',
			],
			590 => [
				'*foo**bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo**bar</em></p>' . "\n",
			],
			591 => [
				'*foo**bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo**bar[/i]',
			],
			592 => [
				'*foo**bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo**bar*<br>',
			],
			593 => [
				'*foo**bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo**bar*',
			],
			594 => [
				'*foo**bar***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo<strong>bar</strong></em></p>' . "\n",
			],
			595 => [
				'*foo**bar***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[b]bar[/b][/i]',
			],
			596 => [
				'*foo**bar***' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo**bar***<br>',
			],
			597 => [
				'*foo**bar***' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo**bar***',
			],
			598 => [
				'foo***bar***baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo<em><strong>bar</strong></em>baz</p>' . "\n",
			],
			599 => [
				'foo***bar***baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo[i][b]bar[/b][/i]baz',
			],
			600 => [
				'foo***bar***baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo***bar***baz<br>',
			],
			601 => [
				'foo***bar***baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo***bar***baz',
			],
			602 => [
				'foo******bar*********baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo<strong><strong><strong>bar</strong></strong></strong>***baz</p>' . "\n",
			],
			603 => [
				'foo******bar*********baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo[b]bar[/b]***baz',
			],
			604 => [
				'foo******bar*********baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo******bar*********baz<br>',
			],
			605 => [
				'foo******bar*********baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo******bar*********baz',
			],
			606 => [
				'**foo' . "\n" . 'bar**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>foo' . "\n" . 'bar</strong></p>' . "\n",
			],
			607 => [
				'**foo' . "\n" . 'bar**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><strong>foo<br>' . "\n" . 'bar</strong></p>' . "\n",
			],
			608 => [
				'**foo' . "\n" . 'bar**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo bar[/b]',
			],
			609 => [
				'**foo' . "\n" . 'bar**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[b]foo' . "\n" . 'bar[/b]',
			],
			610 => [
				'**foo' . "\n" . 'bar**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo<br>bar**<br>',
			],
			611 => [
				'**foo' . "\n" . 'bar**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo' . "\n" . 'bar**',
			],
			612 => [
				'**foo*bar*baz**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>foo<em>bar</em>baz</strong></p>' . "\n",
			],
			613 => [
				'**foo*bar*baz**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[i]bar[/i]baz[/b]',
			],
			614 => [
				'**foo*bar*baz**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo*bar*baz**<br>',
			],
			615 => [
				'**foo*bar*baz**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo*bar*baz**',
			],
			616 => [
				'**foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>*<em>foo</em></p>' . "\n",
			],
			617 => [
				'**foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*[i]foo[/i]',
			],
			618 => [
				'**foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo*<br>',
			],
			619 => [
				'**foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo*',
			],
			620 => [
				'*foo**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo</em>*</p>' . "\n",
			],
			621 => [
				'*foo**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[/i]*',
			],
			622 => [
				'*foo**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo**<br>',
			],
			623 => [
				'*foo**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo**',
			],
			624 => [
				'***foo**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>*<strong>foo</strong></p>' . "\n",
			],
			625 => [
				'***foo**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*[b]foo[/b]',
			],
			626 => [
				'***foo**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***foo**<br>',
			],
			627 => [
				'***foo**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***foo**',
			],
			628 => [
				'****foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>***<em>foo</em></p>' . "\n",
			],
			629 => [
				'****foo*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***[i]foo[/i]',
			],
			630 => [
				'****foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'****foo*<br>',
			],
			631 => [
				'****foo*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'****foo*',
			],
			632 => [
				'**foo***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>foo</strong>*</p>' . "\n",
			],
			633 => [
				'**foo***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[/b]*',
			],
			634 => [
				'**foo***' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo***<br>',
			],
			635 => [
				'**foo***' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo***',
			],
			636 => [
				'*foo****' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo</em>***</p>' . "\n",
			],
			637 => [
				'*foo****' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[/i]***',
			],
			638 => [
				'*foo****' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo****<br>',
			],
			639 => [
				'*foo****' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo****',
			],
			640 => [
				'__foo_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>_<em>foo</em></p>' . "\n",
			],
			641 => [
				'__foo_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_[i]foo[/i]',
			],
			642 => [
				'__foo_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo_<br>',
			],
			643 => [
				'__foo_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo_',
			],
			644 => [
				'_foo__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo</em>_</p>' . "\n",
			],
			645 => [
				'_foo__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[/i]_',
			],
			646 => [
				'_foo__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo__<br>',
			],
			647 => [
				'_foo__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo__',
			],
			648 => [
				'___foo__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>_<strong>foo</strong></p>' . "\n",
			],
			649 => [
				'___foo__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_[b]foo[/b]',
			],
			650 => [
				'___foo__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'___foo__<br>',
			],
			651 => [
				'___foo__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'___foo__',
			],
			652 => [
				'____foo_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>___<em>foo</em></p>' . "\n",
			],
			653 => [
				'____foo_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'___[i]foo[/i]',
			],
			654 => [
				'____foo_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'____foo_<br>',
			],
			655 => [
				'____foo_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'____foo_',
			],
			656 => [
				'__foo___' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>foo</strong>_</p>' . "\n",
			],
			657 => [
				'__foo___' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[/b]_',
			],
			658 => [
				'__foo___' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo___<br>',
			],
			659 => [
				'__foo___' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo___',
			],
			660 => [
				'_foo____' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo</em>___</p>' . "\n",
			],
			661 => [
				'_foo____' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[/i]___',
			],
			662 => [
				'_foo____' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo____<br>',
			],
			663 => [
				'_foo____' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_foo____',
			],
			664 => [
				'**foo**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>foo</strong></p>' . "\n",
			],
			665 => [
				'**foo**' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[/b]',
			],
			666 => [
				'**foo**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo**<br>',
			],
			667 => [
				'**foo**' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**foo**',
			],
			668 => [
				'*_foo_*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em><em>foo</em></em></p>' . "\n",
			],
			669 => [
				'*_foo_*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[/i]',
			],
			670 => [
				'*_foo_*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*_foo_*<br>',
			],
			671 => [
				'*_foo_*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*_foo_*',
			],
			672 => [
				'__foo__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>foo</strong></p>' . "\n",
			],
			673 => [
				'__foo__' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[/b]',
			],
			674 => [
				'__foo__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo__<br>',
			],
			675 => [
				'__foo__' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__foo__',
			],
			676 => [
				'_*foo*_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em><em>foo</em></em></p>' . "\n",
			],
			677 => [
				'_*foo*_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo[/i]',
			],
			678 => [
				'_*foo*_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_*foo*_<br>',
			],
			679 => [
				'_*foo*_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_*foo*_',
			],
			680 => [
				'****foo****' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong><strong>foo</strong></strong></p>' . "\n",
			],
			681 => [
				'****foo****' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[/b]',
			],
			682 => [
				'****foo****' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'****foo****<br>',
			],
			683 => [
				'****foo****' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'****foo****',
			],
			684 => [
				'____foo____' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong><strong>foo</strong></strong></p>' . "\n",
			],
			685 => [
				'____foo____' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[/b]',
			],
			686 => [
				'____foo____' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'____foo____<br>',
			],
			687 => [
				'____foo____' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'____foo____',
			],
			688 => [
				'******foo******' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong><strong><strong>foo</strong></strong></strong></p>' . "\n",
			],
			689 => [
				'******foo******' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]foo[/b]',
			],
			690 => [
				'******foo******' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'******foo******<br>',
			],
			691 => [
				'******foo******' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'******foo******',
			],
			692 => [
				'***foo***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em><strong>foo</strong></em></p>' . "\n",
			],
			693 => [
				'***foo***' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i][b]foo[/b][/i]',
			],
			694 => [
				'***foo***' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***foo***<br>',
			],
			695 => [
				'***foo***' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'***foo***',
			],
			696 => [
				'_____foo_____' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em><strong><strong>foo</strong></strong></em></p>' . "\n",
			],
			697 => [
				'_____foo_____' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i][b]foo[/b][/i]',
			],
			698 => [
				'_____foo_____' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_____foo_____<br>',
			],
			699 => [
				'_____foo_____' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'_____foo_____',
			],
			700 => [
				'*[bar*](/url)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>*<a href="/url" class="bbc_link" target="_blank" rel="noopener">bar*</a></p>' . "\n",
			],
			701 => [
				'*[bar*](/url)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*[url="/url"]bar*[/url]',
			],
			702 => [
				'*[bar*](/url)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*[bar*](/url)<br>',
			],
			703 => [
				'*[bar*](/url)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*[bar*](/url)',
			],
			704 => [
				'**a&lt;http://foo.bar/?q=**&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>a&lt;http://foo.bar/?q=</strong>&gt;</p>' . "\n",
			],
			705 => [
				'**a&lt;http://foo.bar/?q=**&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]a&lt;http://foo.bar/?q=[/b]&gt;',
			],
			706 => [
				'**a&lt;http://foo.bar/?q=**&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**a&lt;http://foo.bar/?q=**&gt;<br>',
			],
			707 => [
				'**a&lt;http://foo.bar/?q=**&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'**a&lt;http://foo.bar/?q=**&gt;',
			],
			708 => [
				'__a&lt;http://foo.bar/?q=__&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><strong>a&lt;http://foo.bar/?q=</strong>&gt;</p>' . "\n",
			],
			709 => [
				'__a&lt;http://foo.bar/?q=__&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[b]a&lt;http://foo.bar/?q=[/b]&gt;',
			],
			710 => [
				'__a&lt;http://foo.bar/?q=__&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__a&lt;http://foo.bar/?q=__&gt;<br>',
			],
			711 => [
				'__a&lt;http://foo.bar/?q=__&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'__a&lt;http://foo.bar/?q=__&gt;',
			],
			712 => [
				'[link](/uri)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="/uri" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n",
			],
			713 => [
				'[link](/uri)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="/uri"]link[/url]',
			],
			714 => [
				'[link](/uri)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](/uri)<br>',
			],
			715 => [
				'[link](/uri)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](/uri)',
			],
			716 => [
				'[link]()' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p></p>' . "\n",
			],
			717 => [
				'[link]()' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'',
			],
			718 => [
				'[link]()' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link]()<br>',
			],
			719 => [
				'[link]()' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link]()',
			],
			720 => [
				'[link](&lt;&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="&lt;&gt;" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n",
			],
			721 => [
				'[link](&lt;&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="&lt;&gt;"]link[/url]',
			],
			722 => [
				'[link](&lt;&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](&lt;&gt;)<br>',
			],
			723 => [
				'[link](&lt;&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](&lt;&gt;)',
			],
			724 => [
				'[link](foo' . "\n" . 'bar)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[link](foo' . "\n" . 'bar)</p>' . "\n",
			],
			725 => [
				'[link](foo' . "\n" . 'bar)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>[link](foo<br>' . "\n" . 'bar)</p>' . "\n",
			],
			726 => [
				'[link](foo' . "\n" . 'bar)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo bar)',
			],
			727 => [
				'[link](foo' . "\n" . 'bar)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[link](foo' . "\n" . 'bar)',
			],
			728 => [
				'[link](foo' . "\n" . 'bar)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo<br>bar)<br>',
			],
			729 => [
				'[link](&lt;foo' . "\n" . 'bar&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[link](&lt;foo' . "\n" . 'bar&gt;)</p>' . "\n",
			],
			730 => [
				'[link](&lt;foo' . "\n" . 'bar&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>[link](&lt;foo<br>' . "\n" . 'bar&gt;)</p>' . "\n",
			],
			731 => [
				'[link](&lt;foo' . "\n" . 'bar&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](&lt;foo bar&gt;)',
			],
			732 => [
				'[link](&lt;foo' . "\n" . 'bar&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[link](&lt;foo' . "\n" . 'bar&gt;)',
			],
			733 => [
				'[link](&lt;foo' . "\n" . 'bar&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](&lt;foo<br>bar&gt;)<br>',
			],
			734 => [
				'[a](&lt;b)c&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c&gt;)</p>' . "\n",
			],
			735 => [
				'[a](&lt;b)c&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="&lt;b"]a[/url]c&gt;)',
			],
			736 => [
				'[a](&lt;b)c&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[a](&lt;b)c&gt;)<br>',
			],
			737 => [
				'[a](&lt;b)c&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[a](&lt;b)c&gt;)',
			],
			738 => [
				'[link](&lt;foo\\&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="&lt;foo&gt;" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n",
			],
			739 => [
				'[link](&lt;foo\\&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="&lt;foo&gt;"]link[/url]',
			],
			740 => [
				'[link](&lt;foo\\&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](&lt;foo\\&gt;)<br>',
			],
			741 => [
				'[link](&lt;foo\\&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](&lt;foo\\&gt;)',
			],
			742 => [
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c' . "\n" . '<a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c&gt;' . "\n" . '[a](<b>c)<br</b>&gt;</p>' . "\n",
			],
			743 => [
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c<br>' . "\n" . '<a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c&gt;<br>' . "\n" . '[a](<b>c)<br</b>&gt;</p>' . "\n",
			],
			744 => [
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="&lt;b"]a[/url]c [url="&lt;b"]a[/url]c&gt; [url="&lt;b&gt;c"]a[/url]',
			],
			745 => [
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[url="&lt;b"]a[/url]c' . "\n" . '[url="&lt;b"]a[/url]c&gt;' . "\n" . '[url="&lt;b&gt;c"]a[/url]',
			],
			746 => [
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c' . "\n" . '<a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c&gt;' . "\n" . '<a href="&lt;b&gt;c" class="bbc_link" target="_blank" rel="noopener">a</a></p>' . "\n",
			],
			747 => [
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c<br>' . "\n" . '<a href="&lt;b" class="bbc_link" target="_blank" rel="noopener">a</a>c&gt;<br>' . "\n" . '<a href="&lt;b&gt;c" class="bbc_link" target="_blank" rel="noopener">a</a></p>' . "\n",
			],
			748 => [
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[a](&lt;b)c<br>[a](&lt;b)c&gt;<br>[a](<b>c)<br</b>>',
			],
			749 => [
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)',
			],
			750 => [
				'[link](\\(foo\\))' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="(foo" class="bbc_link" target="_blank" rel="noopener">link</a>)</p>' . "\n",
			],
			751 => [
				'[link](\\(foo\\))' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="(foo"]link[/url])',
			],
			752 => [
				'[link](\\(foo\\))' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](\\(foo\\))<br>',
			],
			753 => [
				'[link](\\(foo\\))' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](\\(foo\\))',
			],
			754 => [
				'[link](foo(and(bar)))' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="foo(and(bar" class="bbc_link" target="_blank" rel="noopener">link</a>))</p>' . "\n",
			],
			755 => [
				'[link](foo(and(bar)))' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="foo(and(bar"]link[/url]))',
			],
			756 => [
				'[link](foo(and(bar)))' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo(and(bar)))<br>',
			],
			757 => [
				'[link](foo(and(bar)))' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo(and(bar)))',
			],
			758 => [
				'[link](foo\\(and\\(bar\\))' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="foo(and(bar" class="bbc_link" target="_blank" rel="noopener">link</a>)</p>' . "\n",
			],
			759 => [
				'[link](foo\\(and\\(bar\\))' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="foo(and(bar"]link[/url])',
			],
			760 => [
				'[link](foo\\(and\\(bar\\))' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo\\(and\\(bar\\))<br>',
			],
			761 => [
				'[link](foo\\(and\\(bar\\))' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo\\(and\\(bar\\))',
			],
			762 => [
				'[link](&lt;foo(and(bar)&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="&lt;foo(and(bar" class="bbc_link" target="_blank" rel="noopener">link</a>&gt;)</p>' . "\n",
			],
			763 => [
				'[link](&lt;foo(and(bar)&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="&lt;foo(and(bar"]link[/url]&gt;)',
			],
			764 => [
				'[link](&lt;foo(and(bar)&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](&lt;foo(and(bar)&gt;)<br>',
			],
			765 => [
				'[link](&lt;foo(and(bar)&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](&lt;foo(and(bar)&gt;)',
			],
			766 => [
				'[link](foo\\)\\:)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="foo" class="bbc_link" target="_blank" rel="noopener">link</a>&lt;img src=&quot;' . Config::$modSettings['smileys_url'] . '/' . Config::$modSettings['smiley_sets_default'] . '/smiley.png&quot; alt=&quot;&#58;&#41;&quot; title=&quot;Smiley&quot; class=&quot;smiley&quot;&gt;</p>' . "\n",
			],
			767 => [
				'[link](foo\\)\\:)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="foo"]link[/url]:)',
			],
			768 => [
				'[link](foo\\)\\:)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo\\)\\<img src="' . Config::$modSettings['smileys_url'] . '/' . Config::$modSettings['smiley_sets_default'] . '/smiley.png" alt="&#58;&#41;" title="Smiley" class="smiley"><br>',
			],
			769 => [
				'[link](foo\\)\\:)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo\\)\\:)',
			],
			770 => [
				'[link](foo\\)\\:)' . "\n",
				Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo\\)\\<img src="' . Config::$modSettings['smileys_url'] . '/' . Config::$modSettings['smiley_sets_default'] . '/smiley.png" alt="&#58;&#41;" title="Smiley" class="smiley">' . "\n",
			],
			771 => [
				'[link](foo\\)\\:)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="foo" class="bbc_link" target="_blank" rel="noopener">link</a>:)</p>' . "\n",
			],
			772 => [
				'[link](foo\\)\\:)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo\\)\\:)<br>',
			],
			773 => [
				'[link](#fragment)' . "\n\n" . '[link](http://example.com#fragment)' . "\n\n" . '[link](http://example.com?foo=3#frag)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="#fragment" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n" . '<p><a href="http://example.com#fragment" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n" . '<p><a href="http://example.com?foo=3#frag" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n",
			],
			774 => [
				'[link](#fragment)' . "\n\n" . '[link](http://example.com#fragment)' . "\n\n" . '[link](http://example.com?foo=3#frag)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="#fragment"]link[/url]' . "\n\n" . '[url="http://example.com#fragment"]link[/url]' . "\n\n" . '[url="http://example.com?foo=3#frag"]link[/url]',
			],
			775 => [
				'[link](#fragment)' . "\n\n" . '[link](http://example.com#fragment)' . "\n\n" . '[link](http://example.com?foo=3#frag)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](#fragment)<br><br>[link](http://example.com#fragment)<br><br>[link](http://example.com?foo=3#frag)<br>',
			],
			776 => [
				'[link](#fragment)' . "\n\n" . '[link](http://example.com#fragment)' . "\n\n" . '[link](http://example.com?foo=3#frag)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](#fragment)' . "\n\n" . '[link](http://example.com#fragment)' . "\n\n" . '[link](http://example.com?foo=3#frag)',
			],
			777 => [
				'[link](foo\\bar)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="foo\\bar" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n",
			],
			778 => [
				'[link](foo\\bar)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="foo\\bar"]link[/url]',
			],
			779 => [
				'[link](foo\\bar)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo\\bar)<br>',
			],
			780 => [
				'[link](foo\\bar)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo\\bar)',
			],
			781 => [
				'[link](foo%20b&amp;auml;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="foo%20b&amp;auml;" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n",
			],
			782 => [
				'[link](foo%20b&amp;auml;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="foo%20b&amp;auml;"]link[/url]',
			],
			783 => [
				'[link](foo%20b&amp;auml;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo%20b&amp;auml;)<br>',
			],
			784 => [
				'[link](foo%20b&amp;auml;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](foo%20b&amp;auml;)',
			],
			785 => [
				'[link]("title")' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href=""title"" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n",
			],
			786 => [
				'[link]("title")' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url=""title""]link[/url]',
			],
			787 => [
				'[link]("title")' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link]("title")<br>',
			],
			788 => [
				'[link]("title")' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link]("title")',
			],
			789 => [
				'[link](/url&nbsp;"title")' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="/url" class="bbc_link" target="_blank" rel="noopener">link</a></p>' . "\n",
			],
			790 => [
				'[link](/url&nbsp;"title")' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="/url"]link[/url]',
			],
			791 => [
				'[link](/url&nbsp;"title")' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](/url&nbsp;"title")<br>',
			],
			792 => [
				'[link](/url&nbsp;"title")' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[link](/url&nbsp;"title")',
			],
			793 => [
				'[![moon](moon.jpg)](/uri)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="/uri" class="bbc_link" target="_blank" rel="noopener"><img src="//moon.jpg" alt="" class="bbc_img" loading="lazy"></a></p>' . "\n",
			],
			794 => [
				'[![moon](moon.jpg)](/uri)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="/uri"][img]moon.jpg[/img][/url]',
			],
			795 => [
				'[![moon](moon.jpg)](/uri)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[![moon](moon.jpg)](/uri)<br>',
			],
			796 => [
				'[![moon](moon.jpg)](/uri)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[![moon](moon.jpg)](/uri)',
			],
			797 => [
				'![[[foo](uri1)](uri2)](uri3)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><img src="//uri3" alt="" class="bbc_img" loading="lazy"></p>' . "\n",
			],
			798 => [
				'![[[foo](uri1)](uri2)](uri3)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[img]uri3[/img]',
			],
			799 => [
				'![[[foo](uri1)](uri2)](uri3)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'![[[foo](uri1)](uri2)](uri3)<br>',
			],
			800 => [
				'![[[foo](uri1)](uri2)](uri3)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'![[[foo](uri1)](uri2)](uri3)',
			],
			801 => [
				'*[foo*](/uri)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>*<a href="/uri" class="bbc_link" target="_blank" rel="noopener">foo*</a></p>' . "\n",
			],
			802 => [
				'*[foo*](/uri)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*[url="/uri"]foo*[/url]',
			],
			803 => [
				'*[foo*](/uri)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*[foo*](/uri)<br>',
			],
			804 => [
				'*[foo*](/uri)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*[foo*](/uri)',
			],
			805 => [
				'[foo`](/uri)`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>[foo<code class="bbc_tt">](/uri)</code></p>' . "\n",
			],
			806 => [
				'[foo`](/uri)`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo[tt]](/uri)[/tt]',
			],
			807 => [
				'[foo`](/uri)`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo`](/uri)`<br>',
			],
			808 => [
				'[foo`](/uri)`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo`](/uri)`',
			],
			809 => [
				'[foo&lt;http://example.com/?search=](uri)&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="uri" class="bbc_link" target="_blank" rel="noopener">foo&lt;http://example.com/?search=</a>&gt;</p>' . "\n",
			],
			810 => [
				'[foo&lt;http://example.com/?search=](uri)&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="uri"]foo&lt;http://example.com/?search=[/url]&gt;',
			],
			811 => [
				'[foo&lt;http://example.com/?search=](uri)&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo&lt;http://example.com/?search=](uri)&gt;<br>',
			],
			812 => [
				'[foo&lt;http://example.com/?search=](uri)&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo&lt;http://example.com/?search=](uri)&gt;',
			],
			813 => [
				'[foo]' . "\n" . '[bar]' . "\n\n" . '[foo]: /url1' . "\n" . '[bar]: /url2' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><a href="/url1" class="bbc_link" target="_blank" rel="noopener">foo</a>' . "\n" . '<a href="/url2" class="bbc_link" target="_blank" rel="noopener">bar</a></p>' . "\n",
			],
			814 => [
				'[foo]' . "\n" . '[bar]' . "\n\n" . '[foo]: /url1' . "\n" . '[bar]: /url2' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><a href="/url1" class="bbc_link" target="_blank" rel="noopener">foo</a><br>' . "\n" . '<a href="/url2" class="bbc_link" target="_blank" rel="noopener">bar</a></p>' . "\n",
			],
			815 => [
				'[foo]' . "\n" . '[bar]' . "\n\n" . '[foo]: /url1' . "\n" . '[bar]: /url2' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[url="/url1"]foo[/url] [url="/url2"]bar[/url]',
			],
			816 => [
				'[foo]' . "\n" . '[bar]' . "\n\n" . '[foo]: /url1' . "\n" . '[bar]: /url2' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[url="/url1"]foo[/url]' . "\n" . '[url="/url2"]bar[/url]',
			],
			817 => [
				'[foo]' . "\n" . '[bar]' . "\n\n" . '[foo]: /url1' . "\n" . '[bar]: /url2' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo]<br>[bar]<br><br>[foo]: /url1<br>[bar]: /url2<br>',
			],
			818 => [
				'[foo]' . "\n" . '[bar]' . "\n\n" . '[foo]: /url1' . "\n" . '[bar]: /url2' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[foo]' . "\n" . '[bar]' . "\n\n" . '[foo]: /url1' . "\n" . '[bar]: /url2',
			],
			819 => [
				'![foo](train.jpg)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><img src="//train.jpg" alt="" class="bbc_img" loading="lazy"></p>' . "\n",
			],
			820 => [
				'![foo](train.jpg)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[img]train.jpg[/img]',
			],
			821 => [
				'![foo](train.jpg)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'![foo](train.jpg)<br>',
			],
			822 => [
				'![foo](train.jpg)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'![foo](train.jpg)',
			],
			823 => [
				'![foo](&lt;url&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><img src="//&lt;url&gt;" alt="" class="bbc_img" loading="lazy"></p>' . "\n",
			],
			824 => [
				'![foo](&lt;url&gt;)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[img]&lt;url&gt;[/img]',
			],
			825 => [
				'![foo](&lt;url&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'![foo](&lt;url&gt;)<br>',
			],
			826 => [
				'![foo](&lt;url&gt;)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'![foo](&lt;url&gt;)',
			],
			827 => [
				'![](/url)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><img src="//url" alt="" class="bbc_img" loading="lazy"></p>' . "\n",
			],
			828 => [
				'![](/url)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[img]/url[/img]',
			],
			829 => [
				'![](/url)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'![](/url)<br>',
			],
			830 => [
				'![](/url)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'![](/url)',
			],
			831 => [
				'&lt;http://foo.bar.baz&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;http://foo.bar.baz&gt;</p>' . "\n",
			],
			832 => [
				'&lt;http://foo.bar.baz&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://foo.bar.baz&gt;',
			],
			833 => [
				'&lt;http://foo.bar.baz&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://foo.bar.baz&gt;<br>',
			],
			834 => [
				'&lt;http://foo.bar.baz/test?q=hello&amp;id=22&amp;boolean&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;http://foo.bar.baz/test?q=hello&amp;id=22&amp;boolean&gt;</p>' . "\n",
			],
			835 => [
				'&lt;http://foo.bar.baz/test?q=hello&amp;id=22&amp;boolean&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://foo.bar.baz/test?q=hello&amp;id=22&amp;boolean&gt;',
			],
			836 => [
				'&lt;http://foo.bar.baz/test?q=hello&amp;id=22&amp;boolean&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://foo.bar.baz/test?q=hello&amp;id=22&amp;boolean&gt;<br>',
			],
			837 => [
				'&lt;irc://foo.bar:2233/baz&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;irc://foo.bar:2233/baz&gt;</p>' . "\n",
			],
			838 => [
				'&lt;irc://foo.bar:2233/baz&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;irc://foo.bar:2233/baz&gt;',
			],
			839 => [
				'&lt;irc://foo.bar:2233/baz&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;irc://foo.bar:2233/baz&gt;<br>',
			],
			840 => [
				'&lt;MAILTO:FOO@BAR.BAZ&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;MAILTO:FOO@BAR.BAZ&gt;</p>' . "\n",
			],
			841 => [
				'&lt;MAILTO:FOO@BAR.BAZ&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;MAILTO:FOO@BAR.BAZ&gt;',
			],
			842 => [
				'&lt;MAILTO:FOO@BAR.BAZ&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;MAILTO:FOO@BAR.BAZ&gt;<br>',
			],
			843 => [
				'&lt;a+b+c:d&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;a+b+c:d&gt;</p>' . "\n",
			],
			844 => [
				'&lt;a+b+c:d&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;a+b+c:d&gt;',
			],
			845 => [
				'&lt;a+b+c:d&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;a+b+c:d&gt;<br>',
			],
			846 => [
				'&lt;made-up-scheme://foo,bar&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;made-up-scheme://foo,bar&gt;</p>' . "\n",
			],
			847 => [
				'&lt;made-up-scheme://foo,bar&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;made-up-scheme://foo,bar&gt;',
			],
			848 => [
				'&lt;made-up-scheme://foo,bar&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;made-up-scheme://foo,bar&gt;<br>',
			],
			849 => [
				'&lt;http://../&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;http://../&gt;</p>' . "\n",
			],
			850 => [
				'&lt;http://../&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://../&gt;',
			],
			851 => [
				'&lt;http://../&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://../&gt;<br>',
			],
			852 => [
				'&lt;localhost:5001/foo&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;localhost:5001/foo&gt;</p>' . "\n",
			],
			853 => [
				'&lt;localhost:5001/foo&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;localhost:5001/foo&gt;',
			],
			854 => [
				'&lt;localhost:5001/foo&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;localhost:5001/foo&gt;<br>',
			],
			855 => [
				'&lt;http://example.com/\\[\\&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;http://example.com/[&gt;</p>' . "\n",
			],
			856 => [
				'&lt;http://example.com/\\[\\&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://example.com/[&gt;',
			],
			857 => [
				'&lt;http://example.com/\\[\\&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://example.com/\\[\\&gt;<br>',
			],
			858 => [
				'&lt;http://example.com/\\[\\&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;http://example.com/\\[\\&gt;',
			],
			859 => [
				'&lt;foo@bar.example.com&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;foo@bar.example.com&gt;</p>' . "\n",
			],
			860 => [
				'&lt;foo@bar.example.com&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo@bar.example.com&gt;',
			],
			861 => [
				'&lt;foo@bar.example.com&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo@bar.example.com&gt;<br>',
			],
			862 => [
				'&lt;foo+special@Bar.baz-bar0.com&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;foo+special@Bar.baz-bar0.com&gt;</p>' . "\n",
			],
			863 => [
				'&lt;foo+special@Bar.baz-bar0.com&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo+special@Bar.baz-bar0.com&gt;',
			],
			864 => [
				'&lt;foo+special@Bar.baz-bar0.com&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo+special@Bar.baz-bar0.com&gt;<br>',
			],
			865 => [
				'&lt;foo\\+@bar.example.com&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;foo+@bar.example.com&gt;</p>' . "\n",
			],
			866 => [
				'&lt;foo\\+@bar.example.com&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo+@bar.example.com&gt;',
			],
			867 => [
				'&lt;foo\\+@bar.example.com&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo\\+@bar.example.com&gt;<br>',
			],
			868 => [
				'&lt;foo\\+@bar.example.com&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo\\+@bar.example.com&gt;',
			],
			869 => [
				'&lt;&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;&gt;</p>' . "\n",
			],
			870 => [
				'&lt;&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;&gt;',
			],
			871 => [
				'&lt;&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;&gt;<br>',
			],
			872 => [
				'&lt;m:abc&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;m:abc&gt;</p>' . "\n",
			],
			873 => [
				'&lt;m:abc&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;m:abc&gt;',
			],
			874 => [
				'&lt;m:abc&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;m:abc&gt;<br>',
			],
			875 => [
				'&lt;foo.bar.baz&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;foo.bar.baz&gt;</p>' . "\n",
			],
			876 => [
				'&lt;foo.bar.baz&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo.bar.baz&gt;',
			],
			877 => [
				'&lt;foo.bar.baz&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;foo.bar.baz&gt;<br>',
			],
			878 => [
				'http://example.com' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>http://example.com</p>' . "\n",
			],
			879 => [
				'http://example.com' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'http://example.com',
			],
			880 => [
				'http://example.com' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'http://example.com<br>',
			],
			881 => [
				'foo@bar.example.com' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo@bar.example.com</p>' . "\n",
			],
			882 => [
				'foo@bar.example.com' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo@bar.example.com',
			],
			883 => [
				'foo@bar.example.com' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo@bar.example.com<br>',
			],
			884 => [
				'www.commonmark.org' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>www.commonmark.org</p>' . "\n",
			],
			885 => [
				'www.commonmark.org' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.commonmark.org',
			],
			886 => [
				'www.commonmark.org' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.commonmark.org<br>',
			],
			887 => [
				'www.google.com/search?q=Markup+(business)' . "\n\n" . 'www.google.com/search?q=Markup+(business)))' . "\n\n" . '(www.google.com/search?q=Markup+(business))' . "\n\n" . '(www.google.com/search?q=Markup+(business)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>www.google.com/search?q=Markup+(business)</p>' . "\n" . '<p>www.google.com/search?q=Markup+(business)))</p>' . "\n" . '<p>(www.google.com/search?q=Markup+(business))</p>' . "\n" . '<p>(www.google.com/search?q=Markup+(business)</p>' . "\n",
			],
			888 => [
				'www.google.com/search?q=Markup+(business)' . "\n\n" . 'www.google.com/search?q=Markup+(business)))' . "\n\n" . '(www.google.com/search?q=Markup+(business))' . "\n\n" . '(www.google.com/search?q=Markup+(business)' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.google.com/search?q=Markup+(business)' . "\n\n" . 'www.google.com/search?q=Markup+(business)))' . "\n\n" . '(www.google.com/search?q=Markup+(business))' . "\n\n" . '(www.google.com/search?q=Markup+(business)',
			],
			889 => [
				'www.google.com/search?q=Markup+(business)' . "\n\n" . 'www.google.com/search?q=Markup+(business)))' . "\n\n" . '(www.google.com/search?q=Markup+(business))' . "\n\n" . '(www.google.com/search?q=Markup+(business)' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.google.com/search?q=Markup+(business)<br><br>www.google.com/search?q=Markup+(business)))<br><br>(www.google.com/search?q=Markup+(business))<br><br>(www.google.com/search?q=Markup+(business)<br>',
			],
			890 => [
				'www.google.com/search?q=(business))+ok' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>www.google.com/search?q=(business))+ok</p>' . "\n",
			],
			891 => [
				'www.google.com/search?q=(business))+ok' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.google.com/search?q=(business))+ok',
			],
			892 => [
				'www.google.com/search?q=(business))+ok' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.google.com/search?q=(business))+ok<br>',
			],
			893 => [
				'www.google.com/search?q=commonmark&amp;hl=en' . "\n\n" . 'www.google.com/search?q=commonmark&amp;hl;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>www.google.com/search?q=commonmark&amp;hl=en</p>' . "\n" . '<p>www.google.com/search?q=commonmark&amp;hl;</p>' . "\n",
			],
			894 => [
				'www.google.com/search?q=commonmark&amp;hl=en' . "\n\n" . 'www.google.com/search?q=commonmark&amp;hl;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.google.com/search?q=commonmark&amp;hl=en' . "\n\n" . 'www.google.com/search?q=commonmark&amp;hl;',
			],
			895 => [
				'www.google.com/search?q=commonmark&amp;hl=en' . "\n\n" . 'www.google.com/search?q=commonmark&amp;hl;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.google.com/search?q=commonmark&amp;hl=en<br><br>www.google.com/search?q=commonmark&amp;hl;<br>',
			],
			896 => [
				'www.commonmark.org/he&lt;lp' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>www.commonmark.org/he&lt;lp</p>' . "\n",
			],
			897 => [
				'www.commonmark.org/he&lt;lp' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.commonmark.org/he&lt;lp',
			],
			898 => [
				'www.commonmark.org/he&lt;lp' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'www.commonmark.org/he&lt;lp<br>',
			],
			899 => [
				'foo@bar.baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo@bar.baz</p>' . "\n",
			],
			900 => [
				'foo@bar.baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo@bar.baz',
			],
			901 => [
				'foo@bar.baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo@bar.baz<br>',
			],
			902 => [
				'a.b-c_d@a.b' . "\n\n" . 'a.b-c_d@a.b.' . "\n\n" . 'a.b-c_d@a.b-' . "\n\n" . 'a.b-c_d@a.b_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>a.b-c_d@a.b</p>' . "\n" . '<p>a.b-c_d@a.b.</p>' . "\n" . '<p>a.b-c_d@a.b-</p>' . "\n" . '<p>a.b-c_d@a.b_</p>' . "\n",
			],
			903 => [
				'a.b-c_d@a.b' . "\n\n" . 'a.b-c_d@a.b.' . "\n\n" . 'a.b-c_d@a.b-' . "\n\n" . 'a.b-c_d@a.b_' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a.b-c_d@a.b' . "\n\n" . 'a.b-c_d@a.b.' . "\n\n" . 'a.b-c_d@a.b-' . "\n\n" . 'a.b-c_d@a.b_',
			],
			904 => [
				'a.b-c_d@a.b' . "\n\n" . 'a.b-c_d@a.b.' . "\n\n" . 'a.b-c_d@a.b-' . "\n\n" . 'a.b-c_d@a.b_' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'a.b-c_d@a.b<br><br>a.b-c_d@a.b.<br><br>a.b-c_d@a.b-<br><br>a.b-c_d@a.b_<br>',
			],
			905 => [
				'mailto:foo@bar.baz' . "\n\n" . 'mailto:a.b-c_d@a.b' . "\n\n" . 'mailto:a.b-c_d@a.b.' . "\n\n" . 'mailto:a.b-c_d@a.b/' . "\n\n" . 'mailto:a.b-c_d@a.b-' . "\n\n" . 'mailto:a.b-c_d@a.b_' . "\n\n" . 'xmpp:foo@bar.baz' . "\n\n" . 'xmpp:foo@bar.baz.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>mailto:foo@bar.baz</p>' . "\n" . '<p>mailto:a.b-c_d@a.b</p>' . "\n" . '<p>mailto:a.b-c_d@a.b.</p>' . "\n" . '<p>mailto:a.b-c_d@a.b/</p>' . "\n" . '<p>mailto:a.b-c_d@a.b-</p>' . "\n" . '<p>mailto:a.b-c_d@a.b_</p>' . "\n" . '<p>xmpp:foo@bar.baz</p>' . "\n" . '<p>xmpp:foo@bar.baz.</p>' . "\n",
			],
			906 => [
				'mailto:foo@bar.baz' . "\n\n" . 'mailto:a.b-c_d@a.b' . "\n\n" . 'mailto:a.b-c_d@a.b.' . "\n\n" . 'mailto:a.b-c_d@a.b/' . "\n\n" . 'mailto:a.b-c_d@a.b-' . "\n\n" . 'mailto:a.b-c_d@a.b_' . "\n\n" . 'xmpp:foo@bar.baz' . "\n\n" . 'xmpp:foo@bar.baz.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'mailto:foo@bar.baz' . "\n\n" . 'mailto:a.b-c_d@a.b' . "\n\n" . 'mailto:a.b-c_d@a.b.' . "\n\n" . 'mailto:a.b-c_d@a.b/' . "\n\n" . 'mailto:a.b-c_d@a.b-' . "\n\n" . 'mailto:a.b-c_d@a.b_' . "\n\n" . 'xmpp:foo@bar.baz' . "\n\n" . 'xmpp:foo@bar.baz.',
			],
			907 => [
				'mailto:foo@bar.baz' . "\n\n" . 'mailto:a.b-c_d@a.b' . "\n\n" . 'mailto:a.b-c_d@a.b.' . "\n\n" . 'mailto:a.b-c_d@a.b/' . "\n\n" . 'mailto:a.b-c_d@a.b-' . "\n\n" . 'mailto:a.b-c_d@a.b_' . "\n\n" . 'xmpp:foo@bar.baz' . "\n\n" . 'xmpp:foo@bar.baz.' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'mailto:foo@bar.baz<br><br>mailto:a.b-c_d@a.b<br><br>mailto:a.b-c_d@a.b.<br><br>mailto:a.b-c_d@a.b/<br><br>mailto:a.b-c_d@a.b-<br><br>mailto:a.b-c_d@a.b_<br><br>xmpp:foo@bar.baz<br><br>xmpp:foo@bar.baz.<br>',
			],
			908 => [
				'xmpp:foo@bar.baz/txt' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin.com' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>xmpp:foo@bar.baz/txt</p>' . "\n" . '<p>xmpp:foo@bar.baz/txt@bin</p>' . "\n" . '<p>xmpp:foo@bar.baz/txt@bin.com</p>' . "\n",
			],
			909 => [
				'xmpp:foo@bar.baz/txt' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin.com' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'xmpp:foo@bar.baz/txt' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin.com',
			],
			910 => [
				'xmpp:foo@bar.baz/txt' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin.com' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'xmpp:foo@bar.baz/txt<br><br>xmpp:foo@bar.baz/txt@bin<br><br>xmpp:foo@bar.baz/txt@bin.com<br>',
			],
			911 => [
				'xmpp:foo@bar.baz/txt/bin' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>xmpp:foo@bar.baz/txt/bin</p>' . "\n",
			],
			912 => [
				'xmpp:foo@bar.baz/txt/bin' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'xmpp:foo@bar.baz/txt/bin',
			],
			913 => [
				'xmpp:foo@bar.baz/txt/bin' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'xmpp:foo@bar.baz/txt/bin<br>',
			],
			914 => [
				'&lt;a&gt;&lt;bab&gt;&lt;c2c&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;a&gt;&lt;bab&gt;&lt;c2c&gt;</p>' . "\n",
			],
			915 => [
				'&lt;a&gt;&lt;bab&gt;&lt;c2c&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;a&gt;&lt;bab&gt;&lt;c2c&gt;',
			],
			916 => [
				'&lt;a&gt;&lt;bab&gt;&lt;c2c&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;a&gt;&lt;bab&gt;&lt;c2c&gt;<br>',
			],
			917 => [
				'&lt;a/&gt;&lt;b2/&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>&lt;a/&gt;&lt;b2/&gt;</p>' . "\n",
			],
			918 => [
				'&lt;a/&gt;&lt;b2/&gt;' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;a/&gt;&lt;b2/&gt;',
			],
			919 => [
				'&lt;a/&gt;&lt;b2/&gt;' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&lt;a/&gt;&lt;b2/&gt;<br>',
			],
			920 => [
				'foo\\' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo<br>baz</p>' . "\n",
			],
			921 => [
				'foo\\' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo' . "\n" . 'baz',
			],
			922 => [
				'foo\\' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo\\<br>baz<br>',
			],
			923 => [
				'foo\\' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo\\' . "\n" . 'baz',
			],
			924 => [
				'*foo\\' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><em>foo<br>bar</em></p>' . "\n",
			],
			925 => [
				'*foo\\' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[i]foo' . "\n" . 'bar[/i]',
			],
			926 => [
				'*foo\\' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo\\<br>bar*<br>',
			],
			927 => [
				'*foo\\' . "\n" . 'bar*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'*foo\\' . "\n" . 'bar*',
			],
			928 => [
				'`code\\' . "\n" . 'span`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">code\\' . "\n" . 'span</code></p>' . "\n",
			],
			929 => [
				'`code\\' . "\n" . 'span`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p><code class="bbc_tt">code\\<br>' . "\n" . 'span</code></p>' . "\n",
			],
			930 => [
				'`code\\' . "\n" . 'span`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[tt]code\\ span[/tt]',
			],
			931 => [
				'`code\\' . "\n" . 'span`' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[tt]code\\' . "\n" . 'span[/tt]',
			],
			932 => [
				'`code\\' . "\n" . 'span`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`code\\<br>span`<br>',
			],
			933 => [
				'`code\\' . "\n" . 'span`' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'`code\\' . "\n" . 'span`',
			],
			934 => [
				'foo\\' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo</p>' . "\n",
			],
			935 => [
				'foo\\' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo',
			],
			936 => [
				'foo\\' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo\\<br>',
			],
			937 => [
				'foo\\' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo\\',
			],
			938 => [
				'foo' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>foo' . "\n" . 'baz</p>' . "\n",
			],
			939 => [
				'foo' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<p>foo<br>' . "\n" . 'baz</p>' . "\n",
			],
			940 => [
				'foo' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo baz',
			],
			941 => [
				'foo' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'foo' . "\n" . 'baz',
			],
			942 => [
				'foo' . "\n" . 'baz' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'foo<br>baz<br>',
			],
			943 => [
				'&gt; Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '&gt; - Qui *quodsi iracundia*' . "\n" . '&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<p>Lorem ipsum dolor' . "\n" . 'sit amet.</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>Qui <em>quodsi iracundia</em></li>' . "\n" . '<li>aliquando id</li>' . "\n" . '</ul>' . "\n" . '</blockquote>' . "\n",
			],
			944 => [
				'&gt; Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '&gt; - Qui *quodsi iracundia*' . "\n" . '&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<p>Lorem ipsum dolor<br>' . "\n" . 'sit amet.</p>' . "\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>Qui <em>quodsi iracundia</em></li>' . "\n" . '<li>aliquando id</li>' . "\n" . '</ul>' . "\n" . '</blockquote>' . "\n",
			],
			945 => [
				'&gt; Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '&gt; - Qui *quodsi iracundia*' . "\n" . '&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . 'Lorem ipsum dolor sit amet.' . "\n" . '[list]' . "\n" . '[li]Qui [i]quodsi iracundia[/i][/li]' . "\n" . '[li]aliquando id[/li]' . "\n" . '[/list]' . "\n" . '[/quote]',
			],
			946 => [
				'&gt; Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '&gt; - Qui *quodsi iracundia*' . "\n" . '&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => MarkdownParser::BR_IN_PARAGRAPHS,
					'for_print' => false,
				],
				'[quote]' . "\n" . 'Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '[list]' . "\n" . '[li]Qui [i]quodsi iracundia[/i][/li]' . "\n" . '[li]aliquando id[/li]' . "\n" . '[/list]' . "\n" . '[/quote]',
			],
			947 => [
				'&gt; Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '&gt; - Qui *quodsi iracundia*' . "\n" . '&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; Lorem ipsum dolor<br>sit amet.<br>&gt; - Qui *quodsi iracundia*<br>&gt; - aliquando id<br>',
			],
			948 => [
				'&gt; Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '&gt; - Qui *quodsi iracundia*' . "\n" . '&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '&gt; - Qui *quodsi iracundia*' . "\n" . '&gt; - aliquando id',
			],
			949 => [
				'&gt; Lorem ipsum dolor' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n" . 'Lorem ipsum dolor</blockquote>' . "\n",
			],
			950 => [
				'&gt; Lorem ipsum dolor' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . 'Lorem ipsum dolor[/quote]',
			],
			951 => [
				'&gt; Lorem ipsum dolor' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; Lorem ipsum dolor<br>',
			],
			952 => [
				'&gt; Lorem ipsum dolor' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; Lorem ipsum dolor',
			],
			953 => [
				'sit amet.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>sit amet.</p>' . "\n",
			],
			954 => [
				'sit amet.' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'sit amet.',
			],
			955 => [
				'sit amet.' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'sit amet.<br>',
			],
			956 => [
				'&gt; - Qui *quodsi iracundia*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>Qui <em>quodsi iracundia</em></li>' . "\n" . '</ul>' . "\n" . '</blockquote>' . "\n",
			],
			957 => [
				'&gt; - Qui *quodsi iracundia*' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . '[list]' . "\n" . '[li]Qui [i]quodsi iracundia[/i][/li]' . "\n" . '[/list]' . "\n" . '[/quote]',
			],
			958 => [
				'&gt; - Qui *quodsi iracundia*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; - Qui *quodsi iracundia*<br>',
			],
			959 => [
				'&gt; - Qui *quodsi iracundia*' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; - Qui *quodsi iracundia*',
			],
			960 => [
				'&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<blockquote class="bbc_standard_quote"><cite>Quote</cite>' . "\n\n" . '<ul class="bbc_list" style="list-style-type: disc;">' . "\n" . '<li>aliquando id</li>' . "\n" . '</ul>' . "\n" . '</blockquote>' . "\n",
			],
			961 => [
				'&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'[quote]' . "\n" . '[list]' . "\n" . '[li]aliquando id[/li]' . "\n" . '[/list]' . "\n" . '[/quote]',
			],
			962 => [
				'&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; - aliquando id<br>',
			],
			963 => [
				'&gt; - aliquando id' . "\n",
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'&gt; - aliquando id',
			],
		];
	}

	/**
	 * @return array
	 */
	public static function complexStringProvider(): array
	{
		return [
			0 => [
				'Here is some [tt]inline **code**[/tt].',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Here is some <code class="bbc_tt">inline **code**</code>.</p>' . "\n",
			],
			1 => [
				'Here is some [tt]inline **code**[/tt].',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some [tt]inline &#91;b&#93;code&#91;/b&#93;[/tt].',
			],
			2 => [
				'Here is some [tt]inline **code**[/tt].',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Here is some [tt]inline <strong>code</strong>[/tt].</p>' . "\n",
			],
			3 => [
				'Here is some [tt]inline **code**[/tt].',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some <code class="bbc_tt">inline **code**</code>.',
			],
			4 => [
				'Here is some `inline [b]code[/b]`.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Here is some <code class="bbc_tt">inline [b]code[/b]</code>.</p>' . "\n",
			],
			5 => [
				'Here is some `inline [b]code[/b]`.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some [tt]inline &#91;b&#93;code&#91;/b&#93;[/tt].',
			],
			6 => [
				'Here is some `inline [b]code[/b]`.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some `inline <strong>code</strong>`.',
			],
			7 => [
				'Here is some `inline [b]code`.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Here is some <code class="bbc_tt">inline [b]code</code>.</p>' . "\n",
			],
			8 => [
				'Here is some `inline [b]code`.',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some [tt]inline &#91;b&#93;code[/tt].',
			],
			9 => [
				'Here is some `inline [b]code`.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some `inline <strong>code`.</strong>',
			],
			10 => [
				'Here is some `inline [b]code`.',
				Parser::INPUT_BBC,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some `inline [b]code`.[/b]',
			],
			11 => [
				'Here is some [tt]inline [b]code[/b][/tt].',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Here is some <code class="bbc_tt">inline [b]code[/b]</code>.</p>' . "\n",
			],
			12 => [
				'Here is some [tt]inline [b]code[/b][/tt].',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some [tt]inline &#91;b&#93;code&#91;/b&#93;[/tt].',
			],
			13 => [
				'Here is some [tt]inline [b]code[/b][/tt].',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Here is some [tt]inline [b]code[/b][/tt].</p>' . "\n",
			],
			14 => [
				'Here is some [tt]inline [b]code[/b][/tt].',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some <code class="bbc_tt">inline [b]code[/b]</code>.',
			],
			15 => [
				'Here is some [tt]inline [b]code[/tt].',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Here is some <code class="bbc_tt">inline [b]code</code>.</p>' . "\n",
			],
			16 => [
				'Here is some [tt]inline [b]code[/tt].',
				Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS,
				Parser::OUTPUT_BBC,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some [tt]inline &#91;b&#93;code[/tt].',
			],
			17 => [
				'Here is some [tt]inline [b]code[/tt].',
				Parser::INPUT_MARKDOWN,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'<p>Here is some [tt]inline [b]code[/tt].</p>' . "\n",
			],
			18 => [
				'Here is some [tt]inline [b]code[/tt].',
				Parser::INPUT_BBC,
				Parser::OUTPUT_HTML,
				[
					'hard_breaks' => 0,
					'for_print' => false,
				],
				'Here is some <code class="bbc_tt">inline [b]code</code>.',
			],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 *
	 */
	protected function setUp(): void
	{
		// Backup.
		$this->modSettings = Config::$modSettings;
		$this->my_id = isset(User::$me) ? User::$me->id : 0;

		// Ensure everything is enabled.
		Config::$modSettings['enableBBC'] = 1;
		Config::$modSettings['enablePostHTML'] = 1;
		Config::$modSettings['enableMarkdown'] = 1;
		Config::$modSettings['smiley_enable'] = 1;

		// Smiley settings.
		Config::$modSettings['smileys_url'] = Config::$boardurl . '/Smileys';
		Config::$modSettings['smiley_sets_default'] = 'fugue';

		// Populate the smiley data manually in order to avoid calling the database.
		$smiley_parser = new \ReflectionClass(SmileyParser::class);
		$smiley_parser_data = $smiley_parser->getProperty('data');
		$smiley_parser_data->setValue(null, $this->getSmileyData());

		// Ensure we are using UTC for the time zone.
		Config::$modSettings['default_timezone'] = 'UTC';

		// Populate the TLD regex with a default value.
		Config::$modSettings['tld_regex'] ??= Utils::buildRegex(array_merge(Url::$basic_tlds, Url::$cc_tlds, Url::$special_use_tlds));

		// Need a user.
		User::load(0, dataset: UserDataset::None);
		User::setMe(0);
	}

	/**
	 *
	 */
	protected function tearDown(): void
	{
		Config::$modSettings = $this->modSettings;
		User::setMe($this->my_id);
	}

	/**
	 * Gets default data for SmileyParser::$data.
	 *
	 * @return array Data for SmileyParser::$data.
	 */
	protected function getSmileyData(): array
	{
		foreach (
			(new \ReflectionClass(\SMF\Db\Schema\v3_0\Smileys::class))->getDefaultProperties()['initial_data'] as $row
		) {
			$data[''][(int) $row['id_smiley']] = [
				'code' => $row['code'],
				'filename' => '',
				'description' => Lang::getTxt(substr($row['description'], 2, -1), file: 'Maintenance'),
			];
		}

		foreach (
			(new \ReflectionClass(\SMF\Db\Schema\v3_0\SmileyFiles::class))->getDefaultProperties()['initial_data'] as $row
		) {
			if ($row['smiley_set'] === Config::$modSettings['smiley_sets_default']) {
				$data[''][(int) $row['id_smiley']]['filename'] = $row['filename'];
			}
		}

		$data[Config::$modSettings['smiley_sets_default']] = $data[''];

		return $data;
	}
}
