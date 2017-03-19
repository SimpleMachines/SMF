<?php
/**
 * Used to validate and transform user supplied data from forms etc
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
 *
 */
if (!defined('SMF'))
	die('No direct access...');
/**
 * Class used to validate and transform data
 * based on https://github.com/elkarte/Elkarte/blob/development/sources/subs/DataValidator.class.php
 *
 * Initiate
 *    $validation = new Data_Validator();
 *
 * Set validation rules
 *    $validation->validation_rules(array(
 *      'username' => 'required|alpha_numeric|max_length[10]|min_length[6]',
 *      'email'    => 'required|valid_email'
 *    ));
 *
 * Set optional sanitation rules
 *    $validation->sanitation_rules(array(
 *      'username' => 'trim|strtoupper',
 *      'email'    => 'trim|gmail_normalize'
 *    ));
 *
 * Set optional variable name substitutions
 *    $validation->text_replacements(array(
 *      'username' => $txt['someThing'],
 *      'email'    => $txt['someEmail']
 *    ));
 *
 * Set optional special processing tags
 *    $validation->input_processing(array(
 *      'somefield'    => 'csv',
 *      'anotherfield' => 'array'
 *    ));
 *
 * Run the validation
 *    $validation->validate($data);
 * $data must be an array with keys matching the validation rule e.g. $data['username'], $data['email']
 *
 * Get the results
 *    $validation->validation_errors(optional array of fields to return errors on)
 *    $validation->validation_data()
 *    $validation->username
 *
 * Use it inline with the static method
 * $_POST['username'] = ' username '
 * if (Data_Validator::is_valid($_POST, array('username' => 'required|alpha_numeric'), array('username' => 'trim|strtoupper')))
 *    $username = $_POST['username'] // now = 'USERNAME'
 *
 * Current validation can be one or a combination of:
 *    max_length[x], min_length[x], length[x],
 *    alpha, alpha_numeric, alpha_dash
 *    numeric, integer, boolean, float, notequal[x,y,z], isarray, limits[min,max]
 *    valid_url, valid_ip, valid_ipv6, valid_email, valid_color
 *    php_syntax, contains[x,y,x], required, without[x,y,z]
 */
class Data_Validator
{
	/**
	 * Validation rules
	 * @var mixed[]
	 */
	protected $_validation_rules = array();

	/**
	 * Sanitation rules
	 * @var mixed[]
	 */
	protected $_sanitation_rules = array();

	/**
	 * Text substitutions for field names in the error messages
	 * @var mixed[]
	 */
	protected $_replacements = array();

	/**
	 * Holds validation errors
	 * @var mixed[]
	 */
	protected $_validation_errors = array();

	/**
	 * Holds our data
	 * @var mixed[]
	 */
	protected $_data = array();

	/**
	 * Strict data processing,
	 * if true drops data for which no sanitation rule was set
	 * @var boolean
	 */
	protected $_strict = false;

	/**
	 * Holds any special processing that is required for certain fields
	 * csv or array
	 * @var string[]
	 */
	protected $_datatype = array();

	/**
	 * Allow reading otherwise inaccessible data values
	 *
	 * @param string $property key name of array value to return
	 */
	public function __get($property)
	{
		return array_key_exists($property, $this->_data) ? $this->_data[$property] : null;
	}

	/**
	 * Allow testing data values for empty/isset
	 *
	 * @param string $property key name of array value to return
	 */
	public function __isset($property)
	{
		return isset($this->_data[$property]);
	}

	/**
	 * Shorthand static method for simple inline validation
	 *
	 * @param mixed[]|object $data generally $_POST data for this method
	 * @param mixed[] $validation_rules associative array of field => rules
	 * @param mixed[] $sanitation_rules associative array of field => rules
	 */
	public static function is_valid(&$data = array(), $validation_rules = array(), $sanitation_rules = array())
	{
		$validator = new Data_Validator();

		// Set the rules
		$validator->sanitation_rules($sanitation_rules);
		$validator->validation_rules($validation_rules);

		// Run the test
		$result = $validator->validate($data);

		// Replace the data
		if (!empty($sanitation_rules))
		{
			// Handle cases where we have an object
			if (is_object($data))
			{
				$data = array_replace((array) $data, $validator->validation_data());
				$data = (object) $data;
			}
			else
			{
				$data = array_replace($data, $validator->validation_data());
			}
		}

		// Return true or false on valid data
		return $result;
	}

	/**
	 * Set the validation rules that will be run against the data
	 *
	 * @param mixed[] $rules associative array of field => rule|rule|rule
	 */
	public function validation_rules($rules = array())
	{
		// If its not an array, make it one
		if (!is_array($rules))
			$rules = array($rules);

		// Set the validation rules
		if (!empty($rules))
			$this->_validation_rules = $rules;
		else
			return $this->_validation_rules;
	}

	/**
	 * Sets the sanitation rules used to clean data
	 *
	 * @param mixed[] $rules associative array of field => rule|rule|rule
	 * @param boolean $strict
	 */
	public function sanitation_rules($rules = array(), $strict = false)
	{
		// If its not an array, make it one
		if (!is_array($rules))
			$rules = array($rules);

		// Set the sanitation rules
		$this->_strict = $strict;

		if (!empty($rules))
			$this->_sanitation_rules = $rules;
		else
			return $this->_sanitation_rules;
	}

	/**
	 * Field Name Replacements
	 * @param mixed[] $replacements associative array of field => txt string key
	 */
	public function text_replacements($replacements = array())
	{
		if (!empty($replacements))
			$this->_replacements = $replacements;
		else
			return $this->_replacements;
	}

	/**
	 * Set special processing conditions for fields, such as (and only)
	 * csv or array
	 *
	 * @param string[] $datatype csv or array processing for the field
	 */
	public function input_processing($datatype = array())
	{
		if (!empty($datatype))
			$this->_datatype = $datatype;
		else
			return $this->_datatype;
	}

	/**
	 * Run the sanitation and validation on the data
	 *
	 * @param mixed[]|object $input associative array or object of data to process name => value
	 */
	public function validate($input)
	{
		// If its an object, convert it to an array
		if (is_object($input))
			$input = (array) $input;

		// @todo this won't work, $input[$field] will be undefined
		if (!is_array($input))
			$input[$input] = array($input);

		// Clean em
		$this->_data = $this->_sanitize($input, $this->_sanitation_rules);

		// Check em
		return $this->_validate($this->_data, $this->_validation_rules);
	}

	/**
	 * Return any errors found, either in the raw or nicely formatted
	 *
	 * @param mixed[]|string|boolean $raw
	 *    - true returns the raw error array,
	 *    - array returns just error messages of those fields
	 *    - string returns just that error message
	 *    - default is all error message(s)
	 */
	public function validation_errors($raw = false)
	{
		// Return the array
		if ($raw === true)
			return $this->_validation_errors;
		// Otherwise return the formatted text string(s)
		else
			return $this->_get_error_messages($raw);
	}

	/**
	 * Return the validation data, all or a specific key
	 * @param integer|string|null $key int or string
	 */
	public function validation_data($key = null)
	{
		if ($key === null)
			return $this->_data;

		return isset($this->_data[$key]) ? $this->_data[$key] : null;
	}

	/**
	 * Performs data validation against the provided rule
	 *
	 * @param mixed[] $input
	 * @param mixed[] $ruleset
	 */
	private function _validate($input, $ruleset)
	{
		// No errors ... yet ;)
		$this->_validation_errors = array();

		// For each field, run our rules against the data
		foreach ($ruleset as $field => $rules)
		{
			// Special processing required on this field like csv or array?
			if (isset($this->_datatype[$field]) && in_array($this->_datatype[$field], array('csv', 'array')))
				$this->_validate_recursive($input, $field, $rules);
			else
			{
				// Get rules for this field
				$rules = explode('|', $rules);
				foreach ($rules as $rule)
				{
					$validation_parameters = null;
					$validation_parameters_function = array();

					// Were any parameters provided for the rule, e.g. min_length[6]
					if (preg_match('~(.*)\[(.*)\]~', $rule, $match))
					{
						$validation_method = '_validate_' . $match[1];
						$validation_parameters = $match[2];
						$validation_function = $match[1];
						$validation_parameters_function = explode(',', $match[2]);
					}
					// Or just a predefined rule e.g. valid_email
					else
					{
						$validation_method = '_validate_' . $rule;
						$validation_function = $rule;
					}

					// Defined method to use?
					if (is_callable(array($this, $validation_method)))
						$result = $this->{$validation_method}($field, $input, $validation_parameters);
					// Maybe even a custom function set up like a defined one, addons can do this.
					elseif (is_callable($validation_function) && strpos($validation_function, 'validate_') === 0 && isset($input[$field]))
						$result = call_user_func_array($validation_function, array_merge((array) $field, (array) $input[$field], $validation_parameters_function));
					else
						$result = array(
							'field' => $validation_method,
							'input' => isset($input[$field]) ? $input[$field] : null,
							'function' => '_validate_invalid_function',
							'param' => $validation_parameters
						);

					if (is_array($result))
						$this->_validation_errors[] = $result;
				}
			}
		}

		return count($this->_validation_errors) === 0 ? true : false;
	}

	/**
	 * Used when a field contains csv or array of data
	 *
	 * -Will convert field to individual elements and run a separate validation on that group
	 * using the rules defined to the parent node
	 *
	 * @param mixed[] $input
	 * @param string $field
	 * @param string $rules
	 */
	private function _validate_recursive($input, $field, $rules)
	{
		if (!isset($input[$field]))
			return;

		// Start a new instance of the validator to work on this sub data (csv/array)
		$sub_validator = new Data_Validator();

		$fields = array();
		$validation_rules = array();

		if ($this->_datatype[$field] === 'array')
		{
			// Convert the array to individual values, they all use the same rules
			foreach ($input[$field] as $key => $value)
			{
				$validation_rules[$key] = $rules;
				$fields[$key] = $value;
			}
		}
		// CSV is much the same process as array
		elseif ($this->_datatype[$field] === 'csv')
		{
			// Blow it up!
			$temp = explode(',', $input[$field]);
			foreach ($temp as $key => $value)
			{
				$validation_rules[$key] = $rules;
				$fields[$key] = $value;
			}
		}

		// Validate each "new" field
		$sub_validator->validation_rules($validation_rules);
		$result = $sub_validator->validate($fields);

		// If its not valid, then just take the first error and use it for the original field
		if (!$result)
		{
			$errors = $sub_validator->validation_errors(true);
			foreach ($errors as $error)
			{
				$this->_validation_errors[] = array(
					'field' => $field,
					'input' => $error['input'],
					'function' => $error['function'],
					'param' => $error['param'],
				);
			}
		}

		return $result;
	}

	/**
	 * Data sanitation is a good thing
	 *
	 * @param mixed[] $input
	 * @param mixed[] $ruleset
	 * @return mixed
	 */
	private function _sanitize($input, $ruleset)
	{
		// For each field, run our set of rules against the data
		foreach ($ruleset as $field => $rules)
		{
			// Data for which we don't have rules
			if (!array_key_exists($field, $input))
			{
				if ($this->_strict)
					unset($input[$field]);

				continue;
			}

			// Is this a special processing field like csv or array?
			if (isset($this->_datatype[$field]) && in_array($this->_datatype[$field], array('csv', 'array')))
				$input[$field] = $this->_sanitize_recursive($input, $field, $rules);
			else
			{
				// Rules for which we do have data
				$rules = explode('|', $rules);
				foreach ($rules as $rule)
				{
					$sanitation_parameters = null;
					$sanitation_parameters_function = array();

					// Were any parameters provided for the rule, e.g. $smcFunc['htmlspecialchars'][ENT_QUOTES]
					if (preg_match('~(.*)\[(.*)\]~', $rule, $match))
					{
						$sanitation_method = '_sanitation_' . $match[1];
						$sanitation_parameters = $match[2];
						$sanitation_function = $match[1];
						$sanitation_parameters_function = explode(',', defined($match[2]) ? constant($match[2]) : $match[2]);
					}
					// Or just a predefined rule e.g. trim
					else
					{
						$sanitation_method = '_sanitation_' . $rule;
						$sanitation_function = $rule;
					}

					// Defined method to use?
					if (is_callable(array($this, $sanitation_method)))
						$input[$field] = $this->{$sanitation_method}($input[$field], $sanitation_parameters);
					// One of our static methods or even a built in php function like strtoupper, intval, etc?
					elseif (is_callable($sanitation_function))
						$input[$field] = call_user_func_array($sanitation_function, array_merge((array) $input[$field], $sanitation_parameters_function));
					// Or even a language construct?
					elseif (in_array($sanitation_function, array('empty', 'array', 'isset')))
					{
						// could be done as methods instead ...
						switch ($sanitation_function)
						{
							case 'empty':
								$input[$field] = empty($input[$field]);
								break;
							case 'array':
								$input[$field] = is_array($input[$field]) ? $input[$field] : array($input[$field]);
								break;
							case 'isset':
								$input[$field] = isset($input[$field]);
								break;
						}
					}
					else
					{
						// @todo fatal_error or other ? being asked to do something we don't know?
						// results in returning $input[$field] = $input[$field];
					}
				}
			}
		}

		return $input;
	}

	/**
	 * When the input field is an array or csv, this will build a new validator
	 * as if the fields were individual ones, each checked against the base rule
	 *
	 * @param mixed[] $input
	 * @param string $field
	 * @param string $rules
	 */
	private function _sanitize_recursive($input, $field, $rules)
	{
		// create a new instance to run against this sub data
		$validator = new Data_Validator();

		$fields = array();
		$sanitation_rules = array();

		if ($this->_datatype[$field] === 'array')
		{
			// Convert the array to individual values, they all use the same rules
			foreach ($input[$field] as $key => $value)
			{
				$sanitation_rules[$key] = $rules;
				$fields[$key] = $value;
			}

			// Sanitize each "new" field
			$validator->sanitation_rules($sanitation_rules);
			$validator->validate($fields);

			// Take the individual results and replace them in the original array
			$input[$field] = array_replace($input[$field], $validator->validation_data());
		}
		elseif ($this->_datatype[$field] === 'csv')
		{
			// Break up the CSV data so we have an array
			$temp = explode(',', $input[$field]);
			foreach ($temp as $key => $value)
			{
				$sanitation_rules[$key] = $rules;
				$fields[$key] = $value;
			}

			// Sanitize each "new" field
			$validator->sanitation_rules($sanitation_rules);
			$validator->validate($fields);

			// Put it back together with clean data
			$input[$field] = implode(',', $validator->validation_data());
		}

		return $input[$field];
	}

	/**
	 * Process any errors and return the error strings
	 *
	 * @param mixed[]|boolean $keys
	 */
	private function _get_error_messages($keys)
	{
		global $txt;

		if (empty($this->_validation_errors))
			return false;

		loadLanguage('Validation');
		$result = array();

		// Just want specific errors then it must be an array
		if (!empty($keys) && !is_array($keys))
			$keys = array($keys);

		foreach ($this->_validation_errors as $error)
		{
			// Field name substitution supplied?
			$field = isset($this->_replacements[$error['field']]) ? $this->_replacements[$error['field']] : $error['field'];

			// Just want specific field errors returned?
			if (!empty($keys) && is_array($keys) && !in_array($error['field'], $keys))
				continue;

			// Set the error message for this validation failure
			if (isset($error['error']))
				$result[] = sprintf($txt[$error['error']], $field, $error['error_msg']);
			// Use our error text based on the function name itself
			elseif (isset($txt[$error['function']]))
			{
				if (!empty($error['param']))
					$result[] = sprintf($txt[$error['function']], $field, $error['param']);
				else
					$result[] = sprintf($txt[$error['function']], $field, $error['input']);
			}
			// can't find the function text, so set a generic one
			else
				$result[] = sprintf($txt['_validate_generic'], $field);
		}

		return empty($result) ? false : $result;
	}

	/**
	 * Contains ... Verify that a value is one of those provided (case insensitive)
	 *
	 * Usage: '[key]' => 'contains[value, value, value]'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param string|null $validation_parameters array or null
	 */
	protected function _validate_contains($field, $input, $validation_parameters = null)
	{
		$validation_parameters = array_map('trim', explode(',', strtolower($validation_parameters)));
		$input[$field] = isset($input[$field]) ? $input[$field] : '';
		$value = trim(strtolower($input[$field]));

		if (in_array($value, $validation_parameters))
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => implode(',', $validation_parameters)
		);
	}

	/**
	 * NotEqual ... Verify that a value does equal any values in list (case insensitive)
	 *
	 * Usage: '[key]' => 'notequal[value, value, value]'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param string|null $validation_parameters array or null
	 */
	protected function _validate_notequal($field, $input, $validation_parameters = null)
	{
		$validation_parameters = explode(',', trim(strtolower($validation_parameters)));
		$input[$field] = isset($input[$field]) ? $input[$field] : '';
		$value = trim(strtolower($input[$field]));

		if (!in_array($value, $validation_parameters))
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => implode(',', $validation_parameters)
		);
	}

	/**
	 * Limits ... Verify that a value is within the defined limits
	 *
	 * Usage: '[key]' => 'limits[min, max]'
	 * >= min and <= max
	 * Limits may be specified one sided
	 *  - limits[,10] means <=10 with no lower bound check
	 *  - limits[10,] means >= 10 with no upper bound
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param string|null $validation_parameters array or null
	 */
	protected function _validate_limits($field, $input, $validation_parameters = null)
	{
		$validation_parameters = explode(',', $validation_parameters);
		$validation_parameters = array_filter($validation_parameters, 'strlen');
		$input[$field] = isset($input[$field]) ? $input[$field] : '';
		$value = $input[$field];

		// Lower bound ?
		$passmin = true;
		if (isset($validation_parameters[0]))
			$passmin = $value >= $validation_parameters[0];

		// Upper bound ?
		$passmax = true;
		if (isset($validation_parameters[1]))
			$passmax = $value <= $validation_parameters[1];

		if ($passmax && $passmin)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => implode(',', $validation_parameters)
		);
	}

	/**
	 * Without ... Verify that a value does contain any characters/values in list
	 *
	 * Usage: '[key]' => 'without[value, value, value]'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_without($field, $input, $validation_parameters = null)
	{
		$validation_parameters = explode(',', $validation_parameters);
		$input[$field] = isset($input[$field]) ? $input[$field] : '';
		$value = $input[$field];

		foreach ($validation_parameters as $dummy => $check)
		{
			if (strpos($value, $check) !== false)
				return array(
					'field' => $field,
					'input' => $input[$field],
					'function' => __FUNCTION__,
					'param' => implode(',', $validation_parameters)
				);
		}

		return;
	}

	/**
	 * required ... Check if the specified key is present and not empty
	 *
	 * Usage: '[key]' => 'required'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_required($field, $input, $validation_parameters = null)
	{
		if (isset($input[$field]) && trim($input[$field]) !== '')
			return;

		return array(
			'field' => $field,
			'input' => isset($input[$field]) ? $input[$field] : '',
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * valid_email .... Determine if the provided email is valid
	 *
	 * Usage: '[key]' => 'valid_email'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_valid_email($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		// Quick check, no @ in the email
		if (strrpos($input[$field], '@') === false)
			$valid = false;
		else
			$valid = filter_var($input[$field], FILTER_VALIDATE_EMAIL) !== false;

		if ($valid)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * max_length ... Determine if the provided value length is less or equal to a specific value
	 *
	 * Usage: '[key]' => 'max_length[x]'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_max_length($field, $input, $validation_parameters = null)
	{
		global $smcFunc;
		
		if (!isset($input[$field]))
			return;

		if ($smcFunc['strlen']($input[$field]) <= (int) $validation_parameters)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * min_length Determine if the provided value length is greater than or equal to a specific value
	 *
	 * Usage: '[key]' => 'min_length[x]'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_min_length($field, $input, $validation_parameters = null)
	{
		global $smcFunc;
		
		if (!isset($input[$field]))
			return;

		if ($smcFunc['strlen']($input[$field]) >= (int) $validation_parameters)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * length ... Determine if the provided value length matches a specific value
	 *
	 * Usage: '[key]' => 'exact_length[x]'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_length($field, $input, $validation_parameters = null)
	{
		global $smcFunc;
		
		if (!isset($input[$field]))
			return;

		if ($smcFunc['strlen']($input[$field]) == (int) $validation_parameters)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * alpha ... Determine if the provided value contains only alpha characters
	 *
	 * Usage: '[key]' => 'alpha'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_alpha($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		// A character with the Unicode property of letter (any kind of letter from any language)
		if (!preg_match('~^(\p{L})+$~iu', $input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * alpha_numeric ... Determine if the provided value contains only alpha-numeric characters
	 *
	 * Usage: '[key]' => 'alpha_numeric'
	 * Allows letters, numbers dash and underscore characters
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_alpha_numeric($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		// A character with the Unicode property of letter or number (any kind of letter or numeric 0-9 from any language)
		if (!preg_match('~^([-_\p{L}\p{Nd}])+$~iu', $input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * alpha_dash ... Determine if the provided value contains only alpha characters plus dashed and underscores
	 *
	 * Usage: '[key]' => 'alpha_dash'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_alpha_dash($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!preg_match('~^([-_\p{L}])+$~iu', $input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * isarray ... Determine if the provided value exists and is an array
	 *
	 * Usage: '[key]' => 'isarray'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_isarray($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!is_array($input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * numeric ... Determine if the provided value is a valid number or numeric string
	 *
	 * Usage: '[key]' => 'numeric'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_numeric($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!is_numeric($input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * integer ... Determine if the provided value is a valid integer
	 *
	 * Usage: '[key]' => 'integer'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_integer($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		$filter = filter_var($input[$field], FILTER_VALIDATE_INT);

		if ($filter === false && version_compare(PHP_VERSION, 5.4, '<') && ($input[$field] === '+0' || $input[$field] === '-0'))
		{
			$filter = true;
		}

		if ($filter === false)
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * boolean ... Determine if the provided value is a boolean
	 *
	 * Usage: '[key]' => 'boolean'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_boolean($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		$filter = filter_var($input[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

		// Fixed in php 7 and later in php 5.6.27 https://bugs.php.net/bug.php?id=67167
		if (version_compare(PHP_VERSION, '5.6.27', '>='))
		{
			$filter = $filter;
		}
		if (version_compare(PHP_VERSION, 5.4, '<') && $filter === null && ($input[$field] === false || $input[$field] === ''))
		{
			$filter = false;
		}
		if ($filter === false && is_object($input[$field]) && method_exists($input[$field], '__tostring') === false)
		{
			$filter = null;
		}

		if ($filter === null)
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * float ... Determine if the provided value is a valid float
	 *
	 * Usage: '[key]' => 'float'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_float($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (filter_var($input[$field], FILTER_VALIDATE_FLOAT) === false)
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * valid_url ... Determine if the provided value is a valid-ish URL
	 *
	 * Usage: '[key]' => 'valid_url'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_valid_url($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!preg_match('`^(https{0,1}?:(//([a-z0-9\-._~%]+)(:[0-9]+)?(/[a-z0-9\-._~%!$&\'()*+,;=:@]+)*/?))(\?[a-z0-9\-._~%!$&\'()*+,;=:@/?]*)?(\#[a-z0-9\-._~%!$&\'()*+,;=:@/?]*)?$`', $input[$field], $matches))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * valid_ipv6 ... Determine if the provided value is a valid IPv6 address
	 *
	 * Usage: '[key]' => 'valid_ipv6'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_valid_ipv6($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (filter_var($input[$field], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false)
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * valid_ip ... Determine if the provided value is a valid IP4 address
	 *
	 * Usage: '[key]' => 'valid_ip'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_valid_ip($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (filter_var($input[$field], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * Validate PHP syntax of an input.
	 *
	 * This approach to validation has been inspired by Compuart.
	 *
	 * Usage: '[key]' => 'php_syntax'
	 *
	 * @uses ParseError
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_php_syntax($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		// Check the depth.
		$level = 0;
		$tokens = @token_get_all($input[$field]);
		foreach ($tokens as $token)
		{
			if ($token === '{')
				$level++;
			elseif ($token === '}')
				$level--;
		}

		if (!empty($level))
			$result = false;
		else
		{
			// Check the validity of the syntax.
			ob_start();
			$errorReporting = error_reporting(0);
			try
			{
				$result = @eval('
					if (false)
					{
						' . preg_replace('~^(?:\s*<\\?(?:php)?|\\?>\s*$)~u', '', $input[$field]) . '
					}
				');
			}
			catch (ParseError $e)
			{
				$result = false;
			}
			error_reporting($errorReporting);
			@ob_end_clean();
		}

		if ($result === false)
		{
			$errorMsg = error_get_last();

			return array(
				'field' => $field,
				'input' => $input[$field],
				'error' => '_validate_php_syntax',
				'error_msg' => $errorMsg['message'],
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * Checks if the input is a valid css-like color
	 *
	 * Usage: '[key]' => 'valid_color'
	 *
	 * @param string $field
	 * @param mixed[] $input
	 * @param mixed[]|null $validation_parameters array or null
	 */
	protected function _validate_valid_color($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		// A color can be a name: there are 140 valid, but a similar list is too long, so let's just use the basic 17
		if (in_array(strtolower($input[$field]), array('aqua', 'black', 'blue', 'fuchsia', 'gray', 'green', 'lime', 'maroon', 'navy', 'olive', 'orange', 'purple', 'red', 'silver', 'teal', 'white', 'yellow')))
			return true;

		// An hex code
		if (preg_match('~^#([a-f0-9]{3}|[a-f0-9]{6})$~i', $input[$field]) === 1)
			return true;

		// RGB
		if (preg_match('~^rgb\(\d{1,3},\d{1,3},\d{1,3}\)$~i', str_replace(' ', '', $input[$field])) === 1)
			return true;

		// RGBA
		if (preg_match('~^rgba\(\d{1,3},\d{1,3},\d{1,3},(0|0\.\d+|1(\.0*)|\.\d+)\)$~i', str_replace(' ', '', $input[$field])) === 1)
			return true;

		// HSL
		if (preg_match('~^hsl\(\d{1,3},\d{1,3}%,\d{1,3}%\)$~i', str_replace(' ', '', $input[$field])) === 1)
			return true;

		// HSLA
		if (preg_match('~^hsla\(\d{1,3},\d{1,3}%,\d{1,3}%,(0|0\.\d+|1(\.0*)|\.\d+)\)$~i', str_replace(' ', '', $input[$field])) === 1)
			return true;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * gmail_normalize ... Used to normalize a gmail address as many resolve to the same address
	 *
	 * - Gmail user can use @googlemail.com instead of @gmail.com
	 * - Gmail ignores all characters after a + (plus sign) in the username
	 * - Gmail ignores all . (dots) in username
	 * - auser@gmail.com, a.user@gmail.com, auser+big@gmail.com and a.user+gigantic@googlemail.com are same email address.
	 *
	 * @param string $input
	 */
	protected function _sanitation_gmail_normalize($input)
	{
		if (!isset($input))
			return;

		$at_index = strrpos($input, '@');

		// Time to do some checking on the local@domain parts
		$local_name = substr($input, 0, $at_index);
		$domain_name = strtolower(substr($input, $at_index + 1));

		// Gmail address?
		if (in_array($domain_name, array('gmail.com', 'googlemail.com')))
		{
			// Gmail ignores all . (dot) in username
			$local_name = str_replace('.', '', $local_name);

			// Gmail ignores all characters after a + (plus sign) in username
			$temp = explode('+', $local_name);
			$local_name = $temp[0];

			// @todo should we force gmail.com or use $domain_name, force is safest but perhaps most confusing
		}

		return $local_name . '@' . $domain_name;
	}

	/**
	 * Uses $smcFunc['htmlspecialchars'] to sanitize any html in the input
	 *
	 * @param string $input
	 */
	protected function _sanitation_cleanhtml($input)
	{
		global $smcFunc;
		
		if (!isset($input))
			return;

		return $smcFunc['htmlspecialchars']($input);
	}
}
