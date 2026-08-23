<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF;

use SMF\Cache\CacheApi;

/**
 * Represents an IP address and allows performing various operations on it.
 */
class IP implements \Stringable
{
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
		// CloudFlare
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

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var IP|string|null

	 * IP of the current user. Typically filled with $_SERVER['REMOTE_ADDR']
	 * or a IP supplied by a reverse proxy.
	 */
	private static ?string $user_ip = null;

	/**
	 * @var IP|string|null

	 * The alternative IP of a user. Typically filled with $_SERVER['REMOTE_ADDR']
	 * Used to check the IP in cases where a reverse proxy supplied us the main ip.
	 */
	private static ?string $user_ip_alternative = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * If the passed string is not a valid IP address, it will be set to ''.
	 *
	 * @param ?string|self $ip The IP address in either string or binary form.
	 * @param bool $clean Perform cleanups to the IP prior to parsing.
	 */
	public function __construct(self|string|null $ip, bool $clean = false)
	{
		if ($ip instanceof self) {
			$ip = (string) $ip;
		}

		// When we are passing user provided input, we should perform cleanups to try and make sense of it.
		if ($clean) {
			$ip = $this->clean($ip);
		}

		// Is it in a valid IPv4 string?
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
			$this->ip = $ip;
		}
		// Is it in a valid IPv6 string?
		elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
			if (str_starts_with($ip, '64:ff9b::')) {
				// Workaround for a PHP bug where NAT64 addresses aren't handled
				// properly when the last 32 bits are in IPv6 notation instead
				// of IPv4 notation. See https://en.wikipedia.org/wiki/NAT64.
				$this->ip = '64:ff9b::' . inet_ntop(hex2bin(substr(bin2hex(inet_pton($ip)), -8)));
			} else {
				// Pack and unpack to ensure it is in standard form.
				$this->ip = inet_ntop(inet_pton($ip));
			}
		}
		// It's either in binary form or it's invalid.
		else {
			$this->ip = (string) @inet_ntop((string) $ip);
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
	public function toBinary(): string|bool
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
	 * Check whether this is an IPv6 containing an IPv4.
	 *
	 * Returns true for IPs matching one of the following patterns:
	 *  - ::ffff:123.123.123.123
	 *  = 64:ff9b::123.123.123.123
	 *
	 * @return bool
	 */
	public function is4in6(): bool
	{
		return $this->isValid(FILTER_FLAG_IPV6) && (str_starts_with($this->ip, '::ffff:') || str_starts_with($this->ip, '64:ff9b::'));
	}

	/**
	 * If ipv4 has been passed inside ipv6, using ::ffff:ipv4 format, pluck the ipv4 out of there.
	 * We'd rather use the real ipv4 for display & for lookups, etc.
	 * Consistently trim before usage. ipv6 is sometimes enclosed in square brackets (to clarify port vs ip).
	 *
	 * @return string $ip
	 */
	public function simplified()
	{
		return $this->is4in6() ? preg_replace('/^.*:(\d+\.\d+\.\d+\.\d+)$/', '$1', (string) $this->ip) : (string) $this->ip;
	}

	/**
	 * Checks if an IP matches an expected localhost IP, for locally hosted proxies.
	 * Note: FILTER_FLAG_GLOBAL_RANGE may be more helpful here (see RFC6890), but it's only supported in PHP 8.2+
	 *
	 */
	public function isPrivate(): bool
	{
		return $this->isValid() && !$this->isValid(FILTER_FLAG_GLOBAL_RANGE);
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
			$host = gethostbyaddr($this->ip);
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
			$host = $this->isValid(FILTER_FLAG_IPV6) ? '[' . $this->ip . ']' : $this->ip;
		}

		// Set it.
		$this->host = $host;

		// Cache it.
		CacheApi::put('hostlookup-' . $this->ip, $this->host, 600);

		// Return it.
		return $this->host;
	}

	/**
	 * Detect if a IP is in a CIDR address.
	 *
	 * @param string $cidr_address CIDR address to verify.
	 * @return bool Whether the IP matches the CIDR.
	 */
	public function matchToCIDR(string $cidr_address): bool
	{
		// Validate the CIDR, skip if bogus
		$addr_split = explode('/', $cidr_address);
		$cidr_network = new self($addr_split[0]);

		if (!$cidr_network->isValid()) {
			return false;
		}
		$cidr_network_packed = inet_pton((string) $cidr_network);
		$ip_address_packed = inet_pton($this->ip);

		// Can't find an ipv4 in ipv6 CIDRs & vice-versa...
		if (\strlen($cidr_network_packed) !== \strlen($ip_address_packed)) {
			return false;
		}

		// Prefix must make sense if provided...
		$bits = \strlen($cidr_network_packed) * 8;
		$single_ip = !isset($addr_split[1]);

		if (!$single_ip) {
			$cidr_subnetmask = (int) $addr_split[1];

			if (!is_numeric($addr_split[1]) || $cidr_subnetmask < 0 || $cidr_subnetmask > $bits) {
				return false;
			}
		}

		// Now build the range to check against...
		if ($single_ip) {
			$cidr_from = $cidr_network_packed;
			$cidr_to = $cidr_network_packed;
		} else {
			$bin_mask = str_repeat('1', $cidr_subnetmask) . str_repeat('0', $bits - $cidr_subnetmask);
			$bin_chunks = str_split($bin_mask, 8);
			$chr_mask = '';

			foreach ($bin_chunks as $chunk) {
				$chr_mask .= \chr(bindec($chunk));
			}
			$cidr_from = $cidr_network_packed & $chr_mask;
			$cidr_to = $cidr_network_packed | ~$chr_mask;
		}

		// strcmp is binary safe...
		return ((strcmp($ip_address_packed, $cidr_from) > -1) && (strcmp($ip_address_packed, $cidr_to) < 1));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience wrapper for constructor.
	 *
	 * This is just syntactical sugar to ease method chaining.
	 *
	 * @param string $ip The IP address in either string or binary form.
	 * @return self The created object.
	 */
	public static function create(string $ip): self
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
	 *    contain the minimum and maximum values possible with those wildcards.
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
	public static function ip2range(string $addr): array
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
		if (!str_contains($addr, '-')) {
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

			$valid_low = filter_var(strtr($range[0], ['*' => $min]), FILTER_VALIDATE_IP) !== false;
			$valid_high = filter_var(strtr($range[1], ['*' => $max]), FILTER_VALIDATE_IP) !== false;

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
					if (!str_contains($octet, '-')) {
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

							while (filter_var(implode($mode, $range[0]), FILTER_VALIDATE_IP) === false && \count($range[0]) < \count($octets)) {
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
			if (\count($range[$key]) > $max_parts) {
				$range[$key] = \array_slice($range[$key], 0, $max_parts);
			}

			// If they're still too short, pad them out.
			while (filter_var(implode($mode, $range[$key]), FILTER_VALIDATE_IP) === false && \count($range[$key]) < $max_parts) {
				if (\in_array('*', $range[$key])) {
					$range[$key] = explode($mode, preg_replace('/\*(?!' . preg_quote($mode . '*') . ')/', '*' . $mode . '*', implode($mode, $range[$key])));
				} else {
					$range[$key][] = '*';
				}
			}

			// Convert to string and replace the wildcards.
			$range[$key] = strtr(implode($mode, $range[$key]), ['*' => $key === 0 ? $min : $max]);
		}

		// Make sure the low one really is lower than the high one.
		usort($range, fn($a, $b) => inet_pton($a) <=> inet_pton($b));

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
	 * @param string|IP $low The low end of the range.
	 * @param string|IP $high The high end of the range.
	 * @return string A string indicating the range.
	 */
	public static function range2ip(string|IP $low, string|IP $high): string
	{
		if (!$low instanceof IP) {
			$low = new IP($low);
		}

		if (!$high instanceof IP) {
			$high = new IP($high);
		}

		if ($low == '255.255.255.255') {
			return 'unknown';
		}

		if ($low == $high) {
			return (string) $low;
		}

		return $low . '-' . $high;
	}

	/**
	 * Determines the IP of the current user.
	 *
	 * Users can use SMF's proxy configs if desired.  Normally, it is suggested folks use
	 * mod_remoteip, which straightens out proxy vs user IPs.  If mod_remoteip is not
	 * available at your host, or doesn't meet your needs, this SMF function may help.
	 *
	 * What it does:
	 * - Checks REMOTE_ADDR exists
	 * - If proxy detection is enabled, parses headers to find a valid header.
	 * - If proxy IP filtering is setup, validates IP is in acceptable ranges
	 * 		- Single IP or CIDR notation allowed.
	 * - If a valid match is found return.
	 */
	public static function getUserIP(): string
	{
		// We already figured it out once.
		if (static::$user_ip !== null) {
			return static::$user_ip;
		}

		if (empty($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] == 'unknown') {
			return '';
		}

		$remote_addr = new self($_SERVER['REMOTE_ADDR']);
		$valid_sender = false;

		// If we haven't specified how to handle Reverse Proxy IP headers, lets do what we always used to do.
		if (!isset(Config::$modSettings['proxy_ip_header'])) {
			Config::$modSettings['proxy_ip_header'] = 'autodetect';
		}

		// Proxy config? Step 1: Check if IP passed is a valid server...
		if (!empty(Config::$modSettings['proxy_ip_servers'])) {
			foreach (explode(',', Config::$modSettings['proxy_ip_servers']) as $proxy) {
				if (
					$proxy == $_SERVER['REMOTE_ADDR']
					|| $remote_addr->matchToCIDR($proxy)
				) {
					$valid_sender = true;
					break;
				}
			}
		}
		// If a list of proxy ip servers has not been provided, we will assume its a valid sender if its from a localhost IP.
		else {
			$valid_sender = $remote_addr->isPrivate();
		}

		// Which headers are we going to check for Reverse Proxy IP headers?
		if (!$valid_sender || Config::$modSettings['proxy_ip_header'] == 'disabled') {
			$reverseIPheaders = [];
		} elseif (Config::$modSettings['proxy_ip_header'] == 'autodetect') {
			$reverseIPheaders = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP'];
		} else {
			$reverseIPheaders = [Config::$modSettings['proxy_ip_header']];
		}

		// Find the user's IP address. (but don't let it give you 'unknown'!)
		foreach ($reverseIPheaders as $proxyIPheader) {
			// Ignore if this is not set.
			if (empty($_SERVER[$proxyIPheader])) {
				continue;
			}

			// Break out each IP in order from first to last, trim it and set as a reference to this object.
			$ips = array_map(
				fn($ip) => new self($ip),
				array_map(
					'trim',
					explode(',', $_SERVER[$proxyIPheader]),
				),
			);

			// Check each IP is a valid public IP.
			// It may be a comma separated list.  We are only interested in the first one, per RFC 7239..
			foreach ($ips as $ip) {
				if ($ip->isValid(FILTER_FLAG_GLOBAL_RANGE)) {
					// If this IP is a IPv4 stuffed into a IPv6, rip out the IPv4.
					static::$user_ip = $ip->simplified();
					break;
				}
			}
		}

		// If checking proxy headers gave us nothing, use the plain remote address.
		static::$user_ip ??= $_SERVER['REMOTE_ADDR'];

		return static::$user_ip;
	}

	/**
	 * Determines the Alternative IP of the current user.
	 *
	 * Returns BAN_CHECK_IP if set, otherwise REMOTE_ADDR.
	 */
	public static function getUserIPAlternative(): string
	{
		return static::$user_ip_alternative ?? $_SERVER['REMOTE_ADDR'] ?? '';
	}

	/**
	 * Set's the alternative IP for the current user.
	 * If nothing is provided, we use $_SERVER['REMOTE_ADDR']
	 * @param ?string $ip
	 */
	public static function setUserIPAlternative(?string $ip = null): void
	{
		static::$user_ip_alternative = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '';
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
		if (!\function_exists('shell_exec')) {
			return '';
		}

		// Macs can use dscacheutil, host, or nslookup, but this is the recommended one.
		if (Sapi::IsOS(Sapi::OS_MAC)) {
			$test = (string) @shell_exec('dscacheutil -q host -a ' . ($this->isValid(FILTER_FLAG_IPV6) ? 'ipv6_address' : 'ip_address') . ' ' . @escapeshellarg($this->ip));

			if (preg_match('~name:\s+([^\s]+)~i', $test, $match)) {
				$host = $match[1];
			}
		}

		// Try the Unix/Linux host command, perhaps?
		if (!isset($host) && !Sapi::isOS(Sapi::OS_WINDOWS)) {
			if (!isset(Config::$modSettings['host_to_dis'])) {
				$test = (string) @shell_exec('host -W ' . max(1, floor($timeout / 1000)) . ' ' . @escapeshellarg($this->ip));
			} else {
				$test = (string) @shell_exec('host ' . @escapeshellarg($this->ip));
			}

			// Did host say it didn't find anything?
			if (str_contains($test, 'not found')) {
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
		if (!isset($host) && (Sapi::isOS(Sapi::OS_WINDOWS) || Sapi::IsOS(Sapi::OS_MAC))) {
			$test = (string) @shell_exec('nslookup -timeout=' . max(1, floor($timeout / 1000)) . ' ' . @escapeshellarg($this->ip));

			if (str_contains($test, 'Non-existent domain')) {
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
	protected function getHostByAddr(int $timeout = 1000): string
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
			$host[] = substr($response, (int) $position + 1, $len[1]);

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
		$query = \sprintf('%02d', mt_rand(0, 99));

		// Request header.
		$query .= "\1\0\0\1\0\0\0\0\0\0";

		// The interesting stuff.
		foreach ($parts as $part) {
			$query .= \chr(\strlen($part)) . $part;
		}

		// And the final bit of the request.
		$query .= "\0\0\x0C\0\1";

		return $query;
	}

	/**
	 * Performs cleanup logic on a string that may be a IP.
	 *
	 * @param string $raw A raw string which may contain a IP
	 * @return string The cleaned value.
	 */
	private function clean($raw): string
	{
		return trim(trim($raw), '[]');
	}
}
