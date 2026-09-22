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

use SMF\Db\DatabaseApi as Db;

/**
 * One thing a member can sign in with that is not their password.
 */
class Credential
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Credentials issued by an identity provider.
	 */
	public const TYPE_OIDC = 'oidc';

	/**
	 * Passkeys, held by the member's own device.
	 */
	public const TYPE_WEBAUTHN = 'webauthn';

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Records that a member signs in with this credential.
	 *
	 * @param int $id_member The member.
	 * @param string $type One of this class's TYPE_ constants.
	 * @param int $id_provider Which provider it came from, or 0.
	 * @param string $identifier What the issuer calls this credential.
	 * @param string $title What to show the member.
	 * @param string $secret_data Anything the caller needs to keep.
	 */
	public static function add(int $id_member, string $type, int $id_provider, string $identifier, string $title = '', string $secret_data = ''): void
	{
		Db::$db->insert(
			'ignore',
			'{db_prefix}member_auth',
			[
				'id_member' => 'int',
				'type' => 'string',
				'id_provider' => 'int',
				'identifier' => 'string',
				'secret_data' => 'string',
				'title' => 'string',
				'date_created' => 'int',
				'date_last_used' => 'int',
			],
			[
				[
					$id_member,
					$type,
					$id_provider,
					$identifier,
					$secret_data,
					$title,
					time(),
					time(),
				],
			],
			['id_auth'],
		);
	}

	/**
	 * Finds the member who signs in with this credential.
	 *
	 * @param string $type One of this class's TYPE_ constants.
	 * @param int $id_provider Which provider it came from, or 0.
	 * @param string $identifier What the issuer calls this credential.
	 * @return int The member's ID, or 0 if nobody has claimed it.
	 */
	public static function findMember(string $type, int $id_provider, string $identifier): int
	{
		$request = Db::$db->query(
			'SELECT id_member
			FROM {db_prefix}member_auth
			WHERE type = {string:type}
				AND id_provider = {int:provider}
				AND identifier = {string:identifier}
			LIMIT 1',
			[
				'type' => $type,
				'provider' => $id_provider,
				'identifier' => $identifier,
			],
		);

		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		return (int) ($row['id_member'] ?? 0);
	}

	/**
	 * Fetches one credential, with everything that was kept alongside it.
	 *
	 * @param string $type One of this class's TYPE_ constants.
	 * @param int $id_provider Which provider it came from, or 0.
	 * @param string $identifier What the issuer calls this credential.
	 * @return ?array The row, or null if nobody has claimed it.
	 */
	public static function find(string $type, int $id_provider, string $identifier): ?array
	{
		$request = Db::$db->query(
			'SELECT id_auth, id_member, type, id_provider, identifier, secret_data, title, date_created, date_last_used
			FROM {db_prefix}member_auth
			WHERE type = {string:type}
				AND id_provider = {int:provider}
				AND identifier = {string:identifier}
			LIMIT 1',
			[
				'type' => $type,
				'provider' => $id_provider,
				'identifier' => $identifier,
			],
		);

		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		return $row === false || $row === null ? null : $row;
	}

	/**
	 * Replaces what was kept alongside a credential.
	 *
	 * @param int $id_auth The credential to update.
	 * @param string $secret_data What to keep instead.
	 */
	public static function setSecretData(int $id_auth, string $secret_data): void
	{
		Db::$db->query(
			'UPDATE {db_prefix}member_auth
			SET secret_data = {string:secret_data}
			WHERE id_auth = {int:id}',
			[
				'secret_data' => $secret_data,
				'id' => $id_auth,
			],
		);
	}

	/**
	 * Lists what a member can sign in with.
	 *
	 * @param int $id_member The member.
	 * @param ?string $type Only this kind, or null for all of them.
	 * @return array The rows, newest last.
	 */
	public static function listFor(int $id_member, ?string $type = null): array
	{
		$credentials = [];

		$request = Db::$db->query(
			'SELECT id_auth, id_member, type, id_provider, identifier, secret_data, title, date_created, date_last_used
			FROM {db_prefix}member_auth
			WHERE id_member = {int:member}' . ($type === null ? '' : '
				AND type = {string:type}') . '
			ORDER BY date_created',
			[
				'member' => $id_member,
				'type' => (string) $type,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$credentials[(int) $row['id_auth']] = $row;
		}

		Db::$db->free_result($request);

		return $credentials;
	}

	/**
	 * Notes that a credential was just used.
	 *
	 * @param string $type One of this class's TYPE_ constants.
	 * @param int $id_provider Which provider it came from, or 0.
	 * @param string $identifier What the issuer calls this credential.
	 */
	public static function touch(string $type, int $id_provider, string $identifier): void
	{
		Db::$db->query(
			'UPDATE {db_prefix}member_auth
			SET date_last_used = {int:now}
			WHERE type = {string:type}
				AND id_provider = {int:provider}
				AND identifier = {string:identifier}',
			[
				'now' => time(),
				'type' => $type,
				'provider' => $id_provider,
				'identifier' => $identifier,
			],
		);
	}

	/**
	 * Removes one of a member's credentials.
	 *
	 * Refuses to remove the last one when the member has no password, since
	 * that would leave them with no way back in.
	 *
	 * @param int $id_auth The credential to remove.
	 * @param int $id_member Who it must belong to.
	 * @param bool $has_password Whether they can still log in without it.
	 * @return bool Whether it was removed.
	 */
	public static function remove(int $id_auth, int $id_member, bool $has_password): bool
	{
		if (!$has_password && \count(self::listFor($id_member)) < 2) {
			return false;
		}

		Db::$db->query(
			'DELETE FROM {db_prefix}member_auth
			WHERE id_auth = {int:id}
				AND id_member = {int:member}',
			[
				'id' => $id_auth,
				'member' => $id_member,
			],
		);

		return true;
	}
}
