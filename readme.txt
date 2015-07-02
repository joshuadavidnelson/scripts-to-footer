=== Scripts To Footer ===
Contributors: joshuadnelson
Tags: javascript, footer, speed, head, performance
Donate link: http://jdn.im/donate
Requires at least: 3.1.0
Tested up to: 4.2.2
Stable tag: 0.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Move your scripts to the footer to help speed up perceived page load times and improve user experience.

== Description ==

This small plugin moves scripts to the footer to help speed up page load times, while keeping stylesheets in the header. Note that this only works if you have plugins and a theme that utilizes `wp_enqueue_scripts` correctly.

You can disable the plugin on specific pages and posts directly via the post/page edit screen metabox.

You can disable the plugin on specific archive pages (blog page, search page, post type and taxonomy archives) via the settings page.

**Everything Broken?** Try placing jQuery back into the header via Settings > Scripts to Footer, "Keep jQuery in the Header" checkbox.

Check out the [documentation](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) on [GitHub](https://github.com/joshuadavidnelson/scripts-to-footer) or some quick walkthroughs below.

= Keeping specific Scripts in the Header =
As of version 0.6 you can now keep specific scripts in the header. Note: this will print any scripts they depend on as well (if you want to keep `jquery-effects-core` in the header, you'll also get `jQuery` in the header). Specifically for jQuery, see the settings page option, as it is a common request we've built it into the settings.

For any other scripts, use this filter:
`
//add_filter( 'stf_exclude_scripts', 'jdn_header_scripts', 10, 1 );
function jdn_header_scripts( $scripts ) {
	$scripts[] = 'backbone'; // Replace 'backbone' with the script slug
	return $scripts;
}
`

You will need the correct script slug, which is used when the script is registered, and the script will only be printed into the header *if it's enqueued*. Check out the scripts that come registered [out-of-the-box with WordPress](http://codex.wordpress.org/Function_Reference/wp_enqueue_script#Default_Scripts_Included_and_Registered_by_WordPress).

= Custom Post Type Support =
If you're comfortable with code you can use the `scripts_to_footer_post_types` filter to change the post types this applies to (it only applies to pages and posts by default). For example, if you have a custom post type called "project" you could add support for this metabox via the post type filter like this:

`
function stf_add_cpt_support( $post_types ) {
	$post_types[] = 'project';
	
	return $post_types;
}
add_filter( 'scripts_to_footer_post_types', 'stf_add_cpt_support' );
`

= Excluding Pages/Posts/Templates Via Filter =
As of version 0.5 you can either use the checkbox option to disable the plugin's action on a specific page/post, or you can utilize a filter (updated with version 0.6). The filter also passes the post/page id, if there is one (archive templates don't have ids!).

For example, for the "page" post type:

`
function stf_exclude_my_page( $exclude_page, $post_id ) {
	if( is_front_page() ) {
		$exclude_page = 'on'; // this turns on the "exclude" option
	}
	return $exclude_page;
}
add_filter( 'stf_page', 'stf_exclude_my_page' );
`

Replace `stf_page` with `stf_post` for posts, or the slug of your custom post type. For instance, a post type called "project" can be filtered with `stf_project`. 

= View on GitHub =
[View this plugin on GitHub](https://github.com/joshuadavidnelson/scripts-to-footer).


== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `scripts-to-footer.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You can disable the plugin on specific pages or posts via a checkbox.

== FAQ ==


== Screenshots ==

1. The metabox that shows up on the Edit screen.
2. The settings page.

== Changelog ==

= 0.6 =


= 0.5 =
Reverted metabox version to previous - invalid error was sneaking through.

= 0.4 =
Added filter to exclude pages, updated metabox version, plugin version bump and updated readme.txt file.

= 0.3 = 
Added conditional to disable on plugin on admin dashboard, version bump. 
 	
= 0.2 =
Updating code to be object-oriented and added page metabox to disable plugin on specific pages.

= 0.1 =
Initial release

== Upgrade Notice ==

= 0.5 =
Please update to avoid an error on 0.4 version. If you're updating from version 0.3 or earlier, you'll get a new filter.

= 0.4 =
Adds filter for excluded page ids and updated to most current metabox system.

= 0.3 =
Adds safeguard to avoid conflicts on admin dashboard. 

= 0.2 =
This upgrade adds options to disable plugin on specific pages.
