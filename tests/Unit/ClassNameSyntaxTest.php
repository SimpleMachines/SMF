<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Checks that SMF names its own classes with ::class rather than in a string.
 *
 * A name in a string is not a name PHP ever checks. phplint sees a valid string,
 * php-cs-fixer does not read inside strings, and nothing resolves the name until
 * the moment it is used, which for a background task is a cron run on somebody
 * else's forum. Written as a resolver, the compiler resolves the name against
 * the file's imports, so a misspelling is a name that is simply not there.
 *
 * This only sees a name spelled out in one literal. A name assembled at run time,
 * as the menus do with __NAMESPACE__ . '\\Home::call', is invisible here; the
 * class half of it is not written down for anything to check either.
 */
#[CoversNothing]
class ClassNameSyntaxTest extends TestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * The directories that hold the classes SMF ships.
	 */
	private const ROOTS = [
		'Sources',
		'Themes',
	];

	/**
	 * The files that ship outside those directories.
	 */
	private const ENTRY_POINTS = [
		'index.php',
		'cron.php',
		'SSI.php',
		'proxy.php',
		'subscriptions.php',
	];

	/**
	 * The names that have to stay in a string, and why.
	 *
	 * Keyed by the file, with '/' as the separator whatever the platform uses,
	 * so that moving the code around a file does not invalidate the reason.
	 *
	 * Anything added here needs a comment saying why a resolver cannot express
	 * it. "The class does not exist yet" is not one of them: ::class resolves at
	 * compile time and never asks the autoloader, so it is happy to name a class
	 * that has not been loaded, or one that is never loaded at all.
	 */
	private const ALLOWED = [
		// A namespace prefix, which is never a class.
		'Sources/ActionRouter.php' => [
			'SMF\\Actions',
		],
		// The alias class_alias() is about to create. Writing the name it is
		// creating as a resolver would claim the alias already exists.
		'Sources/Actions/Admin/PackageManager.php' => [
			'SMF\\Actions\\Admin\\PackageManager',
		],
		// Two Unicode helper functions, which only some data files define.
		// Functions have no resolver.
		'Sources/Punycode.php' => [
			'SMF\\Unicode\\idna_maps_deviation',
			'SMF\\Unicode\\idna_maps_not_std3',
		],
	];

	/****************
	 * Public methods
	 ****************/

	public function testEveryClassNameIsWrittenAsAResolverRatherThanAString(): void
	{
		$found = [];

		foreach ($this->shippedFiles() as $file) {
			$relative = $this->relative($file);

			foreach ($this->classNameLiterals($file) as [$line, $literal]) {
				if (\in_array($literal, self::ALLOWED[$relative] ?? [], true)) {
					continue;
				}

				$found[] = $relative . ':' . $line . ' names ' . $literal;
			}
		}

		$this->assertSame([], $found, 'A class name is written as a string. Write it as a ::class resolver, or add it to ' . self::class . '::ALLOWED with the reason it cannot be one.');
	}

	/**
	 * An exception whose reason has gone stale is an exception nobody notices,
	 * so the list is only allowed to name things that are really still there.
	 */
	public function testNothingInTheListOfExceptionsHasGoneStale(): void
	{
		$stale = [];

		foreach (self::ALLOWED as $relative => $literals) {
			$file = $this->boardDir() . DIRECTORY_SEPARATOR . strtr($relative, ['/' => DIRECTORY_SEPARATOR]);

			$present = [];

			if (is_file($file)) {
				foreach ($this->classNameLiterals($file) as [, $literal]) {
					$present[] = $literal;
				}
			}

			foreach ($literals as $literal) {
				if (!\in_array($literal, $present, true)) {
					$stale[] = $relative . ' no longer names ' . $literal;
				}
			}
		}

		$this->assertSame([], $stale, 'The list of names allowed to stay in a string mentions something that is not there any more. Remove the entry.');
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Every PHP file the forum ships, entry points included.
	 *
	 * @return array<int, string>
	 */
	private function shippedFiles(): array
	{
		$files = [];

		foreach (self::ROOTS as $dir) {
			$root = $this->boardDir() . DIRECTORY_SEPARATOR . $dir;

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			);

			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$files[] = $file->getPathname();
				}
			}
		}

		foreach (self::ENTRY_POINTS as $name) {
			$files[] = $this->boardDir() . DIRECTORY_SEPARATOR . $name;
		}

		// So that a failure reads the same way wherever it happens.
		sort($files);

		return $files;
	}

	/**
	 * The class names one file writes down as strings, as [line, name] pairs.
	 *
	 * @param string $file The file to read.
	 * @return array<int, array{int, string}>
	 */
	private function classNameLiterals(string $file): array
	{
		$code = (string) file_get_contents($file);

		// Tokenising sixteen megabytes of source to find a handful of strings is
		// worth avoiding, and the Unicode data files are most of that weight.
		// Every literal we want opens with a quote and reaches a namespace
		// separator within a couple of characters.
		if (preg_match('/[\'"]\\\\{0,2}SMF\\\\/', $code) !== 1) {
			return [];
		}

		$found = [];

		// A docblock saying @var SMF\Utils is not a name the code uses, and
		// there are thousands of those, which is why this reads tokens and not
		// lines.
		foreach (token_get_all($code) as $token) {
			if (!\is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
				continue;
			}

			$literal = $this->unquote($token[1]);

			// Every segment has to be non-empty, which leaves out the bare
			// 'SMF\Tasks\' that TaskRunner and ReportedContent build a name
			// from at run time.
			if (preg_match('/^\\\\?SMF(\\\\[A-Za-z0-9_]+)+(::[A-Za-z0-9_]+)?$/', $literal) !== 1) {
				continue;
			}

			$found[] = [$token[2], ltrim($literal, '\\')];
		}

		return $found;
	}

	/**
	 * The text of a quoted string, with its escapes resolved.
	 *
	 * @param string $token The token, quotes and all.
	 */
	private function unquote(string $token): string
	{
		$body = substr($token, 1, -1);

		// A class name in a double-quoted string is an interpolation waiting to
		// happen, so the codebase writes them single-quoted, where the only two
		// escapes are the quote and the backslash itself.
		return $token[0] === "'"
			? strtr($body, ['\\\\' => '\\', '\\\'' => '\''])
			: stripcslashes($body);
	}

	/**
	 * A path as it would be written in a bug report.
	 *
	 * @param string $file An absolute path inside the checkout.
	 */
	private function relative(string $file): string
	{
		return strtr(substr($file, \strlen($this->boardDir()) + 1), [DIRECTORY_SEPARATOR => '/']);
	}

	/**
	 * The root of the checkout, resolved so the walked paths start with it.
	 */
	private function boardDir(): string
	{
		return (string) realpath(TESTS_BOARDDIR);
	}
}
