<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\AntiSpam\AntiSpam;
use SMF\AntiSpam\APIs\ImageVerfication;
use SMF\Config;
use SMF\Routable;
use SMF\User;
use SMF\Uuid;

/**
 * Shows the verification code or let it be heard.
 *
 * TrueType fonts supplied by www.LarabieFonts.com.
 */
class VerificationCode implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/*********************
	 * Internal properties
	 *********************/

	protected string $form_id;

	protected Uuid $agent_id;

	/****************
	 * Public methods
	 ****************/

	public function isRestrictedGuestAccessAllowed(): bool
	{
		return true;
	}

	public function canBeLogged(): bool
	{
		return false;
	}

	/**
	 * Do the job.
	 */
	public function execute(): void
	{
		// Random image for testing?
		if (User::$me->is_admin && isset($_GET['rand'], $_GET['agent'])) {
			[$this->form_id, $this->agent_id] = AntiSpam::setupTestCode($_GET['rand'], $_GET['agent']);
		}
		// Ensure some backwards compatbility and render a ImageVerification if we didn't specify an agent.
		elseif (Config::$backward_compatibility && empty($this->agent_id)) {
			$_SESSION[$this->form_id . '_vv'] ??= [];
			$_SESSION[$this->form_id . '_vv']['agents'] ??= [];
			$_SESSION[$this->form_id . '_vv']['agents'][(string) Uuid::create()] = basename(ImageVerfication::class);
		}

		AntiSpam::showCode($this->form_id, $this->agent_id);

		// We all die one day...
		die();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		$this->form_id = $_GET['vid'] ?? '';
		$this->agent_id = $_GET['aid'] ?? Uuid::create();
	}
}
