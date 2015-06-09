Scripts To Footer
=================

Sleek, light-weight WordPress plugin for moving javascript to the footer, while retaining styles in the header.

Please utilize the [issues](https://github.com/joshuadavidnelson/scripts-to-footer/issues) section if you run into problems or have suggestions for making the plugin better. You can also view the most recent official, stable release on the WordPress repo linked below.

*See the [wiki](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) for specifics on usage, like filters and work-arounds for specific scripts*

### WordPress Plugin

Download for your WordPress site here: [http://wordpress.org/plugins/scripts-to-footerphp/](http://wordpress.org/plugins/scripts-to-footerphp/)

### FAQ
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

### Donate

Like this plugin and feeling generous? Please consdier [donating](http://joshuadnelson.com/donate) to support freelance development.

### TODO

- Add universal "deactivate/activate by default," similar to [Genesis Title Toggle](https://github.com/billerickson/genesis-title-toggle)
- Updated `uninstall.php` to remove post meta data (currently only removes the unused settings field, see [Issue #1](https://github.com/joshuadavidnelson/scripts-to-footer/issues/1))
