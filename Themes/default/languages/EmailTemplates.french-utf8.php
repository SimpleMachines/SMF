<?php
// Version: 2.1 Beta 2; EmailTemplates

global $txtBirthdayEmails;

// Since all of these strings are being used in emails, numeric entities should be used.
// Do not translate anything that is between {}, they are used as replacement variables and MUST remain exactly how they are.
//   Additionally do not translate the @additioinal_parmas: line or the variable names in the lines that follow it. You may
//   translate the description of the variable. Do not translate @description:, however you may translate the rest of that line.
// Do not use block comments in this file, they will have special meaning.
$txt['scheduled_approval_email_topic'] = 'Les sujets suivants sont en attente d\'approbation :';
$txt['scheduled_approval_email_msg'] = 'Les messages suivants sont en attente d\'approbation :';
$txt['scheduled_approval_email_attach'] = 'Les fichiers joints suivants sont en attente d\'approbation :';
$txt['scheduled_approval_email_event'] = 'Les &#233;v&#233;nements suivants sont en attente d\'approbation :';

$txt['resend_activate_message_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['resend_activate_message_body'] = 'Merci d\'avoir rejoint {FORUMNAME}. Votre identifiant est {USERNAME}. En cas d\'oubli de votre mot de passe, vous pouvez le r&#233;initialiser en allant sur {FORGOTPASSWORDLINK}.

Avant de pouvoir vous connecter, vous devez d\'abord activer votre compte en suivant ce lien :

{ACTIVATIONLINK}

En cas de probl&#232;me avec l\'activation, rendez-vous sur {ACTIVATIONLINKWITHOUTCODE} et utilisez le code "{ACTIVATIONCODE}".

{REGARDS}';

$txt['resend_pending_message_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['resend_pending_message_body'] = 'Votre demande d\'inscription sur {FORUMNAME} a &#233;t&#233; re&#231;ue, {REALNAME}.

L\'identifiant sous lequel vous vous &#234;tes inscrit est {USERNAME}.

Avant de pouvoir vous connecter et commencer &#224; utiliser le forum, votre demande sera &#233;tudi&#233;e et valid&#233;e. Lorsque cela sera fait, vous recevrez un autre e-mail exp&#233;di&#233; &#224; partir de cette adresse.

{REGARDS}';

$txt['mc_group_approve_subject'] = 'Approbation de l\'adh&#233;sion &#224; un groupe';
$txt['mc_group_approve_body'] = '{USERNAME},

Nous sommes heureux de vous annoncer que votre demande pour rejoindre le groupe "{GROUPNAME}" sur {FORUMNAME} a &#233;t&#233; accept&#233;e, et que votre compte a &#233;t&#233; mis &#224; jour pour vous inclure dans ce groupe.

{REGARDS}';

$txt['mc_group_reject_subject'] = 'Rejet d\'adh&#233;sion &#224; un groupe';
$txt['mc_group_reject_body'] = '{USERNAME},

Nous sommes d&#233;sol&#233;s de vous annoncer que votre demande pour rejoindre le groupe "{GROUPNAME}" sur {FORUMNAME} a &#233;t&#233; rejet&#233;e.

{REGARDS}';

$txt['mc_group_reject_reason_subject'] = 'Rejet d\'adh&#233;sion &#224; un groupe';
$txt['mc_group_reject_reason_body'] = '{USERNAME},

Nous sommes d&#233;sol&#233;s de vous annoncer que votre demande pour rejoindre le groupe "{GROUPNAME}" sur {FORUMNAME} a &#233;t&#233; rejet&#233;e.

La raison est la suivante :
{REASON}

{REGARDS}';

$txt['admin_approve_accept_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['admin_approve_accept_body'] = 'Bienvenue {NAME} !

Votre compte a &#233;t&#233; activ&#233; manuellement par l\'administrateur. Vous pouvez maintenant vous connecter et poster. Votre identifiant est : {USERNAME}
Si vous oubliez votre mot de passe, vous pouvez le r&#233;initialiser sur {FORGOTPASSWORDLINK}.

{REGARDS}';

$txt['admin_approve_activation_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['admin_approve_activation_body'] = 'Bienvenue {USERNAME} !

Votre compte sur {FORUMNAME} a &#233;t&#233; approuv&#233; par l\'administrateur du forum, et doit &#234;tre maintenant activ&#233; avant de pouvoir commencer &#224; poster. Veuillez utiliser le lien ci-dessous pour activer votre compte :
{ACTIVATIONLINK}

En cas de probl&#232;me avec l\'activation, rendez-vous sur {ACTIVATIONLINKWITHOUTCODE} et d\'y entrer le code "{ACTIVATIONCODE}".

{REGARDS}';

$txt['admin_approve_reject_subject'] = 'Inscription rejet&#233;e';
$txt['admin_approve_reject_body'] = '{USERNAME},

Malheureusement, votre demande pour rejoindre {FORUMNAME} a &#233;t&#233; rejet&#233;e.

{REGARDS}';

$txt['admin_approve_delete_subject'] = 'Compte supprim&#233;';
$txt['admin_approve_delete_body'] = '{USERNAME},

Votre compte sur {FORUMNAME} a été supprimé. L\'une des raisons possibles peut être que vous n\'avez jamais activé votre compte, auquel cas vous devriez pouvoir vous réinscrire.

{REGARDS}';

$txt['admin_approve_remind_subject'] = 'Rappel d\'inscription';
$txt['admin_approve_remind_body'] = '{USERNAME},

Vous n\'avez pas encore activ&#233; votre compte sur {FORUMNAME}.

Veuillez utiliser le lien ci-dessous pour activer votre compte :
{ACTIVATIONLINK}

En cas de probl&#232;me avec l\'activation, rendez-vous sur {ACTIVATIONLINKWITHOUTCODE} et d\'y entrer le code "{ACTIVATIONCODE}".

{REGARDS}';

$txt['admin_register_activate_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['admin_register_activate_body'] = 'Merci d\'avoir rejoint {FORUMNAME}. Votre identifiant est {USERNAME}. En cas d\'oubli de votre mot de passe, vous pouvez le r&#233;initialiser en allant sur {FORGOTPASSWORDLINK}.

Avant de pouvoir vous connecter, vous devez d\'abord activer votre compte en suivant ce lien :

{ACTIVATIONLINK}

En cas de probl&#232;me avec l\'activation, rendez-vous sur {ACTIVATIONLINKWITHOUTCODE} et utilisez le code "{ACTIVATIONCODE}".

{REGARDS}';

$txt['admin_register_immediate_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['admin_register_immediate_body'] = 'Merci d\'avoir rejoint {FORUMNAME}. Votre identifiant est {USERNAME} et votre mot de passe est {PASSWORD}.

{REGARDS}';

$txt['new_announcement_subject'] = 'Nouvelle annonce : {TOPICSUBJECT}';
$txt['new_announcement_body'] = '{MESSAGE}

Pour vous d&#233;sabonner de ces annonces, connectez-vous au forum et d&#233;cochez la case "Recevoir les annonces du forum et les notifications importantes par e-mail." de votre profil.

Vous pouvez voir l\'annonce compl&#232;te en suivant ce lien :
{TOPICLINK}

{REGARDS}';

$txt['notify_boards_once_body_subject'] = 'Nouveau sujet : {TOPICSUBJECT}';
$txt['notify_boards_once_body_body'] = 'Un nouveau sujet, \'{TOPICSUBJECT}\', a &#233;t&#233; cr&#233;&#233; dans une section que vous surveillez.

Vous pouvez le voir via {TOPICLINK}

D\'autres sujets ont pu &#234;tre post&#233;s, mais vous ne recevrez pas d\'autres e-mails de notification tant que vous ne serez pas retourn&#233; sur cette section et que vous n\'en aurez pas lus quelques-uns.

Le texte du sujet est affich&#233; ci-dessous :
{MESSAGE}

Pour vous d&#233;sabonner des nouveaux sujets de cette section, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notify_boards_once_subject'] = 'Nouveau sujet : {TOPICSUBJECT}';
$txt['notify_boards_once_body'] = 'Un nouveau sujet, \'{TOPICSUBJECT}\', a &#233;t&#233; cr&#233;&#233; dans une section que vous surveillez.

Vous pouvez le voir via {TOPICLINK}

D\'autres sujets ont pu &#234;tre post&#233;s, mais vous ne recevrez pas d\'autres e-mails de notification tant que vous ne serez pas retourn&#233; sur cette section et que vous n\'en aurez pas lu quelques uns.

Pour vous d&#233;sabonner des nouveaux sujets de cette section, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notify_boards_body_subject'] = 'Nouveau sujet : {TOPICSUBJECT}';
$txt['notify_boards_body_body'] = 'Un nouveau sujet \'{TOPICSUBJECT}\', a &#233;t&#233; cr&#233;&#233; dans une section que vous surveillez.

Vous pouvez le voir via {TOPICLINK}

Le texte du sujet est affich&#233; ci-dessous :
{MESSAGE}

Pour vous d&#233;sabonner des nouveaux sujets de cette section, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notify_boards_subject'] = 'Nouveau sujet : {TOPICSUBJECT}';
$txt['notify_boards_body'] = 'Un nouveau sujet, \'{TOPICSUBJECT}\', a &#233;t&#233; cr&#233;&#233; dans une section que vous surveillez.

Vous pouvez le voir via {TOPICLINK}

Pour vous d&#233;sabonner des nouveaux sujets de cette section, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['alert_unapproved_reply_subject'] = 'Topic reply: {SUBJECT}';
$txt['alert_unapproved_reply_body'] = 'A reply has been posted to \'{SUBJECT}\' by {POSTERNAME}.

You can see it at
{LINK}

{REGARDS}';

$txt['alert_unapproved_post_subject'] = 'New Unapproved Post: {SUBJECT}';
$txt['alert_unapproved_post_body'] = 'A new post, \'{SUBJECT}\', has been made which needs approved.

You can approve or reject this post by clicking the link below:

{LINK}

{REGARDS}';

$txt['alert_unapproved_topic_subject'] = 'New Unapproved Topic: {SUBJECT}';
$txt['alert_unapproved_topic_body'] = 'A new topic, \'{SUBJECT}\', has been made which needs approved.

You can approve or reject this topic by clicking the link below:

{LINK}

{REGARDS}';

$txt['request_membership_subject'] = 'Demande de nouveau groupe';
$txt['request_membership_body'] = '{RECPNAME},

{APPYNAME} a demand&#233; l\'adh&#233;sion au groupe "{GROUPNAME}". L\'utilisateur a donn&#233; la raison suivante :

{REASON}

Vous pouvez approuver ou rejeter cette demande en cliquant sur le lien ci-dessous :

{MODLINK}

{REGARDS}';

$txt['paid_subscription_reminder_subject'] = 'Souscription sur le point d\'expirer {FORUMNAME}';
$txt['paid_subscription_reminder_body'] = '{REALNAME},

L\'une de vos souscriptions sur {FORUMNAME} est sur le point d\'expirer. Si vous aviez choisi un renouvellement automatique lors de votre souscription initiale, vous n\'avez pas besoin de faire quoique ce soit. Dans le cas contraire, vous souhaitez peut-&#234;tre renouveller votre souscription une fois de plus. Voici les d&#233;tails :

Nom de la souscription : {SUBSCRIPTION}
Date d\'expiration : {END_DATE}

Pour &#233;diter vos souscriptions, veuillez visiter l\'URL suivante :
{PROFILE_LINK}

{REGARDS}';

$txt['activate_reactivate_subject'] = 'Rebienvenue sur {FORUMNAME}';
$txt['activate_reactivate_body'] = 'Pour pouvoir valider à nouveau votre adresse e-mail, votre compte a été désactivé. Cliquez sur le lien suivant pour le réactiver:
{ACTIVATIONLINK}

En cas de problème avec l\'activation, rendez-vous sur {ACTIVATIONLINKWITHOUTCODE} et utilisez le code "{ACTIVATIONCODE}".

{REGARDS}';

$txt['forgot_password_subject'] = 'Nouveau mot de passe pour {FORUMNAME}';
$txt['forgot_password_body'] = 'Cher {REALNAME},
Cet e-mail a &#233;t&#233; envoy&#233; car la fonction \'mot de passe oubli&#233;\' a &#233;t&#233; utilis&#233;e sur votre compte. Pour &#233;tablir un nouveau mot de passe, cliquez sur le lien suivant :
{REMINDLINK}

IP : {IP}
Identifiant : {MEMBERNAME}

{REGARDS}';

$txt['scheduled_approval_subject'] = 'R&#233;sum&#233; des messages en attente d\'approbation sur {FORUMNAME}';
$txt['scheduled_approval_body'] = '{REALNAME},

Cet e-mail contient un r&#233;sum&#233; de tous les choses en attente d\'approbation sur {FORUMNAME}.

{BODY}

Veuillez vous connecter au forum pour les passer en revue.
{SCRIPTURL}

{REGARDS}';

$txt['send_email_subject'] = '{EMAILSUBJECT}';
$txt['send_email_body'] = '{EMAILBODY}';

$txt['report_to_moderator_subject'] = 'Message rapport&#233; : {TOPICSUBJECT} par {POSTERNAME}';
$txt['report_to_moderator_body'] = 'Le message suivant, "{TOPICSUBJECT}" par {POSTERNAME} a &#233;t&#233; rapport&#233; par {REPORTERNAME} dans une section que vous mod&#233;rez :

Le sujet : {TOPICLINK}
Centre de mod&#233;ration : {REPORTLINK}

L\'auteur du rapport a fait le commentaire suivant :
{COMMENT}

{REGARDS}';

$txt['reply_to_moderator_subject'] = 'Follow-up to reported post: {TOPICSUBJECT} by {POSTERNAME}';
$txt['reply_to_moderator_body'] = 'Previously, "{TOPICSUBJECT}" was reported to moderators.

Since then, {COMMENTERNAME} has added a comment to the report. More information can be found in the forum.

The topic: {TOPICLINK}
Moderation center: {REPORTLINK}

{REGARDS}';

$txt['report_member_profile_subject'] = 'Profil signalé: {MEMBERNAME} ';
$txt['report_member_profile_body'] = 'The profile of "{MEMBERNAME}" has been reported by {REPORTERNAME}.

The profile: {PROFILELINK}
Moderation center: {REPORTLINK}

The reporter has made the following comment:
{COMMENT}

{REGARDS}';

$txt['reply_to_member_report_subject'] = 'Follow-up to reported profile: {MEMBERNAME}';
$txt['reply_to_member_report_body'] = 'Previously, the profile of {MEMBERNAME} was reported.

Since then, {COMMENTERNAME} has added a comment to the report. More information can be found in the forum.

The profile: {PROFILELINK}
Moderation center: {REPORTLINK}

{REGARDS}';

$txt['change_password_subject'] = 'Informations sur le nouveau mot de passe';
$txt['change_password_body'] = 'Bonjour {USERNAME} !

Vos informations de connexion sur {FORUMNAME} ont &#233;t&#233; modifi&#233;es et votre mot de passe r&#233;initialis&#233;. Voici ci-dessous les informations de votre nouvelle connexion.

Votre identifiant est "{USERNAME}" et votre mot de passe est "{PASSWORD}".

Une fois connect&#233;, vous pouvez changer votre mot de passe en allant sur la page de votre profil :

{SCRIPTURL}?action=profile

{REGARDS}';

$txt['register_activate_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['register_activate_body'] = 'Merci d\'avoir rejoint {FORUMNAME}. Votre identifiant est {USERNAME}. En cas d\'oubli de votre mot de passe, vous pouvez le r&#233;initialiser en allant sur {FORGOTPASSWORDLINK}.

Avant de pouvoir vous connecter, vous devez d\'abord activer votre compte. Pour ce faire, veuillez suivre ce lien :

{ACTIVATIONLINK}

En cas de probl&#232;me avec l\'activation, rendez-vous sur {ACTIVATIONLINKWITHOUTCODE} et utilisez le code "{ACTIVATIONCODE}".

{REGARDS}';

$txt['register_coppa_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['register_coppa_body'] = 'Merci d\'avoir rejoint {FORUMNAME}. Votre identifiant est {USERNAME}. En cas d\'oubli de votre mot de passe, vous pouvez le r&#233;initialiser en allant sur {FORGOTPASSWORDLINK}.

Avant de pouvoir vous connecter, l\'administrateur de ce forum souhaite obtenir l\'approbation de votre parent/tuteur pour votre adh&#233;sion &#224; cette communaut&#233;. Pour plus d\'informations, veuillez consulter le lien ci-dessous :
{COPPALINK}

{REGARDS}';

$txt['register_immediate_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['register_immediate_body'] = 'Merci d\'avoir rejoint {FORUMNAME}. Votre identifiant est {USERNAME}. En cas d\'oubli de votre mot de passe, vous pouvez le r&#233;initialiser en allant sur {FORGOTPASSWORDLINK}.

{REGARDS}';

$txt['register_pending_subject'] = 'Bienvenue sur {FORUMNAME}';
$txt['register_pending_body'] = 'Votre demande d\'inscription sur {FORUMNAME} a &#233;t&#233; re&#231;ue, {REALNAME}.

L\'identifiant de votre compte est {USERNAME} et son mot de passe est {PASSWORD}. En cas d\'oubli de votre mot de passe, vous pouvez le r&#233;initialiser en allant sur {FORGOTPASSWORDLINK}.

Avant de pouvoir vous connecter, votre demande sera d\'abord &#233;tudi&#233;e et valid&#233;e. Vous recevrez ensuite un e-mail de confirmation.

{REGARDS}';

$txt['notification_reply_subject'] = 'R&#233;ponse &#224; un sujet : {TOPICSUBJECT}';
$txt['notification_reply_body'] = 'Une r&#233;ponse a &#233;t&#233; post&#233;e sur un sujet que vous surveillez par {POSTERNAME}.

Voir la r&#233;ponse : {TOPICLINK}

Pour vous d&#233;sabonner de ce sujet, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notification_reply_body_subject'] = 'R&#233;ponse &#224; un sujet : {TOPICSUBJECT}';
$txt['notification_reply_body_body'] = 'Une r&#233;ponse a &#233;t&#233; post&#233;e par {POSTERNAME} sur un sujet que vous surveillez.

Voir la r&#233;ponse : {TOPICLINK}

Le texte de la r&#233;ponse est affich&#233; ci-dessous :
{MESSAGE}

Pour vous d&#233;sabonner de ce sujet, utilisez ce lien:
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notification_reply_once_subject'] = 'R&#233;ponse &#224; un sujet : {TOPICSUBJECT}';
$txt['notification_reply_once_body'] = 'Une r&#233;ponse a &#233;t&#233; post&#233;e par {POSTERNAME} sur un sujet que vous surveillez.

Voir la r&#233;ponse : {TOPICLINK}

D\'autres r&#233;ponses ont pu &#234;tre post&#233;es, mais vous ne recevrez pas d\'autres e-mails de notification tant que vous n\'aurez pas lu le sujet.

Pour vous d&#233;sabonner de ce sujet, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notification_reply_body_once_subject'] = 'R&#233;ponse &#224; un sujet : {TOPICSUBJECT}';
$txt['notification_reply_body_once_body'] = 'Une r&#233;ponse a &#233;t&#233; post&#233;e par {POSTERNAME} sur un sujet que vous surveillez.

Voir la r&#233;ponse : {TOPICLINK}

Le texte de la r&#233;ponse est affich&#233; ci-dessous :
{MESSAGE}

D\'autres r&#233;ponses ont pu &#234;tre post&#233;es, mais vous ne recevrez pas d\'autres e-mails de notification tant que vous n\'aurez pas lu le sujet.

Pour vous d&#233;sabonner de ce sujet, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notification_sticky_subject'] = 'Sujet &#233;pingl&#233; : {TOPICSUBJECT}';
$txt['notification_sticky_body'] = 'Un sujet que vous surveillez a été épinglé. Voir le sujet : {TOPICLINK} Pour vous désabonner de ce sujet, utilisez ce lien : {UNSUBSCRIBELINK} {REGARDS}';

$txt['notification_lock_subject'] = 'Sujet bloqu&#233; : {TOPICSUBJECT}';
$txt['notification_lock_body'] = 'Un sujet que vous surveillez a &#233;t&#233; bloqu&#233; par {POSTERNAME}.

Voir le sujet : {TOPICLINK}

Pour vous d&#233;sabonner de ce sujet, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notification_unlock_subject'] = 'Sujet d&#233;bloqu&#233; : {TOPICSUBJECT}';
$txt['notification_unlock_body'] = 'Un sujet que vous surveillez a &#233;t&#233; d&#233;bloqu&#233; par {POSTERNAME}.

Voir le sujet : {TOPICLINK}

Pour vous d&#233;sabonner de ce sujet, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notification_remove_subject'] = 'Sujet supprim&#233; : {TOPICSUBJECT}';
$txt['notification_remove_body'] = 'Un sujet que vous surveillez a été supprimé. {REGARDS}';

$txt['notification_move_subject'] = 'Sujet d&#233;plac&#233; : {TOPICSUBJECT}';
$txt['notification_move_body'] = 'Un sujet que vous surveillez a &#233;t&#233; d&#233;plac&#233; dans une autre section par {POSTERNAME}.

Voir le sujet : {TOPICLINK}

Pour vous d&#233;sabonner de ce sujet, utilisez ce lien :
{UNSUBSCRIBELINK}

{REGARDS}';

$txt['notification_merge_subject'] = 'Sujet fusionn&#233; : {TOPICSUBJECT}';
$txt['notification_merge_body'] = 'Un sujet que vous surveillez a été fusionné avec un autre sujet. Voir le nouveau sujet fusionné: {TOPICLINK} Pour vous désabonner de ce sujet, utilisez ce lien: {UNSUBSCRIBELINK} {REGARDS}';

$txt['notification_split_subject'] = 'Sujet s&#233;par&#233; : {TOPICSUBJECT}';
$txt['notification_split_body'] = 'Un sujet que vous surveillez a été divisé en deux ou plus de sujets par {POSTERNAME}. Pour voir ce qu\'il reste de ce sujet : {TOPICLINK} Pour vous désabonner de ce sujet, utilisez ce lien : {UNSUBSCRIBELINK} {REGARDS}';

$txt['admin_notify_subject'] = 'Un nouveau membre s\'est inscrit';
$txt['admin_notify_body'] = '{USERNAME} vient juste de s\'inscrire comme nouveau membre sur votre forum. Cliquez sur le lien ci-dessous pour voir son profil.
{PROFILELINK}

{REGARDS}';

$txt['admin_notify_approval_subject'] = 'Un nouveau membre s\'est inscrit';
$txt['admin_notify_approval_body'] = '{USERNAME} vient juste de s\'inscrire comme nouveau membre sur votre forum. Cliquez sur le lien ci-dessous pour voir son profil.
{PROFILELINK}

Avant que ce membre puisse commencer &#224; poster, son compte doit d\'abord &#234;tre approuv&#233;. Cliquez sur le lien ci-dessous pour aller sur la page d\'approbation.
{APPROVALLINK}

{REGARDS}';

$txt['admin_attachments_full_subject'] = 'Urgent ! Le dossier des fichiers joints est presque plein';
$txt['admin_attachments_full_body'] = '{REALNAME},

Le dossier des fichiers joints sur {FORUMNAME} est presque plein. Veuillez visiter le forum pour apporter une solution.

Notez que, si le dossier des fichiers joints est plein, les utilisateurs de ce forum ne pourront ni ajouter de nouvelles pi&#232;ces jointes, ni envoyer de nouveaux avatars (si cela &#233;tait permis).

{REGARDS}';

$txt['paid_subscription_refund_subject'] = 'Abonnement rembours&#233;';
$txt['paid_subscription_refund_body'] = '{REALNAME},

Un membre a &#233;t&#233; rembours&#233; pour un abonnement. Voici les d&#233;tails de cet abonnement :

	Nom de l\'abonnement : {NAME}
	Nom de l\'utilisateur : {REFUNDNAME} ({REFUNDUSER})
	Date : {DATE}

Vous pouvez afficher le profil de cet utilisateur via le lien ci-dessous :
{PROFILELINK}

{REGARDS}';

$txt['paid_subscription_new_subject'] = 'Nouvelle souscription';
$txt['paid_subscription_new_body'] = '{REALNAME},

Un membre s\'est abonn&#233; &#224; votre forum. Voici les d&#233;tails de cet abonnement :

	Nom de l\'abonnement : {NAME}
	Nom de l\'utilisateur : {SUBNAME} ({SUBUSER})
	Son adresse e-mail : {SUBEMAIL}
	Prix : {PRICE}
	Date : {DATE}

Vous pouvez afficher le profil de cet utilisateur via le lien ci-dessous :
{PROFILELINK}

{REGARDS}';

$txt['paid_subscription_error_subject'] = 'Une erreur est survenue lors de la souscription';
$txt['paid_subscription_error_body'] = '{REALNAME},

L\'erreur suivante est survenue lors de cette souscription
---------------------------------------------------------
{ERROR}

{REGARDS}';

$txt['new_pm_subject'] = 'Nouveau message personnel : {SUBJECT}';
$txt['new_pm_body'] = 'Vous venez tout juste de recevoir un message personnel de la part de {SENDER} sur {FORUMNAME}.

IMPORTANT : Rappelez-vous que ceci n\'est qu\'une notification. Ne r&#233;pondez pas &#224; cet e-mail.

Vous pouvez lire le message personnel ici : {READLINK}

Vous pouvez r&#233;pondre &#224; ce message ici : {REPLYLINK}';

$txt['new_pm_body_subject'] = 'Nouveau message personnel : {SUBJECT}';
$txt['new_pm_body_body'] = 'Vous venez tout juste de recevoir un message personnel de la part de {SENDER} sur  {FORUMNAME}.

IMPORTANT : Rappelez-vous que ceci n\'est qu\'une notification. Ne r&#233;pondez pas &#224; cet e-mail.

Le message qui vous a &#233;t&#233; envoy&#233; est le suivant : {MESSAGE}

Vous pouvez r&#233;pondre &#224; ce message ici : {REPLYLINK}';

$txt['new_pm_tolist_subject'] = 'Nouveau message personnel : {SUBJECT}';
$txt['new_pm_tolist_body'] = 'Vous et {TOLIST} venez tout juste d\'envoyer un message personnel par {SENDER} sur {FORUMNAME}.

IMPORTANT : Rappelez-vous que ceci n\'est qu\'une notification. Ne r&#233;pondez pas &#224; cet e-mail.

Le message qui vous a &#233;t&#233; envoy&#233; est le suivant : {MESSAGE}

Vous pouvez lire le message personnel ici : {READLINK}

Vous pouvez r&#233;pondre &#224; ce message (&#224; l\'envoyeur seulement) ici : {REPLYLINK}';

$txt['new_pm_body_tolist_subject'] = 'Nouveau message personnel : {SUBJECT}';
$txt['new_pm_body_tolist_body'] = 'Vous et {TOLIST} venez tout juste d\'envoyer un message personnel par {SENDER} sur {FORUMNAME}.

IMPORTANT : Rappelez-vous que ceci n\'est qu\'une notification.

Ne r&#233;pondez pas &#224; cet e-mail.

Le message qui vous a &#233;t&#233; envoy&#233; est le suivant : {MESSAGE}

Vous pouvez r&#233;pondre &#224; ce message (&#224; l\'envoyeur seulement) ici : {REPLYLINK}';

$txt['msg_quote_subject'] = 'You have been quoted in the post: {CONTENTSUBJECT}';
$txt['msg_quote_body'] = 'Hello {MEMBERNAME},

You have been quoted in the post titled "{CONTENTSUBJECT}" by {QUOTENAME}, you can see the post here:
{CONTENTLINK}

{REGARDS}';

$txt['msg_mention_subject'] = 'You have been mentioned in the post: {CONTENTSUBJECT}';
$txt['msg_mention_body'] = 'Hello {MEMBERNAME},

You have been mentioned in the post titled "{CONTENTSUBJECT}" by {MENTIONNAME}, you can see the post here:
{CONTENTLINK}

{REGARDS}';

$txtBirthdayEmails['happy_birthday_subject'] = 'Joyeux anniversaire de la part de {FORUMNAME}.';
$txtBirthdayEmails['happy_birthday_body'] = 'Cher {REALNAME},

{FORUMNAME} vous souhaite un joyeux anniversaire.  Que ce jour et l’année qui suit puissent vous remplir de joie.

{REGARDS}';
$txtBirthdayEmails['happy_birthday_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=2676">Thantos</a>';

$txtBirthdayEmails['karlbenson1_subject'] = 'Pour votre anniversaire...';
$txtBirthdayEmails['karlbenson1_body'] = 'Nous aurions pu vous envoyer une carte d\'anniversaire. Nous aurions pu vous envoyer des fleurs ou un g&#226;teau.

Mais nous ne l\'avons pas fait.

Nous aurions m&#234;me pu vous envoyer un de ces messages g&#233;n&#233;r&#233;s automatiquement pour vous souhaiter un joyeux anniversaire o&#249; nous n\'avions m&#234;me pas &#224; remplacer NOM &#192; INS&#201;RER.

Mais nous ne l\'avons pas fait.

Nous avons &#233;crit ces voeux d\'anniversaire juste pour vous.

Nous tenons &#224; vous souhaiter un anniversaire plein de bonheur.

{REGARDS}

//:: Ce message a &#233;t&#233; g&#233;n&#233;r&#233; automatiquement :://';
$txtBirthdayEmails['karlbenson1_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=63186">karlbenson</a>';

$txtBirthdayEmails['nite0859_subject'] = 'Joyeux anniversaire !';
$txtBirthdayEmails['nite0859_body'] = 'Vos amis sur {FORUMNAME} voudraient prendre un peu de votre temps pour vous souhaiter un joyeux anniversaire, {REALNAME}. Si vous ne l\'avez pas fait r&#233;cemment, veuillez visiter notre communaut&#233; afin que d\'autres aient l\'opportunit&#233; de vous transmettre leurs voeux !

M&#234;me si aujourd\'hui c\'est votre anniversaire, {REALNAME}, nous tenons &#224; vous rappeler que votre appartenance &#224; notre communaut&#233; a &#233;t&#233; le plus beau cadeau pour nous &#224; ce jour.

Meilleurs voeux,
L\'&#233;quipe de {FORUMNAME}';
$txtBirthdayEmails['nite0859_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=46625">nite0859</a>';

$txtBirthdayEmails['zwaldowski_subject'] = 'Voeux d\'anniversaire &#224; {REALNAME}';
$txtBirthdayEmails['zwaldowski_body'] = 'Cher {REALNAME},

Une autre année de votre vie s\'est écoulée. {FORUMNAME} espère qu\'elle a été remplie de bonheur, et vous souhaite bonne chance pour la prochaine. {REGARDS}';
$txtBirthdayEmails['zwaldowski_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=72038">zwaldowski</a>';

$txtBirthdayEmails['geezmo_subject'] = 'Joyeux anniversaire, {REALNAME} !';
$txtBirthdayEmails['geezmo_body'] = 'Savez-vous qui f&#234;te son anniversaire aujourd\'hui, {REALNAME} ?

Nous oui... VOUS !

Joyeux anniversaire !

Vous avez maintenant un an de plus mais nous esp&#233;rons que vous &#234;tes beaucoup plus heureux que l\'ann&#233;e derni&#232;re.

Profitez de votre journ&#233;e d\'aujourd\'hui, {REALNAME} !

- De la part de votre famille {FORUMNAME}';
$txtBirthdayEmails['geezmo_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=48671">geezmo</a>';

$txtBirthdayEmails['karlbenson2_subject'] = 'Vos voeux d\'anniversaire';
$txtBirthdayEmails['karlbenson2_body'] = 'Nous esp&#233;rons que votre anniversaire est le meilleur que vous ayez eu quel que soit le temps, nuageux ou ensoleill&#233;.
Ayez beaucoup de g&#226;teaux d\'anniversaire et de plaisir, et vous nous raconterez comment &#231;a s\'est pass&#233;.

Nous esp&#233;rons que ce message vous a encourag&#233; et vous fera durer, jusqu\'au m&#234;me moment au m&#234;me endroit, l\'ann&#233;e prochaine.

{REGARDS}';
$txtBirthdayEmails['karlbenson2_author'] = '<a href="http://www.simplemachines.org/community/?action=profile;u=63186">karlbenson</a>';

?>