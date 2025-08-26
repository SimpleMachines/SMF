<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Table;
use SMF\Lang;
use SMF\Sapi;

/**
 * Used for converting MySQL databases to the utf8mb4 character set.
 *
 * This is intended to be used as a step by maintenance tools.
 *
 * This class only does anything when SMF is configured to use a MySQL database
 * (or a MySQL derivative like MariaDB). If SMF is using a PostgreSQL database,
 * then calling any of this class's methods will do nothing.
 */
class Utf8ConverterStep extends Step
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var array
	 *
	 * Database column types that can contain strings.
	 */
	public const STRING_COLUMN_TYPES = [
		'varchar',
		'char',
		'tinytext',
		'text',
		'mediumtext',
		'longtext',
		'enum',
		'set',
	];

	/**
	 * @var array
	 *
	 * These are the $txt['lang_character_set'] values from all the 2.0.19
	 * language packs that weren't *-utf8 ones. Note that some of them used
	 * UTF-8 in both versions of their language packs, so UTF-8 still shows
	 * up in a number of entries below.
	 */
	public const LANG_CHARSETS = [
		'afrikaans' => 'ISO-8859-1',
		'albanian' => 'ISO-8859-1',
		'arabic' => 'windows-1256',
		'armenian_east' => 'armscii-8',
		'armenian_west' => 'armscii-8',
		'azerbaijani_latin' => 'ISO-8859-9',
		'bangla' => 'UTF-8',
		'basque' => 'ISO-8859-1',
		'belarusian' => 'ISO-8859-5',
		'bosnian' => 'ISO-8859-1',
		'bulgarian' => 'windows-1251',
		'cambodian' => 'UTF-8',
		'catalan' => 'ISO-8859-1',
		'chinese_simplified' => 'gbk',
		'chinese_traditional' => 'big5',
		'croatian' => 'ISO-8859-2',
		'czech' => 'ISO-8859-2',
		'czech_informal' => 'ISO-8859-2',
		'danish' => 'ISO-8859-1',
		'dutch' => 'ISO-8859-1',
		'english' => 'ISO-8859-1',
		'english_british' => 'ISO-8859-1',
		'english_pirate' => 'UTF-8',
		'esperanto' => 'ISO-8859-3',
		'estonian' => 'ISO-8859-15',
		'filipino_tagalog' => 'UTF-8',
		'filipino_visayan' => 'UTF-8',
		'finnish' => 'ISO-8859-1',
		'french' => 'ISO-8859-1',
		'galician' => 'ISO-8859-1',
		'georgian' => 'UTF-8',
		'german' => 'ISO-8859-1',
		'german_informal' => 'ISO-8859-1',
		'greek' => 'windows-1253',
		'hebrew' => 'windows-1255',
		'hindi' => 'ISO-8859-1',
		'hungarian' => 'ISO-8859-2',
		'icelandic' => 'ISO-8859-1',
		'indonesian' => 'ISO-8859-1',
		'irish' => 'UTF-8',
		'italian' => 'ISO-8859-1',
		'japanese' => 'UTF-8',
		'khmer' => 'UTF-8',
		'korean' => 'UTF-8',
		'kurdish_kurmanji' => 'ISO-8859-9',
		'kurdish_sorani' => 'windows-1256',
		'lao' => 'tis-620',
		'latvian' => 'ISO-8859-13',
		'macedonian' => 'UTF-8',
		'malay' => 'ISO-8859-1',
		'malayalam' => 'UTF-8',
		'mongolian' => 'UTF-8',
		'nepali' => 'UTF-8',
		'norwegian' => 'ISO-8859-1',
		'persian' => 'UTF-8',
		'polish' => 'ISO-8859-2',
		'portuguese_brazilian' => 'ISO-8859-1',
		'portuguese_pt' => 'ISO-8859-1',
		'romanian' => 'ISO-8859-2',
		'russian' => 'windows-1251',
		'sakha' => 'UTF-8',
		'serbian_cyrillic' => 'ISO-8859-5',
		'serbian_latin' => 'ISO-8859-2',
		'sinhala' => 'UTF-8',
		'slovak' => 'ISO-8859-2',
		'slovenian' => 'ISO-8859-2',
		'spanish' => 'ISO-8859-1',
		'spanish_es' => 'ISO-8859-1',
		'spanish_latin' => 'ISO-8859-1',
		'swedish' => 'ISO-8859-1',
		'telugu' => 'UTF-8',
		'thai' => 'tis-620',
		'turkish' => 'ISO-8859-9',
		'turkmen' => 'ISO-8859-9',
		'ukrainian' => 'windows-1251',
		'urdu' => 'UTF-8',
		'uzbek_cyrillic' => 'ISO-8859-5',
		'uzbek_latin' => 'ISO-8859-5',
		'vietnamese' => 'UTF-8',
		'welsh' => 'ISO-8859-1',
		'yoruba' => 'UTF-8',
	];

	/**
	 * @var array
	 *
	 * Maps character sets used in old, non-Unicode SMF language files to the
	 * corresponding MySQL aliases for those character sets. This list only
	 * includes exact matches.
	 */
	public const CHARSET_MAPS = [
		// Armenian
		'armscii-8' => 'armscii8',
		// Chinese-traditional.
		'big5' => 'big5',
		// Chinese-simplified.
		'gbk' => 'gbk',
		// West European.
		'ISO-8859-1' => 'latin1',
		// Romanian.
		'ISO-8859-2' => 'latin2',
		// Turkish.
		'ISO-8859-9' => 'latin5',
		// Latvian
		'ISO-8859-13' => 'latin7',
		// Thai.
		'tis-620' => 'tis620',
		// Persian, Chinese, etc.
		'UTF-8' => 'utf8mb3',
		// Russian.
		'windows-1251' => 'cp1251',
		// Arabic.
		'windows-1256' => 'cp1256',
	];

	/**
	 * @var array
	 *
	 * Manual character translation for a couple of rare character sets that old
	 * SMF language files might have used.
	 */
	public const TRANSLATION_TABLES = [
		'windows-1253' => [
			'0x80' => '0xE282AC',
			'0x81' => '\'\'',
			'0x82' => '0xE2809A',
			'0x83' => '0xC692',
			'0x84' => '0xE2809E',
			'0x85' => '0xE280A6',
			'0x86' => '0xE280A0',
			'0x87' => '0xE280A1',
			'0x88' => '\'\'',
			'0x89' => '0xE280B0',
			'0x8A' => '\'\'',
			'0x8B' => '0xE280B9',
			'0x8C' => '\'\'',
			'0x8D' => '\'\'',
			'0x8E' => '\'\'',
			'0x8F' => '\'\'',
			'0x90' => '\'\'',
			'0x91' => '0xE28098',
			'0x92' => '0xE28099',
			'0x93' => '0xE2809C',
			'0x94' => '0xE2809D',
			'0x95' => '0xE280A2',
			'0x96' => '0xE28093',
			'0x97' => '0xE28094',
			'0x98' => '\'\'',
			'0x99' => '0xE284A2',
			'0x9A' => '\'\'',
			'0x9B' => '0xE280BA',
			'0x9C' => '\'\'',
			'0x9D' => '\'\'',
			'0x9E' => '\'\'',
			'0x9F' => '\'\'',
			'0xA0' => '0xC2A0',
			'0xA1' => '0xCE85',
			'0xA2' => '0xCE86',
			'0xA3' => '0xC2A3',
			'0xA4' => '0xC2A4',
			'0xA5' => '0xC2A5',
			'0xA6' => '0xC2A6',
			'0xA7' => '0xC2A7',
			'0xA8' => '0xC2A8',
			'0xA9' => '0xC2A9',
			'0xAA' => '\'\'',
			'0xAB' => '0xC2AB',
			'0xAC' => '0xC2AC',
			'0xAD' => '0xC2AD',
			'0xAE' => '0xC2AE',
			'0xAF' => '0xE28095',
			'0xB0' => '0xC2B0',
			'0xB1' => '0xC2B1',
			'0xB2' => '0xC2B2',
			'0xB3' => '0xC2B3',
			'0xB4' => '0xCE84',
			'0xB5' => '0xC2B5',
			'0xB6' => '0xC2B6',
			'0xB7' => '0xC2B7',
			'0xB8' => '0xCE88',
			'0xB9' => '0xCE89',
			'0xBA' => '0xCE8A',
			'0xBB' => '0xC2BB',
			'0xBC' => '0xCE8C',
			'0xBD' => '0xC2BD',
			'0xBE' => '0xCE8E',
			'0xBF' => '0xCE8F',
			'0xC0' => '0xCE90',
			'0xC1' => '0xCE91',
			'0xC2' => '0xCE92',
			'0xC3' => '0xCE93',
			'0xC4' => '0xCE94',
			'0xC5' => '0xCE95',
			'0xC6' => '0xCE96',
			'0xC7' => '0xCE97',
			'0xC8' => '0xCE98',
			'0xC9' => '0xCE99',
			'0xCA' => '0xCE9A',
			'0xCB' => '0xCE9B',
			'0xCC' => '0xCE9C',
			'0xCD' => '0xCE9D',
			'0xCE' => '0xCE9E',
			'0xCF' => '0xCE9F',
			'0xD0' => '0xCEA0',
			'0xD1' => '0xCEA1',
			'0xD2' => '0xEFBFBD',
			'0xD3' => '0xCEA3',
			'0xD4' => '0xCEA4',
			'0xD5' => '0xCEA5',
			'0xD6' => '0xCEA6',
			'0xD7' => '0xCEA7',
			'0xD8' => '0xCEA8',
			'0xD9' => '0xCEA9',
			'0xDA' => '0xCEAA',
			'0xDB' => '0xCEAB',
			'0xDC' => '0xCEAC',
			'0xDD' => '0xCEAD',
			'0xDE' => '0xCEAE',
			'0xDF' => '0xCEAF',
			'0xE0' => '0xCEB0',
			'0xE1' => '0xCEB1',
			'0xE2' => '0xCEB2',
			'0xE3' => '0xCEB3',
			'0xE4' => '0xCEB4',
			'0xE5' => '0xCEB5',
			'0xE6' => '0xCEB6',
			'0xE7' => '0xCEB7',
			'0xE8' => '0xCEB8',
			'0xE9' => '0xCEB9',
			'0xEA' => '0xCEBA',
			'0xEB' => '0xCEBB',
			'0xEC' => '0xCEBC',
			'0xED' => '0xCEBD',
			'0xEE' => '0xCEBE',
			'0xEF' => '0xCEBF',
			'0xF0' => '0xCF80',
			'0xF1' => '0xCF81',
			'0xF2' => '0xCF82',
			'0xF3' => '0xCF83',
			'0xF4' => '0xCF84',
			'0xF5' => '0xCF85',
			'0xF6' => '0xCF86',
			'0xF7' => '0xCF87',
			'0xF8' => '0xCF88',
			'0xF9' => '0xCF89',
			'0xFA' => '0xCF8A',
			'0xFB' => '0xCF8B',
			'0xFC' => '0xCF8C',
			'0xFD' => '0xCF8D',
			'0xFE' => '0xCF8E',
		],
		'windows-1255' => [
			'0x80' => '0xE282AC',
			'0x81' => '\'\'',
			'0x82' => '0xE2809A',
			'0x83' => '0xC692',
			'0x84' => '0xE2809E',
			'0x85' => '0xE280A6',
			'0x86' => '0xE280A0',
			'0x87' => '0xE280A1',
			'0x88' => '0xCB86',
			'0x89' => '0xE280B0',
			'0x8A' => '\'\'',
			'0x8B' => '0xE280B9',
			'0x8C' => '\'\'',
			'0x8D' => '\'\'',
			'0x8E' => '\'\'',
			'0x8F' => '\'\'',
			'0x90' => '\'\'',
			'0x91' => '0xE28098',
			'0x92' => '0xE28099',
			'0x93' => '0xE2809C',
			'0x94' => '0xE2809D',
			'0x95' => '0xE280A2',
			'0x96' => '0xE28093',
			'0x97' => '0xE28094',
			'0x98' => '0xCB9C',
			'0x99' => '0xE284A2',
			'0x9A' => '\'\'',
			'0x9B' => '0xE280BA',
			'0x9C' => '\'\'',
			'0x9D' => '\'\'',
			'0x9E' => '\'\'',
			'0x9F' => '\'\'',
			'0xA0' => '0xC2A0',
			'0xA1' => '0xC2A1',
			'0xA2' => '0xC2A2',
			'0xA3' => '0xC2A3',
			'0xA4' => '0xE282AA',
			'0xA5' => '0xC2A5',
			'0xA6' => '0xC2A6',
			'0xA7' => '0xC2A7',
			'0xA8' => '0xC2A8',
			'0xA9' => '0xC2A9',
			'0xAA' => '0xC397',
			'0xAB' => '0xC2AB',
			'0xAC' => '0xC2AC',
			'0xAD' => '0xC2AD',
			'0xAE' => '0xC2AE',
			'0xAF' => '0xC2AF',
			'0xB0' => '0xC2B0',
			'0xB1' => '0xC2B1',
			'0xB2' => '0xC2B2',
			'0xB3' => '0xC2B3',
			'0xB4' => '0xC2B4',
			'0xB5' => '0xC2B5',
			'0xB6' => '0xC2B6',
			'0xB7' => '0xC2B7',
			'0xB8' => '0xC2B8',
			'0xB9' => '0xC2B9',
			'0xBA' => '0xC3B7',
			'0xBB' => '0xC2BB',
			'0xBC' => '0xC2BC',
			'0xBD' => '0xC2BD',
			'0xBE' => '0xC2BE',
			'0xBF' => '0xC2BF',
			'0xC0' => '0xD6B0',
			'0xC1' => '0xD6B1',
			'0xC2' => '0xD6B2',
			'0xC3' => '0xD6B3',
			'0xC4' => '0xD6B4',
			'0xC5' => '0xD6B5',
			'0xC6' => '0xD6B6',
			'0xC7' => '0xD6B7',
			'0xC8' => '0xD6B8',
			'0xC9' => '0xD6B9',
			'0xCA' => '0xEFBFBD',
			'0xCB' => '0xD6BB',
			'0xCC' => '0xD6BC',
			'0xCD' => '0xD6BD',
			'0xCE' => '0xD6BE',
			'0xCF' => '0xD6BF',
			'0xD0' => '0xD780',
			'0xD1' => '0xD781',
			'0xD2' => '0xD782',
			'0xD3' => '0xD783',
			'0xD4' => '0xD7B0',
			'0xD5' => '0xD7B1',
			'0xD6' => '0xD7B2',
			'0xD7' => '0xD7B3',
			'0xD8' => '0xD7B4',
			'0xD9' => '\'\'',
			'0xDA' => '\'\'',
			'0xDB' => '\'\'',
			'0xDC' => '\'\'',
			'0xDD' => '\'\'',
			'0xDE' => '\'\'',
			'0xDF' => '\'\'',
			'0xE0' => '0xD790',
			'0xE1' => '0xD791',
			'0xE2' => '0xD792',
			'0xE3' => '0xD793',
			'0xE4' => '0xD794',
			'0xE5' => '0xD795',
			'0xE6' => '0xD796',
			'0xE7' => '0xD797',
			'0xE8' => '0xD798',
			'0xE9' => '0xD799',
			'0xEA' => '0xD79A',
			'0xEB' => '0xD79B',
			'0xEC' => '0xD79C',
			'0xED' => '0xD79D',
			'0xEE' => '0xD79E',
			'0xEF' => '0xD79F',
			'0xF0' => '0xD7A0',
			'0xF1' => '0xD7A1',
			'0xF2' => '0xD7A2',
			'0xF3' => '0xD7A3',
			'0xF4' => '0xD7A4',
			'0xF5' => '0xD7A5',
			'0xF6' => '0xD7A6',
			'0xF7' => '0xD7A7',
			'0xF8' => '0xD7A8',
			'0xF9' => '0xD7A9',
			'0xFA' => '0xD7AA',
			'0xFB' => '\'\'',
			'0xFC' => '\'\'',
			'0xFD' => '0xE2808E',
			'0xFE' => '0xE2808F',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * Character sets supported by the database.
	 */
	public array $supported_charsets = [];

	/**
	 * Character set that SMF has been using to interact with the browser.
	 *
	 * This will typically (but not necessarily) have been the character set
	 * that was specified in the forum's language files.
	 */
	public string $lang_charset = 'UTF-8';

	/**
	 * The subset of self::CHARSET_MAPS that is supported by the database.
	 */
	public array $charset_maps = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id ID of the step.
	 * @param string $name Name of the step.
	 * @param int $progress The amount of progress to be made when this step
	 *    completes.
	 * @param ?string $title The page title we will display for this step.
	 *    If null, defaults to $name.
	 * @param ?string $title The sub-template to use to display for this step.
	 *    If null, will default to 'convertDatabase'.
	 */
	public function __construct(int $id, string $name, int $progress, ?string $title = null, ?string $template = null)
	{
		parent::__construct($id, $name, [$this, 'convertDatabase'], $progress, $title, $template);

		// PostgreSQL databases don't need to do this.
		if (Db::$db->title === POSTGRE_TITLE) {
			return;
		}

		// Get all the characters sets that are supported by this MySQL server.
		$request = Db::$db->query('SHOW CHARACTER SET');
		$this->supported_charsets = array_map(fn($row) => $row['Charset'], Db::$db->fetch_all($request));
		Db::$db->free_result($request);

		// Which character set have they been using for interacting with the browser?
		if (isset(Config::$modSettings['global_character_set'])) {
			$this->lang_charset = Config::$modSettings['global_character_set'];
		} elseif (version_compare(strtolower(str_replace(' ', '.', Config::$modSettings['smfVersion'])), '3.0.dev.1', '>=')) {
			$this->lang_charset = 'UTF-8';
		} else {
			// Figure it out the hard way.
			// Map in the new locales. We do it like this because we want to try
			// our best to capture the correct charset no matter what the status of
			// the language upgrade is.
			foreach (self::LANG_CHARSETS as $key => $value) {
				if (Lang::getLocaleFromLanguageName($key) === Config::$language) {
					$this->lang_charset = $value;
					break;
				}
			}
		}

		// Remove any mapped character sets that are unsupported by this MySQL server.
		$this->charset_maps = array_intersect(self::CHARSET_MAPS, $this->supported_charsets);
	}

	/**
	 * Gets the substeps for this task.
	 *
	 * There will be one substep per table.
	 *
	 * @return array Instances of SMF\Maintenance\SubStepInterface.
	 */
	public function getSubSteps(): array
	{
		// PostgreSQL databases don't need to do this.
		if (Db::$db->title === POSTGRE_TITLE) {
			return [];
		}

		$substeps = [];

		foreach (Db::$db->list_tables() as $table_name) {
			if (str_starts_with($table_name, 'backup_')) {
				continue;
			}

			$substeps[] = new GenericSubStep(
				name: Lang::getTxt('log_table_convertutf8', ['table' => $table_name], file: 'Maintenance'),
				test: [$this, 'isCandidateTable'],
				test_args: [$table_name],
				exec: [$this, 'convertTable'],
				exec_args: [$table_name],
			);
		}

		return $substeps;
	}

	/**
	 * Checks whether the specified table is a candidate for conversion.
	 *
	 * @return bool Whether the table needs to be converted to utf8mb4.
	 */
	public function isCandidateTable(string $table_name): bool
	{
		if (
			// PostgreSQL databases don't need to do this.
			Db::$db->title === POSTGRE_TITLE
			// Ignore backup tables.
			|| str_starts_with($table_name, 'backup_')
		) {
			return false;
		}

		// Must convert if the table's default character set isn't utf8mb4.
		if (Db::$db->detect_charset($table_name) !== 'utf8mb4') {
			return true;
		}

		// Must convert if any string column's character set isn't utf8mb4.
		foreach (Db::$db->list_columns($table_name, true) as $column) {
			if (!\in_array($column['type'], self::STRING_COLUMN_TYPES)) {
				continue;
			}

			if (Db::$db->detect_charset($table_name, $column['name']) !== 'utf8mb4') {
				return true;
			}
		}

		// Table does not need to be converted to utf8mb4.
		return false;
	}

	/**
	 * Converts all SMF tables to utf8mb4.
	 *
	 * @return bool Whether the operation was successful.
	 */
	public function convertDatabase(): bool
	{
		if (!empty($_POST['utf8_done'])) {
			return true;
		}

		// PostgreSQL databases don't need to do this.
		if (Db::$db->title === POSTGRE_TITLE) {
			if (Maintenance::isJson()) {
				Maintenance::jsonResponse([
					'name' => '',
					'skipped' => true,
					'substep' => 0,
					'start' => 0,
					'total' => 0,
					'debug' => [
						'call' => '',
					],
				]);
			}

			return true;
		}

		$substeps = $this->getSubSteps();

		Maintenance::$total_substeps = \count($substeps);

		// Template things.
		Maintenance::$context['table_count'] = Maintenance::$total_substeps;
		Maintenance::$context['cur_table_num'] = Maintenance::getCurrentSubStep();
		Maintenance::$context['cur_table_name'] = str_replace(Config::$db_prefix, '', $substeps[Maintenance::getCurrentSubStep()]->test_args[0]);
		Maintenance::$context['continue'] = true;

		// We are set up for conversion.
		if (!Sapi::isCLI() && !Maintenance::isJson()) {
			return false;
		}

		if (Maintenance::getCurrentSubStep() === 0 && Maintenance::getCurrentStart() === 0) {
			Maintenance::$tool->logProgress(Lang::getTxt('log_starting_step', ['num' => Maintenance::$tool->getStep()->getId(), 'step' => Maintenance::$tool->getStep()->getName()]));
		}

		if (Maintenance::$total_substeps === 0) {
			if (Maintenance::isJson()) {
				Maintenance::jsonResponse([
					'name' => '',
					'skipped' => true,
					'substep' => 0,
					'start' => 0,
					'total' => 0,
					'debug' => [
						'call' => '',
					],
				]);
			}

			return true;
		}

		while (Maintenance::getCurrentSubStep() < Maintenance::$total_substeps) {
			$substep = $substeps[Maintenance::getCurrentSubStep()];

			Maintenance::$tool->logProgress(' +++ ' . $substep->name, true);

			try {
				if (!$substep->isCandidate()) {
					Maintenance::setCurrentSubStep();

					Maintenance::$tool->logProgress(Lang::getTxt('log_skipped', file: 'Maintenance'));

					Maintenance::jsonResponse([
						'name' => $substep->name,
						'next' => $substeps[Maintenance::getCurrentSubStep()]->name ?? '',
						'skipped' => true,
						'substep' => Maintenance::getCurrentSubStep(),
						'start' => Maintenance::getCurrentStart(),
						'total' => Maintenance::$total_substeps,
						'debug' => [
							'call' => $substep::class,
						],
					]);

					continue;
				}
			} catch (\Throwable $e) {
				Maintenance::$tool->logProgress(Lang::getTxt('log_failed_with_error', ['error' => $e->getMessage()], file: 'Maintenance'));

				Maintenance::jsonResponse([
					'name' => $substep->name,
					'failed' => true,
					'substep' => Maintenance::getCurrentSubStep(),
					'start' => Maintenance::getCurrentStart(),
					'total' => Maintenance::$total_substeps,
					'debug' => [
						'call' => $substep::class,
						'msg' => $e->getMessage(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					],
				]);

				return false;
			}

			try {
				if (!$substep->execute()) {
					Maintenance::$tool->logProgress(Lang::getTxt('log_failed', file: 'Maintenance'));

					Maintenance::jsonResponse([
						'name' => $substep->name,
						'completed' => false,
						'substep' => Maintenance::getCurrentSubStep(),
						'start' => Maintenance::getCurrentStart(),
						'total' => Maintenance::$total_substeps,
						'debug' => [
							'call' => $substep::class,
						],
					]);

					return false;
				}
			} catch (\Throwable $e) {
				Maintenance::$tool->logProgress(Lang::getTxt('log_failed_with_error', ['error' => $e->getMessage()], file: 'Maintenance'));

				Maintenance::jsonResponse([
					'name' => $substep->name,
					'failed' => true,
					'substep' => Maintenance::getCurrentSubStep(),
					'start' => Maintenance::getCurrentStart(),
					'total' => Maintenance::$total_substeps,
					'debug' => [
						'call' => $substep::class,
						'msg' => $e->getMessage(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					],
				]);

				return false;
			}

			Maintenance::$tool->logProgress(Lang::getTxt('log_done', file: 'Maintenance'));

			// Increase our current substep by 1.
			Maintenance::setCurrentSubStep();
			Maintenance::setCurrentStart(0);

			// If this is JSON to keep it nice for the user do one table at a time anyway!
			if (Maintenance::isJson()) {
				Maintenance::jsonResponse([
					'name' => $substep->name,
					'next' => $substeps[Maintenance::getCurrentSubStep()]->name,
					'completed' => true,
					'substep' => Maintenance::getCurrentSubStep(),
					'start' => Maintenance::getCurrentStart(),
					'total' => Maintenance::$total_substeps,
					'debug' => [
						'call' => $substep::class,
					],
				]);
			}
		}

		return true;
	}

	/**
	 * Converts the specified table, and all applicable columns in that table,
	 * to utf8mb4.
	 *
	 * @return bool Whether the operation was successful.
	 */
	public function convertTable(string $table_name): bool
	{
		// PostgreSQL databases don't need to do this.
		if (Db::$db->title === POSTGRE_TITLE) {
			return true;
		}

		// Just to make sure it doesn't time out.
		Sapi::setTimeLimit();

		// Get the structural info about the table.
		$table = Db::$db->table_structure($table_name);

		// Get the character set for the table.
		$table['charset'] = Db::$db->detect_charset($table_name);

		// Get the character set for each column.
		foreach ($table['columns'] as $c => $column) {
			if (!\in_array($column['type'], self::STRING_COLUMN_TYPES)) {
				continue;
			}

			$table['columns'][$c]['charset'] = Db::$db->detect_charset($table_name, $column['name']);
		}

		// If there's a fulltext index, we need to drop it first...
		foreach ($table['indexes'] as $i => $index) {
			if ($index['type'] === 'fulltext') {
				Db::$db->remove_index(
					table_name: $table_name,
					index_name: $index['name'],
				);

				if (
					$table_name === 'messages'
					&& (Config::$modSettings['search_index'] ?? null) === 'fulltext'
				) {
					Config::updateModSettings(['search_index' => '']);
					Maintenance::$context['dropping_index'] = true;
				}
			}
		}

		// Is the table already using some version of Unicode?
		$table_is_unicode = str_starts_with($table['charset'], 'utf') || $table['charset'] === 'ucs2';

		// We might need to do each column individually.
		$convert_columns_individually = !(
			// Probably don't need to if the table uses the expected charset.
			$table['charset'] === ($this->charset_maps[$this->lang_charset] ?? null)
			// Probably don't need to if they're just different versions of Unicode.
			|| (
				$table_is_unicode
				&& (
					!isset($this->charset_maps[$this->lang_charset])
					|| str_starts_with($this->charset_maps[$this->lang_charset], 'utf')
					|| $this->charset_maps[$this->lang_charset] === 'ucs2'
				)
			)
		);

		$string_columns = [];

		foreach ($table['columns'] as $c => $column) {
			if (!\in_array($column['type'], self::STRING_COLUMN_TYPES)) {
				continue;
			}

			$string_columns[] = $column['name'];

			// We need to do each column individually if any of them use a
			// different character set than the table as a whole.
			if ($column['charset'] !== $table['charset']) {
				$convert_columns_individually = true;
			}
		}

		// Keep track of whether all columns are prepared for conversion.
		$prepared_columns = [];

		// Convert each column from text to binary and maybe do other stuff.
		if ($convert_columns_individually) {
			foreach ($string_columns as $column_name) {
				if ($this->prepareColumn($table, $column_name)) {
					$prepared_columns[] = $column_name;
				}
			}
		} else {
			$prepared_columns = $string_columns;
		}

		// Change the table's character set to utf8mb4.
		$result = Db::$db->query(
			'ALTER TABLE {identifier:table_name}
			CONVERT TO CHARACTER SET utf8mb4',
			[
				'table_name' => $table_name,
				'db_error_skip' => true,
			],
		);

		// Convert each column from binary back to text.
		if ($convert_columns_individually) {
			foreach ($prepared_columns as $column) {
				Db::$db->change_column(
					$table_name,
					$column,
					[
						'type' => $table['columns'][$column]['type'],
					],
				);
			}
		}

		// @todo Restore any fulltext indexes we deleted above.

		// If the conversion failed, return false now.
		if ($result === false) {
			return false;
		}

		// Create a background task to convert entities to characters.
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string-255',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			[
				[
					'\\SMF\\Tasks\\Utf8EntityDecode',
					json_encode([
						'table' => $table_name,
						'offset' => 0,
					]),
					0,
				],
			],
			[],
		);

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Converts a column from text to binary and, if necessary, manually
	 * converts byte sequences in data stored using the wrong character set.
	 *
	 * @return bool Whether the column is now ready for conversion.
	 */
	protected function prepareColumn(array $table, string $column): bool
	{
		// We don't need to do anything if either of the following are true:
		if (
			// The table and column both use the same character set.
			$table['columns'][$column]['charset'] === $table['charset']
			// The column already uses utf8mb4.
			|| $table['columns'][$column]['charset'] === 'utf8mb4'
		) {
			return true;
		}

		// First, convert the column to binary.
		Db::$db->change_column(
			'{db_prefix}' . $table['name'],
			$table['columns'][$column]['name'],
			[
				'type' => strtr($table['columns'][$column]['type'], ['text' => 'blob', 'char' => 'binary']),
			],
		);

		// Which encoding should we be converting from?
		if (!isset($this->charset_maps[$this->lang_charset])) {
			// $this->lang_charset doesn't map to a supported database charset,
			// which means that the string was stored using the wrong charset but
			// still would have been interpreted as $this->lang_charset once
			// retrieved.
			$from_charset = $this->lang_charset;
		} else {
			// This column simply isn't using the table's default character set.
			$from_charset = $table['columns'][$column]['charset'];
		}

		// If $from_charset is already some variant of UTF-8, we don't need to
		// deal with the byte-level conversion step.
		if (str_starts_with(strtolower($from_charset), 'utf8')) {
			return true;
		}

		// If the data was stored in the wrong charset, we must convert it manually.
		if (!\in_array($from_charset, $this->supported_charsets)) {
			// Build a huge REPLACE statement.
			$replace = '{identifier:column}';

			if (isset($translation_tables[$from_charset])) {
				foreach ($translation_tables[$from_charset] as $from => $to) {
					$replace = 'REPLACE(' . $replace . ', ' . $from . ', ' . $to . ')';
				}
			} else {
				try {
					for ($i = 0; $i <= 0xFF; $i++) {
						$from = '0x' . strtoupper(dechex($i));
						$to = '0x' . strtoupper(bin2hex(mb_convert_encoding(\chr($i), 'UTF-8', $from_charset)));

						if ($from !== $to) {
							$replace = 'REPLACE(' . $replace . ', ' . $from . ', ' . $to . ')';
						}
					}
				} catch (\Throwable $e) {
					// mb_convert_encoding will throw a ValueError if
					// either encoding is unrecognized.
					return false;
				}
			}

			// Convert the characters to UTF-8, using raw bytes.
			$result = Db::$db->query(
				'UPDATE {identifier:table}
				SET {identifier:column} = ' . $replace,
				[
					'table' => Db::$db->quote('{db_prefix}' . $table['name']),
					'column' => $table['columns'][$column]['name'],
					'db_error_skip' => true,
				],
			);

			if ($result === false) {
				return false;
			}
		}

		return true;
	}
}
