<?php

/**
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

use SMF\Cache\CacheApi;

/**
 * Represents an IP address and allows performing various operations on it.
 */
class IP implements \Stringable
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'ip2range' => 'ip2range',
			'range2ip' => 'range2ip',
			'isValidIP' => 'isValidIP',
			'isValidIPv6' => 'isValidIPv6',
			'hostFromIp' => 'host_from_ip',
			'inet_ptod' => 'inet_ptod',
			'inet_dtop' => 'inet_dtop',
			'expandIPv6' => 'expandIPv6',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * IP addresses of some well known public DNS servers.
	 */
	public const DNS_SERVERS = [
		// Google
		'8.8.8.8',
		'8.8.4.4',
		// OpenDNS
		'208.67.222.222',
		'208.67.220.220',
		// CloudFare
		'1.1.1.1',
		'1.0.0.1',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * The string representation of the IP address.
	 */
	protected $ip;

	/**
	 * @var string
	 *
	 * The host name associated with this IP address.
	 */
	protected $host;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * If the passed string is not a valid IP address, it will be set to ''.
	 *
	 * @param ?string $ip The IP address in either string or binary form.
	 */
	public function __construct(?string $ip)
	{
		// Is it in a valid IPv4 string?
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
			$this->ip = $ip;
		}
		// Is it in a valid IPv6 string?
		elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
			// Pack and unpack to ensure it is in standard form.
			$this->ip = inet_ntop(inet_pton($ip));
		}
		// It's either in binary form or it's invalid.
		else {
			$this->ip = (string) @inet_ntop($ip);
		}
	}

	/**
	 * Returns the string form of the IP address.
	 */
	public function __toString(): string
	{
		return $this->ip;
	}

	/**
	 * Returns the binary form of the IP address.
	 */
	public function toBinary(): string
	{
		return inet_pton($this->ip);
	}

	/**
	 * Returns the hexadecimal form of the IP address.
	 */
	public function toHex(): string
	{
		return bin2hex($this->toBinary());
	}

	/**
	 * Returns the fully expanded string form of the IP address.
	 *
	 * For IPv4, this just returns the standard string.
	 * For IPv6, this returns the fully expanded form of the string.
	 *
	 * @return string The fully expanded form of the IP address.
	 */
	public function expand(): string
	{
		// IPv6.
		if ($this->isValid(FILTER_FLAG_IPV6)) {
			return implode(':', str_split($this->toHex(), 4));
		}

		// IPv4.
		return $this->ip;
	}

	/**
	 * Check whether this is a valid IP address.
	 *
	 * @param int $flags Optional flags for filter_var's third parameter.
	 * @return bool
	 */
	public function isValid(int $flags = 0): bool
	{
		return filter_var($this->ip, FILTER_VALIDATE_IP, $flags) !== false;
	}

	/**
	 * Tries to finds the name of the host that corresponds to this IP address.
	 *
	 * @param int $timeout Milliseconds until timeout. Set to 0 for no timeout.
	 *    Default: 1000.
	 * @return string The host name, or the IP address string on failure.
	 */
	public function getHost(int $timeout = 1000): string
	{
		// Can't get a host for an invalid address.
		if (!$this->isValid()) {
			return $this->ip;
		}

		// Avoid unnecessary repetition.
		if (isset($this->host)) {
			return $this->host;
		}

		// Is it cached?
		if (($host = CacheApi::get('hostlookup-' . $this->ip, 600)) !== null) {
			$this->host = $host;

			return $this->host;
		}

		// No negative timeout values allowed.
		$timeout = max(0, $timeout);

		// If we don't need to set a timeout, use PHP's native function.
		if (empty($timeout)) {
			$host = \gethostbyaddr($this->ip);
		}

		// Try asking the operating system to look it up for us.
		if (empty($host)) {
			$host = $this->hostLookup($timeout);
		}

		// Try looking it up ourselves.
		if (empty($host)) {
			$host = $this->getHostByAddr($timeout);
		}

		// If all attempts failed, use the IP address.
		if (empty($host)) {
			$host = $this->ip;
		}

		// Set it.
		$this->host = $host;

		// Cache it.
		CacheApi::put('hostlookup-' . $this->ip, $this->host, 600);

		// Return it.
		return $this->host;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience wrapper for constuctor.
	 *
	 * This is just syntactical sugar to ease method chaining.
	 *
	 * @param string $ip The IP address in either string or binary form.
	 * @return object The created object.
	 */
	public static function create(string $ip): object
	{
		return new self($ip);
	}

	/**
	 * Given an IP address or range of IP addresses, possibly containing
	 * wildcards, returns an array containing two IP addresses that bound the
	 * possible range.
	 *
	 * Used to convert a user-readable format to a format suitable for the
	 * database.
	 *
	 *  - If $addr is one complete IP address, the return value will contain two
	 *    copies of that address, since the range starts and ends with the same
	 *    value.
	 *
	 *    Examples:
	 *
	 *    '1.2.3.4' -> array('low' => '1.2.3.4', 'high' => '1.2.3.4')
	 *
	 *  - If $addr is one IP address with wildcards, the return value will
	 *    contain the minimum and maxium values possible with those wildcards.
	 *
	 *    Examples:
	 *
	 *    '1.2.*' -> array('low' => 1.2.0.0', 'high' => '1.2.255.255')
	 *    '1.*.*.4' -> array('low' => '1.0.0.4', 'high' => '1.255.255.4')
	 *
	 *  - If $addr contains two complete IP addresses, the return value will
	 *    contain those two addresses.
	 *
	 *    Examples:
	 *
	 *    '1.2.3.4-5.6.7.8' -> array('low' => '1.2.3.4', 'high' => '5.6.7.8')
	 *
	 *  - If $addr contains two IP addresses, either of which contain wildcards,
	 *    then any wildcards in the first one will be made as low as possible,
	 *    and any wildcards in the second one will be made as high as possible.
	 *
	 *    Examples:
	 *
	 *    '1.2.*-5.6.7.8'   -> array('low' => '1.2.0.0', 'high' => '5.6.7.8')
	 *    '1.2.3.*-5.6.7.8' -> array('low' => '1.2.3.0', 'high' => '5.6.7.8')
	 *    '1.2.*.4-5.6.*'  -> array('low' => '1.2.0.4', 'high' => '5.6.255.255')
	 *
	 * The examples only show IPv4, but this also works for IPv6 addresses.
	 *
	 * @param string $addr The full IP
	 * @return array An array containing two instances of this class.
	 */
	public static function ip2range($addr): array
	{
		// Pretend that 'unknown' is 255.255.255.255, since that can't be an IP anyway.
		if ($addr == 'unknown') {
			$addr = '255.255.255.255';
		}

		$mode = (preg_match('/:/', $addr) ? ':' : '.');
		$max = ($mode == ':' ? 'ffff' : '255');
		$min = '0';

		$max_parts = ($mode === ':' ? 8 : 4);

		$range = [[], []];

		// No range.
		if (strpos($addr, '-') === false) {
			$range[0] = $range[1] = explode($mode, $addr);

			foreach ($range[0] as &$octet) {
				$octet = strtr($octet, ['*' => $min]);
			}

			foreach ($range[1] as &$octet) {
				$octet = strtr($octet, ['*' => $max]);
			}
		}
		// A range.
		else {
			// It works best to split on the range marker and then figure out the parts.
			list($range[0], $range[1]) = explode('-', $addr);

			$valid_low = filter_var(strtr($range[0], ['*' => $min]), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
			$valid_high = filter_var(strtr($range[1], ['*' => $max]), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

			// Range marker is between two valid IP addresses.
			if ($valid_low && $valid_high) {
				$range[0] = explode($mode, strtr($range[0], ['*' => $min]));
				$range[1] = explode($mode, strtr($range[1], ['*' => $max]));
			}
			// One or the other is a fragment.
			else {
				$range = [
					$valid_low ? explode($mode, strtr($range[0], ['*' => $min])) : [],
					$valid_high ? explode($mode, strtr($range[1], ['*' => $max])) : [],
				];

				$octets = explode($mode, $addr);

				foreach ($octets as $key => $octet) {
					if (strpos($octet, '-') === false) {
						if (!$valid_low) {
							$range[0][] = $octet;
						}

						if (!$valid_high) {
							$range[1][] = $octet;
						}
					} else {
						$ranged = explode('-', $octet);

						// Special handling for '1.2.3.*-5.6.7.8'
						if ($ranged[0] === '*') {
							$ranged[0] = $min;
							$range[0][] = $min;

							if (!$valid_high) {
								$range[1] = [$ranged[1]];
							}

							while (filter_var(implode($mode, $range[0]), FILTER_VALIDATE_IP) === false && count($range[0]) < count($octets)) {
								$range[0][] = $min;
							}

							$valid_low = true;
						} else {
							if (!$valid_low) {
								$range[0][] = $ranged[0];
							}
						}

						// Special handling for '1.2.3.4-*.6.7.8'
						if ($ranged[1] === '*') {
							$ranged[1] = $max;

							if (!$valid_high) {
								$range[1][] = $max;
							}
						} else {
							if (!$valid_high) {
								$range[1][] = $ranged[1];
							}
						}
					}
				}
			}
		}

		// Finalize the strings.
		foreach ([0, 1] as $key) {
			// If it's too long, shorten it.
			if (count($range[$key]) > $max_parts) {
				$range[$key] = array_slice($range[$key], 0, $max_parts);
			}

			// If they're still too short, pad them out.
			while (filter_var(implode($mode, $range[$key]), FILTER_VALIDATE_IP) === false && count($range[$key]) < $max_parts) {
				if (in_array('*', $range[$key])) {
					$range[$key] = explode($mode, preg_replace('/\*(?!' . preg_quote($mode . '*') . ')/', '*' . $mode . '*', implode($mode, $range[$key])));
				} else {
					$range[$key][] = '*';
				}
			}

			// Convert to string and replace the wildcards.
			$range[$key] = strtr(implode($mode, $range[$key]), ['*' => $key === 0 ? $min : $max]);
		}

		// Make sure the low one really is lower than the high one.
		usort($range, fn ($a, $b) => inet_pton($a) <=> inet_pton($b));

		// Return instances of this class, not just plain strings.
		$low = new self($range[0]);
		$high = new self($range[1]);

		// Final validity check, just in case.
		if (!$low->isValid() || !$high->isValid()) {
			return ['low' => new self('255.255.255.255'), 'high' => new self('255.255.255.255')];
		}

		return ['low' => $low, 'high' => $high];
	}

	/**
	 * Convert a range of IP addresses into a single string.
	 * It's practically the reverse function of ip2range().
	 *
	 * @param string $low The low end of the range.
	 * @param string $high The high end of the range.
	 * @return string A string indicating the range.
	 */
	public static function range2ip($low, $high): string
	{
		$low = new IP($low);
		$high = new IP($high);

		if ($low == '255.255.255.255') {
			return 'unknown';
		}

		if ($low == $high) {
			return $low;
		}

		return $low . '-' . $high;
	}

	/**
	 * Backward compatibility wrapper for the isValid() method.
	 *
	 * @param string $ip An IP address in either string or binary form.
	 * @return string Whether $ip is a valid IP address.
	 */
	public static function isValidIP(string $ip): string
	{
		$ip = new self($ip);

		return $ip->isValid();
	}

	/**
	 * Another backward compatibility wrapper for the isValid() method.
	 *
	 * This one checks specifically for valid IPv6 addresses.
	 *
	 * @param string $ip An IPv6 address in either string or binary form.
	 * @return string Whether $ip is a valid IPv6 address.
	 */
	public static function isValidIPv6(string $ip): string
	{
		$ip = new self($ip);

		return $ip->isValid(FILTER_FLAG_IPV6);
	}

	/**
	 * Backward compatibility wrapper for the getHost() method.
	 *
	 * @param string $ip An IP address in either string or binary form.
	 * @return string The host name.
	 */
	public static function hostFromIp(string $ip): string
	{
		$ip = new self($ip);

		return $ip->getHost(0);
	}

	/**
	 * Backward compatibility wrapper for the toBinary() method.
	 *
	 * @param string $ip An IP address in either string or binary form.
	 * @return string The host name.
	 */
	public static function inet_ptod(string $ip): string
	{
		$ip = new self($ip);

		return $ip->toBinary();
	}

	/**
	 * Backward compatibility wrapper for the __toString() method.
	 *
	 * @param string $ip An IP address in either string or binary form.
	 * @return string The host name.
	 */
	public static function inet_dtop(string $ip): string
	{
		return (string) new self($ip);
	}

	/**
	 * Backward compatibility wrapper for the expand() method.
	 *
	 * @param string $ip An IPv6 address.
	 * @param bool $return_bool_if_invalid Controls return type if address is
	 *    invalid. True for boolean, false for empty string. Default: true.
	 * @return string|false The expanded IPv6 address, or false/an empty string
	 *    if address was invalid.
	 */
	public static function expandIPv6(string $ip, bool $return_bool_if_invalid = true): string|false
	{
		$ip = new self($ip);

		if ($return_bool_if_invalid && !$ip->isValid(FILTER_FLAG_IPV6)) {
			return false;
		}

		return $ip->expand();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Asks the operating system to look up the host name for this IP address.
	 *
	 * Only works if we have shell_exec permission and if the host, nslookup,
	 * and/or dscacheutil commands are available.
	 *
	 * @return string The host name, or an empty string on failure.
	 */
	protected function hostLookup(int $timeout = 1000): string
	{
		if (!function_exists('shell_exec')) {
			return '';
		}

		// Macs can use dscacheutil, host, or nslookup, but this is the recommended one.
		if (Utils::$context['server']['is_mac']) {
			$test = (string) @shell_exec('dscacheutil -q host -a ' . ($this->isValid(FILTER_FLAG_IPV6) ? 'ipv6_address' : 'ip_address') . ' ' . @escapeshellarg($this->ip));

			if (preg_match('~name:\s+([^\s]+)~i', $test, $match)) {
				$host = $match[1];
			}
		}

		// Try the Unix/Linux host command, perhaps?
		if (!isset($host) && !Utils::$context['server']['is_windows']) {
			if (!isset(Config::$modSettings['host_to_dis'])) {
				$test = (string) @shell_exec('host -W ' . max(1, floor($timeout / 1000)) . ' ' . @escapeshellarg($this->ip));
			} else {
				$test = (string) @shell_exec('host ' . @escapeshellarg($this->ip));
			}

			// Did host say it didn't find anything?
			if (strpos($test, 'not found') !== false) {
				$host = '';
			}
			// Invalid server option?
			elseif ((strpos($test, 'invalid option') || strpos($test, 'Invalid query name 1')) && !isset(Config::$modSettings['host_to_dis'])) {
				Config::updateModSettings(['host_to_dis' => 1]);
			}
			// Maybe it found something, after all?
			elseif (preg_match('~\s([^\s]+?)\.\s~', $test, $match)) {
				$host = $match[1];
			}
		}

		// This is nslookup; available on Windows and Macs.
		if (!isset($host) && (Utils::$context['server']['is_windows'] || Utils::$context['server']['is_mac'])) {
			$test = (string) @shell_exec('nslookup -timeout=' . max(1, floor($timeout / 1000)) . ' ' . @escapeshellarg($this->ip));

			if (strpos($test, 'Non-existent domain') !== false) {
				$host = '';
			} elseif (preg_match('~Name\s*(?:=|:)\s+([^\s]+)~i', $test, $match)) {
				$host = $match[1];
			}
		}

		return $host ?? '';
	}

	/**
	 * Like PHP's gethostbyaddr(), but with a timeout.
	 *
	 * Inspired by code by king dot macro at gmail dot com.
	 * https://www.php.net/manual/en/function.gethostbyaddr.php#46869
	 *
	 * @param int $timeout Milliseconds until timeout. Default: 1000.
	 * @return string The host name, or the IP address on failure.
	 */
	protected function getHostByAddr(int $timeout = 1000)
	{
		$query = $this->getReverseDnsQuery();

		// Keep it sane...
		$attempt = 0;

		// Submit the query to an external DNS server.
		// If it fails, try another server.
		do {
			$handle = @fsockopen('udp://' . self::DNS_SERVERS[$attempt], 53);

			$sent = @fwrite($handle, $query);
			@stream_set_timeout($handle, intdiv($timeout, 1000), $timeout % 1000);

			$response = @fread($handle, 1000);
			$type = $response == '' ? false : @unpack('s', substr($response, $sent + 2));

			@fclose($handle);
		} while (($type == false || $type[1] != 0x0C00) && isset(self::DNS_SERVERS[++$attempt]));

		// All attempts failed.
		if ($type == false || $type[1] != 0x0C00) {
			return $this->ip;
		}

		// Set up our variables.
		$host = [];
		$len = 0;

		// Set our pointer at the beginning of the host name.
		// Uses the request size from earlier rather than work it out.
		$position = $sent + 12;

		// Reconstruct host name.
		do {
			// Get segment size.
			$len = unpack('c', substr($response, $position));

			// This is a null terminated string, so length 0 = finished.
			if ($len[1] == 0) {
				return implode('.', $host);
			}

			// Add segment to our host.
			$host[] = substr($response, $position + 1, $len[1]);

			// Move pointer on to the next segment.
			$position += $len[1] + 1;
		} while ($len != 0);

		// If we get here, something went wrong.
		return $this->ip;
	}

	/**
	 * Gets the raw data to send in a reverse DNS lookup query for this address.
	 *
	 * @return string The raw query to send.
	 */
	protected function getReverseDnsQuery(): string
	{
		// Is this an IPv6 address?
		if ($this->isValid(FILTER_FLAG_IPV6)) {
			$parts = array_merge(str_split(strrev($this->toHex())), ['ip6', 'arpa']);
		} else {
			$parts = array_merge(array_reverse(explode('.', $this->ip)), ['in-addr', 'arpa']);
		}

		// Random transaction number (for routers, etc., to get the reply back)
		$query = sprintf('%02d', mt_rand(0, 99));

		// Request header.
		$query .= "\1\0\0\1\0\0\0\0\0\0";

		// The interesting stuff.
		foreach ($parts as $part) {
			$query .= chr(strlen($part)) . $part;
		}

		// And the final bit of the request.
		$query .= "\0\0\x0C\0\1";

		return $query;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\IP::exportStatic')) {
	IP::exportStatic();
}

?>