<?php
/**
 * E2E mu-plugin fixture: a public custom post type with an archive, and a
 * custom taxonomy attached to it.
 *
 * Neither is in the plugin's default supported lists (`page`/`post` and
 * `category`/`post_tag`), so the plugin must leave their views untouched
 * until a spec opts them in through `scripts_to_footer_post_types` /
 * `scripts_to_footer_taxonomies` (see stf-test-filters.php).
 *
 * @package Scripts_To_Footer\TestFixtures
 */

defined( 'ABSPATH' ) || exit;

// Inert without STF_TEST_FIXTURES: only the e2e environment defines it.
if ( ! defined( 'STF_TEST_FIXTURES' ) ) {
	return;
}

/**
 * Register the fixture post type and taxonomy.
 *
 * @return void
 */
function stf_test_cpt_register() {

	register_post_type(
		'stf_test_cpt',
		array(
			'label'        => 'STF Test Items',
			'labels'       => array(
				'name'          => 'STF Test Items',
				'singular_name' => 'STF Test Item',
			),
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'rest_base'    => 'stf-test-items',
			'supports'     => array( 'title', 'editor', 'author', 'custom-fields' ),
			'rewrite'      => array( 'slug' => 'stf-test-items' ),
		)
	);

	register_taxonomy(
		'stf_test_tax',
		array( 'stf_test_cpt', 'post' ),
		array(
			'label'        => 'STF Test Terms',
			'labels'       => array(
				'name'          => 'STF Test Terms',
				'singular_name' => 'STF Test Term',
			),
			'public'       => true,
			'show_in_rest' => true,
			'rest_base'    => 'stf-test-terms',
			'rewrite'      => array( 'slug' => 'stf-test-term' ),
		)
	);
}
add_action( 'init', 'stf_test_cpt_register' );
