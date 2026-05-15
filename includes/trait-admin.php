<?php
/**
 * Papy3D_NoticeShield_Admin_Trait.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Papy3D_NoticeShield_Admin_Trait {

	/**
	 * Register admin page.
	 */
	public function register_admin_page() {
		$this->page_hook = add_management_page(
			__( 'Papy3D NoticeShield', 'papy3d-noticeshield' ),
			__( 'Papy3D NoticeShield', 'papy3d-noticeshield' ),
			'manage_options',
			'papy3d-noticeshield',
			array( $this, 'render_admin_page' )
		);

		if ( $this->page_hook ) {
			add_action( 'load-' . $this->page_hook, array( $this, 'suppress_notices_on_own_page' ), 0 );
		}
	}

	/**
	 * Stop third-party admin notices from being printed above the plugin UI.
	 *
	 * This runs only on the dedicated NoticeShield Tools screen. The page already
	 * lists captured third-party notices as review cards, so keeping normal notice
	 * hooks active here would duplicate content above the management interface.
	 *
	 * The suppression is intentionally limited to the generic admin notice hooks
	 * used by most plugins. User-specific notices are not removed, and network
	 * notices are removed only while viewing the plugin page in Network Admin.
	 */
	public function suppress_notices_on_own_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice_hooks = array(
			'admin_notices',
			'all_admin_notices',
		);

		if ( is_multisite() && is_network_admin() ) {
			$notice_hooks[] = 'network_admin_notices';
		}

		foreach ( $notice_hooks as $hook_name ) {
			remove_all_actions( $hook_name );
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$asset_url  = PAPY3D_NS_URL . 'assets/';
		$asset_path = PAPY3D_NS_PATH . 'assets/';

		wp_enqueue_style(
			'papy3d-ns-admin',
			$asset_url . 'admin.css',
			array(),
			file_exists( $asset_path . 'admin.css' ) ? (string) filemtime( $asset_path . 'admin.css' ) : PAPY3D_NS_VERSION
		);

		wp_enqueue_script(
			'papy3d-ns-admin',
			$asset_url . 'admin.js',
			array( 'jquery' ),
			file_exists( $asset_path . 'admin.js' ) ? (string) filemtime( $asset_path . 'admin.js' ) : PAPY3D_NS_VERSION,
			true
		);

		wp_add_inline_script(
			'papy3d-ns-admin',
			'window.papy3dNs = ' . wp_json_encode(
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'adminPostUrl'   => admin_url( 'admin-post.php' ),
					'captureNonce'   => wp_create_nonce( 'papy3d_ns_capture_client_notice' ),
					'decisionNonce'  => wp_create_nonce( 'papy3d_ns_notice_decision' ),
					'knownDecisions' => $this->client_known_decisions(),
					'settings'       => $this->get_settings(),
					'pausedUntil'    => $this->get_paused_until(),
					'i18n'           => array(
						'allow'               => __( 'Allow', 'papy3d-noticeshield' ),
						'block'               => __( 'Block', 'papy3d-noticeshield' ),
						'noticeDecision'      => __( 'Notice decision', 'papy3d-noticeshield' ),
						'missingDecisionData' => __( 'Missing decision data.', 'papy3d-noticeshield' ),
						'saving'              => __( 'Saving...', 'papy3d-noticeshield' ),
						'saved'               => __( 'Saved.', 'papy3d-noticeshield' ),
						'error'               => __( 'Error.', 'papy3d-noticeshield' ),
						'ajaxError'           => __( 'AJAX error: decision not saved. Reload the page and try again.', 'papy3d-noticeshield' ),
						'hideNotice'          => __( 'Hide notice', 'papy3d-noticeshield' ),
						'viewNotice'          => __( 'View notice', 'papy3d-noticeshield' ),
						'previewEmpty'        => __( 'Notice preview could not be loaded.', 'papy3d-noticeshield' ),
						'confirmReset'        => __( 'This will delete captured history and all decisions. Continue?', 'papy3d-noticeshield' ),
					),
				)
			) . ';',
			'before'
		);

		if ( $hook === $this->page_hook ) {
			wp_enqueue_style( 'common' );
		}
	}

	/**
	 * Render admin page.
	 */
	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$papy3d_ns_log    = $this->get_log();
		$papy3d_ns_counts = array(
			'all'     => 0,
			'allow'   => 0,
			'block'   => 0,
			'pending' => 0,
		);

		foreach ( $papy3d_ns_log as $papy3d_ns_item ) {
			$signature = isset( $papy3d_ns_item['signature'] ) ? sanitize_text_field( (string) $papy3d_ns_item['signature'] ) : '';
			$decision  = $this->is_valid_signature( $signature ) ? $this->get_effective_decision( $signature ) : 'pending';

			$papy3d_ns_counts['all']++;
			if ( isset( $papy3d_ns_counts[ $decision ] ) ) {
				$papy3d_ns_counts[ $decision ]++;
			}
		}

		$view_file = PAPY3D_NS_PATH . 'views/admin-page.php';

		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}

	/**
	 * Render per-card action form.
	 *
	 * @param string $signature Signature.
	 * @param string $decision Current decision.
	 */
	private function render_card_action_form( $signature, $decision ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<button class="button papy3d-ns-toggle-preview" type="button" aria-expanded="false" data-show-label="<?php echo esc_attr__( 'View notice', 'papy3d-noticeshield' ); ?>" data-hide-label="<?php echo esc_attr__( 'Hide notice', 'papy3d-noticeshield' ); ?>"><?php echo esc_html__( 'View notice', 'papy3d-noticeshield' ); ?></button>
			<?php wp_nonce_field( 'papy3d_ns_bulk_action' ); ?>
			<input type="hidden" name="action" value="papy3d_ns_bulk_action" />
			<input type="hidden" name="papy3d_ns_signatures[]" value="<?php echo esc_attr( $signature ); ?>" />
			<?php if ( 'allow' !== $decision ) : ?>
				<button class="button button-primary" type="submit" name="papy3d_ns_action" value="allow"><?php echo esc_html__( 'Allow', 'papy3d-noticeshield' ); ?></button>
			<?php else : ?>
				<button class="button button-primary" type="submit" name="papy3d_ns_action" value="allow"><?php echo esc_html__( 'Keep allowed', 'papy3d-noticeshield' ); ?></button>
			<?php endif; ?>
			<?php if ( 'block' !== $decision ) : ?>
				<button class="button button-secondary papy3d-ns-button-block" type="submit" name="papy3d_ns_action" value="block"><?php echo esc_html__( 'Block', 'papy3d-noticeshield' ); ?></button>
			<?php else : ?>
				<button class="button button-secondary papy3d-ns-button-block" type="submit" name="papy3d_ns_action" value="block"><?php echo esc_html__( 'Keep blocked', 'papy3d-noticeshield' ); ?></button>
			<?php endif; ?>
			<button class="button" type="submit" name="papy3d_ns_action" value="mute_24h"><?php echo esc_html__( 'Mute 24h', 'papy3d-noticeshield' ); ?></button>
			<button class="button" type="submit" name="papy3d_ns_action" value="mute_7d"><?php echo esc_html__( 'Mute 7 days', 'papy3d-noticeshield' ); ?></button>
			<button class="button" type="submit" name="papy3d_ns_action" value="reset"><?php echo esc_html__( 'Ask again', 'papy3d-noticeshield' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Extract a short readable summary from stored notice HTML.
	 *
	 * @param string $html Notice HTML.
	 * @return string
	 */
	private function notice_summary_from_html( $html ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $html ) ) );
		if ( '' === $text ) {
			return __( 'No readable text was found for this notice. Click View notice to inspect its HTML-rendered content.', 'papy3d-noticeshield' );
		}
		$limit = 180;
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $text ) > $limit ? mb_substr( $text, 0, $limit ) . '…' : $text;
		}
		return strlen( $text ) > $limit ? substr( $text, 0, $limit ) . '…' : $text;
	}

	/**
	 * Extract a readable title from stored notice HTML.
	 *
	 * @param string $html Notice HTML.
	 * @return string
	 */
	private function notice_title_from_html( $html ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $html ) ) );
		if ( '' === $text ) {
			return __( 'Captured admin notice', 'papy3d-noticeshield' );
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 120 ) : substr( $text, 0, 120 );
	}

	/**
	 * Guess source label from log item.
	 *
	 * @param array $item Log item.
	 * @return string
	 */
	private function notice_source_from_item( $item ) {
		$html = isset( $item['html'] ) ? (string) $item['html'] : '';
		if ( preg_match( '/data-cp-notification-name=["\']([^"\']+)/i', $html, $m ) ) {
			$parts = explode( '_', sanitize_text_field( $m[1] ) );
			return ucwords( str_replace( array( '-', '_' ), ' ', implode( ' ', array_slice( $parts, 0, 2 ) ) ) );
		}
		if ( preg_match( '/class=["\'][^"\']*?([a-z0-9-]+)-(?:notice|admin-notice|notification)/i', $html, $m ) ) {
			return ucwords( str_replace( '-', ' ', sanitize_text_field( $m[1] ) ) );
		}
		return isset( $item['context'] ) ? sanitize_key( $item['context'] ) : __( 'WordPress', 'papy3d-noticeshield' );
	}

	/**
	 * Human readable decision description.
	 *
	 * @param string $decision Decision.
	 * @return string
	 */
	private function decision_description( $decision ) {
		if ( 'allow' === $decision ) {
			return __( 'You chose to display this notice.', 'papy3d-noticeshield' );
		}
		if ( 'block' === $decision ) {
			return __( 'You chose to hide this notice.', 'papy3d-noticeshield' );
		}
		return __( 'No choice yet. The notice will ask again next time it appears.', 'papy3d-noticeshield' );
	}

	/**
	 * Build source statistics from captured notices.
	 *
	 * @param array $log Notice log.
	 * @return array<string,array<string,mixed>>
	 */
	private function source_stats( $log ) {
		$stats = array();

		foreach ( $log as $papy3d_ns_item ) {
			if ( ! is_array( $papy3d_ns_item ) ) {
				continue;
			}

			$papy3d_ns_source   = $this->notice_source_from_item( $papy3d_ns_item );
			$papy3d_ns_key      = sanitize_title( $papy3d_ns_source );
			$papy3d_ns_decision = isset( $papy3d_ns_item['decision'] ) ? sanitize_key( (string) $papy3d_ns_item['decision'] ) : 'pending';
			$papy3d_ns_decision = in_array( $papy3d_ns_decision, array( 'allow', 'block' ), true ) ? $papy3d_ns_decision : 'pending';

			if ( '' === $papy3d_ns_key ) {
				$papy3d_ns_key = 'unknown';
			}

			if ( ! isset( $stats[ $papy3d_ns_key ] ) ) {
				$stats[ $papy3d_ns_key ] = array(
					'label'     => $papy3d_ns_source,
					'total'     => 0,
					'allow'     => 0,
					'block'     => 0,
					'pending'   => 0,
					'last_seen' => '',
				);
			}

			$stats[ $papy3d_ns_key ]['total']++;
			$stats[ $papy3d_ns_key ][ $papy3d_ns_decision ]++;

			if ( ! empty( $papy3d_ns_item['last_seen'] ) && ( '' === $stats[ $papy3d_ns_key ]['last_seen'] || strcmp( (string) $papy3d_ns_item['last_seen'], (string) $stats[ $papy3d_ns_key ]['last_seen'] ) > 0 ) ) {
				$stats[ $papy3d_ns_key ]['last_seen'] = (string) $papy3d_ns_item['last_seen'];
			}
		}

		uasort(
			$stats,
			static function ( $papy3d_ns_a, $papy3d_ns_b ) {
				return (int) $papy3d_ns_b['total'] <=> (int) $papy3d_ns_a['total'];
			}
		);

		return $stats;
	}

	/**
	 * Human-readable status.
	 *
	 * @param string $decision Decision.
	 * @return string
	 */
	private function human_status( $decision ) {
		if ( 'allow' === $decision ) {
			return __( 'Allowed', 'papy3d-noticeshield' );
		}

		if ( 'block' === $decision ) {
			return __( 'Blocked', 'papy3d-noticeshield' );
		}

		return __( 'Pending', 'papy3d-noticeshield' );
	}

}
