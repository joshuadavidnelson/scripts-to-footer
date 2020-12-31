Scripts To Footer
=================

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/scripts-to-footerphp)](https://wordpress.org/plugins/scripts-to-footerphp/) ![Downloads](https://img.shields.io/wordpress/plugin/dt/scripts-to-footerphp.svg) ![Rating](https://img.shields.io/wordpress/plugin/r/scripts-to-footerphp.svg)

**Requires at least:** 4.0  
**Tested up to WordPress:** 5.6  
**Stable version:** 0.6.5å∑  
**License:** GPLv2 or later  
**Requires PHP:** 5.6  
**Tested up to PHP:** 7.4

Move your scripts to the footer to speed up perceived page load times and improve user experience.

Please utilize the [issues](https://github.com/joshuadavidnelson/scripts-to-footer/issues) section if you run into problems or have suggestions for making the plugin better. You can also view the most recent official, stable release on the WordPress repo linked below.

*See the [wiki](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) for specifics on usage, like filters and work-arounds for specific scripts*

### WordPress Plugin

Download for your WordPress site here: [http://wordpress.org/plugins/scripts-to-footerphp/](http://wordpress.org/plugins/scripts-to-footerphp/)

### FAQ
1. My scripts are not moving to the footer. This is likely due to one of three things:
   1. The theme you're using is not enqueuing scripts per [WordPress standards](https://codex.wordpress.org/Function_Reference/wp_enqueue_script#Using_a_Hook).
   2. You have a plugin that is not enqueing scripts [per standards](https://codex.wordpress.org/Function_Reference/wp_enqueue_script#Using_a_Hook).
   3. (Less common) There is a conflict with this plugin and another one. Deactivate all plugins and revert to a built-in theme (like TwentyTwelve or TwentyFifteen). Then activate Scripts-to-Footer. Check your HTML source to confirm it's working. 
	   
	  If so, proceed to activate each of your other plugins one at a time, checking your HTML source each time to see if the scripts behavior changes. Eventually you'll find a conflict, if not with the plugins then activate your theme and check.
	   
	  Please feel free to open a [Github Issue](https://github.com/joshuadavidnelson/scripts-to-footer/issues) to report conflicts or goto [the  WP.org support forum](https://wordpress.org/support/plugin/scripts-to-footerphp). If there is something wrong with Scripts-to-Footer, we'll update it. However, if it's a another plugin or theme we can only contact the developer with the issue to attempt to resolve it.

2. Everything Breaks!!
 - There are lots of scripts that require things like jQuery in the header. Try checking the "Keep jQuery in the header" option in Settings > Scripts to Footer or using the `stf_exclude_scripts` filter noted in the [documentation](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) (Note: only for version 0.6 and higher).

3. My Slider stopped working.
 - See number 2.

4. My Page Speed hasn't improved (or it's worse)
 - This plugin should not change your actual page speed - the same scripts are being loaded, that takes the same amount of time. However, by placing scripts in the footer you can change the _precieved_ load times, moving [render-blocking scripts](https://developers.google.com/speed/docs/insights/BlockingJS) below the fold, allowing your content to load first - instead of loading scripts and slowing the visual portions of your site. That's the whole point. Outside of that, this plugin is not intended to decrease page load speed or minify scripts in anyway. **Ultimately, your page speed score is based on more than where you load your scripts and can be altered by moving them, but depending on what these scripts do it can go either way**. As with most things, it entirely depends on the unique configuration of your site.

### Donate

Like this plugin and feeling generous? Please consider [donating](http://joshuadnelson.com/donate) to support freelance development.
