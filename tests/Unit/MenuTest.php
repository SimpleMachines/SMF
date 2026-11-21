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

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Lang;
use SMF\Menu;

/**
 * Covers menu area labels, including pre-translated text.
 */
#[CoversClass(Menu::class)]
class MenuTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * An explicit area label is kept rather than replaced with the area ID.
	 *
	 * Expected: Personal message menu labels show their translated text.
	 * Guards:   The fallback displayed IDs such as "send" and "manlabels".
	 *
	 * @link https://github.com/SimpleMachines/SMF/issues/9362
	 */
	public function testExplicitAreaLabelsArePreserved(): void
	{
		// Avoid the theme-dependent constructor; exercise Menu::buildArea().
		$menu = new class extends Menu {
			/*******************
			 * Public properties
			 *******************/

			/** @var string Section ID for the isolated test menu. */
			public string $section_id = 'folders';

			/** @var string Area ID for the isolated test menu. */
			public string $area_id = '';

			/****************
			 * Public methods
			 ****************/

			/**
			 * Prepare a small menu without requiring a loaded theme.
			 */
			public function __construct()
			{
				$this->current_action = 'pm';
				$this->base_url = 'test.local/index.php?action=pm';
				$this->options = ['lang_file' => 'General'];
				$this->sections = ['folders' => ['areas' => []]];
				$this->data = ['folders' => ['areas' => []]];
			}

			/**
			 * Add an area through the real Menu::buildArea() method.
			 *
			 * @param string $area_id Area ID.
			 * @param array $details Menu area definition.
			 */
			public function addTestArea(string $area_id, array $details): void
			{
				$this->area_id = $area_id;
				$this->data['folders']['areas'][$area_id] = $details;
				$this->buildArea($details);
			}

			/**
			 * Change the language file for fallback label verification.
			 *
			 * @param string $file Language file name.
			 */
			public function setTestLanguageFile(string $file): void
			{
				$this->options['lang_file'] = $file;
			}
		};

		// PM action pre-translates these before passing them to Menu.
		foreach (
			[
				'send' => 'new_message',
				'sent' => 'sent_items',
				'prune' => 'pm_prune',
				'manlabels' => 'pm_manage_labels',
				'manrules' => 'pm_manage_rules',
			] as $id => $key
		) {
			$label = Lang::getTxt($key, file: 'PersonalMessage');
			$menu->addTestArea($id, ['label' => $label]);

			$this->assertSame($label, $menu->sections['folders']['areas'][$id]['label']);
		}

		// User-defined labels are literal text too, not language keys.
		$menu->addTestArea('label42', ['label' => 'Custom &amp; label']);
		$this->assertSame('Custom &amp; label', $menu->sections['folders']['areas']['label42']['label']);

		// Areas without an explicit label still use their language key.
		$menu->setTestLanguageFile('PersonalMessage');
		$menu->addTestArea('inbox', []);
		$this->assertSame(Lang::getTxt('inbox', file: 'PersonalMessage'), $menu->sections['folders']['areas']['inbox']['label']);
	}
}
