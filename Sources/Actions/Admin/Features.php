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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\Profile\Notification;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Graphics\Image;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Profile;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\TimeZone;
use SMF\User;
use SMF\Utils;

/**
 * Class to manage various core features.
 */
class Features implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ModifyFeatureSettings',
			'list_getProfileFields' => 'list_getProfileFields',
			'list_getProfileFieldSize' => 'list_getProfileFieldSize',
			'modifyBasicSettings' => 'ModifyBasicSettings',
			'modifyBBCSettings' => 'ModifyBBCSettings',
			'modifyLayoutSettings' => 'ModifyLayoutSettings',
			'modifySignatureSettings' => 'ModifySignatureSettings',
			'showCustomProfiles' => 'ShowCustomProfiles',
			'editCustomProfiles' => 'EditCustomProfiles',
			'modifyLikesSettings' => 'ModifyLikesSettings',
			'modifyMentionsSettings' => 'ModifyMentionsSettings',
			'modifyAlertsSettings' => 'ModifyAlertsSettings',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'basic';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'basic' => 'basic',
		'bbc' => 'bbc',
		'layout' => 'layout',
		'sig' => 'signature',
		'profile' => 'profile',
		'profileedit' => 'profileEdit',
		'likes' => 'likes',
		'mentions' => 'mentions',
		'alerts' => 'alerts',
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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// You need to be an admin to edit settings!
		User::$me->isAllowedTo('admin_forum');

		Utils::$context['sub_template'] = 'show_settings';
		Utils::$context['sub_action'] = $this->subaction;

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Config array for changing the basic forum settings
	 *
	 * Accessed via ?action=admin;area=featuresettings;sa=basic
	 */
	public function basic(): void
	{
		$config_vars = self::basicConfigVars();

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Make sure the country codes are valid.
			if (!empty($_POST['timezone_priority_countries'])) {
				$_POST['timezone_priority_countries'] = TimeZone::validateIsoCountryCodes($_POST['timezone_priority_countries'], true);
			}

			// Prevent absurd boundaries here - make it a day tops.
			if (isset($_POST['lastActive'])) {
				$_POST['lastActive'] = min((int) $_POST['lastActive'], 1440);
			}

			IntegrationHook::call('integrate_save_basic_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;

			// Do a bit of housekeeping
			if (empty($_POST['minimize_files']) || $_POST['minimize_files'] != Config::$modSettings['minimize_files']) {
				Theme::deleteAllMinified();
			}

			User::$me->logOnline();
			Utils::redirectexit('action=admin;area=featuresettings;sa=basic');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=featuresettings;save;sa=basic';
		Utils::$context['settings_title'] = Lang::$txt['mods_cat_features'];

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Set a few Bulletin Board Code settings.
	 * It loads a list of Bulletin Board Code tags to allow disabling tags.
	 *
	 * Requires the admin_forum permission.
	 * Accessed via ?action=admin;area=featuresettings;sa=bbc.
	 * @uses template_show_settings()
	 */
	public function bbc(): void
	{
		$config_vars = self::bbcConfigVars();

		// Setup the template.
		Utils::$context['sub_template'] = 'show_settings';
		Utils::$context['page_title'] = Lang::$txt['manageposts_bbc_settings_title'];

		// Make sure we check the right tags!
		Config::$modSettings['bbc_disabled_disabledBBC'] = empty(Config::$modSettings['disabledBBC']) ? [] : explode(',', Config::$modSettings['disabledBBC']);

		// Legacy BBC are listed separately, but we use the same info in both cases
		Config::$modSettings['bbc_disabled_legacyBBC'] = Config::$modSettings['bbc_disabled_disabledBBC'];

		$extra = '';

		if (isset($_REQUEST['cowsay'])) {
			$config_vars[] = ['permissions', 'bbc_cowsay', 'text_label' => sprintf(Lang::$txt['groups_can_use'], '[cowsay]')];
			$extra = ';cowsay';
		}

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Clean up the tags.
			$bbcTags = [];
			$bbcTagsChildren = [];

			foreach (BBCodeParser::getCodes() as $tag) {
				$bbcTags[] = $tag['tag'];

				if (isset($tag['require_children'])) {
					$bbcTagsChildren[$tag['tag']] = !isset($bbcTagsChildren[$tag['tag']]) ? $tag['require_children'] : array_unique(array_merge($bbcTagsChildren[$tag['tag']], $tag['require_children']));
				}
			}

			// Clean up tags with children
			foreach($bbcTagsChildren as $parent_tag => $children) {
				foreach($children as $index => $child_tag) {
					// Remove entries where parent and child tag is the same
					if ($child_tag == $parent_tag) {
						unset($bbcTagsChildren[$parent_tag][$index]);

						continue;
					}

					// Combine chains of tags
					if (isset($bbcTagsChildren[$child_tag])) {
						$bbcTagsChildren[$parent_tag] = array_merge($bbcTagsChildren[$parent_tag], $bbcTagsChildren[$child_tag]);
						unset($bbcTagsChildren[$child_tag]);
					}
				}
			}

			if (!isset($_POST['disabledBBC_enabledTags'])) {
				$_POST['disabledBBC_enabledTags'] = [];
			} elseif (!is_array($_POST['disabledBBC_enabledTags'])) {
				$_POST['disabledBBC_enabledTags'] = [$_POST['disabledBBC_enabledTags']];
			}

			if (!isset($_POST['legacyBBC_enabledTags'])) {
				$_POST['legacyBBC_enabledTags'] = [];
			} elseif (!is_array($_POST['legacyBBC_enabledTags'])) {
				$_POST['legacyBBC_enabledTags'] = [$_POST['legacyBBC_enabledTags']];
			}

			$_POST['disabledBBC_enabledTags'] = array_unique(array_merge($_POST['disabledBBC_enabledTags'], $_POST['legacyBBC_enabledTags']));

			// Enable all children if parent is enabled
			foreach ($bbcTagsChildren as $tag => $children) {
				if (in_array($tag, $_POST['disabledBBC_enabledTags'])) {
					$_POST['disabledBBC_enabledTags'] = array_merge($_POST['disabledBBC_enabledTags'], $children);
				}
			}

			// Work out what is actually disabled!
			$_POST['disabledBBC'] = implode(',', array_diff($bbcTags, $_POST['disabledBBC_enabledTags']));

			// Config::$modSettings['legacyBBC'] isn't really a thing...
			unset($_POST['legacyBBC_enabledTags']);
			$config_vars = array_filter(
				$config_vars,
				function ($config_var) {
					return !isset($config_var[1]) || $config_var[1] != 'legacyBBC';
				},
			);

			IntegrationHook::call('integrate_save_bbc_settings', [$bbcTags]);

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=featuresettings;sa=bbc' . $extra);
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=featuresettings;save;sa=bbc' . $extra;
		Utils::$context['settings_title'] = Lang::$txt['manageposts_bbc_settings_title'];

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Allows modifying the global layout settings in the forum.
	 *
	 * Accessed via ?action=admin;area=featuresettings;sa=layout
	 */
	public function layout(): void
	{
		$config_vars = self::layoutConfigVars();

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_layout_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			User::$me->logOnline();

			Utils::redirectexit('action=admin;area=featuresettings;sa=layout');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=featuresettings;save;sa=layout';
		Utils::$context['settings_title'] = Lang::$txt['mods_cat_layout'];

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Handles signature settings.
	 *
	 * Optionally allows the admin to impose those settings on existing members.
	 */
	public function signature(): void
	{
		$config_vars = self::sigConfigVars();

		// Setup the template.
		Utils::$context['page_title'] = Lang::$txt['signature_settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Disable the max smileys option if we don't allow smileys at all!
		Utils::$context['settings_post_javascript'] = 'document.getElementById(\'signature_max_smileys\').disabled = !document.getElementById(\'signature_allow_smileys\').checked;';

		// Load all the signature settings.
		list($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);
		$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : [];

		// Applying to ALL signatures?!!
		if (isset($_GET['apply'])) {
			// Security!
			User::$me->checkSession('get');

			Utils::$context['sig_start'] = time();
			// This is horrid - but I suppose some people will want the option to do it.
			$_GET['step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;
			$done = false;

			$request = Db::$db->query(
				'',
				'SELECT MAX(id_member)
				FROM {db_prefix}members',
				[
				],
			);
			list(Utils::$context['max_member']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			while (!$done) {
				$changes = [];

				$request = Db::$db->query(
					'',
					'SELECT id_member, signature
					FROM {db_prefix}members
					WHERE id_member BETWEEN {int:step} AND {int:step} + 49
						AND id_group != {int:admin_group}
						AND FIND_IN_SET({int:admin_group}, additional_groups) = 0',
					[
						'admin_group' => 1,
						'step' => $_GET['step'],
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					// Apply all the rules we can realistically do.
					$sig = strtr($row['signature'], ['<br>' => "\n"]);

					// Max characters...
					if (!empty($sig_limits[1])) {
						$sig = Utils::entitySubstr($sig, 0, $sig_limits[1]);
					}

					// Max lines...
					if (!empty($sig_limits[2])) {
						$count = 0;

						for ($i = 0; $i < strlen($sig); $i++) {
							if ($sig[$i] == "\n") {
								$count++;

								if ($count >= $sig_limits[2]) {
									$sig = substr($sig, 0, $i) . strtr(substr($sig, $i), ["\n" => ' ']);
								}
							}
						}
					}

					if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)~i', $sig, $matches) !== false && isset($matches[2])) {
						foreach ($matches[1] as $ind => $size) {
							$limit_broke = 0;

							// Attempt to allow all sizes of abuse, so to speak.
							if ($matches[2][$ind] == 'px' && $size > $sig_limits[7]) {
								$limit_broke = $sig_limits[7] . 'px';
							} elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75)) {
								$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
							} elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16)) {
								$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
							} elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18) {
								$limit_broke = 'large';
							}

							if ($limit_broke) {
								$sig = str_replace($matches[0][$ind], '[size=' . $sig_limits[7] . 'px', $sig);
							}
						}
					}

					// Stupid images - this is stupidly, stupidly challenging.
					if ((!empty($sig_limits[3]) || !empty($sig_limits[5]) || !empty($sig_limits[6]))) {
						$replaces = [];
						$img_count = 0;

						// Get all BBC tags...
						preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br>)*([^<">]+?)(?:<br>)*\[/img\]~i', $sig, $matches);

						// ... and all HTML ones.
						preg_match_all('~&lt;img\s+src=(?:&quot;)?((?:http://|ftp://|https://|ftps://).+?)(?:&quot;)?(?:\s+alt=(?:&quot;)?(.*?)(?:&quot;)?)?(?:\s?/)?&gt;~i', $sig, $matches2, PREG_PATTERN_ORDER);

						// And stick the HTML in the BBC.
						if (!empty($matches2)) {
							foreach ($matches2[0] as $ind => $dummy) {
								$matches[0][] = $matches2[0][$ind];
								$matches[1][] = '';
								$matches[2][] = '';
								$matches[3][] = '';
								$matches[4][] = '';
								$matches[5][] = '';
								$matches[6][] = '';
								$matches[7][] = $matches2[1][$ind];
							}
						}

						// Try to find all the images!
						if (!empty($matches)) {
							$image_count_holder = [];

							foreach ($matches[0] as $key => $image) {
								$width = -1;
								$height = -1;
								$img_count++;

								// Too many images?
								if (!empty($sig_limits[3]) && $img_count > $sig_limits[3]) {
									// If we've already had this before we only want to remove the excess.
									if (isset($image_count_holder[$image])) {
										$img_offset = -1;
										$rep_img_count = 0;

										while ($img_offset !== false) {
											$img_offset = strpos($sig, $image, $img_offset + 1);
											$rep_img_count++;

											if ($rep_img_count > $image_count_holder[$image]) {
												// Only replace the excess.
												$sig = substr($sig, 0, $img_offset) . str_replace($image, '', substr($sig, $img_offset));

												// Stop looping.
												$img_offset = false;
											}
										}
									} else {
										$replaces[$image] = '';
									}

									continue;
								}

								// Does it have predefined restraints? Width first.
								if ($matches[6][$key]) {
									$matches[2][$key] = $matches[6][$key];
								}

								if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5]) {
									$width = $sig_limits[5];
									$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
								} elseif ($matches[2][$key]) {
									$width = $matches[2][$key];
								}

								// ... and height.
								if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6]) {
									$height = $sig_limits[6];

									if ($width != -1) {
										$width = $width * ($height / $matches[4][$key]);
									}
								} elseif ($matches[4][$key]) {
									$height = $matches[4][$key];
								}

								// If the dimensions are still not fixed - we need to check the actual image.
								if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6])) {
									$sizes = Image::getSizeExternal($matches[7][$key]);

									if (is_array($sizes)) {
										// Too wide?
										if ($sizes[0] > $sig_limits[5] && $sig_limits[5]) {
											$width = $sig_limits[5];
											$sizes[1] = $sizes[1] * ($width / $sizes[0]);
										}

										// Too high?
										if ($sizes[1] > $sig_limits[6] && $sig_limits[6]) {
											$height = $sig_limits[6];

											if ($width == -1) {
												$width = $sizes[0];
											}

											$width = $width * ($height / $sizes[1]);
										} elseif ($width != -1) {
											$height = $sizes[1];
										}
									}
								}

								// Did we come up with some changes? If so remake the string.
								if ($width != -1 || $height != -1) {
									$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
								}

								// Record that we got one.
								$image_count_holder[$image] = isset($image_count_holder[$image]) ? $image_count_holder[$image] + 1 : 1;
							}

							if (!empty($replaces)) {
								$sig = str_replace(array_keys($replaces), array_values($replaces), $sig);
							}
						}
					}

					// Try to fix disabled tags.
					if (!empty($disabledTags)) {
						$sig = preg_replace('~\[(?:' . implode('|', $disabledTags) . ').+?\]~i', '', $sig);

						$sig = preg_replace('~\[/(?:' . implode('|', $disabledTags) . ')\]~i', '', $sig);
					}

					$sig = strtr($sig, ["\n" => '<br>']);

					IntegrationHook::call('integrate_apply_signature_settings', [&$sig, $sig_limits, $disabledTags]);

					if ($sig != $row['signature']) {
						$changes[$row['id_member']] = $sig;
					}
				}

				if (Db::$db->num_rows($request) == 0) {
					$done = true;
				}

				Db::$db->free_result($request);

				// Do we need to delete what we have?
				if (!empty($changes)) {
					foreach ($changes as $id => $sig) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}members
							SET signature = {string:signature}
							WHERE id_member = {int:id_member}',
							[
								'id_member' => $id,
								'signature' => $sig,
							],
						);
					}
				}

				$_GET['step'] += 50;

				if (!$done) {
					$this->pauseSignatureApplySettings();
				}
			}

			$settings_applied = true;
		}

		Utils::$context['signature_settings'] = [
			'enable' => $sig_limits[0] ?? 0,
			'max_length' => $sig_limits[1] ?? 0,
			'max_lines' => $sig_limits[2] ?? 0,
			'max_images' => $sig_limits[3] ?? 0,
			'allow_smileys' => isset($sig_limits[4]) && $sig_limits[4] == -1 ? 0 : 1,
			'max_smileys' => isset($sig_limits[4]) && $sig_limits[4] != -1 ? $sig_limits[4] : 0,
			'max_image_width' => $sig_limits[5] ?? 0,
			'max_image_height' => $sig_limits[6] ?? 0,
			'max_font_size' => $sig_limits[7] ?? 0,
		];

		// Temporarily make each setting a modSetting!
		foreach (Utils::$context['signature_settings'] as $key => $value) {
			Config::$modSettings['signature_' . $key] = $value;
		}

		// Make sure we check the right tags!
		Config::$modSettings['bbc_disabled_signature_bbc'] = $disabledTags;

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Clean up the tag stuff!
			$bbcTags = [];

			foreach (BBCodeParser::getCodes() as $tag) {
				$bbcTags[] = $tag['tag'];
			}

			if (!isset($_POST['signature_bbc_enabledTags'])) {
				$_POST['signature_bbc_enabledTags'] = [];
			} elseif (!is_array($_POST['signature_bbc_enabledTags'])) {
				$_POST['signature_bbc_enabledTags'] = [$_POST['signature_bbc_enabledTags']];
			}

			$sig_limits = [];

			foreach (Utils::$context['signature_settings'] as $key => $value) {
				if ($key == 'allow_smileys') {
					continue;
				}

				if ($key == 'max_smileys' && empty($_POST['signature_allow_smileys'])) {
					$sig_limits[] = -1;
				} else {
					$sig_limits[] = !empty($_POST['signature_' . $key]) ? max(1, (int) $_POST['signature_' . $key]) : 0;
				}
			}

			IntegrationHook::call('integrate_save_signature_settings', [&$sig_limits, &$bbcTags]);

			$_POST['signature_settings'] = implode(',', $sig_limits) . ':' . implode(',', array_diff($bbcTags, $_POST['signature_bbc_enabledTags']));

			// Even though we have practically no settings let's keep the convention going!
			$save_vars = [];
			$save_vars[] = ['text', 'signature_settings'];

			ACP::saveDBSettings($save_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=featuresettings;sa=sig');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=featuresettings;save;sa=sig';
		Utils::$context['settings_title'] = Lang::$txt['signature_settings'];

		if (!empty($settings_applied)) {
			Utils::$context['settings_message'] = [
				'label' => Lang::$txt['signature_settings_applied'],
				'tag' => 'div',
				'class' => 'infobox',
			];
		} else {
			Utils::$context['settings_message'] = [
				'label' => sprintf(Lang::$txt['signature_settings_warning'], Utils::$context['session_id'], Utils::$context['session_var'], Config::$scripturl),
				'tag' => 'div',
				'class' => 'centertext',
			];
		}

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Show all the custom profile fields available to the user.
	 */
	public function profile(): void
	{
		Utils::$context['page_title'] = Lang::$txt['custom_profile_title'];
		Utils::$context['sub_template'] = 'show_custom_profile';

		// What about standard fields they can tweak?
		$standard_fields = ['website', 'personal_text', 'timezone', 'posts', 'warning_status'];
		// What fields can't you put on the registration page?
		Utils::$context['fields_no_registration'] = ['posts', 'warning_status'];

		// Are we saving any standard field changes?
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-scp');

			// Do the active ones first.
			$disable_fields = array_flip($standard_fields);

			if (!empty($_POST['active'])) {
				foreach ($_POST['active'] as $value) {
					unset($disable_fields[$value]);
				}
			}

			// What we have left!
			$changes['disabled_profile_fields'] = empty($disable_fields) ? '' : implode(',', array_keys($disable_fields));

			// Things we want to show on registration?
			$reg_fields = [];

			if (!empty($_POST['reg'])) {
				foreach ($_POST['reg'] as $value) {
					if (in_array($value, $standard_fields) && !isset($disable_fields[$value])) {
						$reg_fields[] = $value;
					}
				}
			}

			// What we have left!
			$changes['registration_fields'] = empty($reg_fields) ? '' : implode(',', $reg_fields);

			$_SESSION['adm-save'] = true;

			if (!empty($changes)) {
				Config::updateModSettings($changes);
			}
		}

		SecurityToken::create('admin-scp');

		// Need to know the max order for custom fields
		Utils::$context['custFieldsMaxOrder'] = $this->custFieldsMaxOrder();

		$listOptions = [
			'id' => 'standard_profile_fields',
			'title' => Lang::$txt['standard_profile_title'],
			'base_href' => Config::$scripturl . '?action=admin;area=featuresettings;sa=profile',
			'get_items' => [
				'function' => __CLASS__ . '::list_getProfileFields',
				'params' => [
					true,
				],
			],
			'columns' => [
				'field' => [
					'header' => [
						'value' => Lang::$txt['standard_profile_field'],
					],
					'data' => [
						'db' => 'label',
						'style' => 'width: 60%;',
					],
				],
				'active' => [
					'header' => [
						'value' => Lang::$txt['custom_edit_active'],
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($rowData) {
							$isChecked = $rowData['disabled'] ? '' : ' checked';
							$onClickHandler = $rowData['can_show_register'] ? sprintf(' onclick="document.getElementById(\'reg_%1$s\').disabled = !this.checked;"', $rowData['id']) : '';

							return sprintf('<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s" %2$s%3$s>', $rowData['id'], $isChecked, $onClickHandler);
						},
						'style' => 'width: 20%;',
						'class' => 'centercol',
					],
				],
				'show_on_registration' => [
					'header' => [
						'value' => Lang::$txt['custom_edit_registration'],
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($rowData) {
							$isChecked = $rowData['on_register'] && !$rowData['disabled'] ? ' checked' : '';
							$isDisabled = $rowData['can_show_register'] ? '' : ' disabled';

							return sprintf('<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s" %2$s%3$s>', $rowData['id'], $isChecked, $isDisabled);
						},
						'style' => 'width: 20%;',
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=featuresettings;sa=profile',
				'name' => 'standardProfileFields',
				'token' => 'admin-scp',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="save" value="' . Lang::$txt['save'] . '" class="button">',
				],
			],
		];
		new ItemList($listOptions);

		$listOptions = [
			'id' => 'custom_profile_fields',
			'title' => Lang::$txt['custom_profile_title'],
			'base_href' => Config::$scripturl . '?action=admin;area=featuresettings;sa=profile',
			'default_sort_col' => 'field_order',
			'no_items_label' => Lang::$txt['custom_profile_none'],
			'items_per_page' => 25,
			'get_items' => [
				'function' => __CLASS__ . '::list_getProfileFields',
				'params' => [
					false,
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getProfileFieldSize',
			],
			'columns' => [
				'field_order' => [
					'header' => [
						'value' => Lang::$txt['custom_profile_fieldorder'],
					],
					'data' => [
						'function' => function ($rowData) {
							$return = '<p class="centertext bold_text">';

							if ($rowData['field_order'] > 1) {
								$return .= '<a href="' . Config::$scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $rowData['id_field'] . ';move=up"><span class="toggle_up" title="' . Lang::$txt['custom_edit_order_move'] . ' ' . Lang::$txt['custom_edit_order_up'] . '"></span></a>';
							}

							if ($rowData['field_order'] < Utils::$context['custFieldsMaxOrder']) {
								$return .= '<a href="' . Config::$scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $rowData['id_field'] . ';move=down"><span class="toggle_down" title="' . Lang::$txt['custom_edit_order_move'] . ' ' . Lang::$txt['custom_edit_order_down'] . '"></span></a>';
							}

							$return .= '</p>';

							return $return;
						},
						'style' => 'width: 12%;',
					],
					'sort' => [
						'default' => 'field_order',
						'reverse' => 'field_order DESC',
					],
				],
				'field_name' => [
					'header' => [
						'value' => Lang::$txt['custom_profile_fieldname'],
					],
					'data' => [
						'function' => function ($rowData) {
							$field_name = Lang::tokenTxtReplace($rowData['field_name']);
							$field_desc = Lang::tokenTxtReplace($rowData['field_desc']);

							return sprintf(
								'<a href="%1$s?action=admin;area=featuresettings;sa=profileedit;fid=%2$d">%3$s</a><div class="smalltext">%4$s</div>',
								Config::$scripturl,
								$rowData['id_field'],
								$field_name,
								$field_desc,
							);
						},
						'style' => 'width: 62%;',
					],
					'sort' => [
						'default' => 'field_name',
						'reverse' => 'field_name DESC',
					],
				],
				'field_type' => [
					'header' => [
						'value' => Lang::$txt['custom_profile_fieldtype'],
					],
					'data' => [
						'function' => function ($rowData) {
							$textKey = sprintf('custom_profile_type_%1$s', $rowData['field_type']);

							return Lang::$txt[$textKey] ?? $textKey;
						},
						'style' => 'width: 15%;',
					],
					'sort' => [
						'default' => 'field_type',
						'reverse' => 'field_type DESC',
					],
				],
				'active' => [
					'header' => [
						'value' => Lang::$txt['custom_profile_active'],
					],
					'data' => [
						'function' => function ($rowData) {
							return $rowData['active'] ? Lang::$txt['yes'] : Lang::$txt['no'];
						},
						'style' => 'width: 8%;',
					],
					'sort' => [
						'default' => 'active DESC',
						'reverse' => 'active',
					],
				],
				'placement' => [
					'header' => [
						'value' => Lang::$txt['custom_profile_placement'],
					],
					'data' => [
						'function' => function ($rowData) {
							return Lang::$txt['custom_profile_placement_' . (empty($rowData['placement']) ? 'standard' : Utils::$context['cust_profile_fields_placement'][$rowData['placement']])];
						},
						'style' => 'width: 8%;',
					],
					'sort' => [
						'default' => 'placement DESC',
						'reverse' => 'placement',
					],
				],
				'show_on_registration' => [
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=%1$s">' . Lang::$txt['modify'] . '</a>',
							'params' => [
								'id_field' => false,
							],
						],
						'style' => 'width: 15%;',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=featuresettings;sa=profileedit',
				'name' => 'customProfileFields',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="new" value="' . Lang::$txt['custom_profile_make_new'] . '" class="button">',
				],
			],
		];
		new ItemList($listOptions);

		// There are two different ways we could get to this point. To keep it simple, they both do
		// the same basic thing.
		if (isset($_SESSION['adm-save'])) {
			Utils::$context['saved_successful'] = true;
			unset($_SESSION['adm-save']);
		}
	}

	/**
	 * Edit some profile fields?
	 */
	public function profileEdit()
	{
		// Sort out the context!
		Utils::$context['fid'] = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
		Menu::$loaded['admin']['current_subsection'] = 'profile';
		Utils::$context['page_title'] = Utils::$context['fid'] ? Lang::$txt['custom_edit_title'] : Lang::$txt['custom_add_title'];
		Utils::$context['sub_template'] = 'edit_profile_field';

		// Load the profile language for section names.
		Lang::load('Profile');

		// There's really only a few places we can go...
		$move_to = ['up', 'down'];

		// We need this for both moving and saving so put it right here.
		$order_count = $this->custFieldsMaxOrder();

		if (Utils::$context['fid'] && !isset($_GET['move'])) {
			Utils::$context['field'] = [];
			$request = Db::$db->query(
				'',
				'SELECT
					id_field, col_name, field_name, field_desc, field_type, field_order, field_length, field_options,
					show_reg, show_display, show_mlist, show_profile, private, active, default_value, can_search,
					bbc, mask, enclose, placement
				FROM {db_prefix}custom_fields
				WHERE id_field = {int:current_field}',
				[
					'current_field' => Utils::$context['fid'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if ($row['field_type'] == 'textarea') {
					@list($rows, $cols) = @explode(',', $row['default_value']);
				} else {
					$rows = 3;
					$cols = 30;
				}

				Utils::$context['field'] = [
					'name' => $row['field_name'],
					'desc' => $row['field_desc'],
					'col_name' => $row['col_name'],
					'profile_area' => $row['show_profile'],
					'reg' => $row['show_reg'],
					'display' => $row['show_display'],
					'mlist' => $row['show_mlist'],
					'type' => $row['field_type'],
					'order' => $row['field_order'],
					'max_length' => $row['field_length'],
					'rows' => $rows,
					'cols' => $cols,
					'bbc' => $row['bbc'] ? true : false,
					'default_check' => $row['field_type'] == 'check' && $row['default_value'] ? true : false,
					'default_select' => $row['field_type'] == 'select' || $row['field_type'] == 'radio' ? $row['default_value'] : '',
					'options' => strlen($row['field_options']) > 1 ? explode(',', $row['field_options']) : ['', '', ''],
					'active' => $row['active'],
					'private' => $row['private'],
					'can_search' => $row['can_search'],
					'mask' => $row['mask'],
					'regex' => substr($row['mask'], 0, 5) == 'regex' ? substr($row['mask'], 5) : '',
					'enclose' => $row['enclose'],
					'placement' => $row['placement'],
				];
			}
			Db::$db->free_result($request);
		}

		// Setup the default values as needed.
		if (empty(Utils::$context['field'])) {
			Utils::$context['field'] = [
				'name' => '',
				'col_name' => '???',
				'desc' => '',
				'profile_area' => 'forumprofile',
				'reg' => false,
				'display' => false,
				'mlist' => false,
				'type' => 'text',
				'order' => 0,
				'max_length' => 255,
				'rows' => 4,
				'cols' => 30,
				'bbc' => false,
				'default_check' => false,
				'default_select' => '',
				'options' => ['', '', ''],
				'active' => true,
				'private' => false,
				'can_search' => false,
				'mask' => 'nohtml',
				'regex' => '',
				'enclose' => '',
				'placement' => 0,
			];
		}

		// Are we moving it?
		if (Utils::$context['fid'] && isset($_GET['move']) && in_array(Utils::htmlspecialchars($_GET['move']), $move_to)) {
			$fields = [];
			$new_sort = [];

			$request = Db::$db->query(
				'',
				'SELECT
					id_field, field_order
				FROM {db_prefix}custom_fields
				ORDER BY field_order',
				[],
			);

			while($row = Db::$db->fetch_assoc($request)) {
				$fields[] = $row['id_field'];
			}
			Db::$db->free_result($request);

			$idx = array_search(Utils::$context['fid'], $fields);

			if ($_GET['move'] == 'down' && count($fields) - 1 > $idx) {
				$new_sort = array_slice($fields, 0, $idx, true);
				$new_sort[] = $fields[$idx + 1];
				$new_sort[] = $fields[$idx];
				$new_sort += array_slice($fields, $idx + 2, count($fields), true);
			} elseif (Utils::$context['fid'] > 0 and $idx < count($fields)) {
				$new_sort = array_slice($fields, 0, ($idx - 1), true);
				$new_sort[] = $fields[$idx];
				$new_sort[] = $fields[$idx - 1];
				$new_sort += array_slice($fields, ($idx + 1), count($fields), true);
			} else {
				// @todo implement an error handler
				Utils::redirectexit('action=admin;area=featuresettings;sa=profile');
			}

			$sql_update = 'CASE ';

			foreach ($new_sort as $orderKey => $PKid) {
				$sql_update .= 'WHEN id_field = ' . $PKid . ' THEN ' . ($orderKey + 1) . ' ';
			}
			$sql_update .= 'END';

			Db::$db->query(
				'',
				'UPDATE {db_prefix}custom_fields
				SET field_order = ' . $sql_update,
				[],
			);

			// @todo perhaps a nice confirmation message, dunno.
			Utils::redirectexit('action=admin;area=featuresettings;sa=profile');
		}

		// Are we saving?
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-ecp');

			// Everyone needs a name - even the (bracket) unknown...
			if (trim($_POST['field_name']) == '') {
				Utils::redirectexit(Config::$scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $_GET['fid'] . ';msg=need_name');
			}

			// Regex you say?  Do a very basic test to see if the pattern is valid
			if (!empty($_POST['regex']) && @preg_match($_POST['regex'], 'dummy') === false) {
				Utils::redirectexit(Config::$scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $_GET['fid'] . ';msg=regex_error');
			}

			$_POST['field_name'] = Utils::htmlspecialchars($_POST['field_name']);
			$_POST['field_desc'] = Utils::htmlspecialchars($_POST['field_desc']);

			// Checkboxes...
			$show_reg = isset($_POST['reg']) ? (int) $_POST['reg'] : 0;
			$show_display = isset($_POST['display']) ? 1 : 0;
			$show_mlist = isset($_POST['mlist']) ? 1 : 0;
			$bbc = isset($_POST['bbc']) ? 1 : 0;
			$show_profile = $_POST['profile_area'];
			$active = isset($_POST['active']) ? 1 : 0;
			$private = isset($_POST['private']) ? (int) $_POST['private'] : 0;
			$can_search = isset($_POST['can_search']) ? 1 : 0;

			// Some masking stuff...
			$mask = $_POST['mask'] ?? '';

			if ($mask == 'regex' && isset($_POST['regex'])) {
				$mask .= $_POST['regex'];
			}

			$mask = Utils::normalize($mask);

			$field_length = isset($_POST['max_length']) ? (int) $_POST['max_length'] : 255;
			$enclose = isset($_POST['enclose']) ? Utils::normalize($_POST['enclose']) : '';
			$placement = isset($_POST['placement']) ? (int) $_POST['placement'] : 0;

			// Select options?
			$field_options = '';
			$newOptions = [];
			$default = isset($_POST['default_check']) && $_POST['field_type'] == 'check' ? 1 : '';

			if (!empty($_POST['select_option']) && ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio')) {
				foreach ($_POST['select_option'] as $k => $v) {
					// Clean, clean, clean...
					$v = Utils::htmlspecialchars($v);
					$v = strtr($v, [',' => '']);

					// Nada, zip, etc...
					if (trim($v) == '') {
						continue;
					}

					// Otherwise, save it boy.
					$field_options .= $v . ',';
					// This is just for working out what happened with old options...
					$newOptions[$k] = $v;

					// Is it default?
					if (isset($_POST['default_select']) && $_POST['default_select'] == $k) {
						$default = $v;
					}
				}

				$field_options = substr($field_options, 0, -1);
			}

			// Text area has default has dimensions
			if ($_POST['field_type'] == 'textarea') {
				$default = (int) $_POST['rows'] . ',' . (int) $_POST['cols'];
			}

			// Come up with the unique name?
			if (empty(Utils::$context['fid'])) {
				$col_name = Utils::normalize($_POST['field_name'], 'kc_casefold');
				$col_name = Utils::sanitizeChars($col_name, 2, '-');
				$col_name = preg_replace('~[^\w-]~u', '', $col_name);
				$col_name = trim($col_name, '-_');
				$col_name = Utils::truncate($col_name, 6);

				// If there is nothing to the name, then let's make out own.
				$col_name = $initial_col_name = 'cust_' . (!empty($col_name) ? $col_name : bin2hex(random_bytes(3)));

				// Make sure this is unique.
				$current_fields = [];
				$request = Db::$db->query(
					'',
					'SELECT id_field, col_name
					FROM {db_prefix}custom_fields',
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$current_fields[$row['id_field']] = $row['col_name'];
				}
				Db::$db->free_result($request);

				$i = 0;

				while (in_array($col_name, $current_fields)) {
					// First try appending an integer to the supplied name.
					if ($i <= 9) {
						$col_name = $initial_col_name . $i;
					}
					// Still not a unique column name? Use a random one, then.
					else {
						$col_name = substr('cust_' . bin2hex(random_bytes(4)), 0, 12);
					}

					// In this extremely unlikely event, bail out.
					if (++$i > 20) {
						ErrorHandler::fatalLang('custom_option_not_unique');
					}
				}
			}
			// Work out what to do with the user data otherwise...
			else {
				// Anything going to check or select is pointless keeping - as is anything coming from check!
				if (
					(
						$_POST['field_type'] == 'check'
						&& Utils::$context['field']['type'] != 'check'
					)
					|| (
						(
							$_POST['field_type'] == 'select'
							|| $_POST['field_type'] == 'radio'
						)
						&& Utils::$context['field']['type'] != 'select'
						&& Utils::$context['field']['type'] != 'radio'
					)
					|| (
						Utils::$context['field']['type'] == 'check'
						&& $_POST['field_type'] != 'check'
					)
				) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}themes
						WHERE variable = {string:current_column}
							AND id_member > {int:no_member}',
						[
							'no_member' => 0,
							'current_column' => Utils::$context['field']['col_name'],
						],
					);
				}
				// Otherwise - if the select is edited may need to adjust!
				elseif ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') {
					$optionChanges = [];
					$takenKeys = [];

					// Work out what's changed!
					foreach (Utils::$context['field']['options'] as $k => $option) {
						if (trim($option) == '') {
							continue;
						}

						// Still exists?
						if (in_array($option, $newOptions)) {
							$takenKeys[] = $k;
						}
					}

					// Finally - have we renamed it - or is it really gone?
					foreach ($optionChanges as $k => $option) {
						// Just been renamed?
						if (!in_array($k, $takenKeys) && !empty($newOptions[$k])) {
							Db::$db->query(
								'',
								'UPDATE {db_prefix}themes
								SET value = {string:new_value}
								WHERE variable = {string:current_column}
									AND value = {string:old_value}
									AND id_member > {int:no_member}',
								[
									'no_member' => 0,
									'new_value' => $newOptions[$k],
									'current_column' => Utils::$context['field']['col_name'],
									'old_value' => $option,
								],
							);
						}
					}
				}
				// @todo Maybe we should adjust based on new text length limits?
			}

			// Do the insertion/updates.
			if (Utils::$context['fid']) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}custom_fields
					SET
						field_name = {string:field_name}, field_desc = {string:field_desc},
						field_type = {string:field_type}, field_length = {int:field_length},
						field_options = {string:field_options}, show_reg = {int:show_reg},
						show_display = {int:show_display}, show_mlist = {int:show_mlist}, show_profile = {string:show_profile},
						private = {int:private}, active = {int:active}, default_value = {string:default_value},
						can_search = {int:can_search}, bbc = {int:bbc}, mask = {string:mask},
						enclose = {string:enclose}, placement = {int:placement}
					WHERE id_field = {int:current_field}',
					[
						'field_length' => $field_length,
						'show_reg' => $show_reg,
						'show_display' => $show_display,
						'show_mlist' => $show_mlist,
						'private' => $private,
						'active' => $active,
						'can_search' => $can_search,
						'bbc' => $bbc,
						'current_field' => Utils::$context['fid'],
						'field_name' => $_POST['field_name'],
						'field_desc' => $_POST['field_desc'],
						'field_type' => $_POST['field_type'],
						'field_options' => $field_options,
						'show_profile' => $show_profile,
						'default_value' => $default,
						'mask' => $mask,
						'enclose' => $enclose,
						'placement' => $placement,
					],
				);

				// Just clean up any old selects - these are a pain!
				if (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && !empty($newOptions)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}themes
						WHERE variable = {string:current_column}
							AND value NOT IN ({array_string:new_option_values})
							AND id_member > {int:no_member}',
						[
							'no_member' => 0,
							'new_option_values' => $newOptions,
							'current_column' => Utils::$context['field']['col_name'],
						],
					);
				}
			} else {
				// Gotta figure it out the order.
				$new_order = $order_count > 1 ? ($order_count + 1) : 1;

				Db::$db->insert(
					'',
					'{db_prefix}custom_fields',
					[
						'col_name' => 'string', 'field_name' => 'string', 'field_desc' => 'string',
						'field_type' => 'string', 'field_length' => 'string', 'field_options' => 'string', 'field_order' => 'int',
						'show_reg' => 'int', 'show_display' => 'int', 'show_mlist' => 'int', 'show_profile' => 'string',
						'private' => 'int', 'active' => 'int', 'default_value' => 'string', 'can_search' => 'int',
						'bbc' => 'int', 'mask' => 'string', 'enclose' => 'string', 'placement' => 'int',
					],
					[
						$col_name, $_POST['field_name'], $_POST['field_desc'],
						$_POST['field_type'], $field_length, $field_options, $new_order,
						$show_reg, $show_display, $show_mlist, $show_profile,
						$private, $active, $default, $can_search,
						$bbc, $mask, $enclose, $placement,
					],
					['id_field'],
				);
			}
		}
		// Deleting?
		elseif (isset($_POST['delete']) && Utils::$context['field']['col_name']) {
			User::$me->checkSession();
			SecurityToken::validate('admin-ecp');

			// Delete the user data first.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}themes
				WHERE variable = {string:current_column}
					AND id_member > {int:no_member}',
				[
					'no_member' => 0,
					'current_column' => Utils::$context['field']['col_name'],
				],
			);

			// Finally - the field itself is gone!
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}custom_fields
				WHERE id_field = {int:current_field}',
				[
					'current_field' => Utils::$context['fid'],
				],
			);

			// Re-arrange the order.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}custom_fields
				SET field_order = field_order - 1
				WHERE field_order > {int:current_order}',
				[
					'current_order' => Utils::$context['field']['order'],
				],
			);
		}

		// Rebuild display cache etc.
		if (isset($_POST['delete']) || isset($_POST['save'])) {
			User::$me->checkSession();

			$fields = [];
			$request = Db::$db->query(
				'',
				'SELECT col_name, field_name, field_type, field_order, bbc, enclose, placement, show_mlist, field_options
				FROM {db_prefix}custom_fields
				WHERE show_display = {int:is_displayed}
					AND active = {int:active}
					AND private != {int:not_owner_only}
					AND private != {int:not_admin_only}
				ORDER BY field_order',
				[
					'is_displayed' => 1,
					'active' => 1,
					'not_owner_only' => 2,
					'not_admin_only' => 3,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$fields[] = [
					'col_name' => strtr($row['col_name'], ['|' => '', ';' => '']),
					'title' => strtr($row['field_name'], ['|' => '', ';' => '']),
					'type' => $row['field_type'],
					'order' => $row['field_order'],
					'bbc' => $row['bbc'] ? '1' : '0',
					'placement' => !empty($row['placement']) ? $row['placement'] : '0',
					'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
					'mlist' => $row['show_mlist'],
					'options' => (!empty($row['field_options']) ? explode(',', $row['field_options']) : []),
				];
			}
			Db::$db->free_result($request);

			Config::updateModSettings(['displayFields' => Utils::jsonEncode($fields)]);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=featuresettings;sa=profile');
		}

		SecurityToken::create('admin-ecp');
	}

	/**
	 * Handles modifying the likes settings.
	 *
	 * Accessed from ?action=admin;area=featuresettings;sa=likes
	 */
	public function likes(): void
	{
		$config_vars = self::likesConfigVars();

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_likes_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=featuresettings;sa=likes');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=featuresettings;save;sa=likes';
		Utils::$context['settings_title'] = Lang::$txt['likes'];

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Handles modifying the mentions settings.
	 *
	 * Accessed via ?action=admin;area=featuresettings;sa=mentions
	 */
	public function mentions(): void
	{
		$config_vars = self::mentionsConfigVars();

		// Saving?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_mentions_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=featuresettings;sa=mentions');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=featuresettings;save;sa=mentions';
		Utils::$context['settings_title'] = Lang::$txt['mentions'];

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Handles modifying the alerts settings.
	 */
	public function alerts()
	{
		// Dummy settings for the template...
		User::$me->is_owner = false;
		Utils::$context['member'] = [];
		Utils::$context['id_member'] = 0;
		Utils::$context['menu_item_selected'] = 'alerts';
		Utils::$context['token_check'] = 'noti-admin';

		// Specify our action since we'll want to post back here instead of the profile
		Utils::$context['action'] = 'action=admin;area=featuresettings;sa=alerts;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];

		Theme::loadTemplate('Profile');
		Lang::load('Profile');

		Profile::load(0);
		Notification::call();

		Utils::$context['page_title'] = Lang::$txt['notify_settings'];

		// Override the description
		Utils::$context['description'] = Lang::$txt['notifications_desc'];
		Utils::$context['sub_template'] = 'alert_configuration';
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
	 * Gets the configuration variables for the basic sub-action.
	 *
	 * @return array $config_vars for the basic sub-action.
	 */
	public static function basicConfigVars(): array
	{
		// We need to know if personal text is enabled, and if it's in the registration fields option.
		// If admins have set it up as an on-registration thing, they can't set a default value (because it'll never be used)
		$disabled_fields = isset(Config::$modSettings['disabled_profile_fields']) ? explode(',', Config::$modSettings['disabled_profile_fields']) : [];
		$reg_fields = isset(Config::$modSettings['registration_fields']) ? explode(',', Config::$modSettings['registration_fields']) : [];
		$can_personal_text = !in_array('personal_text', $disabled_fields) && !in_array('personal_text', $reg_fields);

		$config_vars = [
			// Big Options... polls, sticky, bbc....
			['select', 'pollMode', [Lang::$txt['disable_polls'], Lang::$txt['enable_polls'], Lang::$txt['polls_as_topics']]],
			'',

			// Basic stuff, titles, flash, permissions...
			['check', 'allow_guestAccess'],
			['check', 'enable_buddylist'],
			['check', 'allow_hideOnline'],
			['check', 'titlesEnable'],
			['text', 'default_personal_text', 'subtext' => Lang::$txt['default_personal_text_note'], 'disabled' => !$can_personal_text],
			['check', 'topic_move_any'],
			['int', 'defaultMaxListItems', 'step' => 1, 'min' => 1, 'max' => 999],
			'',

			// Jquery source
			[
				'select',
				'jquery_source',
				[
					'cdn' => Lang::$txt['jquery_google_cdn'],
					'jquery_cdn' => Lang::$txt['jquery_jquery_cdn'],
					'microsoft_cdn' => Lang::$txt['jquery_microsoft_cdn'],
					'local' => Lang::$txt['jquery_local'],
					'custom' => Lang::$txt['jquery_custom'],
				],
				'onchange' => 'if (this.value == \'custom\'){document.getElementById(\'jquery_custom\').disabled = false; } else {document.getElementById(\'jquery_custom\').disabled = true;}',
			],
			[
				'text',
				'jquery_custom',
				'disabled' => !isset(Config::$modSettings['jquery_source']) || (isset(Config::$modSettings['jquery_source']) && Config::$modSettings['jquery_source'] != 'custom'), 'size' => 75,
			],
			'',

			// css and js minification.
			['check', 'minimize_files'],
			'',

			// SEO stuff
			['check', 'queryless_urls', 'subtext' => '<strong>' . Lang::$txt['queryless_urls_note'] . '</strong>'],
			['text', 'meta_keywords', 'subtext' => Lang::$txt['meta_keywords_note'], 'size' => 50],
			'',

			// Time zone and formatting.
			['text', 'time_format'],
			['select', 'default_timezone', array_filter(TimeZone::list(), 'is_string', ARRAY_FILTER_USE_KEY)],
			['text', 'timezone_priority_countries', 'subtext' => Lang::$txt['setting_timezone_priority_countries_note']],
			'',

			// Who's online?
			['check', 'who_enabled'],
			['int', 'lastActive', 6, 'postinput' => Lang::$txt['minutes']],
			'',

			// Statistics.
			['check', 'trackStats'],
			['check', 'hitStats'],
			'',

			// Option-ish things... miscellaneous sorta.
			['check', 'disallow_sendBody'],
			'',

			// Alerts stuff
			['check', 'enable_ajax_alerts'],
			['select', 'alerts_auto_purge',
				[
					'0' => Lang::$txt['alerts_auto_purge_0'],
					'7' => Lang::$txt['alerts_auto_purge_7'],
					'30' => Lang::$txt['alerts_auto_purge_30'],
					'90' => Lang::$txt['alerts_auto_purge_90'],
				],
			],
			['int', 'alerts_per_page', 'step' => 1, 'min' => 0, 'max' => 999],
		];

		IntegrationHook::call('integrate_modify_basic_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets the configuration variables for the bbc sub-action.
	 *
	 * @return array $config_vars for the bbc sub-action.
	 */
	public static function bbcConfigVars(): array
	{
		$config_vars = [
			// Main tweaks
			['check', 'enableBBC'],
			['check', 'enableBBC', 0, 'onchange' => 'toggleBBCDisabled(\'disabledBBC\', !this.checked); toggleBBCDisabled(\'legacyBBC\', !this.checked);'],
			['check', 'enablePostHTML'],
			['check', 'autoLinkUrls'],
			'',

			['bbc', 'disabledBBC'],

			// This one is actually pretend...
			['bbc', 'legacyBBC', 'help' => 'legacy_bbc'],
		];

		// Permissions for restricted BBC
		if (!empty(Utils::$context['restricted_bbc'])) {
			$config_vars[] = '';
		}

		foreach (Utils::$context['restricted_bbc'] as $bbc) {
			$config_vars[] = ['permissions', 'bbc_' . $bbc, 'text_label' => sprintf(Lang::$txt['groups_can_use'], '[' . $bbc . ']')];
		}

		Utils::$context['settings_post_javascript'] = '
			toggleBBCDisabled(\'disabledBBC\', ' . (empty(Config::$modSettings['enableBBC']) ? 'true' : 'false') . ');
			toggleBBCDisabled(\'legacyBBC\', ' . (empty(Config::$modSettings['enableBBC']) ? 'true' : 'false') . ');';

		IntegrationHook::call('integrate_modify_bbc_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets the configuration variables for the layout sub-action.
	 *
	 * @return array $config_vars for the layout sub-action.
	 */
	public static function layoutConfigVars(): array
	{
		$config_vars = [
			// Pagination stuff.
			['check', 'compactTopicPagesEnable'],
			[
				'int',
				'compactTopicPagesContiguous',
				null,
				Lang::$txt['contiguous_page_display'] . '<div class="smalltext">' . str_replace(' ', '&nbsp;', '"3" ' . Lang::$txt['to_display'] . ': <strong>1 ... 4 [5] 6 ... 9</strong>') . '<br>' . str_replace(' ', '&nbsp;', '"5" ' . Lang::$txt['to_display'] . ': <strong>1 ... 3 4 [5] 6 7 ... 9</strong>') . '</div>',
			],
			['int', 'defaultMaxMembers'],
			'',

			// Stuff that just is everywhere - today, search, online, etc.
			['select', 'todayMod', [Lang::$txt['today_disabled'], Lang::$txt['today_only'], Lang::$txt['yesterday_today']]],
			['check', 'onlineEnable'],
			'',

			// This is like debugging sorta.
			['check', 'timeLoadPageEnable'],
		];

		IntegrationHook::call('integrate_layout_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets the configuration variables for the sig sub-action.
	 *
	 * @return array $config_vars for the sig sub-action.
	 */
	public static function sigConfigVars(): array
	{
		$config_vars = [
			// Are signatures even enabled?
			['check', 'signature_enable'],
			'',

			// Tweaking settings!
			['int', 'signature_max_length', 'subtext' => Lang::$txt['zero_for_no_limit']],
			['int', 'signature_max_lines', 'subtext' => Lang::$txt['zero_for_no_limit']],
			['int', 'signature_max_font_size', 'subtext' => Lang::$txt['zero_for_no_limit']],
			['check', 'signature_allow_smileys', 'onclick' => 'document.getElementById(\'signature_max_smileys\').disabled = !this.checked;'],
			['int', 'signature_max_smileys', 'subtext' => Lang::$txt['zero_for_no_limit']],
			'',

			// Image settings.
			['int', 'signature_max_images', 'subtext' => Lang::$txt['signature_max_images_note']],
			['int', 'signature_max_image_width', 'subtext' => Lang::$txt['zero_for_no_limit']],
			['int', 'signature_max_image_height', 'subtext' => Lang::$txt['zero_for_no_limit']],
			'',

			['bbc', 'signature_bbc'],
		];

		IntegrationHook::call('integrate_signature_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets the configuration variables for the likes sub-action.
	 *
	 * @return array $config_vars for the likes sub-action.
	 */
	public static function likesConfigVars(): array
	{
		$config_vars = [
			['check', 'enable_likes'],
			['permissions', 'likes_like'],
		];

		IntegrationHook::call('integrate_likes_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets the configuration variables for the mentions sub-action.
	 *
	 * @return array $config_vars for the mentions sub-action.
	 */
	public static function mentionsConfigVars(): array
	{
		$config_vars = [
			['check', 'enable_mentions'],
			['permissions', 'mention'],
		];

		IntegrationHook::call('integrate_mentions_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @param int $start The item to start with (used for pagination purposes)
	 * @param int $items_per_page The number of items to display per page
	 * @param string $sort A string indicating how to sort the results
	 * @param bool $standardFields Whether or not to include standard fields as well
	 * @return array An array of info about the various profile fields
	 */
	public static function list_getProfileFields($start, $items_per_page, $sort, $standardFields): array
	{
		$list = [];

		if ($standardFields) {
			$standard_fields = ['website', 'personal_text', 'timezone', 'posts', 'warning_status'];
			$fields_no_registration = ['posts', 'warning_status'];
			$disabled_fields = isset(Config::$modSettings['disabled_profile_fields']) ? explode(',', Config::$modSettings['disabled_profile_fields']) : [];
			$registration_fields = isset(Config::$modSettings['registration_fields']) ? explode(',', Config::$modSettings['registration_fields']) : [];

			foreach ($standard_fields as $field) {
				$list[] = [
					'id' => $field,
					'label' => Lang::$txt['standard_profile_field_' . $field] ?? (Lang::$txt[$field] ?? $field),
					'disabled' => in_array($field, $disabled_fields),
					'on_register' => in_array($field, $registration_fields) && !in_array($field, $fields_no_registration),
					'can_show_register' => !in_array($field, $fields_no_registration),
				];
			}
		} else {
			// Load all the fields.
			$request = Db::$db->query(
				'',
				'SELECT id_field, col_name, field_name, field_desc, field_type, field_order, active, placement
				FROM {db_prefix}custom_fields
				ORDER BY {raw:sort}
				LIMIT {int:start}, {int:items_per_page}',
				[
					'sort' => $sort,
					'start' => $start,
					'items_per_page' => $items_per_page,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$list[] = $row;
			}
			Db::$db->free_result($request);
		}

		return $list;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @return int The total number of custom profile fields
	 */
	public static function list_getProfileFieldSize(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}custom_fields',
			[
			],
		);

		list($numProfileFields) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $numProfileFields;
	}

	/**
	 * Backward compatibility wrapper for the basic sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyBasicSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::basicConfigVars();
		}

		self::load();
		self::$obj->subaction = 'basic';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the bbc sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyBBCSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::bbcConfigVars();
		}

		self::load();
		self::$obj->subaction = 'bbc';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the layout sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyLayoutSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::layoutConfigVars();
		}

		self::load();
		self::$obj->subaction = 'layout';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the sig sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifySignatureSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::sigConfigVars();
		}

		self::load();
		self::$obj->subaction = 'sig';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the profile sub-action.
	 */
	public static function showCustomProfiles(): void
	{
		self::load();
		self::$obj->subaction = 'profile';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the profileedit sub-action.
	 */
	public static function editCustomProfiles(): void
	{
		self::load();
		self::$obj->subaction = 'profileedit';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the likes sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyLikesSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::likesConfigVars();
		}

		self::load();
		self::$obj->subaction = 'likes';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the mentions sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyMentionsSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::mentionsConfigVars();
		}

		self::load();
		self::$obj->subaction = 'mentions';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the alerts sub-action.
	 */
	public static function modifyAlertsSettings(): void
	{
		self::load();
		self::$obj->subaction = 'alerts';
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
		Lang::load('Help');
		Lang::load('ManageSettings');

		Utils::$context['page_title'] = Lang::$txt['modSettings_title'];
		Utils::$context['show_privacy_policy_warning'] = empty(Config::$modSettings['policy_' . Lang::$default]);

		// Load up all the tabs...
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['modSettings_title'],
			'help' => 'featuresettings',
			'description' => sprintf(Lang::$txt['modSettings_desc'], Theme::$current->settings['theme_id'], Utils::$context['session_id'], Utils::$context['session_var'], Config::$scripturl),
			'tabs' => [
				'basic' => [
				],
				'bbc' => [
					'description' => Lang::$txt['manageposts_bbc_settings_description'],
				],
				'layout' => [
				],
				'sig' => [
					'description' => Lang::$txt['signature_settings_desc'],
				],
				'profile' => [
					'description' => Lang::$txt['custom_profile_desc'],
				],
				'likes' => [
				],
				'mentions' => [
				],
				'alerts' => [
					'description' => Lang::$txt['notifications_desc'],
				],
			],
		];

		IntegrationHook::call('integrate_modify_features', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Just pause the signature applying thing.
	 */
	protected function pauseSignatureApplySettings(): void
	{
		// Try get more time...
		@set_time_limit(600);

		if (function_exists('apache_reset_timeout')) {
			@apache_reset_timeout();
		}

		// Have we exhausted all the time we allowed?
		if (time() - array_sum(explode(' ', Utils::$context['sig_start'])) < 3) {
			return;
		}

		Utils::$context['continue_get_data'] = '?action=admin;area=featuresettings;sa=sig;apply;step=' . $_GET['step'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		Utils::$context['page_title'] = Lang::$txt['not_done_title'];
		Utils::$context['continue_post_data'] = '';
		Utils::$context['continue_countdown'] = '2';
		Utils::$context['sub_template'] = 'not_done';

		// Specific stuff to not break this template!
		Menu::$loaded['admin']['current_subsection'] = 'sig';

		// Get the right percent.
		Utils::$context['continue_percent'] = round(($_GET['step'] / Utils::$context['max_member']) * 100);

		// Never more than 100%!
		Utils::$context['continue_percent'] = min(Utils::$context['continue_percent'], 100);

		Utils::obExit();
	}

	/**
	 * Returns the maximum field_order value for the custom fields
	 *
	 * @return int The maximum value of field_order from the custom_fields table
	 */
	protected function custFieldsMaxOrder(): int
	{
		// Gotta know the order limit
		$result = Db::$db->query(
			'',
			'SELECT MAX(field_order)
			FROM {db_prefix}custom_fields',
			[],
		);

		list($order_count) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		return (int) $order_count;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Features::exportStatic')) {
	Features::exportStatic();
}

?>