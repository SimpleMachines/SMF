<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF;

use League\Plates\Engine;
use League\Plates\Template\Theme as PlatesTheme;

/**
 * Renders the templates that are written for Plates.
 *
 * A theme keeps its Plates templates in its templates directory, with one
 * directory per template and one file per sub-template. So the 'main'
 * sub-template of the Stats template is templates/Stats/main.php, and it is
 * what Theme::loadTemplate('Stats') followed by Theme::loadSubTemplate('main')
 * renders.
 *
 * Templates that have not been written for Plates yet are still the
 * *.template.php files holding template_*() functions, and both kinds can be
 * loaded side by side. A theme can override a single Plates sub-template by
 * holding a file of the same name: each file is looked for in the current
 * theme, then the base theme, then the default theme.
 */
class TemplateEngine
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var string
	 *
	 * The directory in a theme that holds its Plates templates.
	 */
	public const DIRECTORY = 'templates';

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * The template directories the engine looks in, in the order it looks.
	 */
	public array $dirs = [];

	/**
	 * @var array
	 *
	 * The templates loaded so far, in the order they were loaded. Keys are
	 * template names. Values are null for a Plates template, or the file
	 * holding the template_*() functions of one that is not.
	 */
	public array $loaded = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var Engine
	 *
	 * The Plates engine that finds and renders the templates.
	 */
	protected Engine $engine;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var self
	 *
	 * The engine for the current theme.
	 */
	protected static self $instance;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $dirs The template directories to look in, in the order to
	 *    look in them, as in Theme::$current->settings['template_dirs'].
	 */
	public function __construct(array $dirs)
	{
		$this->setDirs($dirs);
	}

	/**
	 * Changes the directories the engine looks in.
	 *
	 * The templates that were loaded stay loaded.
	 *
	 * @param array $dirs The template directories to look in, in the order to
	 *    look in them.
	 */
	public function setDirs(array $dirs): void
	{
		$this->dirs = array_values(array_unique($dirs));

		// Before a theme is loaded there is nowhere to look, and Plates refuses an empty hierarchy.
		if ($this->dirs === []) {
			unset($this->engine);

			return;
		}

		// Plates wants the hierarchy from the bottom up, and a name for each level.
		$themes = [];

		foreach (array_reverse($this->dirs) as $dir) {
			$themes[] = PlatesTheme::new($dir . '/' . self::DIRECTORY, $dir);
		}

		$this->engine = Engine::fromTheme(PlatesTheme::hierarchy($themes));
	}

	/**
	 * Checks whether a template directory holds a template written for Plates.
	 *
	 * @param string $dir A template directory.
	 * @param string $template The name of a template, such as 'Stats'.
	 * @return bool Whether $dir holds a Plates version of $template.
	 */
	public function isPlatesTemplate(string $dir, string $template): bool
	{
		return is_dir($dir . '/' . self::DIRECTORY . '/' . $template);
	}

	/**
	 * Records that a template has been loaded.
	 *
	 * @param string $template The name of the template.
	 * @param ?string $file The file holding the template's template_*()
	 *    functions, or null if it is a Plates template.
	 */
	public function addLoaded(string $template, ?string $file = null): void
	{
		// Loading a template again makes it the last one loaded.
		unset($this->loaded[$template]);

		$this->loaded[$template] = $file;
	}

	/**
	 * Finds the Plates template that renders a sub-template.
	 *
	 * The templates loaded last are searched first. A template_*() function
	 * defined by a template loaded after every Plates template that has the
	 * sub-template wins, so that the most recent loadTemplate() call decides
	 * which one runs whatever kind of template it loaded.
	 *
	 * @param string $sub_template The name of the sub-template, such as 'main'.
	 * @return ?string The Plates name of the template, such as 'Stats/main',
	 *    or null if the sub-template is not one of the loaded Plates templates.
	 */
	public function find(string $sub_template): ?string
	{
		// Every directory has an index.php to stop it being listed, which is not a sub-template.
		if ($sub_template === 'index') {
			return null;
		}

		$function = 'template_' . $sub_template;

		foreach (array_reverse($this->loaded) as $template => $file) {
			if ($file === null) {
				if ($this->exists($template . '/' . $sub_template)) {
					return $template . '/' . $sub_template;
				}
			} elseif (\function_exists($function) && realpath((new \ReflectionFunction($function))->getFileName()) === realpath($file)) {
				return null;
			}
		}

		return null;
	}

	/**
	 * Checks whether a Plates template exists in any of the directories.
	 *
	 * @param string $name The Plates name of the template, such as 'Stats/main'.
	 * @return bool Whether the template exists.
	 */
	public function exists(string $name): bool
	{
		// Engine::exists() does not use the theme hierarchy, but Template::exists() does.
		return isset($this->engine) && $this->engine->make($name)->exists();
	}

	/**
	 * Gets the file that a Plates template will be rendered from.
	 *
	 * @param string $name The Plates name of the template, such as 'Stats/main'.
	 * @return ?string The path to the file, or null if there is no such template.
	 */
	public function path(string $name): ?string
	{
		return $this->exists($name) ? $this->engine->make($name)->path() : null;
	}

	/**
	 * Renders a Plates template.
	 *
	 * @param string $name The Plates name of the template, such as 'Stats/main'.
	 * @param array $data Variables to make available to the template.
	 * @return string The rendered template.
	 */
	public function render(string $name, array $data = []): string
	{
		return $this->engine->render($name, $data);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets the engine for the current theme.
	 *
	 * @return self The engine, looking in the current theme's template
	 *    directories.
	 */
	public static function get(): self
	{
		$dirs = Theme::$current->settings['template_dirs'] ?? [];

		if (!isset(self::$instance)) {
			self::$instance = new self($dirs);
		} elseif (self::$instance->dirs !== array_values(array_unique($dirs))) {
			self::$instance->setDirs($dirs);
		}

		return self::$instance;
	}
}
