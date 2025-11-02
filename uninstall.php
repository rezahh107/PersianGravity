
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/includes/class-pgr-installer.php';

PGR_Installer::uninstall();
