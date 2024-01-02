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
use SMF\Config;
use SMF\PageIndex;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows a popup to search for members.
 *
 * Called by index.php?action=findmember.
 * Uses sub-template find_members of the Help template.
 *
 * @deprecated 3.0 An unused leftover from SMF 2.0's wap2/imode support.
 * @todo This was already unused in SMF 2.1. Maybe just remove in 3.0?
 */
class FindMember implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'JSMembers',
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
		User::$me->checkSession('get');

		// Why is this in the Help template, you ask?  Well, erm... it helps you.  Does that work?
		Theme::loadTemplate('Help');

		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'find_members';

		if (isset($_REQUEST['search'])) {
			Utils::$context['last_search'] = Utils::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);
		} else {
			$_REQUEST['start'] = 0;
		}

		// Allow the user to pass the input to be added to to the box.
		Utils::$context['input_box_name'] = isset($_REQUEST['input']) && preg_match('~^[\w-]+$~', $_REQUEST['input']) === 1 ? $_REQUEST['input'] : 'to';

		// Take the delimiter over GET in case it's \n or something.
		Utils::$context['delimiter'] = isset($_REQUEST['delim']) ? ($_REQUEST['delim'] == 'LB' ? "\n" : $_REQUEST['delim']) : ', ';
		Utils::$context['quote_results'] = !empty($_REQUEST['quote']);

		// List all the results.
		Utils::$context['results'] = [];

		// Some buddy related settings ;)
		Utils::$context['show_buddies'] = !empty(User::$me->buddies);
		Utils::$context['buddy_search'] = isset($_REQUEST['buddies']);

		// If the user has done a search, well - search.
		if (isset($_REQUEST['search'])) {
			$_REQUEST['search'] = Utils::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);

			Utils::$context['results'] = User::find([$_REQUEST['search']], true, Utils::$context['buddy_search']);
			$total_results = count(Utils::$context['results']);

			Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=findmember;search=' . Utils::$context['last_search'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';input=' . Utils::$context['input_box_name'] . (Utils::$context['quote_results'] ? ';quote=1' : '') . (Utils::$context['buddy_search'] ? ';buddies' : ''), $_REQUEST['start'], $total_results, 7);

			// Determine the navigation context.
			$base_url = Config::$scripturl . '?action=findmember;search=' . urlencode(Utils::$context['last_search']) . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
			Utils::$context['links'] = [
				'first' => $_REQUEST['start'] >= 7 ? $base_url . ';start=0' : '',
				'prev' => $_REQUEST['start'] >= 7 ? $base_url . ';start=' . ($_REQUEST['start'] - 7) : '',
				'next' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . ($_REQUEST['start'] + 7) : '',
				'last' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . (floor(($total_results - 1) / 7) * 7) : '',
				'up' => Config::$scripturl . '?action=pm;sa=send' . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']),
			];
			Utils::$context['page_info'] = [
				'current_page' => $_REQUEST['start'] / 7 + 1,
				'num_pages' => floor(($total_results - 1) / 7) + 1,
			];

			Utils::$context['results'] = array_slice(Utils::$context['results'], $_REQUEST['start'], 7);
		} else {
			Utils::$context['links']['up'] = Config::$scripturl . '?action=pm;sa=send' . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']);
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\FindMember::exportStatic')) {
	FindMember::exportStatic();
}

?>