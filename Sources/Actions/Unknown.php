<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Utils;

/**
 * Class Unknown
 */
class Unknown implements ActionInterface
{
	use ActionTrait;

	/********************
	 * Mysterious methods
	 ********************/

	public function canBeLogged(): bool
	{
		return false;
	}

	/**
	 * What's this? I dunno, what are you talking about? Never seen this before, nope. No sir.
	 */
	public function execute(): void
	{
		echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>The Book of Unknown, ', @$_GET['verse'] == '2:18' ? '2:18' : '4:16', '</title>
		<style>
			em
			{
				font-size: 1.3em;
				line-height: 0;
			}
		</style>
	</head>
	<body style="background-color: #444455; color: white; font-style: italic; font-family: serif;">
		<div style="margin-top: 12%; font-size: 1.1em; line-height: 1.4; text-align: center;">';

		if (!isset($_GET['verse']) || ($_GET['verse'] != '2:18' && $_GET['verse'] != '22:1-2')) {
			$_GET['verse'] = '4:16';
		}

		if ($_GET['verse'] == '2:18') {
			echo '
			Woe, it was that his name wasn\'t <em>known</em>, that he came in mystery, and was recognized by none.&nbsp;And it became to be in those days <em>something</em>.&nbsp; Something not yet <em id="unknown" name="[Unknown]">unknown</em> to mankind.&nbsp; And thus what was to be known the <em>secret project</em> began into its existence.&nbsp; Henceforth the opposition was only <em>weary</em> and <em>fearful</em>, for now their match was at arms against them.';
		} elseif ($_GET['verse'] == '4:16') {
			echo '
			And it came to pass that the <em>unbelievers</em> dwindled in number and saw rise of many <em>proselytizers</em>, and the opposition found fear in the face of the <em>x</em> and the <em>j</em> while those who stood with the <em>something</em> grew stronger and came together.&nbsp; Still, this was only the <em>beginning</em>, and what lay in the future was <em id="unknown" name="[Unknown]">unknown</em> to all, even those on the right side.';
		} elseif ($_GET['verse'] == '22:1-2') {
			echo '
			<p>Now <em>behold</em>, that which was once the secret project was <em id="unknown" name="[Unknown]">unknown</em> no longer.&nbsp; Alas, it needed more than <em>only one</em>, but yet even thought otherwise.&nbsp; It became that the opposition <em>rumored</em> and lied, but still to no avail.&nbsp; Their match, though not <em>perfect</em>, had them outdone.</p>
			<p style="margin: 2ex 1ex 0 1ex; font-size: 1.05em; line-height: 1.5; text-align: center;">Let it continue.&nbsp; <em>The end</em>.</p>';
		}

		echo '
		</div>
		<div style="margin-top: 2ex; font-size: 2em; text-align: right;">';

		if ($_GET['verse'] == '2:18') {
			echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="', Config::$scripturl, '?action=about:unknown;verse=4:16" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 2:18</span>';
		} elseif ($_GET['verse'] == '4:16') {
			echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="', Config::$scripturl, '?action=about:unknown;verse=22:1-2" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 4:16</span>';
		} elseif ($_GET['verse'] == '22:1-2') {
			echo '
			from <span style="font-family: Georgia, serif;"><strong>The Book of Unknown</strong>, 22:1-2</span>';
		}

		echo '
		</div>
	</body>
</html>';

		Utils::obExit(false);
	}
}

?>