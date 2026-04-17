<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Actions\Profile;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Parser;
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\UserDataset;
use SMF\Utils;

/**
 * Show all the users buddies, as well as an add/delete interface.
 */
class BuddyIgnoreLists implements ActionInterface
{
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'buddies';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'buddies' => 'buddies',
		'ignore' => 'ignore',
	];

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subtemplates = [
		'buddies' => 'editBuddies',
		'ignore' => 'editIgnoreList',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// Do a quick check to ensure people aren't getting here illegally!
		if (!Profile::$member->is_me || empty(Config::$modSettings['enable_buddylist'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Can we email the user directly?
		Utils::$context['can_moderate_forum'] = User::$me->allowedTo('moderate_forum');

		Utils::$context['list_area'] = $this->subaction;
		Utils::$context['sub_template'] = self::$subtemplates[$this->subaction];

		// Create the tabs for the template.
		Menu::$loaded['profile']->tab_data = [
			'title' => Lang::getTxt('editBuddyIgnoreLists', file: 'Profile'),
			'description' => Lang::getTxt('buddy_ignore_desc', file: 'Profile'),
			'icon_class' => 'main_icons profile_hd',
			'tabs' => [
				'buddies' => [],
				'ignore' => [],
			],
		];

		Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');

		$call = \is_string(self::$subactions[$this->subaction]) && method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			\call_user_func($call);
		}
	}

	/**
	 * Show all the user's buddies, as well as an add/delete interface.
	 */
	public function buddies(): void
	{
		// Removing a buddy?
		if (isset($_GET['remove'])) {
			User::$me->checkSession('get');

			IntegrationHook::call('integrate_remove_buddy', [Profile::$member->id]);

			$_SESSION['prf-save'] = !\in_array(Profile::$member->buddies, (int) $_GET['remove']) ? Lang::getTxt('could_not_remove_person', file: 'Profile') : true;

			// Heh, I'm lazy, do it the easy way...
			Profile::$member->buddies = array_diff(Profile::$member->buddies, [(int) $_GET['remove']]);

			// Make the changes.
			User::updateMemberData(Profile::$member->id, ['buddy_list' => implode(',', Profile::$member->buddies)]);

			// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
			Utils::redirectexit('action=profile;area=lists;sa=buddies;u=' . Profile::$member->id);
		}

		// Adding a buddy?
		if (isset($_POST['new_buddy'])) {
			User::$me->checkSession();

			// Prepare the string for extraction...
			$_POST['new_buddy'] = strtr(Utils::htmlspecialchars($_POST['new_buddy'], ENT_QUOTES), ['&quot;' => '"']);

			preg_match_all('~"([^"]+)"~', $_POST['new_buddy'], $matches);

			$new_buddies = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_buddy']))));

			foreach ($new_buddies as $k => $dummy) {
				$new_buddies[$k] = strtr(trim($new_buddies[$k]), ['\'' => '&#039;']);

				if (\strlen($new_buddies[$k]) == 0 || \in_array($new_buddies[$k], [Profile::$member->username, Profile::$member->name])) {
					unset($new_buddies[$k]);
				}
			}

			IntegrationHook::call('integrate_add_buddies', [Profile::$member->id, &$new_buddies]);

			$_SESSION['prf-save'] = Lang::getTxt('could_not_add_person', file: 'Profile');

			if (!empty($new_buddies)) {
				// Now find out the id_member of the buddy.
				$request = Db::$db->query(
					'SELECT id_member
					FROM {db_prefix}members
					WHERE member_name IN ({array_string:new_buddies}) OR real_name IN ({array_string:new_buddies})
					LIMIT {int:count_new_buddies}',
					[
						'new_buddies' => $new_buddies,
						'count_new_buddies' => \count($new_buddies),
					],
				);

				// Add the new member to the buddies array.
				while ($row = Db::$db->fetch_assoc($request)) {
					$_SESSION['prf-save'] = true;

					if (\in_array($row['id_member'], Profile::$member->buddies)) {
						continue;
					}

					Profile::$member->buddies[] = (int) $row['id_member'];
				}
				Db::$db->free_result($request);

				// Now update the current user's buddy list.
				User::updateMemberData(Profile::$member->id, ['buddy_list' => implode(',', Profile::$member->buddies)]);
			}

			// Back to the buddy list!
			Utils::redirectexit('action=profile;area=lists;sa=buddies;u=' . Profile::$member->id);
		}

		// Gotta load the custom profile fields names.
		Utils::$context['custom_pf'] = [];

		$disabled_fields = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : [];

		$request = Db::$db->query(
			'SELECT col_name, field_name, field_desc, field_type, field_options, show_mlist, bbc, enclose
			FROM {db_prefix}custom_fields
			WHERE active = {int:active}
				AND private < {int:private_level}',
			[
				'active' => 1,
				'private_level' => 2,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset($disabled_fields[$row['col_name']]) && !empty($row['show_mlist'])) {
				Utils::$context['custom_pf'][$row['col_name']] = [
					'label' => Lang::tokenTxtReplace($row['field_name']),
					'type' => $row['field_type'],
					'options' => !empty($row['field_options']) ? explode(',', $row['field_options']) : [],
					'bbc' => !empty($row['bbc']),
					'enclose' => $row['enclose'],
				];
			}
		}
		Db::$db->free_result($request);

		// Get all the user's "buddies"...
		$buddies = User::loadCustom(
			[
				'where' => ['mem.id_member IN ({array_int:buddy_list})'],
				'order' => ['mem.real_name'],
				'limit' => \count(Profile::$member->buddies),
				'params' => [
					'buddy_list' => Profile::$member->buddies,
				],
			],
			UserDataset::Profile,
		);

		Utils::$context['buddy_count'] = \count($buddies);

		// Setup the context for each buddy.
		Utils::$context['buddies'] = [];

		foreach ($buddies as $buddy) {
			Utils::$context['buddies'][$buddy->id] = $buddy->format();

			// Make sure to load the appropriate fields for each user
			if (!empty(Utils::$context['custom_pf'])) {
				foreach (Utils::$context['custom_pf'] as $key => $column) {
					// Don't show anything if there isn't anything to show.
					if (!isset(Utils::$context['buddies'][$buddy->id]['options'][$key])) {
						Utils::$context['buddies'][$buddy->id]['options'][$key] = '';

						continue;
					}

					$currentKey = 0;

					if (!empty($column['options'])) {
						foreach ($column['options'] as $k => $v) {
							if (empty($currentKey)) {
								$currentKey = $v == Utils::$context['buddies'][$buddy->id]['options'][$key] ? $k : 0;
							}
						}
					}

					if ($column['bbc'] && !empty(Utils::$context['buddies'][$buddy->id]['options'][$key])) {
						Utils::$context['buddies'][$buddy->id]['options'][$key] = Parser::transform(
							string: Utils::$context['buddies'][$buddy->id]['options'][$key],
							output_type: Parser::OUTPUT_TEXT,
							options: ['hard_breaks' => 0],
						);
					} elseif ($column['type'] == 'check') {
						Utils::$context['buddies'][$buddy->id]['options'][$key] = Lang::getTxt(Utils::$context['buddies'][$buddy->id]['options'][$key] == 0 ? 'no' : 'yes', file: 'General');
					}

					// Enclosing the user input within some other text?
					if (!empty($column['enclose']) && !empty(Utils::$context['buddies'][$buddy->id]['options'][$key])) {
						Utils::$context['buddies'][$buddy->id]['options'][$key] = strtr($column['enclose'], [
							'{SCRIPTURL}' => Config::$scripturl,
							'{IMAGES_URL}' => Theme::$current->settings['images_url'],
							'{DEFAULT_IMAGES_URL}' => Theme::$current->settings['default_images_url'],
							'{KEY}' => $currentKey,
							'{INPUT}' => Lang::tokenTxtReplace(Utils::$context['buddies'][$buddy->id]['options'][$key]),
						]);
					}
				}
			}
		}

		if (isset($_SESSION['prf-save'])) {
			if ($_SESSION['prf-save'] === true) {
				Utils::$context['saved_successful'] = true;
			} else {
				Utils::$context['saved_failed'] = $_SESSION['prf-save'];
			}

			unset($_SESSION['prf-save']);
		}

		IntegrationHook::call('integrate_view_buddies', [Profile::$member->id]);
	}

	/**
	 * Allows the user to view their ignore list, as well as the option to manage members on it.
	 */
	public function ignore(): void
	{
		// Removing a member from the ignore list?
		if (isset($_GET['remove'])) {
			User::$me->checkSession('get');

			$_SESSION['prf-save'] = !\in_array(Profile::$member->ignoreusers, (int) $_GET['remove']) ? Lang::getTxt('could_not_remove_person', file: 'Profile') : true;

			// Heh, I'm lazy, do it the easy way...
			Profile::$member->ignoreusers = array_diff(Profile::$member->ignoreusers, [(int) $_GET['remove']]);

			// Make the changes.
			User::updateMemberData(Profile::$member->id, ['pm_ignore_list' => implode(',', Profile::$member->ignoreusers)]);

			// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
			Utils::redirectexit('action=profile;area=lists;sa=ignore;u=' . Profile::$member->id);
		}

		// Adding a member to the ignore list?
		if (isset($_POST['new_ignore'])) {
			User::$me->checkSession();

			// Prepare the string for extraction...
			$_POST['new_ignore'] = strtr(Utils::htmlspecialchars($_POST['new_ignore'], ENT_QUOTES), ['&quot;' => '"']);

			preg_match_all('~"([^"]+)"~', $_POST['new_ignore'], $matches);

			$new_entries = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_ignore']))));

			foreach ($new_entries as $k => $dummy) {
				$new_entries[$k] = strtr(trim($new_entries[$k]), ['\'' => '&#039;']);

				if (\strlen($new_entries[$k]) == 0 || \in_array($new_entries[$k], [Profile::$member->data['member_name'], Profile::$member->data['real_name']])) {
					unset($new_entries[$k]);
				}
			}

			$_SESSION['prf-save'] = Lang::getTxt('could_not_add_person', file: 'Profile');

			if (!empty($new_entries)) {
				// Now find out the id_member for the members in question.
				$request = Db::$db->query(
					'SELECT id_member
					FROM {db_prefix}members
					WHERE member_name IN ({array_string:new_entries}) OR real_name IN ({array_string:new_entries})
					LIMIT {int:count_new_entries}',
					[
						'new_entries' => $new_entries,
						'count_new_entries' => \count($new_entries),
					],
				);

				// Add the new member to the ignored array.
				while ($row = Db::$db->fetch_assoc($request)) {
					$_SESSION['prf-save'] = true;

					if (\in_array($row['id_member'], Profile::$member->ignoreusers)) {
						continue;
					}

					Profile::$member->ignoreusers[] = (int) $row['id_member'];
				}
				Db::$db->free_result($request);

				// Now update the current user's ignored list.
				User::updateMemberData(Profile::$member->id, ['pm_ignore_list' => implode(',', Profile::$member->ignoreusers)]);
			}

			// Back to the list of pitiful people!
			Utils::redirectexit('action=profile;area=lists;sa=ignore;u=' . Profile::$member->id);
		}

		// Initialise the list of members we're ignoring.
		$ignored = User::loadCustom(
			[
				'where' => ['mem.id_member IN ({array_int:ignore_list})'],
				'order' => ['mem.real_name'],
				'limit' => \count(Profile::$member->ignoreusers),
				'params' => [
					'ignore_list' => Profile::$member->ignoreusers,
				],
			],
			UserDataset::Profile,
		);

		// Setup the context for each ignored member.
		Utils::$context['ignore_list'] = [];

		foreach ($ignored as $ignore_member) {
			Utils::$context['ignore_list'][$ignore_member->id] = $ignore_member->format();
		}

		if (isset($_SESSION['prf-save'])) {
			if ($_SESSION['prf-save'] === true) {
				Utils::$context['saved_successful'] = true;
			} else {
				Utils::$context['saved_failed'] = $_SESSION['prf-save'];
			}

			unset($_SESSION['prf-save']);
		}
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
			Profile::loadMember();
		}

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}
}
