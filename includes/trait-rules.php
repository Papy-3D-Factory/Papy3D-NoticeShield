<?php
/**
 * Papy3D_NoticeShield_Rules_Trait.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Papy3D_NoticeShield_Rules_Trait {

	/**
	 * Return client-side decisions for stable family keys.
	 *
	 * This prevents already blocked family notices from flashing while the
	 * asynchronous DOM scanner confirms the server-side decision.
	 *
	 * @return array<string,string>
	 */
	private function client_known_decisions() {
		$rules = $this->get_rules();
		$families = array(
			'gtranslate-upgrade-tips',
			'ctc-welcome-updated',
			'freemius-ctc-notice',
			'filebird-empty-folder-notice',
			'advanced-db-cleaner-rating',
			'development-site-warning',
		);

		$map = array();
		foreach ( $families as $family ) {
			$signature = hash( 'sha256', 'family|' . $family );
			if ( isset( $rules[ $signature ] ) && in_array( $rules[ $signature ], array( 'allow', 'block' ), true ) ) {
				$map[ 'family:' . $family ] = $rules[ $signature ];
			}
		}

		return $map;
	}

	/**
	 * Inherit a decision from older signatures when a source becomes an auto-family.
	 *
	 * @param string $signature New signature.
	 * @param string $html Notice HTML.
	 * @param string $default Default decision.
	 * @return string
	 */
	private function inherited_dynamic_family_decision( $signature, $html, $default ) {
		$family = $this->notice_family_from_html( $html );
		if ( '' === $family || 0 !== strpos( $family, 'auto-source-' ) ) {
			return $default;
		}

		$rules  = $this->get_rules();
		$source = $this->source_fingerprint_from_html( $html );
		$log    = $this->get_log();
		$votes  = array( 'block' => 0, 'allow' => 0 );

		foreach ( $log as $item ) {
			if ( ! is_array( $item ) || empty( $item['html'] ) || empty( $item['signature'] ) ) {
				continue;
			}
			if ( $this->source_fingerprint_from_html( (string) $item['html'] ) !== $source ) {
				continue;
			}
			$item_signature = sanitize_text_field( $item['signature'] );
			$item_decision  = isset( $rules[ $item_signature ] ) ? sanitize_key( $rules[ $item_signature ] ) : ( isset( $item['decision'] ) ? sanitize_key( $item['decision'] ) : 'pending' );
			if ( isset( $votes[ $item_decision ] ) ) {
				$votes[ $item_decision ]++;
			}
		}

		if ( $votes['block'] > 0 ) {
			$rules[ $signature ] = 'block';
			update_option( PAPY3D_NS_OPTION_RULES, $rules, false );
			return 'block';
		}
		if ( $votes['allow'] > 0 ) {
			$rules[ $signature ] = 'allow';
			update_option( PAPY3D_NS_OPTION_RULES, $rules, false );
			return 'allow';
		}

		return $default;
	}

	/**
	 * Save one notice decision.
	 *
	 * @param string $signature Notice signature.
	 * @param string $decision Decision.
	 * @return string|WP_Error
	 */
	private function save_notice_decision( $signature, $decision ) {
		if ( ! $this->is_valid_signature( $signature ) || ! in_array( $decision, array( 'allow', 'block', 'mute_24h', 'mute_7d' ), true ) ) {
			return new WP_Error( 'papy3d_ns_invalid_request', __( 'Invalid request.', 'papy3d-noticeshield' ) );
		}

		$rules       = $this->get_rules();
		$expirations = $this->get_expirations();
		$signatures  = $this->related_signatures( array( $signature ) );
		$stored      = in_array( $decision, array( 'mute_24h', 'mute_7d' ), true ) ? 'block' : $decision;
		$expires_at  = 'mute_24h' === $decision ? time() + DAY_IN_SECONDS : ( 'mute_7d' === $decision ? time() + WEEK_IN_SECONDS : 0 );

		foreach ( $signatures as $related_signature ) {
			$rules[ $related_signature ] = $stored;
			if ( $expires_at > 0 ) {
				$expirations[ $related_signature ] = $expires_at;
			} else {
				unset( $expirations[ $related_signature ] );
			}
			$this->update_log_decision( $related_signature, $stored );
			$this->log_decision_change( $related_signature, $decision );
		}

		update_option( PAPY3D_NS_OPTION_RULES, $rules, false );
		update_option( PAPY3D_NS_OPTION_EXPIRATIONS, $expirations, false );

		if ( 'allow' === $decision ) {
			return __( 'This notice will be allowed.', 'papy3d-noticeshield' );
		}
		if ( 'mute_24h' === $decision ) {
			return __( 'This notice is muted for 24 hours.', 'papy3d-noticeshield' );
		}
		if ( 'mute_7d' === $decision ) {
			return __( 'This notice is muted for 7 days.', 'papy3d-noticeshield' );
		}
		return __( 'This notice will be blocked.', 'papy3d-noticeshield' );
	}

	/**
	 * Expand selected signatures to every known signature from the same family/source.
	 *
	 * This prevents legacy signatures from keeping an old allow/block decision after
	 * a notice has been promoted to a stable family signature. It is especially
	 * useful for notices that existed before family grouping was introduced.
	 *
	 * @param array $signatures Selected signatures.
	 * @return array
	 */
	private function related_signatures( $signatures ) {
		$signatures = array_values( array_filter( array_map( 'sanitize_text_field', (array) $signatures ), array( $this, 'is_valid_signature' ) ) );
		if ( empty( $signatures ) ) {
			return array();
		}

		$log      = $this->get_log();
		$families = array();
		$sources  = array();

		foreach ( $log as $item ) {
			if ( ! is_array( $item ) || empty( $item['signature'] ) || empty( $item['html'] ) ) {
				continue;
			}

			$item_signature = sanitize_text_field( (string) $item['signature'] );
			if ( ! in_array( $item_signature, $signatures, true ) ) {
				continue;
			}

			$item_html = (string) $item['html'];
			$family    = $this->notice_family_from_html( $item_html );
			$source    = $this->source_fingerprint_from_html( $item_html );

			if ( '' !== $family ) {
				$families[ $family ] = true;
			}
			if ( '' !== $source ) {
				$sources[ $source ] = true;
			}
		}

		if ( empty( $families ) && empty( $sources ) ) {
			return $signatures;
		}

		foreach ( $log as $item ) {
			if ( ! is_array( $item ) || empty( $item['signature'] ) || empty( $item['html'] ) ) {
				continue;
			}

			$item_html      = (string) $item['html'];
			$item_signature = sanitize_text_field( (string) $item['signature'] );
			$item_family    = $this->notice_family_from_html( $item_html );
			$item_source    = $this->source_fingerprint_from_html( $item_html );

			if ( ( '' !== $item_family && isset( $families[ $item_family ] ) ) || ( '' !== $item_source && isset( $sources[ $item_source ] ) && isset( $families[ $item_family ] ) ) ) {
				$signatures[] = $item_signature;
			}
		}

		return array_values( array_unique( array_filter( $signatures, array( $this, 'is_valid_signature' ) ) ) );
	}

	/**
	 * Resolve the effective decision, including temporary mute expiration.
	 *
	 * @param string $signature Notice signature.
	 * @param string $default Default decision.
	 * @return string
	 */
	private function get_effective_decision( $signature, $default = 'pending' ) {
		$rules      = $this->get_rules();
		$decision   = isset( $rules[ $signature ] ) ? sanitize_key( $rules[ $signature ] ) : $default;
		$expires_at = $this->get_expirations();

		if ( 'block' === $decision && isset( $expires_at[ $signature ] ) && $expires_at[ $signature ] > 0 && time() >= $expires_at[ $signature ] ) {
			unset( $rules[ $signature ], $expires_at[ $signature ] );
			update_option( PAPY3D_NS_OPTION_RULES, $rules, false );
			update_option( PAPY3D_NS_OPTION_EXPIRATIONS, $expires_at, false );
			return 'pending';
		}

		return in_array( $decision, array( 'allow', 'block' ), true ) ? $decision : 'pending';
	}

	/**
	 * Record a decision change.
	 *
	 * @param string $signature Notice signature.
	 * @param string $decision Decision.
	 */
	private function log_decision_change( $signature, $decision ) {
		$decision_log = get_option( PAPY3D_NS_OPTION_DECISION_LOG, array() );
		$decision_log = is_array( $decision_log ) ? $decision_log : array();
		$user         = wp_get_current_user();
		array_unshift(
			$decision_log,
			array(
				'time'      => current_time( 'mysql' ),
				'user'      => $user && $user->exists() ? $user->user_login : 'system',
				'signature' => sanitize_text_field( $signature ),
				'decision'  => sanitize_key( $decision ),
			)
		);
		$decision_log = array_slice( $decision_log, 0, 100 );
		update_option( PAPY3D_NS_OPTION_DECISION_LOG, $decision_log, false );
	}

	/**
	 * Get decision journal.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_decision_log() {
		$decision_log = get_option( PAPY3D_NS_OPTION_DECISION_LOG, array() );
		return is_array( $decision_log ) ? $decision_log : array();
	}

	/**
	 * Get rules option.
	 *
	 * @return array
	 */
	private function get_rules() {
		$rules = get_option( PAPY3D_NS_OPTION_RULES, array() );
		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Get expiration rules.
	 *
	 * @return array<string,int>
	 */
	private function get_expirations() {
		$expirations = get_option( PAPY3D_NS_OPTION_EXPIRATIONS, array() );
		return is_array( $expirations ) ? array_map( 'absint', $expirations ) : array();
	}

	/**
	 * Return the global pause timestamp.
	 *
	 * @return int
	 */
	private function get_paused_until() {
		return absint( get_option( PAPY3D_NS_OPTION_PAUSED_UNTIL, 0 ) );
	}

	/**
	 * Check if NoticeShield is temporarily paused.
	 *
	 * @return bool
	 */
	private function is_temporarily_paused() {
		$paused_until = $this->get_paused_until();
		if ( $paused_until > 0 && time() >= $paused_until ) {
			update_option( PAPY3D_NS_OPTION_PAUSED_UNTIL, 0, false );
			return false;
		}

		return $paused_until > time();
	}

}
