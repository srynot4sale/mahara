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
 * CLI tool to manage Behat integration in Mahara
 *
 * Like Moodle, This tool uses 
 * $CFG->behat_dataroot for $CFG->dataroot
 * and $CFG->behat_dbprefix for $CFG->dbprefix
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('INSTALLER', 1);
define('CLI', 1);

isset($_SERVER['REMOTE_ADDR']) && die();

// Behat classes.
require_once(dirname(dirname(__FILE__)) . '/classes/lib.php');
require_once(dirname(dirname(__FILE__)) . '/classes/behat_util.php');

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/htdocs/lib/errors.php');
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/htdocs/config.php');
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/htdocs/lib/cli.php');

$cli = get_cli();

$options = array();

$options['install'] = new stdClass();
$options['install']->shortoptions = array('i');
$options['install']->description = 'Installs the test environment for acceptance tests';
$options['install']->required = false;
$options['install']->defaultvalue = false;

$options['uninstall'] = new stdClass();
$options['uninstall']->shortoptions = array('u');
$options['uninstall']->description = 'Uninstalls the test environment including composer and behat';
$options['uninstall']->required = false;
$options['uninstall']->defaultvalue = false;

$options['enable'] = new stdClass();
$options['enable']->shortoptions = array('e');
$options['enable']->description = 'Turn ON the test mode';
$options['enable']->required = false;
$options['enable']->defaultvalue = false;

$options['disable'] = new stdClass();
$options['disable']->shortoptions = array('d');
$options['disable']->description = 'Turn OFF the test mode';
$options['disable']->required = false;
$options['disable']->defaultvalue = false;

$options['reset'] = new stdClass();
$options['reset']->shortoptions = array('r');
$options['reset']->description = 'Reset the current test site database and dataroot';
$options['reset']->required = false;
$options['reset']->defaultvalue = false;

$options['diag'] = new stdClass();
$options['diag']->description = 'Get behat test environment status code';
$options['diag']->required = false;
$options['diag']->defaultvalue = false;

$settings = new stdClass();
$settings->options = $options;
$settings->info = 'CLI tool to manage Behat integration in Mahara';

$cli->setup($settings);

try {
    if ($cli->get_cli_param('install')) {
        behat_util::install_test_site();
        cli::cli_exit("\nAcceptance test site is installed\n");
    }
    else if ($cli->get_cli_param('enable')) {
        behat_util::enable_test_mode();
        cli::cli_exit("\nAcceptance test site is enabled\n");
    }
    else if ($cli->get_cli_param('uninstall')) {
        behat_util::uninstall_test_site();
        cli::cli_exit("\nAcceptance test site is uninstalled\n");
    }
    else if ($cli->get_cli_param('disable')) {
        behat_util::disable_test_mode();
        cli::cli_exit("\nAcceptance test site is disabled\n");
    }
    else if ($cli->get_cli_param('reset')) {
        behat_util::reset_test_site();
        cli::cli_exit("\nAcceptance test site database and dataroot are reset\n");
    }
    else if ($cli->get_cli_param('diag')) {
        $code = behat_util::check_test_site();
        exit($code);
    }
}
catch (Exception $e) {
    cli::cli_exit($e->getMessage(), true);
}

exit(0);
