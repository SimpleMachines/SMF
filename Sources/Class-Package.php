<?php

/**
 * The xmlArray class is an xml parser.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Class xmlArray
 * Represents an XML array
 */
class xmlArray
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
	 *  $xml = new xmlArray(file('data.xml'));
	 *
	 * @param string $data The xml data or an array of, unless is_clone is true.
	 * @param bool $auto_trim Used to automatically trim textual data.
	 * @param int $level The debug level. Specifies whether notices should be generated for missing elements and attributes.
	 * @param bool $is_clone default false. If is_clone is true, the  xmlArray is cloned from another - used internally only.
	 */
	public function __construct($data, $auto_trim = false, $level = null, $is_clone = false)
	{
		// If we're using this try to get some more memory.
		setMemoryLimit('32M');

		// Set the debug level.
		$this->debug_level = $level !== null ? $level : error_reporting();
		$this->trim = $auto_trim;

		// Is the data already parsed?
		if ($is_clone)
		{
			$this->array = $data;
			return;
		}

		// Is the input an array? (ie. passed from file()?)
		if (is_array($data))
			$data = implode('', $data);

		// Remove any xml declaration or doctype, and parse out comments and CDATA.
		$data = preg_replace('/<!--.*?-->/s', '', $this->_to_cdata(preg_replace(array('/^<\?xml.+?\?' . '>/is', '/<!DOCTYPE[^>]+?' . '>/s'), '', $data)));

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
	public function name()
	{
		return isset($this->array['name']) ? $this->array['name'] : '';
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
	public function fetch($path, $get_elements = false)
	{
		// Get the element, in array form.
		$array = $this->path($path);

		if ($array === false)
			return false;

		// Getting elements into this is a bit complicated...
		if ($get_elements && !is_string($array))
		{
			$temp = '';

			// Use the _xml() function to get the xml data.
			foreach ($array->array as $val)
			{
				// Skip the name and any attributes.
				if (is_array($val))
					$temp .= $this->_xml($val, null);
			}

			// Just get the XML data and then take out the CDATAs.
			return $this->_to_cdata($temp);
		}

		// Return the value - taking care to pick out all the text values.
		return is_string($array) ? $array : $this->_fetch($array->array);
	}

	/** Get an element, returns a new xmlArray.
	 * It finds any elements that match the path specified.
	 * It will always return a set if there is more than one of the element
	 * or return_set is true.
	 * Example use:
	 *  $element = $xml->path('html/body');
	 *
	 * @param $path string The path to the element to get
	 * @param $return_full bool Whether to return the full result set
	 * @return xmlArray, a new xmlArray.
	 */
	public function path($path, $return_full = false)
	{
		// Split up the path.
		$path = explode('/', $path);

		// Start with a base array.
		$array = $this->array;

		// For each element in the path.
		foreach ($path as $el)
		{
			// Deal with sets....
			if (strpos($el, '[') !== false)
			{
				$lvl = (int) substr($el, strpos($el, '[') + 1);
				$el = substr($el, 0, strpos($el, '['));
			}
			// Find an attribute.
			elseif (substr($el, 0, 1) == '@')
			{
				// It simplifies things if the attribute is already there ;).
				if (isset($array[$el]))
					return $array[$el];
				else
				{
					$trace = debug_backtrace();
					$i = 0;
					while ($i < count($trace) && isset($trace[$i]['class']) && $trace[$i]['class'] == get_class($this))
						$i++;
					$debug = ' (from ' . $trace[$i - 1]['file'] . ' on line ' . $trace[$i - 1]['line'] . ')';

					// Cause an error.
					if ($this->debug_level & E_NOTICE)
						trigger_error('Undefined XML attribute: ' . substr($el, 1) . $debug, E_USER_NOTICE);
					return false;
				}
			}
			else
				$lvl = null;

			// Find this element.
			$array = $this->_path($array, $el, $lvl);
		}

		// Clean up after $lvl, for $return_full.
		if ($return_full && (!isset($array['name']) || substr($array['name'], -1) != ']'))
			$array = array('name' => $el . '[]', $array);

		// Create the right type of class...
		$newClass = get_class($this);

		// Return a new xmlArray for the result.
		return $array === false ? false : new $newClass($array, $this->trim, $this->debug_level, true);
	}

	/**
	 * Check if an element exists.
	 * Example use,
	 *  echo $xml->exists('html/body') ? 'y' : 'n';
	 *
	 * @param string $path The path to the element to get.
	 * @return boolean Whether the specified path exists
	 */
	public function exists($path)
	{
		// Split up the path.
		$path = explode('/', $path);

		// Start with a base array.
		$array = $this->array;

		// For each element in the path.
		foreach ($path as $el)
		{
			// Deal with sets....
			if (strpos($el, '[') !== false)
			{
				$lvl = (int) substr($el, strpos($el, '[') + 1);
				$el = substr($el, 0, strpos($el, '['));
			}
			// Find an attribute.
			elseif (substr($el, 0, 1) == '@')
				return isset($array[$el]);
			else
				$lvl = null;

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
	public function count($path)
	{
		// Get the element, always returning a full set.
		$temp = $this->path($path, true);

		// Start at zero, then count up all the numeric keys.
		$i = 0;
		foreach ($temp->array as $item)
		{
			if (is_array($item))
				$i++;
		}

		return $i;
	}

	/**
	 * Get an array of xmlArray's matching the specified path.
	 * This differs from ->path(path, true) in that instead of an xmlArray
	 * of elements, an array of xmlArray's is returned for use with foreach.
	 * Example use:
	 *  foreach ($xml->set('html/body/p') as $p)
	 *
	 * @param $path string The path to search for.
	 * @return xmlArray[] An array of xmlArray objects
	 */
	public function set($path)
	{
		// None as yet, just get the path.
		$array = array();
		$xml = $this->path($path, true);

		foreach ($xml->array as $val)
		{
			// Skip these, they aren't elements.
			if (!is_array($val) || $val['name'] == '!')
				continue;

			// Create the right type of class...
			$newClass = get_class($this);

			// Create a new xmlArray and stick it in the array.
			$array[] = new $newClass($val, $this->trim, $this->debug_level, true);
		}

		return $array;
	}

	/**
	 * Create an xml file from an xmlArray, the specified path if any.
	 * Example use:
	 *  echo $this->create_xml();
	 *
	 * @param string $path The path to the element. (optional)
	 * @return string Xml-formatted string.
	 */
	public function create_xml($path = null)
	{
		// Was a path specified?  If so, use that array.
		if ($path !== null)
		{
			$path = $this->path($path);

			// The path was not found
			if ($path === false)
				return false;

			$path = $path->array;
		}
		// Just use the current array.
		else
			$path = $this->array;

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
	public function to_array($path = null)
	{
		// Are we doing a specific path?
		if ($path !== null)
		{
			$path = $this->path($path);

			// The path was not found
			if ($path === false)
				return false;

			$path = $path->array;
		}
		// No, so just use the current array.
		else
			$path = $this->array;

		return $this->_array($path);
	}

	/**
	 * Parse data into an array. (privately used...)
	 *
	 * @param string $data The data to parse
	 * @return array The parsed array
	 */
	protected function _parse($data)
	{
		// Start with an 'empty' array with no data.
		$current = array(
		);

		// Loop until we're out of data.
		while ($data != '')
		{
			// Find and remove the next tag.
			preg_match('/\A<([\w\-:]+)((?:\s+.+?)?)([\s]?\/)?' . '>/', $data, $match);
			if (isset($match[0]))
				$data = preg_replace('/' . preg_quote($match[0], '/') . '/s', '', $data, 1);

			// Didn't find a tag?  Keep looping....
			if (!isset($match[1]) || $match[1] == '')
			{
				// If there's no <, the rest is data.
				if (strpos($data, '<') === false)
				{
					$text_value = $this->_from_cdata($data);
					$data = '';

					if ($text_value != '')
						$current[] = array(
							'name' => '!',
							'value' => $text_value
						);
				}
				// If the < isn't immediately next to the current position... more data.
				elseif (strpos($data, '<') > 0)
				{
					$text_value = $this->_from_cdata(substr($data, 0, strpos($data, '<')));
					$data = substr($data, strpos($data, '<'));

					if ($text_value != '')
						$current[] = array(
							'name' => '!',
							'value' => $text_value
						);
				}
				// If we're looking at a </something> with no start, kill it.
				elseif (strpos($data, '<') !== false && strpos($data, '<') == 0)
				{
					if (strpos($data, '<', 1) !== false)
					{
						$text_value = $this->_from_cdata(substr($data, 0, strpos($data, '<', 1)));
						$data = substr($data, strpos($data, '<', 1));

						if ($text_value != '')
							$current[] = array(
								'name' => '!',
								'value' => $text_value
							);
					}
					else
					{
						$text_value = $this->_from_cdata($data);
						$data = '';

						if ($text_value != '')
							$current[] = array(
								'name' => '!',
								'value' => $text_value
							);
					}
				}

				// Wait for an actual occurance of an element.
				continue;
			}

			// Create a new element in the array.
			$el = &$current[];
			$el['name'] = $match[1];

			// If this ISN'T empty, remove the close tag and parse the inner data.
			if ((!isset($match[3]) || trim($match[3]) != '/') && (!isset($match[2]) || trim($match[2]) != '/'))
			{
				// Because PHP 5.2.0+ seems to croak using regex, we'll have to do this the less fun way.
				$last_tag_end = strpos($data, '</' . $match[1] . '>');
				if ($last_tag_end === false)
					continue;

				$offset = 0;
				while (1 == 1)
				{
					// Where is the next start tag?
					$next_tag_start = strpos($data, '<' . $match[1], $offset);
					// If the next start tag is after the last end tag then we've found the right close.
					if ($next_tag_start === false || $next_tag_start > $last_tag_end)
						break;

					// If not then find the next ending tag.
					$next_tag_end = strpos($data, '</' . $match[1] . '>', $offset);

					// Didn't find one? Then just use the last and sod it.
					if ($next_tag_end === false)
						break;
					else
					{
						$last_tag_end = $next_tag_end;
						$offset = $next_tag_start + 1;
					}
				}
				// Parse the insides.
				$inner_match = substr($data, 0, $last_tag_end);
				// Data now starts from where this section ends.
				$data = substr($data, $last_tag_end + strlen('</' . $match[1] . '>'));

				if (!empty($inner_match))
				{
					// Parse the inner data.
					if (strpos($inner_match, '<') !== false)
						$el += $this->_parse($inner_match);
					elseif (trim($inner_match) != '')
					{
						$text_value = $this->_from_cdata($inner_match);
						if ($text_value != '')
							$el[] = array(
								'name' => '!',
								'value' => $text_value
							);
					}
				}
			}

			// If we're dealing with attributes as well, parse them out.
			if (isset($match[2]) && $match[2] != '')
			{
				// Find all the attribute pairs in the string.
				preg_match_all('/([\w:]+)="(.+?)"/', $match[2], $attr, PREG_SET_ORDER);

				// Set them as @attribute-name.
				foreach ($attr as $match_attr)
					$el['@' . $match_attr[1]] = $match_attr[2];
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
	protected function _xml($array, $indent)
	{
		$indentation = $indent !== null ? '
' . str_repeat('	', $indent) : '';

		// This is a set of elements, with no name...
		if (is_array($array) && !isset($array['name']))
		{
			$temp = '';
			foreach ($array as $val)
				$temp .= $this->_xml($val, $indent);
			return $temp;
		}

		// This is just text!
		if ($array['name'] == '!')
			return $indentation . '<![CDATA[' . $array['value'] . ']]>';
		elseif (substr($array['name'], -2) == '[]')
			$array['name'] = substr($array['name'], 0, -2);

		// Start the element.
		$output = $indentation . '<' . $array['name'];

		$inside_elements = false;
		$output_el = '';

		// Run through and recursively output all the elements or attrbutes inside this.
		foreach ($array as $k => $v)
		{
			if (substr($k, 0, 1) == '@')
				$output .= ' ' . substr($k, 1) . '="' . $v . '"';
			elseif (is_array($v))
			{
				$output_el .= $this->_xml($v, $indent === null ? null : $indent + 1);
				$inside_elements = true;
			}
		}

		// Indent, if necessary.... then close the tag.
		if ($inside_elements)
			$output .= '>' . $output_el . $indentation . '</' . $array['name'] . '>';
		else
			$output .= ' />';

		return $output;
	}

	/**
	 * Return an element as an array
	 *
	 * @param array $array An array of data
	 * @return string|array A string with the element's value or an array of element data
	 */
	protected function _array($array)
	{
		$return = array();
		$text = '';
		foreach ($array as $value)
		{
			if (!is_array($value) || !isset($value['name']))
				continue;

			if ($value['name'] == '!')
				$text .= $value['value'];
			else
				$return[$value['name']] = $this->_array($value);
		}

		if (empty($return))
			return $text;
		else
			return $return;
	}

	/**
	 * Parse out CDATA tags. (htmlspecialchars them...)
	 *
	 * @param string $data The data with CDATA tags included
	 * @return string The data contained within CDATA tags
	 */
	function _to_cdata($data)
	{
		$inCdata = $inComment = false;
		$output = '';

		$parts = preg_split('~(<!\[CDATA\[|\]\]>|<!--|-->)~', $data, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($parts as $part)
		{
			// Handle XML comments.
			if (!$inCdata && $part === '<!--')
				$inComment = true;
			if ($inComment && $part === '-->')
				$inComment = false;
			elseif ($inComment)
				continue;

			// Handle Cdata blocks.
			elseif (!$inComment && $part === '<![CDATA[')
				$inCdata = true;
			elseif ($inCdata && $part === ']]>')
				$inCdata = false;
			elseif ($inCdata)
				$output .= htmlentities($part, ENT_QUOTES);

			// Everything else is kept as is.
			else
				$output .= $part;
		}

		return $output;
	}

	/**
	 * Turn the CDATAs back to normal text.
	 *
	 * @param string $data The data with CDATA tags
	 * @return string The transformed data
	 */
	protected function _from_cdata($data)
	{
		// Get the HTML translation table and reverse it.
		$trans_tbl = array_flip(get_html_translation_table(HTML_ENTITIES, ENT_QUOTES));

		// Translate all the entities out.
		$data = strtr(preg_replace_callback('~&#(\d{1,4});~', function($m)
		{
			return chr("$m[1]");
		}, $data), $trans_tbl);

		return $this->trim ? trim($data) : $data;
	}

	/**
	 * Given an array, return the text from that array. (recursive and privately used.)
	 *
	 * @param array $array An aray of data
	 * @return string The text from the array
	 */
	protected function _fetch($array)
	{
		// Don't return anything if this is just a string.
		if (is_string($array))
			return '';

		$temp = '';
		foreach ($array as $text)
		{
			// This means it's most likely an attribute or the name itself.
			if (!isset($text['name']))
				continue;

			// This is text!
			if ($text['name'] == '!')
				$temp .= $text['value'];
			// Another element - dive in ;).
			else
				$temp .= $this->_fetch($text);
		}

		// Return all the bits and pieces we've put together.
		return $temp;
	}

	/**
	 * Get a specific array by path, one level down. (privately used...)
	 *
	 * @param array $array An array of data
	 * @param string $path The path
	 * @param int $level How far deep into the array we should go
	 * @param bool $no_error Whether or not to ignore errors
	 * @return string|array The specified array (or the contents of said array if there's only one result)
	 */
	protected function _path($array, $path, $level, $no_error = false)
	{
		// Is $array even an array?  It might be false!
		if (!is_array($array))
			return false;

		// Asking for *no* path?
		if ($path == '' || $path == '.')
			return $array;
		$paths = explode('|', $path);

		// A * means all elements of any name.
		$show_all = in_array('*', $paths);

		$results = array();

		// Check each element.
		foreach ($array as $value)
		{
			if (!is_array($value) || $value['name'] === '!')
				continue;

			if ($show_all || in_array($value['name'], $paths))
			{
				// Skip elements before "the one".
				if ($level !== null && $level > 0)
					$level--;
				else
					$results[] = $value;
			}
		}

		// No results found...
		if (empty($results))
		{
			$trace = debug_backtrace();
			$i = 0;
			while ($i < count($trace) && isset($trace[$i]['class']) && $trace[$i]['class'] == get_class($this))
				$i++;
			$debug = ' from ' . $trace[$i - 1]['file'] . ' on line ' . $trace[$i - 1]['line'];

			// Cause an error.
			if ($this->debug_level & E_NOTICE && !$no_error)
				trigger_error('Undefined XML element: ' . $path . $debug, E_USER_NOTICE);
			return false;
		}
		// Only one result.
		elseif (count($results) == 1 || $level !== null)
			return $results[0];
		// Return the result set.
		else
			return $results + array('name' => $path . '[]');
	}
}

/**
 * Class ftp_connection
 * Simple FTP protocol implementation.
 *
 * @see https://tools.ietf.org/html/rfc959
 */
class ftp_connection
{
	/**
	 * @var string Holds the connection response
	 */
	public $connection;

	/**
	 * @var string Holds any errors
	 */
	public $error;

	/**
	 * @var string Holds the last message from the server
	 */
	public $last_message;

	/**
	 * @var boolean Whether or not this is a passive connection
	 */
	public $pasv;

	/**
	 * Create a new FTP connection...
	 *
	 * @param string $ftp_server The server to connect to
	 * @param int $ftp_port The port to connect to
	 * @param string $ftp_user The username
	 * @param string $ftp_pass The password
	 */
	public function __construct($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@simplemachines.org')
	{
		// Initialize variables.
		$this->connection = 'no_connection';
		$this->error = false;
		$this->pasv = array();

		if ($ftp_server !== null)
			$this->connect($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
	}

	/**
	 * Connects to a server
	 *
	 * @param string $ftp_server The address of the server
	 * @param int $ftp_port The port
	 * @param string $ftp_user The username
	 * @param string $ftp_pass The password
	 */
	public function connect($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@simplemachines.org')
	{
		if (strpos($ftp_server, 'ftp://') === 0)
			$ftp_server = substr($ftp_server, 6);
		elseif (strpos($ftp_server, 'ftps://') === 0)
			$ftp_server = 'ssl://' . substr($ftp_server, 7);
		if (strpos($ftp_server, 'http://') === 0)
			$ftp_server = substr($ftp_server, 7);
		elseif (strpos($ftp_server, 'https://') === 0)
			$ftp_server = substr($ftp_server, 8);
		$ftp_server = strtr($ftp_server, array('/' => '', ':' => '', '@' => ''));

		// Connect to the FTP server.
		$this->connection = @fsockopen($ftp_server, $ftp_port, $err, $err, 5);
		if (!$this->connection)
		{
			$this->error = 'bad_server';
			$this->last_message = 'Invalid Server';
			return;
		}

		// Get the welcome message...
		if (!$this->check_response(220))
		{
			$this->error = 'bad_response';
			$this->last_message = 'Bad Response';
			return;
		}

		// Send the username, it should ask for a password.
		fwrite($this->connection, 'USER ' . $ftp_user . "\r\n");

		if (!$this->check_response(331))
		{
			$this->error = 'bad_username';
			$this->last_message = 'Invalid Username';
			return;
		}

		// Now send the password... and hope it goes okay.

		fwrite($this->connection, 'PASS ' . $ftp_pass . "\r\n");
		if (!$this->check_response(230))
		{
			$this->error = 'bad_password';
			$this->last_message = 'Invalid Password';
			return;
		}
	}

	/**
	 * Changes to a directory (chdir) via the ftp connection
	 *
	 * @param string $ftp_path The path to the directory we want to change to
	 * @return boolean Whether or not the operation was successful
	 */
	public function chdir($ftp_path)
	{
		if (!is_resource($this->connection))
			return false;

		// No slash on the end, please...
		if ($ftp_path !== '/' && substr($ftp_path, -1) === '/')
			$ftp_path = substr($ftp_path, 0, -1);

		fwrite($this->connection, 'CWD ' . $ftp_path . "\r\n");
		if (!$this->check_response(250))
		{
			$this->error = 'bad_path';
			return false;
		}

		return true;
	}

	/**
	 * Changes a files atrributes (chmod)
	 *
	 * @param string $ftp_file The file to CHMOD
	 * @param int|string $chmod The value for the CHMOD operation
	 * @return boolean Whether or not the operation was successful
	 */
	public function chmod($ftp_file, $chmod)
	{
		if (!is_resource($this->connection))
			return false;

		if ($ftp_file == '')
			$ftp_file = '.';

		// Do we have a file or a dir?
		$is_dir = is_dir($ftp_file);
		$is_writable = false;

		// Set different modes.
		$chmod_values = $is_dir ? array(0750, 0755, 0775, 0777) : array(0644, 0664, 0666);

		foreach ($chmod_values as $val)
		{
			// If it's writable, break out of the loop.
			if (is_writable($ftp_file))
			{
				$is_writable = true;
				break;
			}

			else
			{
				// Convert the chmod value from octal (0777) to text ("777").
				fwrite($this->connection, 'SITE CHMOD ' . decoct($val) . ' ' . $ftp_file . "\r\n");
				if (!$this->check_response(200))
				{
					$this->error = 'bad_file';
					break;
				}
			}
		}
		return $is_writable;
	}

	/**
	 * Deletes a file
	 *
	 * @param string $ftp_file The file to delete
	 * @return boolean Whether or not the operation was successful
	 */
	public function unlink($ftp_file)
	{
		// We are actually connected, right?
		if (!is_resource($this->connection))
			return false;

		// Delete file X.
		fwrite($this->connection, 'DELE ' . $ftp_file . "\r\n");
		if (!$this->check_response(250))
		{
			fwrite($this->connection, 'RMD ' . $ftp_file . "\r\n");

			// Still no love?
			if (!$this->check_response(250))
			{
				$this->error = 'bad_file';
				return false;
			}
		}

		return true;
	}

	/**
	 * Reads the response to the command from the server
	 *
	 * @param string $desired The desired response
	 * @return boolean Whether or not we got the desired response
	 */
	public function check_response($desired)
	{
		// Wait for a response that isn't continued with -, but don't wait too long.
		$time = time();
		do
			$this->last_message = fgets($this->connection, 1024);
		while ((strlen($this->last_message) < 4 || strpos($this->last_message, ' ') === 0 || strpos($this->last_message, ' ', 3) !== 3) && time() - $time < 5);

		// Was the desired response returned?
		return is_array($desired) ? in_array(substr($this->last_message, 0, 3), $desired) : substr($this->last_message, 0, 3) == $desired;
	}

	/**
	 * Used to create a passive connection
	 *
	 * @return boolean Whether the passive connection was created successfully
	 */
	public function passive()
	{
		// We can't create a passive data connection without a primary one first being there.
		if (!is_resource($this->connection))
			return false;

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		@fwrite($this->connection, 'PASV' . "\r\n");
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while (strpos($response, ' ', 3) !== 3 && time() - $time < 5);

		// If it's not 227, we weren't given an IP and port, which means it failed.
		if (strpos($response, '227 ') !== 0)
		{
			$this->error = 'bad_response';
			return false;
		}

		// Snatch the IP and port information, or die horribly trying...
		if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $response, $match) == 0)
		{
			$this->error = 'bad_response';
			return false;
		}

		// This is pretty simple - store it for later use ;).
		$this->pasv = array('ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4], 'port' => $match[5] * 256 + $match[6]);

		return true;
	}

	/**
	 * Creates a new file on the server
	 *
	 * @param string $ftp_file The file to create
	 * @return boolean Whether or not the file was created successfully
	 */
	public function create_file($ftp_file)
	{
		// First, we have to be connected... very important.
		if (!is_resource($this->connection))
			return false;

		// I'd like one passive mode, please!
		if (!$this->passive())
			return false;

		// Seems logical enough, so far...
		fwrite($this->connection, 'STOR ' . $ftp_file . "\r\n");

		// Okay, now we connect to the data port.  If it doesn't work out, it's probably "file already exists", etc.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(150))
		{
			$this->error = 'bad_file';
			@fclose($fp);
			return false;
		}

		// This may look strange, but we're just closing it to indicate a zero-byte upload.
		fclose($fp);
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return true;
	}

	/**
	 * Generates a directory listing for the current directory
	 *
	 * @param string $ftp_path The path to the directory
	 * @param bool $search Whether or not to get a recursive directory listing
	 * @return string|boolean The results of the command or false if unsuccessful
	 */
	public function list_dir($ftp_path = '', $search = false)
	{
		// Are we even connected...?
		if (!is_resource($this->connection))
			return false;

		// Passive... non-agressive...
		if (!$this->passive())
			return false;

		// Get the listing!
		fwrite($this->connection, 'LIST -1' . ($search ? 'R' : '') . ($ftp_path == '' ? '' : ' ' . $ftp_path) . "\r\n");

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(array(150, 125)))
		{
			$this->error = 'bad_response';
			@fclose($fp);
			return false;
		}

		// Read in the file listing.
		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// Everything go okay?
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return $data;
	}

	/**
	 * Determines the current directory we are in
	 *
	 * @param string $file The name of a file
	 * @param string $listing A directory listing or null to generate one
	 * @return string|boolean The name of the file or false if it wasn't found
	 */
	public function locate($file, $listing = null)
	{
		if ($listing === null)
			$listing = $this->list_dir('', true);
		$listing = explode("\n", $listing);

		@fwrite($this->connection, 'PWD' . "\r\n");
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while ($response[3] != ' ' && time() - $time < 5);

		// Check for 257!
		if (preg_match('~^257 "(.+?)" ~', $response, $match) != 0)
			$current_dir = strtr($match[1], array('""' => '"'));
		else
			$current_dir = '';

		for ($i = 0, $n = count($listing); $i < $n; $i++)
		{
			if (trim($listing[$i]) == '' && isset($listing[$i + 1]))
			{
				$current_dir = substr(trim($listing[++$i]), 0, -1);
				$i++;
			}

			// Okay, this file's name is:
			$listing[$i] = $current_dir . '/' . trim(strlen($listing[$i]) > 30 ? strrchr($listing[$i], ' ') : $listing[$i]);

			if ($file[0] == '*' && substr($listing[$i], -(strlen($file) - 1)) == substr($file, 1))
				return $listing[$i];
			if (substr($file, -1) == '*' && substr($listing[$i], 0, strlen($file) - 1) == substr($file, 0, -1))
				return $listing[$i];
			if (basename($listing[$i]) == $file || $listing[$i] == $file)
				return $listing[$i];
		}

		return false;
	}

	/**
	 * Creates a new directory on the server
	 *
	 * @param string $ftp_dir The name of the directory to create
	 * @return boolean Whether or not the operation was successful
	 */
	public function create_dir($ftp_dir)
	{
		// We must be connected to the server to do something.
		if (!is_resource($this->connection))
			return false;

		// Make this new beautiful directory!
		fwrite($this->connection, 'MKD ' . $ftp_dir . "\r\n");
		if (!$this->check_response(257))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	/**
	 * Detects the current path
	 *
	 * @param string $filesystem_path The full path from the filesystem
	 * @param string $lookup_file The name of a file in the specified path
	 * @return array An array of detected info - username, path from FTP root and whether or not the current path was found
	 */
	public function detect_path($filesystem_path, $lookup_file = null)
	{
		$username = '';

		if (isset($_SERVER['DOCUMENT_ROOT']))
		{
			if (preg_match('~^/home[2]?/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match))
			{
				$username = $match[1];

				$path = strtr($_SERVER['DOCUMENT_ROOT'], array('/home/' . $match[1] . '/' => '', '/home2/' . $match[1] . '/' => ''));

				if (substr($path, -1) == '/')
					$path = substr($path, 0, -1);

				if (strlen(dirname($_SERVER['PHP_SELF'])) > 1)
					$path .= dirname($_SERVER['PHP_SELF']);
			}
			elseif (strpos($filesystem_path, '/var/www/') === 0)
				$path = substr($filesystem_path, 8);
			else
				$path = strtr(strtr($filesystem_path, array('\\' => '/')), array($_SERVER['DOCUMENT_ROOT'] => ''));
		}
		else
			$path = '';

		if (is_resource($this->connection) && $this->list_dir($path) == '')
		{
			$data = $this->list_dir('', true);

			if ($lookup_file === null)
				$lookup_file = $_SERVER['PHP_SELF'];

			$found_path = dirname($this->locate('*' . basename(dirname($lookup_file)) . '/' . basename($lookup_file), $data));
			if ($found_path == false)
				$found_path = dirname($this->locate(basename($lookup_file)));
			if ($found_path != false)
				$path = $found_path;
		}
		elseif (is_resource($this->connection))
			$found_path = true;

		return array($username, $path, isset($found_path));
	}

	/**
	 * Close the ftp connection
	 *
	 * @return boolean Always returns true
	 */
	public function close()
	{
		// Goodbye!
		fwrite($this->connection, 'QUIT' . "\r\n");
		fclose($this->connection);

		return true;
	}
}

?>