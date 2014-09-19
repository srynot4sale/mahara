<?php
/**
 * @package    mahara
 * @subpackage test/behat
 * @author     Son Nguyen, Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 * @copyright  portions from Moodle Behat, 2013 David Monllaó
 *
 */

defined('INTERNAL') || die();

/**
 * define BEHAT error codes
 */
define('BEHAT_OK', 0);
define('BEHAT_UNINSTALL_FAILES', 246);
define('BEHAT_INSTALL_FAILES', 247);
define('BEHAT_CONFIG', 248);
define('BEHAT_REQUIREMENT', 249);
define('BEHAT_PERMISSIONS', 250);
define('BEHAT_NO_DATABASE', 252);
define('BEHAT_NO_DATAROOT', 253);
define('BEHAT_NO_DEPENDENCIES', 254);
define('BEHAT_NO_COMPOSER', 255);

/**
 * define BEHAT test site status
 */
define('TEST_SITE_INSTALLING', 2);
define('TEST_SITE_INSTALLED', 3);
define('TEST_SITE_NOTINSTALLED', 4);
define('TEST_SITE_ENABLED', 5);
define('TEST_SITE_DISABLED', 6);

/**
 * Exits with an error code
 *
 * @param  mixed $errorcode
 * @param  string $text
 * @return void Stops execution with error code
 */
function behat_error($errorcode, $text = '') {

    // Adding error prefixes.
    $initscriptpath = realpath(dirname(dirname(__FILE__)) . '/scripts/init.php');
    switch ($errorcode) {
        case BEHAT_CONFIG:
            $text = 'Behat config error: ' . $text;
            break;
        case BEHAT_REQUIREMENT:
            $text = 'Behat requirement not satisfied: ' . $text;
            break;
        case BEHAT_PERMISSIONS:
            $text = 'Behat permissions problem: ' . $text . ', check the permissions';
            break;
        case BEHAT_NO_DATAROOT:
        case BEHAT_NO_DATABASE:
            $text = "Create dataroot and database for behat test, use:\n php $initscriptpath";
            break;
        case BEHAT_NO_COMPOSER:
        case BEHAT_NO_DEPENDENCIES:
            $text = "Install Behat and its dependencies, use:\n php $initscriptpath";
            break;
        case BEHAT_INSTALLED:
            $text = "The Behat site is already installed";
            break;
        default:
            $text = 'Unknown error ' . $errorcode . ' ' . $text;
            break;
    }

    echo($text."\n");
    exit($errorcode);
}

/**
 * Ensure the composer installer and the dependencies installed and updated.
 *
 * @return void exit(int $status)
 */
function ensure_composer_dependencies_installed() {

    chdir(dirname(dirname(__FILE__)));
    // Download composer
    if (!file_exists(dirname(dirname(__FILE__)) . '/composer.phar')) {
        // Download composer if not exists
        passthru("php -r \"readfile('https://getcomposer.org/installer');\" | php", $status);
        if ($status != 0) {
            exit($status);
        }
    }
    // install behat
    else if (!file_exists(dirname(dirname(__FILE__)) . '/bin/behat')) {
        passthru("php composer.phar remove", $status);
        passthru("php composer.phar install", $status);
        if ($status != 0) {
            exit($status);
        }
    }
    // or update it
    else {
        // Update the installer to the latest version
        passthru("php composer.phar self-update", $status);
        if ($status != 0) {
            exit($status);
        }
    }

    // Install or Update dependencies.
    passthru("php composer.phar update", $status);
    if ($status != 0) {
        exit($status);
    }

}

/**
 * Display a error message.
 *
 * @param string $msg
 * @return void
 */
function output_errmsg($msg) {

    // Display the message and continue
    $clibehaterrorstr = "Make sure you set \$CFG->behat_* vars in config.php " .
                    "and ran 'php test/behat/scripts/init.php'\n" .
                    "More info in #Installation\n\n";

    echo 'Error: ' . $msg . "\n\n" . $clibehaterrorstr;
}
