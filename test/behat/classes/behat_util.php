<?php
/**
 * @package    mahara
 * @subpackage test/behat
 * @author     Son Nguyen, Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

/**
 * Utils for behat-related stuff
 *
 * Install composer and behat
 * Init/reset the mahara database and dataroot for Behat testing
 */

require_once(dirname(__FILE__) . '/lib.php');

class behat_util {
    /**
     * The behat test site fullname and shortname.
     */
    const TESTSITENAME = "Mahara test site";

    /**
     * @var array Files to skip when resetting dataroot folder
     */
    protected static $datarootskiponreset = array('.', '..', 'behat', 'behattestdir.txt');

    /**
     * @var array Files to skip when dropping dataroot folder
     */
    protected static $datarootskipondrop = array('.', '..', 'lock');

    /**
     * Installs a fresh mahara test site using $CFG->behat_dataroot and $CFG->behat_dbprefix
     * @throws Exception
     * @return void
     */
    public static function install_test_site() {

        // Check if the test site is ready to install
        if ($errcode = self::check_test_site()) {
            cli::cli_exit('Test site is not ready to install', $errcode);
        }

        // Check if the test site has been installed
        if (self::get_test_site_status_from_file() == TEST_SITE_INSTALLED) {
            cli::cli_exit('Test site has been installed', false);
        }

        // Update the test site status to the file test/behat/test_site_status.txt
        self::update_test_site_status_to_file(TEST_SITE_INSTALLING);

        // New dataroot.
        #cli::cli_print('Removing the dataroot for behat tests.');
        self::remove_dataroot();

        // Changing the cwd to <docroot>.
        chdir(dirname(dirname(dirname(dirname(__FILE__)))) . '/htdocs');
        $output = array();
        $status = 0;

        #cli::cli_print('Installing a fresh mahara site for behat tests.');
        exec("php admin/cli/install.php --adminpassword=Password1 --adminemail=behat@maharatest.org --sitename='" . self::TESTSITENAME . "'", $output, $status);
        if ($status != 0) {
            throw new Exception('Installing failed: ' . implode("\n", $output));
        }

        self::update_test_site_status_to_file(TEST_SITE_INSTALLED);
    }

    /**
     * Uninstall behat and remove the database and data of current mahara test site
     * @throws Exception
     * @return void
     */
    public static function uninstall_test_site() {
        self::remove_test_site_database_data();

        // Uninstall behat and its dependencies
        passthru("php composer.phar update --no-dev", $status);
        if ($status != 0) {
            throw new Exception('Uninstall behat and its dependencies failed.');
        }
    }

    /**
     * Reset the current mahara test site database and data
     * @throws Exception
     * @return void
     */
    public static function reset_test_site() {
        self::remove_test_site_database_data();
        self::update_test_site_status_to_file(TEST_SITE_INSTALLING);
        // Install a fresh mahara site
        chdir(dirname(dirname(dirname(dirname(__FILE__)))) . '/htdocs');
        $output = array();
        $status = 0;

        #cli::cli_print('Installing a fresh mahara site for behat tests.');
        exec("php admin/cli/install.php --adminpassword=Password1 --adminemail=behat@maharatest.org --sitename='" . self::TESTSITENAME . "'", $output, $status);
        if ($status != 0) {
            throw new Exception('Installing failed: ' . implode("\n", $output));
        }
        self::update_test_site_status_to_file(TEST_SITE_INSTALLED);
    }

    /**
     * Remove the current mahara test site database and data
     * @throws Exception
     * @return void
     */
    public static function remove_test_site_database_data() {
        // Check if the test site is ready to uninstall
        if ($errcode = self::check_test_site()) {
            cli::cli_exit('Test site is not ready to uninstall', $errcode);
        }

        // Check if the test site has been uninstalled
        if (self::get_test_site_status_from_file() == TEST_SITE_NOTINSTALLED) {
            cli::cli_exit('Test site has been uninstalled', false);
        }

        // Remove dataroot.
        #cli::cli_print('Removing the dataroot for behat tests.');
        self::remove_dataroot();

        // Changing the cwd to <docroot>.
        chdir(dirname(dirname(dirname(dirname(__FILE__)))) . '/htdocs');
        $output = array();
        $status = 0;
        #cli::cli_print('Drop the mahara database for behat tests.');
        exec("php admin/cli/uninstall.php", $output, $status);
        if ($status != 0) {
            throw new Exception('Uninstalling failed: ' . implode("\n", $output));
        }

        self::update_test_site_status_to_file(TEST_SITE_NOTINSTALLED);

    }

    /**
     * Get the test site status from the file test/behat/test_site_status.txt
     * @throws Exception
     * @return int $status (defined in test/behat/classes/lib.php)
     */
    public static function get_test_site_status_from_file() {
        $filepath = dirname(dirname(__FILE__)) . '/test_site_status.txt';
        if (file_exists($filepath) && is_readable($filepath)) {
            $status = file_get_contents($filepath);
            if ($status >= TEST_SITE_INSTALLING && $status <= TEST_SITE_DISABLED) {
                return $status;
            }
            else {
                throw new MaharaException('The status file of test site is unknown');
            }
        }
        else {
            return TEST_SITE_NOTINSTALLED;
        }
    }

    /**
     * Update the test site status to the file test/behat/test_site_status.txt
     * @param  int $status
     * @throws Exception
     * @return void
     */
    public static function update_test_site_status_to_file($status=TEST_SITE_ENABLED) {
        $filepath = dirname(dirname(__FILE__)) . '/test_site_status.txt';
        if (!file_put_contents($filepath, "$status")) {
            throw new MaharaException('The status file of test site is not writable');
        }
    }

    /**
     * Checks if the test server at url=$cfg->behat_wwwroot is available
     *
     * @return bool
     */
    public static function is_test_server_running() {
        global $cfg;

        if (empty($cfg->behat_wwwroot)) {
            return false;
        }

        $ch = curl_init($cfg->behat_wwwroot);

        // standard curl_setopt stuff; configs passed to the function can override these
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!ini_get('open_basedir')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        }

        curl_exec($ch);

        $errno = curl_errno($ch);
        curl_close($ch);

        return($errno === 0);
    }

    /**
     * Check if the PHP composer is installed
     *
     * @return bool
     */
    public static function is_composer_installed() {
         return file_exists(dirname(dirname(__FILE__)) . '/composer.phar');
    }

    /**
     * Check if the behat dependencies are installed
     *
     * @return bool
     */
    public static function are_dependencies_installed() {
         return is_dir(dirname(dirname(__FILE__)) . '/vendor/behat');
    }

    /**
     * Check if Cygwin on Windows is running.
     * @return bool
     */
    function is_cygwin() {
        return ((!empty($_SERVER['OS'])    && $_SERVER['OS'] === 'Windows_NT')
             || (!empty($_SERVER['SHELL']) && $_SERVER['SHELL'] === '/bin/bash')
             || (!empty($_SERVER['TERM'])  && $_SERVER['TERM'] === 'cygwin'));
    }
    
    /**
     * Check if a mingw CLI is running.
     *
     * @link http://sourceforge.net/p/mingw/bugs/1902
     * @return bool
     */
    function testing_is_mingw() {
    
        return (testing_is_cygwin() || !empty($_SERVER['MSYSTEM'])) ;
    }

/**
     * Returns the executable path
     *
     * Allows returning a customized command for cygwin when the
     * command is just displayed, when using exec(), system() and
     * friends we stay with DIRECTORY_SEPARATOR as they use the
     * normal cmd.exe (in Windows).
     *
     * @param  bool $custombyterm  If the provided command should depend on the terminal where it runs
     * @return string
     */
    public final static function get_behat_command($custombyterm = false) {

        $separator = DIRECTORY_SEPARATOR;
        $exec = 'behat';

        // Cygwin uses linux-style directory separators.
        if ($custombyterm && is_cygwin()) {
            $separator = '/';

            // MinGW can not execute .bat scripts.
            if (!is_mingw()) {
                $exec = 'behat.bat';
            }
        }
        return 'bin' . $separator . $exec;
    }

    /**
     * Runs behat command with provided options
     *
     * @param  string $options  Defaults to '' so tests would be executed
     * @return array            CLI command outputs [0] => string, [1] => integer
     */
    public final static function run($options = '') {

        $currentcwd = getcwd();
        chdir(dirname(dirname(__FILE__)));
        $output = array();
        exec(self::get_behat_command() . ' ' . $options, $output, $code);
        chdir($currentcwd);

        return $code;
    }

    /**
     * Check if the behat test environment is installed and well configured
     * and returns the error code if something went wrong
     * Items to check:
     *    - PHP composer;
     *    - Behat, its dependencies;
     *    - Mahara dataroot, database; and
     *    - Config settings.
     *
     * @return int error code
     */
    public static function check_test_site() {
        global $cfg;

        // Composer.
        if (!self::is_composer_installed()) {
            output_errmsg("The PHP composer is not installed");
            return BEHAT_NO_COMPOSER;
        }

        // Behat dependencies.
        if (!self::are_dependencies_installed()) {
            output_errmsg("The Behat and its dependencies are not installed");
            return BEHAT_NO_DEPENDENCIES;
        }

        // Behat test command.
        $code = self::run('--help');

        if ($code != 0) {
            output_errmsg("Running behat error: $code");
            return BEHAT_NO_DEPENDENCIES;
        }

        // Behat setting in config.php
        // Check required settings for behat testing
        if (empty($cfg->behat_dataroot) || empty($cfg->behat_dbprefix) || empty($cfg->behat_wwwroot)) {
            output_errmsg("The settings \$cfg->behat_dataroot, \$cfg->behat_dbprefix, and \$cfg->behat_wwwroot must be set");
            return BEHAT_CONFIG;
        }

        // Check if there settings are different from the original one
        if (
            ($cfg->behat_dbprefix == $cfg->dbprefix ||
            $cfg->behat_dataroot == $cfg->dataroot ||
            $cfg->behat_wwwroot == $cfg->wwwroot )) {
            output_errmsg("The values of \$cfg->behat_dataroot, \$cfg->behat_dbprefix, and \$cfg->behat_wwwroot must be different from its origin");
            return BEHAT_CONFIG;
        }

        // Checking behat dataroot
        if (!empty($cfg->behat_dataroot)) {
            $cfg->behat_dataroot = realpath($cfg->behat_dataroot);
        }
        if (empty($cfg->behat_dataroot) || !is_dir($cfg->behat_dataroot) || !is_writable($cfg->behat_dataroot)) {
            output_errmsg("The behat dataroot '{$cfg->behat_dataroot}' must be a directory and writable");
            return BEHAT_CONFIG;
        }
        return BEHAT_OK;
    }

    /**
     * Remove the content of dataroot directory of the behat test environment
     * @static
     * @return void
     */
    public static function remove_dataroot() {
        global $cfg;

        require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/htdocs/lib/file.php');
        if (!empty($cfg->behat_dataroot)) {
            $cfg->behat_dataroot = realpath($cfg->behat_dataroot);

            // Clean up the dataroot folder.
            $handle = opendir($cfg->behat_dataroot);
            while (false !== ($item = readdir($handle))) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                rmdirr($cfg->behat_dataroot . "/$item");
            }
            closedir($handle);
        }
    }

    /**
     * Update test scenarios and Enable the behate test site
     *
     * @return bool
     */
    public static function enable_test_mode() {

        // Check if the test site is ready to test
        if ($errcode = self::check_test_site()) {
            cli::cli_exit('Test site is not ready to test', $errcode);
        }

        // Check if the test site has been enabled
        if (self::get_test_site_status_from_file() == TEST_SITE_ENABLED) {
            cli::cli_exit('Test site has been enabled', false);
        }

        // @TODO: Update the test scenarios from Selenium test suite

        self::update_test_site_status_to_file(TEST_SITE_ENABLED);
    }

    /**
     * Disable the behate test site
     *
     * @return bool
     */
    public static function disable_test_mode() {

        // Check if the test site is ready to test
        if ($errcode = self::check_test_site()) {
            cli::cli_exit('Test site is not installed or configured', $errcode);
        }

        // Check if the test site has been disabled
        if (self::get_test_site_status_from_file() == TEST_SITE_DISABLED) {
            cli::cli_exit('Test site has been disabled', false);
        }

        self::update_test_site_status_to_file(TEST_SITE_DISABLED);
    }

}
