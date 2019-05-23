<?php
// Version: 2.1 RC2; EmailTemplates

// Since all of these strings are being used in emails, numeric entities should be used.

// Do not translate anything that is between {}, they are used as replacement variables and MUST remain exactly how they are.
//   Additionally, do not translate the @additional_params: line or the variable names in the lines that follow it. You may
//   translate the description of the variable. Do not translate @description:, however you may translate the rest of that line.

// Do not use block comments in this file, they will have special meaning.

global $txtBirthdayEmails;

/**
	@additional_params: resend_activate_message
		REALNAME: The display name for the member receiving the email.
		USERNAME:  The user name for the member receiving the email.
		ACTIVATIONLINK:  The url link to activate the member's account.
		ACTIVATIONCODE:  The code needed to activate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['resend_activate_message_subject'] = 'Welcome to {FORUMNAME}';
$txt['resend_activate_message_body'] = 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can reset it by visiting {FORGOTPASSWORDLINK}.

Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}';

/**
	@additional_params: resend_pending_message
		REALNAME: The display name for the member receiving the email.
		USERNAME:  The user name for the member receiving the email.
	@description:
*/
$txt['resend_pending_message_subject'] = 'Welcome to {FORUMNAME}';
$txt['resend_pending_message_body'] = 'Hello {REALNAME}, your registration request at {FORUMNAME} has been received.

The username you registered with was {USERNAME}.

Before you can login and start using the forum, your request will be reviewed and approved.

{REGARDS}';

/**
	@additional_params: mc_group_approve
		USERNAME: The user name for the member receiving the email.
		GROUPNAME: The name of the membergroup that the user was accepted into.
	@description: The request to join a particular membergroup has been accepted.
*/
$txt['mc_group_approve_subject'] = 'Group Membership Approval';
$txt['mc_group_approve_body'] = '{USERNAME},

We\'re pleased to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been accepted, and your account has been updated to include this new membergroup.

{REGARDS}';

/**
	@additional_params: mc_group_reject
		USERNAME: The user name for the member receiving the email.
		GROUPNAME: The name of the membergroup that the user was rejected from.
	@description: The request to join a particular membergroup has been rejected.
*/
$txt['mc_group_reject_subject'] = 'Group Membership Rejection';
$txt['mc_group_reject_body'] = '{USERNAME},

We\'re sorry to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been rejected.

{REGARDS}';

/**
	@additional_params: mc_group_reject_reason
		USERNAME: The user name for the member receiving the email.
		GROUPNAME: The name of the membergroup that the user was rejected from.
		REASON: Reason for the rejection.
	@description: The request to join a particular membergroup has been rejected with a reason given.
*/
$txt['mc_group_reject_reason_subject'] = 'Group Membership Rejection';
$txt['mc_group_reject_reason_body'] = '{USERNAME},

We\'re sorry to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been rejected.

This is due to the following reason: {REASON}

{REGARDS}';

/**
	@additional_params: admin_approve_accept
		NAME: The display name of the member.
		USERNAME: The user name for the member receiving the email.
		PROFILELINK: The URL of the profile page.
		FORGOTPASSWORDLINK: The URL of the "forgot password" page.
	@description:
*/
$txt['admin_approve_accept_subject'] = 'Welcome to {FORUMNAME}';
$txt['admin_approve_accept_body'] = 'Welcome, {NAME}

Your account has been activated manually by the admin and you can now login and post. Your username is: {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}.

{REGARDS}';

/**
	@additional_params: admin_approve_activation
		USERNAME: The user name for the member receiving the email.
		ACTIVATIONLINK:  The url link to activate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE: The activation code.
	@description:
*/
$txt['admin_approve_activation_subject'] = 'Welcome to {FORUMNAME}';
$txt['admin_approve_activation_body'] = 'Welcome, {USERNAME}!

Your account on {FORUMNAME} has been approved by the forum administrator. Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}';

/**
	@additional_params: admin_approve_reject
		USERNAME: The user name for the member receiving the email.
	@description:
*/
$txt['admin_approve_reject_subject'] = 'Registration Rejected';
$txt['admin_approve_reject_body'] = '{USERNAME},

Regrettably, your application to join {FORUMNAME} has been rejected.

{REGARDS}';

/**
	@additional_params: admin_approve_delete
		USERNAME: The user name for the member receiving the email.
	@description:
*/
$txt['admin_approve_delete_subject'] = 'Account Deleted';
$txt['admin_approve_delete_body'] = '{USERNAME},

Your account on {FORUMNAME} has been deleted. This may be because you never activated your account, in which case you should be able to register again.

{REGARDS}';

/**
	@additional_params: admin_approve_remind
		USERNAME: The user name for the member receiving the email.
		ACTIVATIONLINK:  The url link to activate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE: The activation code.
	@description:
*/
$txt['admin_approve_remind_subject'] = 'Registration Reminder';
$txt['admin_approve_remind_body'] = '{USERNAME},
You still have not activated your account at {FORUMNAME}.

Please use the link below to activate your account:
{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}';

/**
	@additional_params:
		USERNAME: The user name for the member receiving the email.
		ACTIVATIONLINK:  The url link to activate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE: The activation code.
	@description:
*/
$txt['admin_register_activate_subject'] = 'Welcome to {FORUMNAME}';
$txt['admin_register_activate_body'] = 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME} and your password is {PASSWORD}.

Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}';

$txt['admin_register_immediate_subject'] = 'Welcome to {FORUMNAME}';
$txt['admin_register_immediate_body'] = 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}, your password is {PASSWORD} and the forum url is: {SCRIPTURL}.

{REGARDS}';

/**
	@additional_params: new_announcement
		TOPICSUBJECT: The subject of the topic being announced.
		MESSAGE: The message body of the first post of the announced topic.
		TOPICLINK: A link to the topic being announced.
	@description:
*/
$txt['new_announcement_subject'] = 'New announcement: {TOPICSUBJECT}';
$txt['new_announcement_body'] = '{MESSAGE}

To unsubscribe from these announcements, login to the forum and uncheck "Receive forum announcements and important notifications by email." in your profile.

You can view the full announcement by following this link:
{TOPICLINK}

{REGARDS}';

/**
	@additional_params: notify_boards_once_body
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		MESSAGE: This is the body of the message.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
	@description:
*/
$txt['notify_boards_once_body_subject'] = 'New Topic: {TOPICSUBJECT}';
$txt['notify_boards_once_body_body'] = 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

More topics may be posted, but you won\'t receive more email notifications until you return to the board and read some of them.

The text of the topic is shown below:
{MESSAGE}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notify_boards_once
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
	@description:
*/
$txt['notify_boards_once_subject'] = 'New Topic: {TOPICSUBJECT}';
$txt['notify_boards_once_body'] = 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

More topics may be posted, but you won\'t receive more email notifications until you return to the board and read some of them.

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notify_boards_body
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		MESSAGE: This is the body of the message.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
	@description:
*/
$txt['notify_boards_body_subject'] = 'New Topic: {TOPICSUBJECT}';
$txt['notify_boards_body_body'] = 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

The text of the topic is shown below:
{MESSAGE}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notify_boards
		TOPICSUBJECT: The subject of the topic causing the notification
		TOPICLINK: A link to the topic.
		UNSUBSCRIBELINK: Link to unsubscribe from notifications.
	@description:
*/
$txt['notify_boards_subject'] = 'New Topic: {TOPICSUBJECT}';
$txt['notify_boards_body'] = 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: alert_unapproved_reply
		SUBJECT: The subject of the topic causing the notification
		LINK: A link to the topic.
	@description:
*/
$txt['alert_unapproved_reply_subject'] = 'Topic reply: {SUBJECT}';
$txt['alert_unapproved_reply_body'] = 'A reply has been posted to \'{SUBJECT}\' by {POSTERNAME}.

You can see it at
{LINK}

{REGARDS}';

/**
	@additional_params: unapproved_attachment
		SUBJECT: The subject of the topic causing the notification
		LINK: A link to the message with the attachment.
	@description:
*/
$txt['unapproved_attachment_subject'] = 'New Unapproved Attachment in: {SUBJECT}';
$txt['unapproved_attachment_body'] = 'A new attachment has been made in \'{SUBJECT}\' which needs to be approved.

You can approve or reject this attachment from the link below which will take you to the message that it is a part of.

{LINK}

{REGARDS}';

/**
	@additional_params: alert_unapproved_post
		SUBJECT: The subject of the topic causing the notification
		LINK: A link to the topic.
	@description:
*/
$txt['alert_unapproved_post_subject'] = 'New Unapproved Post: {SUBJECT}';
$txt['alert_unapproved_post_body'] = 'A new post has been made which needs to be approved: \'{SUBJECT}\'

You can approve or reject this post by using the link below:
{LINK}

{REGARDS}';

/**
	@additional_params: alert_unapproved_topic
		SUBJECT: The subject of the topic causing the notification
		LINK: A link to the topic.
	@description:
*/
$txt['alert_unapproved_topic_subject'] = 'New Unapproved Topic: {SUBJECT}';
$txt['alert_unapproved_topic_body'] = 'A new topic has been made which needs to be approved: \'{SUBJECT}\'

You can approve or reject this topic by using the link below:
{LINK}

{REGARDS}';

/**
	@additional_params: request_membership
		RECPNAME: The name of the person receiving the email
		APPYNAME: The name of the person applying for group membership
		GROUPNAME: The name of the group being applied to.
		REASON: The reason given by the applicant for wanting to join the group.
		MODLINK: Link to the group moderation page.
	@description:
*/
$txt['request_membership_subject'] = 'New Group Application';
$txt['request_membership_body'] = '{RECPNAME},

{APPYNAME} has requested membership to the "{GROUPNAME}" group. The user has given the following reason:

{REASON}

You can approve or reject this application by using the link below:

{MODLINK}

{REGARDS}';

/**
	@additional_params: paid_subscription
		REALNAME: The real (display) name of the person receiving the email.
		PROFILE_LINK: Link to profile of member receiving email where can renew.
		SUBSCRIPTION: Name of the subscription.
		END_DATE: Date it expires.
	@description:
*/
$txt['paid_subscription_reminder_subject'] = 'Subscription about to expire at {FORUMNAME}';
$txt['paid_subscription_reminder_body'] = '{REALNAME},

A subscription you are subscribed to at {FORUMNAME} is about to expire. If when you took out the subscription you selected to auto-renew you need take no action - otherwise you may wish to consider subscribing once more. Details are below:

Subscription Name: {SUBSCRIPTION}
Expires: {END_DATE}

To edit your subscriptions visit the following URL:
{PROFILE_LINK}

{REGARDS}';

/**
	@additional_params: activate_reactivate
		ACTIVATIONLINK:  The url link to reactivate the member's account.
		ACTIVATIONCODE:  The code needed to reactivate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
	@description:
*/
$txt['activate_reactivate_subject'] = 'Welcome back to {FORUMNAME}';
$txt['activate_reactivate_body'] = 'In order to re-validate your email address, your account has been deactivated. Click the following link to activate it again:
{ACTIVATIONLINK}

Should you have any problems with activation, please visit {ACTIVATIONLINKWITHOUTCODE} and use the code "{ACTIVATIONCODE}".

{REGARDS}';

/**
	@additional_params: forgot_password
		REALNAME: The real (display) name of the person receiving the reminder.
		REMINDLINK: The link to reset the password.
		IP: The IP address of the requester.
		MEMBERNAME:
	@description:
*/
$txt['forgot_password_subject'] = 'New password for {FORUMNAME}';
$txt['forgot_password_body'] = 'Dear {REALNAME},
This mail was sent because the \'forgot password\' function has been applied to your account. To set a new password, click the following link:
{REMINDLINK}

IP: {IP}
Username: {MEMBERNAME}

{REGARDS}';

/**
	@additional_params: send_email
		EMAILSUBJECT: The subject the user wants to email.
		EMAILBODY: The body the user wants to email.
		SENDERNAME: The name of the member sending the email.
		RECPNAME: The name of the person receiving the email.
	@description:
*/
$txt['send_email_subject'] = '{EMAILSUBJECT}';
$txt['send_email_body'] = '{EMAILBODY}';

/**
	@additional_params: report_to_moderator
		TOPICSUBJECT: The subject of the reported post.
		POSTERNAME: The reported post's author's name.
		REPORTERNAME: The name of the person reporting the post.
		TOPICLINK: The url of the post that is being reported.
		REPORTLINK: The url of the moderation center report.
		COMMENT: The comment left by the reporter, hopefully to explain why they are reporting the post.
	@description: When a user reports a post this email is sent out to moderators and admins of that board.
*/
$txt['report_to_moderator_subject'] = 'Reported post: {TOPICSUBJECT} by {POSTERNAME}';
$txt['report_to_moderator_body'] = 'The following post, "{TOPICSUBJECT}" by {POSTERNAME} has been reported by {REPORTERNAME} on a board you moderate:

The topic: {TOPICLINK}
Moderation center: {REPORTLINK}

The reporter has made the following comment:
{COMMENT}

{REGARDS}';

/**
	@additional_params: report_to_moderator
		TOPICSUBJECT: The subject of the reported post.
		POSTERNAME: The reported post's author's name.
		COMMENTERNAME: The name of the person who replied to the report.
		TOPICLINK: The url of the post that is being reported.
		REPORTLINK: The url of the moderation center report.
	@description: When a moderator replies to a moderation report, this can be sent to the other moderators who previously replied.
*/
$txt['reply_to_moderator_subject'] = 'Follow-up to reported post: {TOPICSUBJECT} by {POSTERNAME}';
$txt['reply_to_moderator_body'] = 'Previously, "{TOPICSUBJECT}" was reported to moderators.

Since then, {COMMENTERNAME} has added a comment to the report. More information can be found in the forum.

The topic: {TOPICLINK}
Moderation center: {REPORTLINK}

{REGARDS}';

/**
	@additional_params: report_user_profile
		MEMBERNAME: The display name of the reported user
		REPORTERNAME: The name of the person reporting the profile
		PROFILELINK: The link to the profile that was reported
		COMMENT: The comment left by the reporter.
 	@description: When a user's profile is reported
*/
$txt['report_member_profile_subject'] = 'Reported profile: {MEMBERNAME}';
$txt['report_member_profile_body'] = 'The profile of "{MEMBERNAME}" has been reported by {REPORTERNAME}.

The profile: {PROFILELINK}
Moderation center: {REPORTLINK}

The reporter has made the following comment:
{COMMENT}

{REGARDS}';

/**
	@additional_params: report_user_profile
		MEMBERNAME: The display name of the reported user
		COMMENTERNAME: The name of the person who added the comment
		PROFILELINK: The link to the profile that was reported
 	@description: When someone replies to a report about a profile, this can be sent to others who replied
*/
$txt['reply_to_member_report_subject'] = 'Follow-up to reported profile: {MEMBERNAME}';
$txt['reply_to_member_report_body'] = 'Previously, the profile of {MEMBERNAME} was reported.

Since then, {COMMENTERNAME} has added a comment to the report. More information can be found in the forum.

The profile: {PROFILELINK}
Moderation center: {REPORTLINK}

{REGARDS}';

/**
	@additional_params: change_password
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
	@description:
*/
$txt['change_password_subject'] = 'New Password Details';
$txt['change_password_body'] = 'Dear {USERNAME},

Your login details at {FORUMNAME} have been changed and your password reset. Below are your new login details.

Your username is "{USERNAME}" and your password is "{PASSWORD}".

You may change it after you login by going to the profile page, or by visiting this page after you login:
{SCRIPTURL}?action=profile

{REGARDS}';

/**
	@additional_params: register_activate
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
		ACTIVATIONLINK:  The url link to reactivate the member's account.
		ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
		ACTIVATIONCODE:  The code needed to reactivate the member's account.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['register_activate_subject'] = 'Welcome to {FORUMNAME}';
$txt['register_activate_body'] = 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can reset it by visiting {FORGOTPASSWORDLINK}.

Before you can login, you first need to activate your account. To do so, please follow this link:

{ACTIVATIONLINK}

Should you have any problems with activation, please visit {ACTIVATIONLINKWITHOUTCODE} use the code "{ACTIVATIONCODE}".

{REGARDS}';

/**
	@additional_params: register_coppa
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
		COPPALINK:  The url link to the coppa form.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['register_coppa_subject'] = 'Welcome to {FORUMNAME}';
$txt['register_coppa_body'] = 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}

Before you can login, the admin requires consent from your parent/guardian for you to join the community. You can obtain more information at the link below:

{COPPALINK}

{REGARDS}';

/**
	@additional_params: register_immediate
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['register_immediate_subject'] = 'Welcome to {FORUMNAME}';
$txt['register_immediate_body'] = 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you may change it at {FORGOTPASSWORDLINK}.

{REGARDS}';

/**
	@additional_params: register_pending
		REALNAME: The display name for the member receiving the email.
		USERNAME: The user name for the member receiving the email.
		PASSWORD: The password for the member.
		FORGOTPASSWORDLINK: The url to the "forgot password" page.
	@description:
*/
$txt['register_pending_subject'] = 'Welcome to {FORUMNAME}';
$txt['register_pending_body'] = 'Hello {REALNAME}, your registration request at {FORUMNAME} has been received.

The username you registered with was {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}.

Before you can login and start using the forum, your request will be reviewed and approved.

{REGARDS}';

/**
	@additional_params: notification_reply
		TOPICSUBJECT:
		POSTERNAME:
		TOPICLINK:
		UNSUBSCRIBELINK:
	@description:
*/
$txt['notification_reply_subject'] = 'Topic reply: {TOPICSUBJECT}';
$txt['notification_reply_body'] = 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notification_reply_body
		TOPICSUBJECT:
		POSTERNAME:
		TOPICLINK:
		UNSUBSCRIBELINK:
		MESSAGE:
	@description:
*/
$txt['notification_reply_body_subject'] = 'Topic reply: {TOPICSUBJECT}';
$txt['notification_reply_body_body'] = 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

The text of the reply is shown below:
{MESSAGE}

{REGARDS}';

/**
	@additional_params: notification_reply_once
		TOPICSUBJECT:
		POSTERNAME:
		TOPICLINK:
		UNSUBSCRIBELINK:
	@description:
*/
$txt['notification_reply_once_subject'] = 'Topic reply: {TOPICSUBJECT}';
$txt['notification_reply_once_body'] = 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

More replies may be posted, but you won\'t receive any more notifications until you read the topic.

{REGARDS}';

/**
	@additional_params: notification_reply_body_once
		TOPICSUBJECT:
		POSTERNAME:
		TOPICLINK:
		UNSUBSCRIBELINK:
		MESSAGE:
	@description:
*/
$txt['notification_reply_body_once_subject'] = 'Topic reply: {TOPICSUBJECT}';
$txt['notification_reply_body_once_body'] = 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

The text of the reply is shown below:
{MESSAGE}

More replies may be posted, but you won\'t receive any more notifications until you read the topic.

{REGARDS}';

/**
	@additional_params: notification_sticky
	@description:
*/
$txt['notification_sticky_subject'] = 'Topic stickied: {TOPICSUBJECT}';
$txt['notification_sticky_body'] = 'A topic you are watching has been marked as a sticky topic.

View the topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notification_lock
	@description:
*/
$txt['notification_lock_subject'] = 'Topic locked: {TOPICSUBJECT}';
$txt['notification_lock_body'] = 'A topic you are watching has been locked.

View the topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notification_unlock
	@description:
*/
$txt['notification_unlock_subject'] = 'Topic unlocked: {TOPICSUBJECT}';
$txt['notification_unlock_body'] = 'A topic you are watching has been unlocked.

View the topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notification_remove
	@description:
*/
$txt['notification_remove_subject'] = 'Topic removed: {TOPICSUBJECT}';
$txt['notification_remove_body'] = 'A topic you are watching has been removed.

{REGARDS}';

/**
	@additional_params: notification_move
	@description:
*/
$txt['notification_move_subject'] = 'Topic moved: {TOPICSUBJECT}';
$txt['notification_move_body'] = 'A topic you are watching has been moved to another board.

View the topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notification_merged
	@description:
*/
$txt['notification_merge_subject'] = 'Topic merged: {TOPICSUBJECT}';
$txt['notification_merge_body'] = 'A topic you are watching has been merged with another topic.

View the new merged topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: notification_split
	@description:
*/
$txt['notification_split_subject'] = 'Topic split: {TOPICSUBJECT}';
$txt['notification_split_body'] = 'A topic you are watching has been split into two or more topics.

View what remains of this topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}';

/**
	@additional_params: admin_notify
		USERNAME:
		PROFILELINK:
	@description:
*/
$txt['admin_notify_subject'] = 'A new member has joined';
$txt['admin_notify_body'] = '{USERNAME} has just signed up as a new member of your forum. Click the link below to view their profile.
{PROFILELINK}

{REGARDS}';

/**
	@additional_params: admin_notify_approval
		USERNAME:
		PROFILELINK:
		APPROVALLINK:
	@description:
*/
$txt['admin_notify_approval_subject'] = 'A new member has joined';
$txt['admin_notify_approval_body'] = '{USERNAME} has just signed up as a new member of your forum. Click the link below to view their profile.
{PROFILELINK}

Before this member can begin posting they must first have their account approved. Click the link below to go to the approval screen.
{APPROVALLINK}

{REGARDS}';

/**
	@additional_params: admin_attachments_full
		REALNAME:
	@description:
*/
$txt['admin_attachments_full_subject'] = 'Urgent! Attachments directory almost full';
$txt['admin_attachments_full_body'] = '{REALNAME},

The attachments directory at {FORUMNAME} is almost full. Please visit the forum to resolve this problem.

Once the attachments directory reaches it\'s maximum permitted size users will not be able to continue to post attachments or upload custom avatars (If enabled).

{REGARDS}';

/**
	@additional_params: paid_subscription_refund
		NAME: Subscription title.
		REALNAME: Recipients name
		REFUNDUSER: Username who took out the subscription.
		REFUNDNAME: User's display name who took out the subscription.
		DATE: Today's date.
		PROFILELINK: Link to members profile.
	@description:
*/
$txt['paid_subscription_refund_subject'] = 'Refunded Paid Subscription';
$txt['paid_subscription_refund_body'] = '{REALNAME},

A member has received a refund on a paid subscription. Below are the details of this subscription:

	Subscription: {NAME}
	User Name: {REFUNDNAME} ({REFUNDUSER})
	Date: {DATE}

You can view this member\'s profile by clicking the link below:
{PROFILELINK}

{REGARDS}';

/**
	@additional_params: paid_subscription_new
		NAME: Subscription title.
		REALNAME: Recipients name
		SUBEMAIL: Email address of the user who took out the subscription
		SUBUSER: Username who took out the subscription.
		SUBNAME: User's display name who took out the subscription.
		DATE: Today's date.
		PROFILELINK: Link to members profile.
	@description:
*/
$txt['paid_subscription_new_subject'] = 'New Paid Subscription';
$txt['paid_subscription_new_body'] = '{REALNAME},

A member has taken out a new paid subscription. Below are the details of this subscription:

	Subscription: {NAME}
	User Name: {SUBNAME} ({SUBUSER})
	User Email: {SUBEMAIL}
	Price: {PRICE}
	Date: {DATE}

You can view this member\'s profile by clicking the link below:
{PROFILELINK}

{REGARDS}';

/**
	@additional_params: paid_subscription_error
		ERROR: Error message.
		REALNAME: Recipients name
	@description:
*/
$txt['paid_subscription_error_subject'] = 'Paid Subscription Error Occurred';
$txt['paid_subscription_error_body'] = 'Dear {REALNAME},

The following error occurred when processing a paid subscription
---------------------------------------------------------------
{ERROR}

{REGARDS}';

/**
	@additional_params: new_pm
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		READLINK:  The link to directly access the read page.
		REPLYLINK:  The link to directly access the reply page.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['new_pm_subject'] = 'New Personal Message: {SUBJECT}';
$txt['new_pm_body'] = 'You have just been sent a personal message by {SENDER} on {FORUMNAME}

IMPORTANT: Remember, this is just a notification. Please do not reply to this email.

Read this Personal Message here: {READLINK}

Reply to this Personal Message here: {REPLYLINK}

{REGARDS}';

/**
	@additional_params: new_pm_body
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		MESSAGE:  The text of the personal message.
		REPLYLINK:  The link to directly access the reply page.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['new_pm_body_subject'] = 'New Personal Message: {SUBJECT}';
$txt['new_pm_body_body'] = 'You have just been sent a personal message by {SENDER} on {FORUMNAME}

IMPORTANT: Remember, this is just a notification. Please do not reply to this email.

The message they sent you was:

{MESSAGE}

Reply to this Personal Message here: {REPLYLINK}

{REGARDS}';

/**
	@additional_params: new_pm_tolist
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		READLINK:  The link to directly access the read page.
		REPLYLINK:  The link to directly access the reply page.
		TOLIST:  The list of users that will receive the personal message.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['new_pm_tolist_subject'] = 'New Personal Message: {SUBJECT}';
$txt['new_pm_tolist_body'] = 'You and {TOLIST} have just been sent a personal message by {SENDER} on {FORUMNAME}

IMPORTANT: Remember, this is just a notification. Please do not reply to this email.

Read this Personal Message here: {READLINK}

Reply to this Personal Message (to the sender only) here: {REPLYLINK}

{REGARDS}';

/**
	@additional_params: new_pm_body_tolist
		SUBJECT: The personal message subject.
		SENDER:  The user name for the member sending the personal message.
		MESSAGE:  The text of the personal message.
		REPLYLINK:  The link to directly access the reply page.
		TOLIST:  The list of users that will receive the personal message.
	@description: A notification email sent to the receivers of a personal message
*/
$txt['new_pm_body_tolist_subject'] = 'New Personal Message: {SUBJECT}';
$txt['new_pm_body_tolist_body'] = 'You and {TOLIST} have just been sent a personal message by {SENDER} on {FORUMNAME}

IMPORTANT: Remember, this is just a notification. Please do not reply to this email.

The message they sent you was:

{MESSAGE}

Reply to this Personal Message (to the sender only) here: {REPLYLINK}

{REGARDS}';

/**
	@additional_params: msg_quote
		CONTENTSUBJECT: The post subject.
		QUOTENAME:  The user name for the member creating the quote
		MEMBERNAME:  The user name for the member being quoted
		CONTENTLINK:  The post's link
	@description: A notification email sent to the members who've been quoted in a post
 */
$txt['msg_quote_subject'] = 'You have been quoted in the post: {CONTENTSUBJECT}';
$txt['msg_quote_body'] = 'Hello {MEMBERNAME},

You have been quoted in the post titled "{CONTENTSUBJECT}" by {QUOTENAME}, you can see the post here:
{CONTENTLINK}

{REGARDS}';

/**
	@additional_params: msg_mention
		CONTENTSUBJECT: The post subject.
		MENTIONNAME:  The user name for the member creating the mention
		MEMBERNAME:  The user name for the member being mentioned
		CONTENTLINK:  The post's link
	@description: A notification email sent to the members who've been mentioned in a post
 */
$txt['msg_mention_subject'] = 'You have been mentioned in the post: {CONTENTSUBJECT}';
$txt['msg_mention_body'] = 'Hello {MEMBERNAME},

You have been mentioned in the post titled "{CONTENTSUBJECT}" by {MENTIONNAME}, you can see the post here:
{CONTENTLINK}

{REGARDS}';

/**
	@additional_params: happy_birthday
		REALNAME: The real (display) name of the person receiving the birthday message.
	@description: A message sent to members on their birthday.
*/
$txtBirthdayEmails['happy_birthday_subject'] = 'Happy birthday from {FORUMNAME}.';
$txtBirthdayEmails['happy_birthday_body'] = 'Dear {REALNAME},

We here at {FORUMNAME} would like to wish you a happy birthday. May this day and the year to follow be full of joy.

{REGARDS}';
$txtBirthdayEmails['happy_birthday_author'] = '<a href="https://www.simplemachines.org/community/?action=profile;u=2676">Thantos</a>';

$txtBirthdayEmails['karlbenson1_subject'] = 'On your Birthday...';
$txtBirthdayEmails['karlbenson1_body'] = 'We could have sent you a birthday card. We could have sent you some flowers or a cake.

But we didn\'t.

We could have even sent you one of those automatically generated messages to wish you happy birthday where we don\'t even have to replace INSERT NAME.

But we didn\'t

We wrote this birthday greeting just for you.

We would like to wish you a very special birthday.

{REGARDS}

//:: This message was automatically generated :://';
$txtBirthdayEmails['karlbenson1_author'] = '<a href="https://www.simplemachines.org/community/?action=profile;u=63186">karlbenson</a>';

$txtBirthdayEmails['nite0859_subject'] = 'Happy Birthday!';
$txtBirthdayEmails['nite0859_body'] = 'Your friends at {FORUMNAME} would like to take a moment of your time to wish you a happy birthday, {REALNAME}. If you have not done so recently, please visit our community in order for others to have the opportunity to pass along their warm regards.

Even though today is your birthday, {REALNAME}, we would like to remind you that your membership in our community has been the best gift to us thus far.

Best Wishes,
The Staff of {FORUMNAME}';
$txtBirthdayEmails['nite0859_author'] = '<a href="https://www.simplemachines.org/community/?action=profile;u=46625">nite0859</a>';

$txtBirthdayEmails['zwaldowski_subject'] = 'Birthday Wishes to {REALNAME}';
$txtBirthdayEmails['zwaldowski_body'] = 'Dear {REALNAME},

Another year in your life has passed. We at {FORUMNAME} hope it has been filled with happiness, and wish you luck in the coming one.

{REGARDS}';
$txtBirthdayEmails['zwaldowski_author'] = '<a href="https://www.simplemachines.org/community/?action=profile;u=72038">zwaldowski</a>';

$txtBirthdayEmails['geezmo_subject'] = 'Happy birthday, {REALNAME}!';
$txtBirthdayEmails['geezmo_body'] = 'Do you know who\'s having a birthday today, {REALNAME}?

We know... YOU!

Happy birthday!

You\'re now a year older but we hope you\'re a lot happier than last year.

Enjoy your day today, {REALNAME}!

- From your {FORUMNAME} family';
$txtBirthdayEmails['geezmo_author'] = '<a href="https://www.simplemachines.org/community/?action=profile;u=48671">geezmo</a>';

$txtBirthdayEmails['karlbenson2_subject'] = 'Your Birthday Greeting';
$txtBirthdayEmails['karlbenson2_body'] = 'We hope your birthday is the best ever cloudy, sunny or whatever the weather.
Have lots of birthday cake and fun, and tell us what you have done.

We hope this message brought you cheer, and make it last, until same time same place, next year.

{REGARDS}';
$txtBirthdayEmails['karlbenson2_author'] = '<a href="https://www.simplemachines.org/community/?action=profile;u=63186">karlbenson</a>';

?>