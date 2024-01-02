<?php

/**
 * The XmlArray class is an XML parser.
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

namespace SMF\PackageManager;

use SMF\Config;
use SMF\Lang;

/**
 * Class XmlArray
 * Represents an XML array
 */
class XmlArray
{
	/**
	 * @var array Holds parsed XML results
	 */
	public $array;

	/**
	 * @var int The debugging level
	 */
	public $debug_level;

	/**
	 * holds trim level textual data
	 *
	 * @var bool Holds trim level textual data
	 */
	public $trim;

	/**
	 * Constructor for the xml parser.
	 * Example use:
	 *  $xml = new XmlArray(file('data.xml'));
	 *
	 * @param string|array $data The xml data or an array of, unless is_clone is true.
	 * @param bool $auto_trim Used to automatically trim textual data.
	 * @param int $level The debug level. Specifies whether notices should be generated for missing elements and attributes.
	 * @param bool $is_clone default false. If is_clone is true, the  XmlArray is cloned from another - used internally only.
	 */
	public function __construct(string|array $data, bool $auto_trim = false, ?int $level = null, bool $is_clone = false)
	{
		// If we're using this try to get some more memory.
		Config::setMemoryLimit('32M');

		// Set the debug level.
		$this->debug_level = $level !== null ? $level : error_reporting();
		$this->trim = $auto_trim;

		// Is the data already parsed?
		if ($is_clone) {
			$this->array = $data;

			return;
		}

		// Is the input an array? (ie. passed from file()?)
		if (is_array($data)) {
			$data = implode('', $data);
		}

		// Remove any xml declaration or doctype, and parse out comments and CDATA.
		$data = preg_replace('/<!--.*?-->/s', '', $this->_to_cdata(preg_replace(['/^<\?xml.+?\?' . '>/is', '/<!DOCTYPE[^>]+?' . '>/s'], '', $data)));

		// Now parse the xml!
		$this->array = $this->_parse($data);
	}

	/**
	 * Get the root element's name.
	 * Example use:
	 *  echo $element->name();
	 *
	 * @return string The root element's name
	 */
	public function name(): string
	{
		return $this->array['name'] ?? '';
	}

	/**
	 * Get a specified element's value or attribute by path.
	 * Children are parsed for text, but only textual data is returned
	 * unless get_elements is true.
	 * Example use:
	 *  $data = $xml->fetch('html/head/title');
	 *
	 * @param string $path The path to the element to fetch
	 * @param bool $get_elements Whether to include elements
	 * @return string The value or attribute of the specified element
	 */
	public function fetch(string $path, bool $get_elements = false): string|bool
	{
		// Get the element, in array form.
		$array = $this->path($path);

		if ($array === false) {
			return false;
		}

		// Getting elements into this is a bit complicated...
		if ($get_elements && !is_string($array)) {
			$temp = '';

			// Use the _xml() function to get the xml data.
			foreach ($array->array as $val) {
				// Skip the name and any attributes.
				if (is_array($val)) {
					$temp .= $this->_xml($val, null);
				}
			}

			// Just get the XML data and then take out the CDATAs.
			return $this->_to_cdata($temp);
		}

		// Return the value - taking care to pick out all the text values.
		return is_string($array) ? $array : $this->_fetch($array->array);
	}

	/** Get an element, returns a new XmlArray.
	 * It finds any elements that match the path specified.
	 * It will always return a set if there is more than one of the element
	 * or return_set is true.
	 * Example use:
	 *  $element = $xml->path('html/body');
	 *
	 * @param string $path The path to the element to get
	 * @param bool $return_full Whether to return the full result set
	 * @return XmlArray|string|bool a new XmlArray. False if we can not find a attribute
	 */
	public function path(string $path, bool $return_full = false): XmlArray|string|false
	{
		// Split up the path.
		$path = explode('/', $path);

		// Start with a base array.
		$array = $this->array;

		// For each element in the path.
		foreach ($path as $el) {
			// Deal with sets....
			if (strpos($el, '[') !== false) {
				$lvl = (int) substr($el, strpos($el, '[') + 1);
				$el = substr($el, 0, strpos($el, '['));
			}
			// Find an attribute.
			elseif (substr($el, 0, 1) == '@') {
				// It simplifies things if the attribute is already there ;).
				if (isset($array[$el])) {
					return $array[$el];
				}

				$trace = debug_backtrace();
				$i = 0;

				while ($i < count($trace) && isset($trace[$i]['class']) && $trace[$i]['class'] == get_class($this)) {
					$i++;
				}
				$debug = ' (from ' . $trace[$i - 1]['file'] . ' on line ' . $trace[$i - 1]['line'] . ')';

				// Cause an error.
				if ($this->debug_level & E_NOTICE) {
					Lang::load('Errors');
					trigger_error(sprintf(Lang::$txt['undefined_xml_attribute'], substr($el, 1) . $debug), E_USER_NOTICE);
				}

				return false;
			} else {
				$lvl = null;
			}

			// Find this element.
			$array = $this->_path($array, $el, $lvl);
		}

		// Clean up after $lvl, for $return_full.
		if ($return_full && (!isset($array['name']) || substr($array['name'], -1) != ']')) {
			$array = ['name' => $el . '[]', $array];
		}

		// Create the right type of class...
		$newClass = get_class($this);

		// Return a new XmlArray for the result.
		return $array === false ? false : new $newClass($array, $this->trim, $this->debug_level, true);
	}

	/**
	 * Check if an element exists.
	 * Example use,
	 *  echo $xml->exists('html/body') ? 'y' : 'n';
	 *
	 * @param string $path The path to the element to get.
	 * @return bool Whether the specified path exists
	 */
	public function exists(string $path): bool
	{
		// Split up the path.
		$path = explode('/', $path);

		// Start with a base array.
		$array = $this->array;

		// For each element in the path.
		foreach ($path as $el) {
			// Deal with sets....
			if (strpos($el, '[') !== false) {
				$lvl = (int) substr($el, strpos($el, '[') + 1);
				$el = substr($el, 0, strpos($el, '['));
			}
			// Find an attribute.
			elseif (substr($el, 0, 1) == '@') {
				return isset($array[$el]);
			} else {
				$lvl = null;
			}

			// Find this element.
			$array = $this->_path($array, $el, $lvl, true);
		}

		return $array !== false;
	}

	/**
	 * Count the number of occurrences of a path.
	 * Example use:
	 *  echo $xml->count('html/head/meta');
	 *
	 * @param string $path The path to search for.
	 * @return int The number of elements the path matches.
	 */
	public function count(string $path): int
	{
		// Get the element, always returning a full set.
		$temp = $this->path($path, true);

		// Start at zero, then count up all the numeric keys.
		$i = 0;

		foreach ($temp->array as $item) {
			if (is_array($item)) {
				$i++;
			}
		}

		return $i;
	}

	/**
	 * Get an array of XmlArray's matching the specified path.
	 * This differs from ->path(path, true) in that instead of an XmlArray
	 * of elements, an array of XmlArray's is returned for use with foreach.
	 * Example use:
	 *  foreach ($xml->set('html/body/p') as $p)
	 *
	 * @param string $path The path to search for.
	 * @return XmlArray[] An array of XmlArray objects
	 */
	public function set(string $path): array
	{
		// None as yet, just get the path.
		$array = [];
		$xml = $this->path($path, true);

		foreach ($xml->array as $val) {
			// Skip these, they aren't elements.
			if (!is_array($val) || $val['name'] == '!') {
				continue;
			}

			// Create the right type of class...
			$newClass = get_class($this);

			// Create a new XmlArray and stick it in the array.
			$array[] = new $newClass($val, $this->trim, $this->debug_level, true);
		}

		return $array;
	}

	/**
	 * Create an xml file from an XmlArray, the specified path if any.
	 * Example use:
	 *  echo $this->create_xml();
	 *
	 * @param string $path The path to the element. (optional)
	 * @return string Xml-formatted string.
	 */
	public function create_xml(?string $path = null): string
	{
		// Was a path specified?  If so, use that array.
		if ($path !== null) {
			$path = $this->path($path);

			// The path was not found
			if ($path === false) {
				return false;
			}

			$path = $path->array;
		}
		// Just use the current array.
		else {
			$path = $this->array;
		}

		// Add the xml declaration to the front.
		return '<?xml version="1.0"?' . '>' . $this->_xml($path, 0);
	}

	/**
	 * Output the xml in an array form.
	 * Example use:
	 *  print_r($xml->to_array());
	 *
	 * @param string $path The path to output.
	 * @return array An array of XML data
	 */
	public function to_array(?string $path = null): array
	{
		// Are we doing a specific path?
		if ($path !== null) {
			$path = $this->path($path);

			// The path was not found
			if ($path === false) {
				return [];
			}

			$path = $path->array;
		}
		// No, so just use the current array.
		else {
			$path = $this->array;
		}

		return $this->_array($path);
	}

	/**
	 * Parse data into an array. (privately used...)
	 *
	 * @param string $data The data to parse
	 * @return array The parsed array
	 */
	protected function _parse(string $data): array
	{
		// Start with an 'empty' array with no data.
		$current = [
		];

		// Loop until we're out of data.
		while ($data != '') {
			// Find and remove the next tag.
			preg_match('/\A<([\w\-:]+)((?:\s+.+?)?)([\s]?\/)?' . '>/', $data, $match);

			if (isset($match[0])) {
				$data = preg_replace('/' . preg_quote($match[0], '/') . '/s', '', $data, 1);
			}

			// Didn't find a tag?  Keep looping....
			if (!isset($match[1]) || $match[1] == '') {
				// If there's no <, the rest is data.
				if (strpos($data, '<') === false) {
					$text_value = $this->_from_cdata($data);
					$data = '';

					if ($text_value != '') {
						$current[] = [
							'name' => '!',
							'value' => $text_value,
						];
					}
				}
				// If the < isn't immediately next to the current position... more data.
				elseif (strpos($data, '<') > 0) {
					$text_value = $this->_from_cdata(substr($data, 0, strpos($data, '<')));
					$data = substr($data, strpos($data, '<'));

					if ($text_value != '') {
						$current[] = [
							'name' => '!',
							'value' => $text_value,
						];
					}
				}
				// If we're looking at a </something> with no start, kill it.
				elseif (strpos($data, '<') !== false && strpos($data, '<') == 0) {
					if (strpos($data, '<', 1) !== false) {
						$text_value = $this->_from_cdata(substr($data, 0, strpos($data, '<', 1)));
						$data = substr($data, strpos($data, '<', 1));

						if ($text_value != '') {
							$current[] = [
								'name' => '!',
								'value' => $text_value,
							];
						}
					} else {
						$text_value = $this->_from_cdata($data);
						$data = '';

						if ($text_value != '') {
							$current[] = [
								'name' => '!',
								'value' => $text_value,
							];
						}
					}
				}

				// Wait for an actual occurrence of an element.
				continue;
			}

			// Create a new element in the array.
			$el = &$current[];
			$el['name'] = $match[1];

			// If this ISN'T empty, remove the close tag and parse the inner data.
			if ((!isset($match[3]) || trim($match[3]) != '/') && (!isset($match[2]) || trim($match[2]) != '/')) {
				// Because PHP 5.2.0+ seems to croak using regex, we'll have to do this the less fun way.
				$last_tag_end = strpos($data, '</' . $match[1] . '>');

				if ($last_tag_end === false) {
					continue;
				}

				$offset = 0;

				while (1 == 1) {
					// Where is the next start tag?
					$next_tag_start = strpos($data, '<' . $match[1], $offset);

					// If the next start tag is after the last end tag then we've found the right close.
					if ($next_tag_start === false || $next_tag_start > $last_tag_end) {
						break;
					}

					// If not then find the next ending tag.
					$next_tag_end = strpos($data, '</' . $match[1] . '>', $offset);

					// Didn't find one? Then just use the last and sod it.
					if ($next_tag_end === false) {
						break;
					}

					$last_tag_end = $next_tag_end;
					$offset = $next_tag_start + 1;
				}
				// Parse the insides.
				$inner_match = substr($data, 0, $last_tag_end);
				// Data now starts from where this section ends.
				$data = substr($data, $last_tag_end + strlen('</' . $match[1] . '>'));

				if (!empty($inner_match)) {
					// Parse the inner data.
					if (strpos($inner_match, '<') !== false) {
						$el += $this->_parse($inner_match);
					} elseif (trim($inner_match) != '') {
						$text_value = $this->_from_cdata($inner_match);

						if ($text_value != '') {
							$el[] = [
								'name' => '!',
								'value' => $text_value,
							];
						}
					}
				}
			}

			// If we're dealing with attributes as well, parse them out.
			if (isset($match[2]) && $match[2] != '') {
				// Find all the attribute pairs in the string.
				preg_match_all('/([\w:]+)="(.+?)"/', $match[2], $attr, PREG_SET_ORDER);

				// Set them as @attribute-name.
				foreach ($attr as $match_attr) {
					$el['@' . $match_attr[1]] = $match_attr[2];
				}
			}
		}

		// Return the parsed array.
		return $current;
	}

	/**
	 * Get a specific element's xml. (privately used...)
	 *
	 * @param array $array An array of element data
	 * @param null|int $indent How many levels to indent the elements (null = no indent)
	 * @return string The formatted XML
	 */
	protected function _xml(array $array, ?int $indent): string
	{
		$indentation = $indent !== null ? '
' . str_repeat('	', $indent) : '';

		// This is a set of elements, with no name...
		if (is_array($array) && !isset($array['name'])) {
			$temp = '';

			foreach ($array as $val) {
				$temp .= $this->_xml($val, $indent);
			}

			return $temp;
		}

		// This is just text!
		if ($array['name'] == '!') {
			return $indentation . '<![CDATA[' . $array['value'] . ']]>';
		}

		if (substr($array['name'], -2) == '[]') {
			$array['name'] = substr($array['name'], 0, -2);
		}

		// Start the element.
		$output = $indentation . '<' . $array['name'];

		$inside_elements = false;
		$output_el = '';

		// Run through and recursively output all the elements or attributes inside this.
		foreach ($array as $k => $v) {
			if (substr($k, 0, 1) == '@') {
				$output .= ' ' . substr($k, 1) . '="' . $v . '"';
			} elseif (is_array($v)) {
				$output_el .= $this->_xml($v, $indent === null ? null : $indent + 1);
				$inside_elements = true;
			}
		}

		// Indent, if necessary.... then close the tag.
		if ($inside_elements) {
			$output .= '>' . $output_el . $indentation . '</' . $array['name'] . '>';
		} else {
			$output .= ' />';
		}

		return $output;
	}

	/**
	 * Return an element as an array
	 *
	 * @param array $array An array of data
	 * @return string|array A string with the element's value or an array of element data
	 */
	protected function _array(array $array): string|array
	{
		$return = [];
		$text = '';

		foreach ($array as $value) {
			if (!is_array($value) || !isset($value['name'])) {
				continue;
			}

			if ($value['name'] == '!') {
				$text .= $value['value'];
			} else {
				$return[$value['name']] = $this->_array($value);
			}
		}

		if (empty($return)) {
			return $text;
		}

		return $return;
	}

	/**
	 * Parse out CDATA tags. (htmlspecialchars them...)
	 *
	 * @param string $data The data with CDATA tags included
	 * @return string The data contained within CDATA tags
	 */
	public function _to_cdata(string $data): string
	{
		$inCdata = $inComment = false;
		$output = '';

		$parts = preg_split('~(<!\[CDATA\[|\]\]>|<!--|-->)~', $data, -1, PREG_SPLIT_DELIM_CAPTURE);

		foreach ($parts as $part) {
			// Handle XML comments.
			if (!$inCdata && $part === '<!--') {
				$inComment = true;
			}

			if ($inComment && $part === '-->') {
				$inComment = false;
			} elseif ($inComment) {
				continue;
			}

			// Handle Cdata blocks.
			elseif (!$inComment && $part === '<![CDATA[') {
				$inCdata = true;
			} elseif ($inCdata && $part === ']]>') {
				$inCdata = false;
			} elseif ($inCdata) {
				$output .= htmlentities($part, ENT_QUOTES);
			}

			// Everything else is kept as is.
			else {
				$output .= $part;
			}
		}

		return $output;
	}

	/**
	 * Turn the CDATAs back to normal text.
	 *
	 * @param string $data The data with CDATA tags
	 * @return string The transformed data
	 */
	protected function _from_cdata(string $data): string
	{
		// Get the HTML translation table and reverse it.
		$trans_tbl = array_flip(get_html_translation_table(HTML_ENTITIES, ENT_QUOTES));

		// Translate all the entities out.
		$data = strtr(
			preg_replace_callback(
				'~&#(\d{1,4});~',
				function ($m) {
					return chr("{$m[1]}");
				},
				$data,
			),
			$trans_tbl,
		);

		return $this->trim ? trim($data) : $data;
	}

	/**
	 * Given an array, return the text from that array. (recursive and privately used.)
	 *
	 * @param null|array|string $array An aray of data
	 * @return string The text from the array
	 */
	protected function _fetch(null|array|string $array): string
	{
		// Don't return anything if this is just a string.
		if (is_string($array)) {
			return '';
		}

		$temp = '';

		foreach ((array) $array as $text) {
			// This means it's most likely an attribute or the name itself.
			if (!isset($text['name'])) {
				continue;
			}

			// This is text!
			if ($text['name'] == '!') {
				$temp .= $text['value'];
			}
			// Another element - dive in ;).
			else {
				$temp .= $this->_fetch($text);
			}
		}

		// Return all the bits and pieces we've put together.
		return $temp;
	}

	/**
	 * Get a specific array by path, one level down. (privately used...)
	 *
	 * @param array $array An array of data
	 * @param string $path The path
	 * @param null|int $level How far deep into the array we should go
	 * @param bool $no_error Whether or not to ignore errors
	 * @return string|array The specified array (or the contents of said array if there's only one result)
	 */
	protected function _path(array $array, string $path, ?int $level, bool $no_error = false): string|array
	{
		// Is $array even an array?  It might be false!
		if (!is_array($array)) {
			return false;
		}

		// Asking for *no* path?
		if ($path == '' || $path == '.') {
			return $array;
		}
		$paths = explode('|', $path);

		// A * means all elements of any name.
		$show_all = in_array('*', $paths);

		$results = [];

		// Check each element.
		foreach ($array as $value) {
			if (!is_array($value) || $value['name'] === '!') {
				continue;
			}

			if ($show_all || in_array($value['name'], $paths)) {
				// Skip elements before "the one".
				if ($level !== null && $level > 0) {
					$level--;
				} else {
					$results[] = $value;
				}
			}
		}

		// No results found...
		if (empty($results)) {
			$trace = debug_backtrace();
			$i = 0;

			while ($i < count($trace) && isset($trace[$i]['class']) && $trace[$i]['class'] == get_class($this)) {
				$i++;
			}
			$debug = ' from ' . $trace[$i - 1]['file'] . ' on line ' . $trace[$i - 1]['line'];

			// Cause an error.
			if ($this->debug_level & E_NOTICE && !$no_error) {
				Lang::load('Errors');
				trigger_error(sprintf(Lang::$txt['undefined_xml_element'], $path . $debug), E_USER_NOTICE);
			}

			return false;
		}

		// Only one result.
		if (count($results) == 1 || $level !== null) {
			return $results[0];
		}

		// Return the result set.
		return $results + ['name' => $path . '[]'];
	}
}

?>