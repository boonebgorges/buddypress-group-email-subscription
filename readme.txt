=== BuddyPress Group Email Subscription ===
Contributors: boonebgorges, dwenaus, aekeron
Tags: buddypress, bp, activities, activity, groups, group, emails, email, notifications, notification, subscribe, subscription, digest, summary
Requires at least: 2.9.1 BP 1.2
Tested up to: 2.9.2 BP 1.2.4
Stable tag: trunk

This powerful plugin allows people to receive email notifications of group activity, especially forum posts. Weekly or daily digests available.

== Description ==

This powerful plugin allows people to receive email notifications of group activity, especially forum posts. Weekly or daily digests available. Each user can choose how they want to subscribe to their groups. 

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
The daily digest email is sent every morning and contains all the emails from all the groups a user is subscribed to. The digest begins with a helpful topic summary. The weekly summary email contains just the topic titles from the past week. Summary and digest timing can be configured in the back end. 

HTML EMAILS
The digest and summary emails are sent out in multipart HTML and plain text email format. This makes the digest much more readable with better links. The email is multipart so users who need only plain text will get plain text. 

EMAILS FOR TOPICS I'VE STARTED OR COMMENTED ON
Users receive email notifications when someone replies to a topic they create or comment on (similar to Facebook). This happens whether they are subscribed or not. Users can control this behaviour in their notifications page.

TOPIC FOLLOW AND MUTE
Users who are not fully subscribed to a group (ie. maybe they are on digest) can choose to get immediate email updates for specific topic threads. Any subsequent replies to that thread will be emailed to them. In an opposite way, users who are fully subscribed to a group but want to stop getting emails from a specific (perhaps annoying) thread can choose to mute that topic.

ADMIN NOTIFICATION
Group admins can send out an email to all group members from the group's admin section. The email will be sent to all group members regardless of subscription status. This feature is helpful to quickly communicate to the whole group, but it should be used with caution.

SPAM PROTECTION
To protect against spam, you can set a minimum number of days users need to be registered before their group activity will be emailed to other users. This feature is off by default, but can be enabled in the admin.

The plugin is fully internationalized.

NOTE TO PLUGIN AUTHORS
If your plugin posts updates to the standard BuddyPress activity stream, then group members who are subscribed via 3. Daily Digest and 5. All Email will get your updates automatically. However people subscribed as 2. Weekly Summary and 4. New Topic will not. If you feel some of your plugin's updates are very important and want to make sure all subscribed members them, then you can filter  'ass_this_activity_is_important' and return TRUE when $type matches your activity. See the ass_this_activity_is_important() function in bp-activity-subscription-functions.php for code you can copy and use. An example: adding a new wiki page would be considered important and should be filtered in, whereas a comment on a wiki page would be less important and should not be hooked in.


== Installation ==

1. Install plugin
2. Go to the front end and set your email options for each of your groups
3. On the group admin settings, set the default subscription status of existing groups
4. to enable follow and mute on individual topic pages, edit core file `bp-themes/bp-default/groups/single/forum/topic.php` and add `<?php do_action( 'bp_before_group_forum_topic_posts' ) ?> ` around line 19, just before `<div id="topic-meta">`. A trac request has been added for this hook. so in future versions of BuddyPress, the line may already exist. 

== Screenshots ==

1. Email Options on settings page
2. Email Options on other group pages
3. Email Options in Group Directory
4. Sample Email (HTML emails in future versions)
5. Follow and mute links in group forum
6. Send Email Notice to entire group (admin only)
7. Admin Settings

== Changelog ==

= 2.4 =
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

