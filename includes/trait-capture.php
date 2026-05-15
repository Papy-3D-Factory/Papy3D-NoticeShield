<?php
/**
 * Papy3D_NoticeShield_Capture_Trait.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Papy3D_NoticeShield_Capture_Trait {

	/** Start all-admin-notices buffer. */
	public function start_all_admin_notices_buffer() { $this->start_notice_buffer( 'all_admin_notices' ); }

	/** Start admin-notices buffer. */
	public function start_admin_notices_buffer() { $this->start_notice_buffer( 'admin_notices' ); }

	/** Start network-admin-notices buffer. */
	public function start_network_admin_notices_buffer() { $this->start_notice_buffer( 'network_admin_notices' ); }

	/** Start user-admin-notices buffer. */
	public function start_user_admin_notices_buffer() { $this->start_notice_buffer( 'user_admin_notices' ); }

	/**
	 * Open output buffer for a notice hook.
	 *
	 * @param string $context Context name.
	 */
	private function start_notice_buffer( $context ) {
		if ( $this->buffering || ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->is_unsafe_buffer_context() ) {
			return;
		}

		$this->buffer_context = sanitize_key( $context );
		$this->buffering      = true;
		ob_start();
	}

	/**
	 * Check contexts where output buffering must not be used.
	 *
	 * @return bool
	 */
	private function is_unsafe_buffer_context() {
		if ( wp_doing_ajax() || wp_is_json_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}

		return ob_get_level() > 5;
	}

	/**
	 * Close and process output buffer.
	 */
	public function end_notice_buffer() {
		if ( ! $this->buffering ) {
			return;
		}

		$content              = ob_get_level() > 0 ? (string) ob_get_clean() : '';
		$this->buffering      = false;
		$context              = $this->buffer_context;
		$this->buffer_context = '';

		echo wp_kses(
			$this->escape_notice_html( $this->process_notice_output( $content, $context ) ),
			$this->allowed_notice_html()
		);
	}

	/**
	 * Check if current screen is the plugin page.
	 *
	 * @return bool
	 */
	private function is_own_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
		if ( 'papy3d-noticeshield' === $page ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && isset( $screen->id ) && false !== strpos( $screen->id, 'papy3d-noticeshield' );
	}

	/**
	 * Return the allowed HTML map for captured admin notices.
	 *
	 * WordPress admin notices often contain links, buttons, forms and simple
	 * structural markup. The map intentionally excludes script, iframe, object
	 * and event-handler attributes so captured notices are escaped at the last
	 * possible moment before output.
	 *
	 * @return array<string,array<string,bool|array|string>>
	 */
	private function allowed_notice_html() {
		$allowed = wp_kses_allowed_html( 'post' );

		$extra_tags = array(
			'div',
			'span',
			'p',
			'strong',
			'em',
			'b',
			'i',
			'ul',
			'ol',
			'li',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'a',
			'button',
			'form',
			'input',
			'label',
			'code',
			'br',
			'small',
		);

		$global_attributes = array(
			'id'                         => true,
			'class'                      => true,
			'title'                      => true,
			'role'                       => true,
			'aria-label'                 => true,
			'aria-live'                  => true,
			'aria-hidden'                => true,
			'data-papy3d-ns-internal'  => true,
			'data-papy3d-ns-status'    => true,
			'data-papy3d-ns-signature' => true,
		);

		foreach ( $extra_tags as $tag ) {
			if ( ! isset( $allowed[ $tag ] ) ) {
				$allowed[ $tag ] = array();
			}
			$allowed[ $tag ] = array_merge( $allowed[ $tag ], $global_attributes );
		}

		$allowed['a'] = array_merge(
			isset( $allowed['a'] ) ? $allowed['a'] : array(),
			array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			)
		);

		$allowed['form'] = array_merge(
			$allowed['form'],
			array(
				'action' => true,
				'method' => true,
			)
		);

		$allowed['input'] = array_merge(
			$allowed['input'],
			array(
				'type'     => true,
				'name'     => true,
				'value'    => true,
				'id'       => true,
				'checked'  => true,
				'disabled' => true,
			)
		);

		$allowed['button'] = array_merge(
			$allowed['button'],
			array(
				'type'     => true,
				'name'     => true,
				'value'    => true,
				'disabled' => true,
			)
		);

		return $allowed;
	}

	/**
	 * Escape captured notice HTML immediately before output.
	 *
	 * @param string $html Captured HTML.
	 * @return string
	 */
	private function escape_notice_html( $html ) {
		$html = (string) $html;
		$html = preg_replace( '/<' . 'script\b[^>]*>.*?<\/' . 'script>/is', '', $html );
		$html = preg_replace( '/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html );
		$html = preg_replace( '/<object\b[^>]*>.*?<\/object>/is', '', $html );
		$html = preg_replace( '/<embed\b[^>]*>.*?<\/embed>/is', '', $html );
		$html = preg_replace( '/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $html );
		$html = preg_replace( '/(href|src|action)\s*=\s*(["\'])\s*javascript:[^"\']*\2/is', '$1="#"', $html );

		return wp_kses( (string) $html, $this->allowed_notice_html() );
	}

	/**
	 * Process notice HTML.
	 *
	 * @param string $html Raw notice HTML.
	 * @param string $context Hook context.
	 * @return string
	 */
	private function process_notice_output( $html, $context ) {
		if ( '' === trim( $html ) ) {
			return '';
		}


		$parts = $this->split_notice_output( $html );
		if ( empty( $parts ) ) {
			return $this->process_single_notice( $html, $context );
		}

		$output     = '';
		$own_screen = $this->is_own_screen();

		foreach ( $parts as $part ) {
			if ( ! isset( $part['type'], $part['html'] ) || '' === trim( (string) $part['html'] ) ) {
				continue;
			}

			if ( 'notice' === $part['type'] ) {
				$output .= $this->process_single_notice( (string) $part['html'], $context );
			} elseif ( ! $own_screen ) {

				if ( ! $this->is_style_or_script_only_fragment( (string) $part['html'] ) ) {
					$output .= (string) $part['html'];
				}
			}
		}

		return $own_screen ? '' : $output;
	}

	/**
	 * Process one notice independently.
	 *
	 * @param string $html Raw notice HTML.
	 * @param string $context Hook context.
	 * @return string
	 */
	private function process_single_notice( $html, $context ) {
		$html = $this->strip_internal_markup( $html );
		
		if ( $this->is_style_or_script_only_fragment( $html ) ) {
			return '';
		}

		if ( $this->is_temporarily_paused() ) {
			return $html;
		}

		if ( $this->is_safe_core_mode_enabled() && $this->is_core_notice_html( $html ) ) {
			return $html;
		}

		$signature = $this->signature_from_html( $html, $context );
		$rules     = $this->get_rules();
		$decision  = $this->get_effective_decision( $signature );
		$decision  = $this->inherited_dynamic_family_decision( $signature, $html, $decision );

		$this->log_notice( $signature, $html, $context, $decision );

		if ( 'pending' === $decision && ! $this->is_learning_mode_enabled() ) {
			return $html;
		}

		if ( $this->is_own_screen() ) {
			return '';
		}

		if ( 'block' === $decision ) {
			$settings = $this->get_settings();
			if ( ! empty( $settings['show_placeholders'] ) ) {
				return '<div class="papy3d-ns-placeholder" data-papy3d-ns-signature="' . esc_attr( $signature ) . '"><strong>' . esc_html__( 'Notice hidden by Papy3D NoticeShield.', 'papy3d-noticeshield' ) . '</strong> <a href="' . esc_url( admin_url( 'tools.php?page=papy3d-noticeshield' ) ) . '">' . esc_html__( 'Review', 'papy3d-noticeshield' ) . '</a></div>';
			}
			return '';
		}

		/*
		 * The captured markup is output generated by active admin plugins/themes, not by a visitor.
		 * It is preserved here so original notice buttons, dismiss links, styles and scripts keep working.
		 */
		$decision_box = 'allow' !== $decision ? $this->render_decision_box( $signature ) : '';
		$notice_html  = $decision_box ? $this->inject_decision_box_into_notice( $html, $decision_box, $signature, $decision ) : $html;

		return '<div class="papy3d-ns-captured-notice' . ( $decision_box ? ' papy3d-ns-has-decision' : '' ) . '" data-papy3d-ns-signature="' . esc_attr( $signature ) . '" data-papy3d-ns-status="' . esc_attr( $decision ) . '">' . $notice_html . '</div>';
	}

	/**
	 * Check whether the current admin request is a WordPress Core workflow where notices must stay untouched.
	 *
	 * @return bool
	 */
	private function is_core_admin_workflow_request() {
		global $pagenow;

		$page = is_string( $pagenow ) ? $pagenow : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only for read-only workflow detection.

		$core_pages = array(
			'update.php',
			'update-core.php',
			'plugin-install.php',
			'theme-install.php',
			'site-health.php',
		);

		if ( in_array( $page, $core_pages, true ) ) {
			return true;
		}

		if ( 'plugins.php' === $page && false !== strpos( $request_uri, 'action=upload-plugin' ) ) {
			return true;
		}

		if ( 'themes.php' === $page && false !== strpos( $request_uri, 'action=upload-theme' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether a URL points to a WordPress Core admin workflow.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_core_admin_workflow_url( $url ) {
		if ( '' === $url ) {
			return false;
		}

		$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$file  = basename( $path );

		if ( in_array( $file, array( 'update.php', 'update-core.php', 'plugin-install.php', 'theme-install.php', 'site-health.php' ), true ) ) {
			return true;
		}

		parse_str( $query, $params );

		if ( 'plugins.php' === $file && isset( $params['action'] ) && 'upload-plugin' === $params['action'] ) {
			return true;
		}

		if ( 'themes.php' === $file && isset( $params['action'] ) && 'upload-theme' === $params['action'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Detect WordPress Core notices that should not be captured or altered.
	 *
	 * @param string $html Notice HTML.
	 * @return bool
	 */
	private function is_core_notice_html( $html ) {
		$text = $this->normalize_notice_text( $html );
		if ( '' === $text ) {
			return false;
		}

		$core_patterns = array(
			'plugin activated',
			'plugin deactivated',
			'plugin deleted',
			'plugin updated successfully',
			'plugin installed successfully',
			'theme activated',
			'theme updated successfully',
			'theme installed successfully',
			'wordpress has been updated',
			'automatic update',
			'update available',
			'update complete',
			'settings saved',
			'this plugin is already installed',
			'do you want to replace the current',
			'upload plugin',
			'upload theme',
			'installation de l extension',
			'extension activee',
			'extension activée',
			'extension desactivee',
			'extension désactivée',
			'extension supprimee',
			'extension supprimée',
			'extension mise a jour',
			'extension mise à jour',
			'extension installee',
			'extension installée',
			'cette extension est deja installee',
			'cette extension est déjà installée',
			'voulez vous remplacer',
			'voulez-vous remplacer',
			'televerser une extension',
			'téléverser une extension',
			'mise a jour de wordpress',
			'mise à jour de wordpress',
			'theme active',
			'thème activé',
			'theme installe',
			'thème installé',
			'parametres enregistres',
			'paramètres enregistrés',
		);

		foreach ( $core_patterns as $pattern ) {
			if ( false !== strpos( $text, $pattern ) ) {
				return true;
			}
		}

		$lower_html = strtolower( $html );
		if ( false !== strpos( $lower_html, 'id="message"' ) || false !== strpos( $lower_html, "id='message'" ) ) {
			$core_short_patterns = array(
				'extension activee',
				'extension activée',
				'plugin activated',
				'parametres enregistres',
				'paramètres enregistrés',
				'settings saved',
			);
			foreach ( $core_short_patterns as $pattern ) {
				if ( 0 === strpos( $text, $pattern ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Split a notice hook output into independent top-level notices.
	 *
	 * @param string $html Raw hook output.
	 * @return array<int,array{type:string,html:string}>
	 */
	private function split_notice_output( $html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return array( array( 'type' => 'notice', 'html' => $html ) );
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument();
		$loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?><div id="papy3d-ns-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return array( array( 'type' => 'notice', 'html' => $html ) );
		}

		$root = null;
		foreach ( $dom->getElementsByTagName( 'div' ) as $div ) {
			if ( 'papy3d-ns-root' === $div->getAttribute( 'id' ) ) {
				$root = $div;
				break;
			}
		}

		if ( ! $root ) {
			return array( array( 'type' => 'notice', 'html' => $html ) );
		}

		$parts        = array();
		$current_key  = null;
		$child_nodes  = array();
		foreach ( $root->childNodes as $child ) {
			$child_nodes[] = $child;
		}

		foreach ( $child_nodes as $child ) {
			$node_html = $dom->saveHTML( $child );
			if ( false === $node_html || '' === trim( $node_html ) ) {
				continue;
			}

			if ( $this->dom_node_is_notice( $child ) ) {
				$parts[]     = array( 'type' => 'notice', 'html' => $node_html );
				$current_key = count( $parts ) - 1;
				continue;
			}

			if ( null !== $current_key && $this->dom_node_belongs_to_previous_notice( $child ) ) {
				$parts[ $current_key ]['html'] .= $node_html;
				continue;
			}

			$parts[]     = array( 'type' => 'other', 'html' => $node_html );
			$current_key = null;
		}

		return $parts;
	}

	/**
	 * Check whether a DOM node is an admin notice container.
	 *
	 * @param DOMNode $node Node.
	 * @return bool
	 */
	private function dom_node_is_notice( $node ) {
		if ( ! $node instanceof DOMElement ) {
			return false;
		}

		$tag = strtolower( $node->tagName );
		if ( ! in_array( $tag, array( 'div', 'section', 'aside' ), true ) ) {
			return false;
		}

		$classes = ' ' . strtolower( $node->getAttribute( 'class' ) ) . ' ';
		if ( preg_match( '/\s(?:notice|updated|error|update-nag|admin-notice)\b/', $classes ) ) {
			return true;
		}

		if ( preg_match( '/(?:notification|notice|nag|alert|promo)/', $classes ) ) {
			return true;
		}

		foreach ( array( 'data-cp-notification-name', 'data-notice', 'data-notification', 'data-notice-id' ) as $attribute ) {
			if ( $node->hasAttribute( $attribute ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a DOM node is auxiliary markup for the preceding notice.
	 *
	 * @param DOMNode $node Node.
	 * @return bool
	 */
	private function dom_node_belongs_to_previous_notice( $node ) {
		if ( ! $node instanceof DOMElement ) {
			return false;
		}

		$tag = strtolower( $node->tagName );

		/*
		 * Keep JavaScript attached to the previous notice because some plugins
		 * initialize dismiss buttons dynamically.
		 *
		 * Do NOT attach standalone style blocks because they become orphan
		 * fragments rendered as fake notices.
		 */
		return 'script' === $tag;
	}

	/**
	 * Insert the decision controls inside the notice markup itself.
	 *
	 * This keeps each Allow/Block choice visually attached to the notice it controls,
	 * instead of printing all choice bars before the real notices. Pending notices are
	 * also marked on the root node so the client-side scanner never cleans their
	 * decision controls during a later MutationObserver pass.
	 *
	 * @param string $html Raw notice HTML.
	 * @param string $decision_box Decision controls HTML.
	 * @param string $signature Notice signature.
	 * @param string $decision Current decision.
	 * @return string
	 */
	private function inject_decision_box_into_notice( $html, $decision_box, $signature = '', $decision = 'pending' ) {
		$pattern = '/(<(?P<tag>div|section|aside)\b(?=[^>]*(?:class\s*=\s*(["\'])(?:(?!\3).)*(?:notice|updated|error|update-nag)(?:(?!\3).)*\3|data-(?:cp-notification-name|notice|notice-id)\s*=))[^>]*)(>)/i';
		$attrs   = sprintf(
			' data-papy3d-ns-status="%1$s" data-papy3d-ns-signature="%2$s"',
			esc_attr( sanitize_key( $decision ) ),
			esc_attr( $signature )
		);

		$result = preg_replace( $pattern, '$1' . $attrs . '$4' . $decision_box, $html, 1, $count );
		if ( is_string( $result ) && $count > 0 ) {
			return $result;
		}

		return $decision_box . $html;
	}

	/**
	 * Build a stable notice signature.
	 *
	 * The signature deliberately ignores volatile values such as nonces, tokens,
	 * timestamps, version numbers, long IDs, counters and URLs. This prevents the
	 * same notice from being rediscovered as a new notice on every page load.
	 *
	 * @param string $html Raw HTML.
	 * @param string $context Hook context.
	 * @return string
	 */
	private function signature_from_html( $html, $context ) {
		$html   = $this->strip_internal_markup( $html );
		$family = $this->notice_family_from_html( $html );

		if ( '' !== $family ) {
			return hash( 'sha256', 'family|' . $family );
		}

		$source   = $this->source_fingerprint_from_html( $html );
		$text     = $this->normalize_notice_text( $html );
		$identity = $this->normalize_notice_identity( $html );

		if ( '' === $text ) {
			$text = $identity;
		}

		/*
		 * Do not include the hook context in the final signature. The same notice may
		 * be captured once by the PHP buffer and later by the DOM scanner; including
		 * the context would turn one visual notice into several stored notices.
		 */
		return hash( 'sha256', 'v2|' . $source . '|' . $identity . '|' . $text );
	}

	/**
	 * Group known or unstable dynamic notices into a stable family.
	 *
	 * Known aggressive notices are handled with explicit rules. Other sources can
	 * be promoted automatically when the same source repeatedly produces similar
	 * or marketing-style notices with different signatures.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function notice_family_from_html( $html ) {
		$html_lc = strtolower( (string) $html );
		$text    = $this->normalize_notice_text( $html );

		if ( false !== strpos( $html_lc, 'gt-admin-notice' ) || false !== strpos( $text, 'upgrading your gtranslate' ) || false !== strpos( $text, 'gtranslate' ) && false !== strpos( $text, 'comparer les forfaits' ) ) {
			return 'gtranslate-upgrade-tips';
		}

		if ( false !== strpos( $html_lc, 'ctc-welcome-notice' ) || ( false !== strpos( $text, 'copy anything to clipboard updated' ) && false !== strpos( $text, 'telemetry opt-in' ) ) ) {
			return 'ctc-welcome-updated';
		}

		if ( false !== strpos( $html_lc, 'fs-notice' ) && ( false !== strpos( $html_lc, 'fs-slug-ctc' ) || false !== strpos( $text, 'copy anything to clipboard' ) ) ) {
			return 'freemius-ctc-notice';
		}

		if ( false !== strpos( $html_lc, 'filebird-empty-folder-notice' ) ) {
			return 'filebird-empty-folder-notice';
		}

		if ( false !== strpos( $html_lc, 'adbc-rating-notice' ) || false !== strpos( $text, 'advanced db cleaner' ) ) {
			return 'advanced-db-cleaner-rating';
		}

		if ( false !== strpos( $html_lc, 'dev-warning-notice' ) || false !== strpos( $text, 'site de développement' ) || false !== strpos( $text, 'site de developpement' ) ) {
			return 'development-site-warning';
		}

		$auto_family = $this->auto_family_from_html( $html );
		if ( '' !== $auto_family ) {
			return $auto_family;
		}

		return '';
	}

	/**
	 * Detect sources that should be grouped as a family.
	 *
	 * A source is considered unstable when it has already generated several
	 * signatures, contains marketing/dismiss/upsell signals, and the current
	 * notice shares either the same action fingerprint or a close text similarity.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function auto_family_from_html( $html ) {
		$source = $this->source_fingerprint_from_html( $html );
		if ( '' === $source ) {
			return '';
		}

		$text       = $this->normalize_notice_text( $html );
		$signals    = $this->notice_instability_score( $html, $text );
		$action_key = $this->notice_action_fingerprint( $html );

		if ( $signals < 2 ) {
			return '';
		}

		$log                 = $this->get_log();
		$same_source_count   = 0;
		$similar_count       = 0;
		$existing_decided    = false;
		$current_text_sample = $this->notice_similarity_sample( $text );

		foreach ( $log as $item ) {
			if ( ! is_array( $item ) || empty( $item['html'] ) ) {
				continue;
			}

			$item_html = (string) $item['html'];
			if ( $this->source_fingerprint_from_html( $item_html ) !== $source ) {
				continue;
			}

			$same_source_count++;

			$item_text       = $this->normalize_notice_text( $item_html );
			$item_action_key = $this->notice_action_fingerprint( $item_html );

			if ( '' !== $action_key && $action_key === $item_action_key ) {
				$similar_count++;
			} elseif ( $this->texts_are_similar( $current_text_sample, $this->notice_similarity_sample( $item_text ) ) ) {
				$similar_count++;
			}

			if ( isset( $item['decision'] ) && in_array( $item['decision'], array( 'allow', 'block' ), true ) ) {
				$existing_decided = true;
			}
		}

		if ( $same_source_count >= 2 && $similar_count >= 1 ) {
			return 'auto-source-' . substr( hash( 'sha256', $source ), 0, 16 );
		}

		if ( $existing_decided && $same_source_count >= 1 && $signals >= 4 ) {
			return 'auto-source-' . substr( hash( 'sha256', $source ), 0, 16 );
		}

		return '';
	}

	/**
	 * Compute instability score for one notice.
	 *
	 * @param string $html Raw HTML.
	 * @param string $text Normalized text.
	 * @return int
	 */
	private function notice_instability_score( $html, $text ) {
		$score   = 0;
		$html_lc = strtolower( (string) $html );

		if ( preg_match( '/\?(?:[^"\']*)(?:nonce|token|ver|version|cache|time|timestamp|rand|papy3d_ns_updated|gt_int|dismiss|ignore|remind)/i', $html_lc ) ) {
			$score += 2;
		}
		if ( preg_match( '/\b(?:upgrade|premium|pro|upsell|pricing|forfaits|rate|review|stars?|telemetry|opt[- ]?in|help improve|analytics|dismiss|remind|later|plus tard|ne plus afficher)\b/i', $text ) ) {
			$score += 2;
		}
		if ( false !== strpos( $html_lc, 'target="_blank"' ) || false !== strpos( $html_lc, 'dashicons-dismiss' ) || false !== strpos( $html_lc, 'notice-dismiss' ) ) {
			$score++;
		}
		if ( preg_match_all( '/\shref\s*=\s*(["\']).*?\1/is', (string) $html, $matches ) && count( $matches[0] ) >= 2 ) {
			$score++;
		}
		if ( preg_match( '/\b(?:plugin|theme|extension)\b/i', $text ) && preg_match( '/\b(?:updated|mise a jour|mise à jour|installed|installee|installée)\b/i', $text ) ) {
			$score = max( 0, $score - 3 );
		}

		return $score;
	}

	/**
	 * Extract a stable action fingerprint from links/buttons.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function notice_action_fingerprint( $html ) {
		$labels = array();
		if ( preg_match_all( '/<(?:a|button)\b[^>]*>(.*?)<\/(?:a|button)>/is', (string) $html, $matches ) ) {
			foreach ( $matches[1] as $label ) {
				$label = $this->normalize_notice_text( $label );
				if ( '' !== $label ) {
					$labels[] = $label;
				}
			}
		}

		$labels = array_values( array_unique( array_filter( $labels ) ) );
		if ( empty( $labels ) ) {
			return '';
		}

		return implode( '|', array_slice( $labels, 0, 6 ) );
	}

	/**
	 * Reduce text to a sample that keeps recurring intent words.
	 *
	 * @param string $text Normalized text.
	 * @return string
	 */
	private function notice_similarity_sample( $text ) {
		$words = preg_split( '/\s+/', (string) $text );
		$stop  = array_fill_keys( array( 'the', 'and', 'you', 'your', 'can', 'have', 'with', 'from', 'this', 'that', 'pour', 'les', 'des', 'une', 'dans', 'plus', 'par', 'sur', 'est', 'sont', 'vous', 'votre' ), true );
		$out   = array();
		foreach ( $words as $word ) {
			$word = trim( (string) $word );
			if ( strlen( $word ) < 4 || isset( $stop[ $word ] ) ) {
				continue;
			}
			$out[] = $word;
		}
		$out = array_values( array_unique( $out ) );
		return implode( ' ', array_slice( $out, 0, 40 ) );
	}

	/**
	 * Compare two normalized text samples.
	 *
	 * @param string $a First text.
	 * @param string $b Second text.
	 * @return bool
	 */
	private function texts_are_similar( $a, $b ) {
		if ( '' === $a || '' === $b ) {
			return false;
		}

		$a_words = array_unique( preg_split( '/\s+/', $a ) );
		$b_words = array_unique( preg_split( '/\s+/', $b ) );
		$common  = array_intersect( $a_words, $b_words );
		$base    = max( 1, min( count( $a_words ), count( $b_words ) ) );

		return ( count( $common ) / $base ) >= 0.45;
	}

	/**
	 * Build source fingerprint from stable attributes/classes.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function source_fingerprint_from_html( $html ) {
		$bits = array();

		foreach ( array( 'data-cp-notification-name', 'data-notice', 'data-notification', 'data-notice-id' ) as $attribute ) {
			if ( preg_match_all( '/\s' . preg_quote( $attribute, '/' ) . '\s*=\s*(["\'])(.*?)\1/is', $html, $matches ) ) {
				foreach ( $matches[2] as $value ) {
					$bits[] = $this->normalize_short_identifier( $value );
				}
			}
		}

		if ( preg_match_all( '/class\s*=\s*(["\'])(.*?)\1/is', $html, $matches ) ) {
			foreach ( $matches[2] as $classes ) {
				foreach ( preg_split( '/\s+/', (string) $classes ) as $class ) {
					$class = $this->normalize_short_identifier( $class );
					if ( preg_match( '/(?:notice|notification|admin-notice|nag|alert|promo|message)/', $class ) ) {
						$bits[] = $class;
					}
				}
			}
		}

		$bits = array_values( array_filter( array_unique( $bits ) ) );

		return implode( '|', array_slice( $bits, 0, 8 ) );
	}

	/**
	 * Normalize notice text for stable fingerprints.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function normalize_notice_text( $html ) {
		$text = (string) $html;
		$text = $this->strip_internal_markup( $text );
		$text = preg_replace( '/<(script|style|svg)\b[^>]*>.*?<\/\1>/is', ' ', $text );
		$text = preg_replace( '/\s(?:href|src|action)\s*=\s*(["\']).*?\1/is', ' ', $text );
		$text = preg_replace( '/\s(?:id|for|aria-describedby|aria-controls)\s*=\s*(["\']).*?\1/is', ' ', $text );
		$text = preg_replace( '/\sdata-[a-z0-9_-]+\s*=\s*(["\']).*?\1/is', ' ', $text );
		$text = preg_replace( '/\sstyle\s*=\s*(["\']).*?\1/is', ' ', $text );
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = strtolower( $text );
		$text = remove_accents( $text );
		$text = preg_replace( '#https?://[^\s]+#i', ' ', $text );
		$text = preg_replace( '/[?&](?:_wpnonce|nonce|token|key|signature|ver|version|cache|time|timestamp|rand|r|papy3d_ns_updated|gt_int|gtranslate_admin_notice_ignore|gtranslate_admin_notice_temp_ignore)=[^\s&]+/i', ' ', $text );
		$text = preg_replace( '/\b[a-f0-9]{8,}\b/i', ' ', $text );
		$text = preg_replace( '/\b\d{4}-\d{2}-\d{2}\b/', ' ', $text );
		$text = preg_replace( '/\b\d{1,2}[\/:]\d{1,2}[\/:]\d{2,4}\b/', ' ', $text );
		$text = preg_replace( '/\b\d{1,2}:\d{2}(?::\d{2})?\b/', ' ', $text );
		$text = preg_replace( '/\b\d{4,}\b/', ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Normalize stable identity attributes.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function normalize_notice_identity( $html ) {
		$identity_bits = array();
		foreach ( array( 'id', 'class', 'data-cp-notification-name', 'data-notice', 'data-notification', 'data-notice-id' ) as $attribute ) {
			if ( preg_match_all( '/\s' . preg_quote( $attribute, '/' ) . '\s*=\s*(["\'])(.*?)\1/is', $html, $matches ) ) {
				foreach ( $matches[2] as $value ) {
					$normalized = $this->normalize_short_identifier( $value );
					if ( '' !== $normalized ) {
						$identity_bits[] = $normalized;
					}
				}
			}
		}

		$identity_bits = array_values( array_filter( array_unique( $identity_bits ) ) );

		return implode( '|', array_slice( $identity_bits, 0, 12 ) );
	}

	/**
	 * Normalize a short ID/class/attribute value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_short_identifier( $value ) {
		$value = strtolower( wp_strip_all_tags( (string) $value ) );
		$value = preg_replace( '/\b[a-f0-9]{8,}\b/i', '', $value );
		$value = preg_replace( '/\b\d{4,}\b/', '', $value );
		$value = preg_replace( '/[_-]?\d+\b/', '', $value );
		$value = preg_replace( '/[^a-z0-9_-]+/', '-', $value );
		$value = preg_replace( '/[-_]{2,}/', '-', $value );

		return trim( (string) $value, '-_' );
	}

	/**
	 * Render first-seen decision UI.
	 *
	 * @param string $signature Notice signature.
	 * @return string
	 */
	private function render_decision_box( $signature ) {
		$nonce = wp_create_nonce( 'papy3d_ns_notice_decision' );

		return sprintf(
			'<div class="papy3d-ns-decision" role="region" aria-label="%1$s" data-papy3d-ns-internal="1"><form class="papy3d-ns-decision-form" method="post" action="%2$s"><input type="hidden" name="action" value="papy3d_ns_notice_decision_post" /><input type="hidden" name="signature" value="%3$s" /><input type="hidden" name="nonce" value="%4$s" /><input type="hidden" name="redirect_to" value="%5$s" /><button type="submit" name="decision" value="allow" class="button button-primary" title="%6$s">%6$s</button><button type="submit" name="decision" value="block" class="button" title="%7$s">%7$s</button><span class="papy3d-ns-result papy3d-ns-muted" aria-live="polite"></span></form></div>',
			esc_attr__( 'Notice decision', 'papy3d-noticeshield' ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $signature ),
			esc_attr( $nonce ),
			esc_url( $this->current_admin_url() ),
			esc_html__( 'Allow', 'papy3d-noticeshield' ),
			esc_html__( 'Block', 'papy3d-noticeshield' )
		);
	}

}
