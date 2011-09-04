=== BuddyPress Group Email Subscription ===
Contributors: dwenaus, boonebgorges
Description: This powerful plugin allows people to receive email notifications of group activity, especially forum posts. Weekly or daily digests available.
Tags: buddypress, bp, activities, activity, groups, group, emails, email, notifications, notification, subscribe, subscription, digest, summary
Requires at least: 2.9.1 (BP 1.2)
Tested up to: 3.2.1
Stable tag: 2.9.2

== Description ==

This powerful plugin allows people to receive email notifications of group activity, especially forum posts. Weekly or daily digests available. Each user can choose how they want to subscribe to their groups. The plugin works for hundreds of groups and users. Tested up to BuddyPress 1.5-beta-2.

EMAIL SUBSCRIPTION LEVELS
There are 5 levels of email subscription options: 
1. No Email - Read this group on the web
2. Weekly Summary Email - A summary of new topics each week
3. Daily Digest Email - All the day's activity bundled into a single email
4. New Topics Email - Send new topics as they arrive (but don't send replies)
5. All Email - Send all group activity as it arrives 

DEFAULT SUBSCRIPTION STATUS
Group admins can choose one of the 5 subscription levels as a default that gets applied when new members join. 

DIGEST AND SUMMARY EMAILS
The daily digest email is sent every morning and contains all the emails from all the groups a user is subscribed to. The digest begins with a helpful topic summary. The weekly summary email contains just the topic titles from the past week. Summary and digest timing can be configured in the back end. (The admin can view a sample of the digests and summaries in the queue by going adding this to your url: mydomain.com/sum=1. This won't send emails just show what will be sent)

HTML EMAILS
The digest and summary emails are sent out in multipart HTML and plain text email format. This makes the digest much more readable with better links. The email is multipart so users who need only plain text will get plain text. 

EMAILS FOR TOPICS I'VE STARTED OR COMMENTED ON
Users receive email notifications when someone replies to a topic they create or comment on (similar to Facebook). This happens whether they are subscribed or not. Users can control this behaviour in their notifications page.

TOPIC FOLLOW AND MUTE
Users who are not fully subscribed to a group (ie. maybe they are on digest) can choose to get immediate email updates for specific topic threads. Any subsequent replies to that thread will be emailed to them. In an opposite way, users who are fully subscribed to a group but want to stop getting emails from a specific (perhaps annoying) thread can choose to mute that topic.

ADMIN NOTIFICATION
Group admins can send out an email to all group members from the group's admin section. The email will be sent to all group members regardless of subscription status. This feature is helpful to quickly communicate to the whole group, but it should be used with caution.

MOVING AN EXISTING SITE
Site admins can reset the subscription level for CURRENT group members by clicking the link at the bottom of the manage members admin page. 

SPAM PROTECTION
To protect against spam, you can set a minimum number of days users need to be registered before their group activity will be emailed to other users. This feature is off by default, but can be enabled in the admin.

GROUP ADMINS CAN SET SUBSCRIPTION LEVEL
Group admins can set the subscription level for existing users in the manage members admin page - either one by one or all at once.

INTERNATIONALIZATION
The plugin is fully internationalized (thanks to chestnut_jp) Please send language po and mo files to deryk@bluemandala.com and i'll include them.  

TRANSLATORS
- japanese - http://buddypress.org/community/members/chestnut_jp/
- spanish - Williams Castillo
- italian - Stefano Russo
- french - http://www.claudegagne-photo.com
- brazilian portuguese - www.about.me/dennisaltermann (or www.congregacao.net)
- dutch - Anja werkgroepen.net/wordpress
- swedish - Thomas Schneider
- german - Peter Peterson
- russian - http://www.viaestvita.net/groups/

NOTE TO PLUGIN AUTHORS
If your plugin posts updates to the standard BuddyPress activity stream, then group members who are subscribed via 3. Daily Digest and 5. All Email will get your updates automatically. However people subscribed as 2. Weekly Summary and 4. New Topic will not. If you feel some of your plugin's updates are very important and want to make sure all subscribed members them, then you can filter  'ass_this_activity_is_important' and return TRUE when $type matches your activity. See the ass_this_activity_is_important() function in bp-activity-subscription-functions.php for code you can copy and use. An example: adding a new wiki page would be considered important and should be filtered in, whereas a comment on a wiki page would be less important and should not be hooked in.

PLUGIN SUPPORTERS: 
Major supporters: shambhalanetwork.org & commons.gc.cuny.edu
Other supporters: bluedotproductions.com

== Installation ==

1. Install plugin
2. Go to the front end and set your email options for each of your groups
3. On the group admin settings, set the default subscription status of existing groups
4. For groups with existing members, go to the bottom of the manage members tab to set all members to the default subscription
5. if emails are not being sent ensure that the activity component is turned on

== Screenshots ==

1. Email Options on settings page
2. Email Options on other group pages
3. Email Options in Group Directory
4. Sample Email (HTML emails in future versions)
5. Follow and mute links in group forum
6. Send Email Notice to entire group (admin only)
7. Admin Settings

== Changelog ==

= 2.9.2 =
Fixed some syntax errors that may have caused problems with digest formatting

= 2.9.1 =
Fixed bug that may have caused scripts and styles not to be loaded on BP 1.2.x
Fixed some PHP notices
Fixed bug that might have caused BPGES javascript to load before jQuery in some cases

= 2.9 =
Full support for BuddyPress 1.5
Code cleanup and normalization

= 2.8.7 =
Tweaked email text so that contentless notifications don't get empty quotation marks

= 2.8.6 =
Added simple activity item caching to digests to reduce database queries at digest time
Fixed bug that caused hidden items not to be sent in digests

= 2.8.5 =
added better brazilian portuguese translation, improved error checking to see if user has email before emailing them

= 2.8.5 =
fixed bug introduced in latest BP version where when an group admin manually removes a group member in Group Admin -> Manage Members -> Remove from group, the user kept getting emails, 
added translation strings for the javascript mute/follow, 
minor code cleanup to how digest/sumaries are stored, 
Added Russian Translation.
added filter to digest subject line
fixed error where only site admins could send out instant emails and not group admins

= 2.8.4 =
Added German Translation

= 2.8.3 =
Fixed issue where activity updates had html in the email subject line

= 2.8.2 =
Fixes Dashboard panel to be compatible with WP 3.1/BP 1.2.8 multisite. Also adds a helpful "settings saved" message to dashboard panel.

= 2.8.1 =
fixed issue when plugin was hiding new group activity update comments

= 2.8 =
bunch of new updates/fixes:  group activity replies are now registered in the plugin and emailed/digested accordingly. Users can now choose to get emails for posts they make (in their settings page). Useful filters were added, you can now add a header or footer to the digest emails. Fixed a bug where group names were sometimes missing for private groups in the digest. Fixed minor bug where people on weekly digest who followed a single topic don't get any emails. Added swedish translation. 

= 2.7.9 =
now translated in japanese, spanish, french, portuguese, italian and dutch. no changes to the code on this update.

= 2.7.8 =
internationalization bug finally fixed. Spanish and Italian now included. 'Email Options' is now translatable

= 2.7.7 =
internationalization bug fixed.

= 2.7.6 =
the cron schedule is now fully fixed, thanks to gvvaughan. plus the plugin is now fully internationalized thanks to chestnut_jp.

= 2.7.5 =
the plugin is now fully internationalized thanks to chestnut_jp! Please send other language po and mo files to deryk@bluemandala.com and i'll include them. load_plugin_textdomain is used also. NOTE: due to a bug in buddypress 1.2.6 users will not be able to see their notifications page (see here for a fix http://trac.buddypress.org/changeset/3375)

= 2.7.3 =
fixed bug where hidden groups would not have names in digest, added link to view digest queue in back end (add ?sum=1 to your url to see queue)

= 2.7.2 =
fixed bug causing wp_cron to misbehave

= 2.7.1 =
fixed bug where digests were not fired - the next digest will combine all missed ones. Fixed bug where edited topics were removed from digest, added note and link at end of users' notification settings pointing them to place they can edit their group subscriptions

= 2.7 =
Major re-write to the digest code, implemented caching and drastically sped up processing, other minor changes. see (but not send) what is in the digest queue by visiting mydomain.com/sum=1

= 2.6.4 =
added 'change email options' link under each group in digest 

= 2.6.3 =
rewrote the email sending so digest emails are read properly in Outlook. Added a link at bottom of digests to go to my groups to edit notification settings. 

= 2.6.2 =
fixed sneaky bug where people who requested access to a private group were subscribed to default setting.

= 2.6.1 =
improves wording of join pull down menu, fix bad follow/mute link. 

= 2.6 =
allow site admins to set the subscription level for all current users of a group (in manage members)

= 2.5.5 =
put back text domain, added more translation hooks

= 2.5.4 =
added css class names, fixed duplication in email display when sending New Topics email

= 2.5.2 =
fixed class name

= 2.5.1 =
fixed silly bug where th tag was not showing up in group forum directory to match the extra td tag for follow/mute. sorry for the delay.

= 2.5 =
fixed a bug where digest emails were cumulative. fixed bug where editing an item would resend the notification email. added filter hooks to digest section

= 2.4.4 =
improved the follow this topic text in New Topic subscription emails so the user knows they won't get replies and gives them instructions if they do. 

= 2.4.3 =
Added ability for group admins to change the subscription settings for users in their group. Added backend options to enable or disable this function. Also added backend options to enable group admin override emails. 

= 2.4.2 =
Fixed minor bug about leaving groups your not subscribed to

= 2.4.1 =
Made daily digests and weekly summaries HTML/Plain text multipart emails

= 2.3.4 =
Added quotes around topic name in emails and added setting status at bottom of emails

= 2.3.2 =
Javascript fix for subscribe options. removed beta notice.

= 2.3 =
Plugin finished and ready for public usage.

= 2.2 =
Plugin complete re-write finished. Digest function added, plus too many features to list here.

= 2.1 =
group admins can set default subscription level

= 2.0 =
Plugin totally re-written by Deryk Wenaus, with new structure and name

= 1.3 =
Added support for topic-by-topic settings for forum notifications

= 1.2 =
Tagged stable release.
Added Boone Gorges as an author.
Made "do a dance" installation step optional.

= 1.1 =
Fixed directory rename causing white screen of death.

= 1.0 =
Initial release.  Please test and provide feedback.  Not recommended for production sites.

