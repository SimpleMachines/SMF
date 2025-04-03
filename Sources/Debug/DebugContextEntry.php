<?php

namespace SMF\Debug;

/**
 * Represents a single entry in the debug context, containing information
 * about debug details and how they should be rendered.
 */
class DebugContextEntry
{
	/**
	 * @param string|null $extra_before A string that is rendered before any other content.
	 *    Defaults to `null`.
	 * @param array|null $source An array of strings representing the source content
	 *    to be displayed. Defaults to `null`.
	 * @param string|null $before_source A string to prepend before each source element.
	 *    Defaults to `<code>`.
	 * @param string|null $after_source A string to append after each source element.
	 *    Defaults to `</code>`.
	 * @param string|null $glue_sources A string used to join multiple source elements.
	 *    Defaults to `, `.
	 * @param bool|null $toggle Indicates whether a `<details>` HTML element should
	 *    be created. If set to `false`, a `<div>` is used instead.
	 *    Defaults to `true`.
	 * @param bool|null $open When `$toggle` is `true`, determines if the `<details>`
	 *    element should be open by default. Defaults to `false`.
	 * @param int|null $num The number to pass as the `num` parameter to `Lang::getTxt`.
	 *    If not provided, it defaults to the count of `$source` or `0`.
	 * @param string|null $extra_after A string that is rendered after all other content.
	 *    Defaults to `null`.
	 * @param array|null $extra_lang_params An array of additional parameters to pass to `Lang::getTxt`.
	 *    These can be used to customize the language text.
	 *    Defaults to an empty array.
	 */
	public function __construct(
		public ?string $extra_before = null,
		public ?array $source = null,
		public ?string $before_source = '<code>',
		public ?string $after_source = '</code>',
		public ?string $glue_sources = ', ',
		public ?bool $toggle = true,
		public ?bool $open = false,
		public ?int $num = null,
		public ?string $extra_after = null,
		public ?array $extra_lang_params = [],
	) {
		// Automatically calculate `num` if not explicitly provided and `source` exists.
		if ($this->num === null && $this->source !== null) {
			$this->num = count($this->source);
		}
	}
}

?>