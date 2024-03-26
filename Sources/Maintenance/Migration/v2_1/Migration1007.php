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

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;

class Migration1007 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Fixing invalid sizes on attachments';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		// @@ TODO, Why do we not do this in a single query?
		/*
			UPDATE attachments
			SET width = 0,
				height = 1
			WHERE
				(width > 0 OR height > 0)
				AND POSITION({literal:image} IN mime_type) IS NULL
		*/

		$attachs = [];
		// If id_member = 0, then it's not an avatar
		// If attachment_type = 0, then it's also not a thumbnail
		// Theory says there shouldn't be *that* many of these
		$request = Db::$db->query(
			'',
			'
			SELECT id_attach, mime_type, width, height
			FROM {db_prefix}attachments
			WHERE id_member = 0
				AND attachment_type = 0',
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (($row['width'] > 0 || $row['height'] > 0) && strpos($row['mime_type'], 'image') !== 0) {
				$attachs[] = $row['id_attach'];
			}
		}
		Db::$db->free_result($request);

		if (!empty($attachs)) {
			Db::$db->query(
				'',
				'
				UPDATE {db_prefix}attachments
				SET width = 0,
					height = 0
				WHERE id_attach IN ({array_int:attachs})',
				[
					'attachs' => $attachs,
				],
			);
		}

			return true;
	}
}

?>