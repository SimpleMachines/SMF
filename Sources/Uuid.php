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

declare(strict_types=1);

namespace SMF;

/**
 * Generates, compresses, and expands Univerally Unique Identifers.
 *
 * This class can generate UUIDs of versions 1 through 7.
 *
 * It is also possible to create a new instance of this class from a UUID string
 * of any version via the Uuid::createFromString() method.
 *
 * This class implements the \Stringable interface, so getting the canonical
 * string representation of a UUID is as simple as casting an instance of this
 * class to string.
 *
 * Because the canonical string representation of a UUID requires 36 characters,
 * e.g. 4e917aef-2843-5b3c-8bf5-a858ee6f36bc, which can be quite cumbersome,
 * this class can also compress and expand UUIDs to and from more compact
 * representations for storage and other uses. In particular:
 *
 *  - The Uuid::getBinary() method returns the 128-bit (16 byte) raw binary
 *    representation of a UUID. This form maintains the same sort order as the
 *    full form and is the most space-efficient form possible.
 *
 *  - The Uuid::getShortForm() method returns a customized base 64 encoding of
 *    the binary form of the UUID. This form is 22 bytes long, maintains the
 *    same sort order as the full form, and is URL safe.
 *
 * For convenience, two static methods, Uuid::compress() and Uuid::expand(), are
 * available in order to simplify the process of converting an existing UUID
 * string between the full, short, or binary forms.
 *
 * For the purposes of software applications that use relational databases, the
 * most useful UUID versions are v7 and v5:
 *
 *  - UUIDv7 is ideal for generating permanently stored database keys, because
 *    these UUIDs naturally sort according to their chronological order of
 *    creation. This is the default version when generating a new UUID.
 *
 *  - UUIDv5 is ideal for situations where UUIDs need to be generated on demand
 *    from pre-existing data, but will not be stored permanently. The generation
 *    algorithm for UUIDv5 always produces the same output given the same input,
 *    so these UUIDs can be regenerated any number of times without varying.
 *
 * At the time of writing, the specifications for the different UUID versions
 * are defined in the following documents:
 *
 *  - UUIDv1: RFC 4122
 *  - UUIDv2: DCE 1.1 Authentication and Security Services
 *  - UUIDv3: RFC 4122
 *  - UUIDv4: RFC 4122
 *  - UUIDv5: RFC 4122
 *  - UUIDv6: draft-ietf-uuidrev-rfc4122bis
 *  - UUIDv7: draft-ietf-uuidrev-rfc4122bis
 *  - UUIDv8: draft-ietf-uuidrev-rfc4122bis
 *  - "Nil" UUID: RFC 4122
 *  - "Max" UUID: draft-ietf-uuidrev-rfc4122bis
 *
 * These documents are available at the following URLs:
 *
 * - https://datatracker.ietf.org/doc/rfc4122/
 * - https://pubs.opengroup.org/onlinepubs/9696989899/chap5.htm#tagcjh_08_02_01_01
 * - https://datatracker.ietf.org/doc/draft-ietf-uuidrev-rfc4122bis/
 */
class Uuid implements \Stringable
{
	/**
	 * Default UUID version to create.
	 */
	public const DEFAULT_VERSION = 7;

	/**
	 * UUID versions that this class can generate.
	 *
	 * Versions 0 and 15 refer to the special nil and max UUIDs.
	 */
	public const SUPPORTED_VERSIONS = [0, 1, 2, 3, 4, 5, 6, 7, 15];

	/**
	 * UUID versions that this class will recognize as valid.
	 *
	 * Versions 0 and 15 refer to the special nil and max UUIDs.
	 * Version 8 is for "experimental or vender-specific use cases."
	 */
	public const KNOWN_VERSIONS = [0, 1, 2, 3, 4, 5, 6, 7, 8, 15];

	/**
	 * The special nil UUID.
	 */
	public const NIL_UUID = '00000000-0000-0000-0000-000000000000';

	/**
	 * The special max UUID.
	 */
	public const MAX_UUID = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

	/**
	 * The predefined namespace UUID for fully qualified domain names.
	 */
	public const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

	/**
	 * The predefined namespace UUID for URLs.
	 */
	public const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

	/**
	 * The predefined namespace UUID for ISO Object Identifiers.
	 */
	public const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';

	/**
	 * The predefined namespace UUID for X.500 Distiguishing Names.
	 */
	public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

	/**
	 * Constants used to implement an alternative version of base64 encoding for
	 * compressed UUID strings.
	 */
	public const BASE64_STANDARD = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
	public const BASE64_SORTABLE = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz~ ';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * The generated UUID.
	 */
	protected string $uuid;

	/**
	 * @var int
	 *
	 * The version of this UUID.
	 */
	protected int $version;

	/**
	 * @var float
	 *
	 * The Unix timestamp of this UUID.
	 */
	protected float $timestamp = 0.0;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var string
	 *
	 * Binary form of a namespace UUID used for UUIDv3 and UUIV5.
	 *
	 * The default value is the UUIDv5 for the executing script's URL.
	 * See self::setNamespace() for more information.
	 */
	protected static string $namespace;

	/**
	 * @var array
	 *
	 * The previously used timestamps for UUIDv1 and UUIDv6.
	 */
	protected static array $prev_timestamps = [
		1 => [],
		6 => [],
	];

	/**
	 * @var array
	 *
	 * The "clock sequence" values used for UUIDv1 and UUIDv6.
	 */
	protected static array $clock_seq = [];

	/**
	 * @var string
	 *
	 * The "node ID" value used in UUIDv1 and UUIDv6.
	 */
	protected static string $node;

	/**
	 * @var object
	 *
	 * A \DateTimeZone object for UTC.
	 */
	protected static \DateTimeZone $utc;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * Handling of the $input parameter varies depending on the $verion:
	 *  - For v3 and v5, $input must be a string or \Stringable object to hash.
	 *  - For v1, v6, and v7, $input can be a Unix timestamp, a parsable
	 *    date string, a \DateTimeInterface object, or null to use current time.
	 *  - For v2, $input must be an array containing a 'domain' element and
	 *    optional 'id' and 'timestamp' elements. (See $this->getHexV2() for
	 *    more info.)
	 *  - Otherwise, $input is ignored.
	 *
	 * In general, using an arbitrary timestamp to create a time-based UUID is
	 * discouraged, because the timestamp is normally intended to refer to the
	 * moment when the UUID was generated. However, there are some situations in
	 * which UUIDs may need to be created from arbitrary timestamps in order to
	 * preserve or enforce a particular position in a sorting sequence, so the
	 * ability to do so is available.
	 *
	 * @param int $version The UUID version to create.
	 * @param mixed $input Input for the UUID generator, if applicable.
	 */
	public function __construct(?int $version = null, mixed $input = null)
	{
		// Determine the version to use.
		$this->version = $version ?? self::DEFAULT_VERSION;

		if (!in_array($this->version, self::SUPPORTED_VERSIONS)) {
			Lang::load('Errors', Lang::$default);
			trigger_error(sprintf(Lang::$txt['uuid_unsupported_version'], $this->version), E_USER_WARNING);
			$this->version = self::DEFAULT_VERSION;
		}

		// Check the input.
		switch (gettype($input)) {
			// Convert supported object types to strings.
			case 'object':
				if ($input instanceof \DateTimeInterface) {
					$input = $input->format('Y-m-d H:i:s.u e');
				} elseif ($input instanceof \Stringable) {
					$input = (string) $input;
				} else {
					$input = null;
				}
				break;

			// UUIDv2 wants an array, but nothing else does.
			case 'array':
				$input = $this->version !== 2 ? reset($input) : $input;
				break;

			// Expected types.
			case 'string':
			case 'integer':
			case 'double':
				break;

			// Unexpected types.
			default:
				$input = null;
				break;
		}

		if (in_array($this->version, [3, 5]) && !isset($input)) {
			Lang::load('Errors', Lang::$default);
			trigger_error(sprintf(Lang::$txt['uuid_requires_string_input'], $this->version), E_USER_WARNING);
			$this->version = 0;
		}

		// Generate hexadecimal value.
		switch ($this->version) {
			case 1:
				$hex = $this->getHexV1($input);
				break;

			case 2:
				$hex = $this->getHexV2((array) $input);
				break;

			case 3:
				$hex = $this->getHexV3($input);
				break;

			case 4:
				$hex = $this->getHexV4();
				break;

			case 5:
				$hex = $this->getHexV5($input);
				break;

			case 6:
				$hex = $this->getHexV6($input);
				break;

			case 7:
				$hex = $this->getHexV7($input);
				break;

			case 15:
				$hex = 'ffffffffffffffffffffffffffffffff';
				break;

			// 0 or unknown.
			default:
				$hex = '00000000000000000000000000000000';
				break;
		}

		// Format in full form.
		// Special checks for nil and max in case of errors during generation.
		switch ($hex) {
			case '00000000000000000000000000000000':
				$this->version = 0;
				$this->uuid = self::NIL_UUID;
				break;

			case 'ffffffffffffffffffffffffffffffff':
				$this->version = 15;
				$this->uuid = self::MAX_UUID;
				break;

			default:
				$this->uuid = implode('-', [
					substr($hex, 0, 8),
					substr($hex, 8, 4),
					dechex($this->version) . substr($hex, 13, 3),
					dechex(hexdec(substr($hex, 16, 4)) & 0x3fff | 0x8000),
					substr($hex, 20, 12),
				]);
				break;
		}
	}

	/**
	 * Returns the version of this UUID.
	 *
	 * @return int The version of this UUID.
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * Returns a binary representation of the UUID.
	 *
	 * @return string 16-byte binary string.
	 */
	public function getBinary(): string
	{
		return hex2bin(str_replace('-', '', $this->uuid));
	}

	/**
	 * Compresses $this->uuid to a 22-character string.
	 *
	 * This short form is URL-safe and maintains the same ASCII sort order as
	 * the original UUID string.
	 *
	 * @return string The short form of the UUID.
	 */
	public function getShortForm(): string
	{
		return rtrim(strtr(base64_encode($this->getBinary()), self::BASE64_STANDARD, self::BASE64_SORTABLE));
	}

	/**
	 * Returns the string representation of the generated UUID.
	 *
	 * @return string The UUID.
	 */
	public function __toString(): string
	{
		return $this->uuid;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Creates a new instance of this class.
	 *
	 * This is just syntactical sugar to simplify method chaining and procedural
	 * coding styles, much like `date_create()` does for `new \DateTime()`.
	 *
	 * @param int $version The UUID version to create.
	 * @param mixed $input Input for the UUID generator, if applicable.
	 * @return Uuid A new Uuid object.
	 */
	public static function create(?int $version = null, mixed $input = null): Uuid
	{
		return new self($version, $input);
	}

	/**
	 * Creates a instance of this class from an existing UUID string.
	 *
	 * If the input UUID string is invalid, behaviour depends on the $strict
	 * parameter:
	 *  - If $strict is false, a warning error will be triggered and an
	 *    instance of this class for the nil UUID will be created.
	 *  - If $strict is true, a fatal error will be triggered.
	 *
	 * @param \Stringable|string $input A UUID string. May be compressed or
	 *    uncompressed.
	 * @param bool $strict If set to true, invalid input causes a fatal error.
	 * @return Uuid A Uuid object.
	 */
	public static function createFromString(\Stringable|string $input, bool $strict = false): Uuid
	{
		if ($input instanceof self) {
			return $input;
		}

		// Binary format is 16 bytes long.
		// Short format is 22 bytes long.
		// Full format is 32 bytes long, once extraneous characters are removed.
		if (strlen($input) === 16) {
			$hex = bin2hex($input);
		} elseif (strlen($input) === 22 && strspn($input, self::BASE64_SORTABLE) === 22) {
			$hex = bin2hex(base64_decode(strtr($input, self::BASE64_SORTABLE, self::BASE64_STANDARD), true));
		} elseif (strspn(str_replace(['{', '-', '}'], '', $input), '0123456789ABCDEFabcdef') === 32) {
			$hex = strtolower(str_replace(['{', '-', '}'], '', $input));
		} else {
			Lang::load('Errors', $strict ? Lang::$default : '');
			trigger_error(sprintf(Lang::$txt['uuid_invalid_string'], $input), $strict ? E_USER_ERROR : E_USER_WARNING);

			$hex = '00000000000000000000000000000000';
		}

		// Validate the version.
		$version = hexdec(substr($hex, 12, 1));

		if (
			// Unknown version.
			!in_array($version, self::KNOWN_VERSIONS)
			// Version can be 0 only in Nil UUID.
			|| ($version === 0 && $hex !== '00000000000000000000000000000000')
			// Version can be 15 only in Max UUID.
			|| ($version === 15 && $hex !== 'ffffffffffffffffffffffffffffffff')
		) {
			Lang::load('Errors', $strict ? Lang::$default : '');
			trigger_error(sprintf(Lang::$txt['uuid_invalid_string'], $input), $strict ? E_USER_ERROR : E_USER_WARNING);

			$hex = '00000000000000000000000000000000';
			$version = 0;
		}

		$obj = new self();
		$obj->version = $version;
		$obj->uuid = implode('-', [
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			substr($hex, 12, 4),
			substr($hex, 16, 4),
			substr($hex, 20, 12),
		]);

		return $obj;
	}

	/**
	 * Convenience method to get the binary or short form of a UUID string.
	 *
	 * @param \Stringable|string $input A UUID string. May be compressed or
	 *    uncompressed.
	 * @param bool $to_base64 If true, compress to short form. Default: false.
	 * @return string The short form of the UUID string.
	 */
	public static function compress(\Stringable|string $input, bool $to_base64 = false): string
	{
		$uuid = self::createFromString($input);

		return $to_base64 ? $uuid->getShortForm() : $uuid->getBinary();
	}

	/**
	 * Convenience method to get the full form of a UUID string.
	 *
	 * @param \Stringable|string $input A UUID string. May be compressed or
	 *    uncompressed.
	 * @return string The full form of the input.
	 */
	public static function expand(\Stringable|string $input): string
	{
		return self::createFromString($input)->uuid;
	}

	/**
	 * Returns the fully expanded value of self::$namespace.
	 *
	 * @return string A UUID string.
	 */
	public static function getNamespace(): string
	{
		self::setNamespace();

		$hex = bin2hex(self::$namespace);

		return implode('-', [
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			substr($hex, 12, 4),
			substr($hex, 16, 4),
			substr($hex, 20, 12),
		]);
	}

	/**
	 * Sets self::$namespace to the binary form of a UUID.
	 *
	 * If $ns is false and self::$namespace has not yet been set, a default
	 * namespace UUID will be generated automatically.
	 *
	 * If $ns is a valid UUID string, that string will be used as the namespace
	 * UUID. A fatal error will be triggered if the string isn't a valid UUID.
	 *
	 * If $ns is true, any existing value of self::$namespace will be replaced
	 * with the default value. This is helpful if you need to reset the value of
	 * self::$namespace after temporarily using a custom namespace.
	 *
	 * The default namespace UUID is the UUIDv5 for Config::$scripturl.
	 *
	 * See RFC 4122, section 4.3.
	 *
	 * @param \Stringable|string|bool $ns Either a valid UUID, true to forcibly
	 *    reset to the automatically generated default value, or false to use
	 *    the current value (which will be set to the default if undefined).
	 *    Default: false.
	 */
	public static function setNamespace(\Stringable|string|bool $ns = false): void
	{
		// Manually supplied namespace.
		if (is_string($ns) || $ns instanceof \Stringable) {
			self::$namespace = self::createFromString($ns, true)->getBinary();

			return;
		}

		// It's already set and we aren't resetting, so we're done.
		if (isset(self::$namespace) && !$ns) {
			return;
		}

		// If a UUID for this forum already exists, use that.
		if (isset(Config::$modSettings['forum_uuid'])) {
			$forum_uuid = self::createFromString(Config::$modSettings['forum_uuid']);

			// Check that Config::$modSettings['forum_uuid'] is valid.
			if ((string) $forum_uuid === Config::$modSettings['forum_uuid']) {
				// It's good, so use it.
				self::$namespace = $forum_uuid->getBinary();

				return;
			}
		}

		// Temporarily set self::$namespace to the binary form of the predefined
		// namespace UUID for URLs. (See RFC 4122, appendix C.)
		self::$namespace = hex2bin(str_replace('-', '', self::NAMESPACE_URL));

		// Set self::$namespace to the binary UUIDv5 for Config::$scripturl.
		self::$namespace = self::create(5, Config::$scripturl)->getBinary();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * UUIDv1: Time-based (but not time-sortable) UUID version.
	 *
	 * The 60-bit timestamp counts 100-nanosecond intervals since Oct 15, 1582,
	 * at 0:00:00 UTC (the date when the Gregorian calendar went into effect).
	 * The maximum date is Jun 18, 5623, at 21:21:00.6846975 UTC. (Note: In the
	 * introduction section of RFC 4122, the maximum date is stated to be
	 * "around A.D. 3400" but this appears to be errata. It would be true if the
	 * timestamp were a signed integer, but in fact the timestamp is unsigned.)
	 *
	 * Uniqueness is ensured by appending a "clock sequence" and a "node ID" to
	 * the timestamp. The clock sequence is a randomly initialized value that
	 * can be incremented or re-randomized whenever necessary. The node ID can
	 * either be a value that is already guaranteed to be unique (typically the
	 * network card's MAC address) or a random value. In this implementation,
	 * both values are initialized with random values each time the script runs.
	 *
	 * @param \Stringable|string|int|float|null $input Timestamp or date string.
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV1(\Stringable|string|int|float|null $input): string
	{
		$this->setTimestamp($input);
		$parts = $this->getGregTimeParts();

		// Date out of range? Bail out.
		if ($this->version != 1) {
			return str_replace('-', '', $this->version === 15 ? self::MAX_UUID : self::NIL_UUID);
		}

		return $parts['time_low'] . $parts['time_mid'] . '0' . $parts['time_high'] . $parts['clock_seq'] . $parts['node'];
	}

	/**
	 * UUIDv2: DCE security version. Suitable only for specific purposes and is
	 * rarely used.
	 *
	 * RFC 4122 does not describe this version. It just reserves UUIDv2 for
	 * "DCE Security version." Instead the specification for UUIDv2 can be found
	 * in the DCE 1.1 Authentication and Security Services specification.
	 *
	 * The purpose of UUIDv2 is to embed information not only about where and
	 * when the UUID was created (via the node ID and timestamp), but also by
	 * whom it was created. This is accomplished by including a user, group, or
	 * organization ID and a type indicator (via a local domain ID and a local
	 * domain type indicator). This ability to know who created a UUID was
	 * apparently helpful for situations where a system needed to perform
	 * security checks related to the UUID value. For general purposes, however,
	 * this ability is not useful, and the trade-offs required to enable it
	 * are highly problematic.
	 *
	 * The most significant problem with UUIDv2 is its extremely high collision
	 * rate. For any given combination of node, local domain type, and local
	 * domain identifier, it can only produce 64 UUIDs every 7 minutes.
	 *
	 * This implementation uses random node IDs rather than real MAC addresses.
	 * This reduces the risk of UUIDv2 collisions occurring at a single site.
	 * Nevertheless, the collision risk remains high in global space.
	 *
	 * Another problem is that DEC 1.1's specifications only describe the case
	 * where a UUIDv2 is generated on a POSIX system, and do not give guidance
	 * about what to do on non-POSIX systems. In particular, UUIDv2 tries to
	 * encode the user ID and/or group ID of the user who created the UUID, but
	 * these concepts may not be defined or available on non-POSIX systems.
	 * Instead, the meaning of all local domain types and local domain IDs is
	 * left undefined by the specification for non-POSIX systems.
	 *
	 * If $input['id'] is set, it will be used as the local domain ID. If it is
	 * not set, the local domain ID will be determined based on the value of
	 * $input['domain']:
	 *
	 *  - If 'domain' is 0, the ID will be the current user's ID number.
	 *  - If 'domain' is 1, the ID will be the current user's group ID number.
	 *  - If 'domain' is 2, the ID will be an organization ID. In this
	 *    implementation, the organization ID is derived from self::$namespace.
	 *
	 * If cross-platform support is desirable, then scripts generating UUIDv2s
	 * should always provide a value in $input['id'] rather than relying on
	 * automatically determined values. ... Or better yet, don't use UUIDv2.
	 *
	 * @param array $input array
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV2(array $input): string
	{
		$domain = $input['domain'] ?? 0;
		$id = $input['id'] ?? null;
		$timestamp = $input['timestamp'] ?? null;

		if ($domain < 0) {
			$this->version = 0;

			return str_replace('-', '', self::NIL_UUID);
		}

		$this->setTimestamp($timestamp ?? 'now');
		$parts = $this->getGregTimeParts();

		// Date out of range? Bail out.
		if ($this->version != 2) {
			return str_replace('-', '', $this->version === 15 ? self::MAX_UUID : self::NIL_UUID);
		}

		// Try to find the $id. Only fully supported on POSIX systems.
		if (!isset($id)) {
			switch ($domain) {
				// Told to use the user ID.
				case 0:
					// On POSIX systems, use ID of the user executing the script.
					// On non-POSIX systems, use ID of the user that owns the script.
					$id = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
					break;

				// Told to use the primary group ID.
				case 1:
					if (function_exists('posix_getgid')) {
						// POSIX systems can actually do this.
						$id = posix_getgid();
					} else {
						// On non-POSIX systems, fall back to user ID because
						// getmygid() returns nothing useful on non-POSIX systems.
						Lang::load('Errors', Lang::$default);
						trigger_error(Lang::$txt['uuid_group_non_posix'], E_USER_NOTICE);

						$id = getmyuid();
						$domain = 0;
					}
					break;

				// Told to use organization ID.
				case 2:
					// This site's namespace UUID is suitable here.
					$id = hexdec(substr(self::getNamespace(), 0, 8));
					break;

				// Unknown domain.
				default:
					Lang::load('Errors');
					trigger_error(sprintf(Lang::$txt['uuid_unknown_domain'], $domain), E_USER_ERROR);
					break;
			}
		}

		$id = sprintf('%08x', $id);
		$domain = sprintf('%02x', $domain);

		// Re-randomize the node every time we generate a UUIDv2.
		self::$node = sprintf('%012x', hexdec(bin2hex(random_bytes(6))) | 0x10000000000);

		return $id . $parts['time_mid'] . '0' . $parts['time_high'] . substr($parts['clock_seq'], 0, 2) . $domain . $parts['node'];
	}

	/**
	 * UUIDv3: Creates a UUID for a name within a namespace using an MD5 hash.
	 *
	 * @param string $input The input string.
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV3(string $input): string
	{
		// Ensure self::$namespace is set.
		self::setNamespace();

		// Concat binary namespace UUID with $input, then get the MD5 hash.
		return md5(self::$namespace . $input);
	}

	/**
	 * UUIDv4: Creates a UUID from random data.
	 *
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV4(): string
	{
		return bin2hex(random_bytes(16));
	}

	/**
	 * UUIDv5: Creates a UUID for a name within a namespace using an SHA-1 hash.
	 *
	 * @param string $input The input string.
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV5(string $input): string
	{
		// Ensure self::$namespace is set.
		self::setNamespace();

		// Concat binary namespace UUID with $input, then get the SHA-1 hash.
		return substr(sha1(self::$namespace . $input), 0, 32);
	}

	/**
	 * UUIDv6: Time-sortable UUID version.
	 *
	 * The timestamp component is monotonic and puts the most significant bit
	 * first, so sorting these UUIDs lexically also sorts them chronologically.
	 *
	 * The 60-bit timestamp counts 100-nanosecond intervals since Oct 15, 1582,
	 * at 0:00:00 UTC (the date when the Gregorian calendar went into effect).
	 * The maximum date is Jun 18, 5623, at 21:21:00.6846975 UTC.
	 *
	 * Uniqueness is ensured by appending a "clock sequence" and a "node ID" to
	 * the timestamp. The clock sequence is a randomly initialized value that
	 * can be incremented or re-randomized whenever necessary. The node ID can
	 * either be a value that is already guaranteed to be unique (typically the
	 * network card's MAC address) or a random value. In this implementation,
	 * both values are initialized with random values each time the script runs.
	 *
	 * @param \Stringable|string|int|float|null $input Timestamp or date string.
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV6(\Stringable|string|int|float|null $input): string
	{
		$this->setTimestamp($input);
		$parts = $this->getGregTimeParts();

		// Date out of range? Bail out.
		if ($this->version != 6) {
			return str_replace('-', '', $this->version === 15 ? self::MAX_UUID : self::NIL_UUID);
		}

		return $parts['time_high'] . $parts['time_mid'] . substr($parts['time_low'], 0, -3) . '0' . substr($parts['time_low'], -3) . $parts['clock_seq'] . $parts['node'];
	}

	/**
	 * UUIDv7: Improved time-sortable UUID version.
	 *
	 * The timestamp component is monotonic and puts the most significant bit
	 * first, so sorting these UUIDs lexically also sorts them chronologically.
	 *
	 * The 48-bit timestamp measures milliseconds since the Unix epoch. The
	 * maximum date is Aug 01, 10889, at 05:31:50.655 UTC.
	 *
	 * Uniqueness is ensured by appending 74 random bits to the timestamp.
	 *
	 * @param \Stringable|string|int|float|null $input Timestamp or date string.
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV7(\Stringable|string|int|float|null $input): string
	{
		$this->setTimestamp($input);
		$timestamp = $this->adjustTimestamp();

		// Date out of range? Bail out.
		if ($timestamp < 0) {
			$this->version = 0;

			return str_replace('-', '', self::NIL_UUID);
		}

		if ($timestamp > 281474976710655) {
			$this->version = 15;

			return str_replace('-', '', self::MAX_UUID);
		}

		return sprintf('%012x', $timestamp) . bin2hex(random_bytes(10));
	}

	/**
	 * Helper method for getHexV1 and getHexV6.
	 *
	 * @return array Components for the UUID.
	 */
	protected function getGregTimeParts(): array
	{
		$timestamp = $this->adjustTimestamp();

		// We can't track the clock sequence between executions, so initialize
		// it to a random value each time. See RFC 4122, section 4.1.5.
		if (!isset(self::$clock_seq[$this->version])) {
			self::$clock_seq[$this->version] = bin2hex(random_bytes(2));
		}

		$clock_seq = self::$clock_seq[$this->version];

		// We don't have direct access to the MAC address in PHP, but the spec
		// allows using random data instead, provided that we set the least
		// significant bit of its first octet to 1. See RFC 4122, section 4.5.
		if (!isset(self::$node)) {
			self::$node = sprintf('%012x', hexdec(bin2hex(random_bytes(6))) | 0x10000000000);
		}

		// Is this a duplicate timestamp?
		while (
			isset(self::$prev_timestamps[$this->version][$clock_seq])
			&& in_array($timestamp, self::$prev_timestamps[$this->version][$clock_seq])
		) {
			// First try incrementing the timestamp.
			// Because the spec uses 100-nanosecond intervals, but PHP offers
			// only microseconds, the spec says we can do this to simulate
			// greater precision. See RFC 4122, section 4.2.1.2.
			$temp = $timestamp;

			for ($i = 0; $i < 9; $i++) {
				if (!in_array(++$temp, self::$prev_timestamps[$this->version][$clock_seq])) {
					$timestamp = $temp;
					break 2;
				}
			}

			// All available slots for this microsecond were taken.
			// Increment $clock_seq and try again.
			$clock_seq = hexdec($clock_seq);
			$clock_seq++;
			$clock_seq %= 0x10000;
			self::$clock_seq[$this->version] = $clock_seq = sprintf('%04x', $clock_seq);
		}

		self::$prev_timestamps[$this->version][$clock_seq][] = $timestamp;

		// Date out of range? Bail out.
		if ($timestamp < 0) {
			$this->version = 0;

			return [];
		}

		if ($timestamp > 1152921504606846975) {
			$this->version = 15;

			return [];
		}

		$time_hex = sprintf('%015x', $timestamp);

		return [
			'time_high' => substr($time_hex, 0, 3),
			'time_mid' => substr($time_hex, 3, 4),
			'time_low' => substr($time_hex, 7, 8),
			'clock_seq' => $clock_seq,
			'node' => self::$node,
		];
	}

	/**
	 * Sets $this->timestamp to a microsecond-precision Unix timestamp.
	 *
	 * @param \Stringable|string|int|float $input A timestamp or date string.
	 *    Default: 'now'.
	 */
	protected function setTimestamp(\Stringable|string|int|float|null $input = 'now'): void
	{
		$input = (string) $input;

		if ($input === 'now') {
			$this->timestamp = (float) microtime(true);
		} else {
			$date = @date_create((is_numeric($input) ? '@' : '') . $input);

			if ($date === false) {
				$date = date_create();
			}

			if (!isset(self::$utc)) {
				self::$utc = new \DateTimeZone('UTC');
			}

			$date->setTimezone(self::$utc);

			$this->timestamp = (float) $date->format('U.u');

			unset($date);
		}
	}

	/**
	 * Adjusts a Unix timestamp to meet the needs of the this UUID version.
	 *
	 * @return int A timestamp value appropriate for this UUID version.
	 */
	protected function adjustTimestamp(): int
	{
		$timestamp = $this->timestamp ?? (float) microtime(true);

		switch ($this->version) {
			// For v1, v2, & v6, use epoch of Oct 15, 1582, at midnight UTC, and
			// use 100-nanosecond precision. Since PHP only offers microsecond
			// precision, the last digit will always be 0, but that's fine.
			case 1:
			case 2:
			case 6:
				$timestamp = (int) ($timestamp * 10000000) + 122192928000000000;
				break;

			// For v7, use millisecond precision.
			case 7:
				$timestamp *= 1000;
				break;

			default:
				Lang::load('Errors', Lang::$default);
				trigger_error(sprintf(Lang::$txt['uuid_unsupported_version'], $this->version), E_USER_WARNING);

				return $timestamp;
		}

		$timestamp = (int) $timestamp;

		if ($timestamp < 0) {
			Lang::load('Errors', Lang::$default);
			trigger_error(sprintf(Lang::$txt['uuid_timestamp_out_of_range'], $this->version), E_USER_WARNING);
		}

		return $timestamp;
	}
}

?>