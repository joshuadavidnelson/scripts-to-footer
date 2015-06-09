=== Scripts To Footer ===
Contributors: joshuadnelson
Tags: javascript, footer, speed, head, performance
Donate link: http://jdn.im/donate
Requires at least: 3.6
Tested up to: 4.2.2
Stable tag: 0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This small plugin moves scripts to the footer to help speed up page load times, while keeping stylesheets in the header.

== Description ==

This small plugin moves scripts to the footer to help speed up page load times, while keeping stylesheets in the header. Note that this only works if you have plugins and a theme that utilizes `wp_enqueue_scripts` correctly.

Now includes an option to disable the plugin on a specific page or post.

= Custom Post Type Support =
If you're comfortable with code you can use the `scripts_to_footer_post_types` filter to change the post types this applies to (it only applies to pages and posts by default). For example, if you have a custom post type called "project" you could add support for this metabox via the post type filter like this:

`
function stf_add_cpt_support( $post_types ) {
	$post_types[] = 'project';
	
	return $post_types;
}
add_filter( 'scripts_to_footer_post_types', 'stf_add_cpt_support' );
`

= Excluding Pages/Posts Via Filter =
As of version 0.5 you can either use the checkbox option to disable the plugin's action on a specific page/post, or you can utilize a filter. The filter also passes the post/page id, which might be useful for more advanced development. For example:

`
function stf_exclude_my_page( $exclude_page, $post_id ) {
	if( is_front_page() ) {
		$exclude_page = 'on'; // this turns on the "exclude" option
	}
	return $exclude_page;
}
add_filter( 'scripts_to_footer_exclude_page', 'stf_exclude_my_page' );
`

= View on GitHub =
[View this plugin on GitHub](https://github.com/joshuadavidnelson/scripts-to-footer)

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `scripts-to-footer.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. You can disable the plugin on specific pages or posts via a checkbox.

== FAQ ==

1. My scripts are not moving to the footer.
 - This is likely due to one of three things:
    1. The theme you're using is not enqueuing scripts per WordPress standards.
    2. You have a plugin that is not enqueing scripts per standards.
    3. (Less common) There is a conflict with this plugin and another one. Deactivate all plugins and revert to a built-in theme (like TwentyTwelve or TwentyFifteen). Then activate Scripts-to-Footer. Check your HTML source to confirm it's working. If so, proceed to activate each of your other plugins one at a time, checking your HTML source each time to see if the scripts behavior changes. Eventually you'll find a conflict, if not with the plugins then activate your theme and check. Please feel free to report conflicts on the Support Forum. If there is something wrong with Scripts-to-Footer, we'll update it. However, if it's a another plugin or theme we can only contact the developer with the issue to attempt to resolve it.

2. Everything Breaks!!
 - There are lots of scripts that require things like jQuery in the header. In those cases, perhaps Scripts-to-Footer isn't the best fit. I'd recommend trying a minifying plugin instead. If you'd like to proceed with using Scripts-to-Footer you'll have to do some custom coding in your functions.php file, or via a custom plugin file. Follow this walkthrough to exclude specific scripts from Scripts-to-Footer and keep them in the header. I would not recommend this method for a large number of scripts, though. 

3. My Slider stopped working.
 - See number 2.

4. My Page Speed hasn't improved (or it's worse)
 - Actually, this plugin should not change your actual page speed - the same scripts are being loaded, that takes the same amount of time. However, by placing scripts in the footer you can change the _precieved_ load times. Your page will likely load the visual content first, then the scripts - instead of loading scripts and slowing the visual portions of your site. That's the whole point. Outside of that, this plugin is not intended to increase page load speed or minify scripts in anyway.

== Screenshots ==

1. The metabox that shows up on the Edit screen.

== Changelog ==

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
