<?php
/**
 * Papy3D_NoticeShield_Storage_Trait.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Papy3D_NoticeShield_Storage_Trait {

	/**
	 * Default plugin settings.
	 *
	 * @return array<string,bool>
	 */
	private static function default_settings() {
		return array(
			'safe_core'         => true,
			'learning_mode'     => true,
			'show_placeholders' => true,
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string,bool>
	 */
	private function get_settings() {
		$settings = get_option( PAPY3D_NS_OPTION_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();
		return array_merge( self::default_settings(), array_map( 'rest_sanitize_boolean', $settings ) );
	}


	/**
	 * Whether Safe Core mode is enabled.
	 *
	 * @return bool
	 */
	private function is_safe_core_mode_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['safe_core'] );
	}

	/**
	 * Whether learning mode is enabled.
	 *
	 * When enabled, unknown notices get review controls. When disabled, existing
	 * rules still apply but unknown notices are displayed normally and only logged.
	 *
	 * @return bool
	 */
	private function is_learning_mode_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['learning_mode'] );
	}

	/**
	 * Remove Papy3D NoticeShield internal controls from captured HTML.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function strip_internal_markup( $html ) {
		$html = (string) $html;
		$html = preg_replace( '/<div\b[^>]*(?:class\s*=\s*(["\'])(?:(?!\1).)*\bpapy3d-ns-decision\b(?:(?!\1).)*\1|data-papy3d-ns-internal\s*=\s*(["\'])1\2)[^>]*>.*?<\/div>/is', '', $html );
		$html = preg_replace( '/\sdata-papy3d-ns-(?:processed-root|processed-notice|signature|internal|ready)\s*=\s*(["\']).*?\1/is', '', $html );
		$html = preg_replace( '/\bpapy3d-ns-(?:captured-notice|has-decision|processed|ready)\b/i', '', $html );
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Detect fragments containing only style/script markup.
	 *
	 * These fragments are often separated from the real notice by DOMDocument
	 * splitting and should never be rendered as standalone notices.
	 *
	 * @param string $html Fragment HTML.
	 * @return bool
	 */
	private function is_style_or_script_only_fragment( $html ) {
		$html = trim( (string) $html );

		if ( '' === $html ) {
			return true;
		}

		$without_style_script = preg_replace(
			'/<(style|script)\b[^>]*>.*?<\/\1>/is',
			'',
			$html
		);

		$without_style_script = wp_strip_all_tags(
			(string) $without_style_script
		);

		return '' === trim( $without_style_script );
	}

	/**
	 * Get log option.
	 *
	 * @return array
	 */
	private function get_log() {
		$log = get_option( PAPY3D_NS_OPTION_LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Log notice.
	 *
	 * @param string $signature Signature.
	 * @param string $html Notice HTML.
	 * @param string $context Hook context.
	 * @param string $decision Decision.
	 */
	private function log_notice( $signature, $html, $context, $decision ) {
		$log   = $this->get_log();
		$html  = $this->strip_internal_markup( $html );
		$clean = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html );
		$clean = wp_kses_post( (string) $clean );
		$now   = current_time( 'mysql' );
		$page  = $this->current_admin_page_reference();

		if ( isset( $log[ $signature ] ) && is_array( $log[ $signature ] ) ) {
			$log[ $signature ]['html']      = $clean;
			$log[ $signature ]['context']   = sanitize_key( $context );
			$log[ $signature ]['decision']  = sanitize_key( $decision );
			$log[ $signature ]['last_seen'] = $now;
			$log[ $signature ]['last_seen_page'] = $page;
			$log[ $signature ]['count']     = isset( $log[ $signature ]['count'] ) ? absint( $log[ $signature ]['count'] ) + 1 : 1;
		} else {
			$log[ $signature ] = array(
				'signature'  => $signature,
				'html'       => $clean,
				'context'    => sanitize_key( $context ),
				'decision'   => sanitize_key( $decision ),
				'first_seen' => $now,
				'last_seen'  => $now,
				'last_seen_page' => $page,
				'count'      => 1,
			);
		}

		uasort(
			$log,
			static function ( $a, $b ) {
				return strcmp( isset( $b['last_seen'] ) ? $b['last_seen'] : '', isset( $a['last_seen'] ) ? $a['last_seen'] : '' );
			}
		);

		$log = array_slice( $log, 0, PAPY3D_NS_MAX_LOG_ITEMS, true );
		update_option( PAPY3D_NS_OPTION_LOG, $log, false );
	}

	/**
	 * Update log decision.
	 *
	 * @param string $signature Signature.
	 * @param string $decision Decision.
	 */
	private function update_log_decision( $signature, $decision ) {
		$log = $this->get_log();

		if ( isset( $log[ $signature ] ) && is_array( $log[ $signature ] ) ) {
			$log[ $signature ]['decision'] = sanitize_key( $decision );
			update_option( PAPY3D_NS_OPTION_LOG, $log, false );
		}
	}

	/**
	 * Validate signature.
	 *
	 * @param string $signature Signature.
	 * @return bool
	 */
	private function is_valid_signature( $signature ) {
		return is_string( $signature ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $signature );
	}

}
