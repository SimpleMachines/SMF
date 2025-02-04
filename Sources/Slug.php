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

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Localization\AsciiTransliterator;

/**
 * Creates slug strings for use in queryless URLs.
 */
class Slug implements \Stringable
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * The slug in the requested URL, if any.
	 *
	 * When applicable, this is set during route parsing.
	 */
	public static self $requested;

	/**
	 * @var array
	 *
	 * Slugs that we know to be correct.
	 */
	public static array $known = [
		'board' => [],
		'topic' => [],
		'member' => [],
		'group' => [],
	];

	/**
	 * @var array
	 *
	 * Patterns used to build redirection URLs if an incorrect slug was provided
	 * in the requested URL. Used by Slug::redirectNoncanonical().
	 *
	 * Keys are slug types. Values are sub-arrays containing information for
	 * building a query string for the redirection URL.
	 *
	 * Within each sub-array, keys are URL query parameter names and values are
	 * patterns for creating the URL query parameter values.
	 *
	 * The patterns can contain literal text and/or substitution tokens.
	 *
	 * Substitution tokens are surrounded by braces. The content inside the
	 * braces is usually a $_REQUEST key, with two special cases:
	 *
	 *  - The special token `{id}` will be replaced with the slug's $id value.
	 *
	 *  - The special token `{start}` will be replaced with $_REQUEST['start']
	 *    or with `0` if $_REQUEST['start'] is not set.
	 *
	 * All other tokens will be replaced with the indicated $_REQUEST variable
	 * or with an empty string if that $_REQUEST variable is not set.
	 *
	 * Once all substitutions have been made, the set of parameters will be
	 * filtered. Parameters that are intentionally valueless (i.e. the pattern
	 * itself is an empty string) will be retained, but any other valueless
	 * parameters will be discarded from the compiled URL query string.
	 *
	 * MOD AUTHORS:
	 *
	 * 1. To add patterns for custom slug types to this array or to adjust the
	 *    existing patterns, use the integrate_slug_redirect_patterns hook.
	 *
	 * 2. Redirect patterns use a normal URL query, not a queryless URL. The
	 *    query will be rewritten to a queryless URL by other code elsewhere.
	 */
	public static array $redirect_patterns = [
		'board' => [
			'board' => '{id}.{start}',
		],
		'topic' => [
			'topic' => '{id}.{start}',
		],
		'member' => [
			'action' => 'profile',
			'area' => '{area}',
			'sa' => '{sa}',
			'u' => '{id}',
		],
		'group' => [
			'action' => 'groups',
			'sa' => 'members',
			'group' => '{id}',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * The slug string.
	 */
	protected string $slug;

	/**
	 * @var int
	 *
	 * The ID of the item associated with this slug.
	 */
	protected int $id;

	/**
	 * @var string
	 *
	 * The type of the item associated with this slug.
	 */
	protected string $type;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var string
	 *
	 * Regular expression to match common words in the current language.
	 */
	protected static string $common_words_regex;

	/**
	 * @var bool
	 *
	 * Whether the integrate_slug_redirect_patterns hook has been called yet.
	 */
	protected static bool $called_redirect_patterns_hook = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $string The original string.
	 * @param string $type Type of the associated item.
	 * @param int $id ID of the associated item.
	 * @param int $max_length Maximum length of the slug string. Default: 30.
	 */
	public function __construct(string $string, string $type, int $id, int $max_length = 30)
	{
		$this->id = $id;
		$this->type = $type;

		// Empty string means empty slug.
		if ($string === '') {
			$this->slug = '';
		}

		// Is it cached?
		if (!isset($this->slug)) {
			$slug = self::getCached($type, $id);

			if ($slug !== '') {
				$this->slug = $slug;
			}
		}

		// Build the slug from the string.
		if (!isset($this->slug)) {
			$this->build($string, $max_length);

			// Prevent silly things like 'board-5-5'.
			if (str_ends_with($this->slug, '-' . $this->id)) {
				$this->slug = mb_substr($this->slug, 0, mb_strrpos($this->slug, '-'));
			}

			// Cache it.
			if ($this->slug !== '') {
				CacheApi::put('slug_type-' . $this->type . '_id-' . $this->id, $this->slug);
			}
		}

		self::$known[$this->type][$this->id] = $this;

		// Make sure we always show the correct slug.
		self::redirectNoncanonical($this);
	}

	/**
	 * Return the slug string.
	 */
	public function __toString(): string
	{
		return $this->slug;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience wrapper for constructor.
	 *
	 * @param string $string The original string.
	 * @param string $type Type of the associated item.
	 * @param int $id ID of the associated item.
	 * @param int $max_length Maximum length of the slug string. Default: 30.
	 * @return self The created object.
	 */
	public static function create(string $string, string $type, int $id, int $max_length = 30): self
	{
		return new self($string, $type, $id, $max_length);
	}

	/**
	 * Sets self::$requested to the slug in the requested URL.
	 *
	 * @param string $slug The slug string provided in the requested URL.
	 * @param string $type Type of the associated item.
	 * @param int $id ID of the associated item.
	 */
	public static function setRequested(string $slug, string $type, int $id): void
	{
		self::$requested = new self('', $type, $id);
		self::$requested->slug = $slug;
	}

	/**
	 * Attempts to fetch a slug string from the cache.
	 *
	 * @param string $type Type of the associated item.
	 * @param int $id ID of the associated item.
	 * @return string The cached slug string, or an empty string on failure.
	 */
	public static function getCached(string $type, int $id): string
	{
		if (!CacheApi::$enable) {
			return '';
		}

		return (string) CacheApi::get('slug_type-' . $type . '_id-' . $id);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Builds $this->slug.
	 *
	 * @param string $string The original string.
	 * @param int $max_length Maximum length of the slug string.
	 */
	protected function build(string $string, int $max_length): void
	{
		// Decode any percent-encoding.
		$this->slug = rawurldecode($string);

		// Decode any HTML entities.
		$this->slug = Utils::entityDecode($this->slug, true);

		// Get rid of formatting characters, punctuation, etc.
		// Note: does not remove apostrophes inside words, so we do that later.
		$this->slug = implode(' ', Utils::extractWords($this->slug, 2));

		// Remove common words.
		if (!isset(self::$common_words_regex)) {
			Lang::load('Search');

			self::$common_words_regex = '/\b' . Utils::buildRegex(explode(',', Lang::getTxt('search_stopwords')), '/') . '\b/iu';
		}

		$this->slug = preg_replace(self::$common_words_regex, ' ', $this->slug);

		// Do we want an ASCII slug or a Unicode slug?
		if (!empty(Config::$modSettings['use_ascii_slugs'])) {
			// Transliterate.
			$this->slug = AsciiTransliterator::toAscii($this->slug);

			// Convert to lower case.
			$this->slug = Utils::convertCase($this->slug, 'lower', false, 'c', true);

			// Remove unwanted stuff and hyphenate.
			$this->slug = preg_replace(
				[
					// Remove apostrophes and quotation marks within words.
					'/\B["\']\B/',

					// Replace all characters that aren't letters or numbers with hyphens.
					'/[^\p{L}\p{N}]/',

					// Collapse runs of hyphens.
					'/-+/',

					// Trim leading and trailing hyphens.
					'/^-|-$/',
				],
				[
					'',
					'-',
					'-',
					'',
				],
				$this->slug,
			);
		} else {
			// Convert to lower case and decomposed form.
			$this->slug = Utils::convertCase($this->slug, 'lower', false, 'd', true);

			// Remove unwanted stuff and hyphenate.
			$this->slug = preg_replace(
				[
					// Remove apostrophes, quotation marks, etc., within words.
					'/(?<=[\p{L}\p{M}\p{N}])["\'\p{Pi}\p{Pf}](?=[\p{L}\p{M}\p{N}])/u',

					// Remove combining and enclosing marks.
					'/[\p{Mn}\p{Me}]/u',

					// Replace all characters that aren't letters or numbers with hyphens.
					// Note: ideographic characters count as letters.
					'/[^\p{L}\p{N}]/u',

					// Collapse runs of hyphens.
					'/-+/',

					// Trim leading and trailing hyphens.
					'/^-|-$/',
				],
				[
					'',
					'',
					'-',
					'-',
					'',
				],
				$this->slug,
			);

			// Convert back to composed form.
			$this->slug = Utils::normalize($this->slug, 'c');
		}

		// Truncate to fit within the allowed length.
		while (mb_strlen($this->slug) > $max_length) {
			$this->slug = mb_substr($this->slug, 0, str_contains($this->slug, '-') ? mb_strrpos($this->slug, '-') : $max_length);
		}

		// Give mods a chance to adjust the slug string.
		IntegrationHook::call('integrate_make_slug', [$string, &$this->slug]);
	}

	/**
	 * If this slug is for the item requested in the URL, but the URL used the
	 * wrong slug, this method issues a redirect to the canonical URL.
	 */
	protected function redirectNoncanonical(): void
	{
		// Allow mods to add patterns for custom slug types to self::$redirect_patterns.
		if (!self::$called_redirect_patterns_hook) {
			IntegrationHook::call('integrate_slug_redirect_patterns', [&self::$redirect_patterns]);

			// Only call the hook once.
			self::$called_redirect_patterns_hook = true;
		}

		if (
			isset(self::$requested)
			&& self::$requested->type === $this->type
			&& self::$requested->id === $this->id
			&& self::$requested->slug !== $this->slug
			&& isset(self::$redirect_patterns[$this->type])
		) {
			$params = [];

			foreach (self::$redirect_patterns[$this->type] as $param => $pattern) {
				$params[$param] = preg_replace_callback(
					'/{(\w+)}/',
					fn($matches) => $matches[1] === 'id' ? $this->id : ($matches[1] === 'start' ? ($_REQUEST['start'] ?? 0) : ($_REQUEST[$matches[1]] ?? '')),
					$pattern,
				);


				if ($params[$param] === '' && $pattern !== '') {
					unset($params[$param]);
				}
			}

			if (!empty($params)) {
				Utils::redirectexit('?' . http_build_query($params, '', ';'));
			}
		}
	}
}

?>