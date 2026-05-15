<?php
declare( strict_types=1 );
/**
 * Plugin Name: Papy3D NoticeShield
 * Plugin URI: https://github.com/Papy-3D-Factory/Papy3D-NoticeShield
 * Description: Control intrusive third-party admin notices with allow/block decisions, history, source filters, and a clean WordPress dashboard experience.
 * Version: 2.1.4
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: papy3d
 * Author URI: https://papy-3d-factory.xyz
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: papy3d-noticeshield
 * Domain Path: /languages
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PAPY3D_NS_VERSION', '2.1.4' );
define( 'PAPY3D_NS_EXPORT_SCHEMA', 1 );
define( 'PAPY3D_NS_FILE', __FILE__ );
define( 'PAPY3D_NS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PAPY3D_NS_URL', plugin_dir_url( __FILE__ ) );
define( 'PAPY3D_NS_OPTION_RULES', 'papy3d_noticeshield_rules' );
define( 'PAPY3D_NS_OPTION_LOG', 'papy3d_noticeshield_log' );
define( 'PAPY3D_NS_MAX_LOG_ITEMS', 500 );
define( 'PAPY3D_NS_OPTION_SETTINGS', 'papy3d_noticeshield_settings' );
define( 'PAPY3D_NS_OPTION_DECISION_LOG', 'papy3d_noticeshield_decision_log' );
define( 'PAPY3D_NS_OPTION_EXPIRATIONS', 'papy3d_noticeshield_expirations' );
define( 'PAPY3D_NS_OPTION_PAUSED_UNTIL', 'papy3d_noticeshield_paused_until' );


require_once PAPY3D_NS_PATH . 'includes/trait-admin.php';
require_once PAPY3D_NS_PATH . 'includes/trait-capture.php';
require_once PAPY3D_NS_PATH . 'includes/trait-ajax.php';
require_once PAPY3D_NS_PATH . 'includes/trait-rules.php';
require_once PAPY3D_NS_PATH . 'includes/trait-storage.php';
require_once PAPY3D_NS_PATH . 'includes/trait-utils.php';
require_once PAPY3D_NS_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Papy3D_NoticeShield_Plugin', 'activate' ) );
Papy3D_NoticeShield_Plugin::instance();
