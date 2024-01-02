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
use SMF\Lang;
use SMF\Utils;

/**
 * Toggles email notification preferences for announcements.
 */
class NotifyAnnouncements extends Notify implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'AnnouncementsNotify',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The notification type that this action handles.
	 */
	public string $type = 'announcements';

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

	/**
	 * This does nothing for announcments.
	 */
	protected function setId()
	{
	}

	/**
	 * Converts $_GET['sa'] to $_GET['mode'].
	 *
	 * sa=on/off is used for email subscribe/unsubscribe links.
	 */
	protected function saToMode()
	{
		if (!isset($_GET['mode']) && isset($_GET['sa'])) {
			$_GET['mode'] = $_GET['sa'] == 'on' ? 3 : 0;
			unset($_GET['sa']);
		}
	}

	/**
	 * Sets $this->mode.
	 */
	protected function setMode()
	{
		$this->saToMode();

		// What do we do?  Better ask if they didn't say..
		if (!isset($_GET['mode'])) {
			$this->ask();
		} else {
			$this->mode = $_GET['mode'];
		}
	}

	/**
	 * Sets any additional data needed for the ask template.
	 */
	protected function askTemplateData()
	{
		Utils::$context['sub_template'] = 'notify_announcements';
	}

	/**
	 * Updates the notification preference in the database.
	 */
	protected function changePref()
	{
		// Get the preference like normal, but then drop the alert bit.
		$this->setAlertPref();
		$this->alert_pref = $this->alert_pref & parent::PREF_EMAIL;

		// Update their announcement notification preference.
		parent::setNotifyPrefs((int) $this->member_info['id'], ['announcements' => $this->alert_pref]);

		// Show a confirmation message.
		Utils::$context['sub_template'] = 'notify_pref_changed';
		Utils::$context['notify_success_msg'] = sprintf(Lang::$txt['notify_announcements' . (!empty($this->alert_pref) ? '_subscribed' : '_unsubscribed')], $this->member_info['email']);
	}

	/**
	 * Gets the success message to display.
	 */
	protected function getSuccessMsg()
	{
		return sprintf(Lang::$txt['notify_announcements' . (!empty($this->mode) ? '_subscribed' : '_unsubscribed')], $this->member_info['email']);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\NotifyAnnouncements::exportStatic')) {
	NotifyAnnouncements::exportStatic();
}

?>