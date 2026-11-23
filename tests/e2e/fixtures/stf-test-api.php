<?php
/**
 * E2E mu-plugin fixture: a small REST API for seeding and inspecting state
 * that core REST does not expose.
 *
 *   GET    stf-test/v1/options        -> the raw `scripts-to-footer` option
 *   PUT    stf-test/v1/options        -> replace it ({ "options": {...} })
 *   DELETE stf-test/v1/options        -> delete it
 *   PUT    stf-test/v1/meta/<id>      -> write raw `stf_exclude` meta
 *                                         ({ "value": 1 | "on" | null })
 *   GET    stf-test/v1/meta/<id>      -> read raw `stf_exclude` meta
 *
 * `stf_exclude` is not registered for REST by the plugin, and its legacy
 * 'on' value cannot be written through the metabox, so specs seed it here.
 * Every route requires `manage_options`.
 *
 * @package Scripts_To_Footer\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// Inert without STF_TEST_FIXTURES: only the e2e environment defines it.
if ( ! defined( 'STF_TEST_FIXTURES' ) ) {
	return;
}

/**
 * Permission callback shared by every route.
 *
 * @return bool
 */
function stf_test_api_can_manage() {
	return current_user_can( 'manage_options' );
}

/**
 * Register the fixture routes.
 *
 * @return void
 */
function stf_test_api_register_routes() {

	register_rest_route(
		'stf-test/v1',
		'/options',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => 'stf_test_api_can_manage',
				'callback'            => static function () {
					return rest_ensure_response(
						array(
							'options'              => get_option( 'scripts-to-footer', null ),
							'stf_version'          => get_option( 'stf_version', null ),
							'stf_previous_version' => get_option( 'stf_previous_version', null ),
						)
					);
				},
			),
			array(
				'methods'             => 'PUT',
				'permission_callback' => 'stf_test_api_can_manage',
				'callback'            => static function ( WP_REST_Request $request ) {
					$options = $request->get_param( 'options' );
					if ( ! is_array( $options ) ) {
						return new WP_Error( 'stf_test_invalid', 'options must be an object', array( 'status' => 400 ) );
					}
					update_option( 'scripts-to-footer', $options );
					return rest_ensure_response( array( 'options' => get_option( 'scripts-to-footer' ) ) );
				},
			),
			array(
				'methods'             => 'DELETE',
				'permission_callback' => 'stf_test_api_can_manage',
				'callback'            => static function () {
					delete_option( 'scripts-to-footer' );
					return rest_ensure_response( array( 'deleted' => true ) );
				},
			),
		)
	);

	register_rest_route(
		'stf-test/v1',
		'/meta/(?P<id>\d+)',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => 'stf_test_api_can_manage',
				'callback'            => static function ( WP_REST_Request $request ) {
					$id = (int) $request['id'];
					return rest_ensure_response(
						array(
							'exists' => metadata_exists( 'post', $id, 'stf_exclude' ),
							'value'  => get_post_meta( $id, 'stf_exclude', true ),
						)
					);
				},
			),
			array(
				'methods'             => 'PUT',
				'permission_callback' => 'stf_test_api_can_manage',
				'callback'            => static function ( WP_REST_Request $request ) {
					$id    = (int) $request['id'];
					$value = $request->get_param( 'value' );
					if ( ! get_post( $id ) ) {
						return new WP_Error( 'stf_test_no_post', 'no such post', array( 'status' => 404 ) );
					}
					if ( null === $value ) {
						delete_post_meta( $id, 'stf_exclude' );
					} else {
						update_post_meta( $id, 'stf_exclude', $value );
					}
					return rest_ensure_response(
						array(
							'exists' => metadata_exists( 'post', $id, 'stf_exclude' ),
							'value'  => get_post_meta( $id, 'stf_exclude', true ),
						)
					);
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'stf_test_api_register_routes' );
