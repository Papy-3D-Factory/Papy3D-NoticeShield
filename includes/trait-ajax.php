<?php
/**
 * Papy3D_NoticeShield_Ajax_Trait.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Papy3D_NoticeShield_Ajax_Trait {

	/**
	 * AJAX endpoint used by the client-side scanner for dynamic notices.
	 */
	public function ajax_capture_client_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'papy3d-noticeshield' ) ), 403 );
		}

		check_ajax_referer( 'papy3d_ns_capture_client_notice', 'nonce' );

		$html    = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified immediately above; sanitized after signature generation.
		$source  = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'client-dom'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'client_dom'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
		$url     = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.

		if ( $this->is_temporarily_paused() ) {
			wp_send_json_success( array( 'decision' => 'allow', 'signature' => '' ) );
		}

		if ( $this->is_safe_core_mode_enabled() && $this->is_core_admin_workflow_url( $url ) ) {
			wp_send_json_success( array( 'decision' => 'allow', 'signature' => '' ) );
		}

		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			wp_send_json_error( array( 'message' => __( 'Empty notice.', 'papy3d-noticeshield' ) ), 400 );
		}

		$context = $context ? $context : 'client_dom';
		$html    = $this->strip_internal_markup( $html );

		if ( $this->is_safe_core_mode_enabled() && $this->is_core_notice_html( $html ) ) {
			wp_send_json_success( array( 'decision' => 'allow', 'signature' => '' ) );
		}

		$signature = $this->signature_from_html( $html, $context );
		$rules     = $this->get_rules();
		$decision  = $this->get_effective_decision( $signature );
		$decision  = $this->inherited_dynamic_family_decision( $signature, $html, $decision );

		$this->log_notice( $signature, $html, $context, $decision );

		if ( 'pending' === $decision && ! $this->is_learning_mode_enabled() ) {
			wp_send_json_success(
				array(
					'signature' => $signature,
					'decision'  => 'allow',
					'nonce'     => wp_create_nonce( 'papy3d_ns_notice_decision' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'signature' => $signature,
				'decision'  => $decision,
				'nonce'     => wp_create_nonce( 'papy3d_ns_notice_decision' ),
			)
		);
	}

	/**
	 * AJAX decision endpoint.
	 */
	public function ajax_notice_decision() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'papy3d-noticeshield' ) ), 403 );
		}

		check_ajax_referer( 'papy3d_ns_notice_decision', 'nonce' );

		$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
		$decision  = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.

		$result = $this->save_notice_decision( $signature, $decision );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'message' => $result ) );
	}

	/**
	 * Non-JavaScript decision endpoint.
	 */
	public function handle_notice_decision_post() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'papy3d-noticeshield' ) );
		}

		check_admin_referer( 'papy3d_ns_notice_decision', 'nonce' );

		$signature   = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
		$decision    = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
		$redirect_raw = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
		$redirect_to  = $this->sanitize_redirect_url( $redirect_raw );

		$result = $this->save_notice_decision( $signature, $decision );
		$arg    = is_wp_error( $result ) ? 'papy3d_ns_error' : 'papy3d_ns_updated';

		wp_safe_redirect( add_query_arg( $arg, '1', $redirect_to ) );
		exit;
	}

	/**
	 * Handle bulk/admin page actions.
	 */
	public function handle_bulk_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'papy3d-noticeshield' ) );
		}

		check_admin_referer( 'papy3d_ns_bulk_action' );

		$action     = isset( $_POST['papy3d_ns_action'] ) ? sanitize_key( wp_unslash( $_POST['papy3d_ns_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
		$signatures = isset( $_POST['papy3d_ns_signatures'] ) && is_array( $_POST['papy3d_ns_signatures'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['papy3d_ns_signatures'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
		$signatures = array_values( array_filter( $signatures, array( $this, 'is_valid_signature' ) ) );
		$signatures = $this->related_signatures( $signatures );

		if ( 'reset_all' === $action ) {
			update_option( PAPY3D_NS_OPTION_LOG, array(), false );
			update_option( PAPY3D_NS_OPTION_RULES, array(), false );
			update_option( PAPY3D_NS_OPTION_EXPIRATIONS, array(), false );
			update_option( PAPY3D_NS_OPTION_DECISION_LOG, array(), false );
			update_option( PAPY3D_NS_OPTION_PAUSED_UNTIL, 0, false );
		} elseif ( 'clear_history' === $action ) {
			update_option( PAPY3D_NS_OPTION_LOG, array(), false );
		} elseif ( 'pause_1h' === $action ) {
			update_option( PAPY3D_NS_OPTION_PAUSED_UNTIL, time() + HOUR_IN_SECONDS, false );
		} elseif ( in_array( $action, array( 'allow', 'block', 'reset', 'mute_24h', 'mute_7d' ), true ) && ! empty( $signatures ) ) {
			$rules       = $this->get_rules();
			$expirations = $this->get_expirations();

			foreach ( $signatures as $signature ) {
				if ( 'reset' === $action ) {
					unset( $rules[ $signature ] );
					$this->update_log_decision( $signature, 'pending' );
					$this->log_decision_change( $signature, 'reset' );
				} elseif ( in_array( $action, array( 'mute_24h', 'mute_7d' ), true ) ) {
					$rules[ $signature ] = 'block';
					$expirations[ $signature ] = 'mute_24h' === $action ? time() + DAY_IN_SECONDS : time() + WEEK_IN_SECONDS;
					$this->update_log_decision( $signature, 'block' );
					$this->log_decision_change( $signature, $action );
				} else {
					$rules[ $signature ] = $action;
					$this->update_log_decision( $signature, $action );
					$this->log_decision_change( $signature, $action );
				}
			}

			update_option( PAPY3D_NS_OPTION_RULES, $rules, false );
			update_option( PAPY3D_NS_OPTION_EXPIRATIONS, $expirations, false );
		}


		$source_action = isset( $_POST['papy3d_ns_source_action'] ) ? sanitize_key( wp_unslash( $_POST['papy3d_ns_source_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$source_key    = isset( $_POST['papy3d_ns_source_key'] ) ? sanitize_title( wp_unslash( $_POST['papy3d_ns_source_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		if ( in_array( $source_action, array( 'allow_source', 'block_source' ), true ) && '' !== $source_key ) {
			$source_decision = 'allow_source' === $source_action ? 'allow' : 'block';
			$rules           = $this->get_rules();
			foreach ( $this->get_log() as $item ) {
				if ( ! is_array( $item ) || empty( $item['signature'] ) ) {
					continue;
				}
				if ( sanitize_title( $this->notice_source_from_item( $item ) ) !== $source_key ) {
					continue;
				}
				$item_signature = sanitize_text_field( (string) $item['signature'] );
				if ( $this->is_valid_signature( $item_signature ) ) {
					$rules[ $item_signature ] = $source_decision;
					$this->update_log_decision( $item_signature, $source_decision );
					$this->log_decision_change( $item_signature, $source_decision . '_source' );
				}
			}
			update_option( PAPY3D_NS_OPTION_RULES, $rules, false );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'papy3d-noticeshield', 'updated' => '1' ), admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Export allow/block rules as JSON.
	 */
	public function handle_export_rules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'papy3d-noticeshield' ) );
		}
		check_admin_referer( 'papy3d_ns_export_rules' );
		$data = array(
			'plugin'      => 'Papy3D NoticeShield',
			'generator'   => 'Papy3D NoticeShield',
			'schema'      => PAPY3D_NS_EXPORT_SCHEMA,
			'version'     => PAPY3D_NS_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'rules'       => $this->get_rules(),
			'expirations' => $this->get_expirations(),
			'settings'    => $this->get_settings(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="papy3d-noticeshield-rules.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * Import allow/block rules from JSON textarea.
	 */
	public function handle_import_rules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'papy3d-noticeshield' ) );
		}

		check_admin_referer( 'papy3d_ns_import_rules' );

		$redirect_url = admin_url( 'tools.php?page=papy3d-noticeshield' );
		$json         = isset( $_POST['papy3d_ns_import_json'] ) ? wp_unslash( $_POST['papy3d_ns_import_json'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; decoded and sanitized below.
		$json         = is_string( $json ) ? trim( $json ) : '';

		if ( '' === $json || strlen( $json ) > 262144 ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'papy3d-noticeshield', 'import_error' => 'size' ), admin_url( 'tools.php' ) ) );
			exit;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'papy3d-noticeshield', 'import_error' => 'json' ), admin_url( 'tools.php' ) ) );
			exit;
		}

		$generator = isset( $data['generator'] ) ? sanitize_text_field( (string) $data['generator'] ) : '';
		$schema    = isset( $data['schema'] ) ? absint( $data['schema'] ) : 0;
		if ( 'Papy3D NoticeShield' !== $generator || PAPY3D_NS_EXPORT_SCHEMA !== $schema || ! isset( $data['rules'] ) || ! is_array( $data['rules'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'papy3d-noticeshield', 'import_error' => 'schema' ), admin_url( 'tools.php' ) ) );
			exit;
		}

		$rules = array();
		foreach ( $data['rules'] as $signature => $decision ) {
			$signature = sanitize_text_field( (string) $signature );
			$decision  = sanitize_key( (string) $decision );
			if ( $this->is_valid_signature( $signature ) && in_array( $decision, array( 'allow', 'block' ), true ) ) {
				$rules[ $signature ] = $decision;
			}
		}

		$expirations = array();
		if ( isset( $data['expirations'] ) && is_array( $data['expirations'] ) ) {
			foreach ( $data['expirations'] as $signature => $expires_at ) {
				$signature  = sanitize_text_field( (string) $signature );
				$expires_at = absint( $expires_at );
				if ( $this->is_valid_signature( $signature ) && $expires_at > time() ) {
					$expirations[ $signature ] = $expires_at;
				}
			}
		}

		$settings = $this->get_settings();
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$settings = array_merge(
				self::default_settings(),
				array(
					'safe_core'         => ! empty( $data['settings']['safe_core'] ),
					'learning_mode'     => ! empty( $data['settings']['learning_mode'] ),
					'show_placeholders' => ! empty( $data['settings']['show_placeholders'] ),
				)
			);
		}

		update_option( PAPY3D_NS_OPTION_RULES, $rules, false );
		update_option( PAPY3D_NS_OPTION_EXPIRATIONS, $expirations, false );
		update_option( PAPY3D_NS_OPTION_SETTINGS, $settings, false );

		wp_safe_redirect( add_query_arg( array( 'page' => 'papy3d-noticeshield', 'imported' => '1' ), admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Save plugin settings.
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'papy3d-noticeshield' ) );
		}
		check_admin_referer( 'papy3d_ns_save_settings' );
		$settings = array(
			'safe_core'         => isset( $_POST['safe_core'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'learning_mode'     => isset( $_POST['learning_mode'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'show_placeholders' => isset( $_POST['show_placeholders'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		);
		update_option( PAPY3D_NS_OPTION_SETTINGS, $settings, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'papy3d-noticeshield', 'updated' => '1' ), admin_url( 'tools.php' ) ) );
		exit;
	}

}
