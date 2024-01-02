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

/**
 * Represents a security token.
 *
 * Extends \ArrayObject for backward compatibility purposes. Specifically, old
 * code expected $_SESSION['token'][$whatever] to be an array with numeric keys,
 * where the elements were in the order of variable name, hash, time, and value.
 * By extending \ArrayObject and taking care in the constructor, we can maintain
 * that behaviour when a SecurityToken object is handled like an array.
 */
class SecurityToken extends \ArrayObject
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'create' => 'createToken',
			'validate' => 'validateToken',
			'clean' => 'cleanTokens',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * How long (in seconds) a token is good for.
	 */
	public const EXPIRY_TIME = 10800;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The token variable name.
	 */
	public string $var;

	/**
	 * @var string
	 *
	 * The token value.
	 */
	public string $val;

	/**
	 * @var int
	 *
	 * The time when the token was created.
	 */
	public int $time;

	/**
	 * @var string
	 *
	 * The hashed value for the token.
	 */
	public string $hash;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// Var always needs to start with a letter because it is used in the URL params.
		$this->var = dechex(random_int(0xA000000000, 0xFFFFFFFFFF));

		// Just a nice random value.
		$this->val = bin2hex(random_bytes(16));

		$this->hash = self::getHash($this->val);

		$this->time = time();

		// This is the backward compatibility part, so that the named properties
		// can be accessed as the numeric keys of an array by any old mods that
		// expect that deprecated format.
		parent::__construct([$this->var, $this->hash, $this->time, $this->val]);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Lets give you a token of our appreciation.
	 *
	 * Sets $_SESSION['token'][$type . '-' . $action] to a new instance of this
	 * class.
	 *
	 * Sets Utils::$context[$action . '_token_var'] to the the $var property of
	 * the token instance, and Utils::$context[$action . '_token'] to the $val
	 * property. Also returns that data as an array.
	 *
	 * @param string $action The action to create the token for
	 * @param string $type The type of token ('post', 'get' or 'request')
	 * @return array An array containing the var and value of the token.
	 */
	public static function create(string $action, string $type = 'post'): array
	{
		$_SESSION['token'][$type . '-' . $action] = new self();

		Utils::$context[$action . '_token_var'] = $_SESSION['token'][$type . '-' . $action]->var;
		Utils::$context[$action . '_token'] = $_SESSION['token'][$type . '-' . $action]->val;

		return [
			$action . '_token_var' => $_SESSION['token'][$type . '-' . $action]->var,
			$action . '_token' => $_SESSION['token'][$type . '-' . $action]->val,
		];
	}

	/**
	 * Only patrons with valid tokens can ride this ride.
	 *
	 * @param string $action The action to validate the token for
	 * @param string $type The type of request (get, request, or post)
	 * @param bool $reset Whether to reset the token and display an error if validation fails
	 * @return bool returns whether the validation was successful
	 */
	public static function validate(string $action, string $type = 'post', bool $reset = true): bool
	{
		$type = $type == 'get' || $type == 'request' ? $type : 'post';

		// Validate the token.
		if (
			// Do we have a token in $_SESSION for this type and action?
			isset($_SESSION['token'][$type . '-' . $action])

			// Is the token an instance of the expected class?
			&& ($_SESSION['token'][$type . '-' . $action] instanceof self)

			// Is a corresponding value present in $_POST/$_GET/$_REQUEST?
			&& isset($GLOBALS['_' . strtoupper($type)][$_SESSION['token'][$type . '-' . $action]->var])

			// Is the value correct?
			&& $_SESSION['token'][$type . '-' . $action]->val === $GLOBALS['_' . strtoupper($type)][$_SESSION['token'][$type . '-' . $action]->var]

			// Is the hash correct?
			&& $_SESSION['token'][$type . '-' . $action]->hash === self::getHash($GLOBALS['_' . strtoupper($type)][$_SESSION['token'][$type . '-' . $action]->var])

			// Is it recent enough?
			&& $_SESSION['token'][$type . '-' . $action]->time + self::EXPIRY_TIME >= time()
		) {
			// Delete this token now.
			unset($_SESSION['token'][$type . '-' . $action]);

			return true;
		}

		// Patrons with invalid tokens get the boot.
		if ($reset) {
			// Might as well do some cleanup on this.
			self::clean();

			// I'm back, baby.
			self::create($action, $type);

			ErrorHandler::fatalLang('token_verify_fail', false);
		}

		// Delete this token since it's useless.
		unset($_SESSION['token'][$type . '-' . $action]);

		// Randomly check if we should delete some older tokens.
		if (mt_rand(0, 138) == 23) {
			self::clean();
		}

		return false;
	}

	/**
	 * Removes old, unused tokens from session.
	 *
	 * Defaults to 3 hours before a token is considered expired.
	 * If $complete = true, all tokens will be removed.
	 *
	 * @param bool $complete Whether to remove all tokens or only expired ones.
	 */
	public static function clean(bool $complete = false): void
	{
		// We appreciate cleaning up after yourselves.
		if (empty($_SESSION['token'])) {
			return;
		}

		if ($complete) {
			$_SESSION['token'] = [];

			return;
		}

		// Clean up tokens, trying to give enough time still.
		foreach ($_SESSION['token'] as $key => $token) {
			if (!($token instanceof self) || $token->time + self::EXPIRY_TIME < time()) {
				unset($_SESSION['token'][$key]);
			}
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Gets the hash for a token.
	 *
	 * The generated hash depends on $val and the user's "session check" value, and
	 * the current user agent string. In other words, the token will be valid
	 * only for the current session and in the current browser.
	 *
	 * Note that checking the user agent isn't a security measure, since user
	 * agents are not unique and are easy to spoof. Rather, it's simply a way to
	 * help prevent users from surprising themselves if they switch browsers or
	 * devices while using the same cookies and/or pasting URLs with the session
	 * ID in the URL parameters.
	 *
	 * @param string $val The value for the token.
	 * @return string The hash.
	 */
	protected static function getHash(string $val): string
	{
		return hash_hmac('sha1', $val . (User::$sc ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''), Config::getAuthSecret());
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\SecurityToken::exportStatic')) {
	SecurityToken::exportStatic();
}

?>