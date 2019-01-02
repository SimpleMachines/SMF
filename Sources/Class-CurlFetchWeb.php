<?php
/**
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
 * Class curl_fetch_web_data
 * Simple cURL class to fetch a web page
 * Properly redirects even with safe mode and basedir restrictions
 * Can provide simple post options to a page
 *
 * Load class
 * Initiate as
 *  - $fetch_data = new cURL_fetch_web_data();
 *	- optionally pass an array of cURL options and redirect count
 *	- cURL_fetch_web_data(cURL options array, Max redirects);
 *  - $fetch_data = new cURL_fetch_web_data(array(CURLOPT_SSL_VERIFYPEER => 1), 5);
 *
 * Make the call
 *  - $fetch_data('https://www.simplemachines.org'); // fetch a page
 *  - $fetch_data('https://www.simplemachines.org', array('user' => 'name', 'password' => 'password')); // post to a page
 *  - $fetch_data('https://www.simplemachines.org', parameter1&parameter2&parameter3); // post to a page
 *
 * Get the data
 *  - $fetch_data->result('body'); // just the page content
 *  - $fetch_data->result(); // an array of results, body, header, http result codes
 *  - $fetch_data->result_raw(); // show all results of all calls (in the event of a redirect)
 *  - $fetch_data->result_raw(0); // show all results of call x
 */
class curl_fetch_web_data
{
	/**
	 * Set the default items for this class
	 *
	 * @var array $default_options
	 */
	private $default_options = array(
		CURLOPT_RETURNTRANSFER	=> 1, // Get returned value as a string (don't output it)
		CURLOPT_HEADER			=> 1, // We need the headers to do our own redirect
		CURLOPT_FOLLOWLOCATION	=> 0, // Don't follow, we will do it ourselves so safe mode and open_basedir will dig it
		CURLOPT_USERAGENT		=> 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:11.0) Gecko Firefox/11.0', // set a normal looking useragent
		CURLOPT_CONNECTTIMEOUT	=> 15, // Don't wait forever on a connection
		CURLOPT_TIMEOUT			=> 90, // A page should load in this amount of time
		CURLOPT_MAXREDIRS		=> 5, // stop after this many redirects
		CURLOPT_ENCODING		=> 'gzip,deflate', // accept gzip and decode it
		CURLOPT_SSL_VERIFYPEER	=> 0, // stop cURL from verifying the peer's certificate
		CURLOPT_SSL_VERIFYHOST	=> 0, // stop cURL from verifying the peer's host
		CURLOPT_POST			=> 0, // no post data unless its passed
	);

	/**
	 * @var int Maximum number of redirects
	 */
	public $max_redirect;

	/**
	 * @var array An array of cURL options
	 */
	public $user_options = array();

	/**
	 * @var string Any post data as form name => value
	 */
	public $post_data;

	/**
	 * @var array An array of cURL options
	 */
	public $options;

	/**
	 * @var int ???
	 */
	public $current_redirect;

	/**
	 * @var array Stores responses (url, code, error, headers, body) in the response array
	 */
	public $response = array();

	/**
	 * @var string The header
	 */
	public $headers;

	/**
	 * Start the curl object
	 * - allow for user override values
	 *
	 * @param array $options An array of cURL options
	 * @param int $max_redirect Maximum number of redirects
	 */
	public function __construct($options = array(), $max_redirect = 3)
	{
		// Initialize class variables
		$this->max_redirect = intval($max_redirect);
		$this->user_options = $options;
	}

	/**
	 * Main calling function,
	 *  - will request the page data from a given $url
	 *  - optionally will post data to the page form if post data is supplied
	 *  - passed arrays will be converted to a post string joined with &'s
	 *  - calls set_options to set the curl opts array values based on the defaults and user input
	 *
	 * @param string $url the site we are going to fetch
	 * @param array $post_data any post data as form name => value
	 * @return object An instance of the curl_fetch_web_data class
	 */
	public function get_url_data($url, $post_data = array())
	{
		// POSTing some data perhaps?
		if (!empty($post_data) && is_array($post_data))
			$this->post_data = $this->build_post_data($post_data);
		elseif (!empty($post_data))
			$this->post_data = trim($post_data);

		// set the options and get it
		$this->set_options();
		$this->curl_request(str_replace(' ', '%20', $url));

		return $this;
	}

	/**
	 * Makes the actual cURL call
	 *  - stores responses (url, code, error, headers, body) in the response array
	 *  - detects 301, 302, 307 codes and will redirect to the given response header location
	 *
	 * @param string $url The site to fetch
	 * @param bool $redirect Whether or not this was a redirect request
	 * @return void|bool Sets various properties of the class or returns false if the URL isn't specified
	 */
	private function curl_request($url, $redirect = false)
	{
		// we do have a url I hope
		if ($url == '')
			return false;
		else
			$this->options[CURLOPT_URL] = $url;

		// if we have not already been redirected, set it up so we can if needed
		if (!$redirect)
		{
			$this->current_redirect = 1;
			$this->response = array();
		}

		// Initialize the curl object and make the call
		$cr = curl_init();
		curl_setopt_array($cr, $this->options);
		curl_exec($cr);

		// Get what was returned
		$curl_info = curl_getinfo($cr);
		$curl_content = curl_multi_getcontent($cr);
		$url = $curl_info['url']; // Last effective URL
		$http_code = $curl_info['http_code']; // Last HTTP code
		$body = (!curl_error($cr)) ? substr($curl_content, $curl_info['header_size']) : false;
		$error = (curl_error($cr)) ? curl_error($cr) : false;

		// close this request
		curl_close($cr);

		// store this 'loops' data, someone may want all of these :O
		$this->response[] = array(
			'url' => $url,
			'code' => $http_code,
			'error' => $error,
			'headers' => isset($this->headers) ? $this->headers : false,
			'body' => $body,
			'size' => $curl_info['download_content_length'],
		);

		// If this a redirect with a location header and we have not given up, then do it again
		if (preg_match('~30[127]~i', $http_code) === 1 && $this->headers['location'] != '' && $this->current_redirect <= $this->max_redirect)
		{
			$this->current_redirect++;
			$header_location = $this->get_redirect_url($url, $this->headers['location']);
			$this->redirect($header_location, $url);
		}
	}

	/**
	 * Used if being redirected to ensure we have a fully qualified address
	 *
	 * @param string $last_url The URL we went to
	 * @param string $new_url The URL we were redirected to
	 * @return string The new URL that was in the HTTP header
	 */
	private function get_redirect_url($last_url = '', $new_url = '')
	{
		// Get the elements for these urls
		$last_url_parse = parse_url($last_url);
		$new_url_parse = parse_url($new_url);

		// redirect headers are often incomplete or relative so we need to make sure they are fully qualified
		$new_url_parse['scheme'] = isset($new_url_parse['scheme']) ? $new_url_parse['scheme'] : $last_url_parse['scheme'];
		$new_url_parse['host'] = isset($new_url_parse['host']) ? $new_url_parse['host'] : $last_url_parse['host'];
		$new_url_parse['path'] = isset($new_url_parse['path']) ? $new_url_parse['path'] : $last_url_parse['path'];
		$new_url_parse['query'] = isset($new_url_parse['query']) ? $new_url_parse['query'] : '';

		// Build the new URL that was in the http header
		return $new_url_parse['scheme'] . '://' . $new_url_parse['host'] . $new_url_parse['path'] . (!empty($new_url_parse['query']) ? '?' . $new_url_parse['query'] : '');
	}

	/**
	 * Used to return the results to the calling program
	 *  - called as ->result() will return the full final array
	 *  - called as ->result('body') to just return the page source of the result
	 *
	 * @param string $area Used to return an area such as body, header, error
	 * @return string The response
	 */
	public function result($area = '')
	{
		$max_result = count($this->response) - 1;

		// just return a specifed area or the entire result?
		if ($area == '')
			return $this->response[$max_result];
		else
			return isset($this->response[$max_result][$area]) ? $this->response[$max_result][$area] : $this->response[$max_result];
	}

	/**
	 * Will return all results from all loops (redirects)
	 *  - Can be called as ->result_raw(x) where x is a specific loop results.
	 *  - Call as ->result_raw() for everything.
	 *
	 * @param string $response_number Which response we want to get
	 * @return array|string The entire response array or just the specified response
	 */
	public function result_raw($response_number = '')
	{
		if (!is_numeric($response_number))
			return $this->response;
		else
		{
			$response_number = min($response_number, count($this->response) - 1);
			return $this->response[$response_number];
		}
	}

	/**
	 * Takes supplied POST data and url encodes it
	 *  - forms the date (for post) in to a string var=xyz&var2=abc&var3=123
	 *  - drops vars with @ since we don't support sending files (uploading)
	 *
	 * @param array|string $post_data The raw POST data
	 * @return string A string of post data
	 */
	private function build_post_data($post_data)
	{
		if (is_array($post_data))
		{
			$postvars = array();

			// build the post data, drop ones with leading @'s since those can be used to send files, we don't support that.
			foreach ($post_data as $name => $value)
				$postvars[] = $name . '=' . urlencode($value[0] == '@' ? '' : $value);

			return implode('&', $postvars);
		}
		else
			return $post_data;
	}

	/**
	 * Sets the final cURL options for the current call
	 *  - overwrites our default values with user supplied ones or appends new user ones to what we have
	 *  - sets the callback function now that $this is existing
	 *
	 * @return void
	 */
	private function set_options()
	{
		// Callback to parse the returned headers, if any
		$this->default_options[CURLOPT_HEADERFUNCTION] = array($this, 'header_callback');

		// Any user options to account for
		if (is_array($this->user_options))
		{
			$keys = array_merge(array_keys($this->default_options), array_keys($this->user_options));
			$vals = array_merge($this->default_options, $this->user_options);
			$this->options = array_combine($keys, $vals);
		}
		else
			$this->options = $this->default_options;

		// POST data options, here we don't allow any overide
		if (isset($this->post_data))
		{
			$this->options[CURLOPT_POST] = 1;
			$this->options[CURLOPT_POSTFIELDS] = $this->post_data;
		}
	}

	/**
	 * Called to initiate a redirect from a 301, 302 or 307 header
	 *  - resets the cURL options for the loop, sets the referrer flag
	 *
	 * @param string $target_url The URL we want to redirect to
	 * @param string $referer_url The URL that we're redirecting from
	 */
	private function redirect($target_url, $referer_url)
	{
		// no no I last saw that over there ... really, 301, 302, 307
		$this->set_options();
		$this->options[CURLOPT_REFERER] = $referer_url;
		$this->curl_request($target_url, true);
	}

	/**
	 * Callback function to parse returned headers
	 *  - lowercases everything to make it consistent
	 *
	 * @param type $cr Not sure what this is used for?
	 * @param string $header The header
	 * @return int The length of the header
	 */
	private function header_callback($cr, $header)
	{
		$_header = trim($header);
		$temp = explode(': ', $_header, 2);

		// set proper headers only
		if (isset($temp[0]) && isset($temp[1]))
			$this->headers[strtolower($temp[0])] = strtolower(trim($temp[1]));

		// return the length of what was passed unless you want a Failed writing header error ;)
		return strlen($header);
	}
}

?>