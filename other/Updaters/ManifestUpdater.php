<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
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

namespace SMF\other\Updaters;

use SMF\Config;

/**
 * Builds a manifest of files that must exist for SMF to function.
 *
 * The manifest is saved to Sources/Maintenance/manifest.json.
 */
class ManifestUpdater extends UpdaterBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $commit_msg = 'Updates file manifest';

	/**
	 * @var array
	 *
	 * Relative paths to individual files and directories that must exist for
	 * SMF to function.
	 *
	 * Directories (but not the directory contents) listed in the "required"
	 * element of composer.json will be added to this list at runtime.
	 *
	 * Files in this list are always included in the manifest and are not
	 * subject to filtering.
	 */
	public array $files = [
		'attachments/.htaccess',
		'attachments/index.php',
		'avatars/blank.png',
		'avatars/default.png',
		'avatars/index.php',
		'cron.php',
		'custom_avatar/blank.png',
		'custom_avatar/index.php',
		'favicon.ico',
		'index.php',
		'proxy.php',
		'Smileys/index.php',
		'SSI.php',
		'subscriptions.php',
		'vendor/autoload.php',
		'vendor/composer',
		'vendor/index.php',
	];

	/**
	 * @var array
	 *
	 * Relative paths to directories containing more files that must exist for
	 * SMF to function.
	 *
	 * All files in these directories will be added to the manifest, except for
	 * any filtered out by the ignore filters.
	 *
	 * The cache directory is intentionally not included in this list because
	 * SMF will dynamically create it when necessary.
	 */
	public array $dirs = [
		'Languages',
		'Packages',
		'Smileys',
		'Sources',
		'Themes',
	];

	/**
	 * @var array
	 *
	 * Regex patterns for paths to ignore (unless overridden by $not_ignored).
	 *
	 * This will be populated from the .gitignore file.
	 */
	public array $ignored = [];

	/**
	 * @var array
	 *
	 * Regex patterns for paths that should not be ignored (unless overridden by
	 * $always_ignored).
	 *
	 * This will be populated from the .gitignore file.
	 */
	public array $not_ignored = [];

	/**
	 * @var array
	 *
	 * Regex patterns for paths to always ignore, no matter what.
	 *
	 * If the .git/info/excluded file exists, it will be used to add additional
	 * patterns to this list.
	 */
	public array $always_ignored = [
		// Always ignore manifest.json.
		'~^Sources/Maintenance/manifest.json$~',
		// Always ignore dot files and directories except .htaccess.
		'~(?<=^|/)\.(?!htaccess$)~',
	];

	/**
	 * @var array
	 *
	 * Regex patterns for files that must exist but don't need to have a
	 * matching checksum.
	 */
	public array $no_checksum = [
		'~(?<=^|/)agreement\.txt$~',
		'~\.(gdf|ttf|bmp|ico|jpg|jpeg|gif|png|svg|webp|wav)$~',
		'~^vendor/(?!index\.php)~',
	];

	/**
	 * @var array
	 *
	 * Manifest data, divided into sections using '<major>.<minor>' version
	 * number syntax.
	 *
	 * Each section is in turn an array where each key is a file path that must
	 * exist and each value is either the MD5 hash of the file or just true for
	 * files that are allowed to have varying content.
	 */
	public array $manifest = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct(?string $new_branch = null)
	{
		parent::__construct($new_branch);

		// Add required directories in ./vendor to $files.
		$composer_json = json_decode(file_get_contents(Config::$boarddir . '/composer.json'), true);

		$this->files = array_merge(
			$this->files,
			array_map(
				fn($package) => 'vendor/' . $package,
				array_keys($composer_json['require']),
			),
		);

		// Process .gitignore into regular expressions.
		foreach (file(Config::$boarddir . '/.gitignore') as $line) {
			if (str_starts_with($line, '#') || trim($line) === '') {
				continue;
			}

			if (str_starts_with($line, '!')) {
				$line = substr($line, 1);
				$var = 'not_ignored';
			} else {
				$var = 'ignored';
			}

			$line = rtrim($line);

			$this->{$var}[] = '~' . (!str_starts_with($line, '/') ? '\b' : '') . strtr($line, ['.' => '\\.', '?' => '.', '*' => '.*', '~' => '\\~']) . (preg_match('/\b$/', $line) ? '\b' : '') . '~';
		}

		// Process .git/info/exclude into regular expressions.
		if (is_file(Config::$boarddir . '/.git/info/exclude')) {
			foreach (file(Config::$boarddir . '/.git/info/exclude') as $line) {
				if (str_starts_with($line, '#') || trim($line) === '') {
					continue;
				}

				if (str_starts_with($line, '!')) {
					$line = substr($line, 1);
					$var = 'not_ignored';
				} else {
					$var = 'always_ignored';
				}

				$line = rtrim($line);

				$this->{$var}[] = '~' . (!str_starts_with($line, '/') ? '\b' : '') . strtr($line, ['.' => '\\.', '?' => '.', '*' => '.*', '~' => '\\~']) . (preg_match('/\b$/', $line) ? '\b' : '') . '~';
			}
		}
	}

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		if (php_sapi_name() === 'cli') {
			echo 'Updating manifest.json...', PHP_EOL;
		}

		$this->checkoutNewBranch();

		$this->build();
		$this->write();

		if (php_sapi_name() === 'cli') {
			echo 'Done.', !$this->hasChanged() ? ' No changes were made.' : '', PHP_EOL;
		}

		$this->ready_to_commit = true;

		$this->removeUselessBranch();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Builds the manifest data.
	 */
	private function build(): void
	{
		// Add the individual files to the manifest.
		foreach ($this->files as $path) {
			if (($real_path = realpath(Config::$boarddir . '/' . $path)) === false) {
				throw new \Exception($path . ' does not exist');
			}

			if (is_dir($path)) {
				$this->manifest[$this->chooseSection($path)][$path] = true;
				continue;
			}

			foreach ($this->no_checksum as $pattern) {
				if (preg_match($pattern, $path)) {
					$this->manifest[$this->chooseSection($path)][$path] = true;
					continue 2;
				}
			}

			$this->manifest[$this->chooseSection($path)][$path] = md5_file($real_path);
		}

		// Add the contents of the directories to the manifest.
		foreach ($this->dirs as $path) {
			if (($real_path = realpath(Config::$boarddir . '/' . $path)) === false) {
				throw new \Exception($path . ' does not exist.');
			}

			foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real_path)) as $f) {
				if (!$f->isFile()) {
					continue;
				}

				$pathname = strtr($f->getPathname(), DIRECTORY_SEPARATOR, '/');

				$ignore = false;

				foreach (['always_ignored' => true, 'not_ignored' => false, 'ignored' => true] as $var => $should_ignore) {
					foreach ($this->{$var} as $pattern) {
						if (preg_match($pattern, $pathname)) {
							$ignore = $should_ignore;
							break 2;
						}
					}
				}

				if ($ignore) {
					continue;
				}

				$relative_path = strtr(substr($f->getPathname(), \strlen(Config::$boarddir) + 1), DIRECTORY_SEPARATOR, '/');

				$checksum = md5_file($f->getPathname());

				foreach ($this->no_checksum as $pattern) {
					if (preg_match($pattern, $relative_path)) {
						$checksum = true;
					}
				}

				$this->manifest[$this->chooseSection($relative_path)][$relative_path] = $checksum;
			}
		}

		foreach ($this->manifest as $section => $list) {
			uksort($this->manifest[$section], fn($a, $b) => strtolower($a) <=> strtolower($b));
		}
	}

	/**
	 * Saves the manifest data as a JSON file.
	 */
	private function write(): void
	{
		file_put_contents(Config::$boarddir . '/Sources/Maintenance/manifest.json', json_encode($this->manifest, JSON_PRETTY_PRINT));
	}

	/**
	 * Decides which section of the manifest to put a path in.
	 *
	 * @param string $path
	 * @return string
	 */
	private function chooseSection(string $path): string
	{
		// For Db\Schema\*, Maintenance\Migration\*, and Maintenance\Cleanup\*,
		// choose the section that matches the path.
		if (preg_match('/\/v(\d)_(\d)(?=\/|$)/', $path, $matches)) {
			return $matches[1] . '.' . $matches[2];
		}

		// For everything else, choose the section for the current version.
		return preg_replace('/^(\d)\.(\d).*/', '$1.$2', SMF_VERSION);
	}
}
