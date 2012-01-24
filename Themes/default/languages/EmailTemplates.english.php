<?php
// Version: 2.0; EmailTemplates

// Since all of these strings are being used in emails, numeric entities should be used.

// Do not translate anything that is between {}, they are used as replacement variables and MUST remain exactly how they are.
//   Additionally do not translate the @additioinal_parmas: line or the variable names in the lines that follow it.  You may
//   translate the description of the variable.  Do not translate @description:, however you may translate the rest of that line.

// Do not use block comments in this file, they will have special meaning.

global $context, $birthdayEmails;

$txt['scheduled_approval_email_topic'] = 'The following topics are awaiting approval:';
$txt['scheduled_approval_email_msg'] = 'The following posts are awaiting approval:';
$txt['scheduled_approval_email_attach'] = 'The following attachments are awaiting approval:';
$txt['scheduled_approval_email_event'] = 'The following events are awaiting approval:';

$txt['emails'] = array(
	'resend_activate_message' => array(
		/*
			@additional_params: resend_activate_message
				REALNAME: The display name for the member receiving the email.
				USERNAME:  The user name for the member receiving the email.
				ACTIVATIONLINK:  The url link to activate the member's account.
				ACTIVATIONCODE:  The code needed to activate the member's account.
				ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
				FORGOTPASSWORDLINK: The url to the "forgot password" page.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can reset it by visiting {FORGOTPASSWORDLINK}.

Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}',
	),

	'resend_pending_message' => array(
		/*
			@additional_params: resend_pending_message
				REALNAME: The display name for the member receiving the email.
				USERNAME:  The user name for the member receiving the email.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Your registration request at {FORUMNAME} has been received, {REALNAME}.

The username you registered with was {USERNAME}.

Before you can login and start using the forum, your request will be reviewed and approved.  When this happens, you will receive another email from this address.

{REGARDS}',
	),
	'mc_group_approve' => array(
		/*
			@additional_params: mc_group_approve
				USERNAME: The user name for the member receiving the email.
				GROUPNAME: The name of the membergroup that the user was accepted into.
			@description: The request to join a particular membergroup has been accepted.
		*/
		'subject' => 'Group Membership Approval',
		'body' => '{USERNAME},

We\'re pleased to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been accepted, and your account has been updated to include this new membergroup.

{REGARDS}',
	),
	'mc_group_reject' => array(
		/*
			@additional_params: mc_group_reject
				USERNAME: The user name for the member receiving the email.
				GROUPNAME: The name of the membergroup that the user was rejected from.
			@description: The request to join a particular membergroup has been rejected.
		*/
		'subject' => 'Group Membership Rejection',
		'body' => '{USERNAME},

We\'re sorry to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been rejected.

{REGARDS}',
	),
	'mc_group_reject_reason' => array(
		/*
			@additional_params: mc_group_reject_reason
				USERNAME: The user name for the member receiving the email.
				GROUPNAME: The name of the membergroup that the user was rejected from.
				REASON: Reason for the rejection.
			@description: The request to join a particular membergroup has been rejected with a reason given.
		*/
		'subject' => 'Group Membership Rejection',
		'body' => '{USERNAME},

We\'re sorry to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been rejected.

This is due to the following reason: {REASON}

{REGARDS}',
	),
	'admin_approve_accept' => array(
		/*
			@additional_params: admin_approve_accept
				NAME: The display name of the member.
				USERNAME: The user name for the member receiving the email.
				PROFILELINK: The URL of the profile page.
				FORGOTPASSWORDLINK: The URL of the "forgot password" page.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Welcome, {NAME}!

Your account has been activated manually by the admin and you can now login and post. Your username is: {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}.

{REGARDS}',
	),
	'admin_approve_activation' => array(
		/*
			@additional_params: admin_approve_activation
				USERNAME: The user name for the member receiving the email.
				ACTIVATIONLINK:  The url link to activate the member's account.
				ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
				ACTIVATIONCODE: The activation code.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Welcome, {USERNAME}!

Your account on {FORUMNAME} has been approved by the forum administrator. Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}',
	),
	'admin_approve_reject' => array(
		/*
			@additional_params: admin_approve_reject
				USERNAME: The user name for the member receiving the email.
			@description:
		*/
		'subject' => 'Registration Rejected',
		'body' => '{USERNAME},

Regrettably, your application to join {FORUMNAME} has been rejected.

{REGARDS}',
	),
	'admin_approve_delete' => array(
		/*
			@additional_params: admin_approve_delete
				USERNAME: The user name for the member receiving the email.
			@description:
		*/
		'subject' => 'Account Deleted',
		'body' => '{USERNAME},

Your account on {FORUMNAME} has been deleted.  This may be because you never activated your account, in which case you should be able to register again.

{REGARDS}',
	),
	'admin_approve_remind' => array(
		/*
			@additional_params: admin_approve_remind
				USERNAME: The user name for the member receiving the email.
				ACTIVATIONLINK:  The url link to activate the member's account.
				ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
				ACTIVATIONCODE: The activation code.
			@description:
		*/
		'subject' => 'Registration Reminder',
		'body' => '{USERNAME},
You still have not activated your account at {FORUMNAME}.

Please use the link below to activate your account:
{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}',
	),
	'admin_register_activate' => array(
		/*
			@additional_params:
				USERNAME: The user name for the member receiving the email.
				ACTIVATIONLINK:  The url link to activate the member's account.
				ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
				ACTIVATIONCODE: The activation code.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME} and your password is {PASSWORD}.

Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}',
	),
	'admin_register_immediate' => array(
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME} and your password is {PASSWORD}.

{REGARDS}',
	),
	'new_announcement' => array(
		/*
			@additional_params: new_announcement
				TOPICSUBJECT: The subject of the topic being announced.
				MESSAGE: The message body of the first post of the announced topic.
				TOPICLINK: A link to the topic being announced.
			@description:

		*/
		'subject' => 'New announcement: {TOPICSUBJECT}',
		'body' => '{MESSAGE}

To unsubscribe from these announcements, login to the forum and uncheck "Receive forum announcements and important notifications by email." in your profile.

You can view the full announcement by following this link:
{TOPICLINK}

{REGARDS}',
	),
	'notify_boards_once_body' => array(
		/*
			@additional_params: notify_boards_once_body
				TOPICSUBJECT: The subject of the topic causing the notification
				TOPICLINK: A link to the topic.
				MESSAGE: This is the body of the message.
				UNSUBSCRIBELINK: Link to unsubscribe from notifications.
			@description:
		*/
		'subject' => 'New Topic: {TOPICSUBJECT}',
		'body' => 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

More topics may be posted, but you won\'t receive more email notifications until you return to the board and read some of them.

The text of the topic is shown below:
{MESSAGE}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notify_boards_once' => array(
		/*
			@additional_params: notify_boards_once
				TOPICSUBJECT: The subject of the topic causing the notification
				TOPICLINK: A link to the topic.
				UNSUBSCRIBELINK: Link to unsubscribe from notifications.
			@description:
		*/
		'subject' => 'New Topic: {TOPICSUBJECT}',
		'body' => 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

More topics may be posted, but you won\'t receive more email notifications until you return to the board and read some of them.

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notify_boards_body' => array(
		/*
			@additional_params: notify_boards_body
				TOPICSUBJECT: The subject of the topic causing the notification
				TOPICLINK: A link to the topic.
				MESSAGE: This is the body of the message.
				UNSUBSCRIBELINK: Link to unsubscribe from notifications.
			@description:
		*/
		'subject' => 'New Topic: {TOPICSUBJECT}',
		'body' => 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

The text of the topic is shown below:
{MESSAGE}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notify_boards' => array(
		/*
			@additional_params: notify_boards
				TOPICSUBJECT: The subject of the topic causing the notification
				TOPICLINK: A link to the topic.
				UNSUBSCRIBELINK: Link to unsubscribe from notifications.
			@description:
		*/
		'subject' => 'New Topic: {TOPICSUBJECT}',
		'body' => 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
	),
	'request_membership' => array(
		/*
			@additional_params: request_membership
				RECPNAME: The name of the person recieving the email
				APPYNAME: The name of the person applying for group membership
				GROUPNAME: The name of the group being applied to.
				REASON: The reason given by the applicant for wanting to join the group.
				MODLINK: Link to the group moderation page.
			@description:
		*/
		'subject' => 'New Group Application',
		'body' => '{RECPNAME},

{APPYNAME} has requested membership to the "{GROUPNAME}" group. The user has given the following reason:

{REASON}

You can approve or reject this application by clicking the link below:

{MODLINK}

{REGARDS}',
	),
	'paid_subscription_reminder' => array(
		/*
			@additional_params: scheduled_approval
				REALNAME: The real (display) name of the person receiving the email.
				PROFILE_LINK: Link to profile of member receiving email where can renew.
				SUBSCRIPTION: Name of the subscription.
				END_DATE: Date it expires.
			@description:
		*/
		'subject' => 'Subscription about to expire at {FORUMNAME}',
		'body' => '{REALNAME},

A subscription you are subscribed to at {FORUMNAME} is about to expire. If when you took out the subscription you selected to auto-renew you need take no action - otherwise you may wish to consider subscribing once more. Details are below:

Subscription Name: {SUBSCRIPTION}
Expires: {END_DATE}

To edit your subscriptions visit the following URL:
{PROFILE_LINK}

{REGARDS}',
	),
	'activate_reactivate' => array(
		/*
			@additional_params: activate_reactivate
				ACTIVATIONLINK:  The url link to reactivate the member's account.
				ACTIVATIONCODE:  The code needed to reactivate the member's account.
				ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
			@description:
		*/
		'subject' => 'Welcome back to {FORUMNAME}',
		'body' => 'In order to re-validate your email address, your account has been deactivated.  Click the following link to activate it again:
{ACTIVATIONLINK}

Should you have any problems with activation, please visit {ACTIVATIONLINKWITHOUTCODE} and use the code "{ACTIVATIONCODE}".

{REGARDS}',
	),
	'forgot_password' => array(
		/*
			@additional_params: forgot_password
				REALNAME: The real (display) name of the person receiving the reminder.
				REMINDLINK: The link to reset the password.
				IP: The IP address of the requester.
				MEMBERNAME:
			@description:
		*/
		'subject' => 'New password for {FORUMNAME}',
		'body' => 'Dear {REALNAME},
This mail was sent because the \'forgot password\' function has been applied to your account. To set a new password, click the following link:
{REMINDLINK}

IP: {IP}
Username: {MEMBERNAME}

{REGARDS}',
	),
	'forgot_openid' => array(
		/*
			@additional_params: forgot_password
				REALNAME: The real (display) name of the person receiving the reminder.
				IP: The IP address of the requester.
				OPENID: The members OpenID identity.
			@description:
		*/
		'subject' => 'OpenID reminder for {FORUMNAME}',
		'body' => 'Dear {REALNAME},
This mail was sent because the \'forgot OpenID\' function has been applied to your account. Below is the OpenID that your account is associated with:
{OPENID}

IP: {IP}
Username: {MEMBERNAME}

{REGARDS}',
	),
	'scheduled_approval' => array(
		/*
			@additional_params: scheduled_approval
				REALNAME: The real (display) name of the person receiving the email.
				BODY: The generated body of the mail.
			@description:
		*/
		'subject' => 'Summary of posts awaiting approval at {FORUMNAME}',
		'body' => '{REALNAME},

This email contains a summary of all items awaiting approval at {FORUMNAME}.

{BODY}

Please log in to the forum to review these items.
{SCRIPTURL}

{REGARDS}',
	),
	'send_topic' => array(
		/*
			@additional_params: send_topic
				TOPICSUBJECT: The subject of the topic being sent.
				SENDERNAME: The name of the member sending the topic.
				RECPNAME: The name of the person receiving the email.
				TOPICLINK: A link to the topic being sent.
			@description:
		*/
		'subject' => 'Topic: {TOPICSUBJECT} (From: {SENDERNAME})',
		'body' => 'Dear {RECPNAME},
I want you to check out "{TOPICSUBJECT}" on {FORUMNAME}.  To view it, please click this link:

{TOPICLINK}

Thanks,

{SENDERNAME}',
	),
	'send_topic_comment' => array(
		/*
			@additional_params: send_topic_comment
				TOPICSUBJECT: The subject of the topic being sent.
				SENDERNAME: The name of the member sending the topic.
				RECPNAME: The name of the person receiving the email.
				TOPICLINK: A link to the topic being sent.
				COMMENT: A comment left by the sender.
			@description:
		*/
		'subject' => 'Topic: {TOPICSUBJECT} (From: {SENDERNAME})',
		'body' => 'Dear {RECPNAME},
I want you to check out "{TOPICSUBJECT}" on {FORUMNAME}.  To view it, please click this link:

{TOPICLINK}

A comment has also been added regarding this topic:
{COMMENT}

Thanks,

{SENDERNAME}',
	),
	'send_email' => array(
		/*
			@additional_params: send_email
				EMAILSUBJECT: The subject the user wants to email.
				EMAILBODY: The body the user wants to email.
				SENDERNAME: The name of the member sending the email.
				RECPNAME: The name of the person receiving the email.
			@description:
		*/
		'subject' => '{EMAILSUBJECT}',
		'body' => '{EMAILBODY}',
	),
	'report_to_moderator' => array(
		/*
			@additional_params: report_to_moderator
				TOPICSUBJECT: The subject of the reported post.
				POSTERNAME: The report post's author's name.
				REPORTERNAME: The name of the person reporting the post.
				TOPICLINK: The url of the post that is being reported.
				REPORTLINK: The url of the moderation center report.
				COMMENT: The comment left by the reporter, hopefully to explain why they are reporting the post.
			@description: When a user reports a post this email is sent out to moderators and admins of that board.
		*/
		'subject' => 'Reported post: {TOPICSUBJECT} by {POSTERNAME}',
		'body' => 'The following post, "{TOPICSUBJECT}" by {POSTERNAME} has been reported by {REPORTERNAME} on a board you moderate:

The topic: {TOPICLINK}
Moderation center: {REPORTLINK}

The reporter has made the following comment:
{COMMENT}

{REGARDS}',
	),
	'change_password' => array(
		/*
			@additional_params: change_password
				USERNAME: The user name for the member receiving the email.
				PASSWORD: The password for the member.
			@description:
		*/
		'subject' => 'New Password Details',
		'body' => 'Hey, {USERNAME}!

Your login details at {FORUMNAME} have been changed and your password reset. Below are your new login details.

Your username is "{USERNAME}" and your password is "{PASSWORD}".

You may change it after you login by going to the profile page, or by visiting this page after you login:
{SCRIPTURL}?action=profile

{REGARDS}',
	),
	'register_activate' => array(
		/*
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
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can reset it by visiting {FORGOTPASSWORDLINK}.

Before you can login, you first need to activate your account. To do so, please follow this link:

{ACTIVATIONLINK}

Should you have any problems with activation, please visit {ACTIVATIONLINKWITHOUTCODE} use the code "{ACTIVATIONCODE}".

{REGARDS}',
	),
	'register_openid_activate' => array(
		/*
			@additional_params: register_activate
				REALNAME: The display name for the member receiving the email.
				USERNAME: The user name for the member receiving the email.
				OPENID: The openID identity for the member.
				ACTIVATIONLINK:  The url link to reactivate the member's account.
				ACTIVATIONLINKWITHOUTCODE: The url to the page where the activation code can be entered.
				ACTIVATIONCODE:  The code needed to reactivate the member's account.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. You have chosen to authenticate using the following OpenID identity:
{OPENID}

Before you can login, you first need to activate your account. To do so, please follow this link:

{ACTIVATIONLINK}

Should you have any problems with activation, please visit {ACTIVATIONLINKWITHOUTCODE} and use the code "{ACTIVATIONCODE}".

{REGARDS}',
	),
	'register_coppa' => array(
		/*
			@additional_params: register_coppa
				REALNAME: The display name for the member receiving the email.
				USERNAME: The user name for the member receiving the email.
				PASSWORD: The password for the member.
				COPPALINK:  The url link to the coppa form.
				FORGOTPASSWORDLINK: The url to the "forgot password" page.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}

Before you can login, the admin requires consent from your parent/guardian for you to join the community. You can obtain more information at the link below:

{COPPALINK}

{REGARDS}',
	),
	'register_openid_coppa' => array(
		/*
			@additional_params: register_coppa
				REALNAME: The display name for the member receiving the email.
				USERNAME: The user name for the member receiving the email.
				OPENID: The openID identity for the member.
				COPPALINK:  The url link to the coppa form.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}.

You have chosen to authenticate using the following OpenID identity:
{OPENID}

Before you can login, the admin requires consent from your parent/guardian for you to join the community. You can obtain more information at the link below:

{COPPALINK}

{REGARDS}',
	),
	'register_immediate' => array(
		/*
			@additional_params: register_immediate
				REALNAME: The display name for the member receiving the email.
				USERNAME: The user name for the member receiving the email.
				PASSWORD: The password for the member.
				FORGOTPASSWORDLINK: The url to the "forgot password" page.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you may change it at {FORGOTPASSWORDLINK}.

{REGARDS}',
	),
	'register_openid_immediate' => array(
		/*
			@additional_params: register_immediate
				REALNAME: The display name for the member receiving the email.
				USERNAME: The user name for the member receiving the email.
				OPENID: The openID identity for the member.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}.

You have chosen to authenticate using the following OpenID identity:
{OPENID}

You may update your profile by visiting this page after you login:

{SCRIPTURL}?action=profile

{REGARDS}',
	),
	'register_pending' => array(
		/*
			@additional_params: register_pending
				REALNAME: The display name for the member receiving the email.
				USERNAME: The user name for the member receiving the email.
				PASSWORD: The password for the member.
				FORGOTPASSWORDLINK: The url to the "forgot password" page.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Your registration request at {FORUMNAME} has been received, {REALNAME}.

The username you registered with was {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}.

Before you can login and start using the forum, your request will be reviewed and approved.  When this happens, you will receive another email from this address.

{REGARDS}',
	),
	'register_openid_pending' => array(
		/*
			@additional_params: register_pending
				REALNAME: The display name for the member receiving the email.
				USERNAME: The user name for the member receiving the email.
				OPENID: The openID identity for the member.
			@description:
		*/
		'subject' => 'Welcome to {FORUMNAME}',
		'body' => 'Your registration request at {FORUMNAME} has been received, {REALNAME}.

The username you registered with was {USERNAME}.

You have chosen to authenticate using the following OpenID identity:
{OPENID}

Before you can login and start using the forum, your request will be reviewed and approved.  When this happens, you will receive another email from this address.

{REGARDS}',
	),
	'notification_reply' => array(
		/*
			@additional_params: notification_reply
				TOPICSUBJECT:
				POSTERNAME:
				TOPICLINK:
				UNSUBSCRIBELINK:
			@description:
		*/
		'subject' => 'Topic reply: {TOPICSUBJECT}',
		'body' => 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notification_reply_body' => array(
		/*
			@additional_params: notification_reply_body
				TOPICSUBJECT:
				POSTERNAME:
				TOPICLINK:
				UNSUBSCRIBELINK:
				MESSAGE:
			@description:
		*/
		'subject' => 'Topic reply: {TOPICSUBJECT}',
		'body' => 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

The text of the reply is shown below:
{MESSAGE}

{REGARDS}',
	),
	'notification_reply_once' => array(
		/*
			@additional_params: notification_reply_once
				TOPICSUBJECT:
				POSTERNAME:
				TOPICLINK:
				UNSUBSCRIBELINK:
			@description:
		*/
		'subject' => 'Topic reply: {TOPICSUBJECT}',
		'body' => 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

More replies may be posted, but you won\'t receive any more notifications until you read the topic.

{REGARDS}',
	),
	'notification_reply_body_once' => array(
		/*
			@additional_params: notification_reply_body_once
				TOPICSUBJECT:
				POSTERNAME:
				TOPICLINK:
				UNSUBSCRIBELINK:
				MESSAGE:
			@description:
		*/
		'subject' => 'Topic reply: {TOPICSUBJECT}',
		'body' => 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

The text of the reply is shown below:
{MESSAGE}

More replies may be posted, but you won\'t receive any more notifications until you read the topic.

{REGARDS}',
	),
	'notification_sticky' => array(
		/*
			@additional_params: notification_sticky
			@description:
		*/
		'subject' => 'Topic stickied: {TOPICSUBJECT}',
		'body' => 'A topic you are watching has been marked as a sticky topic by {POSTERNAME}.

View the topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notification_lock' => array(
		/*
			@additional_params: notification_lock
			@description:
		*/
		'subject' => 'Topic locked: {TOPICSUBJECT}',
		'body' => 'A topic you are watching has been locked by {POSTERNAME}.

View the topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notification_unlock' => array(
		/*
			@additional_params: notification_unlock
			@description:
		*/
		'subject' => 'Topic unlocked: {TOPICSUBJECT}',
		'body' => 'A topic you are watching has been unlocked by {POSTERNAME}.

View the topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notification_remove' => array(
		/*
			@additional_params: notification_remove
			@description:
		*/
		'subject' => 'Topic removed: {TOPICSUBJECT}',
		'body' => 'A topic you are watching has been removed by {POSTERNAME}.

{REGARDS}',
	),
	'notification_move' => array(
		/*
			@additional_params: notification_move
			@description:
		*/
		'subject' => 'Topic moved: {TOPICSUBJECT}',
		'body' => 'A topic you are watching has been moved to another board by {POSTERNAME}.

View the topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notification_merge' => array(
		/*
			@additional_params: notification_merged
			@description:
		*/
		'subject' => 'Topic merged: {TOPICSUBJECT}',
		'body' => 'A topic you are watching has been merged with another topic by {POSTERNAME}.

View the new merged topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}',
	),
	'notification_split' => array(
		/*
			@additional_params: notification_split
			@description:
		*/
		'subject' => 'Topic split: {TOPICSUBJECT}',
		'body' => 'A topic you are watching has been split into two or more topics by {POSTERNAME}.

View what remains of this topic at: {TOPICLINK}

Unsubscribe to this topic by using this link: {UNSUBSCRIBELINK}

{REGARDS}',
	),
	'admin_notify' => array(
		/*
			@additional_params: admin_notify
				USERNAME:
				PROFILELINK:
			@description:
		*/
		'subject' => 'A new member has joined',
		'body' => '{USERNAME} has just signed up as a new member of your forum. Click the link below to view their profile.
{PROFILELINK}

{REGARDS}',
	),
	'admin_notify_approval' => array(
		/*
			@additional_params: admin_notify_approval
				USERNAME:
				PROFILELINK:
				APPROVALLINK:
			@description:
		*/
		'subject' => 'A new member has joined',
		'body' => '{USERNAME} has just signed up as a new member of your forum. Click the link below to view their profile.
{PROFILELINK}

Before this member can begin posting they must first have their account approved. Click the link below to go to the approval screen.
{APPROVALLINK}

{REGARDS}',
	),
	'admin_attachments_full' => array(
		/*
			@additional_params: admin_attachments_full
				REALNAME:
			@description:
		*/
		'subject' => 'Urgent! Attachments folder almost full',
		'body' => '{REALNAME},

The attachments folder at {FORUMNAME} is almost full. Please visit the forum to resolve this problem.

Once the attachments folder reaches it\'s maximum permitted size users will not be able to continue to post attachments or upload custom avatars (If enabled).

{REGARDS}',
	),
	'paid_subscription_refund' => array(
		/*
			@additional_params: paid_subscription_refund
				NAME: Subscription title.
				REALNAME: Recipients name
				REFUNDUSER: Username who took out the subscription.
				REFUNDNAME: User's display name who took out the subscription.
				DATE: Today's date.
				PROFILELINK: Link to members profile.
			@description:
		*/
		'subject' => 'Refunded Paid Subscription',
		'body' => '{REALNAME},

A member has received a refund on a paid subscription. Below are the details of this subscription:

	Subscription: {NAME}
	User Name: {REFUNDNAME} ({REFUNDUSER})
	Date: {DATE}

You can view this members profile by clicking the link below:
{PROFILELINK}

{REGARDS}',
	),
	'paid_subscription_new' => array(
		/*
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
		'subject' => 'New Paid Subscription',
		'body' => '{REALNAME},

A member has taken out a new paid subscription. Below are the details of this subscription:

	Subscription: {NAME}
	User Name: {SUBNAME} ({SUBUSER})
	User Email: {SUBEMAIL}
	Price: {PRICE}
	Date: {DATE}

You can view this members profile by clicking the link below:
{PROFILELINK}

{REGARDS}',
	),
	'paid_subscription_error' => array(
		/*
			@additional_params: paid_subscription_error
				ERROR: Error message.
				REALNAME: Recipients name
			@description:
		*/
		'subject' => 'Paid Subscription Error Occurred',
		'body' => '{REALNAME},

The following error occurred when processing a paid subscription
---------------------------------------------------------------
{ERROR}

{REGARDS}',
	),
);

/*
	@additional_params: happy_birthday
		REALNAME: The real (display) name of the person receiving the birthday message.
	@description: A message sent to members on their birthday.
*/
$birthdayEmails = array(
	'happy_birthday' => array(
		'subject' => 'Happy birthday from {FORUMNAME}.',
		'body' => 'Dear {REALNAME},

We here at {FORUMNAME} would like to wish you a happy birthday.  May this day and the year to follow be full of joy.

{REGARDS}',
		'author' => '<a href="http://www.simplemachines.org/community/?action=profile;u=2676">Thantos</a>',
	),
	'karlbenson1' => array(
		'subject' => 'On your Birthday...',
		'body' => 'We could have sent you a birthday card.  We could have sent you some flowers or a cake.

But we didn\'t.

We could have even sent you one of those automatically generated messages to wish you happy birthday where we don\'t even have to replace INSERT NAME.

But we didn\'t

We wrote this birthday greeting just for you.

We would like to wish you a very special birthday.

{REGARDS}

//:: This message was automatically generated :://',
		'author' => '<a href="http://www.simplemachines.org/community/?action=profile;u=63186">karlbenson</a>',
	),
	'nite0859' => array(
		'subject' => 'Happy Birthday!',
		'body' => 'Your friends at {FORUMNAME} would like to take a moment of your time to wish you a happy birthday, {REALNAME}. If you have not done so recently, please visit our community in order for others to have the opportunity to pass along their warm regards.

Even though today is your birthday, {REALNAME}, we would like to remind you that your membership in our community has been the best gift to us thus far.

Best Wishes,
The Staff of {FORUMNAME}',
		'author' => '<a href="http://www.simplemachines.org/community/?action=profile;u=46625">nite0859</a>',
	),
	'zwaldowski' => array(
		'subject' => 'Birthday Wishes to {REALNAME}',
		'body' => 'Dear {REALNAME},

Another year in your life has passed.  We at {FORUMNAME} hope it has been filled with happiness, and wish you luck in the coming one.

{REGARDS}',
		'author' => '<a href="http://www.simplemachines.org/community/?action=profile;u=72038">zwaldowski</a>',
	),
	'geezmo' => array(
		'subject' => 'Happy birthday, {REALNAME}!',
		'body' => 'Do you know who\'s having a birthday today, {REALNAME}?

We know... YOU!

Happy birthday!

You\'re now a year older but we hope you\'re a lot happier than last year.

Enjoy your day today, {REALNAME}!

- From your {FORUMNAME} family',
		'author' => '<a href="http://www.simplemachines.org/community/?action=profile;u=48671">geezmo</a>',
	),
	'karlbenson2' => array(
		'subject' => 'Your Birthday Greeting',
		'body' => 'We hope your birthday is the best ever cloudy, sunny or whatever the weather.
Have lots of birthday cake and fun, and tell us what you have done.

We hope this message brought you cheer, and make it last, until same time same place, next year.

{REGARDS}',
		'author' => '<a href="http://www.simplemachines.org/community/?action=profile;u=63186">karlbenson</a>',
	),
);
?>