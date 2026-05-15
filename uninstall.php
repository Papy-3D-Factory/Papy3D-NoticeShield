<?php
/**
 * Uninstall Papy3D NoticeShield.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'papy3d_noticeshield_rules' );
delete_option( 'papy3d_noticeshield_log' );
delete_option( 'papy3d_noticeshield_settings' );
delete_option( 'papy3d_noticeshield_decision_log' );
delete_option( 'papy3d_noticeshield_expirations' );
delete_option( 'papy3d_noticeshield_paused_until' );

// Compatibility cleanup for earlier prerelease builds.
delete_option( 'papy3d_admin_notice_control_rules' );
delete_option( 'papy3d_admin_notice_control_log' );
