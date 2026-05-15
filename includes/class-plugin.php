<?php
/**
 * Main plugin class.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Papy3D_NoticeShield_Plugin {

	use Papy3D_NoticeShield_Admin_Trait;
	use Papy3D_NoticeShield_Capture_Trait;
	use Papy3D_NoticeShield_Ajax_Trait;
	use Papy3D_NoticeShield_Rules_Trait;
	use Papy3D_NoticeShield_Storage_Trait;
	use Papy3D_NoticeShield_Utils_Trait;

	/**
	 * Singleton instance.
	 *
	 * @var Papy3D_NoticeShield_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Current buffer context.
	 *
	 * @var string
	 */
	private $buffer_context = '';

	/**
	 * Whether a buffer is currently open.
	 *
	 * @var bool
	 */
	private $buffering = false;

	/**
	 * Own admin page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Return singleton instance.
	 *
	 * @return Papy3D_NoticeShield_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_action( 'all_admin_notices', array( $this, 'start_all_admin_notices_buffer' ), 0 );
		add_action( 'all_admin_notices', array( $this, 'end_notice_buffer' ), PHP_INT_MAX );
		add_action( 'admin_notices', array( $this, 'start_admin_notices_buffer' ), 0 );
		add_action( 'admin_notices', array( $this, 'end_notice_buffer' ), PHP_INT_MAX );
		add_action( 'network_admin_notices', array( $this, 'start_network_admin_notices_buffer' ), 0 );
		add_action( 'network_admin_notices', array( $this, 'end_notice_buffer' ), PHP_INT_MAX );
		add_action( 'user_admin_notices', array( $this, 'start_user_admin_notices_buffer' ), 0 );
		add_action( 'user_admin_notices', array( $this, 'end_notice_buffer' ), PHP_INT_MAX );

		add_action( 'wp_ajax_papy3d_ns_notice_decision', array( $this, 'ajax_notice_decision' ) );
		add_action( 'wp_ajax_papy3d_ns_capture_client_notice', array( $this, 'ajax_capture_client_notice' ) );
		add_action( 'admin_post_papy3d_ns_bulk_action', array( $this, 'handle_bulk_action' ) );
		add_action( 'admin_post_papy3d_ns_export_rules', array( $this, 'handle_export_rules' ) );
		add_action( 'admin_post_papy3d_ns_import_rules', array( $this, 'handle_import_rules' ) );
		add_action( 'admin_post_papy3d_ns_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_papy3d_ns_notice_decision_post', array( $this, 'handle_notice_decision_post' ) );
	}

	/**
	 * Activation routine.
	 */
	public static function activate() {
		if ( false === get_option( PAPY3D_NS_OPTION_RULES, false ) ) {
			add_option( PAPY3D_NS_OPTION_RULES, array(), '', false );
		}

		if ( false === get_option( PAPY3D_NS_OPTION_LOG, false ) ) {
			add_option( PAPY3D_NS_OPTION_LOG, array(), '', false );
		}

		if ( false === get_option( PAPY3D_NS_OPTION_SETTINGS, false ) ) {
			add_option( PAPY3D_NS_OPTION_SETTINGS, self::default_settings(), '', false );
		}

		if ( false === get_option( PAPY3D_NS_OPTION_DECISION_LOG, false ) ) {
			add_option( PAPY3D_NS_OPTION_DECISION_LOG, array(), '', false );
		}

		if ( false === get_option( PAPY3D_NS_OPTION_EXPIRATIONS, false ) ) {
			add_option( PAPY3D_NS_OPTION_EXPIRATIONS, array(), '', false );
		}

		if ( false === get_option( PAPY3D_NS_OPTION_PAUSED_UNTIL, false ) ) {
			add_option( PAPY3D_NS_OPTION_PAUSED_UNTIL, 0, '', false );
		}
	}

}
