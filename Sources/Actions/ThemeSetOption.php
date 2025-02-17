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
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Routable;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Sets a theme option via an AJAX call.
 *
 * - sets a theme option without outputting anything.
 * - can be used with JavaScript, via a dummy image (which doesn't require the page to reload.)
 * - requires someone who is logged in.
 * - accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
 * - does not log access to the Who's Online log. (in index.php..)
 */
class ThemeSetOption implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	public function canBeLogged(): bool
	{
		return false;
	}

	/**
	 * Do the job.
	 */
	public function execute(): void
	{
		// Check the session id.
		User::$me->checkSession('get');

		if (!isset(Theme::$current)) {
			Theme::load();
		}

		// This good-for-nothing pixel is used to keep the session alive.
		if (empty($_GET['var']) || !isset($_GET['val'])) {
			Utils::redirectexit(Theme::$current->settings['images_url'] . '/blank.png');
		}

		// Sorry, guests can't go any further than this.
		if (User::$me->is_guest || User::$me->id == 0) {
			Utils::obExit(false);
		}

		// Can't change reserved vars.
		if (in_array(strtolower($_GET['var']), Theme::$reservedVars)) {
			Utils::redirectexit(Theme::$current->settings['images_url'] . '/blank.png');
		}

		// Use a specific theme?
		if (isset($_GET['th']) || isset($_GET['id'])) {
			// Invalidate the current themes cache too.
			CacheApi::put('theme_settings-' . Theme::$current->settings['theme_id'] . ':' . User::$me->id, null, 60);

			Theme::$current->settings['theme_id'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];
		}

		// If this is the admin preferences the passed value will just be an element of it.
		if ($_GET['var'] == 'admin_preferences') {
			Theme::$current->options['admin_preferences'] = !empty(Theme::$current->options['admin_preferences']) ? Utils::jsonDecode(Theme::$current->options['admin_preferences'], true) : [];

			// New thingy...
			if (isset($_GET['admin_key']) && strlen($_GET['admin_key']) < 5) {
				Theme::$current->options['admin_preferences'][$_GET['admin_key']] = $_GET['val'];
			}

			// Change the value to be something nice,
			$_GET['val'] = Utils::jsonEncode(Theme::$current->options['admin_preferences']);
		}

		// Update the option.
		Db::$db->insert(
			'replace',
			'{db_prefix}themes',
			[
				'id_theme' => 'int',
				'id_member' => 'int',
				'variable' => 'string-255',
				'value' => 'string-65534',
			],
			[
				[
					Theme::$current->settings['theme_id'],
					User::$me->id,
					$_GET['var'],
					is_array($_GET['val']) ? implode(',', $_GET['val']) : $_GET['val'],
				],
			],
			[
				'id_theme',
				'id_member',
				'variable',
			],
		);

		CacheApi::put('theme_settings-' . Theme::$current->settings['theme_id'] . ':' . User::$me->id, null, 60);

		// Don't output anything...
		Utils::redirectexit(Theme::$current->settings['images_url'] . '/blank.png');
	}
}

?>