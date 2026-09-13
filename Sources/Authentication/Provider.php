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

namespace SMF\Authentication;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Utils;

/**
 * One configured identity provider that members can sign in with.
 */
class Provider
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This provider's ID.
	 */
	public int $id = 0;

	/**
	 * @var string
	 *
	 * Which protocol this provider speaks. Only 'oidc' is implemented.
	 */
	public string $type = 'oidc';

	/**
	 * @var string
	 *
	 * What the button on the login form says.
	 */
	public string $title = '';

	/**
	 * @var string
	 *
	 * The issuer URL. Everything else is discovered from it.
	 */
	public string $issuer = '';

	/**
	 * @var string
	 *
	 * The client ID this forum was registered with.
	 */
	public string $client_id = '';

	/**
	 * @var string
	 *
	 * The client secret this forum was registered with.
	 */
	public string $client_secret = '';

	/**
	 * @var string
	 *
	 * Space separated scopes to ask for. Must include 'openid'.
	 */
	public string $scopes = 'openid email profile';

	/**
	 * @var bool
	 *
	 * Whether this provider is offered on the login form.
	 */
	public bool $enabled = false;

	/**
	 * @var int
	 *
	 * Sort order on the login form.
	 */
	public int $order = 0;

	/**
	 * @var array
	 *
	 * Anything else: the cached discovery document, and the policy switches
	 * described by self::defaultSettings().
	 */
	public array $settings = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $row A row from the auth_providers table, if we have one.
	 */
	public function __construct(array $row = [])
	{
		if ($row === []) {
			$this->settings = self::defaultSettings();

			return;
		}

		$this->id = (int) $row['id_provider'];
		$this->type = $row['provider_type'];
		$this->title = $row['title'];
		$this->issuer = $row['issuer'];
		$this->client_id = $row['client_id'];
		$this->client_secret = $row['client_secret'];
		$this->scopes = $row['scopes'];
		$this->enabled = !empty($row['enabled']);
		$this->order = (int) $row['provider_order'];
		// array_merge, not +: with + the left hand side wins for keys present in
		// both, which would quietly discard everything that was saved.
		$this->settings = array_merge(self::defaultSettings(), (array) Utils::jsonDecode($row['settings'] ?? '', true));
	}

	/**
	 * Whether this provider has enough filled in to attempt a sign in.
	 *
	 * @return bool Whether it does.
	 */
	public function isUsable(): bool
	{
		return $this->issuer !== '' && $this->client_id !== '' && $this->client_secret !== '';
	}

	/**
	 * Where the identity provider sends the member back to.
	 *
	 * Registered with the provider, so it has to be stable and exact. Built
	 * from Config::$boardurl rather than the current request, because the two
	 * can differ and only one of them was registered.
	 *
	 * @return string The redirect URI.
	 */
	public function redirectUri(): string
	{
		return Config::$boardurl . '/index.php?action=authext;sa=callback;provider=' . $this->id;
	}

	/**
	 * Saves this provider, inserting it if it is new.
	 *
	 * @return int This provider's ID.
	 */
	public function save(): int
	{
		$columns = [
			'provider_type' => 'string',
			'title' => 'string',
			'issuer' => 'string',
			'client_id' => 'string',
			'client_secret' => 'string',
			'scopes' => 'string',
			'enabled' => 'int',
			'provider_order' => 'int',
			'settings' => 'string',
		];

		$values = [
			$this->type,
			$this->title,
			rtrim($this->issuer, '/'),
			$this->client_id,
			$this->client_secret,
			$this->scopes,
			(int) $this->enabled,
			$this->order,
			json_encode($this->settings),
		];

		if ($this->id === 0) {
			$this->id = Db::$db->insert(
				'insert',
				'{db_prefix}auth_providers',
				$columns,
				[$values],
				['id_provider'],
				Db::INSERT_RETURN_MODE_SINGLE,
			);

			return $this->id;
		}

		Db::$db->query(
			'UPDATE {db_prefix}auth_providers
			SET
				provider_type = {string:type},
				title = {string:title},
				issuer = {string:issuer},
				client_id = {string:client_id},
				client_secret = {string:client_secret},
				scopes = {string:scopes},
				enabled = {int:enabled},
				provider_order = {int:order},
				settings = {string:settings}
			WHERE id_provider = {int:id}',
			[
				'type' => $this->type,
				'title' => $this->title,
				'issuer' => rtrim($this->issuer, '/'),
				'client_id' => $this->client_id,
				'client_secret' => $this->client_secret,
				'scopes' => $this->scopes,
				'enabled' => (int) $this->enabled,
				'order' => $this->order,
				'settings' => json_encode($this->settings),
				'id' => $this->id,
			],
		);

		return $this->id;
	}

	/**
	 * Deletes this provider, and every credential that came from it.
	 */
	public function delete(): void
	{
		if ($this->id === 0) {
			return;
		}

		Db::$db->query(
			'DELETE FROM {db_prefix}member_auth
			WHERE type = {string:type}
				AND id_provider = {int:id}',
			[
				'type' => $this->type,
				'id' => $this->id,
			],
		);

		Db::$db->query(
			'DELETE FROM {db_prefix}auth_providers
			WHERE id_provider = {int:id}',
			[
				'id' => $this->id,
			],
		);

		$this->id = 0;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The settings every provider has, and what they mean.
	 *
	 * @return array The defaults.
	 */
	public static function defaultSettings(): array
	{
		return [
			// The discovery document, and when we fetched it.
			'discovery' => [],
			'discovered_at' => 0,
			/*
			 * Whether an unrecognised sign in may claim an existing account
			 * because the email matches. Off by default and deliberately so:
			 * it is an account takeover waiting to happen at any provider that
			 * does not verify the addresses it hands out. Even when on, the
			 * claim is only honoured if the provider says email_verified.
			 */
			'link_by_verified_email' => false,
			// Whether a sign in may create an account that does not exist yet.
			'allow_registration' => true,
			/*
			 * Whether to allow an issuer that resolves to a private address.
			 * Needed for a self hosted provider on the same network, and off
			 * by default so a public forum cannot be pointed inwards.
			 */
			'allow_private_host' => false,
		];
	}

	/**
	 * Loads one provider.
	 *
	 * @param int $id The provider to load.
	 * @return ?self The provider, or null if there is no such thing.
	 */
	public static function load(int $id): ?self
	{
		$request = Db::$db->query(
			'SELECT *
			FROM {db_prefix}auth_providers
			WHERE id_provider = {int:id}
			LIMIT 1',
			[
				'id' => $id,
			],
		);

		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		return $row === false || $row === null ? null : new self($row);
	}

	/**
	 * Loads every provider.
	 *
	 * @param bool $enabled_only Whether to skip the disabled ones.
	 * @return array Instances of this class, in display order.
	 */
	public static function loadAll(bool $enabled_only = false): array
	{
		$providers = [];

		$request = Db::$db->query(
			'SELECT *
			FROM {db_prefix}auth_providers' . ($enabled_only ? '
			WHERE enabled = {int:one}' : '') . '
			ORDER BY provider_order, id_provider',
			[
				'one' => 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$providers[(int) $row['id_provider']] = new self($row);
		}

		Db::$db->free_result($request);

		return $providers;
	}

	/**
	 * The issuer and scopes to start from for well known providers.
	 *
	 * Only fills in the parts that are the same for everyone. The client ID and
	 * secret still have to come from whoever registered the forum with them.
	 *
	 * @return array Preset name => the fields it sets.
	 */
	public static function presets(): array
	{
		return [
			'google' => [
				'title' => 'Google',
				'issuer' => 'https://accounts.google.com',
				'scopes' => 'openid email profile',
			],
			'microsoft' => [
				'title' => 'Microsoft',
				'issuer' => 'https://login.microsoftonline.com/common/v2.0',
				'scopes' => 'openid email profile',
			],
			'apple' => [
				'title' => 'Apple',
				'issuer' => 'https://appleid.apple.com',
				'scopes' => 'openid email name',
			],
		];
	}
}
