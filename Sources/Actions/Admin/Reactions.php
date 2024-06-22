<?php

/**
 * This file takes care of managing reactions
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types = 1);

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\ReactionTrait;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This class handles everything related to managing reactions.
 */
class Reactions implements ActionInterface
{

	use ActionTrait;
	use ReactionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 * Our default subaction
	 */
	public string $subaction = 'settings';


	/**************************
	 * Public static properties
	 **************************/

	/** @var array
	 * Available subactions
	 */
	public static array $subactions = [
		'edit' => 'editreactions',
		'settings' => 'settings',
	];

	/***********************
	 * Public methods
	 ***********************/

	/**
	 * Handles modifying reactions settings
	 */
	public static function settings(): void
	{
		$config_vars = self::getConfigVars();

		// Setup the basics of the settings template.
		Utils::$context['sub_template'] = 'show_settings';

		// Finish up the form...
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=reactions;save;sa=settings';
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets the configuration variables for the settings sub-action.
	 *
	 * @return array $config_vars for the settings sub-action.
	 */
	public static function getConfigVars(): array
	{
		$config_vars = [
			['check', 'enable_reacts'],
			['permissions', 'reactions_react'],
		];

		IntegrationHook::call('integrate_reactions_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Handle adding, deleting and editing reactions
	 */
	public function editreactions(): void
	{
		// Make sure we select the right menu item
		Menu::$loaded['admin']['currentsubsection'] = 'editreactions';

		// Get the reactions. If we're updating things then we'll overwrite this later
		$reactions = $this->getReactions();

		// They must have submitted a form.
		if (isset($_POST['react_save']) || isset($_POST['react_delete'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-mss', 'request');

			// This will indicate whether we need to update the reactions cache later...
			$do_update = false;

			// Anything to delete?
			if (isset($_POST['react_delete']) && isset($_POST['delete_reacts'])) {
				$do_update = true;
				$deleted = [];

				foreach($_POST['delete_reacts'] as $to_delete) {
					$deleted[] = (int) $to_delete;
				}

				// Now to do the actual deleting
				Db::$db->query('
					DELETE FROM {db_pref}reactions
					WHERE id_react IN ({array_int:deleted})',
					[
						'deleted' => $deleted,
					]
				);

				// Are there any posts that used these reactions?
				$get_reacted_posts = Db::$db->query('
					SELECT id_msg, COUNT (id_react) AS num_reacts
					FROM {db_pref}reactions
					GROUP BY id_msg
					WHERE id_react IN ({array_int:deleted})',
					[
						'deleted' => $deleted,
					]
				);

				// Update the number of reactions for the affected post(s)
				// Did we find anything?
				if (Db::$db->num_rows($get_reacted_posts) > 0) {
					while ($reacted_post = $get_reacted_posts->fetchAssoc()) {
						Db::$db->query('
						UPDATE {db_prefix}messages
						SET reactions = reactions-{int:deleted}
						WHERE id_msg = {int:msg}',
							[
								'deleted' => $reacted_post['num_reacts'],
								'msg' => $reacted_post['id_msg'],
							]
						);
					}
				}
			}
			// Updating things?
			elseif (isset($_POST['reacts'])) {
				// Adding things?
				if (isset($_POST['react_add'])) {
					foreach($_POST['react_add'] as $new_react)
					{
						// No funny stuff now..
						$new_react = trim($new_react);
						if (!empty($new_react)) {
							$add[] = $new_react;
						}
					}

					if (!empty($add)) {
						$do_update = true;

						// Insert the new reactions
						Db::$db->insert('insert', '{db_pref}reactions', ['name'], $add, []);
					}
				}

				// Updating things...
				$updates = [];
				foreach($_POST['reacts'] as $id => $name) {
					// Again, no funny stuff...
					$name = trim($name);

					// Did they update this one? Ignore empty ones for now
					if ($reactions[$id] != $name && !empty($name)) {
						$updates[$id] = $name;
					}
				}

				// Anything to update?
				if (!empty($updates)) {
					$do_update = true;
					// Do the update
					Db::$db->insert('replace', ['id_react, name'], $updates, 'id_react');
				}
			}

			// If we updated anything, re-cache everything
			if ($do_update) {
				// Re-cache the reactions and update the reactions variable so the form will show the changes
				CacheApi::put('reactions', null);
				$reactions = $this->getReactions();
				CacheApi::put('reactions', $reactions, 480);
			}
		}

		// Set up the form now...

		// Create our token
		SecurityToken::create('admin-mr', 'request');

		// Set up our list. Use a special function for the get_items so we can output things in input fields...
		$listOptions = [
			'id' => 'reactions_list',
			'title' => Lang::$txt['reactions'],
			'no_items_label' => Lang::$txt['no_reactions'],
			'base_href' => Config::$scripturl . '?action=admin;area=reactions;sa=edit',
			'get_items' => [
				'function' => function(int $start, int $items_per_page, string $sort_by, array $params) use ($reactions) : array {
					$items = [];
					foreach ($reactions as $id => $name) {
						$items[] = [
							'name' => '<input type="text" name="reacts[' . $id . ']" value="' . $name . '">',
							'check' => '<input type="check" name="delete_reacts[]" value="' . $id . '">',
						];
					}
					return $items;
				},
			],
			'get_count' => [
				'value' => count($reactions),
			],
			'columns' =>
			[
				'name' =>
				[
					'header' => [
						'value' => Lang::$txt['reactions_name'],
					]
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
				]
			]
		];

		// The column for deleting things
		$listOptions['columns']['remove'] = [
			'header' => [
				'value' => Lang::$txt['reacts_delete'],
				'style' => 'width:3%',
			],
			'data' => [
				'function' => function () use ($reactions) {
					$checks = [];
					foreach(array_keys($reactions) as $id) {
						$checks[] = '<input type="check" name="delete_reacts[]" value="' . $id . '">';
					}
					return $checks;
				},
			],
			'class' => 'centertext',
		];

		// Add a row for a blank field to add a reaction, and a link to add another blank field.
		$listOptions['additional_rows'][] = [
			[
				'position' => 'bottom_of_list',
				'data' => [
					'value' => '<input type="text" name="reacts_add[]">'
				]
			],
			[
				// Clicking this magic link adds a new row...
				'position' => 'bottom_of_list',
				'data' => [
					'value' => '<button type="button" onclick="addrow()" value="' . Lang::$txt['reacts_add'] . '">'
				]
			],
			[
				// And last but not least our buttons
				'position' => 'below_table_data',
				'data' => [
					'value' => '<input type="submit" name="reacts_save" value="' . Lang::$txt['reacts_save'] . '" class="button">
								<input type="submit" name="reacts_delete" value="' . Lang::$txt['reacts_delete'] . '" data-confirm="' . Lang::$txt['reacts_delete_confirm'] . '" class="button you_sure>'
				]
			]
		];

		// And some inline JS to handle adding another row
		$listOptions['javascript'] = [
			'
			function addrow() {
				reacts_table = document.getElementById(\'reactions_list\');
				new_row = document.getElementById(\'reactions_list\').insertRow(reacts_table.rows.length - 1);
				new_row.insertCell(0).innerHTML = \'<input type="text" name="reacts_add[]">\';
				new_row.insertCell(1).innerHTML = \'\';
			}',
		];

		// Now that we have our list options set up, have some fun...
		$listOptions['form'] = [
			'href' => Config::$scripturl . '?action=admin;area=react;sa=edit;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			'name' => 'list_reactions',
		];

		new ItemList($listOptions);

		Utils::$context['page_title'] = Lang::$txt['reactions_manage'];
		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'list_reactions';
	}
}
?>