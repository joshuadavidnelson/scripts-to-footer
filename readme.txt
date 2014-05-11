=== Scripts To Footer ===
Contributors: joshuadnelson
Tags: javascript, footer, speed, head, performance
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FGQXZEW8S9UPC
Requires at least: 3.6
Tested up to: 3.9.1
Stable tag: 0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This small plugin moves scripts to the footer to help speed up page load times, while keeping stylesheets in the header.

== Description ==

This small plugin moves scripts to the footer to help speed up page load times, while keeping stylesheets in the header. Note that this only works if you have plugins and a theme that utilizes `wp_enqueue_scripts` correctly.

Now includes an option to disable the plugin on a specific page.

= Custom Post Type Support =
If you're comfortable with code you can use the `scripts_to_footer_post_types` filter to change the post types this applies to (it only applies to pages and posts by default). For example, if you have a custom post type called "project" you could add support for this metabox via the post type filter like this:

`
function stf_add_project_support( $post_types ) {
	$post_types[] = 'project';
	
	return $post_types;
}
add_filter( 'scripts_to_footer_post_types', 'stf_add_project_support' );
`

= Excluding Pages/Posts Via Filter =
As of version 0.4 you can either use the checkbox option to disable the plugin's action on a specific page/post, or you can utilize a filter. The filter also passes the post/page id, which might be useful for more advanced development. For example:

`
function stf_exclude_my_post( $excluded_pages, $post_id ) {
	
	if( $post_id = '1234' ) { // change to your post id, or use a different conditional to get crazy
		$excluded_pages = 'off'; // set to 'on' to enable the plugin
	}

	return $excluded_pages;
}
add_filter( '', 'stf_exclude_my_post' );
`

= View on GitHub =
[View this plugin on GitHub](https://github.com/joshuadavidnelson/scripts-to-footer)

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `scripts-to-footer.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You can disable the plugin on specific pages or posts via a checkbox.

== Screenshots ==

1. The metabox that shows up on the Edit screen.

== Changelog ==

= 0.4 =
Added filter for excluded page ids, version bump and updated readme.txt file.

= 0.3 = 
Added conditional to disable on plugin on admin dashboard, version bump. 
 	
= 0.2 =
Updating code to be object-oriented and added page metabox to disable plugin on specific pages.

= 0.1 =
Initial release

== Upgrade Notice ==

= 0.4 =
Adds filter for excluded page ids.

= 0.3 =
Adds safeguard to avoid conflicts on admin dashboard. 

= 0.2 =
This upgrade adds options to disable plugin on specific pages.
