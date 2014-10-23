Scripts To Footer
=================

Sleek, light-weight WordPress plugin for moving javascript to the footer, while retaining styles in the header.

#####Note: this is the "bleeding edge" version and may not necessarily be stable, use at your own risk. Let me know in the [issues](https://github.com/joshuadavidnelson/scripts-to-footer/issues) section if you run into problems or have suggestions for making the plugin better. You can also view the most recent official, stable release on the WordPress repo linked below.

## WordPress Plugin

Download for your WordPress site here: [http://wordpress.org/plugins/scripts-to-footerphp/](http://wordpress.org/plugins/scripts-to-footerphp/)

Like this plugin and feeling generous? Please consdier [donating](http://joshuadnelson.com/donate) to support freelance development.

## Usage

This small plugin moves scripts to the footer to help speed up page load times, while keeping stylesheets in the header. Note that this only works if you have plugins and a theme that utilizes `wp_enqueue_scripts` correctly.

Now includes an option to disable the plugin on a specific page.

### Custom Post Type Support
If you're comfortable with code you can use the `scripts_to_footer_post_types` filter to change the post types this applies to (it only applies to pages and posts by default). For example, if you have a custom post type called "project" you could add support for this metabox via the post type filter like this:

```php
function stf_add_project_support( $post_types ) {
	$post_types[] = 'project';
	
	return $post_types;
}
add_filter( 'scripts_to_footer_post_types', 'stf_add_project_support' );
```

### Excluding Pages/Posts Via Filter
As of version 0.4 you can either use the checkbox option to disable the plugin's action on a specific page/post, or you can utilize a filter. The filter also passes the post/page id, which might be useful for more advanced development. For example:

```php
function stf_exclude_my_post( $excluded_pages, $post_id ) {
	
	if( $post_id = '1234' ) { // change to your post id, or use a different conditional to get crazy
		$excluded_pages = 'off'; // set to 'on' to enable the plugin
	}

	return $excluded_pages;
}
add_filter( '', 'stf_exclude_my_post' );
```

### Changelog

##### 0.5
Reverted metabox version to previous - invalid error was sneaking through.

##### 0.4
Added filter to exclude pages, updated metabox version, plugin version bump and updated readme.txt file.

##### 0.3
Added conditional to disable on plugin on admin dashboard, version bump. 
 	
##### 0.2
Updating code to be object-oriented and added page metabox to disable plugin on specific pages.

##### 0.1
Initial release

### TODO

- Add universal "deactivate/activate by default," similar to [Genesis Title Toggle](https://github.com/billerickson/genesis-title-toggle)
- Updated `uninstall.php` to remove post meta data (currently only removes the unused settings field, see [Issue #1](https://github.com/joshuadavidnelson/scripts-to-footer/issues/1))
