<?php
/**
 * This file contains core of the code for Mentions
 *
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
 * This really is a pseudo class, I couldn't justify having instance of it
 * while mentioning so I just made every method static
 */
class Mentions
{
	/**
	 * @var string The character used for mentioning users
	 */
	protected static $char = '@';

	/**
	 * @var string Regular expression matching BBC that can't contain mentions
	 */
	protected static $excluded_bbc_regex = '';

	/**
	 * Returns mentions for a specific content
	 *
	 * @static
	 * @param string $content_type The content type
	 * @param int $content_id The ID of the desired content
	 * @param array $members Whether to limit to a specific set of members
	 * @return array An array of arrays containing info about each member mentioned
	 */
	public static function getMentionsByContent($content_type, $content_id, array $members = [])
	{
		$request = Db::$db->query(
			'',
			'SELECT mem.id_member, mem.real_name, mem.email_address, mem.id_group, mem.id_post_group, mem.additional_groups,
				mem.lngfile, ment.id_member AS id_mentioned_by, ment.real_name AS mentioned_by_name
			FROM {db_prefix}mentions AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_mentioned)
				INNER JOIN {db_prefix}members AS ment ON (ment.id_member = m.id_member)
			WHERE content_type = {string:type}
				AND content_id = {int:id}' . (!empty($members) ? '
				AND mem.id_member IN ({array_int:members})' : ''),
			[
				'type' => $content_type,
				'id' => $content_id,
				'members' => (array) $members,
			],
		);
		$members = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$members[$row['id_member']] = [
				'id' => $row['id_member'],
				'real_name' => $row['real_name'],
				'email_address' => $row['email_address'],
				'groups' => array_unique(array_merge([$row['id_group'], $row['id_post_group']], explode(',', $row['additional_groups']))),
				'mentioned_by' => [
					'id' => $row['id_mentioned_by'],
					'name' => $row['mentioned_by_name'],
				],
				'lngfile' => $row['lngfile'],
			];
		}
		Db::$db->free_result($request);

		return $members;
	}

	/**
	 * Inserts mentioned members
	 *
	 * @static
	 * @param string $content_type The content type
	 * @param int $content_id The ID of the specified content
	 * @param array $members An array of members who have been mentioned
	 * @param int $id_member The ID of the member who mentioned them
	 */
	public static function insertMentions($content_type, $content_id, array $members, $id_member)
	{
		IntegrationHook::call('mention_insert_' . $content_type, [$content_id, &$members]);

		foreach ($members as $member) {
			Db::$db->insert(
				'ignore',
				'{db_prefix}mentions',
				['content_id' => 'int', 'content_type' => 'string', 'id_member' => 'int', 'id_mentioned' => 'int', 'time' => 'int'],
				[(int) $content_id, $content_type, $id_member, $member['id'], time()],
				['content_id', 'content_type', 'id_mentioned'],
			);
		}
	}

	/**
	 * Updates list of mentioned members.
	 *
	 * Intended for use when a post is modified.
	 *
	 * @static
	 * @param string $content_type The content type
	 * @param int $content_id The ID of the specified content
	 * @param array $members An array of members who have been mentioned
	 * @param int $id_member The ID of the member who mentioned them
	 * @return array An array of unchanged, removed, and added member IDs.
	 */
	public static function modifyMentions($content_type, $content_id, array $members, $id_member)
	{
		$existing_members = self::getMentionsByContent($content_type, $content_id);

		$members_to_remove = array_diff_key($existing_members, $members);
		$members_to_insert = array_diff_key($members, $existing_members);
		$members_unchanged = array_diff_key($existing_members, $members_to_remove, $members_to_insert);

		// Delete mentions from the table that have been deleted in the content.
		if (!empty($members_to_remove)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}mentions
				WHERE content_type = {string:type}
					AND content_id = {int:id}
					AND id_mentioned IN ({array_int:members})',
				[
					'type' => $content_type,
					'id' => $content_id,
					'members' => array_keys($members_to_remove),
				],
			);
		}

		// Insert any new mentions.
		if (!empty($members_to_insert)) {
			self::insertMentions($content_type, $content_id, $members_to_insert, $id_member);
		}

		return [
			'unchanged' => $members_unchanged,
			'removed' => $members_to_remove,
			'added' => $members_to_insert,
		];
	}

	/**
	 * Gets appropriate mentions replaced in the body
	 *
	 * @static
	 * @param string $body The text to look for mentions in
	 * @param array $members An array of arrays containing info about members (each should have 'id' and 'member')
	 * @return string The body with mentions replaced
	 */
	public static function getBody($body, array $members)
	{
		if (empty($body)) {
			return $body;
		}

		foreach ($members as $member) {
			$body = str_ireplace(static::$char . $member['real_name'], '[member=' . $member['id'] . ']' . $member['real_name'] . '[/member]', $body);
		}

		return $body;
	}

	/**
	 * Takes a piece of text and finds all the mentioned members in it
	 *
	 * @static
	 * @param string $body The body to get mentions from
	 * @return array An array of arrays containing members who were mentioned (each has 'id_member' and 'real_name')
	 */
	public static function getMentionedMembers($body)
	{
		if (empty($body)) {
			return [];
		}

		$possible_names = self::getPossibleMentions($body);
		$existing_mentions = self::getExistingMentions($body);

		if ((empty($possible_names) && empty($existing_mentions)) || !User::$me->allowedTo('mention')) {
			return [];
		}

		// Make sure we don't pass empty arrays to the query.
		if (empty($existing_mentions)) {
			$existing_mentions = [0 => ''];
		}

		if (empty($possible_names)) {
			$possible_names = $existing_mentions;
		}

		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:ids})
				OR real_name IN ({array_string:names})
			ORDER BY LENGTH(real_name) DESC
			LIMIT {int:count}',
			[
				'ids' => array_keys($existing_mentions),
				'names' => $possible_names,
				'count' => count($possible_names),
			],
		);
		$members = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset($existing_mentions[$row['id_member']]) && stripos($body, static::$char . $row['real_name']) === false) {
				continue;
			}

			$members[$row['id_member']] = [
				'id' => $row['id_member'],
				'real_name' => $row['real_name'],
			];
		}
		Db::$db->free_result($request);

		return $members;
	}

	/**
	 * Parses a body in order to see if there are any mentions, returns possible mention names
	 *
	 * Names are tagged by "@<username>" format in post, but they can contain
	 * any type of character up to 60 characters length. So we extract, starting from @
	 * up to 60 characters in length (or if we encounter a line break) and make
	 * several combination of strings after splitting it by anything that's not a word and join
	 * by having the first word, first and second word, first, second and third word and so on and
	 * search every name.
	 *
	 * One potential problem with this is something like "@Admin Space" can match
	 * "Admin Space" as well as "Admin", so we sort by length in descending order.
	 * One disadvantage of this is that we can only match by one column, hence I've chosen
	 * real_name since it's the most obvious.
	 *
	 * If there's an @ symbol within the name, it is counted in the ongoing string and a new
	 * combination string is started from it as well in order to account for all the possibilities.
	 * This makes the @ symbol to not be required to be escaped
	 *
	 * @static
	 * @param string $body The text to look for mentions in
	 * @return array An array of names of members who have been mentioned
	 */
	protected static function getPossibleMentions($body)
	{
		if (empty($body)) {
			return [];
		}

		// preparse code does a few things which might mess with our parsing
		$body = htmlspecialchars_decode(preg_replace('~<br\s*/?' . '>~', "\n", str_replace('&nbsp;', ' ', $body)), ENT_QUOTES);

		if (empty(self::$excluded_bbc_regex)) {
			self::setExcludedBbcRegex();
		}

		// Exclude the content of various BBCodes.
		$body = preg_replace('~\[(' . self::$excluded_bbc_regex . ')[^\]]*\](?' . '>(?' . '>[^\[]|\[(?!/?\1[^\]]*\]))|(?0))*\[/\1\]~', '', $body);

		$matches = [];
		// Split before every Unicode character.
		$string = preg_split('/(?=\X)/u', $body, -1, PREG_SPLIT_NO_EMPTY);
		$depth = 0;

		foreach ($string as $k => $char) {
			if ($char == static::$char && ($k == 0 || trim($string[$k - 1]) == '')) {
				$depth++;
				$matches[] = [];
			} elseif ($char == "\n") {
				$depth = 0;
			}

			for ($i = $depth; $i > 0; $i--) {
				if (count($matches[count($matches) - $i]) > 60) {
					$depth--;

					continue;
				}
				$matches[count($matches) - $i][] = $char;
			}
		}

		foreach ($matches as $k => $match) {
			$matches[$k] = substr(implode('', $match), 1);
		}

		// Names can have spaces, other breaks, or they can't...we try to match every possible
		// combination.
		$names = [];

		foreach ($matches as $match) {
			// '[^\p{L}\p{M}\p{N}_]' is the Unicode equivalent of '[^\w]'
			$match = preg_split('/([^\p{L}\p{M}\p{N}_])/u', $match, -1, PREG_SPLIT_DELIM_CAPTURE);
			$count = count($match);

			for ($i = 1; $i <= $count; $i++) {
				$names[] = Utils::htmlspecialchars(Utils::htmlTrim(implode('', array_slice($match, 0, $i))));
			}
		}

		$names = array_unique($names);

		return $names;
	}

	/**
	 * Like getPossibleMentions(), but for `[member=1]name[/member]` format.
	 *
	 * @static
	 * @param string $body The text to look for mentions in.
	 * @param array $members An array of arrays containing info about members (each should have 'id' and 'member').
	 * @return array An array of arrays containing info about members that are in fact mentioned in the body.
	 */
	public static function getExistingMentions($body)
	{
		if (empty(self::$excluded_bbc_regex)) {
			self::setExcludedBbcRegex();
		}

		// Don't include mentions inside quotations, etc.
		$body = preg_replace('~\[(' . self::$excluded_bbc_regex . ')[^\]]*\](?' . '>(?' . '>[^\[]|\[(?!/?\1[^\]]*\]))|(?0))*\[/\1\]~', '', $body);

		$existing_mentions = [];

		preg_match_all('~\[member=([0-9]+)\]([^\[]*)\[/member\]~', $body, $matches, PREG_SET_ORDER);

		foreach ($matches as $match_set) {
			$existing_mentions[$match_set[1]] = trim($match_set[2]);
		}

		return $existing_mentions;
	}

	/**
	 * Verifies that members really are mentioned in the text.
	 *
	 * This function assumes the incoming text has already been processed by
	 * the Mentions::getBody() function.
	 *
	 * @static
	 * @param string $body The text to look for mentions in.
	 * @param array $members An array of arrays containing info about members (each should have 'id' and 'member').
	 * @return array An array of arrays containing info about members that are in fact mentioned in the body.
	 */
	public static function verifyMentionedMembers($body, array $members)
	{
		if (empty($body)) {
			return [];
		}

		if (empty(self::$excluded_bbc_regex)) {
			self::setExcludedBbcRegex();
		}

		// Don't include mentions inside quotations, etc.
		$body = preg_replace('~\[(' . self::$excluded_bbc_regex . ')[^\]]*\](?' . '>(?' . '>[^\[]|\[(?!/?\1[^\]]*\]))|(?0))*\[/\1\]~', '', $body);

		foreach ($members as $member) {
			if (strpos($body, '[member=' . $member['id'] . ']' . $member['real_name'] . '[/member]') === false) {
				unset($members[$member['id']]);
			}
		}

		return $members;
	}

	/**
	 * Retrieves info about the authors of posts quoted in a block of text.
	 *
	 * @static
	 * @param string $body A block of text, such as the body of a post.
	 * @param int $poster_id The member ID of the author of the text.
	 * @return array Info about any members who were quoted.
	 */
	public static function getQuotedMembers($body, $poster_id)
	{
		if (empty($body)) {
			return [];
		}

		$blocks = preg_split('/(\[quote.*?\]|\[\/quote\])/i', $body, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		$quote_level = 0;
		$message = '';

		foreach ($blocks as $block) {
			if (preg_match('/\[quote(.*)?\]/i', $block, $matches)) {
				if ($quote_level == 0) {
					$message .= '[quote' . $matches[1] . ']';
				}
				$quote_level++;
			} elseif (preg_match('/\[\/quote\]/i', $block)) {
				if ($quote_level <= 1) {
					$message .= '[/quote]';
				}

				if ($quote_level >= 1) {
					$quote_level--;
					$message .= "\n";
				}
			} elseif ($quote_level <= 1) {
				$message .= $block;
			}
		}

		preg_match_all('/\[quote.*?link=msg=([0-9]+).*?\]/i', $message, $matches);

		$id_msgs = (array) $matches[1];

		foreach ($id_msgs as $k => $id_msg) {
			$id_msgs[$k] = (int) $id_msg;
		}

		if (empty($id_msgs)) {
			return [];
		}

		// Get the messages
		$request = Db::$db->query(
			'',
			'SELECT m.id_member AS id, mem.email_address, mem.lngfile, mem.real_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE id_msg IN ({array_int:msgs})
			LIMIT {int:count}',
			[
				'msgs' => array_unique($id_msgs),
				'count' => count(array_unique($id_msgs)),
			],
		);

		$members = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($poster_id == $row['id']) {
				continue;
			}

			$members[$row['id']] = $row;
		}

		return $members;
	}

	/**
	 * Builds a regular expression matching BBC that can't contain mentions.
	 *
	 * @static
	 */
	protected static function setExcludedBbcRegex()
	{
		if (empty(self::$excluded_bbc_regex)) {
			// Exclude quotes. We don't want to get double mentions.
			$excluded_bbc = ['quote'];

			// Exclude everything with unparsed content.
			foreach (BBCodeParser::getCodes() as $code) {
				if (!empty($code['type']) && in_array($code['type'], ['unparsed_content', 'unparsed_commas_content', 'unparsed_equals_content'])) {
					$excluded_bbc[] = $code['tag'];
				}
			}

			self::$excluded_bbc_regex = Utils::buildRegex($excluded_bbc, '~');
		}
	}
}

?>