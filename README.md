Scripts To Footer
=================

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/scripts-to-footerphp)](https://wordpress.org/plugins/scripts-to-footerphp/) ![Downloads](https://img.shields.io/wordpress/plugin/dt/scripts-to-footerphp.svg) ![Rating](https://img.shields.io/wordpress/plugin/r/scripts-to-footerphp.svg)

**Requires at least:** 5.3  
**Tested up to WordPress:** 6.4.2  
**Stable version:** 0.7.2  
**License:** GPLv2 or later  
**Requires PHP:** 7.4  
**Tested up to PHP:** 8.1

Move your scripts to the footer to speed up perceived page load times and improve user experience.

## Description

This small plugin moves scripts to the footer. Note that this only works if you have plugins and a theme that utilizes `wp_enqueue_scripts` correctly.

You can disable the plugin on specific pages and posts directly via the post/page edit screen metabox.

You can disable the plugin on specific archive pages (blog page, search page, post type and taxonomy archives) via the settings page.

**Everything Broken?** Try placing jQuery back into the header via Settings > Scripts to Footer, "Keep jQuery in the Header" checkbox. If that doesn't work, refer to the walkthrough below for using the `stf_exclude_scripts` filter for the script that is causing the issue.

Check out the [documentation](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) on [GitHub](https://github.com/joshuadavidnelson/scripts-to-footer) or some quick walkthroughs below.

## Support

Please utilize the [issues](https://github.com/joshuadavidnelson/scripts-to-footer/issues) section if you run into problems or have suggestions for making the plugin better. You can also view the most recent official, stable release on the WordPress repo linked below.

*See the [wiki](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) for specifics on usage, like filters and work-arounds for specific scripts*

## WordPress Plugin

Download for your WordPress site here: [http://wordpress.org/plugins/scripts-to-footerphp/](http://wordpress.org/plugins/scripts-to-footerphp/)

## FAQ
1. My scripts are not moving to the footer. This is likely due to one of three things:
   1. The theme you're using is not enqueuing scripts per [WordPress standards](https://codex.wordpress.org/Function_Reference/wp_enqueue_script#Using_a_Hook).
   2. You have a plugin that is not enqueuing scripts [per standards](https://codex.wordpress.org/Function_Reference/wp_enqueue_script#Using_a_Hook).
   3. (Less common) There is a conflict with this plugin and another one. Deactivate all plugins and revert to a built-in theme (like TwentyTwelve or TwentyFifteen). Then activate Scripts-to-Footer. Check your HTML source to confirm it's working. 
	   
	  If so, proceed to activate each of your other plugins one at a time, checking your HTML source each time to see if the scripts behavior changes. Eventually you'll find a conflict, if not with the plugins then activate your theme and check.
	   
	  Please feel free to open a [Github Issue](https://github.com/joshuadavidnelson/scripts-to-footer/issues) to report conflicts or goto [the  WP.org support forum](https://wordpress.org/support/plugin/scripts-to-footerphp). If there is something wrong with Scripts-to-Footer, we'll update it. However, if it's a another plugin or theme we can only contact the developer with the issue to attempt to resolve it.

2. Everything Breaks!!
 - There are lots of scripts that require things like jQuery in the header. Try checking the "Keep jQuery in the header" option in Settings > Scripts to Footer or using the `stf_exclude_scripts` filter noted in the [documentation](https://github.com/joshuadavidnelson/scripts-to-footer/wiki) (Note: only for version 0.6 and higher).

3. My Slider stopped working.
 - See number 2.

4. My Page Speed hasn't improved (or it's worse)
 - This plugin should not change your actual page speed - the same scripts are being loaded, that takes the same amount of time. However, by placing scripts in the footer you can change the _precieved_ load times, moving [render-blocking scripts](https://developers.google.com/speed/docs/insights/BlockingJS) below the fold, allowing your content to load first - instead of loading scripts and slowing the visual portions of your site. That's the whole point. Outside of that, this plugin is not intended to decrease page load speed or minify scripts in anyway. **Ultimately, your page speed score is based on more than where you load your scripts and can be altered by moving them, but depending on what these scripts do it can go either way**. As with most things, it entirely depends on the unique configuration of your site.

## Donate

Like this plugin and feeling generous? Please consider [donating](http://joshuadnelson.com/donate) to support freelance development.

## Contributing

All contributions are welcomed and considered, please refer to [contributing.md](contributing.md).

### Pull requests
All pull requests should be directed at the `develop` branch, and will be reviewed prior to merging. No pull requests will be merged with failing tests, but it's okay if you don't initially pass tests. Please create a draft pull request for proof of concept code or changes you'd like to have input on prior to review.

Please make on a branch specific to a single issue or feature. For instance, if you are suggest a solution to an issue, please create fork with a branch like `issue-894`. Or if you are proposing a new feature, create a fork with the branch name indicating the feature like `feature-example-bananas`

All improvements are merged into `develop` and then queued up for release before being merged into `stable`. Releases are deployed via github actions to wordpress.org on tagging a new release.

### Main Branches

The `stable` branch is reserved for releases and intended to be a mirror of the official current release, or `trunk` on wordpress.org.

The `develop` branch is the most current working branch. _Please direct all pull requests to the `develop` branch_

### Local Development

**Requirements:**
- Docker
- Node Package Manager (npm)

This repo contains the files needed to boot up a local development environment using [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

Run `npm install` and the `npm run env:start` to boot up a local environment. 
