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

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Authentication\OidcClient;
use SMF\Authentication\Provider;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Lets the admin set up the identity providers members can sign in with.
 */
class Authentication implements ActionInterface
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
	public string $subaction = 'list';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'list' => 'providerList',
		'edit' => 'edit',
		'save' => 'save',
		'delete' => 'delete',
		'test' => 'test',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		User::$me->isAllowedTo('admin_forum');

		Theme::loadTemplate('Authentication');

		Utils::$context['page_title'] = Lang::getTxt('authentication_providers', file: 'ManageSettings');

		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::getTxt('authentication_providers', file: 'ManageSettings'),
			'description' => Lang::getTxt('authentication_providers_desc', file: 'ManageSettings'),
		];

		$call = \is_string(self::$subactions[$this->subaction]) && method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			\call_user_func($call);
		}
	}

	/**
	 * Shows every configured provider.
	 */
	public function providerList(): void
	{
		Utils::$context['sub_template'] = 'authentication_list';
		Utils::$context['providers'] = Provider::loadAll();
		Utils::$context['presets'] = Provider::presets();
	}

	/**
	 * Shows the form for one provider.
	 */
	public function edit(): void
	{
		$provider = Provider::load((int) ($_REQUEST['provider'] ?? 0)) ?? new Provider();

		// Starting from a preset just fills the form in; nothing is saved yet.
		if ($provider->id === 0 && !empty($_REQUEST['preset'])) {
			$preset = Provider::presets()[$_REQUEST['preset']] ?? [];

			foreach ($preset as $field => $value) {
				$provider->{$field} = $value;
			}
		}

		Utils::$context['sub_template'] = 'authentication_edit';
		Utils::$context['provider'] = $provider;
		Utils::$context['redirect_uri'] = $provider->id === 0
			? Lang::getTxt('authentication_redirect_uri_pending', file: 'ManageSettings')
			: $provider->redirectUri();

		SecurityToken::create('admin-authp');
	}

	/**
	 * Saves one provider.
	 */
	public function save(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-authp');

		$provider = Provider::load((int) ($_REQUEST['provider'] ?? 0)) ?? new Provider();

		$provider->title = Utils::htmlTrim($_POST['title'] ?? '');
		$provider->issuer = Utils::htmlTrim($_POST['issuer'] ?? '');
		$provider->client_id = Utils::htmlTrim($_POST['client_id'] ?? '');
		$provider->scopes = Utils::htmlTrim($_POST['scopes'] ?? 'openid email profile');
		$provider->enabled = !empty($_POST['enabled']);
		$provider->order = (int) ($_POST['provider_order'] ?? 0);

		// An empty secret box means "leave it alone", so that editing a provider
		// does not require retyping a secret the admin may not have to hand.
		if (($_POST['client_secret'] ?? '') !== '') {
			$provider->client_secret = $_POST['client_secret'];
		}

		$provider->settings['link_by_verified_email'] = !empty($_POST['link_by_verified_email']);
		$provider->settings['allow_registration'] = !empty($_POST['allow_registration']);
		$provider->settings['allow_private_host'] = !empty($_POST['allow_private_host']);

		if ($provider->title === '' || $provider->issuer === '') {
			ErrorHandler::fatalLang('authentication_needs_title_and_issuer', false);
		}

		// The issuer moved, so whatever we discovered about the old one is junk.
		$provider->settings['discovery'] = [];
		$provider->settings['discovered_at'] = 0;

		$provider->save();

		Utils::redirectexit('action=admin;area=authentication;saved');
	}

	/**
	 * Removes a provider, and every credential that came from it.
	 */
	public function delete(): void
	{
		User::$me->checkSession('get');

		$provider = Provider::load((int) ($_REQUEST['provider'] ?? 0));

		if ($provider !== null) {
			$provider->delete();
		}

		Utils::redirectexit('action=admin;area=authentication;deleted');
	}

	/**
	 * Fetches the discovery document, so the admin can see it working.
	 */
	public function test(): void
	{
		User::$me->checkSession('get');

		$provider = Provider::load((int) ($_REQUEST['provider'] ?? 0));

		if ($provider === null) {
			Utils::redirectexit('action=admin;area=authentication');
		}

		$client = new OidcClient($provider);
		$document = $client->discover(true);

		Utils::$context['sub_template'] = 'authentication_test';
		Utils::$context['provider'] = $provider;
		Utils::$context['test_error'] = $client->error;
		Utils::$context['test_endpoints'] = $document === [] ? [] : [
			'authorization_endpoint' => $document['authorization_endpoint'] ?? '',
			'token_endpoint' => $document['token_endpoint'] ?? '',
			'userinfo_endpoint' => $document['userinfo_endpoint'] ?? '',
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}
}
