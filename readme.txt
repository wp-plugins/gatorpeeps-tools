=== Gatorpeeps Tools ===
Contributors: justinhartman
Donate link: http://gatorpeeps.com
Tags: gatorpeeps, peep, peeps, integration, post, digest, notify, integrate, archive, widget, tools, twitter
Requires at least: 2.3
Tested up to: 2.7.1
Stable tag: 1.1

Gatorpeeps Tools is a plugin that creates a complete integration between your WordPress blog and your Gatorpeeps account.

== Description ==

This plugin integrates your WordPress blog to your Gatorpeeps.com account. It allows you to pull your peeps into your blog (as posts and digests) and create new peeps on blog posts and from within WordPress. 

Gatorpeeps Tools integrates with Gatorpeeps by giving you the following functionality:

* Archive your Gatorpeeps peeps (downloaded every 10 minutes)
* Create a blog post from each of your peeps
* Create a daily or weekly digest post of your peeps
* Create a peep on Gatorpeeps whenever you post in your blog, with a shortened link to the blog post using http://gatorurl.com
* Post a peep from your sidebar
* Post a peep from the WP Admin screens
* Pass your peeps along to Twitter (see topic on Double Posting below)


== Installation ==

1. Download the plugin archive and expand it.
2. Put the 'gatorpeeps-tools.php' file into your wp-content/plugins/ directory.
3. Go to the Plugins page in your WordPress Administration area and click 'Activate' for Gatorpeeps Tools.
4. Go to the Gatorpeeps Tools Options page (Options > Gatorpeeps Tools) to set your Gatorpeeps account information and preferences.


== Configuration ==

There are a number of configuration options for Gatorpeeps Tools. You can find these in Options > Gatorpeeps Tools.

== Showing Your peeps ==

= Widget Friendly =

If you are using widgets, you can drag Gatorpeeps Tools to your sidebar to display your latest peeps.

= Template Tags =

If you are not using widgest, you can use a template tag to add your latest peeps to your sidebar.

`<?php peeps_sidebar_peeps(); ?>`


If you just want your latest peep, use this template tag.

`<?php peeps_latest_peep(); ?>`

== Double posting to Gatorpeeps and Twitter ==

You can not directly post to Gatorpeeps and Twitter as this plugin only posts directly to Gatorpeeps. However, if you'd like to post to both services then follow these simple steps.

1. Log into [Gatorpeeps](http://gatorpeeps.com)
2. Click on the [settings page](http://gatorpeeps.com/settings)
3. Enter your twitter username and password and hit save

Easy as one, two, three :) Anything you post via this plugin to Gatorpeeps will also update to your Twitter account.


== Hooks/API ==

Gatorpeeps Tools contains a hook that can be used to pass along your peep data to another service (for example, some folks have wanted to be able to update their Facebook status). To use this hook, create a plugin and add an action to:

`peeps_add_peep`

Your plugin function will receive an `aktt_peep` object as the first parameter.

Example psuedo-code:

`function my_status_update($peep) { // do something here }`
`add_action('peeps_add_peep', 'my_status_update')`

== Known Issues ==

* Only one Gatorpeeps account is supported (not one account per author).
* peeps are not deleted from the peep table in your WordPress database when they are deleted from Gatorpeeps. To delete from your WordPress database, use a database admin tool like phpMyAdmin.

== Frequently Asked Questions ==

= Who is allowed to post a peep from within WordPress? =

Anyone who has a 'publish_post' permission. Basically, if you can post to the blog, you can also post to Gatorpeeps (using the account info in the Gatorpeeps Tools configuration).

= What happens if I have both my peeps posting to my blog as posts and my posts sent to Gatorpeeps? Will it cause the world to end in a spinning fireball of death? = 

Actually, Gatorpeeps Tools has taken this into account and you can safely enable both creating posts from your peeps and peeps from your posts without duplicating them in either place.

= Does Gatorpeeps Tools use a URL shortening service by default? =

Yes, Gatorpeeps Tools uses http://gatorurl.com to shorten your blog post links. This is done to prevent new peeps from being cut off when posting to Gatorpeeps.

= Is there any way to change the 'New Blog Post:' prefix when my new posts get peeped? =

Yes there is, but you have to change the code in the plugin file. 

The reason this is done this way, and not as an easily changeable option from the admin screen, is so that the plugin correctly identifies the peeps that originated from previous blog posts when creating the digest posts, displaying the latest peep, displaying sidebar peeps, and creating blog posts from peeps (you don't want peeps that are blog post notifications being treated like peeps that originated on Gatorpeeps).

To make the change, look for and modify the following line: 

`$this->peeps_prefix = 'New blog post';`

= Can I remove the 'New Blog Post:' prefix entirely? =

No, this is not a good idea. Gatorpeeps Tools needs to be able to look at the beginning of the peep and identify if it's a notification from your blog or not. Otherwise, Gatorpeeps Tools and Gatorpeeps could keep passing the blog posts and resulting peeps back and forth resulting in the 'spinning fireball of death' mentioned above.

== Screenshots ==

1. Gatorpeeps Tools menu link in the Wordpress 2.7.1 Settings tab.
2. Gatorpeeps Tools Options page. You can change all the settings on this page.
3. Test blog showing Gatorpeeps Daily Digest of peeps as well as Sidebar Widget with 5 latest peeps.
4. The "peep" menu under the Wordpress 2.7.1 Posts tab. Clicking this link will allow you to peep directly from the Dashboard to Gatorpeeps.
5. Screenshot showing the Writing of Peeps via the Wordpress admin area.
6. Setting under new blog post that let's you decide if you want to post to Gatorpeeps or not.

== Credits ==

This plugin would simply not be possible without the magnificent [Twitter Tools](http://alexking.org/projects/wordpress) plugin created by Alex King. We have adapted Alex's original plugin to work with the Gatorpeeps API so we can't claim credit for this plugin at all! Thanks Alex!

== History ==
* 2009-05-04 = Fixed the relative timestamp in sidebar peeps.
* 2009-05-04 = Fixed a bug where fopen wasn't supported on server. We now check if fopen is supported before converting to short url.
* 2009-05-04 = Fixed some php fatal errors with rewriting of the global $peeps.
* 2009-05-03 = Version 1.0 released. Adapted to Gatorpeeps API.
