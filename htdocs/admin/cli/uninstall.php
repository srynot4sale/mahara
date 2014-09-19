<?php

/**
 *
 * @package    mahara
 * @subpackage core
 * @author     Son Nguyen <son.nguyen@catalyst.net.nz>, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

/**
 * This will delete all mahara tables but not drop the database
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('INSTALLER', 1);
define('CLI', 1);

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
require(get_config('libroot') . 'upgrade.php');
require(get_config('docroot') . 'local/install.php');

// Drop triggers
try {
    db_drop_trigger('update_unread_insert', 'notification_internal_activity');
    db_drop_trigger('update_unread_update', 'notification_internal_activity');
    db_drop_trigger('update_unread_delete', 'notification_internal_activity');
    db_drop_trigger('unmark_quota_exeed_notified_on_update_setting', 'artefact_config');
    db_drop_trigger('unmark_quota_exeed_notified_on_update_usr_setting', 'usr');
}
catch (Exception $e) {
    exit(1);
}

// Drop plugins' tables
foreach (array_reverse(plugin_types_installed()) as $t) {
    if ($installed = plugins_installed($t, true)) {
        foreach ($installed  as $p) {
            $location = get_config('docroot') . $t . '/' . $p->name. '/db/';
            log_info('Uninstalling ' . $location);
            if (is_readable($location . 'install.xml')) {
                uninstall_from_xmldb_file($location . 'install.xml');
            }
        }
    }
}
// now uninstall core
log_info('Uninstalling core');

// These constraints must be dropped manually as they cannot be
// created with xmldb due to ordering issues
try {
    if (is_postgres()) {
        execute_sql('ALTER TABLE {usr} DROP CONSTRAINT {usr_pro_fk}');
        execute_sql('ALTER TABLE {institution} DROP CONSTRAINT {inst_log_fk}');
    }
    else {
        execute_sql('ALTER TABLE {usr} DROP FOREIGN KEY {usr_pro_fk}');
        execute_sql('ALTER TABLE {institution} DROP FOREIGN KEY {inst_log_fk}');
    }
}
catch (Exception $e) {
    exit(1);
}

uninstall_from_xmldb_file(get_config('docroot') . 'lib/db/install.xml');

exit(0);