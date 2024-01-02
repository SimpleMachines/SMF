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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\BrowserDetector;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * Displays the COPPA form during registration.
 */
class CoppaForm implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'CoppaForm',
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
	 * Display the contact information for the forum, as well a form to fill in.
	 */
	public function execute(): void
	{
		// Get the user details...
		$request = Db::$db->query(
			'',
			'SELECT member_name
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
				AND is_activated = {int:is_coppa}',
			[
				'id_member' => (int) $_GET['member'],
				'is_coppa' => 5,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			Db::$db->free_result($request);
			ErrorHandler::fatalLang('no_access', false);
		}
		list($username) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (isset($_GET['form'])) {
			// Some simple contact stuff for the forum.
			Utils::$context['forum_contacts'] = (!empty(Config::$modSettings['coppaPost']) ? Config::$modSettings['coppaPost'] . '<br><br>' : '') . (!empty(Config::$modSettings['coppaFax']) ? Config::$modSettings['coppaFax'] . '<br>' : '');
			Utils::$context['forum_contacts'] = !empty(Utils::$context['forum_contacts']) ? Utils::$context['forum_name_html_safe'] . '<br>' . Utils::$context['forum_contacts'] : '';

			// Showing template?
			if (!isset($_GET['dl'])) {
				// Shortcut for producing underlines.
				Utils::$context['ul'] = '<u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u>';
				Utils::$context['template_layers'] = [];
				Utils::$context['sub_template'] = 'coppa_form';
				Utils::$context['page_title'] = sprintf(Lang::$txt['coppa_form_title'], Utils::$context['forum_name_html_safe']);
				Utils::$context['coppa_body'] = str_replace(['{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}'], [Utils::$context['ul'], Utils::$context['ul'], $username], sprintf(Lang::$txt['coppa_form_body'], Utils::$context['forum_name_html_safe']));
			}
			// Downloading.
			else {
				// The data.
				$ul = '                ';
				$crlf = "\r\n";
				$data = Utils::$context['forum_contacts'] . $crlf . Lang::$txt['coppa_form_address'] . ':' . $crlf . Lang::$txt['coppa_form_date'] . ':' . $crlf . $crlf . $crlf . sprintf(Lang::$txt['coppa_form_body'], Utils::$context['forum_name_html_safe']);
				$data = str_replace(['{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}', '<br>', '<br>'], [$ul, $ul, $username, $crlf, $crlf], $data);

				// Send the headers.
				header('connection: close');
				header('content-disposition: attachment; filename="approval.txt"');
				header('content-type: ' . (BrowserDetector::isBrowser('ie') || BrowserDetector::isBrowser('opera') ? 'application/octetstream' : 'application/octet-stream'));
				header('content-length: ' . count($data));

				echo $data;
				Utils::obExit(false);
			}
		} else {
			Utils::$context += [
				'page_title' => Lang::$txt['coppa_title'],
				'sub_template' => 'coppa',
			];

			Utils::$context['coppa'] = [
				'body' => str_replace('{MINIMUM_AGE}', Config::$modSettings['coppaAge'], sprintf(Lang::$txt['coppa_after_registration'], Utils::$context['forum_name_html_safe'])),
				'many_options' => !empty(Config::$modSettings['coppaPost']) && !empty(Config::$modSettings['coppaFax']),
				'post' => empty(Config::$modSettings['coppaPost']) ? '' : Config::$modSettings['coppaPost'],
				'fax' => empty(Config::$modSettings['coppaFax']) ? '' : Config::$modSettings['coppaFax'],
				'phone' => empty(Config::$modSettings['coppaPhone']) ? '' : str_replace('{PHONE_NUMBER}', Config::$modSettings['coppaPhone'], Lang::$txt['coppa_send_by_phone']),
				'id' => $_GET['member'],
			];
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		Lang::load('Login');
		Theme::loadTemplate('Register');

		// No User ID??
		if (!isset($_GET['member'])) {
			ErrorHandler::fatalLang('no_access', false);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\CoppaForm::exportStatic')) {
	CoppaForm::exportStatic();
}

?>