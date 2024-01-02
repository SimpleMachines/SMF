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

namespace SMF;

use SMF\Db\DatabaseApi as Db;

/**
 * Represents one of the choices a user can vote for in a poll.
 */
class PollChoice implements \ArrayAccess
{
	use ArrayAccessHelper;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This poll choice's ID number.
	 */
	public int $id;

	/**
	 * @var int
	 *
	 * ID number of the poll this choice is connected to.
	 */
	public int $poll;

	/**
	 * @var string
	 *
	 *
	 */
	public string $label = '';

	/**
	 * @var int
	 *
	 *
	 */
	public int $votes = 0;

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $new = false;

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $voted_this = false;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_choice' => 'id',
		'id_poll' => 'poll',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $props Properties to set.
	 */
	public function __construct(array $props = [])
	{
		$this->set($props);
	}

	/**
	 * Saves this poll choice.
	 *
	 * Calls $this->add(), $this->update(), or $this->delete() as necessary.
	 */
	public function save(): void
	{
		// Delete if label is empty.
		if (trim(Utils::normalizeSpaces($this->label)) === '') {
			$this->delete();
		}
		// Not new, so update the existing one.
		elseif (empty($this->new)) {
			$this->update();
		}
		// New, so add it.
		else {
			$this->add();
		}
	}

	/**
	 *
	 */
	public function add(): void
	{
		Db::$db->insert(
			'',
			'{db_prefix}poll_choices',
			['id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255'],
			[$this->poll, $this->id, $this->label],
			[],
		);
	}

	/**
	 *
	 */
	public function update(): void
	{
		Db::$db->query(
			'',
			'UPDATE {db_prefix}poll_choices
			SET label = {string:label}, votes = {int:votes}
			WHERE id_poll = {int:id_poll}
				AND id_choice = {int:id_choice}',
			[
				'id_poll' => $this->poll,
				'id_choice' => $this->id,
				'label' => $this->label,
				'votes' => $this->votes,
			],
		);
	}

	/**
	 *
	 */
	public function delete(): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_polls
			WHERE id_poll = {int:id_poll}
				AND id_choice = {int:to_delete}',
			[
				'to_delete' => $this->id,
				'id_poll' => $this->id_poll,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}poll_choices
			WHERE id_poll = {int:id_poll}
				AND id_choice = {int:to_delete}',
			[
				'to_delete' => $this->id,
				'id_poll' => $this->id_poll,
			],
		);
	}
}

?>