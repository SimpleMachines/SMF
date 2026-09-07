<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Checks every class name that SMF writes down as a string.
 *
 * A name in a string is not a name PHP ever checks. It is handed to the
 * autoloader at the moment it is used, which for a background task is a cron run
 * on somebody else's forum: TaskRunner::execute() logs the class it could not
 * find, drops the row from the queue and carries on, so the work simply never
 * happens and the only trace is a line in the error log.
 *
 * The names are compared against a set built by walking the tree rather than with
 * class_exists(). Composer's PSR-4 loader ends at file_exists(), which is
 * case-insensitive on Windows and macOS, so class_exists() answers a different
 * question depending on the machine and a misspelling can pass here while failing
 * in production. A directory entry stores the case it was created with on every
 * platform, so a set built from the file names is exact everywhere and an
 * ordinary string comparison against it is the check we actually want.
 */
#[CoversNothing]
class ClassReferenceTest extends TestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * The PSR-4 roots from composer.json, as namespace prefix => directory.
	 */
	private const ROOTS = [
		'SMF\\' => 'Sources',
		'SMF\\Themes\\' => 'Themes',
	];

	/**
	 * The files that ship outside the PSR-4 roots.
	 */
	private const ENTRY_POINTS = [
		'index.php',
		'cron.php',
		'SSI.php',
		'proxy.php',
		'subscriptions.php',
	];

	/**
	 * Functions whose whole purpose is to name something that may not be there.
	 *
	 * Config and IntegrationHook ask about classes the bootstrap may not have
	 * reached yet, Punycode asks about two Unicode helpers that only some data
	 * files define, and proxy.php keeps one for the benefit of old mods. A name
	 * inside one of these is a question rather than a claim, so it is left alone.
	 */
	private const PROBES = [
		'class_exists',
		'enum_exists',
		'function_exists',
		'interface_exists',
		'is_callable',
		'method_exists',
		'trait_exists',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array<string, array<int, string>> The PHP files under each root.
	 */
	private array $files = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * The regression this pins is a background task queued as
	 * 'SMF\Tasks\FetchSMfiles' by the upgrader, where the class is FetchSMFiles.
	 * The wrong spelling is the scheduled task's own name, fetchSMfiles, which
	 * sits two lines from the right answer in TaskRunner::$scheduled_tasks.
	 */
	public function testEveryClassNameWrittenAsAStringNamesSomethingReal(): void
	{
		$known = $this->shippedNames();
		$unresolved = [];

		foreach ($this->shippedFiles() as $file) {
			foreach ($this->classNameLiterals($file) as [$line, $literal]) {
				[$class, $method] = array_pad(explode('::', $literal, 2), 2, null);

				if (!isset($known[$class])) {
					$unresolved[] = $this->relative($file) . ':' . $line . ' names ' . $literal;

					continue;
				}

				// Only the class half is case-sensitive, because only it turns
				// into a file path. Method names are not, and they resolve
				// through traits and parents, so PHP is the thing to ask: the
				// call() on Actions\Groups comes from ActionTrait.
				if ($method !== null && !method_exists($class, $method)) {
					$unresolved[] = $this->relative($file) . ':' . $line . ' names ' . $literal;
				}
			}
		}

		$this->assertSame([], $unresolved, 'A class name written as a string names a class, a namespace or a method that does not exist.');
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Every class and namespace the forum ships, as name => true.
	 *
	 * @return array<string, bool>
	 */
	private function shippedNames(): array
	{
		$names = [];

		foreach (self::ROOTS as $prefix => $dir) {
			$root = $this->boardDir() . DIRECTORY_SEPARATOR . $dir;

			foreach ($this->phpFilesIn($root) as $file) {
				$parts = explode('/', trim(strtr(substr($file, \strlen($root)), ['\\' => '/']), '/'));
				$name = substr((string) array_pop($parts), 0, -4);

				// index.php is a directory stub, and a file PHP could not use
				// the name of as an identifier is not holding a class either.
				if ($name === 'index' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
					continue;
				}

				$parts[] = $name;
				$fqcn = $prefix . implode('\\', $parts);
				$names[$fqcn] = true;

				// The namespaces along the way are names too. ActionRouter asks
				// whether its own class sits under 'SMF\Actions', which is a
				// namespace and never a class.
				while (($at = strrpos($fqcn, '\\')) !== false) {
					$fqcn = substr($fqcn, 0, $at);
					$names[$fqcn] = true;
				}
			}
		}

		return $names;
	}

	/**
	 * Every PHP file the forum ships, entry points included.
	 *
	 * @return array<int, string>
	 */
	private function shippedFiles(): array
	{
		$files = [];

		foreach (self::ROOTS as $dir) {
			$files = array_merge($files, $this->phpFilesIn($this->boardDir() . DIRECTORY_SEPARATOR . $dir));
		}

		foreach (self::ENTRY_POINTS as $name) {
			$files[] = $this->boardDir() . DIRECTORY_SEPARATOR . $name;
		}

		return $files;
	}

	/**
	 * The PHP files under a directory, sorted so a failure reads the same way
	 * wherever it happens.
	 *
	 * Both the set of names and the set of files to search are built from these
	 * walks, and walking a bind-mounted checkout is slow enough to be worth doing
	 * once per root rather than once per caller.
	 *
	 * @param string $root The directory to walk.
	 * @return array<int, string>
	 */
	private function phpFilesIn(string $root): array
	{
		if (isset($this->files[$root])) {
			return $this->files[$root];
		}

		$files = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$files[] = $file->getPathname();
			}
		}

		sort($files);

		return $this->files[$root] = $files;
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

		// Tokenising sixteen megabytes of source to find eighty strings is worth
		// avoiding, and the Unicode data files are most of that weight. Every
		// literal we want opens with a quote and reaches a namespace separator
		// within a couple of characters.
		if (preg_match('/[\'"]\\\\{0,2}SMF\\\\/', $code) !== 1) {
			return [];
		}

		$found = [];
		$tokens = token_get_all($code);

		// A docblock saying @var SMF\Utils is not a reference, and there are
		// thousands of those, which is why this reads tokens and not lines.
		foreach ($tokens as $at => $token) {
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

			if ($this->isProbeArgument($tokens, $at)) {
				continue;
			}

			$found[] = [$token[2], ltrim($literal, '\\')];
		}

		return $found;
	}

	/**
	 * Whether a string token is the argument of an existence probe.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens The whole file.
	 * @param int $at Which token to look behind.
	 */
	private function isProbeArgument(array $tokens, int $at): bool
	{
		$open = $this->significantBefore($tokens, $at);

		if ($open === null || $tokens[$open] !== '(') {
			return false;
		}

		$callee = $this->significantBefore($tokens, $open);
		$name = $callee === null ? null : $tokens[$callee];

		return \is_array($name)
			&& \in_array($name[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)
			&& \in_array(strtolower(ltrim($name[1], '\\')), self::PROBES, true);
	}

	/**
	 * The token before this one that is neither whitespace nor a comment.
	 *
	 * @param array<int, array{int, string, int}|string> $tokens The whole file.
	 * @param int $at Which token to look behind.
	 */
	private function significantBefore(array $tokens, int $at): ?int
	{
		for ($before = $at - 1; $before >= 0; $before--) {
			$token = $tokens[$before];

			if (!\is_array($token) || !\in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				return $before;
			}
		}

		return null;
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
		return strtr(substr($file, \strlen($this->boardDir()) + 1), ['\\' => '/']);
	}

	/**
	 * The root of the checkout, resolved so the walked paths start with it.
	 */
	private function boardDir(): string
	{
		return (string) realpath(TESTS_BOARDDIR);
	}
}
