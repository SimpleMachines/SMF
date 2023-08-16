<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * Represents a member's profile as shown by ?action=profile.
 *
 * Note: The code for the profile action is located in the SMF\Actions\Profile\*
 * classes. This class instead represents the data structure of a member profile
 * and provides methods for loading, manipulating, and saving that data.
 */
class Profile extends User implements \ArrayAccess
{
	use BackwardCompatibility, ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'backcompat_profileLoadGroups' => 'profileLoadGroups',
			'backcompat_loadProfileFields' => 'loadProfileFields',
			'backcompat_loadCustomFields' => 'loadCustomFields',
			'backcompat_loadThemeOptions' => 'loadThemeOptions',
			'backcompat_setupProfileContext' => 'setupProfileContext',
			'backcompat_makeCustomFieldChanges' => 'makeCustomFieldChanges',
			'backcompat_makeThemeChanges' => 'makeThemeChanges',
		),
		'prop_names' => array(
			'profile_fields' => 'profile_fields',
			'profile_vars' => 'profile_vars',
			'cur_profile' => 'cur_profile',
		),
	);

	/*****************
	 * Class constants
	 *****************/

	const RESERVED_VARS = array(
		'actual_theme_url',
		'actual_images_url',
		'base_theme_dir',
		'base_theme_url',
		'default_images_url',
		'default_theme_dir',
		'default_theme_url',
		'default_template',
		'images_url',
		'number_recent_posts',
		'smiley_sets_default',
		'theme_dir',
		'theme_id',
		'theme_layers',
		'theme_templates',
		'theme_url',
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * The standard profile fields for this member.
	 */
	public array $standard_fields = array();

	/**
	 * @var array
	 *
	 * The standard custom fields for this member.
	 */
	public array $custom_fields = array();

	/**
	 * @var bool
	 *
	 * Whether members are required to fill in at least one of the custom fields.
	 */
	public bool $custom_fields_required = false;

	/**
	 * @var array
	 *
	 * Info about groups that this member could be assigned to.
	 */
	public array $assignable_groups = array();

	/**
	 * @var array
	 *
	 * Profile data about the user whose profile is being viewed.
	 *
	 * This is a reference to User::$profiles[$this->id].
	 */
	public array $data = array();

	/**
	 * @var array
	 *
	 * New profile values to save.
	 */
	public array $new_data = array();

	/**
	 * @var array
	 *
	 * New custom profile field values to save.
	 */
	public array $new_cf_data = array(
		'updates' => array(),
		'deletes' => array(),
	);

	/**
	 * @var array
	 *
	 * New theme option values to save.
	 */
	public array $new_options = array(
		'updates' => array(),
		'deletes' => array(),
	);

	/**
	 * @var array
	 *
	 * Any errors encountered while trying to save changes.
	 */
	public array $save_errors = array();

	/**
	 * @var bool
	 *
	 * Whether the $_POST values have been sanitized yet.
	 */
	public bool $post_sanitized = false;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = array();

	/**
	 * @var int
	 *
	 * ID of the member whose profile is being viewed.
	 */
	public static int $memID;

	/**
	 * @var object
	 *
	 * Instance of this class for the member whose profile is being viewed.
	 */
	public static object $member;

	/**
	 * @var array
	 *
	 * Definitions for all known custom profile fields.
	 */
	public static array $custom_field_definitions;

	/**
	 * @var array
	 *
	 * Groups that cannot be assigned.
	 */
	public static array $unassignable_groups;

	/**
	 * @var array
	 *
	 * This is a reference to Profile::$member->data.
	 * Only exists for backward compatibility reasons.
	 */
	public static array $cur_profile = array();

	/**
	 * @var array
	 *
	 * This is a reference to Profile::$member->standard_fields.
	 * Only exists for backward compatibility reasons.
	 */
	public static array $profile_fields = array();

	/**
	 * @var array
	 *
	 * This is a reference to Profile::$member->new_data.
	 * Only exists for backward compatibility reasons.
	 */
	public static array $profile_vars = array();

	/**
	 * @var array
	 *
	 * This is a reference to Profile::$member->save_errors.
	 * Only exists for backward compatibility reasons.
	 */
	public static array $post_errors = array();

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * General-purpose permission for anything that doesn't have its own.
	 */
	protected bool $can_change_extra = false;

	/**
	 * @var array
	 *
	 * Data about a downloaded avatar.
	 */
	protected array $new_avatar_data = array();

	/**
	 * @var array
	 *
	 * Data to pass to logChanges() upon a successful save.
	 */
	protected array $log_changes = array();

	/**
	 * @var int
	 *
	 * ID of the user who made whatever changes we are saving.
	 */
	protected int $applicator;

	/**
	 * @var array
	 *
	 * Any errors encountered while trying to save custom fields.
	 *
	 * Everything in this array is also added to $save_errors, but it is helpful
	 * in some places to be able to distinguish these from the others.
	 */
	protected array $cf_save_errors = array();

	/****************
	 * Public methods
	 ****************/

	/**
	 * This defines every profile field known to man.
	 *
	 * @param bool $force_reload Whether to reload the data.
	 */
	public function loadStandardFields(bool $force_reload = false)
	{
		// Don't load this twice!
		if (!empty($this->standard_fields) && !$force_reload)
			return;

		/**
		 * This horrific array defines all the profile fields in the world!
		 *
		 * In general each "field" has one array, the key of which is the
		 * database column name associated with said field. Each item can have
		 * the following attributes:
		 *
		 * string $type:        The type of field this is.
		 *
		 *   Valid types are:
		 *
		 *   - callback:        This is a field which has its own callback
		 *                      mechanism for templating.
		 *   - check:           A simple checkbox.
		 *   - hidden:          This doesn't have any visual aspects but may
		 *                      have some validity.
		 *   - password:        A password box.
		 *   - select:          A select box.
		 *   - text:            A string of some description.
		 *
		 * string $label:       The label for this item. Default will be
		 *                      Lang::$txt[$key] if this isn't set.
		 *
		 * string $subtext:     The subtext (Small label) for this item.
		 *
		 * int $size:           Optional size for a text area.
		 *
		 * array $input_attr:   An array of text strings to be added to the
		 *                      input box for this item.
		 *
		 * string $value:       The value of the item. If this is not set,
		 *                      Profile::$member->$key is assumed.
		 *
		 * string $permission:  Permission required for this item.
		 *                      Where relevant, the _any/_own suffix will be
		 *                      appended automatically.
		 *
		 * function $input_validate:
		 *                      A runtime function which validates the element
		 *                      before going to the database. It is passed the
		 *                      relevant $_POST element if it exists and should
		 *                      be treated like a reference.
		 *
		 *    Return types for $input_validate:
		 *
		 *    - true:           Element can be stored.
		 *    - false:          Skip this element.
		 *    - a text string:  An error occured; this is the error message.
		 *
		 * function $preload:   A function that is used to load data required
		 *                      for this element to be displayed. Must return
		 *                      true to be displayed at all.
		 *
		 * string $cast_type:   If set, casts the element to a certain type.
		 *                      Valid types are bool, int, and float.
		 *
		 * string $save_key:    The database column in which to save the value.
		 *                      If not set, defaults to the key of this element
		 *                      in the overall $this->standard_fields array.
		 *
		 * bool $is_dummy:      If true, then nothing is saved for this element.
		 *
		 * bool $enabled:       A test to determine whether this is available.
		 *                      If not enabled, the element is unset.
		 *
		 * string $link_with:   Key that links this field to an overall set.
		 *
		 *
		 * Any elements that has a custom input_validate must ensure that it
		 * sets the value of $this->data correctly in order to
		 * enable the changes to be displayed correctly on submit of the form.
		 */
		$this->standard_fields = array(
			'avatar_choice' => array(
				'type' => 'callback',
				'callback_func' => 'avatar_select',
				// This handles the permissions too.
				'preload' => array(array($this, 'loadAvatarData')),
				'input_validate' => array($this, 'validateAvatarData'),
				'save_key' => 'avatar',
			),
			'bday1' => array(
				'type' => 'callback',
				'callback_func' => 'birthdate',
				'permission' => 'profile_extra',
				'preload' => function()
				{
					// Split up the birthdate....
					list($uyear, $umonth, $uday) = explode('-', empty($this->birthdate) || $this->birthdate === '1004-01-01' ? '--' : $this->birthdate);

					Utils::$context['member']['birth_date'] = array(
						'year' => $uyear,
						'month' => $umonth,
						'day' => $uday,
					);

					return true;
				},
				'input_validate' => function(&$value)
				{
					if (isset($_POST['bday2'], $_POST['bday3']) && $value > 0 && $_POST['bday2'] > 0)
					{
						// Set to blank?
						if ((int) $_POST['bday3'] == 1 && (int) $_POST['bday2'] == 1 && (int) $value == 1)
						{
							$value = '1004-01-01';
						}
						else
						{
							$value = checkdate($value, $_POST['bday2'], $_POST['bday3'] < 1004 ? 1004 : $_POST['bday3']) ? sprintf('%04d-%02d-%02d', $_POST['bday3'] < 1004 ? 1004 : $_POST['bday3'], $_POST['bday1'], $_POST['bday2']) : '1004-01-01';
						}
					}
					else
					{
						$value = '1004-01-01';
					}

					$this->new_data['birthdate'] = $value;
					$this->birthdate = $value;
					return false;
				},
			),
			// Setting the birthdate the old style way?
			'birthdate' => array(
				'type' => 'hidden',
				'permission' => 'profile_extra',
				'input_validate' => function(&$value)
				{
					// @todo Should we check for this year and tell them they made a mistake :P? (based on coppa at least?)
					if (preg_match('/(\d{4})[\-\., ](\d{2})[\-\., ](\d{2})/', $value, $dates) === 1)
					{
						$value = checkdate($dates[2], $dates[3], $dates[1] < 4 ? 4 : $dates[1]) ? sprintf('%04d-%02d-%02d', $dates[1] < 4 ? 4 : $dates[1], $dates[2], $dates[3]) : '1004-01-01';

						return true;
					}
					else
					{
						$value = empty($this->birthdate) ? '1004-01-01' : $this->birthdate;

						return false;
					}
				},
			),
			'date_registered' => array(
				'type' => 'date',
				'value' => empty($this->date_registered) || !is_int($this->date_registered) ? Lang::$txt['not_applicable'] : smf_strftime('%Y-%m-%d', $this->date_registered),
				'label' => Lang::$txt['date_registered'],
				'log_change' => true,
				'permission' => 'moderate_forum',
				'input_validate' => function(&$value)
				{
					// Bad date!  Go try again - please?
					if (($value = strtotime($value)) === false)
					{
						$value = $this->date_registered;

						return Lang::$txt['invalid_registration'] . ' ' . smf_strftime('%d %b %Y ' . (strpos(User::$me->time_format, '%H') !== false ? '%I:%M:%S %p' : '%H:%M:%S'), time());
					}
					// As long as it doesn't equal "N/A"...
					elseif ($value != Lang::$txt['not_applicable'] && $value != strtotime(smf_strftime('%Y-%m-%d', $this->date_registered)))
					{
						$diff = $this->date_registered - strtotime(smf_strftime('%Y-%m-%d', $this->date_registered));

						$value = $value + $diff;
					}
					else
					{
						$value = $this->date_registered;
					}

					return true;
				},
			),
			'email_address' => array(
				'type' => 'email',
				'label' => Lang::$txt['user_email_address'],
				'subtext' => Lang::$txt['valid_email'],
				'log_change' => true,
				'permission' => 'profile_password',
				'js_submit' => !empty(Config::$modSettings['send_validation_onChange']) ? '
					form_handle.addEventListener("submit", function(event)
					{
						if (this.email_address.value != "' . (!empty($this->email) ? $this->email : '') . '")
						{
							alert(' . JavaScriptEscape(Lang::$txt['email_change_logout']) . ');
							return true;
						}
					}, false);' : '',
				'input_validate' => function(&$value)
				{
					if (strtolower($value) == strtolower($this->email))
						return false;

					$isValid = self::validateEmail($value, $this->id);

					// Do they need to revalidate? If so schedule the function!
					if ($isValid === true && !empty(Config::$modSettings['send_validation_onChange']) && !allowedTo('moderate_forum'))
					{
						$this->new_data['validation_code'] = User::generateValidationCode();

						$this->new_data['is_activated'] = 2;

						Utils::$context['profile_execute_on_save'][] = array(array($this, 'sendActivation'));

						unset(Utils::$context['profile_execute_on_save']['reload_user']);
					}

					return $isValid;
				},
			),
			// Selecting group membership is complicated so we handle it separately.
			'id_group' => array(
				'type' => 'callback',
				'callback_func' => 'group_manage',
				'permission' => 'manage_membergroups',
				'preload' => array(array($this, 'loadAssignableGroups')),
				'log_change' => true,
				'input_validate' => function(&$value)
				{
					return $this->validateGroups($value, $_POST['additional_groups'] ?? array());
				},
			),
			'id_theme' => array(
				'type' => 'callback',
				'callback_func' => 'theme_pick',
				'permission' => 'profile_extra',
				'enabled' => Config::$modSettings['theme_allow'] || allowedTo('admin_forum'),
				'preload' => function()
				{
					$request = Db::$db->query('', '
						SELECT value
						FROM {db_prefix}themes
						WHERE id_theme = {int:id_theme}
							AND variable = {string:variable}
						LIMIT 1', array(
							'id_theme' => $this->theme,
							'variable' => 'name',
						)
					);
					list($name) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					Utils::$context['member']['theme'] = array(
						'id' => $this->theme,
						'name' => empty($this->theme) ? Lang::$txt['theme_forum_default'] : $name
					);

					return true;
				},
				'input_validate' => function(&$value)
				{
					$value = (int) $value;
					return true;
				},
			),
			'lngfile' => array(
				'type' => 'select',
				'options' => function()
				{
					return array_map(fn($lang) => $lang['name'], Lang::get());
				},
				'label' => Lang::$txt['preferred_language'],
				'permission' => 'profile_identity',
				'enabled' => !empty(Config::$modSettings['userLanguage']),
				'value' => $this->language,
				'input_validate' => function(&$value)
				{
					// Load the languages.
					Lang::get();

					if (isset(Utils::$context['languages'][$value]))
					{
						if (User::$me->is_owner && empty(Utils::$context['password_auth_failed']))
						{
							$_SESSION['language'] = $value;
						}

						return true;
					}
					else
					{
						$value = $this->language;

						return false;
					}
				},
			),
			// The username is not always editable - so adjust it as such.
			'member_name' => array(
				'type' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? 'text' : 'label',
				'label' => Lang::$txt['username'],
				'subtext' => allowedTo('admin_forum') && !isset($_GET['changeusername']) ? '[<a href="' . Config::$scripturl . '?action=profile;u=' . $this->id . ';area=account;changeusername" style="font-style: italic;">' . Lang::$txt['username_change'] . '</a>]' : '',
				'log_change' => true,
				'permission' => 'profile_identity',
				'prehtml' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? '<div class="alert">' . Lang::$txt['username_warning'] . '</div>' : '',
				'input_validate' => function(&$value)
				{
					if (allowedTo('admin_forum'))
					{
						// We'll need this...
						require_once(Config::$sourcedir . '/Subs-Auth.php');

						// Maybe they are trying to change their password as well?
						$resetPassword = true;

						if (
							isset($_POST['passwrd1'])
							&& $_POST['passwrd1'] != ''
							&& isset($_POST['passwrd2'])
							&& $_POST['passwrd1'] == $_POST['passwrd2']
							&& validatePassword(un_htmlspecialchars($_POST['passwrd1']), $value, array($this->name, User::$me->username, User::$me->name, User::$me->email)) == null
						)
						{
							$resetPassword = false;
						}

						// Do the reset... this will send them an email too.
						if ($resetPassword)
						{
							resetPassword($this->id, $value);
						}
						elseif ($value !== null)
						{
							validateUsername($this->id, trim(Utils::normalizeSpaces(Utils::sanitizeChars($value, 1, ' '), true, true, array('no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true))));

							User::updateMemberData($this->id, array('member_name' => $value));

							// Call this here so any integrated systems will know about the name change (resetPassword() takes care of this if we're letting SMF generate the password)
							call_integration_hook('integrate_reset_pass', array($this->username, $value, $_POST['passwrd1']));
						}
					}
					return false;
				},
			),
			'passwrd1' => array(
				'type' => 'password',
				'label' => Lang::$txt['choose_pass'],
				'subtext' => Lang::$txt['password_strength'],
				'size' => 20,
				'value' => '',
				'permission' => 'profile_password',
				'save_key' => 'passwd',
				// Note this will only work if passwrd2 also exists!
				'input_validate' => function(&$value)
				{
					// If we didn't try it then ignore it!
					if ($value == '')
						return false;

					// Do the two entries for the password even match?
					if (!isset($_POST['passwrd2']) || $value != $_POST['passwrd2'])
						return 'bad_new_password';

					// Let's get the validation function into play...
					require_once(Config::$sourcedir . '/Subs-Auth.php');

					$passwordErrors = validatePassword(un_htmlspecialchars($value), $this->username, array($this->name, User::$me->username, User::$me->name, User::$me->email));

					// Were there errors?
					if ($passwordErrors != null)
						return 'password_' . $passwordErrors;

					// Set up the new password variable... ready for storage.
					$value = hash_password($this->username, un_htmlspecialchars($value));

					return true;
				},
			),
			'passwrd2' => array(
				'type' => 'password',
				'label' => Lang::$txt['verify_pass'],
				'size' => 20,
				'value' => '',
				'permission' => 'profile_password',
				'is_dummy' => true,
			),
			'personal_text' => array(
				'type' => 'text',
				'label' => Lang::$txt['personal_text'],
				'log_change' => true,
				'input_attr' => array('maxlength="50"'),
				'size' => 50,
				'permission' => 'profile_blurb',
				'input_validate' => function(&$value)
				{
					if (Utils::entityStrlen($value) > 50)
						return 'personal_text_too_long';

					return true;
				},
			),
			// This does ALL the pm settings
			'pm_prefs' => array(
				'type' => 'callback',
				'callback_func' => 'pm_settings',
				'permission' => 'pm_read',
				'preload' => function()
				{
					Utils::$context['display_mode'] = $this->pm_prefs & 3;

					Utils::$context['receive_from'] = !empty($this->pm_receive_from) ? $this->pm_receive_from : 0;

					return true;
				},
				'input_validate' => function(&$value)
				{
					// Simple validate and apply the two "sub settings"
					$value = max(min($value, 2), 0);

					$this->pm_receive_from = $this->new_data['pm_receive_from'] = max(min((int) $_POST['pm_receive_from'], 4), 0);

					return true;
				},
			),
			'posts' => array(
				'type' => 'int',
				'label' => Lang::$txt['profile_posts'],
				'log_change' => true,
				'size' => 7,
				'min' => 0,
				'max' => 2 ** 24 - 1,
				'permission' => 'moderate_forum',
				'input_validate' => function(&$value)
				{
					if (!is_numeric($value))
						return 'digits_only';

					if ($value < 0 || $value > 2 ** 24 - 1)
						return 'posts_out_of_range';

					$value = $value != '' ? strtr($value, array(',' => '', '.' => '', ' ' => '')) : 0;

					return true;
				},
			),
			'real_name' => array(
				'type' => allowedTo('profile_displayed_name_own') || allowedTo('profile_displayed_name_any') || allowedTo('moderate_forum') ? 'text' : 'label',
				'label' => Lang::$txt['name'],
				'subtext' => Lang::$txt['display_name_desc'],
				'log_change' => true,
				'input_attr' => array('maxlength="60"'),
				'permission' => 'profile_displayed_name',
				'enabled' => allowedTo('profile_displayed_name_own') || allowedTo('profile_displayed_name_any') || allowedTo('moderate_forum'),
				'input_validate' => function(&$value)
				{
					$value = trim(Utils::normalizeSpaces(Utils::sanitizeChars($value, 1, ' '), true, true, array('no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true)));

					if (trim($value) == '')
						return 'no_name';

					if (Utils::entityStrlen($value) > 60)
						return 'name_too_long';

					if ($this->name != $value && User::isReservedName($value, $this->id))
					{
						return 'name_taken';
					}

					return true;
				},
			),
			'secret_question' => array(
				'type' => 'text',
				'label' => Lang::$txt['secret_question'],
				'subtext' => Lang::$txt['secret_desc'],
				'size' => 50,
				'permission' => 'profile_password',
			),
			'secret_answer' => array(
				'type' => 'text',
				'label' => Lang::$txt['secret_answer'],
				'subtext' => Lang::$txt['secret_desc2'],
				'size' => 20,
				'postinput' => '<span class="smalltext"><a href="' . Config::$scripturl . '?action=helpadmin;help=secret_why_blank" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help"></span> ' . Lang::$txt['secret_why_blank'] . '</a></span>',
				'value' => '',
				'permission' => 'profile_password',
				'input_validate' => function(&$value)
				{
					$value = $value != '' ? hash_password($this->username, $value) : '';
					return true;
				},
			),
			'signature' => array(
				'type' => 'callback',
				'callback_func' => 'signature_modify',
				'permission' => 'profile_signature',
				'enabled' => substr(Config::$modSettings['signature_settings'], 0, 1) == 1,
				'preload' => array(array($this, 'loadSignatureData')),
				'input_validate' => __CLASS__ . '::validateSignature',
			),
			'show_online' => array(
				'type' => 'check',
				'label' => Lang::$txt['show_online'],
				'permission' => 'profile_identity',
				'enabled' => !empty(Config::$modSettings['allow_hideOnline']) || allowedTo('moderate_forum'),
			),
			'smiley_set' => array(
				'type' => 'callback',
				'callback_func' => 'smiley_pick',
				'enabled' => !empty(Config::$modSettings['smiley_sets_enable']),
				'permission' => 'profile_extra',
				'preload' => function()
				{
					Utils::$context['member']['smiley_set']['id'] = empty($this->smiley_set) ? '' : $this->smiley_set;

					Utils::$context['smiley_sets'] = explode(',', 'none,,' . Config::$modSettings['smiley_sets_known']);

					$set_names = explode("\n", Lang::$txt['smileys_none'] . "\n" . Lang::$txt['smileys_forum_board_default'] . "\n" . Config::$modSettings['smiley_sets_names']);

					$filenames = array();

					$result = Db::$db->query('', '
						SELECT f.filename, f.smiley_set
						FROM {db_prefix}smiley_files AS f
							JOIN {db_prefix}smileys AS s ON (s.id_smiley = f.id_smiley)
						WHERE s.code = {string:smiley}',
						array(
							'smiley' => ':)',
						)
					);
					while ($row = Db::$db->fetch_assoc($result))
					{
						$filenames[$row['smiley_set']] = $row['filename'];
					}
					Db::$db->free_result($result);

					// In case any sets don't contain a ':)' smiley
					$no_smiley_sets = array_diff(explode(',', Config::$modSettings['smiley_sets_known']), array_keys($filenames));

					foreach ($no_smiley_sets as $set)
					{
						$allowedTypes = array('gif', 'png', 'jpg', 'jpeg', 'tiff', 'svg');
						$images = glob(implode('/', array(Config::$modSettings['smileys_dir'], $set, '*.{' . (implode(',', $allowedTypes) . '}'))), GLOB_BRACE);

						// Just use some image or other
						if (!empty($images))
						{
							$image = array_pop($images);
							$filenames[$set] = pathinfo($image, PATHINFO_BASENAME);
						}
						// No images at all? That's no good. Let the admin know, and quietly skip for this user.
						else
						{
							Lang::load('Errors', Lang::$default);

							ErrorHandler::log(sprintf(Lang::$txt['smiley_set_dir_not_found'], $set_names[array_search($set, Utils::$context['smiley_sets'])]));

							Utils::$context['smiley_sets'] = array_filter(Utils::$context['smiley_sets'], fn($v) => $v != $set);
						}
					}

					foreach (Utils::$context['smiley_sets'] as $i => $set)
					{
						Utils::$context['smiley_sets'][$i] = array(
							'id' => Utils::htmlspecialchars($set),
							'name' => Utils::htmlspecialchars($set_names[$i]),
							'selected' => $set == Utils::$context['member']['smiley_set']['id']
						);

						if ($set === 'none')
						{
							Utils::$context['smiley_sets'][$i]['preview'] = Theme::$current->settings['images_url'] . '/blank.png';
						}
						elseif ($set === '')
						{
							$default_set = !empty(Theme::$current->settings['smiley_sets_default']) ? Theme::$current->settings['smiley_sets_default'] : Config::$modSettings['smiley_sets_default'];

							Utils::$context['smiley_sets'][$i]['preview'] = implode('/', array(Config::$modSettings['smileys_url'], $default_set, $filenames[$default_set]));
						}
						else
						{
							Utils::$context['smiley_sets'][$i]['preview'] = implode('/', array(Config::$modSettings['smileys_url'], $set, $filenames[$set]));
						}

						if (Utils::$context['smiley_sets'][$i]['selected'])
						{
							Utils::$context['member']['smiley_set']['name'] = $set_names[$i];

							Utils::$context['member']['smiley_set']['preview'] = Utils::$context['smiley_sets'][$i]['preview'];
						}

						Utils::$context['smiley_sets'][$i]['preview'] = Utils::htmlspecialchars(Utils::$context['smiley_sets'][$i]['preview']);
					}

					return true;
				},
				'input_validate' => function(&$value)
				{
					$smiley_sets = explode(',', Config::$modSettings['smiley_sets_known']);

					if (!in_array($value, $smiley_sets) && $value != 'none')
						$value = '';

					return true;
				},
			),
			// Pretty much a dummy entry - it populates all the theme settings.
			'theme_settings' => array(
				'type' => 'callback',
				'callback_func' => 'theme_settings',
				'permission' => 'profile_extra',
				'is_dummy' => true,
				'preload' => function()
				{
					Lang::load('Settings');

					Utils::$context['allow_no_censored'] = false;

					if (User::$me->is_admin || User::$me->is_owner)
					{
						Utils::$context['allow_no_censored'] = !empty(Config::$modSettings['allow_no_censored']);
					}

					return true;
				},
			),
			'tfa' => array(
				'type' => 'callback',
				'callback_func' => 'tfa',
				'permission' => 'profile_password',
				'enabled' => !empty(Config::$modSettings['tfa_mode']),
				'preload' => function()
				{
					Utils::$context['tfa_enabled'] = !empty($this->tfa_secret);

					return true;
				},
			),
			'time_format' => array(
				'type' => 'callback',
				'callback_func' => 'timeformat_modify',
				'permission' => 'profile_extra',
				'preload' => function()
				{
					Utils::$context['easy_timeformats'] = array(
						array(
							'format' => '',
							'title' => Lang::$txt['timeformat_default'],
						),
						array(
							'format' => '%B %d, %Y, %I:%M:%S %p',
							'title' => Lang::$txt['timeformat_easy1'],
						),
						array(
							'format' => '%B %d, %Y, %H:%M:%S',
							'title' => Lang::$txt['timeformat_easy2'],
						),
						array(
							'format' => '%Y-%m-%d, %H:%M:%S',
							'title' => Lang::$txt['timeformat_easy3'],
						),
						array(
							'format' => '%d %B %Y, %H:%M:%S',
							'title' => Lang::$txt['timeformat_easy4'],
						),
						array(
							'format' => '%d-%m-%Y, %H:%M:%S',
							'title' => Lang::$txt['timeformat_easy5'],
						),
					);

					Utils::$context['member']['time_format'] = $this->time_format;

					Utils::$context['current_forum_time'] = timeformat(time(), false, 'forum');

					Utils::$context['current_forum_time_js'] = smf_strftime('%Y,' . ((int) smf_strftime('%m', time()) - 1) . ',%d,%H,%M,%S', time());

					Utils::$context['current_forum_time_hour'] = (int) smf_strftime('%H', time());

					return true;
				},
			),
			'timezone' => array(
				'type' => 'select',
				'options' => smf_list_timezones(),
				'disabled_options' => array_filter(array_keys(smf_list_timezones()), 'is_int'),
				'permission' => 'profile_extra',
				'label' => Lang::$txt['timezone'],
				'value' => empty(User::$me->timezone) ? Config::$modSettings['default_timezone'] : User::$me->timezone,
				'input_validate' => function($value)
				{
					$tz = smf_list_timezones();

					if (!isset($tz[$value]))
						return 'bad_timezone';

					return true;
				},
			),
			'usertitle' => array(
				'type' => 'text',
				'label' => Lang::$txt['custom_title'],
				'log_change' => true,
				'input_attr' => array('maxlength="50"'),
				'size' => 50,
				'permission' => 'profile_title',
				'enabled' => !empty(Config::$modSettings['titlesEnable']),
				'input_validate' => function(&$value)
				{
					if (Utils::entityStrlen($value) > 50)
						return 'user_title_too_long';

					return true;
				},
			),
			'website_title' => array(
				'type' => 'text',
				'label' => Lang::$txt['website_title'],
				'subtext' => Lang::$txt['include_website_url'],
				'size' => 50,
				'permission' => 'profile_website',
				'link_with' => 'website',
				'input_validate' => function(&$value)
				{
					if (mb_strlen($value) > 250)
						return 'website_title_too_long';

					return true;
				},
			),
			'website_url' => array(
				'type' => 'url',
				'label' => Lang::$txt['website_url'],
				'subtext' => Lang::$txt['complete_url'],
				'size' => 50,
				'permission' => 'profile_website',
				// Fix the URL...
				'input_validate' => function(&$value)
				{
					if (strlen(trim($value)) > 0 && strpos($value, '://') === false)
						$value = 'http://' . $value;

					if (strlen($value) < 8 || (substr($value, 0, 7) !== 'http://' && substr($value, 0, 8) !== 'https://'))
						$value = '';

					$value = (string) validate_iri(normalize_iri($value));

					return true;
				},
				'link_with' => 'website',
			),
		);

		call_integration_hook('integrate_load_profile_fields', array(&$this->standard_fields));

		$disabled_fields = !empty(Config::$modSettings['disabled_profile_fields']) ? explode(',', Config::$modSettings['disabled_profile_fields']) : array();

		// For each of the above let's take out the bits which don't apply - to save memory and security!
		foreach ($this->standard_fields as $key => $field)
		{
			// Do we have permission to do this?
			if (isset($field['permission']) && !allowedTo((User::$me->is_owner ? array($field['permission'] . '_own', $field['permission'] . '_any') : $field['permission'] . '_any')) && !allowedTo($field['permission']))
			{
				unset($this->standard_fields[$key]);
			}

			// Is it enabled?
			if (isset($field['enabled']) && !$field['enabled'])
				unset($this->standard_fields[$key]);

			// Is it specifically disabled?
			if (in_array($key, $disabled_fields) || (isset($field['link_with']) && in_array($field['link_with'], $disabled_fields)))
			{
				unset($this->standard_fields[$key]);
			}
		}
	}

	/**
	 * Load any custom field data for this area...
	 *
	 * No area means load all, 'summary' loads all public ones.
	 *
	 * @param string $area Which area to load fields for.
	 */
	public function loadCustomFields(string $area = 'summary'): void
	{
		self::loadCustomFieldDefinitions();

		foreach (self::$custom_field_definitions as $cf_def)
		{
			// Skips custom fields that are not active.
			if (empty($cf_def['active']))
				continue;

			// Skip custom fields that don't belong in the area we are viewing.
			if (!in_array($area, array('summary', $cf_def['show_profile'])))
				continue;

			// If this is for registration, skip any that aren't shown there.
			if ($area === 'register' && empty($cf_def['show_reg']))
				continue;

			// Check the privacy level for this field.
			if ($area !== 'register' && !allowedTo('admin_forum'))
			{
				if ($cf_def['private'] >= (User::$me->is_owner ? 3 : 2))
					continue;

				if ($area !== 'summary' && $cf_def['private'] == 1)
					continue;
			}

			// Shortcut.
			$exists = !empty($this->id) && isset($this->options[$cf_def['col_name']]);

			$value = $exists ? $this->options[$cf_def['col_name']] : '';

			$cf_def['field_options'] = array_filter(explode(',', $cf_def['field_options']), 'strlen');

			$current_key = 0;
			if (!empty($cf_def['field_options']))
			{
				foreach ($cf_def['field_options'] as $k => $v)
				{
					if (empty($current_key))
						$current_key = $v === $value ? $k : 0;
				}
			}

			// If this was submitted already then make the value the posted version.
			if (isset($_POST['customfield']) && isset($_POST['customfield'][$cf_def['col_name']]))
			{
				$value = Utils::htmlspecialchars($_POST['customfield'][$cf_def['col_name']]);

				if (in_array($cf_def['field_type'], array('select', 'radio')))
					$value = $cf_def['field_options'][$value] ?? '';
			}

			// Don't show the "disabled" option for the "gender" field if we are on the "summary" area.
			if ($area == 'summary' && $cf_def['col_name'] == 'cust_gender' && $value == '{gender_0}')
				continue;

			// HTML for the input form.
			$output_html = $value;

			if ($cf_def['field_type'] == 'check')
			{
				$true = (!$exists && !empty($cf_def['default_value'])) || !empty($value);

				$input_html = '<input type="checkbox" name="customfield[' . $cf_def['col_name'] . ']" id="customfield[' . $cf_def['col_name'] . ']"' . ($true ? ' checked' : '') . '>';

				$output_html = $true ? Lang::$txt['yes'] : Lang::$txt['no'];
			}
			elseif ($cf_def['field_type'] == 'select')
			{
				$input_html = '<select name="customfield[' . $cf_def['col_name'] . ']" id="customfield[' . $cf_def['col_name'] . ']"><option value="-1"></option>';

				foreach ($cf_def['field_options'] as $k => $v)
				{
					$true = (!$exists && $cf_def['default_value'] == $v) || $value == $v;

					$input_html .= '<option value="' . $k . '"' . ($true ? ' selected' : '') . '>' . Lang::tokenTxtReplace($v) . '</option>';

					if ($true)
						$output_html = $v;
				}

				$input_html .= '</select>';
			}
			elseif ($cf_def['field_type'] == 'radio')
			{
				$input_html = '<fieldset>';

				foreach ($cf_def['field_options'] as $k => $v)
				{
					$true = (!$exists && $cf_def['default_value'] == $v) || $value == $v;

					$input_html .= '<label for="customfield_' . $cf_def['col_name'] . '_' . $k . '"><input type="radio" name="customfield[' . $cf_def['col_name'] . ']" id="customfield_' . $cf_def['col_name'] . '_' . $k . '" value="' . $k . '"' . ($true ? ' checked' : '') . '>' . Lang::tokenTxtReplace($v) . '</label><br>';

					if ($true)
						$output_html = $v;
				}

				$input_html .= '</fieldset>';
			}
			elseif ($cf_def['field_type'] == 'text')
			{
				$input_html = '<input type="text" name="customfield[' . $cf_def['col_name'] . ']" id="customfield[' . $cf_def['col_name'] . ']"' . ($cf_def['field_length'] != 0 ? ' maxlength="' . $cf_def['field_length'] . '"' : '') . ' size="' . ($cf_def['field_length'] == 0 || $cf_def['field_length'] >= 50 ? 50 : ($cf_def['field_length'] > 30 ? 30 : ($cf_def['field_length'] > 10 ? 20 : 10))) . '" value="' . un_htmlspecialchars($value) . '"' . ($cf_def['show_reg'] == 2 ? ' required' : '') . '>';
			}
			else
			{
				list($rows, $cols) = explode(',', $cf_def['default_value'] ?? '');

				$input_html = '<textarea name="customfield[' . $cf_def['col_name'] . ']" id="customfield[' . $cf_def['col_name'] . ']"' . ($cf_def['field_length'] != 0 ? ' maxlength="' . $cf_def['field_length'] . '"' : '') . (!empty($rows) ? ' rows="' . $rows . '"' : '') . (!empty($cols) ? ' cols="' . $cols . '"' : '') . ($cf_def['show_reg'] == 2 ? ' required' : '') . '>' . un_htmlspecialchars($value) . '</textarea>';
			}

			// Parse BBCode
			if ($cf_def['bbc'])
			{
				$output_html = BBCodeParser::load()->parse($output_html);
			}
			elseif ($cf_def['field_type'] == 'textarea')
			{
				// Allow for newlines at least
				$output_html = strtr($output_html, array("\n" => '<br>'));
			}

			// Enclosing the user input within some other text?
			if (!empty($cf_def['enclose']) && !empty($output_html))
			{
				$output_html = strtr($cf_def['enclose'], array(
					'{SCRIPTURL}' => Config::$scripturl,
					'{IMAGES_URL}' => Theme::$current->settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => Theme::$current->settings['default_images_url'],
					'{INPUT}' => un_htmlspecialchars($output_html),
					'{KEY}' => $current_key
				));
			}

			$this->custom_fields[] = array(
				'name' => Lang::tokenTxtReplace($cf_def['field_name']),
				'desc' => Lang::tokenTxtReplace($cf_def['field_desc']),
				'type' => $cf_def['field_type'],
				'order' => $cf_def['field_order'],
				'input_html' => $input_html,
				'output_html' => Lang::tokenTxtReplace($output_html),
				'placement' => $cf_def['placement'],
				'colname' => $cf_def['col_name'],
				'value' => $value,
				'show_reg' => $cf_def['show_reg'],
			);

			$this->custom_fields_required = $this->custom_fields_required || $cf_def['show_reg'] == 2;
		}

		Utils::$context['custom_fields'] = &$this->custom_fields;
		Utils::$context['custom_fields_required'] = &$this->custom_fields_required;

		call_integration_hook('integrate_load_custom_profile_fields', array($this->id, $area));
	}

	/**
	 * Loads the theme options for the member.
	 *
	 * @param bool $defaultSettings If true, we are loading default options.
	 */
	public function loadThemeOptions(bool $defaultSettings = false)
	{
		if (isset($_POST['default_options']))
		{
			$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];
		}

		Utils::$context['member']['options'] = $this->data['options'] ?? array();

		if (isset($_POST['options']) && is_array($_POST['options']))
		{
			foreach ($_POST['options'] as $k => $v)
				Utils::$context['member']['options'][$k] = $v;
		}

		// Load up the default theme options for any missing.
		parent::loadOptions(-1);

		foreach (parent::$profiles[-1]['options'] as $k => $v)
		{
			if (!isset(Utils::$context['member']['options'][$k]))
				Utils::$context['member']['options'][$k] = $v;
		}
	}

	/**
	 * Load avatar context data for the member whose profile is being viewed.
	 *
	 * @return bool Whether the data was loaded or not.
	 */
	public function loadAvatarData(): bool
	{
		Utils::$context['avatar_url'] = Config::$modSettings['avatar_url'];

		// Default context.
		$this->formatted['avatar'] += array(
			'custom' => stristr($this->avatar['url'], 'http://') || stristr($this->avatar['url'], 'https://') ? $this->avatar['url'] : 'http://',
			'selection' => $this->avatar['url'] == '' || (stristr($this->avatar['url'], 'http://') || stristr($this->avatar['url'], 'https://')) ? '' : $this->avatar['url'],
			'allow_server_stored' => (empty(Config::$modSettings['gravatarEnabled']) || empty(Config::$modSettings['gravatarOverride'])) && (allowedTo('profile_server_avatar') || (!User::$me->is_owner && allowedTo('profile_extra_any'))),
			'allow_upload' => (empty(Config::$modSettings['gravatarEnabled']) || empty(Config::$modSettings['gravatarOverride'])) && (allowedTo('profile_upload_avatar') || (!User::$me->is_owner && allowedTo('profile_extra_any'))),
			'allow_external' => (empty(Config::$modSettings['gravatarEnabled']) || empty(Config::$modSettings['gravatarOverride'])) && (allowedTo('profile_remote_avatar') || (!User::$me->is_owner && allowedTo('profile_extra_any'))),
			'allow_gravatar' => !empty(Config::$modSettings['gravatarEnabled']),
		);

		// Gravatar?
		if (
			$this->formatted['avatar']['allow_gravatar']
			&& (
				stristr($this->avatar['url'], 'gravatar://')
				|| !empty(Config::$modSettings['gravatarOverride'])
			)
		)
		{
			$this->formatted['avatar'] += array(
				'choice' => 'gravatar',
				'server_pic' => 'blank.png',
				'external' => $this->avatar['url'] == 'gravatar://' || empty(Config::$modSettings['gravatarAllowExtraEmail']) || (!empty(Config::$modSettings['gravatarOverride']) && substr($this->avatar['url'], 0, 11) != 'gravatar://') ? $this->email : substr($this->avatar['url'], 11)
			);
			$this->formatted['avatar']['href'] = get_gravatar_url($this->formatted['avatar']['external']);
		}
		// An attachment?
		elseif (
			$this->avatar['original_url'] == ''
			&& $this->avatar['id_attach'] > 0
			&& $this->formatted['avatar']['allow_upload']
		)
		{
			$this->formatted['avatar'] += array(
				'choice' => 'upload',
				'server_pic' => 'blank.png',
				'external' => 'http://'
			);

			$this->formatted['avatar']['href'] = !$this->avatar['custom_dir'] ? Config::$scripturl . '?action=dlattach;attach=' . $this->avatar['id_attach'] . ';type=avatar' : Config::$modSettings['custom_avatar_url'] . '/' . $this->avatar['filename'];
		}
		// External image?
		// Use "avatar_original" here so we show what the user entered even if the image proxy is enabled
		elseif (
			$this->formatted['avatar']['allow_external']
			&& (
				stristr($this->avatar['url'], 'http://')
				|| stristr($this->avatar['url'], 'https://')
			))
		{
			$this->formatted['avatar'] += array(
				'choice' => 'external',
				'server_pic' => 'blank.png',
				'external' => $this->avatar['original_url'],
			);
		}
		// Server stored image?
		elseif (
			$this->avatar['url'] != ''
			&& $this->formatted['avatar']['allow_server_stored']
			&& file_exists(Config::$modSettings['avatar_directory'] . '/' . $this->avatar['url'])
		)
		{
			$this->formatted['avatar'] += array(
				'choice' => 'server_stored',
				'server_pic' => $this->avatar['url'] == '' ? 'blank.png' : $this->avatar['url'],
				'external' => 'http://'
			);
		}
		// No avatar?
		else
		{
			$this->formatted['avatar'] += array(
				'choice' => 'none',
				'server_pic' => 'blank.png',
				'external' => 'http://'
			);
		}

		// Get a list of all the server stored avatars.
		if ($this->formatted['avatar']['allow_server_stored'])
		{
			Utils::$context['avatar_list'] = array();
			Utils::$context['avatars'] = is_dir(Config::$modSettings['avatar_directory']) ? $this->getAvatars('', 0) : array();
		}
		else
		{
			Utils::$context['avatars'] = array();
		}

		// Second level selected avatar...
		Utils::$context['avatar_selected'] = substr(strrchr($this->formatted['avatar']['server_pic'], '/'), 1);

		return !empty($this->formatted['avatar']['allow_server_stored']) || !empty($this->formatted['avatar']['allow_external']) || !empty($this->formatted['avatar']['allow_upload']) || !empty($this->formatted['avatar']['allow_gravatar']);
	}

	/**
	 * Load key signature context data.
	 *
	 * @return bool Whether the data was loaded or not.
	 */
	public function loadSignatureData(): bool
	{
		// Signature limits.
		list($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);

		Utils::$context['signature_enabled'] = isset($sig_limits[0]) ? $sig_limits[0] : 0;

		Utils::$context['signature_limits'] = array(
			'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
			'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
			'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
			'max_smileys' => isset($sig_limits[4]) ? $sig_limits[4] : 0,
			'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
			'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
			'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
			'bbc' => !empty($sig_bbc) ? explode(',', $sig_bbc) : array(),
		);

		// Kept this line in for backwards compatibility!
		Utils::$context['max_signature_length'] = Utils::$context['signature_limits']['max_length'];

		// Warning message for signature image limits?
		Utils::$context['signature_warning'] = '';

		if (Utils::$context['signature_limits']['max_image_width'] && Utils::$context['signature_limits']['max_image_height'])
		{
			Utils::$context['signature_warning'] = sprintf(Lang::$txt['profile_error_signature_max_image_size'], Utils::$context['signature_limits']['max_image_width'], Utils::$context['signature_limits']['max_image_height']);
		}
		elseif (Utils::$context['signature_limits']['max_image_width'] || Utils::$context['signature_limits']['max_image_height'])
		{
			Utils::$context['signature_warning'] = sprintf(Lang::$txt['profile_error_signature_max_image_' . (Utils::$context['signature_limits']['max_image_width'] ? 'width' : 'height')], Utils::$context['signature_limits'][Utils::$context['signature_limits']['max_image_width'] ? 'max_image_width' : 'max_image_height']);
		}

		if (empty(Utils::$context['do_preview']))
		{
			Utils::$context['member']['signature'] = empty($this->data['signature']) ? '' : str_replace(array('<br>', '<br/>', '<br />', '<', '>', '"', '\''), array("\n", "\n", "\n", '&lt;', '&gt;', '&quot;', '&#039;'), $this->data['signature']);
		}
		else
		{
			$signature = $_POST['signature'] = !empty($_POST['signature']) ? Utils::normalize($_POST['signature']) : '';

			$validation = self::validateSignature($signature);

			if (empty(Utils::$context['post_errors']))
			{
				Lang::load('Errors');
				Utils::$context['post_errors'] = array();
			}

			Utils::$context['post_errors'][] = 'signature_not_yet_saved';

			if ($validation !== true && $validation !== false)
				Utils::$context['post_errors'][] = $validation;

			Lang::censorText(Utils::$context['member']['signature']);

			Utils::$context['member']['current_signature'] = Utils::$context['member']['signature'];

			Lang::censorText($signature);

			Utils::$context['member']['signature_preview'] = BBCodeParser::load()->parse($signature, true, 'sig' . $this->id, BBCodeParser::getSigTags());

			Utils::$context['member']['signature'] = $_POST['signature'];
		}

		// Load the spell checker?
		if (Utils::$context['show_spellchecking'])
		{
			Theme::loadJavaScriptFile('spellcheck.js', array('defer' => false, 'minimize' => true), 'smf_spellcheck');
		}

		return true;
	}

	/**
	 * Handles the "manage groups" section of the profile
	 *
	 * Populates Utils::$context['member_groups'] with info about all the groups
	 * that the member whose profile we are viewing could be assigned to.
	 *
	 * @return bool Whether the data was loaded or not.
	 */
	public function loadAssignableGroups(): bool
	{
		$this->assignable_groups = array(
			0 => array(
				'id' => 0,
				'name' => Lang::$txt['no_primary_membergroup'],
				'desc' => Lang::$txt['regular_members_desc'],
				'color' => null,
				'type' => 2,
				'is_primary' => $this->data['id_group'] == 0,
				'can_be_additional' => false,
				'can_be_primary' => true,
				'can_leave' => false,
			)
		);

		self::loadUnassignableGroups();

		// Load membergroups, but only those groups the user can assign.
		$request = Db::$db->query('', '
			SELECT id_group, group_name, description, group_type, online_color, hidden
			FROM {db_prefix}membergroups
			WHERE id_group != {int:moderator_group}
				AND min_posts = {int:min_posts}' . (empty(self::$unassignable_groups) ? '' : '
				AND id_group NOT IN ({array_int:protected_groups})') . '
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE {int:newbie_group} END, group_name',
			array(
				'moderator_group' => 3,
				'min_posts' => -1,
				'protected_groups' => self::$unassignable_groups,
				'newbie_group' => 4,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$this->assignable_groups[$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'desc' => $row['description'],
				'color' => $row['online_color'],
				'type' => (int) $row['group_type'],
				'is_primary' => $this->data['id_group'] == $row['id_group'],
				'is_additional' => in_array($row['id_group'], $this->additional_groups),
				'can_be_additional' => true,
				'can_be_primary' => $row['hidden'] != 2,
				// Can't leave a private or protected group.
				// For example, if the admin added you to a group for miscreants
				// with reduced privileges, you can't just decide to leave.
				'can_leave' => !in_array($row['id_group'], self::$unassignable_groups),
			);
		}
		Db::$db->free_result($request);

		// For the templates.
		Utils::$context['member_groups'] = $this->assignable_groups;

		return true;
	}

	/**
	 * Set up the context for a page load!
	 *
	 * @param array $fields The profile fields to display. Each item should
	 *    correspond to an item in the $this->standard_fields array.
	 */
	public function setupContext(array $fields): void
	{
		// Some default bits.
		Utils::$context['profile_prehtml'] = '';
		Utils::$context['profile_posthtml'] = '';
		Utils::$context['profile_javascript'] = '';
		Utils::$context['profile_onsubmit_javascript'] = '';

		call_integration_hook('integrate_setup_profile_context', array(&$fields));

		// Make sure we have this!
		$this->loadStandardFields(true);

		// First check for any linked sets.
		foreach ($this->standard_fields as $key => $field)
		{
			if (isset($field['link_with']) && in_array($field['link_with'], $fields))
				$fields[] = $key;
		}

		$i = 0;
		$last_type = '';
		foreach ($fields as $key => $field)
		{
			if (isset($this->standard_fields[$field]))
			{
				// Shortcut.
				$cur_field = &$this->standard_fields[$field];

				// Does it have a preload and does that preload succeed?
				if (isset($cur_field['preload']) && !call_user_func(...((array) $cur_field['preload'])))
				{
					continue;
				}

				// If this is anything but complex we need to do more cleaning!
				if ($cur_field['type'] != 'callback' && $cur_field['type'] != 'hidden')
				{
					if (!isset($cur_field['label']))
					{
						$cur_field['label'] = isset(Lang::$txt[$field]) ? Lang::$txt[$field] : $field;
					}

					// Everything has a value!
					if (!isset($cur_field['value']))
					{
						$cur_field['value'] = isset($this->data[$field]) ? $this->data[$field] : '';
					}

					// Any input attributes?
					$cur_field['input_attr'] = !empty($cur_field['input_attr']) ? implode(',', $cur_field['input_attr']) : '';
				}

				// Any javascript stuff?
				if (!empty($cur_field['js_submit']))
					Utils::$context['profile_onsubmit_javascript'] .= $cur_field['js_submit'];

				if (!empty($cur_field['js']))
					Utils::$context['profile_javascript'] .= $cur_field['js'];

				// Any template stuff?
				if (!empty($cur_field['prehtml']))
					Utils::$context['profile_prehtml'] .= $cur_field['prehtml'];

				if (!empty($cur_field['posthtml']))
					Utils::$context['profile_posthtml'] .= $cur_field['posthtml'];

				// Finally put it into context?
				if ($cur_field['type'] != 'hidden')
				{
					$last_type = $cur_field['type'];
					Utils::$context['profile_fields'][$field] = &$this->standard_fields[$field];
				}
			}
			// Bodge in a line break - without doing two in a row ;)
			elseif ($field == 'hr' && $last_type != 'hr' && $last_type != '')
			{
				$last_type = 'hr';
				Utils::$context['profile_fields'][$i++]['type'] = 'hr';
			}
		}

		// Some spicy JS.
		Theme::addInlineJavaScript('
		var form_handle = document.forms.creator;
		createEventListener(form_handle);
		' . (!empty(Utils::$context['require_password']) ? '
		form_handle.addEventListener("submit", function(event)
		{
			if (this.oldpasswrd.value == "")
			{
				event.preventDefault();
				alert(' . (JavaScriptEscape(Lang::$txt['required_security_reasons'])) . ');
				return false;
			}
		}, false);' : ''), true);

		// Any onsubmit JavaScript?
		if (!empty(Utils::$context['profile_onsubmit_javascript']))
			Theme::addInlineJavaScript(Utils::$context['profile_onsubmit_javascript'], true);

		// Any totally custom stuff?
		if (!empty(Utils::$context['profile_javascript']))
			Theme::addInlineJavaScript(Utils::$context['profile_javascript'], true);
	}

	/**
	 * Saves profile data.
	 */
	public function save()
	{
		// General-purpose permission for anything that doesn't have its own.
		$this->can_change_extra = allowedTo(User::$me->is_owner ? array('profile_extra_any', 'profile_extra_own') : array('profile_extra_any'));

		// The applicator is the same as the member affected if we are registering a new member.
		$this->applicator = empty(User::$me->id) && ($_REQUEST['sa'] ?? null) === 'register' ? $this->id : User::$me->id;

		// If $_POST hasn't already been sanitized, do that now.
		if (!$this->post_sanitized)
		{
			$_POST = htmltrim__recursive($_POST);
			$_POST = htmlspecialchars__recursive($_POST);
			$this->post_sanitized = true;
		}

		// This allows variables to call activities when they save.
		Utils::$context['profile_execute_on_save'] = array();

		if (User::$me->is_owner && in_array(Menu::$loaded['profile']->current_area, array('account', 'forumprofile', 'theme')))
		{
			Utils::$context['profile_execute_on_save']['reload_user'] = array(__CLASS__ . '::reloadUser', Profile::$member->id);
		}

		$this->prepareToSaveStandardFields();
		$this->prepareToSaveIgnoreBoards();
		$this->prepareToSaveBuddyList();
		$this->prepareToSaveOptions();
		$this->prepareToSaveCustomFields($_REQUEST['sa'] ?? null);

		// Give hooks some access to the save data.
		call_integration_hook('integrate_profile_save', array(&Profile::$member->new_data, &Profile::$member->save_errors, Profile::$member->id, Profile::$member->data, Menu::$loaded['profile']->current_area));

		// There was a problem. Let them try again.
		if (!empty($this->save_errors))
		{
			// Load the language file so we can give a nice explanation of the errors.
			Lang::load('Errors');
			Utils::$context['post_errors'] = $this->save_errors;

			return;
		}

		// Save the standard profile data.
		if (!empty($this->new_data))
		{
			// If we've changed the password, notify any integration that may be listening in.
			if (isset($this->new_data['passwd']))
			{
				call_integration_hook('integrate_reset_pass', array($this->username, $this->username, $_POST['passwrd2']));
			}

			parent::updateMemberData($this->id, $this->new_data);
		}

		// Make any updates to custom fields and theme options.
		if (!empty($this->new_cf_data['updates']) || !empty($this->new_options['updates']))
		{
			Db::$db->insert('replace',
				'{db_prefix}themes',
				array(
					'id_theme' => 'int',
					'variable' => 'string-255',
					'value' => 'string-65534',
					'id_member' => 'int',
				),
				array_merge($this->new_cf_data['updates'], $this->new_options['updates']),
				array(
					'id_member',
					'id_theme',
					'variable',
				)
			);
		}

		// Delete any removed custom fields or theme options.
		if (!empty($this->new_cf_data['deletes']) || !empty($this->new_options['deletes']))
		{
			Db::$db->query('', '
				DELETE FROM {db_prefix}themes
				WHERE id_member = {int:id_member}
					AND (
						(
							id_theme = {int:id_theme}
							AND variable IN ({array_string:cf}
						)
						OR (
							id_theme != {int:id_theme}
							AND variable IN ({array_string:opt}
						)
					)',
				array(
					'id_theme' => 1,
					'id_member' => $this->id,
					'cf' => !empty($this->new_cf_data['deletes']) ? $this->new_cf_data['deletes'] : array(''),
					'opt' => !empty($this->new_options['deletes']) ? $this->new_options['deletes'] : array(''),
				)
			);
		}

		// What if this is the newest member?
		if (Config::$modSettings['latestMember'] == $this->id)
		{
			Logging::updateStats('member');
		}
		// Did the member change his/her name or birthdate?
		elseif (isset($this->new_data['birthdate']) || isset($this->new_data['real_name']))
		{
			// Changing name or birthdate both entail changing the calendar.
			$to_update = array('calendar_updated' => time());

			// Changing the name means the memberlist has been updated.
			if (isset($this->new_data['real_name']))
				$to_update['memberlist_updated'] = time();

			Config::updateModSettings($to_update);
		}

		// Anything worth logging?
		if (!empty($this->log_changes) && !empty(Config::$modSettings['modlog_enabled']))
		{
			Logging::logActions($this->log_changes);
		}

		// Do we have any post save functions to execute?
		if (!empty(Utils::$context['profile_execute_on_save']))
		{
			foreach (Utils::$context['profile_execute_on_save'] as $saveFunc)
				call_user_func(...((array) $saveFunc));
		}

		// Let them know it worked!
		Utils::$context['profile_updated'] = User::$me->is_owner ? Lang::$txt['profile_updated_own'] : sprintf(Lang::$txt['profile_updated_else'], $this->username);

		// Invalidate any cached data.
		CacheApi::put('member_data-profile-' . $this->id, null, 0);
	}

	/**
	 * Validate an email address.
	 *
	 * @param string $email The email address to validate
	 * @return bool|string True if the email is valid, otherwise a string
	 *    indicating what the problem is.
	 */
	public function validateEmail($email): bool|string
	{
		$email = strtr($email, array('&#039;' => '\''));

		// Check the name and email for validity.
		if (trim($email) == '')
			return 'no_email';

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
			return 'bad_email';

		// Email addresses should be and stay unique.
		$request = Db::$db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member != {int:selected_member}
				AND email_address = {string:email_address}
			LIMIT 1',
			array(
				'selected_member' => $this->id,
				'email_address' => $email,
			)
		);
		$return = Db::$db->num_rows($request) > 0 ? 'email_taken' : true;
		Db::$db->free_result($request);

		return $return;
	}

	/**
	 * Sanitizes and validates input for any changes to the member's groups.
	 *
	 * @param int &$value The ID of the (new) primary group.
	 * @param array $additional_groups The contents of $_POST['additional_groups'].
	 * @return bool Always returns true.
	 */
	public function validateGroups(int &$value, array $additional_groups = array()): bool
	{
		$additional_groups = array_unique($additional_groups);

		// Do we need to protect some groups?
		self::loadUnassignableGroups();

		// The account page allows you to change your id_group - but not to a protected group!
		if (!empty(self::$unassignable_groups) && in_array($value, self::$unassignable_groups) && !in_array($value, $this->groups))
		{
			$value = $this->data['id_group'];
		}

		// Find the additional membergroups (if any).
		// Never add group 0 or any protected groups that the user isn't already in.
		$additional_groups = array_diff(array_filter(array_map('intval', $additional_groups)), array_diff(self::$unassignable_groups, $this->groups));

		// Put the protected groups back in there if you don't have permission to take them away.
		$additional_groups = array_unique(array_merge($additional_groups, array_intersect($this->additional_groups, self::$unassignable_groups)));

		// Don't include the primary group in the additional groups.
		$additional_groups = array_diff($additional_groups, array($value));

		if (implode(',', $additional_groups) !== $this->data['additional_groups'])
		{
			$this->new_data['additional_groups'] = implode(',', $additional_groups);
			$this->data['additional_groups'] = implode(',', $additional_groups);
			$this->additional_groups = $additional_groups;
		}

		// Make sure there is always an admin.
		if ($this->is_admin)
		{
			$stillAdmin = $value == 1 || in_array(1, $additional_groups);

			// If they would no longer be an admin, look for another...
			if (!$stillAdmin)
			{
				$request = Db::$db->query('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
						AND id_member != {int:selected_member}
					LIMIT 1',
					array(
						'admin_group' => 1,
						'selected_member' => $this->id,
					)
				);
				list($another) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				if (empty($another))
					ErrorHandler::fatalLang('at_least_one_admin', 'critical');
			}
		}

		// If we are changing group status, update permission cache as necessary.
		if ($value != $this->data['id_group'] || isset($this->new_data['additional_groups']))
		{
			if (User::$me->is_owner)
			{
				$_SESSION['mc']['time'] = 0;
			}
			else
			{
				Config::updateModSettings(array('settings_updated' => time()));
			}
		}

		// Announce to any hooks that we have changed groups, but don't allow them to change it.
		call_integration_hook('integrate_profile_profileSaveGroups', array($value, $additional_groups));

		return true;
	}

	/**
	 * The avatar is incredibly complicated, what with the options... and what not.
	 *
	 * @param string &$value What kind of avatar we're expecting.
	 *    Can be 'none', 'server_stored', 'gravatar', 'external' or 'upload'.
	 * @return bool|string False if success (or if user ID is empty and password authentication failed), otherwise a string indicating what error occurred
	 */
	public function validateAvatarData(string &$value): bool|string
	{
		Utils::$context['max_external_size_url'] = 255;

		if (empty($this->id) && !empty(Utils::$context['password_auth_failed']))
			return false;

		call_integration_hook('before_profile_save_avatar', array(&$value));

		// External url too large
		if ($value == 'external' && allowedTo('profile_remote_avatar') && strlen($_POST['userpicpersonal']) > Utils::$context['max_external_size_url'])
		{
			return 'bad_avatar_url_too_long';
		}

		// We're going to put this in a nice custom dir.
		$upload_dir = Config::$modSettings['custom_avatar_dir'];
		$id_folder = 1;

		$downloadedExternalAvatar = false;

		if (
			$value == 'external'
			&& allowedTo('profile_remote_avatar')
			&& (
				stripos($_POST['userpicpersonal'], 'http://') === 0
				|| stripos($_POST['userpicpersonal'], 'https://') === 0
			)
			&& strlen($_POST['userpicpersonal']) > 7
			&& !empty(Config::$modSettings['avatar_download_external'])
		)
		{
			if (!is_writable($upload_dir))
				ErrorHandler::fatalLang('attachments_no_write', 'critical');

			$url = parse_iri($_POST['userpicpersonal']);

			$contents = fetch_web_data($url['scheme'] . '://' . $url['host'] . (empty($url['port']) ? '' : ':' . $url['port']) . str_replace(' ', '%20', trim($url['path'])));

			$new_filename = $upload_dir . '/' . Attachment::createHash();

			if ($contents != false && $tmpAvatar = fopen($new_filename, 'wb'))
			{
				fwrite($tmpAvatar, $contents);
				fclose($tmpAvatar);

				$downloadedExternalAvatar = true;
				$_FILES['attachment']['tmp_name'] = $new_filename;
			}
		}

		// Removes whatever attachment there was before updating
		if ($value == 'none')
		{
			$this->new_data['avatar'] = '';

			// Reset the attach ID.
			$this->data['id_attach'] = 0;
			$this->data['attachment_type'] = 0;
			$this->data['filename'] = '';

			Attachment::remove(array('id_member' => $this->id));
		}
		// An avatar from the server-stored galleries.
		elseif ($value == 'server_stored' && allowedTo('profile_server_avatar'))
		{
			$this->new_data['avatar'] = strtr(empty($_POST['file']) ? (empty($_POST['cat']) ? '' : $_POST['cat']) : $_POST['file'], array('&amp;' => '&'));

			$this->new_data['avatar'] = preg_match('~^([\w _!@%*=\-#()\[\]&.,]+/)?[\w _!@%*=\-#()\[\]&.,]+$~', $this->new_data['avatar']) != 0 && preg_match('/\.\./', $this->new_data['avatar']) == 0 && file_exists(Config::$modSettings['avatar_directory'] . '/' . $this->new_data['avatar']) ? ($this->new_data['avatar'] == 'blank.png' ? '' : $this->new_data['avatar']) : '';

			// Clear current profile...
			$this->data['id_attach'] = 0;
			$this->data['attachment_type'] = 0;
			$this->data['filename'] = '';

			// Get rid of their old avatar. (if uploaded.)
			Attachment::remove(array('id_member' => $this->id));
		}
		elseif ($value == 'gravatar' && !empty(Config::$modSettings['gravatarEnabled']))
		{
			// One wasn't specified, or it's not allowed to use extra email addresses, or it's not a valid one, reset to default Gravatar.
			if (
				empty($_POST['gravatarEmail'])
				|| empty(Config::$modSettings['gravatarAllowExtraEmail'])
				|| !filter_var($_POST['gravatarEmail'], FILTER_VALIDATE_EMAIL)
			)
			{
				$this->new_data['avatar'] = 'gravatar://';
			}
			else
			{
				$this->new_data['avatar'] = 'gravatar://' . ($_POST['gravatarEmail'] != $this->data['email_address'] ? $_POST['gravatarEmail'] : '');
			}

			// Get rid of their old avatar. (if uploaded.)
			Attachment::remove(array('id_member' => $this->id));
		}
		elseif (
			$value == 'external'
			&& allowedTo('profile_remote_avatar')
			&& (
				stripos($_POST['userpicpersonal'], 'http://') === 0
				|| stripos($_POST['userpicpersonal'], 'https://') === 0
			)
			&& empty(Config::$modSettings['avatar_download_external'])
		)
		{
			// We need these clean...
			$this->data['id_attach'] = 0;
			$this->data['attachment_type'] = 0;
			$this->data['filename'] = '';

			// Remove any attached avatar...
			Attachment::remove(array('id_member' => $this->id));

			$this->new_data['avatar'] = str_replace(' ', '%20', preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $_POST['userpicpersonal']));

			$mime_type = get_mime_type($this->new_data['avatar'], true);
			$mime_valid = strpos($mime_type, 'image/') === 0;

			if ($this->new_data['avatar'] == 'http://' || $this->new_data['avatar'] == 'http:///')
			{
				$this->new_data['avatar'] = '';
			}
			// Trying to make us do something we'll regret?
			elseif (substr($this->new_data['avatar'], 0, 7) != 'http://' && substr($this->new_data['avatar'], 0, 8) != 'https://')
			{
				return 'bad_avatar_invalid_url';
			}
			elseif (empty($mime_valid))
			{
				return 'bad_avatar';
			}
			// SVGs are special.
			elseif ($mime_type === 'image/svg+xml')
			{
				$safe = false;

				if (($tempfile = @tempnam($upload_dir, 'tmp_')) !== false && ($svg_content = @fetch_web_data($this->new_data['avatar'])) !== false && (file_put_contents($tempfile, $svg_content)) !== false)
				{
					require_once(Config::$sourcedir . '/Subs-Graphics.php');

					$safe = checkSvgContents($tempfile);
					@unlink($tempfile);
				}

				if (!$safe)
					return 'bad_avatar';
			}
			// Should we check dimensions?
			elseif (!empty(Config::$modSettings['avatar_max_height_external']) || !empty(Config::$modSettings['avatar_max_width_external']))
			{
				// Now let's validate the avatar.
				$sizes = url_image_size($this->new_data['avatar']);

				if (
					is_array($sizes)
					&& (
						(
							$sizes[0] > Config::$modSettings['avatar_max_width_external']
							&& !empty(Config::$modSettings['avatar_max_width_external'])
						)
						|| (
							$sizes[1] > Config::$modSettings['avatar_max_height_external']
							&& !empty(Config::$modSettings['avatar_max_height_external'])
						)
					)
				)
				{
					// Houston, we have a problem. The avatar is too large!!
					if (Config::$modSettings['avatar_action_too_large'] == 'option_refuse')
						return 'bad_avatar_too_large';

					if (Config::$modSettings['avatar_action_too_large'] == 'option_download_and_resize')
					{
						if ($this->downloadAvatar($this->new_data['avatar'], Config::$modSettings['avatar_max_width_external'], Config::$modSettings['avatar_max_height_external']))
						{
							$this->new_data['avatar'] = '';
							$this->data['id_attach'] = $this->new_avatar_data['id'];
							$this->data['filename'] = $this->new_avatar_data['filename'];
							$this->data['attachment_type'] = $this->new_avatar_data['type'];
						}
						else
						{
							return 'bad_avatar';
						}
					}
				}
			}
		}
		elseif (($value == 'upload' && allowedTo('profile_upload_avatar')) || $downloadedExternalAvatar)
		{
			if ((isset($_FILES['attachment']['name']) && $_FILES['attachment']['name'] != '') || $downloadedExternalAvatar)
			{
				// Get the dimensions of the image.
				if (!$downloadedExternalAvatar)
				{
					if (!is_writable($upload_dir))
						ErrorHandler::fatalLang('avatars_no_write', 'critical');

					$new_filename = $upload_dir . '/' . Attachment::createHash();

					if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $new_filename))
						ErrorHandler::fatalLang('attach_timeout', 'critical');

					$_FILES['attachment']['tmp_name'] = $new_filename;
				}

				$mime_type = get_mime_type($_FILES['attachment']['tmp_name'], true);
				$mime_valid = strpos($mime_type, 'image/') === 0;

				$sizes = empty($mime_valid) ? false : @getimagesize($_FILES['attachment']['tmp_name']);

				// SVGs are special.
				if ($mime_type === 'image/svg+xml')
				{
					require_once(Config::$sourcedir . '/Subs-Graphics.php');

					if ((checkSvgContents($_FILES['attachment']['tmp_name'])) === false)
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}

					$extension = 'svg';
					$dest_name = 'avatar_' . $this->id . '_' . time() . '.' . $extension;
					list($width, $height) = getSvgSize($_FILES['attachment']['tmp_name']);
					$file_hash = '';

					Attachment::remove(array('id_member' => $this->id));

					$this->data['id_attach'] = Db::$db->insert('',
						'{db_prefix}attachments',
						array(
							'id_member' => 'int',
							'attachment_type' => 'int',
							'filename' => 'string',
							'file_hash' => 'string',
							'fileext' => 'string',
							'size' => 'int',
							'width' => 'int',
							'height' => 'int',
							'mime_type' => 'string',
							'id_folder' => 'int',
						),
						array(
							$this->id,
							1,
							$dest_name,
							$file_hash,
							$extension,
							filesize($_FILES['attachment']['tmp_name']),
							(int) $width,
							(int) $height,
							$mime_type,
							$id_folder,
						),
						array('id_attach'),
						1
					);

					$this->data['filename'] = $dest_name;
					$this->data['attachment_type'] = 1;

					$destination_path = $upload_dir . '/' . $dest_name;
					if (!rename($_FILES['attachment']['tmp_name'], $destination_path))
					{
						Attachment::remove(array('id_member' => $this->id));
						ErrorHandler::fatalLang('attach_timeout', 'critical');
					}

					smf_chmod($destination_path, 0644);
				}
				// No size, then it's probably not a valid pic.
				elseif ($sizes === false)
				{
					@unlink($_FILES['attachment']['tmp_name']);
					return 'bad_avatar';
				}
				// Check whether the image is too large.
				elseif (
					(
						!empty(Config::$modSettings['avatar_max_width_upload'])
						&& $sizes[0] > Config::$modSettings['avatar_max_width_upload']
					)
					|| (
						!empty(Config::$modSettings['avatar_max_height_upload'])
						&& $sizes[1] > Config::$modSettings['avatar_max_height_upload']
					)
				)
				{
					if (!empty(Config::$modSettings['avatar_resize_upload']))
					{
						// Attempt to chmod it.
						smf_chmod($_FILES['attachment']['tmp_name'], 0644);

						if (!$this->downloadAvatar($_FILES['attachment']['tmp_name'], Config::$modSettings['avatar_max_width_upload'], Config::$modSettings['avatar_max_height_upload']))
						{
							@unlink($_FILES['attachment']['tmp_name']);
							return 'bad_avatar';
						}

						// Reset attachment avatar data.
						$this->data['id_attach'] = $this->new_avatar_data['id'];
						$this->data['filename'] = $this->new_avatar_data['filename'];
						$this->data['attachment_type'] = $this->new_avatar_data['type'];
					}
					// Admin doesn't want to resize large avatars, can't do much about it but to tell you to use a different one :(
					else
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar_too_large';
					}
				}
				// So far, so good, checks lies ahead!
				elseif (is_array($sizes))
				{
					// Now try to find an infection.
					require_once(Config::$sourcedir . '/Subs-Graphics.php');

					if (!checkImageContents($_FILES['attachment']['tmp_name'], !empty(Config::$modSettings['avatar_paranoid'])))
					{
						// It's bad. Try to re-encode the contents?
						if (empty(Config::$modSettings['avatar_reencode']) || (!reencodeImage($_FILES['attachment']['tmp_name'], $sizes[2])))
						{
							@unlink($_FILES['attachment']['tmp_name']);
							return 'bad_avatar_fail_reencode';
						}

						// We were successful. However, at what price?
						$sizes = @getimagesize($_FILES['attachment']['tmp_name']);

						// Hard to believe this would happen, but can you bet?
						if ($sizes === false)
						{
							@unlink($_FILES['attachment']['tmp_name']);
							return 'bad_avatar';
						}
					}

					$extensions = array(
						'1' => 'gif',
						'2' => 'jpg',
						'3' => 'png',
						'6' => 'bmp'
					);

					$extension = isset($extensions[$sizes[2]]) ? $extensions[$sizes[2]] : 'bmp';

					$dest_name = 'avatar_' . $this->id . '_' . time() . '.' . $extension;

					list($width, $height) = getimagesize($_FILES['attachment']['tmp_name']);

					$file_hash = '';

					// Remove previous attachments this member might have had.
					Attachment::remove(array('id_member' => $this->id));

					$this->data['id_attach'] = Db::$db->insert('',
						'{db_prefix}attachments',
						array(
							'id_member' => 'int',
							'attachment_type' => 'int',
							'filename' => 'string',
							'file_hash' => 'string',
							'fileext' => 'string',
							'size' => 'int',
							'width' => 'int',
							'height' => 'int',
							'mime_type' => 'string',
							'id_folder' => 'int',
						),
						array(
							$this->id,
							1,
							$dest_name,
							$file_hash,
							$extension,
							filesize($_FILES['attachment']['tmp_name']),
							(int) $width,
							(int) $height,
							$mime_type,
							$id_folder,
						),
						array('id_attach'),
						1
					);

					$this->data['filename'] = $dest_name;
					$this->data['attachment_type'] = 1;

					$destination_path = $upload_dir . '/' . (empty($file_hash) ? $dest_name : $this->data['id_attach'] . '_' . $file_hash . '.dat');

					if (!rename($_FILES['attachment']['tmp_name'], $destination_path))
					{
						// I guess a man can try.
						Attachment::remove(array('id_member' => $this->id));
						ErrorHandler::fatalLang('attach_timeout', 'critical');
					}

					// Attempt to chmod it.
					smf_chmod($destination_path, 0644);
				}

				$this->new_data['avatar'] = '';

				// Delete any temporary file.
				if (file_exists($_FILES['attachment']['tmp_name']))
					@unlink($_FILES['attachment']['tmp_name']);
			}
			// Selected the upload avatar option and had one already uploaded before or didn't upload one.
			else
			{
				$this->new_data['avatar'] = '';
			}
		}
		elseif ($value == 'gravatar' && allowedTo('profile_gravatar_avatar'))
		{
			$this->new_data['avatar'] = 'gravatar://www.gravatar.com/avatar/' . md5(strtolower(trim($this->data['email_address'])));
		}
		else
		{
			$this->new_data['avatar'] = '';
		}

		// Setup the profile variables so it shows things right on display!
		$this->data['avatar'] = $this->new_data['avatar'];

		call_integration_hook('after_profile_save_avatar');

		return false;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads an array of users by ID, member_name, or email_address.
	 *
	 * In practice, this will typically only be called without any arguments,
	 * in which case it will load based on $_REQUEST['user'], $_REQUEST['u'],
	 * or else User::$me->id.
	 *
	 * @param mixed $users Users specified by ID, name, or email address.
	 *    If not set, will load based on $_REQUEST['user'], $_REQUEST['u'], or
	 *    else User::$me->id.
	 * @param int $type Whether $users contains IDs, names, or email addresses.
	 *    Possible values are this class's LOAD_BY_* constants.
	 *    If $users is not set, this will be ignored.
	 * @param string $dataset Ignored.
	 * @return array The IDs of the loaded members.
	 */
	public static function load($users = array(), int $type = self::LOAD_BY_ID, string $dataset = null): array
	{
		$users = (array) $users;

		if (empty($users))
		{
			// Did we get the user by name...
			if (isset($_REQUEST['user']))
			{
				$users = (array) $_REQUEST['user'];
				$type = self::LOAD_BY_NAME;
			}
			// ... or by id_member?
			elseif (!empty($_REQUEST['u']))
			{
				$users = array_map('intval', (array) $_REQUEST['u']);
			}
			// If it was just ?action=profile, edit your own profile, but only if you're not a guest.
			else
			{
				// Members only...
				is_not_guest();
				$users = array(User::$me->id);
			}
		}

		$loaded_ids = parent::loadUserData($users, $type, 'profile');

		foreach (array_diff($loaded_ids, array_keys(self::$loaded)) as $id)
			new self($id);

		return $loaded_ids;
	}

	/**
	 * Populates self::$custom_field_definitions.
	 */
	public static function loadCustomFieldDefinitions(): void
	{
		if (!empty(self::$custom_field_definitions))
			return;

		$request = Db::$db->query('', '
			SELECT *
			FROM {db_prefix}custom_fields
			ORDER BY field_order',
			array()
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			self::$custom_field_definitions[$row['col_name']] = $row;
		}
		Db::$db->free_result($request);
	}

	/**
	 * Populates self::$unassignable_groups with the IDs of any groups that the
	 * current user cannot assign.
	 */
	public static function loadUnassignableGroups(): void
	{
		// Admins can assign any group they like.
		if (allowedTo('admin_forum'))
		{
			self::$unassignable_groups = array();
			return;
		}

		// No need to do this twice.
		if (isset(self::$unassignable_groups))
			return;

		// Obviously, protect the admin group.
		self::$unassignable_groups = array(1);

		// Prevent privilege escalation.
		$protected_permissions = array();
		foreach (array('admin_forum', 'manage_membergroups', 'manage_permissions') as $permission)
		{
			if (!allowedTo($permission))
				$protected_permissions[] = $permission;
		}

		$request = Db::$db->query('', '
			SELECT id_group
			FROM {db_prefix}permissions
			WHERE permission IN ({array_string:protected})
				AND add_deny = {int:add}',
			array(
				'protected' => $protected_permissions,
				'add' => 1,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			self::$unassignable_groups[] = (int) $row['id_group'];
		}
		Db::$db->free_result($request);

		// Find any other groups that are designated as protected.
		$request = Db::$db->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type IN ({array_int:is_protected})
				OR min_posts > -1',
			array(
				'is_protected' => !allowedTo('manage_membergroups') ? array(0, 1) : array(1),
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			self::$unassignable_groups[] = (int) $row['id_group'];
		}
		Db::$db->free_result($request);

		self::$unassignable_groups = array_unique(self::$unassignable_groups);
	}

	/**
	 * Validate the signature.
	 *
	 * This is static to make it easy to validate signatures during preview.
	 *
	 * @param string &$value The new signature
	 * @return bool|string True if the signature passes the checks, otherwise
	 *    a string indicating what the problem is.
	 */
	public static function validateSignature(&$value): bool|string
	{
		// Admins can do whatever they hell they want!
		if (!allowedTo('admin_forum'))
		{
			// Load all the signature limits.
			list($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);
			$sig_limits = explode(',', $sig_limits);
			$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : array();

			$unparsed_signature = strtr(un_htmlspecialchars($value), array("\r" => '', '&#039' => '\''));

			// Too many lines?
			if (!empty($sig_limits[2]) && substr_count($unparsed_signature, "\n") >= $sig_limits[2])
			{
				Lang::$txt['profile_error_signature_max_lines'] = sprintf(Lang::$txt['profile_error_signature_max_lines'], $sig_limits[2]);

				return 'signature_max_lines';
			}

			// Too many images?!
			if (!empty($sig_limits[3]) && (substr_count(strtolower($unparsed_signature), '[img') + substr_count(strtolower($unparsed_signature), '<img')) > $sig_limits[3])
			{
				Lang::$txt['profile_error_signature_max_image_count'] = sprintf(Lang::$txt['profile_error_signature_max_image_count'], $sig_limits[3]);

				return 'signature_max_image_count';
			}

			// What about too many smileys!
			$smiley_parsed = BBCodeParser::load()->parseSmileys($unparsed_signature);

			$smiley_count = substr_count(strtolower($smiley_parsed), '<img') - substr_count(strtolower($unparsed_signature), '<img');

			if (!empty($sig_limits[4]) && $sig_limits[4] == -1 && $smiley_count > 0)
			{
				return 'signature_allow_smileys';
			}
			elseif (!empty($sig_limits[4]) && $sig_limits[4] > 0 && $smiley_count > $sig_limits[4])
			{
				Lang::$txt['profile_error_signature_max_smileys'] = sprintf(Lang::$txt['profile_error_signature_max_smileys'], $sig_limits[4]);

				return 'signature_max_smileys';
			}

			// Maybe we are abusing font sizes?
			if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)~i', $unparsed_signature, $matches) !== false && isset($matches[2]))
			{
				foreach ($matches[1] as $ind => $size)
				{
					$limit_broke = 0;

					// Attempt to allow all sizes of abuse, so to speak.
					if ($matches[2][$ind] == 'px' && $size > $sig_limits[7])
					{
						$limit_broke = $sig_limits[7] . 'px';
					}
					elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75))
					{
						$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
					}
					elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16))
					{
						$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
					}
					elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18)
					{
						$limit_broke = 'large';
					}

					if ($limit_broke)
					{
						Lang::$txt['profile_error_signature_max_font_size'] = sprintf(Lang::$txt['profile_error_signature_max_font_size'], $limit_broke);

						return 'signature_max_font_size';
					}
				}
			}

			// The difficult one - image sizes! Don't error on this - just fix it.
			if ((!empty($sig_limits[5]) || !empty($sig_limits[6])))
			{
				// Get all BBC tags...
				preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br>)*([^<">]+?)(?:<br>)*\[/img\]~i', $unparsed_signature, $matches);

				// ... and all HTML ones.
				preg_match_all('~<img\s+src=(?:")?((?:http://|ftp://|https://|ftps://).+?)(?:")?(?:\s+alt=(?:")?(.*?)(?:")?)?(?:\s?/)?' . '>~i', $unparsed_signature, $matches2, PREG_PATTERN_ORDER);

				// And stick the HTML in the BBC.
				if (!empty($matches2))
				{
					foreach ($matches2[0] as $ind => $dummy)
					{
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

				$replaces = array();

				// Try to find all the images!
				if (!empty($matches))
				{
					foreach ($matches[0] as $key => $image)
					{
						$width = -1;
						$height = -1;

						// Does it have predefined restraints? Width first.
						if ($matches[6][$key])
							$matches[2][$key] = $matches[6][$key];

						if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
						{
							$width = $sig_limits[5];
							$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
						}
						elseif ($matches[2][$key])
						{
							$width = $matches[2][$key];
						}

						// ... and height.
						if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
						{
							$height = $sig_limits[6];

							if ($width != -1)
								$width = $width * ($height / $matches[4][$key]);
						}
						elseif ($matches[4][$key])
						{
							$height = $matches[4][$key];
						}

						// If the dimensions are still not fixed - we need to check the actual image.
						if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
						{
							$sizes = url_image_size($matches[7][$key]);

							if (is_array($sizes))
							{
								// Too wide?
								if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
								{
									$width = $sig_limits[5];
									$sizes[1] = $sizes[1] * ($width / $sizes[0]);
								}

								// Too high?
								if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
								{
									$height = $sig_limits[6];

									if ($width == -1)
										$width = $sizes[0];

									$width = $width * ($height / $sizes[1]);
								}
								elseif ($width != -1)
								{
									$height = $sizes[1];
								}
							}
						}

						// Did we come up with some changes? If so remake the string.
						if ($width != -1 || $height != -1)
						{
							$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
						}
					}

					if (!empty($replaces))
					{
						$value = str_replace(array_keys($replaces), array_values($replaces), $value);
					}
				}
			}

			// Any disabled BBC?
			$disabledSigBBC = implode('|', $disabledTags);
			if (!empty($disabledSigBBC))
			{
				if (preg_match('~\[(' . $disabledSigBBC . '[ =\]/])~i', $unparsed_signature, $matches) !== false && isset($matches[1]))
				{
					$disabledTags = array_unique($disabledTags);

					Lang::$txt['profile_error_signature_disabled_bbc'] = sprintf(Lang::$txt['profile_error_signature_disabled_bbc'], implode(', ', $disabledTags));

					return 'signature_disabled_bbc';
				}
			}
		}

		Msg::preparsecode($value);

		// Too long?
		if (!allowedTo('admin_forum') && !empty($sig_limits[1]) && Utils::entityStrlen(str_replace('<br>', "\n", $value)) > $sig_limits[1])
		{
			$_POST['signature'] = trim(Utils::htmlspecialchars(str_replace('<br>', "\n", $value), ENT_QUOTES));

			Lang::$txt['profile_error_signature_max_length'] = sprintf(Lang::$txt['profile_error_signature_max_length'], $sig_limits[1]);

			return 'signature_max_length';
		}

		return true;
	}

	/**
	 * Backward compatibilty wrapper for the loadAssignableGroups() method.
	 *
	 * @param int $id ID number of the member whose profile is being viewed.
	 * @return true Always returns true
	 */
	public static function backcompat_profileLoadGroups(int $id = null)
	{
		if (!isset(self::$loaded[$id]))
			self::load($id);

		self::$loaded[$id]->loadAssignableGroups();
		return true;
	}

	/**
	 * Backward compatibilty wrapper for the loadStandardFields() method.
	 *
	 * @param bool $force_reload Whether to reload the data.
	 * @param int $id The ID of the member.
	 */
	public static function backcompat_loadProfileFields($force_reload = false, int $id = null): void
	{
		if (!isset(self::$loaded[$id]))
			self::load($id);

		self::$loaded[$id]->loadStandardFields($force_reload);
	}

	/**
	 * Backward compatibilty wrapper for the loadCustomFields() method.
	 *
	 * @param int $id The ID of the member.
	 * @param string $area Which area to load fields for.
	 */
	public static function backcompat_loadCustomFields(int $id, string $area = 'summary'): void
	{
		if (!isset(self::$loaded[$id]))
			self::load($id);

		self::$loaded[$id]->loadCustomFields($area);
	}

	/**
	 * Backward compatibilty wrapper for the loadThemeOptions() method.
	 *
	 * @param int $id The ID of the member.
	 * @param bool $defaultSettings If true, we are loading default options.
	 */
	public static function backcompat_loadThemeOptions(int $id, bool $defaultSettings = false)
	{
		if (!isset(self::$loaded[$id]))
			self::load($id);

		self::$loaded[$id]->loadThemeOptions($defaultSettings);
	}

	/**
	 * Backward compatibilty wrapper for the setupContext() method.
	 *
	 * @param array $fields The profile fields to display. Each item should
	 *    correspond to an item in the Profile::$member->standard_fields array.
	 * @param int $id The ID of the member.
	 */
	public static function backcompat_setupProfileContext(array $fields, int $id): void
	{
		if (!isset(self::$loaded[$id]))
			self::load($id);

		self::$member->setupContext($fields);
	}

	/**
	 * Backward compatibilty wrapper for the save() method.
	 * Deals with changes to custom fields in particular.
	 *
	 * @param int $id The ID of the member
	 * @param string $area The area of the profile these fields are in.
	 * @param bool $sanitize = true Whether or not to sanitize the data.
	 * @param bool $return_errors Whether or not to return any error information.
	 * @return void|array Returns nothing or returns an array of error info if $return_errors is true.
	 */
	public static function backcompat_makeCustomFieldChanges($id, $area, $sanitize = true, $return_errors = false)
	{
		if (!isset(self::$loaded[$id]))
			self::load($id);

		$_REQUEST['sa'] = $area;
		self::$member->post_sanitized = !$sanitize;
		self::$member->save();

		if (!empty($return_errors))
			return $this->cf_save_errors;
	}
	/**
	 * Backward compatibilty wrapper for the save() method.
	 * Deals with changes to theme options in particular.
	 *
	 * @param int $id The ID of the user
	 * @param int $id_theme The ID of the theme
	 */
	public static function backcompat_makeThemeChanges($id, $id_theme)
	{
		if (!isset(self::$loaded[$id]))
			self::load($id);

		self::$member->new_data['id_theme'] = $id_theme;
		self::$member->save();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected in order to force instantiation via self::load().
	 *
	 * @param int $id The ID number of the user.
	 */
	protected function __construct(int $id)
	{
		parent::__construct($id, 'profile');

		self::$loaded[$this->id] = $this;

		if (empty(self::$member->id))
		{
			self::$member = $this;
			self::$memID = $this->id;
		}

		$this->data = &parent::$profiles[$this->id];

		// Let's have some information about this member ready, too.
		$this->format();
		Utils::$context['member'] = &$this->formatted;
		Utils::$context['id_member'] = $id;

		// Is this the profile of the user himself or herself?
		parent::$me->is_owner = $this->id === parent::$me->id;

		// Backward compatibility.
		self::$cur_profile = &self::$member->data;
		self::$profile_fields = &$this->standard_fields;
		self::$profile_vars = &$this->new_data;
		self::$post_errors = &$this->save_errors;
	}

	/**
	 * Sanitizes and validates input for any changes to the standard fields.
	 */
	protected function prepareToSaveStandardFields(): void
	{
		$this->loadStandardFields();

		// Cycle through the profile fields working out what to do!
		foreach ($this->standard_fields as $key => $field)
		{
			if (!isset($_POST[$key]) || !empty($field['is_dummy']) || (isset($_POST['preview_signature']) && $key == 'signature'))
			{
				continue;
			}

			// Make sure there are no evil characters in the input.
			$_POST[$key] = Utils::sanitizeChars(Utils::normalize($_POST[$key]), in_array($key, array('member_name', 'real_name')) ? 1 : 0);

			// What gets updated?
			$db_key = isset($field['save_key']) ? $field['save_key'] : $key;

			// Right - we have something that is enabled, we can act upon and has a value posted to it. Does it have a validation function?
			if (isset($field['input_validate']))
			{
				$is_valid = $field['input_validate']($_POST[$key], $this->id);

				// An error occurred - set it as such!
				if ($is_valid !== true)
				{
					// Is this an actual error?
					if ($is_valid !== false)
					{
						$this->save_errors[$key] = $is_valid;
						$this->standard_fields[$key]['is_error'] = $is_valid;
					}

					continue;
				}
			}

			// Are we doing a cast?
			$field['cast_type'] = empty($field['cast_type']) ? $field['type'] : $field['cast_type'];

			// Finally, clean up certain types.
			if ($field['cast_type'] == 'int')
			{
				$_POST[$key] = (int) $_POST[$key];
			}
			elseif ($field['cast_type'] == 'float')
			{
				$_POST[$key] = (float) $_POST[$key];
			}
			elseif ($field['cast_type'] == 'check')
			{
				$_POST[$key] = (int) !empty($_POST[$key]);
			}

			// If we got here we're doing OK.
			if ($field['type'] != 'hidden' && (!isset($this->data[$key]) || $_POST[$key] != $this->data[$key]))
			{
				// Set the save variable.
				$this->new_data[$db_key] = $_POST[$key];

				// And update the user profile.
				$this->data[$key] = $this->new_data[$key];

				// Are we logging it?
				if (!empty($field['log_change']) && isset($this->data[$key]))
				{
					$this->log_changes[] = array(
						'action' => $key,
						'log_type' => 'user',
						'extra' => array(
							'previous' => $this->data[$key],
							'new' => $this->new_data[$db_key],
							'applicator' => $this->applicator,
							'member_affected' => $this->id,
						),
					);
				}
			}

			// Logging group changes are a bit different...
			if ($key == 'id_group' && $field['log_change'])
			{
				$this->loadAssignableGroups();

				// Any changes to primary group?
				if (isset($this->new_data['id_group']) && $this->new_data['id_group'] != $this->group_id)
				{
					$this->log_changes[] = array(
						'action' => 'id_group',
						'log_type' => 'user',
						'extra' => array(
							'previous' => !empty($this->data[$key]) && isset(Utils::$context['member_groups'][$this->data[$key]]) ? Utils::$context['member_groups'][$this->data[$key]]['name'] : '',
							'new' => !empty($this->new_data[$key]) && isset(Utils::$context['member_groups'][$this->new_data[$key]]) ? Utils::$context['member_groups'][$this->new_data[$key]]['name'] : '',
							'applicator' => $this->applicator,
							'member_affected' => $this->id,
						),
					);
				}

				// Prepare additional groups for comparison.
				$additional_groups = array(
					'previous' => !empty($this->additional_groups) ? $this->additional_groups : array(),
					'new' => !empty($this->new_data['additional_groups']) ? array_diff(explode(',', $this->new_data['additional_groups']), array(0)) : array(),
				);

				sort($additional_groups['previous']);
				sort($additional_groups['new']);

				// What about additional groups?
				if ($additional_groups['previous'] != $additional_groups['new'])
				{
					foreach ($additional_groups as $type => $groups)
					{
						foreach ($groups as $id => $group)
						{
							if (isset(Utils::$context['member_groups'][$group]))
							{
								$additional_groups[$type][$id] = Utils::$context['member_groups'][$group]['name'];
							}
							else
							{
								unset($additional_groups[$type][$id]);
							}
						}
						$additional_groups[$type] = implode(', ', $additional_groups[$type]);
					}

					$this->log_changes[] = array(
						'action' => 'additional_groups',
						'log_type' => 'user',
						'extra' => array(
							'previous' => $additional_groups['previous'],
							'new' => $additional_groups['new'],
							'applicator' => $this->applicator,
							'member_affected' => $this->id,
						),
					);
				}
			}
		}
	}

	/**
	 * Sanitizes input for any changes to the ignored boards.
	 */
	protected function prepareToSaveIgnoreBoards(): void
	{
		if (!$this->can_change_extra || ($_POST['sa'] ?? null) !== 'ignoreboards')
			return;

		// Whatever it is set to is a dirty filthy thing. Kinda like our minds.
		unset($_POST['ignore_boards']);

		if (empty($_POST['brd']))
			$_POST['brd'] = array();

		if (!is_array($_POST['brd']))
			$_POST['brd'] = array($_POST['brd']);

		$this->new_data['ignore_boards'] = implode(',', array_unique(array_filter(array_map('intval', $_POST['brd']))));

		unset($_POST['brd']);
	}

	/**
	 * Sanitizes input for any changes to the buddy list.
	 */
	protected function prepareToSaveBuddyList(): void
	{
		if (!$this->can_change_extra || !isset($_POST['buddy_list']))
			return;

		$this->new_data['buddy_list'] = implode(',', array_unique(array_filter(array_map('intval', explode(',', $_POST['buddy_list'])))));
	}

	/**
	 * Sanitizes and validates input for any changes to the custom fields.
	 */
	protected function prepareToSaveCustomFields(string $area = null): void
	{
		if (!$this->can_change_extra || !isset($area))
			return;

		$deletes = array();
		$errors = array();

		self::loadCustomFieldDefinitions();

		foreach (self::$custom_field_definitions as $cf_def)
		{
			$mask_error = false;

			// Skips custom fields that are not active.
			if (empty($cf_def['active']))
				continue;

			// Skip custom fields that don't belong in the area we are viewing.
			if ($area !== $cf_def['show_profile'])
				continue;

			// If this is for registration, skip any that aren't shown there.
			if ($area === 'register' && empty($cf_def['show_reg']))
				continue;

			// Check the privacy level for this field.
			if ($area !== 'register' && !allowedTo('admin_forum'))
			{
				// If not the admin or the owner, cannot modify.
				if (!User::$me->is_owner)
					continue;

				// Owners can only modify levels 0 and 2.
				if (!in_array($cf_def['private'], array(0, 2)))
					continue;
			}

			// Validate the user data.
			if ($cf_def['field_type'] == 'check')
			{
				$value = (int) isset($_POST['customfield'][$cf_def['col_name']]);
			}
			elseif ($cf_def['field_type'] == 'select' || $cf_def['field_type'] == 'radio')
			{
				$value = $cf_def['default_value'];

				foreach (explode(',', $cf_def['field_options']) as $k => $v)
				{
					if (isset($_POST['customfield'][$cf_def['col_name']]) && $_POST['customfield'][$cf_def['col_name']] == $k)
					{
						$value = $v;
					}
				}
			}
			// Otherwise some form of text!
			else
			{
				$value = $_POST['customfield'][$cf_def['col_name']] ?? '';

				if ($cf_def['field_length'])
					$value = Utils::entitySubstr($value, 0, $cf_def['field_length']);

				// Any masks?
				if ($cf_def['field_type'] == 'text' && !empty($cf_def['mask']) && $cf_def['mask'] != 'none')
				{
					$value = Utils::htmlTrim($value);
					$valueReference = un_htmlspecialchars($value);

					// Try and avoid some checks. '0' could be a valid non-empty value.
					if (empty($value) && !is_numeric($value))
						$value = '';

					if ($cf_def['mask'] == 'nohtml' && ($valueReference != strip_tags($valueReference) || $value != Utils::htmlspecialchars($value, ENT_NOQUOTES) || preg_match('/<(.+?)[\s]*\/?[\s]*>/si', $valueReference)))
					{
						$mask_error = 'custom_field_nohtml_fail';
						$value = '';
					}
					elseif ($cf_def['mask'] == 'email' && !empty($value) && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 255))
					{
						$mask_error = 'custom_field_mail_fail';
						$value = '';
					}
					elseif ($cf_def['mask'] == 'number')
					{
						$value = (int) $value;
					}
					elseif (substr($cf_def['mask'], 0, 5) == 'regex' && trim($value) != '' && preg_match(substr($cf_def['mask'], 5), $value) === 0)
					{
						$mask_error = 'custom_field_regex_fail';
						$value = '';
					}

					unset($valueReference);
				}
			}

			if (!isset($this->options[$cf_def['col_name']]))
				$this->options[$cf_def['col_name']] = '';

			// If they tried to set a bad value, report the error.
			if (is_string($mask_error))
			{
				$this->cf_save_errors[] = $mask_error;
			}
			// Did it change?
			elseif ($this->options[$cf_def['col_name']] != $value)
			{
				$this->log_changes[] = array(
					'action' => 'customfield_' . $cf_def['col_name'],
					'log_type' => 'user',
					'extra' => array(
						'previous' => !empty($this->options[$cf_def['col_name']]) ? $this->options[$cf_def['col_name']] : '',
						'new' => $value,
						'applicator' => $this->applicator,
						'member_affected' => $this->id,
					),
				);

				if (empty($value))
				{
					// Even though id_theme and id_member will be the same in
					// every element of this array, we include them for the sake
					// of backward compatibility with what the hook expects.
					$deletes[] = array(
						'id_theme' => 1,
						'variable' => $cf_def['col_name'],
						'id_member' => $this->id,
					);

					unset($this->options[$cf_def['col_name']]);
				}
				else
				{
					$this->new_cf_data['updates'][] = array(
						1,
						$cf_def['col_name'],
						$value,
						$this->id,
					);

					$this->options[$cf_def['col_name']] = $value;
				}
			}
		}

		// The true in the hook params replaces an obsolete $returnErrors variable.
		// The !self::$member->post_sanitized replaces an obsolete $sanitize variable.
		$hook_errors = call_integration_hook('integrate_save_custom_profile_fields', array(
			&$this->new_cf_data['updates'],
			&$this->log_changes,
			&$this->cf_save_errors,
			true,
			$this->id,
			$area,
			!self::$member->post_sanitized,
			&$deletes,
		));

		// Now that the hook is done, we can set deletes to just the value we need.
		$this->new_cf_data['deletes'] = array_map(fn($del) => $del['variable'], $deletes);

		if (!empty($hook_errors) && is_array($hook_errors))
			$this->cf_save_errors = array_merge($this->cf_save_errors, $hook_errors);

		$this->save_errors = array_merge($this->save_errors, $this->cf_save_errors);
	}

	/**
	 * Sanitizes and validates input for any changes to the theme options.
	 */
	protected function prepareToSaveOptions(): void
	{
		if (!$this->can_change_extra)
			return;

		$id_theme = (int) ($this->new_data['id_theme'] ?? $this->theme);

		// Can't change reserved vars.
		foreach (array('options', 'default_options') as $key)
		{
			if (!isset($_POST[$key]))
				continue;

			if (count(array_intersect(array_keys($_POST[$key]), self::RESERVED_VARS)) != 0)
				ErrorHandler::fatalLang('no_access', false);
		}

		self::loadCustomFieldDefinitions();

		// These are the theme changes...
		if (isset($_POST['options']) && is_array($_POST['options']))
		{
			foreach ($_POST['options'] as $opt => $val)
			{
				if (isset(self::$custom_field_definitions[$opt]))
					continue;

				// We don't set this per theme anymore.
				if ($opt == 'allow_no_censored')
					continue;

				// These need to be controlled.
				if ($opt == 'topics_per_page' || $opt == 'messages_per_page')
					$val = min(max($val, 0), 50);

				$this->new_options['updates'][] = array(
					$id_theme,
					$opt,
					is_array($val) ? implode(',', $val) : $val,
					$this->id,
				);
			}
		}

		if (isset($_POST['default_options']) && is_array($_POST['default_options']))
		{
			foreach ($_POST['default_options'] as $opt => $val)
			{
				if (isset(self::$custom_field_definitions[$opt]))
					continue;

				// Only let admins and owners change the censor.
				if ($opt == 'allow_no_censored' && !User::$me->is_admin && !User::$me->is_owner)
					continue;

				// These need to be controlled.
				if ($opt == 'topics_per_page' || $opt == 'messages_per_page')
					$val = min(max($val, 0), 50);

				$this->new_options['updates'][] = array(
					1,
					$opt,
					is_array($val) ? implode(',', $val) : $val,
					$this->id,
				);

				// If this option appeared in any themes besides the default, remove it.
				$this->new_options['deletes'][] = $opt;
			}
		}
	}

	/**
	 * Recursive function to retrieve server-stored avatar files.
	 *
	 * @param string $directory The directory to look for files in.
	 * @param int $level How many levels we should go in the directory.
	 * @return array An array of information about the files and directories found.
	 */
	protected function getAvatars(string $directory, int $level = 0): array
	{
		$result = array();

		$dirs = array();
		$files = array();

		// Open the directory..
		$dir = dir(Config::$modSettings['avatar_directory'] . (!empty($directory) ? '/' : '') . $directory);

		if (!$dir)
			return array();

		while ($line = $dir->read())
		{
			if (in_array($line, array('.', '..', 'blank.png', 'index.php')))
				continue;

			if (is_dir(Config::$modSettings['avatar_directory'] . '/' . $directory . (!empty($directory) ? '/' : '') . $line))
			{
				$dirs[] = $line;
			}
			else
			{
				$files[] = $line;
			}
		}
		$dir->close();

		// Sort the results...
		natcasesort($dirs);
		natcasesort($files);

		if ($level == 0)
		{
			$result[] = array(
				'filename' => 'blank.png',
				'checked' => in_array(Utils::$context['member']['avatar']['server_pic'], array('', 'blank.png')),
				'name' => Lang::$txt['no_pic'],
				'is_dir' => false
			);
		}

		foreach ($dirs as $line)
		{
			$tmp = $this->getAvatars($directory . (!empty($directory) ? '/' : '') . $line, $level + 1);

			if (!empty($tmp))
			{
				$result[] = array(
					'filename' => Utils::htmlspecialchars($line),
					'checked' => strpos(Utils::$context['member']['avatar']['server_pic'], $line . '/') !== false,
					'name' => '[' . Utils::htmlspecialchars(str_replace('_', ' ', $line)) . ']',
					'is_dir' => true,
					'files' => $tmp
				);
			}

			unset($tmp);
		}

		foreach ($files as $line)
		{
			$filename = substr($line, 0, (strlen($line) - strlen(strrchr($line, '.'))));
			$extension = substr(strrchr($line, '.'), 1);

			// Make sure it is an image.
			// @todo Change this to use MIME type.
			if (
				strcasecmp($extension, 'gif') != 0
				&& strcasecmp($extension, 'jpg') != 0
				&& strcasecmp($extension, 'jpeg') != 0
				&& strcasecmp($extension, 'png') != 0
				&& strcasecmp($extension, 'bmp') != 0
			)
			{
				continue;
			}

			$result[] = array(
				'filename' => Utils::htmlspecialchars($line),
				'checked' => $line == Utils::$context['member']['avatar']['server_pic'],
				'name' => Utils::htmlspecialchars(str_replace('_', ' ', $filename)),
				'is_dir' => false
			);

			if ($level == 1)
				Utils::$context['avatar_list'][] = $directory . '/' . $line;
		}

		return $result;
	}

	/**
	 * Downloads a file from a URL and stores it locally for avatar use by the
	 * member.
	 *
	 * @param string $url The full path to the temporary file
	 * @param int $max_width The maximum allowed width for the avatar
	 * @param int $max_height The maximum allowed height for the avatar
	 * @return boolean Whether the download and resize was successful.
	 *
	 */
	protected function downloadAvatar($url, $max_width, $max_height): bool
	{
		require_once(Config::$sourcedir . '/Subs-Graphics.php');

		$ext = !empty(Config::$modSettings['avatar_download_png']) ? 'png' : 'jpeg';
		$preferred_format = !empty(Config::$modSettings['avatar_download_png']) ? 3 : 0;
		$dest_name = 'avatar_' . $this->id . '_' . time() . '.' . $ext;
		$dest_path = Config::$modSettings['custom_avatar_dir'] . '/' . $dest_name . '.tmp';

		// Resize it. Bail out if it fails.
		if (!resizeImageFile($url, $dest_path, $max_width, $max_height, $preferred_format))
		{
			@unlink($dest_path);
			return false;
		}

		// Remove the .tmp extension from the attachment. Bail out if it fails.
		if (!rename($dest_path, Config::$modSettings['custom_avatar_dir'] . '/' . $dest_name))
		{
			@unlink($dest_path);
			return false;
		}

		// Remove the .tmp extension.
		$dest_path = Config::$modSettings['custom_avatar_dir'] . '/' . $dest_name;

		// Get dimensions and MIME type.
		list($width, $height) = getimagesize($dest_path);
		$mime_type = get_mime_type($dest_path, true);

		// Remove old avatar.
		Attachment::remove(array('id_member' => $this->id));

		// Add the new avatar to our records.
		$attach_id = Db::$db->insert('',
			'{db_prefix}attachments',
			array(
				'id_member' => 'int',
				'attachment_type' => 'int',
				'filename' => 'string-255',
				'file_hash' => 'string-255',
				'fileext' => 'string-8',
				'size' => 'int',
				'id_folder' => 'int',
				'width' => 'int',
				'height' => 'int',
				'mime_type' => 'string-127',
			),
			array(
				$this->id,
				1,
				$dest_name,
				'',
				$ext,
				filesize($dest_path),
				1,
				(int) $width,
				(int) $height,
				$mime_type,
			),
			array('id_attach'),
			1
		);

		// Let other methods know about the new avatar.
		$this->new_avatar_data = array(
			'id' => $attach_id,
			'filename' => $dest_name,
			'type' => 1,
		);

		return true;
	}

	/**
	 * Send the user a new activation email if they need to reactivate!
	 */
	protected function sendActivation(): void
	{
		// Shouldn't happen but just in case.
		if (empty($this->new_data['email_address']))
			return;

		$replacements = array(
			'ACTIVATIONLINK' => Config::$scripturl . '?action=activate;u=' . $this->id . ';code=' . $this->new_data['validation_code'],
			'ACTIVATIONCODE' => $this->new_data['validation_code'],
			'ACTIVATIONLINKWITHOUTCODE' => Config::$scripturl . '?action=activate;u=' . $this->id,
		);

		// Send off the email.
		$emaildata = Mail::loadEmailTemplate('activate_reactivate', $replacements, empty(User::$profiles[$this->id]['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : User::$profiles[$this->id]['lngfile']);

		Mail::send($this->new_data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'reactivate', $emaildata['is_html'], 0);

		// Log the user out.
		Db::$db->query('', '
			DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:selected_member}',
			array(
				'selected_member' => $this->id,
			)
		);

		$_SESSION['log_time'] = 0;
		$_SESSION['login_' . Config::$cookiename] = Utils::jsonEncode(array(0, '', 0));

		if (isset($_COOKIE[Config::$cookiename]))
			$_COOKIE[Config::$cookiename] = '';

		User::setMe(0);

		redirectexit('action=sendactivation');
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Reload a user's settings.
	 */
	protected static function reloadUser(int $memID): void
	{
		if ($memID == User::$me->id && isset($_POST['passwrd2']) && $_POST['passwrd2'] != '')
		{
			setLoginCookie(60 * Config::$modSettings['cookieTime'], User::$me->id, hash_salt($_POST['passwrd1'], User::$me->password_salt));
		}

		User::reload($memID, 'profile');

		User::$me->logOnline();
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Profile::exportStatic'))
	Profile::exportStatic();

// Old mods might include this file to get access to functions that have been moved.
class_exists('\\SMF\\Actions\\Profile\\Main');
class_exists('\\SMF\\Actions\\Profile\\Popup');
class_exists('\\SMF\\Actions\\Profile\\AlertsPopup');

?>