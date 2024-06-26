<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF;

/**
 * Contains functions that aid in debugging and are generally
 * useful to developers.
 */
class DebugUtils
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Trims excess indentation off a string.
	 *
	 * Example: If both the first and the third lines are indented thrice but
	 * the second one has four indents, the returned string will have three
	 * less indents, where only the second line has any indentation left.
	 *
	 * Ignores lines with no leading whitrespace.
	 *
	 * @param string $string Query with indentation to remove.
	 * @return string Query without excess indentation.
	 */
	public static function trimIndent(string $string): string
	{
		preg_match_all('/^[ \t]+(?=\S)/m', $string, $matches);
		$min_indent = PHP_INT_MAX;

		foreach ($matches[0] as $match) {
			$min_indent = min($min_indent, strlen($match));
		}

		if ($min_indent != PHP_INT_MAX) {
			$string = preg_replace('/^[ \t]{' . $min_indent . '}/m', '', $string);
		}

		return $string;
	}

	/**
	 * Highlights a well-formed JSON string as HTML.
	 *
	 * @param string $string Well-formed JSON.
	 * @return string Highlighted JSON.
	 */
	public static function highlightJson(string $string): string
	{
		$colors = [
			'STRING' => '#567A0D',
			'NUMBER' => '#015493',
			'NULL' => '#B75301',
			'KEY' => '#803378',
			'COMMENT' => '#666F78',
		];

		return preg_replace_callback(
			'/"[^"]+"(?(?=\s*:)(*MARK:KEY)|(*MARK:STRING))|\b(?:true|false|null)\b(*MARK:NULL)|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?(*MARK:NUMBER)/',
			fn (array $matches): string => '<span style=\'color:' . $colors[$matches['MARK']] . '\'>' . $matches[0] . '</span>',
			str_replace(['<', '>', '&'], ['&lt;', '&gt;', '&amp;'], $string),
		) ?? $string;
	}

	/**
	 * Highlights a SQL string as HTML.
	 *
	 * @param string $string SQL.
	 * @return string Highlighted SQL.
	 */
	public static function highlightSql(string $string): string
	{
		$keyword_regex = '(?>HAVING|GROUP(?> BY|)|MATCH|JOIN|KEY(?>S|)|PR(?>OCEDURE|AGMA|I(?>MARY(?> KEY|)|NT))|A(?>UTO_INCREMENT|DD(?> CONSTRAINT|)|L(?>TER(?> (?>COLUMN|TABLE)|)|L)|N(?>[DY])|S(?>C|))|B(?>ACKUP DATABASE|INARY|LOB|E(?>TWEEN|GIN)|Y)|C(?>URRENT_(?>DATE|TIME)|REATE(?> (?>OR REPLACE VIEW|UNIQUE INDEX|PROCEDURE|DATABASE|INDEX|TABLE|VIEW)|)|AS(?>CADE|E)|H(?>ECK|AR)|O(?>NSTRAINT|LUMN))|D(?>ISTINCT|ROP(?> (?>INDEX|TABLE|VIEW|CO(?>NSTRAINT|LUMN)|D(?>ATABASE|EFAULT))|)|AT(?>ABASE|ETIME)|E(?>CIMAL|FAULT|LETE|SC))|E(?>ACH|LSE(?>IF|)|N(?>GINE|D)|X(?>ISTS|EC))|F(?>ULL OUTER JOIN|ALSE|ROM|OR(?>EIGN KEY|))|I(?>F(?>NULL|)|N(?>NER JOIN|SERT(?> INTO(?> SELECT|)|)|DEX(?>_LIST|)|T(?>E(?>RVAL|GER)|O)|)|S(?> N(?>OT NULL|ULL)|))|L(?>ONGTEXT|E(?>ADING|FT(?> JOIN|))|I(?>MIT|KE))|N(?>ULL|OT(?> NULL|))|O(?>VERLAPS|PTION|UT(?>ER(?> JOIN|)|)|N|R(?>DER(?> BY|)|))|R(?>OWNUM|IGHT(?> JOIN|)|E(?>FERENCES|PLACE))|S(?>HOW|E(?>LECT(?> (?>DISTINCT|INTO|TOP)|)|T))|T(?>ABLE|EXT|HEN|I(?>MESTAMP|NY(?>BLOB|TEXT|INT))|O(?>P|)|R(?>AILING|U(?>NCATE TABLE|E)))|U(?>PDATE|N(?>SIGNED|I(?>QUE|ON(?> ALL|))))|V(?>IEW|A(?>LUES|R(?>BINARY|CHAR)))|W(?>ITH|HE(?>RE|N)))';

		$colors = [
			'STRING' => '#567A0D',
			'NUMBER' => '#015493',
			'FUNCTION' => '#015493',
			'OPERATOR' => '#B75301',
			'KEY' => '#803378',
			'COMMENT' => '#666F78',
		];

		return preg_replace_callback(
			'/(["\'])?(?(1)(?:(?!\1).)*+\1(*MARK:STRING)|(?:\b' . $keyword_regex . '\b(*MARK:KEY)|--.*$|\/\*[\s\S]*?\*\/(*MARK:COMMENT)|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?(*MARK:NUMBER)|[!=%\/*-,;:<>](*MARK:OPERATOR)|\w+\((?:[^)(]+|(?R))*\)(*MARK:FUNCTION)))/',
			fn (array $matches): string => '<span style=\'color:' . $colors[$matches['MARK']] . '\'>' . $matches[0] . '</span>',
			$string,
		) ?? $string;
	}
}

?>