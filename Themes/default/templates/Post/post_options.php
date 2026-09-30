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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Draws the checkboxes that sit below the editor on the posting form.
 *
 * See Post::setupPostOptions() for what each option looks like. Anything an
 * option lists under 'hidden' is written out just before its checkbox, because
 * a checkbox that is not ticked submits nothing at all and the hidden field of
 * the same name is what carries the "off" value.
 *
 * @param array $post_options The options to draw, in the order they go in.
 */
?>

					<ul id="post_options" class="smalltext">
<?php foreach ($post_options as $option): ?>
<?php if (!$option['can_show']): ?>
<?php continue; ?>
<?php endif; ?>
						<li>
<?php foreach ($option['hidden'] ?? [] as $hidden_name => $hidden_value): ?>
							<input type="hidden" name="<?= $hidden_name ?>" value="<?= $hidden_value ?>">
<?php endforeach; ?>
							<label for="<?= $option['id'] ?>">
								<input type="checkbox" name="<?= $option['name'] ?>" id="<?= $option['id'] ?>" value="<?= $option['value'] ?? '1' ?>"<?= $option['checked'] ? ' checked' : '' ?>> <?= $option['label'] ?>

							</label>
						</li>
<?php endforeach; ?>
					</ul><!-- #post_options -->