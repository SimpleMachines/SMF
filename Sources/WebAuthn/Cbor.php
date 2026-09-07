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

namespace SMF\WebAuthn;

/**
 * Reads the CBOR data format, described by RFC 8949.
 *
 * Authenticators speak CBOR: an attestation object is one, and so is the public
 * key inside it. Only what those two contain is implemented here, which is a
 * small corner of the format. Anything else throws rather than guessing, since
 * a value we cannot read is a value we must not accept.
 *
 * In particular there is no support for indefinite lengths, floating point
 * numbers or tags, none of which appear in the CTAP2 canonical form that
 * authenticators are required to produce.
 */
class Cbor
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Reads one item.
	 *
	 * @param string $data The encoded bytes.
	 * @param int $offset Where to start, updated to just past what was read.
	 * @throws \SMF\WebAuthn\WebAuthnException If the data cannot be read.
	 * @return mixed The decoded value.
	 */
	public static function decode(string $data, int &$offset = 0): mixed
	{
		$byte = self::takeByte($data, $offset);

		$major = $byte >> 5;
		$argument = $byte & 0x1F;

		// Major type 7 keeps its own meanings in the low bits, so it is read
		// before the length rules that every other type shares.
		if ($major === 7) {
			return match ($argument) {
				20 => false,
				21 => true,
				22, 23 => null,
				default => throw new WebAuthnException('unsupported CBOR simple value ' . $argument),
			};
		}

		$value = self::readArgument($data, $offset, $argument);

		return match ($major) {
			0 => $value,
			1 => -1 - $value,
			2, 3 => self::takeBytes($data, $offset, $value),
			4 => self::decodeArray($data, $offset, $value),
			5 => self::decodeMap($data, $offset, $value),
			// A tag decorates the item that follows it. Nothing here cares what
			// the decoration means, so read the item and hand that back.
			6 => self::decode($data, $offset),
			default => throw new WebAuthnException('unsupported CBOR major type ' . $major),
		};
	}

	/**
	 * Reads one item from the start of a string.
	 *
	 * @param string $data The encoded bytes.
	 * @throws \SMF\WebAuthn\WebAuthnException If the data cannot be read, or if
	 *    anything follows the item.
	 * @return mixed The decoded value.
	 */
	public static function decodeAll(string $data): mixed
	{
		$offset = 0;
		$value = self::decode($data, $offset);

		if ($offset !== \strlen($data)) {
			throw new WebAuthnException('CBOR data has ' . (\strlen($data) - $offset) . ' trailing bytes');
		}

		return $value;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Reads the argument that follows an item's first byte.
	 *
	 * @param string $data The encoded bytes.
	 * @param int $offset Where to start, updated to just past what was read.
	 * @param int $argument The low five bits of the first byte.
	 * @throws \SMF\WebAuthn\WebAuthnException If the argument cannot be read.
	 * @return int The value of the argument.
	 */
	protected static function readArgument(string $data, int &$offset, int $argument): int
	{
		if ($argument < 24) {
			return $argument;
		}

		$length = match ($argument) {
			24 => 1,
			25 => 2,
			26 => 4,
			27 => 8,
			default => throw new WebAuthnException('unsupported CBOR argument ' . $argument),
		};

		$bytes = self::takeBytes($data, $offset, $length);
		$value = 0;

		foreach (str_split($bytes) as $byte) {
			/*
			 * Eight byte lengths are legal and would overflow a signed integer.
			 * Nothing an authenticator sends is anywhere near that big, so it
			 * means the data is not what we think it is.
			 */
			if ($value > (\PHP_INT_MAX >> 8)) {
				throw new WebAuthnException('CBOR value is too large to read');
			}

			$value = ($value << 8) | \ord($byte);
		}

		return $value;
	}

	/**
	 * Reads the given number of items.
	 *
	 * @param string $data The encoded bytes.
	 * @param int $offset Where to start, updated to just past what was read.
	 * @param int $count How many items to read.
	 * @throws \SMF\WebAuthn\WebAuthnException If the items cannot be read.
	 * @return array The decoded values.
	 */
	protected static function decodeArray(string $data, int &$offset, int $count): array
	{
		$items = [];

		for ($i = 0; $i < $count; $i++) {
			$items[] = self::decode($data, $offset);
		}

		return $items;
	}

	/**
	 * Reads the given number of key and value pairs.
	 *
	 * @param string $data The encoded bytes.
	 * @param int $offset Where to start, updated to just past what was read.
	 * @param int $count How many pairs to read.
	 * @throws \SMF\WebAuthn\WebAuthnException If the pairs cannot be read.
	 * @return array The decoded map.
	 */
	protected static function decodeMap(string $data, int &$offset, int $count): array
	{
		$map = [];

		for ($i = 0; $i < $count; $i++) {
			$key = self::decode($data, $offset);

			// COSE keys are negative integers, which is exactly why this cannot
			// be handed to something expecting a list.
			if (!\is_int($key) && !\is_string($key)) {
				throw new WebAuthnException('CBOR map key is not an integer or a string');
			}

			$map[$key] = self::decode($data, $offset);
		}

		return $map;
	}

	/**
	 * Reads a single byte.
	 *
	 * @param string $data The encoded bytes.
	 * @param int $offset Where to read from, updated to just past it.
	 * @throws \SMF\WebAuthn\WebAuthnException If there is nothing there.
	 * @return int The value of the byte.
	 */
	protected static function takeByte(string $data, int &$offset): int
	{
		return \ord(self::takeBytes($data, $offset, 1));
	}

	/**
	 * Reads a run of bytes.
	 *
	 * @param string $data The encoded bytes.
	 * @param int $offset Where to read from, updated to just past them.
	 * @param int $length How many to read.
	 * @throws \SMF\WebAuthn\WebAuthnException If there are not that many left.
	 * @return string The bytes.
	 */
	protected static function takeBytes(string $data, int &$offset, int $length): string
	{
		if ($length < 0 || $offset + $length > \strlen($data)) {
			throw new WebAuthnException('CBOR data ended before it should have');
		}

		$bytes = substr($data, $offset, $length);
		$offset += $length;

		return $bytes;
	}
}
