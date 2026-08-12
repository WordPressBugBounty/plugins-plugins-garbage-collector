=== Plugins Garbage Collector (Database Cleanup) ===
Contributors: shinephp
Donate link: http://www.shinephp.com/donate/
Tags: database, clear, unused tables, cleaner, plugin tables
Requires at least: 4.6
Tested up to: 7.1
Stable tag: 0.15
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Find and delete unused database tables belong to deactivated or deleted plugins directly from WP dashboard.

== Description ==

Database Cleanup plugin scans the database and shows the tables beyond of core WordPress installation. Some WordPress plugins create and use its own database tables. 
Those tables are left in your database after plugin deactivation and deletion often. 
With the help of this plugin you can check your database and discover if it is clean or not.
Extra columns added to the core WordPress tables could be shown also.
To read more about 'Plugins Garbage Collector' visit this link at <a href="http://www.shinephp.com/plugins-garbage-collector-wordpress-plugin/" rel="nofollow">shinephp.com</a>


== Installation ==

Installation procedure:

1. Deactivate plugin if you have the previous version installed.
2. Extract "plugins-garbage-collector.x.x.x.zip" archive content to the "/wp-content/plugins/plugins-garbage-collector" directory.
3. Activate "Plugins Garbage Collector" plugin via 'Plugins' menu in WordPress admin menu. 
4. Go to the "Tools"-"Plugins Garbage Collector" menu item and scan your WordPress database if it has some forgotten tables from old plugins.

== Frequently Asked Questions ==
Comming soon. Just ask it. I will search the answer.


== Screenshots ==
1. screenshot-1.png Plugins Garbage Collector scan action results.


== Changelog ==
= 0.15 [12.08.2026] =
* Update: Full code refactoring to provide compatibility with PHP 8.4, WordPress 7.0
* Update: Auto-refreshed "known plugins" data cache is now stored under wp-content/uploads/pgc-data/ instead of the plugin's own directory, so the plugin no longer needs its own directory to be writable. Falls back to the previous location if the uploads directory isn't usable.
* Update: "Known plugins" JSON data files were moved back to Amazon Web Services S3
* Update: uninstall.php was added.
* Fix: Vulnerability type: Cross Site Request Forgery (CSRF) (OWASP A1: Broken Access Control). Thanks to [patchstack.com] team for the report.
* Fix: Showed false "Database is clean" message if "Newsletters" (Tribulant) plugin is active.

= 0.14 [03.04.2022] =
* Update: "Known plugins" JSON data files were moved from Amazon Web Services S3 to Yandex Cloud Object Storage.
* Update: "Delete Tables" button label was replaced with "Delete Selected Tables".
* Update: Additional confirmation request was added before database tables deletion. It contains the list of tables selected for deletion.

Read changelog.txt for the full list of changes.


== Additional Documentation ==

You can find more information about "Plugins Garbage Collector" plugin at this page
http://www.shinephp.com/plugins-garbage-collector-wordpress-plugin/

I am ready to answer on your questions about this plugin usage. Use plugin page comments or site contact form for that please.
