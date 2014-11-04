<?php

/**
 * Create user CLI script
 *
 * @package    mahara
 * @subpackage core
 * @author     Aaron Barnes <aaronb@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 * @copyright  2014 Aaron Barnes <aaronb@catalyst.net.nz>
 *
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('INSTALLER', 1);
define('CLI', 1);

require_once(dirname(dirname(dirname(__FILE__))) . '/init.php');
require_once(get_config('libroot') . 'cli.php');


// CLI setup
$cli = get_cli();
$options = array();
$options['username'] = new stdClass();
$options['username']->defaultvalue = 'student1';
$options['username']->description = 'Mahara username (must be unique)';
$options['username']->required = 1;

$options['firstname'] = new stdClass();
$options['firstname']->defaultvalue = 'John';
$options['firstname']->description = 'User\'s first name';
$options['firstname']->required = 1;

$options['lastname'] = new stdClass();
$options['lastname']->defaultvalue = 'Doe';
$options['lastname']->description = 'User\'s surname';
$options['lastname']->required = 1;

$options['email'] = new stdClass();
$options['email']->defaultvalue = 'student1@example.com';
$options['email']->description = 'User\'s email address';
$options['email']->required = 1;

$options['password'] = new stdClass();
$options['password']->defaultvalue = 'mystrongpassword';
$options['password']->description = 'User\'s password';
$options['password']->required = 1;

$settings = new stdClass();
$settings->options = $options;
$settings->allowunmatched = false;
$settings->info = 'Command line script for creating a user';

$cli->setup($settings);


// Create user object
$user = new stdClass();
foreach (array('username', 'lastname', 'surname', 'email', 'password') as $param) {
    $user->$param = $cli->get_cli_param($param);
}

// Get auth instance
foreach (auth_get_auth_instances() as $authinstance) {
    if ($authinstance->name == 'mahara') {
        $user->authinstance = $authinstance->id;
        break;
    }
}

// Attempt to create user
try {
    $newid = create_user($user);
    if ($newid) {
        cli::cli_exit('Successfully created user');
    } else {
        cli::cli_exit('Failed to create user', true);
    }
}
catch (Exception $e) {
    cli::cli_exit($e->getMessage(), true);
}
