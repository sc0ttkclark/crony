=== Crony Cronjob Manager ===
Contributors: sc0ttkclark
Donate link: http://scottkclark.com/
Tags: cron, wp_cron, cronjob, cron job, automatic, scheduler
Requires at least: 3.8
Tested up to: 4.4
Stable tag: 0.4.7

Create and Manage Cronjobs in WP by loading Scripts via URLs, including Scripts, running Functions, and/or running PHP code. This plugin utilizes the wp_cron API.

== Description ==

Create and Manage Cronjobs in WP by loading Scripts via URLs, including Scripts, running Functions, and/or running PHP code. This plugin utilizes the wp_cron API.

All you do is install the plugin, schedule your Scripts / Functions / PHP code to run at a specific interval, and live your life -- Cron it up!

== Frequently Asked Questions ==

**What does wp_cron() do?**

As you receive visitors on your site, WordPress checks your database to see if anything is scheduled to run. If you have a wp_cron() job scheduled every 12 hours, then the very first visitor 12+ hours from the last scheduled run of that function will trigger the function to run in the background. The Cronjob (or Cron Job) sends a request to run cron through HTTP request that doesn't halt page loading for the visitor.

**How is wp_cron() different from Server configured Cronjobs?**

Cronjobs configured on a server run on their intervals automatically, while wp_cron() jobs run only after being triggered from a visitor to your site.

== Changelog ==

= 0.4.7 =
* Additional escaping fixes for WP_Admin_UI (reported by Sathish Kumar from cybersecurity works)

= 0.4.6 =
* Escaping fixes for WP_Admin_UI (reported by Sathish Kumar from cybersecurity works)

= 0.4.5 =
* Security fix for orderby handling

= 0.4.4 =
* Fixes for DB tables and reinstalling (when DB tables don't exist but Crony was installed before)

= 0.4.3 =
* Fixes for output e-mails

= 0.4.2 =
* Fixes log saving / max log handling, keeps logs maximum of 2 weeks

= 0.4.1 =
* Now clears logs when adding to the log, keeps max logs set to 80 of the latest run crons
* Bug fixes for WP Admin UI

= 0.4.0 =
* Added Settings area to reset Crony, or empty Crony Logs
* Added URL to load a script / page from, uses wp_remote_post, where the script include only uses include_once
* Bug fixes for WP Admin UI
* Bug fix for schedule running, previously was assuming current timezone for everything but WP runs cron under GMT timestamps

= 0.3.1 =
* Bug fix for dates in Log

= 0.3.0 =
* Added Cronjob Activity Log
* Added View / Remove Existing Cronjobs (external to Crony) and Available Cronjob Schedules
* Various bug fixes
* PHP must now be init with an opening PHP tag for Custom PHP (migrated existing Cronjob code for you)

= 0.1.6 =
* Bug fix, the dates saved didn't include times

= 0.1.5 =
* Bug fix, the menu access was incorrect

= 0.1.4 =
* Bug fix, the column width was off in Firefox in Manage screens

= 0.1.3 =
* Bug fix, the SQL was not installed correctly in 0.1.2
* Added option for E-mail Notifications
* Added Last Run tracking and Ability to set Next Run date

= 0.1.2 =
* Bug fix, the wp_cron jobs were not removed on save, scheduling over previous versions of the same job
* Updated Admin.class.php with latest bug fixes / features

= 0.1.1 =
* Bug fix, the db table was created without an essential field

= 0.1 =
* First official release to the public as a plugin

== Installation ==

1. Unpack the entire contents of this plugin zip file into your `wp-content/plugins/` folder locally
1. Upload to your site
1. Navigate to `wp-admin/plugins.php` on your site (your WP plugin page)
1. Activate this plugin

OR you can just install it with WordPress by going to Plugins >> Add New >> and type this plugin's name

== Official Support ==

Crony Cronjob Manager - Support Forums: http://scottkclark.com/forums/crony-cronjob-manager/

== About the Plugin Author ==

Scott Kingsley Clark from SKC Development -- Scott specializes in WordPress and Pods CMS Framework development using PHP, MySQL, and AJAX. Scott is also a developer on the Pods CMS Framework plugin

== Features ==

= Administration =
* Create and Manage Custom Cronjobs
* View Custom Cronjob Activity Log
* View and Remove Existing Cronjobs
* View Available Cronjob Schedules and Intervals
* Reset Logs or all Crony settings
* Admin.Class.php - A class for plugins to manage data using the WordPress UI appearance

== Roadmap ==

= 0.5.0 =
* Test a Job by running the script via iframe