<?php

declare(strict_types=1);

namespace SMF\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Tests\Support\DomSnapshot;

/**
 * The rules the template snapshots are compared by.
 *
 * Each pair below is two renders that must read the same, or must not. The
 * second half matters as much as the first: a snapshot that masks too much
 * passes whatever the templates do, and would say nothing about a conversion.
 */
#[CoversNothing]
class DomSnapshotTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	#[DataProvider('sameRender')]
	public function testTheseRendersReadTheSame(string $before, string $after): void
	{
		$this->assertSame($this->describe($before), $this->describe($after));
	}

	#[DataProvider('differentRender')]
	public function testTheseRendersDoNot(string $before, string $after): void
	{
		$this->assertNotSame($this->describe($before), $this->describe($after));
	}

	public function testItSaysWhatItLeftOut(): void
	{
		$description = (new DomSnapshot())->describe('<main><p id="volatile">Now</p><p>Kept</p></main>', ['main'], ['#volatile']);

		$this->assertStringContainsString('snapshot-ignored [selector="#volatile"]', $description);
		$this->assertStringNotContainsString('Now', $description);
		$this->assertStringContainsString('"Kept"', $description);
	}

	public function testARootNamingAnIdReadsTheSameOnAnyForum(): void
	{
		$describe = static fn(int $id): string => (new DomSnapshot())->describe(
			'<main><div id="category_' . $id . '">Boards</div></main>',
			['#category_' . $id],
		);

		$this->assertSame($describe(2), $describe(3));
	}

	public function testItSaysWhenARootIsMissing(): void
	{
		$this->assertStringContainsString(
			'(nothing matches #gone)',
			(new DomSnapshot())->describe('<main></main>', ['#gone']),
		);
	}

	public function testMaskedTextKeepsItsElement(): void
	{
		$description = (new DomSnapshot())->describe('<main><li class="postgroup">Hero Member</li></main>', ['main'], [], ['.postgroup']);

		$this->assertStringContainsString('li.postgroup', $description);
		$this->assertStringContainsString('"{text}"', $description);
		$this->assertStringNotContainsString('Hero', $description);
	}

	public function testAListCanBeNarrowedToWhatMentionsTheFixtures(): void
	{
		$picker = static fn(string $others): string => '<main><select name="toboard">'
			. '<optgroup label="Snapshot fixtures"><option>Snapshot board</option></optgroup>'
			. $others
			. '</select></main>';

		$describe = static fn(string $html): string => (new DomSnapshot())->describe($html, ['main'], [], [], ['optgroup' => 'Snapshot']);

		$one = $describe($picker('<optgroup label="General Category"><option>General Discussion</option></optgroup>'));
		$many = $describe($picker('<optgroup label="Other"><option>A</option></optgroup><optgroup label="More"><option>B</option></optgroup>'));

		$this->assertSame($one, $many);
		$this->assertStringContainsString('Snapshot fixtures', $one);
		$this->assertStringNotContainsString('General', $one);
	}

	public function testSomethingOnlyThereSometimesCanBeLeftOutWithoutATrace(): void
	{
		$describe = static fn(string $html): string => (new DomSnapshot())->describe($html, ['main'], [], [], ['.amt' => null]);

		$this->assertSame(
			$describe('<main><a>Messages</a></main>'),
			$describe('<main><a>Messages <span class="amt">3</span></a></main>'),
		);
	}

	public function testRowsInDatabaseOrderCanBeComparedSorted(): void
	{
		$describe = static fn(string $rows): string => (new DomSnapshot())->describe(
			'<main><table><tbody>' . $rows . '</tbody></table></main>',
			['main'],
			[],
			[],
			[],
			['tbody'],
		);

		$this->assertSame(
			$describe('<tr><td>Post new topics</td></tr><tr><td>Delete own posts</td></tr>'),
			$describe('<tr><td>Delete own posts</td></tr><tr><td>Post new topics</td></tr>'),
		);
	}

	public function testTheBoardUrlIsNotMistakenForAName(): void
	{
		// A member called "admin" is common, and ?action=admin is on every
		// page an administrator sees.
		$description = (new DomSnapshot(['http://forum.test' => '{boardurl}']))->describe(
			'<main><a href="http://forum.test/index.php?action=profile;u=1">admin</a> '
			. '<a href="http://forum.test/index.php?action=admin">Admin</a></main>',
			['main'],
		);

		$this->assertStringContainsString('{boardurl}/index.php?action=admin"', $description);
		$this->assertStringContainsString('"Admin"', $description);
		$this->assertStringContainsString('"{member}"', $description);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Pairs that differ only in ways a template conversion is allowed to
	 * change, or that the forum changes on its own.
	 *
	 * @return array The cases.
	 */
	public static function sameRender(): array
	{
		return [
			'whitespace and indentation' => [
				'<main><div class="a"><p>Hello world</p></div></main>',
				"<main>\n\t<div class=\"a\">\n\t\t<p>\n\t\t\tHello   world\n\t\t</p>\n\t</div>\n</main>",
			],
			'comments' => [
				'<main><p>Hello</p><!-- .end --></main>',
				'<main><p>Hel<!-- split -->lo</p></main>',
			],
			'attribute and class order' => [
				'<main><a href="#x" title="t" class="one two">x</a></main>',
				'<main><a class="two one" title="t" href="#x">x</a></main>',
			],
			'entities' => [
				'<main><p>&quot;quoted&quot; &amp; &#58;)</p></main>',
				'<main><p>"quoted" &amp; :)</p></main>',
			],
			'the session' => [
				'<main><script>var smf_session_id = "0123456789abcdef0123456789abcdef"; var smf_session_var = "abcdef12";</script>'
					. '<a href="/index.php?action=logout;abcdef12=0123456789abcdef0123456789abcdef">x</a>'
					. '<input type="hidden" name="abcdef12" value="0123456789abcdef0123456789abcdef"></main>',
				'<main><script>var smf_session_id = "fedcba9876543210fedcba9876543210"; var smf_session_var = "c0ffee99";</script>'
					. '<a href="/index.php?action=logout;c0ffee99=fedcba9876543210fedcba9876543210">x</a>'
					. '<input type="hidden" name="c0ffee99" value="fedcba9876543210fedcba9876543210"></main>',
			],
			'a security token' => [
				'<main><input type="hidden" name="aa11bb22cc" value="0123456789abcdef0123456789abcdef"></main>',
				'<main><input type="hidden" name="dd33ee44" value="fedcba9876543210fedcba9876543210"></main>',
			],
			'ids and counts' => [
				'<main><a id="msg12" href="/index.php?topic=3.0">Posts: 7</a></main>',
				'<main><a id="msg5012" href="/index.php?topic=31.15">Posts: 1234</a></main>',
			],
			'a minified file' => [
				'<main><link rel="stylesheet" href="/Themes/default/css/minified_4f4174a0308454b7fa39683936f468b4.css?smf30_1"></main>',
				'<main><link rel="stylesheet" href="/Themes/default/css/minified_ab62f1f68a1d7a284da72fd8555dbf49.css?smf30_2"></main>',
			],
			'an email address' => [
				'<main><a href="mailto:someone@example.com">someone@example.com</a><input value="noreply@myserver.com"></main>',
				'<main><a href="mailto:other.person+tag@example.org">other.person+tag@example.org</a><input value="admin@example.com"></main>',
			],
			'a search carried to the next page' => [
				'<main><a href="/index.php?action=search;params=eJxLTClLzEtOTalRrzGoUapJKgKxjICs">Search</a></main>',
				'<main><a href="/index.php?action=search;params=eJxLTClLzEtOTalRrzGoUapJKgKxzICs">Search</a></main>',
			],
			'an average and a total' => [
				'<main><dd>3</dd><dd>999</dd></main>',
				'<main><dd>2.5</dd><dd>1,000</dd></main>',
			],
			'a date and a relative date' => [
				'<main><p>Last post: Jan 15, 2020, 10:00 AM</p></main>',
				'<main><p>Last post: <strong>Today</strong> at 04:43 PM</p></main>',
			],
			'a length of time' => [
				'<main><dd>5 minutes</dd></main>',
				'<main><dd>2 hours and 1 minute</dd></main>',
			],
			'singular and plural' => [
				'<main><p>1 reply, 1 view, 1 member</p></main>',
				'<main><p>2 replies, 30 views, 0 members</p></main>',
			],
			'names in links, and wherever else they appear' => [
				'<main><a href="/index.php?action=profile;u=1" title="View the profile of Alice">Alice</a> said hello</main>',
				'<main><a href="/index.php?action=profile;u=9" title="View the profile of Bob">Bob</a> said hello</main>',
			],
			'a shortened subject and its title' => [
				'<main><a href="/index.php?topic=1.msg1#new" title="A very long subject indeed">A very long sub...</a></main>',
				'<main><a href="/index.php?topic=2.msg9#new" title="Short">Short</a></main>',
			],
			'the latest post on the board index' => [
				'<main><a href="/index.php?topic=1.msg1#new" title="Welcome to SMF!">Welcome to SMF!</a></main>',
				'<main><a href="/index.php?topic=9.msg90;boardseen#new" title="Another subject entirely">Another subject e...</a></main>',
			],
			'rows of a list' => [
				'<main><ul><li><a href="/index.php?board=1.0">One</a></li></ul></main>',
				'<main><ul><li><a href="/index.php?board=1.0">One</a></li><li><a href="/index.php?board=2.0">Two</a></li>'
					. '<li><a href="/index.php?board=3.0">Three</a></li></ul></main>',
			],
			'pairs of a definition list' => [
				'<main><dl><dt><a href="/index.php?action=profile;u=1">Alice</a></dt><dd>5</dd></dl></main>',
				'<main><dl><dt><a href="/index.php?action=profile;u=1">Alice</a></dt><dd>5</dd>'
					. '<dt><a href="/index.php?action=profile;u=2">Bob</a></dt><dd>3</dd></dl></main>',
			],
			'items separated by commas' => [
				'<main><p><a href="/index.php?board=1.0">One</a>, <a href="/index.php?board=2.0">Two</a></p></main>',
				'<main><p><a href="/index.php?board=1.0">One</a>, <a href="/index.php?board=2.0">Two</a>, '
					. '<a href="/index.php?board=3.0">Three</a></p></main>',
			],
		];
	}

	/**
	 * Pairs that differ in a way a template conversion must not introduce.
	 *
	 * @return array The cases.
	 */
	public static function differentRender(): array
	{
		return [
			'a class dropped' => [
				'<main><div class="windowbg sticky">x</div></main>',
				'<main><div class="windowbg">x</div></main>',
			],
			'an attribute dropped' => [
				'<main><input type="checkbox" name="x" checked></main>',
				'<main><input type="checkbox" name="x"></main>',
			],
			'an element changed' => [
				'<main><h3 class="catbg">Title</h3></main>',
				'<main><h4 class="catbg">Title</h4></main>',
			],
			'a language string changed' => [
				'<main><p>Started by</p></main>',
				'<main><p>Started by:</p></main>',
			],
			'a language string missing' => [
				'<main><button>Save</button></main>',
				'<main><button></button></main>',
			],
			'escaping lost' => [
				'<main><p>&lt;b&gt;not bold&lt;/b&gt;</p></main>',
				'<main><p><b>not bold</b></p></main>',
			],
			'an element moved' => [
				'<main><div class="a"></div><div class="b"></div></main>',
				'<main><div class="b"></div><div class="a"></div></main>',
			],
			'a column lost from every row' => [
				'<main><table><tr><td class="x">1</td><td class="y">2</td></tr><tr><td class="x">3</td><td class="y">4</td></tr></table></main>',
				'<main><table><tr><td class="x">1</td></tr><tr><td class="x">3</td></tr></table></main>',
			],
			'a value lost from a definition list' => [
				// Both values read "{date}", but they belong to different terms,
				// so the second is not a repeat of the first.
				'<main><dl><dt>Registered</dt><dd>Jan 1, 2020</dd><dt>Last active</dt><dd>Jan 2, 2020</dd></dl></main>',
				'<main><dl><dt>Registered</dt><dd>Jan 1, 2020</dd><dt>Last active</dt></dl></main>',
			],
			'a repeated word in prose' => [
				'<main><p><u>a</u> and <s>b</s> and <i>c</i></p></main>',
				'<main><p><u>a</u> and <s>b</s> <i>c</i></p></main>',
			],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Describes a fragment the way the snapshot tests describe a page.
	 *
	 * @param string $html The fragment.
	 * @return string The description.
	 */
	private function describe(string $html): string
	{
		return (new DomSnapshot())->describe('<!DOCTYPE html><html><body>' . $html . '</body></html>', ['main']);
	}
}
