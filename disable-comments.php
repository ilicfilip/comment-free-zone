<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Main plugin file.
/**
 * A plugin to fully disable comments on your WordPress site.
 *
 * @package Disable_Comments
 *
 * Plugin name:       Disable Comments
 * Plugin URI:        https://prpl.fyi/disable-comments
 * Description:       A plugin to fully disable comments on your WordPress site.
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Version:           1.0.0
 * Author:            Team Progress Planner
 * Author URI:        https://prpl.fyi/about
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       disable-comments
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable comments and trackbacks in post types.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Main plugin class.
 */
class Disable_Comments {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'disable_comments' ] );
	}

	/**
	 * Disable comments and trackbacks in post types.
	 */
	public function disable_comments() {
		add_action(
			'admin_menu',
			function () {
				remove_submenu_page( 'options-general.php', 'options-discussion.php' ); // Comments settings.
				remove_submenu_page( 'options-general.php', 'options-writing.php' ); // Post settings.
			},
			999
		);
		add_filter( 'manage_pages_columns', [ $this, 'remove_comments_column_from_pages' ] );
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );

		// Disable default comment & ping status.
		add_filter(
			'get_default_comment_status',
			function () {
				return 'closed';
			},
			999
		);

		// Disable outgoing pings.
		add_action(
			'pre_ping',
			function () {
				return [];
			}
		);

		// Disable incoming pingbacks.
		add_filter(
			'xmlrpc_methods',
			function ( $methods ) {
				unset( $methods['pingback.ping'] );
				return $methods;
			}
		);

		// Disable support for comments and trackbacks in post types.
		$post_types = get_post_types();
		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}

			// Remove comment_status and ping_status from the REST API schema.
			add_filter( "rest_{$post_type}_item_schema", [ $this, 'cleanup_rest_api_schema' ] );

			// Remove replies link from REST API responses.
			add_filter( 'rest_prepare_' . $post_type, [ $this, 'cleanup_rest_prepare_post_type' ] );
		}
	}

	/**
	 * Remove comment_status and ping_status from the REST API schema.
	 *
	 * @param array $schema The schema.
	 * @return array The modified schema.
	 */
	public function cleanup_rest_api_schema( $schema ) {
		unset( $schema['properties']['comment_status'] );
		unset( $schema['properties']['ping_status'] );
		return $schema;
	}

	/**
	 * Remove the replies link from the REST API response.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @return WP_REST_Response The modified response object.
	 */
	public function cleanup_rest_prepare_post_type( $response ) {
		$response->remove_link( 'replies' );
		return $response;
	}

	/**
	 * Remove the Comments column from the Pages list table.
	 *
	 * @param array $columns The columns of the Pages list table.
	 * @return array The modified columns.
	 */
	public function remove_comments_column_from_pages( $columns ) {
		unset( $columns['comments'] ); // Removes the Comments column.

		return $columns;
	}
}
