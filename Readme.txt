=== Skydrv-hotlink ===
Contributors: dpchiesa
Donate link: http://dinochiesa.github.io/Skydrv-hotlink-Donate.html
Tags: Google+, social, widget
Requires at least: 3.2
Tested up to: 3.9.1
Stable tag: 2014.07.03
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.txt

== Description ==

skydrv-hotlink is a Wordpress Plugin.

Onedrive is a cloud-based storage service offered by Microsoft, accessible at http://onedrive.com .  It's free. It works nicely and integrates well with Microsoft Office and Windows. Previously it was known as Skydrive. 

One problem is that Onedrive does not easily provide direct-download links for the files located on onedrive. Suppose you would like to share a document that exists on your Skydrive.  You click the "share" button, and Skydrive gives you a link which you can then embed into any web page.

But, that link is not a direct-download link for the file you intend to share. The actual download link will be embedded *within that page*. Clicking the "share" link provided by onedrive takes the user to onedrive, where they need to click another link to download the document.

This is fine, but perhaps not as convenient as it could be for users, in some cases.

This plugin allows a wordpress blog to eliminate that extra hop.
It automatically convert links to Onedrive documents into direct-download links.


The way it works:

1. You login to Onedrive.

2. Select the file you would like to share.

3. Click the "share" button. (see screenshot #1)

4. Copy (ctrl-C) the link for that particular file.  Don't use the URL shortener. The link should look something like this:

https://onedrive.live.com/redir?resid=842434EBE9688900!1169&authkey=!AA4kWNcMStEjSUg&ithint=file%2c.pptx

or like this:

https://onedrive.live.com/redir?resid=842434EBE9688900!1123


5. Insert an anchor tag into one of your wordpress posts or one of your
wordpress pages; specify that value as the href.  It should look like
this:

&lt; a src='https://onedrive.live.com/?id=842434EBE9688900!1123&cid=842434ebe9688900#'
   class='skydrv-hotlink'&gt;Download the file&lt;/ a &gt;

The markup is regular HTML markup, but the anchor must be decorated with the special class 'skydrv-hotlink'.  (see screenshot #2)

6. When the actual page or post is rendered, the plugin will transform the href into a direct-download link.  The transformed href will look something like this:

https://pszy1q.bay.livefilestore.com/y1p...many characters...Qgg/Dino%20Chiesa%20-%20Resume.docx

When the user clicks that link, it will download the file directly.

Note: The direct-download link has a limited lifetime, so you should not retain it indefinitely. In fact that is the entire reason this plugin exists. Set the cache period for the plugin to set the lifetime of any link. Generally, I've found that links are good for at least an hour.


== Installation ==

1. Download skydrv-hotlink-wp-plugin.zip and unzip into the
  `/wp-content/plugins/` directory

2. From the Wordpress admin backend, Activate the plugin through the
   'Plugins' menu

3. From the Wordpress admin backend, in the Settings menu, specify the
   cache lifetime if any.

4. In your posts and pages, insert anchor tags marked with the 'skydrv-hotlink' class.


That's it !


== Frequently Asked Questions ==

= Why would anyone use this plugin? =

Onedrive is a nice service that allows people to host files "in the cloud". It's also possible to share some of those files with others. But Skydrive doesn't provide an easy way to get a direct-download link for a file that you want to share.

This plugin lets you do that. It lets you embed a link to a file hosted on skydrive, into any wordpress page or post.  Then viewers will be able to download that file without first connecting directly to the skydrive site.

= How does this plugin really work? =

Warning: geek-speak ahead.

The plugin is implemented in two pieces.  There is some browser-side Javascript logic, and some server-side PHP logic.

On the server side, the plugin tells wordpress to append a small javascript module in each page that is rendered. This Javascript module contains client-side logic that relies on jQuery. It scans the rendered page for any anchor tags decorated with the class 'skydrv-hotlink'.  Upon finding one, it sends an AJAX request to admin-ajax.php, which is a page on the wordpress site. If the client-side logic finds no specially-marked anchor tags, then nothing further happens.

The skydrv-hotlink plugin registers with wordpress to receive the
requests from the client-side logic. Upon receiving such a request,
Wordpress routes the incoming request to the plugin. The plugin checks
the cache for the requested link. If it is not available in cache, the
plugin retrieves the full download page from Onedrive, scrapes the
resulting HTML page, and extracts the hidden direct-download link. The
plugin then returns the direct-download link to the javascript logic
running in the browser, as a json blob.

The javascript in the browser then replaces the href tag in the anchor with the direct download link.

= What happens if, for whatever reason, the plugin fails? =

The plugin is designed so that if the lookup of the direct-download link fails, the original anchor (A tag) continues to work with its original href. In the case of failure, when the user clicks the A link, instead of getting a direct-download, he will visit the usual Onedrive download page.

= Do I need to define a css class called 'skydrv-hotlink'? =

No. It's used as a marker for the plugin. You need not attach any css
styling to that class name. You can do so if you like.

= What if I want to style my anchor tags with a different class? =

You can specify multiple classes on html tags.  The syntax is like this:

 <a href='http://ksk'  class='skydrv-hotlink my-other-class'>...</a>

= Can viewers of the site detect that the plugin is in use? =

Generally, no. A savvy user could examine the source of your wordpress page and see the client-side javascript that does the replacement of hrefs.

= Why doesn't this plugin use a shortcode? =

It's just a design choice.

- The approach used by this plugin results in faster page loads. The
  replacement of regular share links with direct download links *can*
  require a call out to Microsoft Onedrive, which can take several
  seconds. Doing this on the server-side with a shortcode would simply
  add those several seconds to the page render time that the user sees.

  But this plugin perfoms the replacement of the href on the
  browser-side, using client-side javascript. This replacement occurs
  after the page has been loaded and rendered. Only the href changes,
  and that happens with no discernable UI update. The result is a faster
  page load time.

- Also, the approach used by this plugin is also more reliable than a
  short code. Here's why: if for any reason the connection to onedrive
  fails, the anchor link will remain unchanged, pointing to the regular
  share page on onedrive.


= Does the plugin perform caching? =

Yes. On the server side, the plugin caches links and re-uses them.  You can specify the lifetime of the cache in the admin back-end. Caching allows the pages to render more quickly. It will also reduce the number of outbound http connections initiated by your wordpress site, which is a good thing in general.

= Where is the cache stored? =

The direct links are cached in files in a subdirectory of the wp-content directory.
The subdirectory is named skydrv-hotlink-cache . The cache files are very small, around 128 bytes each. There will be at most one cache file per hotlinked file.

= Will I be charged a fee by Onedrive if I use this plugin? =

No. As far as I know, Onedrive is free.

= Will I be charged a fee by my hoster if I use this plugin? =

I don't know. The plugin makes outbound http connections. Most hosters allow this, and don't charge a fee for data transmission. If you're not sure, check with your hoster.

= Do I need to do anything special on Onedrive to make this work? =

You need to set permissions on the file to be downloaded, to make it publicly accessible. Consult the help for Onedrive to understand how to do this.

= How is the AJAX stuff implemented? =

The plugin uses the recommended wordpress practice of registering an
action word with admin-ajax.php.

= Can any user access the AJAX request that provides direct download links? =

No. This plugin verifies that the request for download links comes from one of the posts or pages of your blog. It uses Wordpress API check_ajax_referer() to do so.


== Screenshots ==

1. This shows Windows Onedrive; right-click to select the share option for a document.
2. This shows how to specify anchor tags in wordpress pages.
3. The Settings menu in the wordpress admin backend. The menu item for skydrv-hotlink is showed here at the bottom of the list. Click it to set the cache lifetime value.


== Changelog ==

= 2014.07.03 =
* tested on wordpress version 3.9.1
* Readme updated to use the term "Onedrive" instead of "Skydrive"

= 2012.07.06 =
* replace space with + in the base64-encoded token before
  decoding. Without this fix, sometimes the validation of ajax
  requests would deliver false negatives.

= 2012.06.26 =
* generalize the regex to accept more onedrive links.

= 2012.06.25 =
* introduced more flexibility into browser javascript for handling hrefs.
* fix admin form to display properly when mcrypt does not exist.

= 2012.06.24 =
* relaxed the hard dependency on mcrypt().

= 2012.06.23a =
* fixed the external admin form so that it displays properly.
* include error_log() statements to aid in diagnosis in case of problems.

= 2012.06.23 =
* include code to prevent direct access, correctly.

= 2012.06.22 =
* fixed a bug in the generation of random strings.
* un-broke activation.

= 2012.06.21 =
* delegated admin form rendering to an external php file
* slightly tweaked readme.

= 2012.06.10 =
* first tagged release
* refactored and improved AJAX token validation

= 2012.06.06 =
* initial checkin to SVN
* slight refactoring of php code

= 2012.6.4 =
* added verifier token to prevent replays

= 2012.6.3 =
* initial release

== Dependencies ==

- This plugin relies on jQuery.
- Therefore the user viewing your wordpress blog must have browser side Javascript enabled.
- The publisher of the target document must have a Onedrive account.
- The wordpress host must allow outgoing http connections. (true in most cases)
- Wordpress must be configured to enable curl. (true in most cases)
- PHP should have mcrypt enabled. If not, operation of the plugin is less secure.


== Thanks ==

Thanks for your interest!

You can make a donation at http://dinochiesa.github.io/Skydrv-hotlink-Donate.html

Check out all my plugins:
http://wordpress.org/extend/plugins/search.php?q=dpchiesa


-Dino Chiesa

