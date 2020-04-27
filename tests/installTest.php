<?php

/**
 * The main purpose of this file is to show a list of all errors that were
 * logged on the forum, and allow filtering and deleting them.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

define('SMFPHPUNIT',TRUE);
use PHPUnit\Framework\TestCase;
require_once('.\install.php');

final class InstallTest extends TestCase
{
    public function testWrite() {
        load_lang_file();
        $var = CheckFilesWritable();
        $this->assertEquals(true,$var);
    }

    public function testDBSettings() {
        $_POST['db_type'] = 'postgresql';
        $_POST['db_prefix'] = 'smf_';
        $_POST['db_name'] = 'travis_ci_test';
        $_POST['db_user'] = 'postgres';
        $_POST['db_passwd'] = '';
        $_POST['db_server'] = 'localhost';
        $_POST['db_port'] = '5432';

        // updateSettingsFile got different way to detect setting dir...
        $GLOBALS['boarddir'] = getcwd();

        load_lang_file();
        $var = DatabaseSettings();
        $this->assertEquals(true,$var);
    }

    public function testForumSettings() {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_POST['boardurl'] = 'http://localhost';
        $_POST['mbname'] = 'My PHPUnit Community';
        load_lang_file();
        $var = ForumSettings();
        $this->assertEquals(true,$var);
    }

    public function testDatabasePopulation() {
        load_lang_file();
        $var = DatabasePopulation();
        $this->assertEquals(true,$var);
    }

    public function testAdminAccount() {
        $_POST['username'] = 'Admin';
        $_POST['email'] = 'phpunit@localhost.com';
        $_POST['password1'] = 'admin';    
        $_POST['contbutt'] = '1234';

        $_POST['password2'] = $_POST['password1'];
        $_POST['server_email'] = $_POST['email'];
        load_lang_file();
        $var = AdminAccount();
        $this->assertEquals(true,$var);
    }
}
