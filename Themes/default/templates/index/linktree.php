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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Show a linktree. This is that thing that shows "My Community | General Category | General Discussion"..
 *
 * @param bool $force_show Whether to force showing it even if settings say otherwise
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['force_show' => false], EXTR_SKIP);
?><?php
global $shown_linktree;
// If linktree is empty, just return - also allow an override.
?><?php if (empty(Utils::$context['linktree']) || (!empty(Utils::$context['dont_default_linktree']) && !$force_show)): ?><?php return; ?><?php endif; ?><?php
// A breadcrumb trail is a landmark, and it is an ordered list rather than an
// unordered one - "General Category" comes before "General Discussion".
?>

				<nav class="navigate_section" aria-label="<?= Lang::getTxt('breadcrumb', file: 'General') ?>">
					<ol itemscope itemtype="https://schema.org/BreadcrumbList"><?php /* Each tree item has a URL and name. Some may have extra_before and extra_after. */ ?><?php foreach (Utils::$context['linktree'] as $link_num => $tree): ?>

						<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"<?= ($link_num == count(Utils::$context['linktree']) - 1) ? ' class="last"' : '' ?>><?php
// Don't show a separator for the first one.
// Better here. Always points to the next level when the linktree breaks to a second line.
// Picked a better looking HTML entity, and added support for RTL plus a span for styling.
?><?php if ($link_num != 0): ?>

							<span class="dividers"><?= Utils::$context['right_to_left'] ? ' &#9668; ' : ' &#9658; ' ?></span><?php endif; ?><?php /* Show something before the link? */ ?><?php if (isset($tree['extra_before'])): ?><?= $tree['extra_before'] ?> <?php endif; ?><?php
// Show the link, including a URL if it should have one. The itemprop
// attributes let a search engine read the trail as a breadcrumb.
?><?php if (isset($tree['url'])): ?>

							<a itemprop="item" href="<?= $tree['url'] ?>"><span itemprop="name"><?= $tree['name'] ?></span></a><?php else: ?>

							<span itemprop="name"><?= $tree['name'] ?></span><?php endif; ?><?php /* Where this one sits in the trail. Positions are 1 based. */ ?>

							<meta itemprop="position" content="<?= $link_num + 1 ?>"><?php /* Show something after the link...? */ ?><?php if (isset($tree['extra_after'])): ?> <?= $tree['extra_after'] ?><?php endif; ?>

						</li><?php endforeach; ?>

					</ol>
				</nav><!-- .navigate_section --><?php $shown_linktree = true; ?>
