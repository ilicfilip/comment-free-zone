<?php
/**
 * A plugin to help you fight procrastination and get things done.
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
        add_action( 'admin_menu', function() {
		    remove_submenu_page( 'options-general.php', 'options-discussion.php' ); // Comments settings
		    remove_submenu_page( 'options-general.php', 'options-writing.php' ); // Post settings
		}, 999 );
		add_filter( 'manage_pages_columns', [ $this, 'remove_comments_column_from_pages' ] );
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );

   		// Disable outgoing pings
		add_action( 'pre_ping', function() {
		    return [];
		});

   		// Disable incoming pingbacks
		add_filter( 'xmlrpc_methods', function( $methods ) {
		    unset( $methods[ 'pingback.ping' ] );
		    return $methods;
		});

        // Disable support for comments and trackbacks in post types.
		$post_types = get_post_types();
		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}

		// Disable the comments REST API endpoint.
		add_filter( 'rest_endpoints', [ $this, 'remove_comments_endpoint' ] );

		// Remove comment data from REST API responses.
		add_action( 'rest_api_init', [ $this, 'remove_comment_data_from_responses' ] );
	}

    /**
     * Remove the Comments column from the Pages list table.
     *
     * @param array $columns The columns of the Pages list table.
     * @return array The modified columns.
     */
    public function remove_comments_column_from_pages( $columns ) {
	    unset( $columns[ 'comments' ] ); // Removes the Comments column.

	    return $columns;
	}

	/**
	 * Remove the comments endpoint from the REST API.
	 *
	 * @param array $endpoints The REST API endpoints.
	 * @return array The modified endpoints.
	 */
	public function remove_comments_endpoint( $endpoints ) {
		unset( $endpoints['/wp/v2/comments'] );
		unset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
		return $endpoints;
	}

	/**
	 * Remove comment data from REST API responses for all post types.
	 */
	public function remove_comment_data_from_responses() {
		$post_types = get_post_types( [ 'show_in_rest' => true ] );
		foreach ( $post_types as $post_type ) {
			add_filter( "rest_prepare_{$post_type}", [ $this, 'remove_comment_fields_from_response' ], 10, 3 );
		}
	}

	/**
	 * Remove comment-related fields from a REST API response.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response The modified response.
	 */
	public function remove_comment_fields_from_response( $response, $post, $request ) {
		$data = $response->get_data();
		unset( $data['comment_status'] );
		unset( $data['ping_status'] );
		$response->set_data( $data );

		$links = $response->get_links();
		if ( isset( $links['replies'] ) ) {
			$response->remove_link( 'replies' );
		}

		return $response;
	}
}
