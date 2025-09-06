<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

namespace SMF\AntiSpam\APIs;

use SMF\AntiSpam\AntiSpamAgent;
use SMF\AntiSpam\AntiSpamInterface;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Theme;

/**
 * Blank field in play.
 */
class BlankField extends AntiSpamAgent implements AntiSpamInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $empty_field;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isConfigured(): bool
	{
		return true;
	}

	/**
	 *
	 */
	public function create(?array $options = []): bool
	{
		if (empty($_SESSION[$this->sessionID()]['empty_field'])) {
			$this->setup();
		}

		Theme::addInlineCss('.vv_special { display: none; }');

		return true;
	}

	public function html(): void
	{
		echo '
				<div class="smalltext vv_special">
					', Lang::getTxt('visual_verification_hidden', file: 'General'), '
					<input type="text" name="', $_SESSION[$this->sessionID()]['empty_field'], '" autocomplete="off" size="30" value="">
				</div>';
	}

	/**
	 *
	 */
	public function validate(?array $options = []): array|bool
	{
		// Hmm, it's requested but not actually declared. This shouldn't happen.
		if (empty($_SESSION[$this->sessionID()]['empty_field'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// While we're here, did the user do something bad?
		if (!empty($_REQUEST[$_SESSION[$this->sessionID()]['empty_field']])) {
			return ['wrong_verification_answer'];
		}

		return true;
	}

	public function refresh(bool $only_if_necessary, bool $do_test): bool
	{
		if (parent::shouldRefresh($only_if_necessary, $do_test)) {
			$this->setup();
		}

		return false;
	}

	/******************
	 * Internal methods
	 ******************/

	private function setup(): void
	{
		// We're building a field that lives in the template, that we hope to be empty later. But at least we give it a believable name.
		$terms = ['gadget', 'device', 'uid', 'gid', 'guid', 'uuid', 'unique', 'identifier'];

		$second_terms = ['hash', 'cipher', 'code', 'key', 'unlock', 'bit', 'value'];

		$start = random_int(0, 27);

		$hash = bin2hex(random_bytes(2));

		$_SESSION[$this->sessionID()]['empty_field'] = $terms[array_rand($terms)] . '-' . $second_terms[array_rand($second_terms)] . '-' . $hash;
	}
}
