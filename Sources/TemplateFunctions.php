<?php

/**
 * Backward compatibility file.
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

/*
 * The functions that templates call on each other by name.
 *
 * Mods and themes call template_button_strip(), template_show_list() and a few
 * more directly, from templates of their own. Once the template that held one
 * of them is a Plates template, the function is defined here instead, and
 * renders the Plates template.
 *
 * TemplateEngine::addLoaded() includes this file each time it loads a Plates
 * template, with $template set to its name, so that only that template's
 * functions are defined, and only then. A function that already exists is
 * left alone: a theme that still has the template as a *.template.php file
 * has its own.
 */

use SMF\TemplateEngine;

if (!defined('SMF')) {
	die('No direct access...');
}

switch ($template) {
	case 'index':
		if (!function_exists('template_button_strip')) {
			function template_button_strip($button_strip, $direction = '', $strip_options = [])
			{
				echo TemplateEngine::get()->render('index/button_strip', get_defined_vars());
			}
		}

		if (!function_exists('template_quickbuttons')) {
			function template_quickbuttons($list_items, $list_class = null, $output_method = 'echo')
			{
				$html = TemplateEngine::get()->render('index/quickbuttons', get_defined_vars());

				// There are a few spots where the result needs to be returned.
				if ($output_method != 'echo') {
					return $html;
				}

				echo $html;
			}
		}

		if (!function_exists('template_menu')) {
			function template_menu()
			{
				echo TemplateEngine::get()->render('index/menu');
			}
		}

		if (!function_exists('theme_linktree')) {
			function theme_linktree($force_show = false)
			{
				echo TemplateEngine::get()->render('index/linktree', get_defined_vars());
			}
		}

		break;

	case 'GenericList':
		if (!function_exists('template_show_list')) {
			function template_show_list($list_id = null)
			{
				echo TemplateEngine::get()->render('GenericList/show_list', get_defined_vars());
			}
		}

		break;

	case 'GenericControls':
		if (!function_exists('template_control_richedit')) {
			function template_control_richedit(string $editor_id, bool|string|null $smiley_container = null, bool|string|null $bbc_container = null): void
			{
				echo TemplateEngine::get()->render('GenericControls/control_richedit', get_defined_vars());
			}
		}

		if (!function_exists('template_control_richedit_buttons')) {
			function template_control_richedit_buttons(string $editor_id): void
			{
				echo TemplateEngine::get()->render('GenericControls/control_richedit_buttons', get_defined_vars());
			}
		}

		// Always returns null: a Plates template cannot say whether there is more to show one item at a time.
		if (!function_exists('template_control_verification')) {
			function template_control_verification(int|string $verify_id, string $display_type = 'all', bool $reset = false): ?bool
			{
				echo TemplateEngine::get()->render('GenericControls/control_verification', get_defined_vars());

				return null;
			}
		}

		break;

	// An XML response has no index template, so nothing it shows can call these for real.
	case 'Xml':
		if (!function_exists('template_button_strip')) {
			function template_button_strip($button_strip, $direction = 'top', $strip_options = []) {}
		}

		if (!function_exists('template_menu')) {
			function template_menu() {}
		}

		if (!function_exists('theme_linktree')) {
			function theme_linktree() {}
		}

		break;
}
