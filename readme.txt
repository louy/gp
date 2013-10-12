=== Plugin Name ===
Contributors: louyx
Author URL: http://l0uy.com/
Tags: google, oauth, login, google-plus, comment, connect, admin, plugin, comments, wpmu, button
Requires at least: 3.0
Tested up to: 3.5.2
Stable tag: 1.0

All the tools you need to integrate your WordPress and Google+.

== Description ==

GeePress, gives you all the tools you need to integrate your WordPress and Google+, including "Login with Google+" and "Comment via Google+"...
Highly customizable and easy to use.

= Key Features =

* Allow your visitors to comment using their Google+ accounts
* Allow your blog users to sign in with their Google+ accounts. one click signin!
* Easily customizable by theme authors.

== Changelog ==

= 1.0 =
* Initial release

== Installation ==

1. Download the plugin, unzip it and upload it to `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go to Settings &raquo; GeePress App and enter your ID and secret.

== Frequently Asked Questions ==

= When I click "Sign in with Google+", I get a 404 error page. what can I do? =

Well, GeePress uses rewrite, so check your Settings > Permalink page and make sure rewrite is enabled.

= I'm getting an error when I try to sign in with my Google+ account: "Google+ user not recognized!" =

Make sure you've linked your Google+ and wordpress accounts, you can do that in your /wp-admin/profile.php page.

= The comments login button isn't showing! =

It may be because your theme is a bit old or doesn't use the new Wordpress standards.
You have to modify your theme to use this function.

In your comments.php file (or wherever your comments form is), you need to do the following.
1. Find the three inputs for the author, email, and url information. They need to have those ID's on the inputs (author, email, url). This is what the default theme and all standardized themes use, but some may be slightly different. You'll have to alter them to have these ID's in that case.
1. Just before the first input, add this code: &lt;div id="comment-user-details"&gt; &lt;?php do_action('alt_comment_login'); ?&gt;
1. Just below the last input (not the comment text area, just the name/email/url inputs, add this: <code>&lt;/div&gt;</code>

That will add the necessary pieces to allow the script to work.

