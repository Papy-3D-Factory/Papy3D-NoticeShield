<?php
/**
 * Admin page view.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
		<div class="wrap papy3d-ns-wrap">
			<div class="papy3d-ns-page-header">
				<div class="papy3d-ns-title-wrap">
					<h1><span class="dashicons dashicons-shield"></span><?php echo esc_html__( 'Papy3D NoticeShield', 'papy3d-noticeshield' ); ?> <span class="papy3d-ns-version"><?php echo esc_html( PAPY3D_NS_VERSION ); ?></span></h1>
					<p><?php echo esc_html__( 'Manage intrusive third-party admin notices from one dedicated screen. Core workflow notices are preserved when Safe Core mode is enabled.', 'papy3d-noticeshield' ); ?></p>
				</div>
				<div class="papy3d-ns-header-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'papy3d_ns_bulk_action' ); ?>
						<input type="hidden" name="action" value="papy3d_ns_bulk_action" />
						<input type="hidden" name="papy3d_ns_action" value="pause_1h" />
						<button class="button papy3d-ns-button-warning" type="submit"><?php echo esc_html__( 'Pause 1 hour', 'papy3d-noticeshield' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'papy3d_ns_bulk_action' ); ?>
						<input type="hidden" name="action" value="papy3d_ns_bulk_action" />
						<input type="hidden" name="papy3d_ns_action" value="clear_history" />
						<button class="button papy3d-ns-button-clear" type="submit" data-confirm="<?php echo esc_attr__( 'Clear captured notice history while keeping all allow/block rules?', 'papy3d-noticeshield' ); ?>"><?php echo esc_html__( 'Clear history', 'papy3d-noticeshield' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'papy3d_ns_bulk_action' ); ?>
						<input type="hidden" name="action" value="papy3d_ns_bulk_action" />
						<input type="hidden" name="papy3d_ns_action" value="reset_all" />
						<button class="button button-secondary papy3d-ns-confirm-reset" type="submit" data-confirm="<?php echo esc_attr__( 'Reset all captured notices, allow/block rules, decision journal and temporary mutes?', 'papy3d-noticeshield' ); ?>"><?php echo esc_html__( 'Reset all', 'papy3d-noticeshield' ); ?></button>
					</form>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'tools.php?page=papy3d-noticeshield' ) ); ?>"><?php echo esc_html__( 'Reload list', 'papy3d-noticeshield' ); ?></a>
				</div>
			</div>

			<div id="papy3d-ns-debug-panel" class="papy3d-ns-debug-panel" aria-live="polite"></div>

			<div class="notice notice-info inline"><p><?php echo esc_html__( 'Papy3D NoticeShield does not delete notices. With Safe Core mode enabled, WordPress Core workflow notices are preserved outside this management screen.', 'papy3d-noticeshield' ); ?></p></div>

			<?php if ( $this->is_safe_core_mode_enabled() ) : ?>
				<div class="notice notice-success inline"><p><?php echo esc_html__( 'Safe Core mode is active: NoticeShield will not apply allow/block decisions to WordPress Core workflow notices such as updates, maintenance and critical admin messages.', 'papy3d-noticeshield' ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html__( 'Safe Core mode is disabled. WordPress Core workflow notices can be reviewed like other notices. This is not recommended for production sites.', 'papy3d-noticeshield' ); ?></p></div>
			<?php endif; ?>

			<?php if ( is_multisite() ) : ?>
				<div class="notice notice-info inline"><p><?php echo esc_html__( 'Multisite note: rules and history are stored per site, not network-wide.', 'papy3d-noticeshield' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $this->is_temporarily_paused() ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( /* translators: %s: pause expiration date and time. */ __( 'NoticeShield is paused until %s. Third-party notices are temporarily displayed normally.', 'papy3d-noticeshield' ), get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $this->get_paused_until() ), 'Y-m-d H:i' ) ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Informational flag only. ?>
				<div class="notice notice-success is-dismissible inline papy3d-ns-message"><p><?php echo esc_html__( 'Settings updated.', 'papy3d-noticeshield' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Informational flag only. ?>
				<div class="notice notice-success is-dismissible inline papy3d-ns-message"><p><?php echo esc_html__( 'Rules imported successfully.', 'papy3d-noticeshield' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['import_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Informational flag only. ?>
				<div class="notice notice-error is-dismissible inline papy3d-ns-message"><p><?php echo esc_html__( 'Import failed. Please use a valid Papy3D NoticeShield JSON export file.', 'papy3d-noticeshield' ); ?></p></div>
			<?php endif; ?>

			<?php $papy3d_ns_source_stats = $this->source_stats( $papy3d_ns_log ); $papy3d_ns_settings = $this->get_settings(); ?>
			<div class="papy3d-ns-dashboard-grid" aria-label="<?php echo esc_attr__( 'Notice statistics', 'papy3d-noticeshield' ); ?>">
				<div class="papy3d-ns-stat"><strong><?php echo esc_html( $papy3d_ns_counts['all'] ); ?></strong><span><?php echo esc_html__( 'Captured notices', 'papy3d-noticeshield' ); ?></span></div>
				<div class="papy3d-ns-stat"><strong><?php echo esc_html( $papy3d_ns_counts['pending'] ); ?></strong><span><?php echo esc_html__( 'Waiting for review', 'papy3d-noticeshield' ); ?></span></div>
				<div class="papy3d-ns-stat"><strong><?php echo esc_html( $papy3d_ns_counts['block'] ); ?></strong><span><?php echo esc_html__( 'Hidden notices', 'papy3d-noticeshield' ); ?></span></div>
				<div class="papy3d-ns-stat"><strong><?php echo esc_html( count( $papy3d_ns_source_stats ) ); ?></strong><span><?php echo esc_html__( 'Detected sources', 'papy3d-noticeshield' ); ?></span></div>
			</div>

			<div class="papy3d-ns-section">
				<h2><?php echo esc_html__( 'Source control', 'papy3d-noticeshield' ); ?></h2>
				<p><?php echo esc_html__( 'Review sources at a glance and allow or block every captured notice from a specific source.', 'papy3d-noticeshield' ); ?></p>
				<table class="papy3d-ns-source-table">
					<thead><tr><th><?php echo esc_html__( 'Source', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'Total', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'Allowed', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'Blocked', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'Pending', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'Last seen', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'Actions', 'papy3d-noticeshield' ); ?></th></tr></thead>
					<tbody>
					<?php if ( empty( $papy3d_ns_source_stats ) ) : ?>
						<tr><td colspan="7"><?php echo esc_html__( 'No source detected yet.', 'papy3d-noticeshield' ); ?></td></tr>
					<?php else : foreach ( $papy3d_ns_source_stats as $papy3d_ns_source_key => $papy3d_ns_source ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $papy3d_ns_source['label'] ); ?></strong></td><td><?php echo esc_html( $papy3d_ns_source['total'] ); ?></td><td><?php echo esc_html( $papy3d_ns_source['allow'] ); ?></td><td><?php echo esc_html( $papy3d_ns_source['block'] ); ?></td><td><?php echo esc_html( $papy3d_ns_source['pending'] ); ?></td><td><?php echo esc_html( $papy3d_ns_source['last_seen'] ); ?></td>
							<td><form class="papy3d-ns-source-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'papy3d_ns_bulk_action' ); ?><input type="hidden" name="action" value="papy3d_ns_bulk_action" /><input type="hidden" name="papy3d_ns_source_key" value="<?php echo esc_attr( $papy3d_ns_source_key ); ?>" /><button class="button" type="submit" name="papy3d_ns_source_action" value="allow_source"><?php echo esc_html__( 'Allow source', 'papy3d-noticeshield' ); ?></button><button class="button papy3d-ns-button-danger" type="submit" name="papy3d_ns_source_action" value="block_source"><?php echo esc_html__( 'Block source', 'papy3d-noticeshield' ); ?></button></form></td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>

			<div class="papy3d-ns-section">
				<h2><?php echo esc_html__( 'Settings and portability', 'papy3d-noticeshield' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'papy3d_ns_save_settings' ); ?><input type="hidden" name="action" value="papy3d_ns_save_settings" />
					<div class="papy3d-ns-settings-grid">
						<div class="papy3d-ns-setting"><label><input type="checkbox" name="safe_core" <?php checked( ! empty( $papy3d_ns_settings['safe_core'] ) ); ?> /> <?php echo esc_html__( 'Safe Core mode', 'papy3d-noticeshield' ); ?></label><p><?php echo esc_html__( 'Never alter WordPress Core workflow notices.', 'papy3d-noticeshield' ); ?></p></div>
						<div class="papy3d-ns-setting"><label><input type="checkbox" name="learning_mode" <?php checked( ! empty( $papy3d_ns_settings['learning_mode'] ) ); ?> /> <?php echo esc_html__( 'Learning mode', 'papy3d-noticeshield' ); ?></label><p><?php echo esc_html__( 'When enabled, unknown notices get review controls. When disabled, existing rules still apply but unknown notices display normally.', 'papy3d-noticeshield' ); ?></p></div>
						<div class="papy3d-ns-setting"><label><input type="checkbox" name="show_placeholders" <?php checked( ! empty( $papy3d_ns_settings['show_placeholders'] ) ); ?> /> <?php echo esc_html__( 'Blocked notice placeholder', 'papy3d-noticeshield' ); ?></label><p><?php echo esc_html__( 'Show a small placeholder instead of silently hiding blocked notices.', 'papy3d-noticeshield' ); ?></p></div>
					</div>
					<p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save settings', 'papy3d-noticeshield' ); ?></button></p>
				</form>
				<div class="papy3d-ns-source-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'papy3d_ns_export_rules' ); ?><input type="hidden" name="action" value="papy3d_ns_export_rules" /><button class="button" type="submit"><?php echo esc_html__( 'Export rules', 'papy3d-noticeshield' ); ?></button></form></div>
				<form class="papy3d-ns-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'papy3d_ns_import_rules' ); ?><input type="hidden" name="action" value="papy3d_ns_import_rules" /><label for="papy3d-ns-import-json"><strong><?php echo esc_html__( 'Import JSON rules', 'papy3d-noticeshield' ); ?></strong></label><textarea id="papy3d-ns-import-json" name="papy3d_ns_import_json" class="large-text code" rows="4"></textarea><p><button class="button" type="submit"><?php echo esc_html__( 'Import rules', 'papy3d-noticeshield' ); ?></button></p></form>
			</div>

			<div class="papy3d-ns-section">
				<h2><?php echo esc_html__( 'Decision journal', 'papy3d-noticeshield' ); ?></h2>
				<table class="papy3d-ns-history-table"><thead><tr><th><?php echo esc_html__( 'Date', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'User', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'Decision', 'papy3d-noticeshield' ); ?></th><th><?php echo esc_html__( 'Signature', 'papy3d-noticeshield' ); ?></th></tr></thead><tbody><?php $papy3d_ns_decision_log = $this->get_decision_log(); if ( empty( $papy3d_ns_decision_log ) ) : ?><tr><td colspan="4"><?php echo esc_html__( 'No decision recorded yet.', 'papy3d-noticeshield' ); ?></td></tr><?php else : foreach ( array_slice( $papy3d_ns_decision_log, 0, 20 ) as $papy3d_ns_entry ) : ?><tr><td><?php echo esc_html( isset( $papy3d_ns_entry['time'] ) ? $papy3d_ns_entry['time'] : '' ); ?></td><td><?php echo esc_html( isset( $papy3d_ns_entry['user'] ) ? $papy3d_ns_entry['user'] : '' ); ?></td><td><?php echo esc_html( isset( $papy3d_ns_entry['decision'] ) ? $papy3d_ns_entry['decision'] : '' ); ?></td><td><code><?php echo esc_html( isset( $papy3d_ns_entry['signature'] ) ? substr( (string) $papy3d_ns_entry['signature'], 0, 12 ) : '' ); ?></code></td></tr><?php endforeach; endif; ?></tbody></table>
			</div>

			<div class="papy3d-ns-toolbar">
				<div class="papy3d-ns-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Notice filters', 'papy3d-noticeshield' ); ?>">
					<button type="button" class="papy3d-ns-tab is-active" data-filter="all"><?php echo esc_html__( 'All', 'papy3d-noticeshield' ); ?> (<?php echo esc_html( $papy3d_ns_counts['all'] ); ?>)</button>
					<button type="button" class="papy3d-ns-tab" data-filter="allow"><?php echo esc_html__( 'Allowed', 'papy3d-noticeshield' ); ?> (<?php echo esc_html( $papy3d_ns_counts['allow'] ); ?>)</button>
					<button type="button" class="papy3d-ns-tab" data-filter="block"><?php echo esc_html__( 'Blocked', 'papy3d-noticeshield' ); ?> (<?php echo esc_html( $papy3d_ns_counts['block'] ); ?>)</button>
					<button type="button" class="papy3d-ns-tab" data-filter="pending"><?php echo esc_html__( 'Ask again', 'papy3d-noticeshield' ); ?> (<?php echo esc_html( $papy3d_ns_counts['pending'] ); ?>)</button>
				</div>
				<div class="papy3d-ns-controls">
					<input type="search" id="papy3d-ns-search" placeholder="<?php echo esc_attr__( 'Search a notice...', 'papy3d-noticeshield' ); ?>" />
					<select id="papy3d-ns-source-filter" aria-label="<?php echo esc_attr__( 'Filter by source', 'papy3d-noticeshield' ); ?>">
						<option value=""><?php echo esc_html__( 'All sources', 'papy3d-noticeshield' ); ?></option>
					</select>
				</div>
			</div>

			<?php if ( empty( $papy3d_ns_log ) ) : ?>
				<div class="papy3d-ns-empty"><p><?php echo esc_html__( 'No admin notices have been captured yet.', 'papy3d-noticeshield' ); ?></p></div>
			<?php else : ?>
				<div class="papy3d-ns-bulk-row papy3d-ns-muted"><?php echo esc_html__( 'The notices displayed at the top of this admin page are hidden. Use “View notice” inside a card to inspect the captured content.', 'papy3d-noticeshield' ); ?></div>

					<?php foreach ( $papy3d_ns_log as $papy3d_ns_item ) : ?>
						<?php
						$papy3d_ns_signature = isset( $papy3d_ns_item['signature'] ) ? sanitize_text_field( (string) $papy3d_ns_item['signature'] ) : '';
						$papy3d_ns_decision  = $this->is_valid_signature( $papy3d_ns_signature ) ? $this->get_effective_decision( $papy3d_ns_signature ) : 'pending';
						$papy3d_ns_html      = isset( $papy3d_ns_item['html'] ) ? (string) $papy3d_ns_item['html'] : '';
						$papy3d_ns_title     = $this->notice_title_from_html( $papy3d_ns_html );
						$papy3d_ns_source    = $this->notice_source_from_item( $papy3d_ns_item );
						$papy3d_ns_family    = $this->notice_family_from_html( $papy3d_ns_html );
						$papy3d_ns_classes   = 'papy3d-ns-card is-' . $papy3d_ns_decision;
						?>
						<article class="<?php echo esc_attr( $papy3d_ns_classes ); ?>" data-status="<?php echo esc_attr( $papy3d_ns_decision ); ?>" data-source="<?php echo esc_attr( strtolower( $papy3d_ns_source ) ); ?>" data-source-label="<?php echo esc_attr( $papy3d_ns_source ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $papy3d_ns_html . ' ' . $papy3d_ns_signature . ' ' . $papy3d_ns_source ) ) ); ?>">
							<div class="papy3d-ns-card-header">
								<div>
									<p class="papy3d-ns-card-title"><?php echo esc_html( $papy3d_ns_title ); ?></p>
									<p class="papy3d-ns-card-summary"><?php echo esc_html( $this->notice_summary_from_html( $papy3d_ns_html ) ); ?></p>
									<div class="papy3d-ns-meta">
										<span class="papy3d-ns-chip"><?php echo esc_html__( 'Source:', 'papy3d-noticeshield' ); ?> <?php echo esc_html( $papy3d_ns_source ); ?></span>
										<span class="papy3d-ns-chip"><?php echo esc_html__( 'Detected:', 'papy3d-noticeshield' ); ?> <?php echo esc_html( isset( $papy3d_ns_item['last_seen'] ) ? $papy3d_ns_item['last_seen'] : '' ); ?></span>
										<span class="papy3d-ns-chip"><?php echo esc_html__( 'Seen:', 'papy3d-noticeshield' ); ?> <?php echo esc_html( isset( $papy3d_ns_item['count'] ) ? absint( $papy3d_ns_item['count'] ) : 1 ); ?></span>
										<?php if ( ! empty( $papy3d_ns_item['last_seen_page'] ) ) : ?>
											<span class="papy3d-ns-chip"><?php echo esc_html__( 'Last page:', 'papy3d-noticeshield' ); ?> <?php echo esc_html( $this->short_admin_page_label( (string) $papy3d_ns_item['last_seen_page'] ) ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $papy3d_ns_family ) : ?>
											<span class="papy3d-ns-chip papy3d-ns-chip-family"><?php echo esc_html__( 'Family rule', 'papy3d-noticeshield' ); ?></span>
										<?php endif; ?>
									</div>
								</div>
								<span class="papy3d-ns-id"><?php echo esc_html__( 'ID:', 'papy3d-noticeshield' ); ?> <?php echo esc_html( substr( $papy3d_ns_signature, 0, 7 ) ); ?></span>
							</div>
							<div class="papy3d-ns-card-preview" id="papy3d-ns-preview-<?php echo esc_attr( substr( $papy3d_ns_signature, 0, 12 ) ); ?>" hidden></div>
							<textarea class="papy3d-ns-notice-template" hidden readonly><?php echo esc_textarea( wp_kses_post( $papy3d_ns_html ) ); ?></textarea>
							<div class="papy3d-ns-card-details">
								<div>
									<span class="papy3d-ns-detail-label"><?php echo esc_html__( 'Signature (identifier)', 'papy3d-noticeshield' ); ?></span>
									<code class="papy3d-ns-signature"><?php echo esc_html( $papy3d_ns_signature ); ?></code>
								</div>
								<div>
									<span class="papy3d-ns-detail-label"><?php echo esc_html__( 'Your choice', 'papy3d-noticeshield' ); ?></span>
									<span class="papy3d-ns-status-badge papy3d-ns-badge-<?php echo esc_attr( $papy3d_ns_decision ); ?>"><?php echo esc_html( $this->human_status( $papy3d_ns_decision ) ); ?></span>
									<span class="papy3d-ns-muted"><?php echo esc_html( $this->decision_description( $papy3d_ns_decision ) ); ?></span>
								</div>
								<div class="papy3d-ns-card-actions">
									<?php $this->render_card_action_form( $papy3d_ns_signature, $papy3d_ns_decision ); ?>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
			<?php endif; ?>
		</div>
