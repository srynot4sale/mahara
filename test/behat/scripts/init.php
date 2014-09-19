<?php
/**
 *
 * @package    mahara
 * @subpackage test/behat
 * @author     Son Nguyen, Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 * @copyright  portions from Moodle Behat, 2013 David Monllaó
 *
 */

/**
 * CLI script to set up the behat test environment for Mahara.
 *
 * - install behat and dependencies
 * - creates a fresh database
 * - reset the dataroot
 * - updates gherkin scenarios from the selenium test suite
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('INSTALLER', 1);
define('CLI', 1);

isset($_SERVER['REMOTE_ADDR']) && die();

// Basic behat functions.
require_once(dirname(dirname(__FILE__)) . '/classes/lib.php');

// Changing the cwd to <behatroot>.
chdir(dirname(dirname(__FILE__)));

$output = array();
$status = 0;
exec("php scripts/util.php --diag", $output, $status);

switch ($status) {
    case 0:
        echo "The Behat test environment has been already installed and enabled\n";
        break;
    case BEHAT_NO_COMPOSER:
    case BEHAT_NO_DEPENDENCIES:
        ensure_composer_dependencies_installed();
    case BEHAT_NO_DATAROOT:
    case BEHAT_NO_DATABASE:
        // Install a fresh Mahara database and dataroot for Behat tests
        passthru("php scripts/util.php --install", $status);
        if ($status !== 0) {
            behat_error($status, 'Installing Mahara test site failed.');
        }
        break;
    default:
        // Other error, just display it.
        behat_error($status, 'Error code unknown.' . implode("\n", $output) . "\n");
        break;
}

// Enable testing mode
passthru("php scripts/util.php --enable", $status);
if ($status != 0) {
    behat_error($status, 'Enabling Behat test environment failed.');
}

exit(0);
