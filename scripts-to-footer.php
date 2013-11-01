<?php
/*
Plugin Name: Scripts-To-Footer
Plugin URI: http://joshuadnelson.com/
Description: This small plugin moves scripts to the footer to help speed up page load times, while keep stylesheets in the header. Note that this only works if you have plugins and a theme that utilizes wp_enqueue_scripts correctly.
Version: 0.1
Author: Joshua David Nelson
Author URI: http://joshuadnelson.com
License: GPL2

Copyright 2013  Joshua David Nelson  (email : joshuadavidnelson@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE, aka AS-IS.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function custom_clean_head() { 
	remove_action('wp_head', 'wp_print_scripts'); 
	remove_action('wp_head', 'wp_print_head_scripts', 9); 
	remove_action('wp_head', 'wp_enqueue_scripts', 1); 
} 
add_action( 'wp_enqueue_scripts', 'custom_clean_head' );


?>
