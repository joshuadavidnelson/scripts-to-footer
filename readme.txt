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

**Everything Broken?** Try placing jQuery back into the header via Settings > Scripts to Footer, "Keep jQuery in the Header" checkbox. If that doesn't work, refer to the walkthrough below for using the `stf_exclude_scripts` filter for the script that is causing the issue.

Check out the [documentation](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) on [GitHub](https://github.com/joshuadavidnelson/scripts-to-footer) or some quick walkthroughs below.

= Keeping specific Scripts in the Header =
As of version 0.6 you can now keep specific scripts in the header. Note: this will print any scripts they depend on as well (if you want to keep `jquery-effects-core` in the header, you'll also get `jQuery` in the header, so no need to add both). 

Specifically for jQuery, see the settings page option, as it is a common request we've built it into the settings.

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

1. My scripts are not moving to the footer. This is likely due to one of three things:
   1. The theme you're using is not enqueuing scripts per [WordPress standards](https://codex.wordpress.org/Function_Reference/wp_enqueue_script#Using_a_Hook).
   2. You have a plugin that is not enqueing scripts [per standards](https://codex.wordpress.org/Function_Reference/wp_enqueue_script#Using_a_Hook).
   3. (Less common) There is a conflict with this plugin and another one. Deactivate all plugins and revert to a built-in theme (like TwentyTwelve or TwentyFifteen). Then activate Scripts-to-Footer. Check your HTML source to confirm it's working. 
	   
	   If so, proceed to activate each of your other plugins one at a time, checking your HTML source each time to see if the scripts behavior changes. Eventually you'll find a conflict, if not with the plugins then activate your theme and check.
	   
	   Please feel free to open a [Github Issue](https://github.com/joshuadavidnelson/scripts-to-footer/issues) to report conflicts or goto [the  WP.org support forum](https://wordpress.org/support/plugin/scripts-to-footerphp). If there is something wrong with Scripts-to-Footer, we'll update it. However, if it's a another plugin or theme we can only contact the developer with the issue to attempt to resolve it.

2. Everything Breaks!!
 - There are lots of scripts that require things like jQuery in the header. Try checking the "Keep jQuery in the header" option in Settings > Scripts to Footer or using the `stf_exclude_scripts` filter noted in the [documnetation](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) (Note: only for version 0.6 and higher).

3. My Slider stopped working.
 - See number 2.

4. My Page Speed hasn't improved (or it's worse)
 - Actually, this plugin should not change your actual page speed - the same scripts are being loaded, that takes the same amount of time. However, by placing scripts in the footer you can change the _precieved_ load times. Your page will likely load the visual content first, then the scripts - instead of loading scripts and slowing the visual portions of your site. That's the whole point. Outside of that, this plugin is not intended to increase page load speed or minify scripts in anyway.


== Screenshots ==

1. The metabox that shows up on the Edit screen.
2. The settings page.


== Changelog ==

= 0.6 =
Large number of improvements:
- Add settings page with global disable options for home page, search pages, post type archives, taxonomy archives, and other archives.
- Update uninstall.php to remove things correctly.
- Add FAQ to readme.txt and readme.md.
- Add a changelog as a separate file.
- Change the custom post type filter. Refer to update FAQ and Github wiki.
- Add support for custom taxonomy archives.
- Change the exclude filter, to be more relevant to the new options. Older filter is depreciated, but still supported (for now).
- Update the post meta for disabling the plugin on specific posts/pages.
- Add Github Updater support.
- Removed CMB and built metaboxes the old fashion way.
- Added debug logging to better track any potential errors moving forward.

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

= 0.6 =
Large improvements, including: a settings page to resolve issues on archives and blog roll pages, setting to keep jQuery in the header, and updated filters. Refer to the documentation if you are using a filter currently, as they have changed, prior to updating.

= 0.5 =
Please update to avoid an error on 0.4 version. If you're updating from version 0.3 or earlier, you'll get a new filter.

= 0.4 =
Adds filter for excluded page ids and updated to most current metabox system.

= 0.3 =
Adds safeguard to avoid conflicts on admin dashboard. 

= 0.2 =
This upgrade adds options to disable plugin on specific pages.
