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
 * Prints the input fields in the form's header (subject, message icon, guest name & email, etc.)
 *
 * Mod authors can use the 'integrate_post_end' hook to modify or add to these (see Post.php).
 *
 * Theme authors can customize the output in a couple different ways:
 * 1. Change specific values in the Utils::$context['posting_fields'] array.
 * 2. Add an 'html' element to the 'label' and/or 'input' elements of the field they want to
 *    change. This should contain the literal HTML string to be printed.
 *
 * See the documentation in Post.php for more info on the Utils::$context['posting_fields'] array.
 */
?><?php /* Sanity check: submitting the form won't work without at least a subject field */ ?><?php if (empty(Utils::$context['posting_fields']['subject']) || !is_array(Utils::$context['posting_fields']['subject'])): ?><?php
Utils::$context['posting_fields']['subject'] = [
	'label' => ['html' => '<label for="subject" id="caption_subject">' . Lang::getTxt('subject', file: 'General') . '</label>'],
	'input' => ['html' => '<input type="text" id="subject" name="subject" value="' . Utils::$context['subject'] . '" size="80" maxlength="80" required>'],
];
?><?php endif; ?><?php
// THEME AUTHORS: Above this line is a great place to make customizations to the posting_fields array
// Start printing the header
?>

					<dl id="post_header"><?php foreach (Utils::$context['posting_fields'] as $pfid => $pf): ?><?php /* We need both a label and an input */ ?><?php if (empty($pf['label']) || empty($pf['input'])): ?><?php continue; ?><?php endif; ?><?php /* The labels are pretty simple... */ ?>

						<dt class="clear pf_<?= $pfid ?>"><?php /* Any leading HTML before the label */ ?><?php if (!empty($pf['label']['before'])): ?>

							<?= $pf['label']['before'] ?><?php endif; ?><?php if (!empty($pf['label']['html'])): ?><?= $pf['label']['html'] ?><?php else: ?>

							<label<?= ($pf['input']['type'] === 'radio_select' ? '' : ' for="' . (!empty($pf['input']['attributes']['id']) ? $pf['input']['attributes']['id'] : $pfid) . '"') ?> id="caption_<?= $pfid ?>"<?= !empty($pf['label']['class']) ? ' class="' . $pf['label']['class'] . '"' : '' ?>><?= $pf['label']['text'] ?></label><?php endif; ?><?php /* Any trailing HTML after the label */ ?><?php if (!empty($pf['label']['after'])): ?>

							<?= $pf['label']['after'] ?><?php endif; ?>

						</dt><?php /* Here's where the fun begins... */ ?>

						<dd class="pf_<?= $pfid ?>"><?php /* Any leading HTML before the main input */ ?><?php if (!empty($pf['input']['before'])): ?>

							<?= $pf['input']['before'] ?><?php endif; ?><?php /* If there is a literal HTML string already defined, just print it. */ ?><?php if (!empty($pf['input']['html'])): ?><?= $pf['input']['html'] ?><?php /* Simple text inputs and checkboxes */ ?><?php elseif (in_array($pf['input']['type'], ['text', 'password', 'color', 'date', 'datetime-local', 'email', 'month', 'number', 'range', 'tel', 'time', 'url', 'week', 'checkbox'])): ?>

							<input type="<?= $pf['input']['type'] ?>"<?php if (empty($pf['input']['attributes']['id'])): ?> id="<?= $pfid ?>"<?php endif; ?><?php if (empty($pf['input']['attributes']['name'])): ?> name="<?= $pfid ?>"<?php endif; ?><?php if (!empty($pf['input']['attributes']) && is_array($pf['input']['attributes'])): ?><?php foreach ($pf['input']['attributes'] as $attribute => $value): ?><?php if (is_bool($value)): ?><?= $value ? ' ' . $attribute : '' ?><?php else: ?> <?= $attribute ?>="<?= $value ?>"<?php endif; ?><?php endforeach; ?><?php endif; ?>><?php /* textarea */ ?><?php elseif ($pf['input']['type'] === 'textarea'): ?>

							<textarea<?php if (empty($pf['input']['attributes']['id'])): ?> id="<?= $pfid ?>"<?php endif; ?><?php if (empty($pf['input']['attributes']['name'])): ?> name="<?= $pfid ?>"<?php endif; ?><?php if (!empty($pf['input']['attributes']) && is_array($pf['input']['attributes'])): ?><?php foreach ($pf['input']['attributes'] as $attribute => $value): ?><?php if ($attribute === 'value'): ?><?php continue; ?><?php endif; ?><?php if (is_bool($value)): ?><?= $value ? ' ' . $attribute : '' ?><?php else: ?> <?= $attribute ?>="<?= $value ?>"<?php endif; ?><?php endforeach; ?><?php endif; ?>><?= !empty($pf['input']['attributes']['value']) ? $pf['input']['attributes']['value'] : '' ?></textarea><?php /* Select menus are more complicated */ ?><?php elseif ($pf['input']['type'] === 'select' && is_array($pf['input']['options'])): ?><?php /* The select element itself */ ?>

							<select<?php if (empty($pf['input']['attributes']['id'])): ?> id="<?= $pfid ?>"<?php endif; ?><?php if (empty($pf['input']['attributes']['name'])): ?> name="<?= $pfid ?><?= !empty($pf['input']['attributes']['multiple']) ? '[]' : '' ?>"<?php endif; ?><?php if (!empty($pf['input']['attributes']) && is_array($pf['input']['attributes'])): ?><?php foreach ($pf['input']['attributes'] as $attribute => $value): ?><?php if (is_bool($value)): ?><?= $value ? ' ' . $attribute : '' ?><?php else: ?> <?= $attribute ?>="<?= $value ?>"<?php endif; ?><?php endforeach; ?><?php endif; ?>><?php /* The options */ ?><?php foreach ($pf['input']['options'] as $optlabel => $option): ?><?php /* An option containing options is an optgroup */ ?><?php if (!empty($option['options']) && is_array($option['options'])): ?>

								<optgroup<?php if (empty($option['label'])): ?> label="<?= $optlabel ?>"<?php endif; ?><?php if (!empty($option) && is_array($option)): ?><?php foreach ($option as $attribute => $value): ?><?php if ($attribute === 'options'): ?><?php continue; ?><?php endif; ?><?php if (is_bool($value)): ?><?= $value ? ' ' . $attribute : '' ?><?php else: ?> <?= $attribute ?>="<?= $value ?>"<?php endif; ?><?php endforeach; ?><?php endif; ?>><?php foreach ($option['options'] as $grouped_optlabel => $grouped_option): ?>

									<option<?php foreach ($grouped_option as $attribute => $value): ?><?php if (is_bool($value)): ?><?= $value ? ' ' . $attribute : '' ?><?php else: ?> <?= $attribute ?>="<?= $value ?>"<?php endif; ?><?php endforeach; ?>><?= $grouped_option['label'] ?></option><?php endforeach; ?>

								</optgroup><?php /* Simple option */ ?><?php else: ?>

								<option<?php foreach ($option as $attribute => $value): ?><?php if (is_bool($value)): ?><?= $value ? ' ' . $attribute : '' ?><?php else: ?> <?= $attribute ?>="<?= $value ?>"<?php endif; ?><?php endforeach; ?>><?= $optlabel ?></option><?php endif; ?><?php endforeach; ?><?php /* Close the select element */ ?>

							</select><?php /* Radio_select makes a div with some radio buttons in it */ ?><?php elseif ($pf['input']['type'] === 'radio_select' && is_array($pf['input']['options'])): ?>

							<div<?php if (!empty($pf['input']['attributes']) && is_array($pf['input']['attributes'])): ?><?php foreach ($pf['input']['attributes'] as $attribute => $value): ?><?php if ($attribute === 'name'): ?><?php continue; ?><?php endif; ?><?php if (is_bool($value)): ?><?= $value ? ' ' . $attribute : '' ?><?php else: ?> <?= $attribute ?>="<?= $value ?>"<?php endif; ?><?php endforeach; ?><?php endif; ?>><?php foreach ($pf['input']['options'] as $optlabel => $option): ?>

							<label style="margin-right:2ch"><input type="radio" name="<?= !empty($pf['input']['attributes']['name']) ? $pf['input']['attributes']['name'] : $pfid ?>"<?php foreach ($option as $attribute => $value): ?><?php if ($attribute === 'label'): ?><?php continue; ?><?php endif; ?><?php if (is_bool($value)): ?><?= $value ? ' ' . ($attribute === 'selected' ? 'checked' : $attribute) : '' ?><?php else: ?> <?= $attribute ?>="<?= $value ?>"<?php endif; ?><?php endforeach; ?>> <?= $option['label'] ?? $optlabel ?></label><?php endforeach; ?>

							</div><?php endif; ?><?php /* Any trailing HTML after the main input */ ?><?php if (!empty($pf['input']['after'])): ?>

							<?= $pf['input']['after'] ?><?php endif; ?>

						</dd><?php endforeach; ?>

					</dl>