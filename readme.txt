=== MiniMU ===
Contributors: shelkie
Tags: domain, domains, multiple, theme, themes, admin
Requires at least: 3.0.0
Tested up to: 3.5.1
Stable tag: 0.6.9

Manage multiple blogs with a single standard WordPress installation. Each may have its own theme and domain while sharing users and administration.

== Description ==

Manage multiple blogs with a single standard WordPress installation. Each may have its own theme and domain while sharing users and administration.

Handy for situations where multiple blogs or domains are required, but WordPress MU seems like overkill. MiniMU adds this functionality without added complexity or administration headaches.

By associating a WordPress category with each of your domains, you're able to control where your posts and pages appear. Have some posts and pages show up on a single domain, and others be visible on all domains. Simply assign the appropriate categories to your posts and pages to control where they appear.

You are able to select a different theme for each MiniMU domain if so desired. Additionally, you're able to change the Blog title and Tagline for each. Often, it's desirable to include other bits of content that vary by domain. MiniMU allows you to create custom "variables", and assign unique content for each domain. These bits of custom content may be plain text or HTML.

== Installation ==

This section describes how to install the plugin and get it working.

**Automatic installation**

1. Log in to your WordPress Administration Panel, and click on "Plugins" in the left column.
2. Click "Add new" and then search for "MiniMU"
3. Follow the prompts to install and activate the plugin.

**Manual installation**

1. Download the plugin .zip file from http://wordpress.org/extend/plugins/minimu
2. Unzip the file to your /wp-content/plugins directory
3. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= How do you pronounce this plugin's name? =

"mini-mew"

= Can you control under which domain(s) pages will appear? =
Yes, the plugin adds the ability to specify categories for your pages. Pages with no categories defined will appear on all domains.

= Does this plugin support custom page types? =
Yes



== Screenshots ==

1. When you first visit the MiniMU settings page, only the base domain is show
2. Add as many domains as you'd like, optionally choosing categories, theme and custom variables for each
3. Add categories to your pages if you want them to only appear on certain domains

== Changelog ==

= 0.6.9 =
* Fixing SQL error
* Fix undefined variable notices
* Don't redirect front-end use of admin-ajax.php (Thanks @chancezeus)

= 0.6.8 =
* No new functionality - just fixing version numbering problem

= 0.6.5 =
* Improved handling of custom post types
* Added ability to have pages appear only under selected domains

= 0.6.4 =
* Fixed previous/next post link bug

= 0.6.3 =
* Fixed comment filter bug

= 0.6.2 =
* Fixed child theme bug

= 0.6.1 =
* Fixed theme switching bug
* Comments now filtered by domain

= 0.6.0 =
* Fixed error that occured if no categories were selected for a domain
* Improved handling of special characters
* Category links should now load the correct domain
* Domains can now begin with a number

= 0.5.9 =
* Made compatible with WordPress 3.2


= 0.5.8 =
* Category lists now show categories for the associated domain
* Archive now only shows posts for current domain
* Fixed bug that prevented Pages from working correctly
* Now requires WordPress 3.0 or higher
* Domain assignments are now shown on the WP Categories screen
* Multiple categories can be assigned to each domain
* Add "Blog list" widget
* Fixed incorrect domain references in header tags
* Previous/Next links now loop through current domain posts only
* Tag cloud now shows only applicable terms
* Category slug no longer added to body class


= 0.4.0 =
* Made compatible with Wordpress 3.1.1
* Admin are now shows all posts, rather only the current domain

= 0.3.3 =
* Fixed errant PHP "short tag"
* Allow "localhost" domain

= 0.3.2 =
* Fixed issue with posts appearing twice in some situations
* Fixed domain redirect bug introduced in 0.3.1

= 0.3.1 =
* Improved handling of duplicate domain names

= 0.3 =
* Fixed issues with post Achrives and Categories
* Existing links should now redirect to the correct domain when plugin is enabled

= 0.2 =
* Fixed redirect problem that could occur if WP is installed in a subdirectory

= 0.1 =
* First release

