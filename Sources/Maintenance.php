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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Tools;
use SMF\Maintenance\ToolsInterface;
use SMF\Maintenance\Template;
use SMF\Maintenance\ToolsBase;
use SMF\Maintenance\TemplateInterface;

class Maintenance
{
    public const INSTALL = 1;

    public const UPGRADE = 2;

    public const CONVERT = 3;

    public const TOOL = 4;

    public const SPECIAL = 99;

    public static array $context = [];

    public static array $languages = [];
    public static array $warnings = [];
    public static array $errors = [];
    public static string $fatal_error = '';


    /** @var \SMF\Maintenance\ToolsInterface&\SMF\Maintenance\ToolsBase $tool */
    public static ToolsInterface $tool;

    /** @var \SMF\Maintenance\TemplateInterface $template */
    public static TemplateInterface $template;
    public static string $sub_template = '';

    private static array $valid_tools = [
        self::INSTALL => 'Install',
        self::UPGRADE => 'Upgrade',
        self::CONVERT => 'Convert',
        self::TOOL => 'Tool',
        self::SPECIAL => 'Special'
    ];

    public static int $overall_percent = 0;

    public function __construct()
    {
        Security::frameOptionsHeader('SAMEORIGIN');
    }

    public function execute(int $type): void
    {
        if (!self::toolIsValid($type)) {
            die('Invalid Tool selected');
        }

        /** @var \SMF\Maintenance\ToolsInterface&\SMF\Maintenance\ToolsBase $tool_class */
        $tool_class = '\\SMF\\Maintenance\\Tools\\' . self::$valid_tools[$type];
        require_once Config::$sourcedir . '/Maintenance/Tools/' . self::$valid_tools[$type] . '.php';
        self::$tool = new $tool_class();


        /** @var \SMF\Maintenance\TemplateInterface $templ_class */
        $templ_class = '\\SMF\\Maintenance\\Template\\' . self::$valid_tools[$type];
        require_once Config::$sourcedir . '/Maintenance/Template/' . self::$valid_tools[$type] . '.php';
        self::$template = new $templ_class();

    	// This is really quite simple; if ?delete is on the URL, delete the installer...
        if (isset($_GET['delete'])) {
            self::$tool->deleteTool();
            exit;
        }

        /** @var \SMF\Maintenance\Step $step */
        foreach (self::$tool->getSteps() as $num => $step) {
            if ($num >= self::getCurrentStep()) {
                // The current weight of this step in terms of overall progress.
                self::$context['step_weight'] = $step->getProgress();

                // Make sure we reset the skip button.
                self::$context['skip'] = false;
        
                // Call the step and if it returns false that means pause!
                if (method_exists(self::$tool, $step->getFunction()) && self::$tool->{$step->getFunction()}() === false) {
                    break;
                }
        
                if (method_exists(self::$tool, $step->getFunction())) {
                    self::setCurrentStep();
                }
        
                // No warnings pass on.
                self::$context['warning'] = '';
            }
            self::$overall_percent += (int) $step->getProgress();
        }

        // Last chance to set our template.
        if (self::$sub_template === '') {
            self::$sub_template = self::$tool->getSteps()[self::getCurrentStep()]->getFunction();
        }

        self::exit();
    }

    public static function getRequiredVersionForPHP(): string
    {
        return '8.0.1';
    }

	// See if we think they have already installed it?
    public static function isInstalled(): bool
    {
        if (Config::$image_proxy_secret === 'smfisawesome' && Config::$db_passwd === '' && Config::$boardurl === 'http://127.0.0.1/smf') {
            return false;
        }
        return true;
    }


    public static function getSelf(): string
    {
        return $_SERVER['PHP_SELF'];
    }

    public static function getBaseDir(): string
    {
        return dirname(SMF_SETTINGS_FILE);
    }

    public static function getCurrentStep(): int
    {
        return isset($_GET['step']) ? (int) $_GET['step'] : 0;
    }

    public static function getCurrentSubStep(): int
    {
        return isset($_GET['substep']) ? (int) $_GET['substep'] : 0;
    }

    public static function setCurrentSubStep(?int $substep = null): void
    {
        $_GET['substep'] = $substep ?? (self::getCurrentSubStep() + 1);
    }

    public static function getCurrentStart(): int
    {
        return isset($_GET['start']) ? (int) $_GET['start'] : 0;
    }

    public static function setCurrentStart(?int $start = null): void
    {
        $_GET['start'] = $start ?? (self::getCurrentStart() + 1);
    }

    public static function getRequestedLanguage(): string
    {
        if (isset($_GET['lang_file'])) {
            $_SESSION['lang_file'] = strtr((string) $_GET['lang_file'], './\\:', '____');
            return $_SESSION['lang_file'];
        } else if (isset($_SESSION['lang_file'])) {
            return $_SESSION['lang_file'];
        } else {
            return 'en_US';
        }

    }

    public static function setSubTemplate(string $tmpl): void
    {
        if (method_exists(self::$template, $tmpl)) {
            self::$sub_template = $tmpl;
        }
    }
    private static function toolIsValid(int $type): bool
    {
        return isset(self::$valid_tools[$type]);
    }

    private static function setCurrentStep(?int $step = null): void
    {
        $_GET['step'] = $step ?? (self::getCurrentStep() + 1);
    }

    private static function exit(bool $fallThrough = false): void
    {
        // Send character set.
        header('content-type: text/html; charset=' . (Lang::$txt['lang_character_set'] ?? 'UTF-8'));
    
        // We usually dump our templates out.
        if (!$fallThrough) {
            // The top bit.
            Template::header();
            call_user_func([self::$template, 'upper']);
    
            // Call the template.
            if (self::$sub_template !== '') {
                self::$context['form_url'] = self::getSelf() . '?step=' . self::getCurrentStep();
    
                call_user_func([self::$template, self::$sub_template]);
            }

            // Show the footer.
            call_user_func([self::$template, 'lower']);
            Template::footer();
        }
    
        // Bang - gone!
        die();
    }
}

