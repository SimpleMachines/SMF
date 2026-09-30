<?php

/**
 * Calls the template functions the way a mod's own template would, through
 * SSI, so that the old templates and the Plates ones can be compared on what
 * mods reach directly. .dev/capture-pages.sh copies it into the forum root,
 * requests it, and removes it again.
 */

declare(strict_types=1);

require __DIR__ . '/SSI.php';

echo "\n[ssi_menubar]\n";
ssi_menubar();

echo "\n[template_button_strip]\n";
template_button_strip([
	'home' => ['text' => 'home', 'url' => SMF\Config::$scripturl, 'lang' => true],
	'search' => ['text' => 'search', 'url' => SMF\Config::$scripturl . '?action=search', 'lang' => true, 'active' => true],
], 'right');

echo "\n[template_quickbuttons return]\n";
echo var_export(template_quickbuttons([
	'reply' => ['label' => 'Reply', 'href' => '#reply', 'icon' => 'reply_button'],
	'more' => [['label' => 'Split', 'href' => '#split']],
], 'probe', 'return'), true);

echo "\n[template_quickbuttons echo]\n";
var_export(template_quickbuttons([
	'reply' => ['label' => 'Reply', 'href' => '#reply'],
], 'probe'));

SMF\Utils::$context['linktree'] = [
	['url' => SMF\Config::$scripturl, 'name' => 'Forum'],
	['url' => SMF\Config::$scripturl . '?board=1.0', 'name' => 'Board'],
];

echo "\n[theme_linktree]\n";
theme_linktree(true);

echo "\n[done]\n";
