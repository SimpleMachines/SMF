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

use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Show the menu up top. Something like [home] [help] [profile] [logout]...
 */
?>
					<ul class="dropmenu menu_nav">
<?php /* Note: Menu markup has been cleaned up to remove unnecessary spans and classes. */ ?>
<?php foreach (Utils::$context['menu_buttons'] as $act => $button): ?>
						<li class="button_<?= $act ?><?= !empty($button['sub_buttons']) ? ' subsections"' : '"' ?>>
							<a<?= $button['active_button'] ? ' class="active"' : '' ?> href="<?= $button['href'] ?>"<?= isset($button['target']) ? ' target="' . $button['target'] . '"' : '' ?><?= isset($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '' ?>>
								<?= $button['icon'] ?><span class="textmenu"><?= $button['title'] ?><?= !empty($button['amt']) ? ' <span class="amt">' . $button['amt'] . '</span>' : '' ?></span>
							</a>
<?php /* 2nd level menus */ ?>
<?php if (!empty($button['sub_buttons'])): ?>
							<ul>
<?php foreach ($button['sub_buttons'] as $childbutton): ?>
								<li<?= !empty($childbutton['sub_buttons']) ? ' class="subsections"' : '' ?>>
									<a href="<?= $childbutton['href'] ?>"<?= isset($childbutton['target']) ? ' target="' . $childbutton['target'] . '"' : '' ?><?= isset($childbutton['onclick']) ? ' onclick="' . $childbutton['onclick'] . '"' : '' ?>>
										<?= $childbutton['title'] ?><?= !empty($childbutton['amt']) ? ' <span class="amt">' . $childbutton['amt'] . '</span>' : '' ?>
									</a>
<?php /* 3rd level menus :) */ ?>
<?php if (!empty($childbutton['sub_buttons'])): ?>
									<ul>
<?php foreach ($childbutton['sub_buttons'] as $grandchildbutton): ?>
										<li>
											<a href="<?= $grandchildbutton['href'] ?>"<?= isset($grandchildbutton['target']) ? ' target="' . $grandchildbutton['target'] . '"' : '' ?><?= isset($grandchildbutton['onclick']) ? ' onclick="' . $grandchildbutton['onclick'] . '"' : '' ?>>
												<?= $grandchildbutton['title'] ?><?= !empty($grandchildbutton['amt']) ? ' <span class="amt">' . $grandchildbutton['amt'] . '</span>' : '' ?>
											</a>
										</li>
<?php endforeach; ?>
									</ul>
<?php endif; ?>
								</li>
<?php endforeach; ?>
							</ul>
<?php endif; ?>
						</li>
<?php endforeach; ?>
					</ul><!-- .menu_nav -->