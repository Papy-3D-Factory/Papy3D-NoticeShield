<?php
/**
 * Papy3D_NoticeShield_Utils_Trait.
 *
 * @package Papy3D_NoticeShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Papy3D_NoticeShield_Utils_Trait {

	/**
	 * Get current admin URL for non-JS fallback redirects.
	 *
	 * @return string
	 */
	private function current_admin_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$current_url = $request_uri ? set_url_scheme( admin_url( ltrim( preg_replace( '#^.*/wp-admin/#', '', $request_uri ), '/' ) ), is_ssl() ? 'https' : 'http' ) : '';

		return $this->sanitize_redirect_url( $current_url );
	}

	/**
	 * Return a safe admin redirect URL.
	 *
	 * Never redirect back to admin-ajax.php/admin-post.php because they are endpoints,
	 * not real admin screens. This prevents the white page containing only "0" after
	 * a non-JavaScript fallback submit from a dynamically injected notice.
	 *
	 * @param string $candidate Candidate redirect URL.
	 * @return string
	 */
	private function sanitize_redirect_url( $candidate = '' ) {
		$fallback = admin_url( 'tools.php?page=papy3d-noticeshield' );
		$referer  = wp_get_referer();
		$url      = $candidate ? $candidate : $referer;

		if ( $this->is_endpoint_redirect_url( $url ) && $referer && ! $this->is_endpoint_redirect_url( $referer ) ) {
			$url = $referer;
		}

		$url = $url ? remove_query_arg( array( 'papy3d_ns_updated', 'papy3d_ns_error' ), $url ) : '';

		if ( ! $url || $this->is_endpoint_redirect_url( $url ) ) {
			$url = $fallback;
		}

		return wp_validate_redirect( $url, $fallback );
	}

	/**
	 * Check whether a redirect URL points to a WordPress endpoint rather than a page.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_endpoint_redirect_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return ( false !== strpos( $path, 'admin-ajax.php' ) || false !== strpos( $path, 'admin-post.php' ) );
	}

	/**
	 * Return a compact reference to the current admin page.
	 *
	 * @return string
	 */
	private function current_admin_page_reference() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! empty( $screen->id ) ) {
			return sanitize_key( (string) $screen->id );
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen reference.
		return '' !== $page ? $page : 'admin';
	}

	/**
	 * Shorten a stored admin page reference for display.
	 *
	 * @param string $page Page reference.
	 * @return string
	 */
	private function short_admin_page_label( $page ) {
		$page = sanitize_text_field( $page );
		return '' !== $page ? $page : __( 'Unknown admin page', 'papy3d-noticeshield' );
	}

}
