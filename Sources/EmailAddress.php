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

/**
 * A class for manipulating email addresses.
 *
 * In particular, it provides non-destructive methods for safely dealing with
 * Unicode characters and character case conversion in email addresses.
 *
 * The first use case for this class is basic normalization and sanitization
 * of email addresses. Simply creating an instance of this class and then
 * casting it back to a string will normalize the domain part of the email
 * address to the domain name's canonical form. If the $sanitize argument of
 * the constructor is set to true, it will also sanitize the email address by
 * removing any disallowed characters.
 *
 * In order to normalize an email address, make a new instance of this class
 * and then cast it to string, like so:
 *
 *    `$address = (string) new EmailAddress($address);`
 *
 * In order to fully sanitize the email address, set the constructor's $sanitize
 * argument to true, like so:
 *
 *    `$address = (string) new EmailAddress($address, true);`
 *
 * The second use case for this class is dealing with internationalized email
 * addresses as described in RFC 6530. These email addresses can contain
 * non-ASCII characters in both the local part and the domain part. Generally
 * speaking, when sending to an internationalized email addresses, converting
 * the domain part to ASCII using the Punycode algorithm improves the likelihood
 * of successful delivery. Although mail transport agents that fully support the
 * SMTPUTF8 extension (see RFC 6530 and RFC 6531) can handle literal Unicode in
 * domain names, outdated MTAs along the delivery path might not, so converting
 * the domain to ASCII before sending is safest.
 *
 * In order to prepare an email address for sending, make a new instance of
 * this class and then call its sendable() method, like so:
 *
 *    `$address = EmailAddress::create($address)->sendable();`
 *
 * The third use case for this class is to get a casefolded version of an email
 * address, suitable for case-insensitive comparisons. This should only ever be
 * used for internal processing, such as conducting a case-insensitive search.
 *
 * IMPORTANT: THE CASEFOLDED FORM OF AN EMAIL ADDRESS IS NOT THE "CORRECT" FORM.
 * Most email providers treat JDoe@example.com and jdoe@example.com as aliases,
 * but others treat them as separate addresses. Moreover, when the local part of
 * the address contains international characters, casefolding can cause changes
 * to the string that go beyond simply substituting one character with another.
 *
 * In order to get a casefolded version an email address, make a new instance of
 * this class and then call its casefolded() method, like so:
 *
 *    `$address = EmailAddress::create($address)->casefolded();`
 */
class EmailAddress implements \Stringable
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The local part of the email address.
	 */
	public private(set) string $local_part;

	/**
	 * @var string
	 *
	 * The domain part of the email address.
	 */
	public private(set) string $domain_part;

	/**
	 * @var string
	 *
	 * Punycode encoded version of the domain.
	 */
	public private(set) string $ascii_domain_part;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * If $sanitize is true, then any disallowed characters will be stripped
	 * from the address. The set of disallowed characters includes whitespace,
	 * certain ASCII punctuation characters, and anything that fits in the
	 * Unicode "other characters" category (e.g. control characters, private
	 * use characters, etc.).
	 *
	 * If the address is valid (either because it was already valid or because
	 * sanitizing successfully made it valid), then the domain part of the
	 * address will be normalized to the canonical form of the domain name.
	 *
	 * @param string $address The email address string.
	 * @param bool $sanitize Whether to sanitize the address.
	 */
	public function __construct(string $address, bool $sanitize = false)
	{
		// We need this.
		if (!\function_exists('idn_to_ascii')) {
			require_once Sapi::canonicalPath(Config::$sourcedir . '/Subs-Compat.php');
		}

		// Split into local and domain parts.
		[$this->local_part, $this->domain_part] = array_pad(explode('@', $address, 2), 2, '');

		// Sanitize while preserving allowed non-ASCII characters.
		if ($sanitize) {
			// Sanitize the local part using FILTER_SANITIZE_EMAIL.
			$this->local_part = preg_replace_callback(
				'/[^\x00-\x7F\pZ\pC]|%/u',
				fn($matches) => rawurlencode($matches[0]),
				$this->local_part,
			);

			$this->local_part = filter_var($this->local_part, FILTER_SANITIZE_EMAIL);
			$this->local_part = rawurldecode($this->local_part);

			// The domain part is subject to URL character restrictions, which
			// are a superset of the email character restrictions.
			$this->domain_part = preg_replace_callback(
				'/[^\x00-\x7F\pZ\pC]|%/u',
				fn($matches) => rawurlencode($matches[0]),
				$this->domain_part,
			);

			$this->domain_part = filter_var($this->domain_part, FILTER_SANITIZE_URL);
			$this->domain_part = rawurldecode($this->domain_part);
		}

		// Normalize the domain.
		$this->domain_part = Utils::normalize($this->domain_part, 'kc_casefold');

		// Get the Punycode encoded version of the domain.
		$this->ascii_domain_part = !empty($this->domain_part) ? (idn_to_ascii($this->domain_part) ?: $this->domain_part) : $this->domain_part;
	}

	/**
	 * Returns the email address as a string.
	 *
	 * If the input passed to the constructor was not a syntactically valid
	 * email address, this method will return an empty string.
	 *
	 * @return string The email address.
	 */
	public function __toString(): string
	{
		if (!$this->isValid()) {
			return '';
		}

		return $this->local_part . '@' . $this->domain_part;
	}

	/**
	 * Gets a version of this email address with mixed case Unicode in the local
	 * part and lowercase ASCII in the domain part.
	 *
	 * This form should be used when sending an email message to this address.
	 *
	 * This will typically be the same as the default form, but it will differ
	 * if the domain part of the address is an internationalized domain name.
	 *
	 * If the input passed to the constructor was not a syntactically valid
	 * email address, this method will return an empty string.
	 *
	 * @return string The sendable version of the email address.
	 */
	public function sendable(): string
	{
		if (!$this->isValid()) {
			return '';
		}

		return $this->local_part . '@' . $this->ascii_domain_part;
	}

	/**
	 * Gets a version of this email address with casefolded Unicode in the local
	 * part and lowercase ASCII in the domain part.
	 *
	 * This form should only be used when conducting a case-insensitive
	 * comparison. DO NOT TRY TO SEND ANYTHING TO A CASEFOLDED ADDRESS.
	 *
	 * If the input passed to the constructor was not a syntactically valid
	 * email address, this method will return an empty string.
	 *
	 * @return string A casefolded version of the email address.
	 */
	public function casefolded(): string
	{
		if (!$this->isValid()) {
			return '';
		}

		return Utils::convertCase($this->local_part, 'fold') . '@' . $this->ascii_domain_part;
	}

	/**
	 * Checks whether the email address is syntactically valid.
	 *
	 * @param bool $allow_unicode Whether to allow Unicode characters.
	 *    Default: true.
	 * @return bool Whether the email address is syntactically valid.
	 */
	public function isValid(bool $allow_unicode = true): bool
	{
		if (empty($this->local_part) || empty($this->ascii_domain_part)) {
			return false;
		}

		return (bool) filter_var(
			// As of PHP 8.4, FILTER_FLAG_EMAIL_UNICODE still doesn't understand
			// Unicode in domain names. So to avoid spurious errors we must use
			// the ASCII domain name when Unicode is allowed. But if Unicode is
			// not allowed then we should check the version of the domain name
			// that might contain Unicode. This seems backwards, but it's true.
			$this->local_part . '@' . ($allow_unicode ? $this->ascii_domain_part : $this->domain_part),
			FILTER_VALIDATE_EMAIL,
			$allow_unicode ? FILTER_FLAG_EMAIL_UNICODE : 0,
		);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience wrapper for constructor.
	 *
	 * @param string $address The email address string.
	 * @param bool $sanitize Whether to sanitize the address.
	 * @return self An instance of this class.
	 */
	public static function create(string $address, bool $sanitize = false): self
	{
		return new self($address, $sanitize);
	}
}
