<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\Alert;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows all alerts for a member.
 */
class ShowAlerts implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'showAlerts' => 'showAlerts',
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// Are we opening a specific alert? (i.e.: ?action=profile;area=showalerts;alert=12345)
		if (!empty($_REQUEST['alert'])) {
			$alert_id = (int) $_REQUEST['alert'];
			$alerts = Alert::fetch(User::$me->id, $alert_id);
			$alert = array_pop($alerts);

			/*
			 * MOD AUTHORS:
			 * To control this redirect, use the 'integrate_fetch_alerts' hook to
			 * set the value of $alert['extra']['content_link'], which will become
			 * the value for $alert['target_href'].
			 */

			// In case it failed to determine this alert's link
			if (empty($alert->target_href)) {
				Utils::redirectexit('action=profile;area=showalerts');
			}

			// Mark the alert as read while we're at it.
			Alert::mark(User::$me->id, $alert_id, 1);

			// Take the user to the content
			Utils::redirectexit($alert->target_href);
		}

		// Prepare the pagination vars.
		$maxIndex = !empty(Config::$modSettings['alerts_per_page']) && (int) Config::$modSettings['alerts_per_page'] < 1000 ? min((int) Config::$modSettings['alerts_per_page'], 1000) : 25;
		Utils::$context['start'] = (int) isset($_REQUEST['start']) ? $_REQUEST['start'] : 0;

		// Fix invalid 'start' offsets.
		if (Utils::$context['start'] > User::$me->alerts) {
			Utils::$context['start'] = User::$me->alerts - (User::$me->alerts % $maxIndex);
		} else {
			Utils::$context['start'] = Utils::$context['start'] - (Utils::$context['start'] % $maxIndex);
		}

		// Get the alerts.
		Utils::$context['alerts'] = Alert::fetch(User::$me->id, true, $maxIndex, Utils::$context['start'], true, true);
		$toMark = false;
		$action = '';

		//  Are we using checkboxes?
		Utils::$context['showCheckboxes'] = !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1;

		// Create the pagination.
		Utils::$context['pagination'] = new PageIndex(Config::$scripturl . '?action=profile;area=showalerts;u=' . User::$me->id, Utils::$context['start'], User::$me->alerts, $maxIndex, false);

		// Set some JavaScript for checking all alerts at once.
		if (Utils::$context['showCheckboxes']) {
			Theme::addInlineJavaScript('
			$(function(){
				$(\'#select_all\').on(\'change\', function() {
					var checkboxes = $(\'ul.quickbuttons\').find(\':checkbox\');
					if($(this).prop(\'checked\')) {
						checkboxes.prop(\'checked\', true);
					}
					else {
						checkboxes.prop(\'checked\', false);
					}
				});
			});', true);
		}

		// The quickbuttons
		foreach (Utils::$context['alerts'] as $id => $alert) {
			Utils::$context['alerts'][$id]['quickbuttons'] = [
				'delete' => [
					'label' => Lang::$txt['delete'],
					'href' => Config::$scripturl . '?action=profile;u=' . Utils::$context['id_member'] . ';area=showalerts;do=remove;aid=' . $id . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . (!empty(Utils::$context['start']) ? ';start=' . Utils::$context['start'] : ''),
					'class' => 'you_sure',
					'icon' => 'remove_button',
				],
				'mark' => [
					'label' => $alert['is_read'] != 0 ? Lang::$txt['mark_unread'] : Lang::$txt['mark_read_short'],
					'href' => Config::$scripturl . '?action=profile;u=' . Utils::$context['id_member'] . ';area=showalerts;do=' . ($alert['is_read'] != 0 ? 'unread' : 'read') . ';aid=' . $id . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . (!empty(Utils::$context['start']) ? ';start=' . Utils::$context['start'] : ''),
					'icon' => $alert['is_read'] != 0 ? 'unread_button' : 'read_button',
				],
				'view' => [
					'label' => Lang::$txt['view'],
					'href' => Config::$scripturl . '?action=profile;area=showalerts;alert=' . $id . ';',
					'icon' => 'move',
				],
				'quickmod' => [
					'class' => 'inline_mod_check',
					'content' => '<input type="checkbox" name="mark[' . $id . ']" value="' . $id . '">',
					'show' => Utils::$context['showCheckboxes'],
				],
			];
		}

		// The Delete all unread link.
		Utils::$context['alert_purge_link'] = Config::$scripturl . '?action=profile;u=' . Utils::$context['id_member'] . ';area=showalerts;do=purge;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . (!empty(Utils::$context['start']) ? ';start=' . Utils::$context['start'] : '');

		// Set a nice message.
		if (!empty($_SESSION['update_message'])) {
			Utils::$context['update_message'] = Lang::$txt['profile_updated_own'];
			unset($_SESSION['update_message']);
		}

		// Saving multiple changes?
		if (isset($_GET['save']) && !empty($_POST['mark'])) {
			// Get the values.
			$toMark = array_map('intval', (array) $_POST['mark']);

			// Which action?
			$action = !empty($_POST['mark_as']) ? Utils::htmlspecialchars(Utils::htmlTrim($_POST['mark_as'])) : '';
		}

		// A single change.
		if (!empty($_GET['do']) && !empty($_GET['aid'])) {
			$toMark = (int) $_GET['aid'];
			$action = Utils::htmlspecialchars(Utils::htmlTrim($_GET['do']));
		}
		// Delete all read alerts.
		elseif (!empty($_GET['do']) && $_GET['do'] === 'purge') {
			$action = 'purge';
		}

		// Save the changes.
		if (!empty($action) && (!empty($toMark) || $action === 'purge')) {
			User::$me->checkSession('request');

			// Call it!
			switch ($action) {
				case 'remove':
					Alert::delete($toMark, User::$me->id);
					break;

				case 'purge':
					Alert::purge(User::$me->id);
					break;

				default:
					Alert::mark(User::$me->id, $toMark, $action == 'read' ? 1 : 0);
					break;
			}

			// Set a nice update message.
			$_SESSION['update_message'] = true;

			// Redirect.
			Utils::redirectexit('action=profile;area=showalerts;u=' . User::$me->id . (!empty(Utils::$context['start']) ? ';start=' . Utils::$context['start'] : ''));
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Backward compatibility wrapper.
	 */
	public static function showAlerts(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Profile::$member)) {
			Profile::load();
		}

		// Users may only view their own alerts.
		if (!User::$me->is_owner) {
			Utils::redirectexit('action=profile;u=' . Profile::$member->id);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ShowAlerts::exportStatic')) {
	ShowAlerts::exportStatic();
}

?>