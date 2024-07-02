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

use SMF\Config;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class PostgresqlSchemaDiff extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Correct schema diff';

	/**
	 * Schmea fixes we will peform, these are not cross database safe as we intend to run this only on PostgreSQL.
	 * @var array
	 */
	private array $schemaFixes = [
		['log_subscribed', 'ALTER pending_details DROP DEFAULT'],
		['mail_queue', 'ALTER recipient SET DEFAULT {empty}'],
		['mail_queue', 'ALTER subject SET DEFAULT {empty}'],
		['members', 'ALTER lngfile SET DEFAULT {empty}'],
		['members', 'ALTER real_name SET DEFAULT {empty}'],
		['members', 'ALTER pm_ignore_list SET DEFAULT {empty}'],
		['members', 'ALTER pm_ignore_list TYPE TEXT'],
		['members', 'ALTER pm_ignore_list DROP NOT NULL'],
		['members', 'ALTER pm_ignore_list DROP DEFAULT'],
		['members', 'ALTER email_address SET DEFAULT {empty}'],
		['members', 'ALTER personal_text SET DEFAULT {empty}'],
		['members', 'ALTER website_title SET DEFAULT {empty}'],
		['members', 'ALTER website_url SET DEFAULT {empty}'],
		['members', 'ALTER avatar SET DEFAULT {empty}'],
		['members', 'ALTER usertitle SET DEFAULT {empty}'],
		['members', 'ALTER secret_question SET DEFAULT {empty}'],
		['members', 'ALTER additional_groups SET DEFAULT {empty}'],
		['members', 'ALTER COLUMN password_salt TYPE varchar(255)'],
		['messages', 'ALTER subject SET DEFAULT {empty}'],
		['messages', 'ALTER poster_name SET DEFAULT {empty}'],
		['messages', 'ALTER poster_email SET DEFAULT {empty}'],
		['package_servers', 'ALTER name SET DEFAULT {empty}'],
		['package_servers', 'ALTER url SET DEFAULT {empty}'],
		['permission_profiles', 'ALTER profile_name SET DEFAULT {empty}'],
		['personal_messages', 'ALTER subject SET DEFAULT {empty}'],
		['polls', 'ALTER question SET DEFAULT {empty}'],
		['poll_choices', 'ALTER label SET DEFAULT {empty}'],
		['settings', 'ALTER variable SET DEFAULT {empty}'],
		['sessions', 'ALTER session_id SET DEFAULT {empty}'],
		['sessions', 'ALTER last_update SET DEFAULT 0'],
		['spiders', 'ALTER spider_name SET DEFAULT {empty}'],
		['spiders', 'ALTER user_agent SET DEFAULT {empty}'],
		['spiders', 'ALTER ip_info SET DEFAULT {empty}'],
		['subscriptions', 'ALTER id_subscribe TYPE int'],
		['subscriptions', 'ALTER name SET DEFAULT {empty}'],
		['subscriptions', 'ALTER description SET DEFAULT {empty}'],
		['subscriptions', 'ALTER length SET DEFAULT {empty}'],
		['subscriptions', 'ALTER add_groups SET DEFAULT {empty}'],
		['themes', 'ALTER variable SET DEFAULT {empty}'],
		['admin_info_files', 'ALTER filename SET DEFAULT {empty}'],
		['admin_info_files', 'ALTER path SET DEFAULT {empty}'],
		['admin_info_files', 'ALTER parameters SET DEFAULT {empty}'],
		['admin_info_files', 'ALTER filetype SET DEFAULT {empty}'],
		['attachments', 'ALTER filename SET DEFAULT {empty}'],
		['ban_items', 'ALTER hostname SET DEFAULT {empty}'],
		['ban_items', 'ALTER email_address SET DEFAULT {empty}'],
		['boards', 'ALTER name SET DEFAULT {empty}'],
		['categories', 'ALTER name SET DEFAULT {empty}'],
		['custom_fields', 'ALTER field_desc SET DEFAULT {empty}'],
		['custom_fields', 'ALTER mask SET DEFAULT {empty}'],
		['custom_fields', 'ALTER default_value SET DEFAULT {empty}'],
		['log_banned', 'ALTER email SET DEFAULT {empty}'],
		['log_comments', 'ALTER recipient_name SET DEFAULT {empty}'],
		['log_digest', 'ALTER id_topic SET DEFAULT 0'],
		['log_digest', 'ALTER id_msg SET DEFAULT 0'],
		['log_errors', 'ALTER file SET DEFAULT {empty}'],
		['log_member_notices', 'ALTER subject SET DEFAULT {empty}'],
		['log_online', 'ALTER url SET DEFAULT {empty}'],
		['log_packages', 'ALTER filename SET DEFAULT {empty}'],
		['log_packages', 'ALTER package_id SET DEFAULT {empty}'],
		['log_packages', 'ALTER name SET DEFAULT {empty}'],
		['log_packages', 'ALTER version SET DEFAULT {empty}'],
		['log_packages', 'ALTER themes_installed SET DEFAULT {empty}'],
		['log_reported', 'ALTER membername SET DEFAULT {empty}'],
		['log_reported', 'ALTER subject SET DEFAULT {empty}'],
		['log_reported_comments', 'ALTER membername SET DEFAULT {empty}'],
		['log_reported_comments', 'ALTER comment SET DEFAULT {empty}'],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Config::$db_type === POSTGRE_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();
		$step = 0;

		while (Maintenance::getCurrentSubStep() <= Maintenance::$total_substeps) {
			$fix = $this->schemaFixes[Maintenance::getCurrentSubStep()];

			$this->query('', '
				ALTER TABLE {db_prefix}' . $fix[0] . '
				' . $fix[1]);

			Maintenance::setCurrentSubStep();
			$this->handleTimeout();
		}

		$this->query('', '
			DROP INDEX IF EXISTS {db_prefix}log_actions_id_topic_id_log');
		$this->query('', '
			CREATE INDEX {db_prefix}log_actions_id_topic_id_log ON {db_prefix}log_actions (id_topic, id_log)');

		return true;
	}
}

?>