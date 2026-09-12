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
 * The manifest is saved to Sources/Maintenance/manifest.php.
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
	 * Directories (but not the directory contents) listed in the 'require'
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
	 * Regex patterns for paths to exclude from the manifest.
	 */
	public array $excluded = [
		// Always ignore manifest.php.
		'~^Sources/Maintenance/manifest.php$~',
		// Always ignore dot files and directories except .htaccess.
		'~(?<=^|/)\.(?!htaccess$)~',
		// Always ignore Packages/backups, since SMF will dynamically create it
		// when necessary.
		'~^Packages/backups/~',
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
				array_filter(
					array_keys($composer_json['require']),
					fn($package) => is_dir(Config::$sourcedir . '/vendor/' . $package),
				),
			),
		);
	}

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		if (php_sapi_name() === 'cli') {
			echo 'Updating manifest file...', PHP_EOL;
		}

		$this->checkoutNewBranch();

		$this->build();
		$this->write();
		$this->gitAdd();

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
		$tracked_files = array_filter(explode(PHP_EOL, (string) shell_exec('git ls-files')), 'strlen');

		// Add the individual files to the manifest.
		foreach ($this->files as $path) {
			if (($real_path = realpath(Config::$boarddir . '/' . $path)) === false) {
				throw new \Exception($path . ' does not exist');
			}

			if (is_dir($real_path)) {
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

				$relative_path = strtr(substr($f->getPathname(), \strlen(Config::$boarddir) + 1), DIRECTORY_SEPARATOR, '/');

				if (!in_array($relative_path, $tracked_files)) {
					continue;
				}

				foreach ($this->excluded as $pattern) {
					if (preg_match($pattern, $relative_path)) {
						continue 2;
					}
				}

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
		file_put_contents(
			Config::$boarddir . '/Sources/Maintenance/manifest.php',
			'<' . '?php' . "\n\n" . 'return ' . Config::varExport($this->manifest) . ';' . "\n",
		);
	}

	/**
	 * Adds the manifest file to git.
	 */
	private function gitAdd(): void
	{
		shell_exec('git add ' . escapeshellarg('Sources/Maintenance/manifest.php'));
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
