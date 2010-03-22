=== BuddyPress Group Email Subscription ===
Contributors: David Cartwright, boonebgorges, Deryk Wenaus
Donate link: http://namoo.co.uk/
Tags: buddypress, activities, groups, emails, notifications
Requires at least: 2.9.1
Tested up to: 2.9.2 BP 1.2.1
Stable tag: 2.0b

This plugin allows members to subscribe to groups to receive email notifications for forum topics and posts and activity updates. 

== Description ==

This plugin is currently in early beta status. Please refrain from using on production websites. (It is here in the plugin directory so we can use the trac features.)

This plugin allow group members to receive email updates about new forum topics, forum posts and group activity updates. In order to get updates, a user must either subscribe or super-subscribe to the group. Subscribed users will get all new forum topics, but not the subsequent comments (same with activity posts). If a user super-subscribes to the group, they will get an email will all new content.

By default, the plugin will send email updates to a user for any comments on a forum topic they create or comment on. These settings are controlled in the users' settings->notifications page. 

We are actively working on an email digest function.

Group admins and mods can also override the user settings for updates to send out group notifications to all their group users.

To protect against spam, you can set a minimum time users need to be registered before people receive email. This feature is off by default, but can be enabled in the admin.

In the final version, the plugin will be internationalized, and there will be more helpful support text and admin options. 

NOTE TO PLUGIN AUTHORS: You can hook in your own activity types. See the example code below:

`// an example of a plugin adding an activity filter to group email notifications
function my_fun_activity_filter( $ass_activity ) {
	$ass_activity[ 'wiki_add' ] = array(
		"level"=>"sub",  // can be either "sub" or "supersub"
		"name_past"=>"added a wiki page", 
		"name_pres"=>"adds a wiki page", 
		"section"=>"Wiki"
		);
	return $ass_activity;
}
add_filter( 'ass_activity_types', 'my_fun_activity_filter' );
`

The above code adds support for a new activity type "wiki_add" at the regular subscription level. 

== Installation ==

1. Install plugin
2. Do a dance (optional)

== Screenshots ==

1. Changing Email Options
1. Example Email

== Changelog ==

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

