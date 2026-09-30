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

use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The template for the AJAX quote feature
 */
?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<title><?= Lang::getTxt('retrieving_quote', file: 'Post') ?></title>
		<script src="<?= Theme::$current->settings['default_theme_url'] ?>/scripts/script.js<?= Utils::$context['browser_cache'] ?>"></script>
	</head>
	<body>
		<?= Lang::getTxt('retrieving_quote', file: 'Post') ?>

		<div id="temporary_posting_area" style="display: none;"></div>
		<script><?php if (Utils::$context['close_window']): ?>

			window.close();<?php else: ?><?php /* Lucky for us, Internet Explorer has an "innerText" feature which basically converts entities <--> text. Use it if possible ;) */ ?>

			var quote = '<?= Utils::$context['quote']['text'] ?>';
			var stage = 'createElement' in document ? document.createElement("DIV") : document.getElementById("temporary_posting_area");

			if ('DOMParser' in window && !('opera' in window))
			{
				var xmldoc = new DOMParser().parseFromString("<temp>" + '<?= Utils::$context['quote']['mozilla'] ?>'.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_") + "</temp>", "text/xml");
				quote = xmldoc.childNodes[0].textContent.replace(/_SMF-BREAK_/g, "\n").replace(/_SMF-TAB_/g, "\t");
			}
			else if ('innerText' in stage)
			{
				setInnerHTML(stage, quote.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_").replace(/</g, "&lt;").replace(/>/g, "&gt;"));
				quote = stage.innerText.replace(/_SMF-BREAK_/g, "\n").replace(/_SMF-TAB_/g, "\t");
			}

			if ('opera' in window)
				quote = quote.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, '"').replace(/&amp;/g, "&");

			window.opener.onReceiveOpener(quote);

			window.focus();
			setTimeout(function() { window.close(); }, 400);<?php endif; ?>

		</script>
	</body>
</html>