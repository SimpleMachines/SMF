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

declare(strict_types=1);

namespace SMF\Permissions;

use SMF\Db\DatabaseApi as Db;
use SMF\Group;
use SMF\Lang;
use SMF\Utils;

/**
 * Represents a permission profile.
 */
class PermissionProfile
{
	/*****************
	 * Class constants
	 *****************/

	public const DEFAULT = 1;
	public const NO_POLLS = 2;
	public const REPLY_ONLY = 3;
	public const READ_ONLY = 4;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The ID of this permission profile.
	 */
	public int $id;

	/**
	 * @var string
	 *
	 * The raw name of this permission profile.
	 */
	public string $name;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Boards where this profile is used.
	 */
	private array $boards;

	/**
	 * @var int
	 *
	 * ID of the profile that this one was copied from.
	 *
	 * Only set during self::copy().
	 */
	private int $copied_from;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	private static array $loaded = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct(int $id, string $name)
	{
		$this->id = $id;
		$this->name = $name;
	}

	/**
	 * Save this permission profile to the database.
	 */
	public function save(): void
	{
		if (empty($this->id)) {
			$this->id = Db::$db->insert(
				'',
				'{db_prefix}permission_profiles',
				['profile_name' => 'string'],
				[[$this->name]],
				['id_profile'],
				1,
			);

			if (!empty($this->copied_from)) {
				foreach (GroupPermissionSet::load($this->copied_from, Group::getAll()) as $set) {
					$set->profile = $this->id;
					$set->save();
				}

				unset($this->copied_from);
			}

			self::$loaded[$this->id] = $this;
		} else {
			Db::$db->query(
				'UPDATE {db_prefix}permission_profiles
				SET profile_name = {string:profile_name}
				WHERE id_profile = {int:current_profile}',
				[
					'current_profile' => $this->id,
					'profile_name' => $this->name,
				],
			);

			// This will only ever be called if an expected profile was deleted.
			if (Db::$db->affected_rows() == 0) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}permission_profiles',
					['id_profile' => 'int', 'profile_name' => 'string'],
					[[$this->id, $this->name]],
					['id_profile'],
				);
			}
		}
	}

	/**
	 * Deletes this permission profile.
	 *
	 * @return bool Whether the operation was successful.
	 */
	public function delete(): bool
	{
		// Can't delete predefined profiles or profiles that are in use.
		if ($this->isPredefined() || !empty($this->boards())) {
			return false;
		}

		Db::$db->query(
			'DELETE FROM {db_prefix}permission_profiles
			WHERE id_profile = {int:profile}',
			[
				'profile' => $this->id,
			],
		);

		unset(self::$loaded[$this->id]);

		return true;
	}

	/**
	 * Checks whether this permission profile can be modified.
	 *
	 * @return bool Whether this permission profile can be modified.
	 */
	public function canModify(): bool
	{
		return !in_array($this->id, [self::NO_POLLS, self::REPLY_ONLY, self::READ_ONLY]);
	}

	/**
	 * Checks whether this permission profile is predefined.
	 *
	 * @return bool Whether this permission profile is predefined.
	 */
	public function isPredefined(): bool
	{
		return in_array($this->id, [self::DEFAULT, self::NO_POLLS, self::REPLY_ONLY, self::READ_ONLY]);
	}

	/**
	 * Gets the boards where this profile is used.
	 *
	 * @return array IDs of boards that use this profile.
	 */
	public function boards(): array
	{
		if (!isset($this->boards)) {
			$request = Db::$db->query(
				'SELECT id_board
				FROM {db_prefix}boards
				WHERE id_profile = {int:profile}',
				[
					'profile' => $this->id,
				],
			);

			$this->boards = array_map(fn($row) => (int) $row['id_board'], Db::$db->fetch_all($request));

			Db::$db->free_result($request);
		}

		return $this->boards;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads the specified permission profile.
	 *
	 * @param string|int $id_or_name The ID number or name of the profile.
	 * @return ?self An instance of this class, or null on error.
	 */
	public static function load(string|int $id_or_name): ?self
	{
		// Loading them all is cheap, so we might as well do so.
		foreach (self::loadAll() as $profile) {
			if ($profile->id === $id_or_name || $profile->name === $id_or_name) {
				return $profile;
			}
		}

		return null;
	}

	/**
	 * Loads all known permission profiles.
	 *
	 * @return array Instances of this class.
	 */
	public static function loadAll(): array
	{
		// Avoid unnecessary repetition.
		if (!empty(self::$loaded)) {
			return self::$loaded;
		}

		$request = Db::$db->query(
			'SELECT p.id_profile, p.profile_name, b.id_board
			FROM {db_prefix}permission_profiles AS p
			LEFT JOIN {db_prefix}boards AS b ON (p.id_profile = b.id_profile)',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$id = (int) $row['id_profile'];

			if (!isset(self::$loaded[$id])) {
				self::$loaded[$id] = new self($id, $row['profile_name']);
			}

			if (!is_null($row['id_board'])) {
				self::$loaded[$id]->boards[] = (int) $row['id_board'];
			}
		}

		Db::$db->free_result($request);

		// Just in case the predefined ones somehow went missing from the database...
		foreach ([self::DEFAULT => 'default', self::NO_POLLS => 'no_polls', self::REPLY_ONLY => 'reply_only', self::READ_ONLY => 'read_only'] as $id => $name) {
			if (!isset(self::$loaded[$id])) {
				self::$loaded[$id] = new self($id, $name);
				self::$loaded[$id]->save();
			}
		}

		return self::$loaded;
	}

	/**
	 * Loads the permission profiles for the specified boards.
	 *
	 * @param int|array $boards IDs of one or more boards.
	 * @return array Key-value pairs where keys are board IDs and values are
	 *    instances of this class.
	 */
	public static function loadByBoard(int|array $boards): array
	{
		$boards = (array) $boards;

		$loaded = [];

		foreach (self::loadAll() as $profile) {
			foreach ($profile->boards() as $board) {
				if (in_array($board, $boards)) {
					$loaded[$board] = $profile;
				}
			}
		}

		ksort($loaded);

		return $loaded;
	}

	/**
	 * Populates Utils::$context['profiles'] with info for all profiles.
	 */
	public static function loadContext(): void
	{
		Utils::$context['profiles'] = [];

		foreach (self::loadAll() as $profile) {
			Utils::$context['profiles'][$profile->id] = [
				'id' => $profile->id,
				'name' => Lang::txtExists('permissions_profile_' . $profile->name, file: 'ManagePermissions') ? Lang::getTxt('permissions_profile_' . $profile->name, file: 'ManagePermissions') : $profile->name,
				'can_modify' => $profile->canModify(),
				'unformatted_name' => $profile->name,
			];
		}
	}

	/**
	 * Creates a new permission profile by copying an existing one.
	 *
	 * @param int $copy_from ID of the profile to duplicate.
	 * @param string $name Name for the new profile.
	 * @return self|null A new instance of this class, or null on error.
	 */
	public static function copy(int $copy_from, string $name): ?self
	{
		if (($source_profile = PermissionProfile::load($copy_from)) === null) {
			return null;
		}

		$new_profile = new self(0, $name);
		$new_profile->copied_from = $source_profile->id;

		// Saving will set the new ID, propagate the permissions, and add the
		// new profile to the list of loaded profiles.
		$new_profile->save();

		return $new_profile;
	}
}
