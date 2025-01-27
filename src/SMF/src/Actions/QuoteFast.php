<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Msg;
use SMF\OutputTypeInterface;
use SMF\OutputTypes;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This class handles quoting posts via JavaScript.
 *
 * Loads a post an inserts it into the current editing text box.
 * - uses the Post language file.
 * - uses special (sadly browser dependent) javascript to parse entities for
 *   internationalization reasons.
 * - accessed with ?action=quotefast.
 */
class QuoteFast implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	public function getOutputType(): OutputTypeInterface
	{
		return new OutputTypes\Xml();
	}

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		$query_customizations = [
			'selects' => [
				'COALESCE(mem.real_name, m.poster_name) AS poster_name',
				'm.poster_time',
				'm.body',
				'm.id_msg',
				'm.id_topic',
				'm.subject',
				'm.id_board',
				'm.id_member',
				'm.approved',
				'm.modified_time',
				'm.modified_name',
				'm.modified_reason',
			],
			'joins' => [
				'JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)',
				'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)',
			],
			'where' => [
				'{query_see_message_board}',
				'm.id_msg IN ({array_int:message_list})',
			],
			'order' => [],
			'params' => [
				'not_locked' => 0,
			],
		];

		$bq = User::$me->mod_cache['bq'];

		if (isset($_REQUEST['modify']) || $bq != '1=1') {
			$query_customizations['where'][] = 't.locked = {int:not_locked}' . ($bq == '0=1' || $bq == '1=1' ? '' : ' OR m.' . $bq);
		}

		$row = current(Msg::load((int) $_REQUEST['quote'], $query_customizations));

		if ($row === false) {
			return;
		}

		Utils::$context['sub_template'] = 'quotefast';

		$can_view_post = $row['approved'] || ($row['id_member'] != 0 && $row['id_member'] == User::$me->id) || User::$me->allowedTo('approve_posts', $row['id_board']);

		if ($can_view_post) {
			Theme::loadTemplate('Xml');
			// Remove special formatting we don't want anymore.
			$body = Msg::un_preparsecode($row['body']);
			$subject = $row['subject'];

			// Censor the message!
			Lang::censorText($body);

			// Want to modify a single message by double clicking it?
			if (isset($_REQUEST['modify'])) {
				Lang::censorText($subject);

				Utils::$context['sub_template'] = 'modifyfast';
				Utils::$context['message'] = [
					'id' => $_REQUEST['quote'],
					'body' => $body,
					'subject' => addcslashes($subject, '"'),
					'reason' => [
						'name' => $row['modified_name'],
						'text' => $row['modified_reason'],
						'time' => $row['modified_time'],
					],
				];

				return;
			}

			// Remove any nested quotes.
			if (!empty(Config::$modSettings['removeNestedQuotes'])) {
				$body = preg_replace(['~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'], '', $body);
			}

			$lb = "\n";

			// Add a quote string on the front and end.
			Utils::$context['quote']['xml'] = '[quote author=' . $row['poster_name'] . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $row['poster_time'] . ']' . $lb . $body . $lb . '[/quote]';
			Utils::$context['quote']['text'] = strtr(Utils::htmlspecialcharsDecode(Utils::$context['quote']['xml']), ['\'' => '\\\'', '\\' => '\\\\', "\n" => '\\n', '</script>' => '</\' + \'script>']);
			Utils::$context['quote']['xml'] = strtr(Utils::$context['quote']['xml'], ['&nbsp;' => '&#160;', '<' => '&lt;', '>' => '&gt;']);

			Utils::$context['quote']['mozilla'] = strtr(Utils::htmlspecialchars(Utils::$context['quote']['text']), ['&quot;' => '"']);
		}
		// @todo Needs a nicer interface.
		// In case our message has been removed in the meantime.
		elseif (isset($_REQUEST['modify'])) {
			Utils::$context['sub_template'] = 'modifyfast';
			Utils::$context['message'] = [
				'id' => 0,
				'body' => '',
				'subject' => '',
				'reason' => [
					'name' => '',
					'text' => '',
					'time' => '',
				],
			];
		} else {
			Utils::$context['quote'] = [
				'xml' => '',
				'mozilla' => '',
				'text' => '',
			];
		}

		IntegrationHook::call('integrate_quotefast', [$row]);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		Lang::load('Post');

		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('Post');
		}
	}
}

?>