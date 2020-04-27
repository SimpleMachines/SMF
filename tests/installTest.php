<?php

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
