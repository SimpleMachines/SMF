<?php

/**
 * This file is intended to be executing on the command line in order to
 * generate tests for SMF\Tests\Unit\ParserTest.
 *
 * Whenever you need to add a test for a new string, just append it to the
 * relevant sub-array of the $strings property and then run this file from
 * the CLI. It will automatically update SMF\Tests\Unit\ParserTest for you.
 *
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

namespace SMF\Tests;

use SMF\BBCode\BBCode;
use SMF\Config;
use SMF\Db\Schema\v3_0\SmileyFiles;
use SMF\Db\Schema\v3_0\Smileys;
use SMF\Lang;
use SMF\Parser;
use SMF\Parsers\SmileyParser;
use SMF\Url;
use SMF\User;
use SMF\UserDataset;
use SMF\Utils;

require_once '../bootstrap.php';
(new ParserTestGenerator())->execute();

/**
 * Updates SMF\Tests\Unit\ParserTest with new tests.
 */
class ParserTestGenerator
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Strings to test.
	 *
	 * The sub-arrays in of this property correspond to the provider methods in
	 * SMF\Tests\Unit\ParserTest:
	 *
	 *  - basicBbcodeStringProvider: For testing BBCode and smiley parsing, without
	 *    any Markdown involved.
	 *
	 *  - basicMarkdownStringProvider: For testing Markdown parsing, without any
	 *    BBCode and/or smileys involved.
	 *
	 *  - complexStringProvider: For testing mixed BBCode and Markdown syntax.
	 */
	public array $strings = [
		/*********************************************************************
		 * Tests for BBCode and smiley parsing, without any Markdown involved.
		 *********************************************************************/
		'basicBbcodeStringProvider' => [
			// Basic smiley test.
			'Lorem ipsum :)',

			// BBCode.
			// No tests for the acronym BBCode because it is just an alias of the abbr BBCode.
			// No tests for the attach BBCode because it requires a database connection.
			// No tests for the ftp BBCode because it is just an alias of the url BBCode.
			// No tests for the html BBCode because the test user isn't an admin.
			'Lorem [abbr=ipsum]dolor[/abbr] sit amet.',
			'Lorem [anchor=ipsum]ipsum[/anchor] dolor sit amet.',
			'Lorem [b]ipsum dolor[/b] sit amet.',
			'[bdo=ltr]Lorem ipsum dolor sit amet.[/bdo]',
			'Lorem [black]ipsum dolor[/black] sit amet.',
			'Lorem [blue]ipsum dolor[/blue] sit amet.',
			'Lorem [br]ipsum dolor sit amet.',
			'[center]Lorem ipsum dolor sit amet.[/center]',
			'[code]Lorem ipsum dolor sit amet.[/code]',
			'[code=foobar]Lorem ipsum dolor sit amet.[/code]',
			'Lorem [color=red]ipsum dolor[/color] sit amet.',
			'[details]Lorem ipsum dolor sit amet.[/details]',
			'[details summary=unquoted summary]Lorem ipsum dolor sit amet.[/details]',
			'[details summary="quoted summary"]Lorem ipsum dolor sit amet.[/details]',
			'Lorem [email]ipsum@email.invalid[/email] dolor sit amet.',
			'Lorem [email=ipsum@email.invalid]dolor[/email] sit amet.',
			'Lorem [flash=1,2]http://example.com/ipsum.swf[/flash] dolor sit amet.',
			'[float=left]Lorem ipsum dolor sit amet.[/float]',
			'Lorem [font=Arial]ipsum dolor[/font] sit amet.',
			'Lorem [glow=#fff,123]ipsum dolor[/glow] sit amet.',
			'Lorem [green]ipsum dolor[/green] sit amet.',
			'[h1]Lorem ipsum dolor sit amet.[/h1]',
			'[h2]Lorem ipsum dolor sit amet.[/h2]',
			'[h3]Lorem ipsum dolor sit amet.[/h3]',
			'[h4]Lorem ipsum dolor sit amet.[/h4]',
			'[h5]Lorem ipsum dolor sit amet.[/h5]',
			'[h6]Lorem ipsum dolor sit amet.[/h6]',
			'Lorem ipsum [hr]dolor sit amet.',
			'Lorem ipsum' . "\n" . '[hr]' . "\n" . 'dolor sit amet.',
			'Lorem [i]ipsum dolor[/i] sit amet.',
			'Lorem ipsum [img]http://example.com/path/to/file.png[/img] dolor sit amet.',
			'Lorem [iurl]http://example.com/path/to/page.html[/iurl] dolor sit amet.',
			'Lorem ipsum [iurl=http://example.com/path/to/page.html]dolor[/iurl] sit amet.',
			'Lorem ipsum [iurl="http://example.com/path/to/page.html"]dolor[/iurl] sit amet.',
			'[justify]Lorem ipsum dolor sit amet.[/justify]',
			'[right]Lorem ipsum dolor sit amet.[/right]',
			'[list]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
			'[list type=disc]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
			'[list type=decimal]' . "\n" . '[li]Lorem ipsum dolor sit amet.[/li]' . "\n" . '[li]Lorem ipsum dolor sit amet.' . "\n\n" . 'Lorem ipsum dolor sit amet.[/li]' . "\n" . '[/list]',
			'[ltr]Lorem ipsum dolor sit amet.[/ltr]',
			'[me=foobar]Lorem ipsum dolor sit amet.[/me]',
			'Lorem [member=123]dolor[/member] sit amet.',
			'[move]Lorem ipsum dolor sit amet.[/move]',
			'Lorem [nobbc][b]ipsum[/b][/nobbc] dolor sit amet.',
			'Lorem [nolink]http://example.com/path/to/page.html[/nolink] sit amet.',
			'Lorem [php]ipsum[/php] dolor sit amet.',
			'Lorem [pre]ipsum dolor[/pre] sit amet.',
			'[quote]Lorem ipsum dolor sit amet.[/quote]',
			'[quote=&quot;[url]http://example.com[/url]&quot;]Lorem ipsum dolor sit amet.[/quote]',
			'[quote="[url]http://example.com[/url]"]Lorem ipsum dolor sit amet.[/quote]',
			'[quote="foobar"]Lorem ipsum dolor sit amet.[/quote]',
			'[quote=foobar]Lorem ipsum dolor sit amet.[/quote]',
			'[quote author=foobar]Lorem ipsum dolor sit amet.[/quote]',
			'[quote author=foobar link=msg=123 date=1793143433]Lorem ipsum dolor sit amet.[/quote]',
			'Lorem [red]ipsum dolor[/red] sit amet.',
			'[right]Lorem ipsum dolor sit amet.[/right]',
			'[rtl]Lorem ipsum dolor sit amet.[/rtl]',
			'Lorem [s]ipsum dolor[/s] sit amet.',
			'Lorem [shadow=#000,bottom]ipsum dolor[/shadow] sit amet.',
			'Lorem [size=large]dolor[/size] sit amet.',
			'Lorem [size=4]dolor[/size] sit amet.',
			'Lorem [spoiler]ipsum dolor[/spoiler] sit amet.',
			'[spoiler=foobar]Lorem ipsum dolor sit amet.[/spoiler]',
			'Lorem [sub]ipsum dolor[/sub] sit amet.',
			'Lorem [sup]ipsum dolor[/sup] sit amet.',
			'[table][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][tr][td]Lorem ipsum dolor sit amet.[/td][td]Lorem ipsum dolor sit amet.[/td][/tr][/table]',
			'Lorem [time=2026-10-27T23:23:53+00:00]dolor[/time] sit amet.',
			'Lorem ipsum [time]2026-10-27T23:23:53+00:00[/time] dolor sit amet.',
			'Lorem [tt]ipsum[/tt] dolor sit amet.',
			'Lorem [u]ipsum dolor[/u] sit amet.',
			'Lorem ipsum [url]http://example.com/path/to/page.html[/url] dolor sit amet.',
			'Lorem [url=http://example.com/path/to/page.html]ipsum[/url] dolor sit amet.',
			'Lorem [url="http://example.com/path/to/page.html"]ipsum[/url] dolor sit amet.',
			'Lorem [white]ipsum dolor[/white] sit amet.',
			'Lorem ipsum [youtube]76Gopgo-4PY[/youtube] dolor sit amet.',

			// Test that only the hour is changed when parsing implied 12 hour postmeridian times.
			'Implied 12 hour postmeridian time: [time]Nov 3, 3033, at 3:33[/time]',
			'[time=Nov 3, 3033, at 3:33]Implied 12 hour postmeridian time[/time]',
		],

		/*************************************************************************
		 * Tests for Markdown parsing, without any BBCode and/or smileys involved.
		 *************************************************************************/
		'basicMarkdownStringProvider' => [
			// Markdown tests taken from the GFM spec.
			'paragraph' . "\n" . '    code?' . "\n",
			'1. one' . "\n\n" . '2. two' . "\n" . '3. three' . "\n",
			'1.  one' . "\n" . '    - a' . "\n\n" . '    - b' . "\n" . '2.  two' . "\n",
			' 8. item 1' . "\n" . ' 9. item 2' . "\n" . '10. item 2a' . "\n",
			'* a' . "\n" . '* * * * *' . "\n" . '* b' . "\n",
			'1. fee' . "\n" . '2. fie' . "\n" . '-  foe' . "\n" . '-  fum' . "\n",
			'[a backtick (`)](/url) and [another backtick (`)](/url).' . "\n",
			'*foo *bar* baz*' . "\n",
			'- `a long code span can contain a hyphen like this' . "\n" . '  - and it can screw things up`' . "\n",
			'- # Heading' . "\n",
			'* a' . "\n" . '*' . "\n" . '* b' . "\n",
			'&gt; Blockquote [foo].' . "\n" . '&gt;' . "\n" . '&gt; [foo]: /url' . "\n",
			'[foo]: /url1' . "\n" . '[foo]: /url2' . "\n\n" . '[foo][]' . "\n",
			"\t" . 'foo' . "\t" . 'baz' . "\t\t" . 'bim' . "\n",
			'&gt;' . "\t\t" . 'foo' . "\n",
			'-' . "\t\t" . 'foo' . "\n",
			'#' . "\t" . 'Foo' . "\n",
			'*' . "\t" . '*' . "\t" . '*' . "\t\n",
			'***' . "\n" . '---' . "\n" . '___' . "\n",
			'+++' . "\n",
			'===' . "\n",
			'--' . "\n" . '**' . "\n" . '__' . "\n",
			'_____________________________________' . "\n",
			'Foo' . "\n" . '***' . "\n" . 'bar' . "\n",
			'Foo' . "\n" . '---' . "\n" . 'bar' . "\n",
			'Foo' . "\n" . '-------------------------' . "\n\n" . 'Foo' . "\n" . '=' . "\n",
			'Foo\\' . "\n" . '----' . "\n",
			'Foo' . "\n" . 'Bar' . "\n" . '---' . "\n",
			'---' . "\n" . 'Foo' . "\n" . '---' . "\n" . 'Bar' . "\n" . '---' . "\n" . 'Baz' . "\n",
			"\n" . '====' . "\n",
			'---' . "\n" . '---' . "\n",
			'Foo' . "\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
			'Foo' . "\n\n" . 'bar' . "\n" . '---' . "\n" . 'baz' . "\n",
			'Foo' . "\n" . 'bar' . "\n\n" . '---' . "\n\n" . 'baz' . "\n",
			'Foo' . "\n" . 'bar' . "\n" . '\\---' . "\n" . 'baz' . "\n",
			'``' . "\n" . 'foo' . "\n" . '``' . "\n",
			'```' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '```' . "\n",
			'~~~' . "\n" . 'aaa' . "\n" . '```' . "\n" . '~~~' . "\n",
			'````' . "\n" . 'aaa' . "\n" . '```' . "\n" . '``````' . "\n",
			'~~~~' . "\n" . 'aaa' . "\n" . '~~~' . "\n" . '~~~~' . "\n",
			'```' . "\n",
			'`````' . "\n\n" . '```' . "\n" . 'aaa' . "\n",
			'```' . "\n" . '```' . "\n",
			'foo' . "\n" . '```' . "\n" . 'bar' . "\n" . '```' . "\n" . 'baz' . "\n",
			'````;' . "\n" . '````' . "\n",
			'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . '&lt;pre&gt;' . "\n" . '**Hello**,' . "\n\n" . '_world_.' . "\n" . '&lt;/pre&gt;' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
			'&lt;/div&gt;' . "\n" . '*foo*' . "\n",
			'&lt;div&gt;' . "\n" . '*foo*' . "\n\n" . '*bar*' . "\n",
			'&lt;table&gt;&lt;tr&gt;&lt;td&gt;' . "\n" . 'foo' . "\n" . '&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;' . "\n",
			'&lt;Warning&gt;' . "\n" . '*bar*' . "\n" . '&lt;/Warning&gt;' . "\n",
			'&lt;/ins&gt;' . "\n" . '*bar*' . "\n",
			'&lt;del&gt;' . "\n" . '*foo*' . "\n" . '&lt;/del&gt;' . "\n",
			'&lt;del&gt;' . "\n\n" . '*foo*' . "\n\n" . '&lt;/del&gt;' . "\n",
			'&lt;del&gt;*foo*&lt;/del&gt;' . "\n",
			'&lt;style&gt;p{color:red;}&lt;/style&gt;' . "\n" . '*foo*' . "\n",
			'Foo' . "\n" . '&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n",
			'&lt;div&gt;' . "\n" . 'bar' . "\n" . '&lt;/div&gt;' . "\n" . '*foo*' . "\n",
			'&lt;table&gt;' . "\n\n" . '&lt;tr&gt;' . "\n\n" . '&lt;td&gt;' . "\n" . 'Hi' . "\n" . '&lt;/td&gt;' . "\n\n" . '&lt;/tr&gt;' . "\n\n" . '&lt;/table&gt;' . "\n",
			'[foo]:' . "\n" . '/url' . "\n\n" . '[foo]' . "\n",
			'[foo]:' . "\n\n" . '[foo]' . "\n",
			'aaa' . "\n\n" . 'bbb' . "\n",
			'aaa' . "\n" . 'bbb' . "\n\n" . 'ccc' . "\n" . 'ddd' . "\n",
			'aaa' . "\n\n\n" . 'bbb' . "\n",
			'&gt; foo' . "\n" . '&gt; ---' . "\n",
			'&gt; - foo' . "\n" . '&gt; - bar' . "\n",
			'&gt; foo' . "\n" . '&gt;     - bar' . "\n",
			'&gt;' . "\n",
			'-one' . "\n\n" . '2.two' . "\n",
			'*' . "\n",
			'foo' . "\n" . '*' . "\n\n" . 'foo' . "\n" . '1.' . "\n",
			'- foo' . "\n\n" . '  bar' . "\n\n" . '  - baz' . "\n",
			'   - one' . "\n\n" . '  two' . "\n",
			'&gt;   - one' . "\n" . '&gt;' . "\n" . '&gt;  two' . "\n",
			' 10. foo' . "\n\n" . '   bar' . "\n",
			'1.  foo' . "\n\n" . '        indented code' . "\n",
			'The number of windows in my house is' . "\n" . '14.  The number of doors is 6.' . "\n",
			'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
			'  * I need to buy' . "\n" . '    - new shoes' . "\n" . '    - a coat' . "\n" . '    - a plane ticket' . "\n",
			'I need to buy' . "\n" . '- new shoes' . "\n" . '- a coat' . "\n" . '- a plane ticket' . "\n",
			'`hi`lo`' . "\n",
			'\\!\\"\\#\\$\\%\\&amp;\'\\(\\)\\*\\+\\,\\-\\.\\/\\:\\;\\&lt;\\=\\&gt;\\?\\@\\[\\\\]\\^\\_\\`\\{\\|\\}\\~' . "\n",
			'\\*emphasis*' . "\n",
			'foo\\' . "\n" . 'bar' . "\n",
			'~~~' . "\n" . '\\[\\]' . "\n" . '~~~' . "\n",
			'&lt;http://example.com?find=\\*&gt;' . "\n",
			'&amp;copy' . "\n",
			'&amp;MadeUpEntity;' . "\n",
			'`f&amp;ouml;&amp;ouml;`' . "\n",
			'&amp;#42;foo&amp;#42;' . "\n" . '*foo*' . "\n",
			'foo&amp;#10;&amp;#10;bar' . "\n",
			'&amp;#9;foo' . "\n",
			'`foo`' . "\n",
			'`&nbsp;b&nbsp;`' . "\n",
			'`foo\\`bar`' . "\n",
			'``foo`bar``' . "\n",
			'*foo`*`' . "\n",
			'`&lt;http://foo.bar.`baz&gt;`' . "\n",
			'&lt;http://foo.bar.`baz&gt;`' . "\n",
			'```foo``' . "\n",
			'`foo' . "\n",
			'`foo``bar``' . "\n",
			'***strong emph***' . "\n" . '***strong** in emph*' . "\n" . '***emph* in strong**' . "\n" . '**in strong *emph***' . "\n" . '*in emph **strong***' . "\n",
			'*emph *with emph* in it*' . "\n" . '**strong **with strong** in it**' . "\n",
			'internal emphasis: foo*bar*baz' . "\n" . 'no emphasis: foo_bar_baz' . "\n",
			'a*"foo"*' . "\n",
			'*&nbsp;a&nbsp;*' . "\n",
			'foo*bar*' . "\n",
			'5*6*78' . "\n",
			'a_"foo"_' . "\n",
			'foo_bar_' . "\n",
			'5_6_78' . "\n",
			'пристаням_стремятся_' . "\n",
			'aa_"bb"_cc' . "\n",
			'foo-_(bar)_' . "\n",
			'_foo*' . "\n",
			'*(*foo)' . "\n",
			'*(*foo*)*' . "\n",
			'*foo*bar' . "\n",
			'_(_foo)' . "\n",
			'_(_foo_)_' . "\n",
			'_foo_bar' . "\n",
			'_пристаням_стремятся' . "\n",
			'_foo_bar_baz_' . "\n",
			'_(bar)_.' . "\n",
			'a**"foo"**' . "\n",
			'foo**bar**' . "\n",
			'a__"foo"__' . "\n",
			'foo__bar__' . "\n",
			'5__6__78' . "\n",
			'пристаням__стремятся__' . "\n",
			'foo-__(bar)__' . "\n",
			'**(**foo)' . "\n",
			'*(**foo**)*' . "\n",
			'**foo**bar' . "\n",
			'__(__foo)' . "\n",
			'_(__foo__)_' . "\n",
			'__foo__bar' . "\n",
			'__пристаням__стремятся' . "\n",
			'__foo__bar__baz__' . "\n",
			'__(bar)__.' . "\n",
			'*foo' . "\n" . 'bar*' . "\n",
			'*foo**bar**baz*' . "\n",
			'&lt;p&gt;&lt;em&gt;foo&lt;/em&gt;&lt;em&gt;bar&lt;em&gt;&lt;/em&gt;baz&lt;/em&gt;&lt;/p&gt;' . "\n",
			'*foo**bar*' . "\n",
			'*foo**bar***' . "\n",
			'foo***bar***baz' . "\n",
			'foo******bar*********baz' . "\n",
			'**foo' . "\n" . 'bar**' . "\n",
			'**foo*bar*baz**' . "\n",
			'**foo*' . "\n",
			'*foo**' . "\n",
			'***foo**' . "\n",
			'****foo*' . "\n",
			'**foo***' . "\n",
			'*foo****' . "\n",
			'__foo_' . "\n",
			'_foo__' . "\n",
			'___foo__' . "\n",
			'____foo_' . "\n",
			'__foo___' . "\n",
			'_foo____' . "\n",
			'**foo**' . "\n",
			'*_foo_*' . "\n",
			'__foo__' . "\n",
			'_*foo*_' . "\n",
			'****foo****' . "\n",
			'____foo____' . "\n",
			'******foo******' . "\n",
			'***foo***' . "\n",
			'_____foo_____' . "\n",
			'*[bar*](/url)' . "\n",
			'**a&lt;http://foo.bar/?q=**&gt;' . "\n",
			'__a&lt;http://foo.bar/?q=__&gt;' . "\n",
			'[link](/uri)' . "\n",
			'[link]()' . "\n",
			'[link](&lt;&gt;)' . "\n",
			'[link](foo' . "\n" . 'bar)' . "\n",
			'[link](&lt;foo' . "\n" . 'bar&gt;)' . "\n",
			'[a](&lt;b)c&gt;)' . "\n",
			'[link](&lt;foo\\&gt;)' . "\n",
			'[a](&lt;b)c' . "\n" . '[a](&lt;b)c&gt;' . "\n" . '[a](&lt;b&gt;c)' . "\n",
			'[link](\\(foo\\))' . "\n",
			'[link](foo(and(bar)))' . "\n",
			'[link](foo\\(and\\(bar\\))' . "\n",
			'[link](&lt;foo(and(bar)&gt;)' . "\n",
			'[link](foo\\)\\:)' . "\n",
			'[link](#fragment)' . "\n\n" . '[link](http://example.com#fragment)' . "\n\n" . '[link](http://example.com?foo=3#frag)' . "\n",
			'[link](foo\\bar)' . "\n",
			'[link](foo%20b&amp;auml;)' . "\n",
			'[link]("title")' . "\n",
			'[link](/url&nbsp;"title")' . "\n",
			'[![moon](moon.jpg)](/uri)' . "\n",
			'![[[foo](uri1)](uri2)](uri3)' . "\n",
			'*[foo*](/uri)' . "\n",
			'[foo`](/uri)`' . "\n",
			'[foo&lt;http://example.com/?search=](uri)&gt;' . "\n",
			'[foo]' . "\n" . '[bar]' . "\n\n" . '[foo]: /url1' . "\n" . '[bar]: /url2' . "\n",
			'![foo](train.jpg)' . "\n",
			'![foo](&lt;url&gt;)' . "\n",
			'![](/url)' . "\n",
			'&lt;http://foo.bar.baz&gt;' . "\n",
			'&lt;http://foo.bar.baz/test?q=hello&amp;id=22&amp;boolean&gt;' . "\n",
			'&lt;irc://foo.bar:2233/baz&gt;' . "\n",
			'&lt;MAILTO:FOO@BAR.BAZ&gt;' . "\n",
			'&lt;a+b+c:d&gt;' . "\n",
			'&lt;made-up-scheme://foo,bar&gt;' . "\n",
			'&lt;http://../&gt;' . "\n",
			'&lt;localhost:5001/foo&gt;' . "\n",
			'&lt;http://example.com/\\[\\&gt;' . "\n",
			'&lt;foo@bar.example.com&gt;' . "\n",
			'&lt;foo+special@Bar.baz-bar0.com&gt;' . "\n",
			'&lt;foo\\+@bar.example.com&gt;' . "\n",
			'&lt;&gt;' . "\n",
			'&lt;m:abc&gt;' . "\n",
			'&lt;foo.bar.baz&gt;' . "\n",
			'http://example.com' . "\n",
			'foo@bar.example.com' . "\n",
			'www.commonmark.org' . "\n",
			'www.google.com/search?q=Markup+(business)' . "\n\n" . 'www.google.com/search?q=Markup+(business)))' . "\n\n" . '(www.google.com/search?q=Markup+(business))' . "\n\n" . '(www.google.com/search?q=Markup+(business)' . "\n",
			'www.google.com/search?q=(business))+ok' . "\n",
			'www.google.com/search?q=commonmark&amp;hl=en' . "\n\n" . 'www.google.com/search?q=commonmark&amp;hl;' . "\n",
			'www.commonmark.org/he&lt;lp' . "\n",
			'foo@bar.baz' . "\n",
			'a.b-c_d@a.b' . "\n\n" . 'a.b-c_d@a.b.' . "\n\n" . 'a.b-c_d@a.b-' . "\n\n" . 'a.b-c_d@a.b_' . "\n",
			'mailto:foo@bar.baz' . "\n\n" . 'mailto:a.b-c_d@a.b' . "\n\n" . 'mailto:a.b-c_d@a.b.' . "\n\n" . 'mailto:a.b-c_d@a.b/' . "\n\n" . 'mailto:a.b-c_d@a.b-' . "\n\n" . 'mailto:a.b-c_d@a.b_' . "\n\n" . 'xmpp:foo@bar.baz' . "\n\n" . 'xmpp:foo@bar.baz.' . "\n",
			'xmpp:foo@bar.baz/txt' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin' . "\n\n" . 'xmpp:foo@bar.baz/txt@bin.com' . "\n",
			'xmpp:foo@bar.baz/txt/bin' . "\n",
			'&lt;a&gt;&lt;bab&gt;&lt;c2c&gt;' . "\n",
			'&lt;a/&gt;&lt;b2/&gt;' . "\n",
			'foo\\' . "\n" . 'baz' . "\n",
			'*foo\\' . "\n" . 'bar*' . "\n",
			'`code\\' . "\n" . 'span`' . "\n",
			'foo\\' . "\n",
			'foo' . "\n" . 'baz' . "\n",
			'&gt; Lorem ipsum dolor' . "\n" . 'sit amet.' . "\n" . '&gt; - Qui *quodsi iracundia*' . "\n" . '&gt; - aliquando id' . "\n",
			'&gt; Lorem ipsum dolor' . "\n",
			'sit amet.' . "\n",
			'&gt; - Qui *quodsi iracundia*' . "\n",
			'&gt; - aliquando id' . "\n",
		],

		/**********************************************************
		 * Tests for strings with mixed BBCode and Markdown syntax.
		 **********************************************************/
		'complexStringProvider' => [
			// Test that the content of inline code spans is protected.
			'Here is some [tt]inline **code**[/tt].',
			'Here is some `inline [b]code[/b]`.',
			'Here is some `inline [b]code`.',
			'Here is some [tt]inline [b]code[/b][/tt].',
			'Here is some [tt]inline [b]code[/tt].',
		],
	];

	/**
	 * @var string
	 *
	 * Relative path to ParserTest.php
	 */
	public string $path_to_parser_test = '../Unit/ParserTest.php';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Performs necessary setup.
	 */
	public function __construct()
	{
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
		$smiley_parser_data->setAccessible(true);
		$smiley_parser_data->setValue(null, $this->getSmileyData());

		// Populate the TLD regex with a default value.
		Config::$modSettings['tld_regex'] ??= Utils::buildRegex(array_merge(Url::$basic_tlds, Url::$cc_tlds, Url::$special_use_tlds));

		// Need a user.
		User::load(0, dataset: UserDataset::None);
		User::setMe(0);
	}

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// Get the current contents of ParserTest.php.
		$contents = file_get_contents($this->path_to_parser_test);

		// Make the tests.
		foreach ($this->strings as $provider => $strings) {
			$tests = [];
			$test_num = 0;

			foreach ($strings as $string) {
				$first_test_num_for_string = $test_num;

				for (
					$input_types = (Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS);
					$input_types >= Parser::INPUT_BBC;
					$input_types--
				) {
					if (
						($input_types & Parser::INPUT_SMILEYS)
						&& $input_types !== (Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS)
						&& !str_contains($string, ':)')
					) {
						continue;
					}

					foreach ([Parser::OUTPUT_HTML, Parser::OUTPUT_BBC] as $output_type) {
						for (
							$hard_breaks = 0;
							$hard_breaks <= ($input_types & Parser::INPUT_MARKDOWN) ? 3 : 0;
							$hard_breaks++
						) {
							$options['hard_breaks'] = $hard_breaks;

							foreach ([false, true] as $for_print) {
								$options['for_print'] = $for_print;

								$output = Parser::transform(
									$string,
									input_types: $input_types,
									output_type: $output_type,
									options: $options,
								);

								if ($string === $output && $output_type === Parser::OUTPUT_BBC) {
									continue;
								}

								for ($i = $first_test_num_for_string; $i < $test_num; $i++) {
									if (isset($tests[$i]) && $output === $tests[$i][4]) {
										continue 2;
									}
								}

								$tests[$test_num++] = [
									$string,
									$input_types,
									$output_type,
									$options,
									$output,
								];
							}
						}
					}
				}
			}

			$tests = str_replace(
				[
					'1 => 1',
					'1 => 2',
					'1 => 3',
					'1 => 4',
					'1 => 5',
					'1 => 6',
					'1 => 7',

					'2 => 0',
					'2 => 1',
					'2 => 2',
					'2 => 3',

					'\'hard_breaks\' => 1',
					'\'hard_breaks\' => 2',
					'\'hard_breaks\' => 3',

					Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE,
					Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE,
					Utils::TAB_SUBSTITUTE,
					'\'\' . Utils::TAB_SUBSTITUTE',
					'Utils::TAB_SUBSTITUTE . \'\'',
				],
				[
					'Parser::INPUT_BBC',
					'Parser::INPUT_MARKDOWN',
					'Parser::INPUT_BBC | Parser::INPUT_MARKDOWN',
					'Parser::INPUT_SMILEYS',
					'Parser::INPUT_BBC | Parser::INPUT_SMILEYS',
					'Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS',
					'Parser::INPUT_BBC | Parser::INPUT_MARKDOWN | Parser::INPUT_SMILEYS',

					'Parser::OUTPUT_HTML',
					'Parser::OUTPUT_TEXT',
					'Parser::OUTPUT_BBC',
					'MarkdownParser::OUTPUT_HTML_STRICT',

					'\'hard_breaks\' => MarkdownParser::BR_LINES',
					'\'hard_breaks\' => MarkdownParser::BR_IN_PARAGRAPHS',
					'\'hard_breaks\' => MarkdownParser::BR_LINES | MarkdownParser::BR_IN_PARAGRAPHS',

					'\' . Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE . \'',
					'\' . Utils::TAB_SUBSTITUTE . Utils::TAB_SUBSTITUTE . \'',
					'\' . Utils::TAB_SUBSTITUTE . \'',
					'Utils::TAB_SUBSTITUTE',
					'Utils::TAB_SUBSTITUTE',
				],
				'return ' . Config::varExport($tests) . ';' . "\n",
			);

			$tests = preg_replace(
				[
					'/\t\t\K\d+ => /',
					'/' . preg_quote(Config::$modSettings['smileys_url'] . '/' . Config::$modSettings['smiley_sets_default'], '/') . '/',
					'/' . preg_quote(Config::$modSettings['smileys_url'], '/') . '/',
					'/' . preg_quote(Config::$scripturl, '/') . '/',
					'/' . preg_quote(Config::$boardurl, '/') . '/',
					'/^/m',
				],
				[
					'',
					"'" . ' . Config::$modSettings[\'smileys_url\'] . \'/\' . Config::$modSettings[\'smiley_sets_default\'] . ' . "'",
					"'" . ' . Config::$modSettings[\'smileys_url\'] . \'' . "'",
					"'" . ' . Config::$scripturl . ' . "'",
					"'" . ' . Config::$boardurl . ' . "'",
					"\t\t",
				],
				$tests,
			);

			// Update ParserTest.php with our new tests.
			$contents = preg_replace(
				'/public\s+static\s+function\s+' . $provider . '\(\)\:\s*array\s*\{\n\X*?\n\t\}/',
				'public static function ' . $provider . '(): array' . "\n\t" . '{' . "\n" . strtr($tests, ['\\' => '\\\\']) . "\t}",
				$contents,
			);
		}

		// Save the updated tests to the file.
		file_put_contents($this->path_to_parser_test, $contents);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets default data to populate SmileyParser::$data with.
	 *
	 * @return array Data to populate SmileyParser::$data with.
	 */
	private function getSmileyData(): array
	{
		foreach (
			(new \ReflectionClass(Smileys::class))->getDefaultProperties()['initial_data'] as $row
		) {
			$data[''][(int) $row['id_smiley']] = [
				'code' => $row['code'],
				'filename' => '',
				'description' => Lang::getTxt(substr($row['description'], 2, -1), file: 'Maintenance'),
			];
		}

		foreach (
			(new \ReflectionClass(SmileyFiles::class))->getDefaultProperties()['initial_data'] as $row
		) {
			if ($row['smiley_set'] === Config::$modSettings['smiley_sets_default']) {
				$data[''][(int) $row['id_smiley']]['filename'] = $row['filename'];
			}
		}

		$data[Config::$modSettings['smiley_sets_default']] = $data[''];

		return $data;
	}
}
