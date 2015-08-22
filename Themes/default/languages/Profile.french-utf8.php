<?php
// Version: 2.1 Beta 2; Profile

global $scripturl, $context;

// Some of the things from the popup need their own descriptions
$txt['popup_summary'] = 'Mon Profil';
$txt['popup_showposts'] = 'Mes messages';
$txt['popup_ignore'] = 'Ignorer des personnes';

$txt['no_profile_edit'] = 'Vous n\'êtes pas autorisé à modifier le profil de ce membre.';
$txt['website_title'] = 'Titre du site web';
$txt['website_url'] = 'URL du site web';
$txt['signature'] = 'Signature';
$txt['profile_posts'] = 'Messages';
$txt['change_profile'] = 'Changer le profil';
$txt['preview_signature'] = 'Prévisualiser la signature';
$txt['current_signature'] = 'Signature actuelle';
$txt['signature_preview'] = 'Prévisualisation de la signature';
$txt['delete_user'] = 'Effacer le membre';
$txt['current_status'] = 'Statut actuel&nbsp;:';
$txt['personal_picture'] = 'Avatar personnalisé';
$txt['no_avatar'] = 'Pas d\'avatar';
$txt['choose_avatar_gallery'] = 'Choisir un avatar dans la galerie';
$txt['picture_text'] = 'Image/texte';
$txt['reset_form'] = 'Réinitialiser le formulaire';
$txt['preferred_language'] = 'Langue préférée';
$txt['age'] = 'Âge';
$txt['no_pic'] = '(pas d\'image)';
$txt['latest_posts'] = 'Derniers messages de&nbsp;: ';
$txt['additional_info'] = 'Informations supplémentaires';
$txt['avatar_by_url'] = 'Spécifiez votre propre avatar par URL. (ex&nbsp;: <em>http://www.mapage.com/monimage.gif</em>)';
$txt['my_own_pic'] = 'Spécifier un avatar par son URL';
$txt['use_gravatar'] = 'Utiliser mon Gravatar';
$txt['gravatar_alternateEmail'] = 'Normally, the Gravatar used will be based on your regular email address but if you wish to use the Gravatar from a different email account to your regular forum account (say, the Gravatar from your blog\'s email account), you can enter that email address here.';
$txt['gravatar_noAlternateEmail'] = 'The Gravatar displayed will be the one based on your account\'s email address.';
$txt['date_format'] = 'Cette sélection change le format d\'affichage des dates sur ce forum.';
$txt['time_format'] = 'Format de l\'heure';
$txt['timezone'] = 'Timezone';
$txt['display_name_desc'] = 'Ceci est le nom affiché sur toutes les pages du forum, celui que les visiteurs verront.';
$txt['personal_time_offset'] = 'Nombre d\'heures en +/- pour faire correspondre l\'heure affichée avec votre heure locale.';
$txt['dob'] = 'Date de naissance';
$txt['dob_month'] = 'Mois (MM)';
$txt['dob_day'] = 'Jour (JJ)';
$txt['dob_year'] = 'Année (AAAA)';
$txt['password_strength'] = 'Pour plus de sécurité, vous devriez choisir au moins six (6) caractères avec une combinaison de lettres, chiffres et symboles.';
$txt['include_website_url'] = 'Indispensable si vous mettez une URL ci-dessous.';
$txt['complete_url'] = 'Ceci doit être une URL complète. (http://www. ...)';
$txt['sig_info'] = 'Les signatures sont affichées en bas de chaque message ou message personnel. Vous pouvez y inclure du BBCode et des smileys.';
$txt['no_signature_set'] = 'No signature set.';
$txt['no_signature_preview'] = 'No signature to preview.';
$txt['max_sig_characters'] = '%1$s caractères sont autorisés au maximum. Il en reste&nbsp;: ';
$txt['send_member_pm'] = 'Envoyer un message personnel à ce membre';
$txt['hidden'] = 'caché';
$txt['current_time'] = 'Heure actuelle du forum';

$txt['skype_username'] = 'Votre identifiant Skype.';

$txt['language'] = 'Langue';
$txt['avatar_too_big'] = 'Cet Avatar est trop gros, merci de le redimensionner avant de réessayer (max';
$txt['invalid_registration'] = 'Date d\'inscription invalide, exemple&nbsp;: ';
$txt['current_password'] = 'Mot de passe actuel';
// Don't use entities in the below string, except the main ones. (lt, gt, quot.)
$txt['required_security_reasons'] = 'Pour des raisons évidentes de sécurité, votre mot de passe actuel est nécessaire pour modifier votre compte.';
$txt['email_change_logout'] = 'Since you decided to change your email, you will need to reactivate your account. You will now be logged out.';

$txt['timeoffset_autodetect'] = '(auto-détecter)';

$txt['secret_question'] = 'Question secrète';
$txt['secret_desc'] = 'Pour vous aider à retrouver votre mot de passe, vous pouvez entrer ici une question et sa réponse dont <strong>vous seul</strong> connaissez la teneur.';
$txt['secret_desc2'] = 'Choisissez-la prudemment et évitez que l\'on puisse deviner facilement la réponse&nbsp;! ';
$txt['secret_answer'] = 'Réponse';
$txt['secret_ask'] = 'Posez-moi ma question secrète';
$txt['cant_retrieve'] = 'Il est impossible de récupérer votre mot de passe, mais vous pouvez en spécifier un nouveau en suivant le lien envoyé à votre adresse émail.  Vous avez aussi la possibilité de spécifier un nouveau mot de passe en répondant à votre question secrète.';
$txt['incorrect_answer'] = 'Désolé, mais vous n\'avez pas défini de question secrète et réponse dans votre profil.  Merci de cliquer le bouton Retour, et utilisez la méthode par défaut pour obtenir votre mot de passe.';
$txt['enter_new_password'] = 'Merci d\'entrer la réponse à votre question, et le mot de passe que vous souhaitez utiliser.  Votre mot de passe sera remplacé par celui fourni si vous répondez correctement à votre question.';
$txt['password_success'] = 'Votre mot de passe a été modifié avec succès.<br>Cliquez <a href="' . $scripturl . '?action=login">ici</a> pour vous connecter.';
$txt['secret_why_blank'] = 'Pourquoi est-ce vide&nbsp;? ';

$txt['authentication_reminder'] = 'Rappel d\'authentification';
$txt['password_reminder_desc'] = 'Si vous avez oublié vos détails de connexion, pas de souci, on peut vous aider. Pour commencer, veuillez entrer votre identifiant ou adresse e-mail ci-dessous.';
$txt['authentication_options'] = 'Please select one of the two options below';
$txt['authentication_password_email'] = 'Envoyez-moi par e-mail un nouveau mot de passe';
$txt['authentication_password_secret'] = 'Je veux répondre à ma &quot;question secrète&quot; pour changer de mot de passe';
$txt['reminder_continue'] = 'Continuer';

$txt['current_theme'] = 'Thème actuel';
$txt['change'] = '(changer)';
$txt['theme_preferences'] = 'Préférences de thème';
$txt['theme_forum_default'] = 'Thème par défaut';
$txt['theme_forum_default_desc'] = 'Ceci est le thème par défaut, ce qui signifie que votre thème changera suivant les réglages de l\'administrateur et le forum que vous lisez.';

$txt['profileConfirm'] = 'Voulez-vous réellement effacer ce membre&nbsp;?';

$txt['custom_title'] = 'Titre personnel';

$txt['lastLoggedIn'] = 'Dernière visite';

$txt['alert_prefs'] = 'Notification Preferences';
$txt['alert_prefs_desc'] = 'This page will allow you to configure when and how you get notified about new content.';
$txt['watched_topics'] = 'Watched Topics';
$txt['watched_topics_desc'] = 'This page lets you review which topics you are watching; when topics that you are watching have been replied to, you can be notified.';
$txt['watched_boards'] = 'Watched Boards';
$txt['watched_boards_desc'] = 'This page lets you review which boards you are watching; when boards that you are watching have new topics, you can be notified.';

$txt['notification_general'] = 'General Settings';
$txt['notify_settings'] = 'Paramètres de notification&nbsp;:';
$txt['notify_save'] = 'Sauvegarder les paramètres';
$txt['notify_important_email'] = 'Recevoir les lettres d\'information, les annonces du forum et les notifications importantes par e-mail.';
$txt['notify_regularity'] = 'Pour les sujets et les sections pour lesquels j\'ai demandé la notification, notifiez-moi&nbsp;';
$txt['notify_regularity_instant'] = 'Instantanément';
$txt['notify_regularity_first_only'] = 'Instantanément - mais seulement pour la première réponse non lue';
$txt['notify_regularity_daily'] = 'Une fois par jour';
$txt['notify_regularity_weekly'] = 'Une fois par semaine';
$txt['auto_notify'] = 'Me notifier automatiquement les réponses quand je commence ou réponds à un sujet.';
$txt['notify_send_types'] = 'Pour les sujets et sections que j\'ai demandé à suivre, notifiez-moi&nbsp;';
$txt['notify_send_type_everything'] = 'De tout ce qui s\'y produit';
$txt['notify_send_type_everything_own'] = 'Des actions de modération, seulement si j\'ai démarré le sujet et que je le suis';
$txt['notify_send_type_only_replies'] = 'Seulement des réponses';
$txt['notify_send_type_nothing'] = 'Ne me notifiez pas';
$txt['notify_send_body'] = 'Lors de l\'envoi des notifications de réponses à un sujet, inclure le message complet dans l\'e-mail <em>(mais veuillez ne pas répondre à l\'e-mail envoyé&nbsp;!)</em>';
$txt['notify_alert_timeout'] = 'Timeout for Alert desktop notifications';

$txt['notify_what_how'] = 'Alert Preferences';
$txt['receive_alert'] = 'Recevoir une alerte';
$txt['receive_mail'] = 'Recevoir un émail';
$txt['alert_group_board'] = 'Sections et Sujets';
$txt['alert_group_msg'] = 'Messages';
$txt['alert_opt_pm_notify'] = 'If enabled, e-mail alerts for:';
$txt['alert_opt_msg_notify_type'] = 'Notifiez moi de:';
$txt['alert_opt_msg_auto_notify'] = 'Follow topics I create and reply to';
$txt['alert_opt_msg_receive_body'] = 'Receive message body in e-mails';
$txt['alert_opt_msg_notify_pref'] = 'How frequently to tell me:';
$txt['alert_opt_msg_notify_pref_nothing'] = 'Nothing, just make a note of it';
$txt['alert_opt_msg_notify_pref_instant'] = 'Instantanément';
$txt['alert_opt_msg_notify_pref_first'] = 'Instantanément (mais seulement pour le premier message non lu)';
$txt['alert_opt_msg_notify_pref_daily'] = 'Send me a daily email digest';
$txt['alert_opt_msg_notify_pref_weekly'] = 'Send me a weekly email digest';
$txt['alert_topic_notify'] = 'When a topic I follow gets a reply, I normally want to know via...';
$txt['alert_board_notify'] = 'When a board I follow gets a topic, I normally want to know via...';
$txt['alert_msg_mention'] = 'When my @name is mentioned in a post';
$txt['alert_msg_quote'] = 'When a post of mine is quoted (when I\'m not already watching that topic)';
$txt['alert_msg_like'] = 'When a message of mine is liked';
$txt['alert_unapproved_reply'] = 'When an reply is made to my unapproved topic';
$txt['alert_group_pm'] = 'Messages Personnels';
$txt['alert_pm_new'] = 'When I receive a new personal message';
$txt['alert_pm_reply'] = 'When a personal message I sent gets replied to';
$txt['alert_group_moderation'] = 'Modération';
$txt['alert_unapproved_post'] = 'When an unapproved topic is created';
$txt['alert_msg_report'] = 'When a message is reported';
$txt['alert_msg_report_reply'] = 'When a post report I\'ve replied to gets replied to';
$txt['alert_group_members'] = 'Membres';
$txt['alert_member_register'] = 'When a new person registers';
$txt['alert_warn_any'] = 'When other members receive a warning';
$txt['alert_buddy_request'] = 'When other members adds me as their buddy';
$txt['alert_group_calendar'] = 'Calendrier';
$txt['alert_event_new'] = 'When a new event goes into the calendar';
$txt['alert_request_group'] = 'When someone requests to join a group I moderate';
$txt['alert_member_report'] = 'When another member\'s profile is reported';
$txt['alert_member_report_reply'] = 'When a member report I\'ve replied to gets replied to';
$txt['alert_group_paidsubs'] = 'Souscriptions payées';
$txt['alert_paidsubs_expiring'] = 'When your Paid Subscriptions are about to expire';
$txt['toggle_all'] = 'toggle all';

$txt['notifications_topics'] = 'Notifications actuelles pour le sujet';
$txt['notifications_topics_list'] = 'Vous êtes avisé pour les réponses aux sujets suivants';
$txt['notifications_topics_none'] = 'Actuellement, vous ne recevez aucune notification.';
$txt['notifications_topics_howto'] = 'Pour recevoir des notifications d\'un sujet, cliquez le bouton &quot;Notifier&quot; dans celui-ci.';
$txt['notifications_boards'] = 'Notifications actuelles de sections';
$txt['notifications_boards_list'] = 'Vous êtes notifié des nouveaux sujets démarrés dans les sections suivantes&nbsp;:';
$txt['notifications_boards_none'] = 'Actuellement, vous ne recevez aucune notification de section.';
$txt['notifications_boards_howto'] = 'Pour demander des notifications pour une section spécifique, cliquez le bouton &quot;Suivre&quot; à l\'index de cette section.';
$txt['notifications_update'] = 'Désinscription';

$txt['statPanel_showStats'] = 'Statistiques utilisateur pour&nbsp;: ';
$txt['statPanel_users_votes'] = 'Nombre de votants';
$txt['statPanel_users_polls'] = 'Nombre de sondages créés';
$txt['statPanel_total_time_online'] = 'Temps total passé en ligne';
$txt['statPanel_noPosts'] = 'Vous n\'avez posté aucun message&nbsp;!';
$txt['statPanel_generalStats'] = 'Statistiques générales';
$txt['statPanel_posts'] = 'messages';
$txt['statPanel_topics'] = 'Sujets';
$txt['statPanel_total_posts'] = 'Total des messages';
$txt['statPanel_total_topics'] = 'Total des sujets commencés';
$txt['statPanel_votes'] = 'votes';
$txt['statPanel_polls'] = 'sondages';
$txt['statPanel_topBoards'] = 'Popularité des sections par messages';
$txt['statPanel_topBoards_posts'] = '%1$d messages sur les %2$d de cette section (%3$01.2f%%)';
$txt['statPanel_topBoards_memberposts'] = '%1$d messages sur les %2$d de ce membre (%3$01.2f%%)';
$txt['statPanel_topBoardsActivity'] = 'Popularité des sections par activité';
$txt['statPanel_activityTime'] = 'Activité de postage par heure';
$txt['statPanel_activityTime_posts'] = '%1$d messages (%2$d%%)';
$txt['statPanel_timeOfDay'] = 'Heure de la journée';

$txt['deleteAccount_warning'] = 'Attention - Ces actions sont irréversibles&nbsp;!';
$txt['deleteAccount_desc'] = 'À partir de cette page vous pouvez supprimer le compte et les messages d\'un membre.';
$txt['deleteAccount_member'] = 'Supprimer le compte de ce membre';
$txt['deleteAccount_posts'] = 'Supprimer les messages postés par ce membre';
$txt['deleteAccount_all_posts'] = 'Réponses de sujets';
$txt['deleteAccount_topics'] = 'Sujets et messages';
$txt['deleteAccount_votes'] = 'Remove poll votes made by this member';
$txt['deleteAccount_confirm'] = 'Êtes-vous absolument sûr de vouloir supprimer ce compte&nbsp;?';
$txt['deleteAccount_approval'] = 'Veuillez prendre note que les modérateurs du forum devront approuver cette suppression avant qu\'elle soit effective.';

$txt['profile_of_username'] = 'Profil de %1$s';
$txt['profileInfo'] = 'Infos du Profil';
$txt['showPosts'] = 'Voir les contributions';
$txt['showPosts_help'] = 'Cette section vous permet de consulter les contributions (messages, sujets et fichiers joints) d\'un utilisateur. Vous ne pourrez voir que les contributions des zones auxquelles vous avez accès.';
$txt['showMessages'] = 'Messages';
$txt['showTopics'] = 'Sujets';
$txt['showUnwatched'] = 'Unwatched topics';
$txt['showAttachments'] = 'Fichiers joints';
$txt['viewWarning_help'] = 'Cette section vous permettra de visualiser tous les avertissements donnés à ce membre.';
$txt['statPanel'] = 'Voir les statistiques';
$txt['editBuddyIgnoreLists'] = 'Amis et Ignorés';
$txt['could_not_add_person'] = 'You could not add that person to your list';
$txt['could_not_remove_person'] = 'You could not remove that person from your list';
$txt['editBuddies'] = 'Modifier la liste d\'Amis';
$txt['editIgnoreList'] = 'Modifier la liste d\'Ignorés';
$txt['trackUser'] = 'Suivre le membre';
$txt['trackActivity'] = 'Activité';
$txt['trackIP'] = 'Adresse IP';
$txt['trackLogins'] = 'Connexions';

$txt['account_info'] = 'Ceci sont vos préférences de compte. Cette page contient toutes les informations critiques qui peuvent vous identifier sur le forum. Pour des raisons de sécurité, vous devrez entrer votre mot de passe (actuel) pour modifier ces informations.';
$txt['forumProfile_info'] = 'Vous pouvez changer vos infos personnelles sur cette page. Ces informations seront visible à travers ' . $context['forum_name_html_safe'] . '. Si vous ne désirez pas présenter certaines infos, ne remplissez pas le champ - rien n\'est obligatoire ici.';
$txt['theme_info'] = 'Cette section vous permet de personnaliser l\'affichage et la disposition du forum.';
$txt['notification'] = 'Notifications et E-mails';
$txt['notification_info'] = 'SMF vous permet d\'être avisé des nouvelles réponses aux sujets, des nouveaux sujets et des nouvelles annonces du forum. Vous pouvez changer vos réglages ici, ou voir les sujets et sections que vous suivez actuellement.';
$txt['groupmembership'] = 'Adhésions aux Groupes';
$txt['groupMembership_info'] = 'Dans cette section de votre profil, vous pouvez changer le ou les groupes auxquels vous appartenez.';
$txt['ignoreboards'] = 'Ignorer les Sections';
$txt['ignoreboards_info'] = 'Cette page vous permet d\'ignorer certaines sections. Lorsqu\'une section est ignorée, l\'indicateur de nouveaux messages ne s\'affichera pas sur l\'accueil du forum. Les nouveaux messages ne seront pas affichés lors de l\'utilisation de la fonction "messages non lus" (la recherche des messages ignorera ces sections). Malgré tout, les sections ignorées apparaîtront toujours sur l\'accueil du forum et les sujets mis à jour y seront signalés si vous y pénétrez. Enfin, la fonction "réponses non lues" prend en compte toutes les sections, y compris celles qui sont ignorées.';
$txt['alerts_show'] = 'Show Alerts';

$txt['profileAction'] = 'Actions';
$txt['deleteAccount'] = 'Effacer ce compte';
$txt['profileSendIm'] = 'Envoyer un message personnel';
$txt['profile_sendpm_short'] = 'Envoyer un MP';

$txt['profileBanUser'] = 'Bannir ce membre';

$txt['display_name'] = 'Pseudonyme';
$txt['enter_ip'] = 'Entrer une IP (plage)';
$txt['errors_by'] = 'Messages d\'erreur par';
$txt['errors_desc'] = 'Ci-dessous est affichée une liste des messages d\'erreur récents transmis par ce membre.';
$txt['errors_from_ip'] = 'Messages d\'erreur depuis l\'IP (plage)';
$txt['errors_from_ip_desc'] = 'Ci-dessous est affichée une liste de toutes les erreurs générées par cette (plage d\') IP.';
$txt['ip_address'] = 'Adresse IP';
$txt['ips_in_errors'] = 'Adresses IP utilisées dans les messages d\'erreur';
$txt['ips_in_messages'] = 'Adresses IP utilisées dans les derniers messages';
$txt['members_from_ip'] = 'Membres sur cette (plage d\') IP';
$txt['members_in_range'] = 'Membres possibles dans la même plage';
$txt['messages_from_ip'] = 'Messages envoyés depuis cette (plage d\') IP';
$txt['messages_from_ip_desc'] = 'Ci-dessous est affichée la liste de tous les messages postés depuis cette (plage d\') IP.';
$txt['trackLogins_desc'] = 'La liste ci-dessous affichera tous les temps de connexion de ce compte.';
$txt['most_recent_ip'] = 'Adresse IP la plus récente';
$txt['why_two_ip_address'] = 'Pourquoi y-a-t\'il deux adresses IP listées&nbsp;?';
$txt['no_errors_from_ip'] = 'Aucun message d\'erreur depuis cette adresse IP (plage)';
$txt['no_errors_from_user'] = 'Aucun message d\'erreur de ce membre';
$txt['no_members_from_ip'] = 'Aucun membre ayant l\'IP (plage) spécifiée n\'a été trouvé';
$txt['no_messages_from_ip'] = 'Aucun message depuis l\'IP (plage) spécifiée n\'a été trouvé';
$txt['trackLogins_none_found'] = 'Aucune connexion récente n\'a été trouvée';
$txt['none'] = 'Aucun';
$txt['own_profile_confirm'] = 'Voulez-vous réellement effacer votre compte&nbsp;?';
$txt['view_ips_by'] = 'Voir les IPs utilisées par';

$txt['avatar_will_upload'] = 'Transférer un avatar';
$txt['avatar_max_size_wh'] = 'Max size: %1$spx by %2$spx';
$txt['avatar_max_size_w'] = 'Max size: %1$spx wide';
$txt['avatar_max_size_h'] = 'Max size: %2$spx high';

// Use numeric entities in the below three strings.
$txt['no_reminder_email'] = 'Impossible d\'envoyer l\'e-mail de rappel.';
$txt['send_email'] = 'Envoyer un e-mail à';
$txt['to_ask_password'] = 'pour demander le mot de passe';

$txt['user_email'] = 'Identifiant/e-mail';

// Use numeric entities in the below two strings.
$txt['reminder_subject'] = 'Nouveau mot de passe pour ' . $context['forum_name'];
$txt['reminder_mail'] = 'Cet e-mail a &#233;t&#233; envoy&#233; car la fonction "Rappel de mot de passe" a &#233;t&#233; appliqu&#233;e &#224; votre compte. Pour obtenir un nouveau mot de passe, cliquez sur le lien suivant';
$txt['reminder_sent'] = 'Un e-mail a été envoyé à votre adresse e-mail. Suivez le lien dans ce message pour obtenir un nouveau mot de passe.';
$txt['reminder_set_password'] = 'Définir le mot de passe';
$txt['reminder_password_set'] = 'Mot de passe défini avec succès';
$txt['reminder_error'] = '%1$s n\'a pas réussi à répondre correctement à sa question secrète en voulant retrouver un mot de passe perdu.';

$txt['registration_not_approved'] = 'Désolé, ce compte n\'a pas encore été approuvé. Si vous avez besoin de changer votre adresse e-mail, cliquez <a href="%1$s">ici</a>.';
$txt['registration_not_activated'] = 'Desolé, ce compte n\'a pas encore été activé. Si vous avez besoin de changer votre adresse e-mail, cliquez <a href="%1$s">ici</a>.';

$txt['primary_membergroup'] = 'Groupe principal';
$txt['additional_membergroups'] = 'Groupes additionnels';
$txt['additional_membergroups_show'] = 'montrer les groupes additionnels';
$txt['no_primary_membergroup'] = '(pas de groupe principal)';
$txt['deadmin_confirm'] = 'Êtes-vous sûr de vouloir quitter irrévocablement votre statut d\\\'administrateur&nbsp;?';

$txt['account_activate_method_2'] = 'Le compte requiert une réactivation après un changement d\'adresse e-mail';
$txt['account_activate_method_3'] = 'Le compte n\'est pas approuvé';
$txt['account_activate_method_4'] = 'Le compte est en attente d\'approbation de suppression';
$txt['account_activate_method_5'] = 'Le compte est un un compte &quot;sous l\'âge minimum&quot; en attente d\'approbation';
$txt['account_not_activated'] = 'Ce compte n\'est pas activé actuellement';
$txt['account_activate'] = 'activer';
$txt['account_approve'] = 'approuver';
$txt['user_is_banned'] = 'L\'utilisateur est actuellement banni';
$txt['view_ban'] = 'Voir';
$txt['user_banned_by_following'] = 'Cet utilisateur est actuellement affecté par les bannissements suivants';
$txt['user_cannot_due_to'] = 'L\'utilisateur ne peut pas %1$s, en raison de son bannissement&nbsp;: &quot;%2$s&quot;';
$txt['ban_type_post'] = 'poster';
$txt['ban_type_register'] = 's\'inscrire';
$txt['ban_type_login'] = 'se connecter';
$txt['ban_type_access'] = 'accéder au forum';

$txt['show_online'] = 'Autoriser l\'affichage de ma présence en ligne';

$txt['return_to_post'] = 'Par défaut, retourner dans les sujets après avoir posté.';
$txt['posts_apply_ignore_list'] = 'Cacher les messages postés par les membres que j\'ignore.';
$txt['recent_posts_at_top'] = 'Voir les messages récents en premier dans les sections.';
$txt['recent_pms_at_top'] = 'Voir les messages personnels récents en premier.';
$txt['wysiwyg_default'] = 'Afficher l\'éditeur WYSIWYG sur la page d\'écriture par défaut.';

$txt['timeformat_default'] = '(Par défaut - Réglages du forum)';
$txt['timeformat_easy1'] = 'Mois Jour, Année, hh:mm:ss am/pm (sur 12 heures)';
$txt['timeformat_easy2'] = 'Mois Jour, Année, hh:mm:ss (sur 24 heures)';
$txt['timeformat_easy3'] = 'AAAA-MM-JJ, hh:mm:ss';
$txt['timeformat_easy4'] = 'JJ Mois AAAA, hh:mm:ss';
$txt['timeformat_easy5'] = 'JJ-MM-AAAA, hh:mm:ss';

$txt['poster'] = 'Auteur';

$txt['show_children'] = 'Montrer les sous-sections sur chaque page des sections, pas seulement la première.';
$txt['show_no_avatars'] = 'Ne pas montrer les avatars des autres membres.';
$txt['show_no_signatures'] = 'Ne pas montrer les signatures des autres membres.';
$txt['show_no_censored'] = 'Ne pas censurer les mots.';
$txt['topics_per_page'] = 'Sujets affichés par page:';
$txt['messages_per_page'] = 'Messages affichés par page:';
$txt['per_page_default'] = 'Option par défaut';
$txt['calendar_start_day'] = 'Premier jour de la semaine pour le calendrier';
$txt['use_editor_quick_reply'] = 'Utiliser l\'éditeur complet dans les réponses rapides';
$txt['display_quick_mod'] = 'Montrer la modération rapide comme';
$txt['display_quick_mod_none'] = 'ne pas montrer';
$txt['display_quick_mod_check'] = 'boîtes à cocher';
$txt['display_quick_mod_image'] = 'icônes';

$txt['whois_title'] = 'Chercher cette IP sur un serveur WHOIS régional';
$txt['whois_afrinic'] = 'AfriNIC (Afrique)';
$txt['whois_apnic'] = 'APNIC (Asie et Pacifique)';
$txt['whois_arin'] = 'ARIN (Amérique du Nord, une partie des Caraïbes et Afrique sub-saharienne)';
$txt['whois_lacnic'] = 'LACNIC (Amérique Latine et Caraïbes)';
$txt['whois_ripe'] = 'RIPE (Europe, le Moyen-Orient et des parties de l\'Afrique et Asie)';

$txt['moderator_why_missing'] = 'Pourquoi n\'y a-t-il aucun modérateur ici&nbsp;?';
$txt['username_change'] = 'changer';
$txt['username_warning'] = 'Pour changer l\'identifiant de ce membre, le forum doit aussi réinitialiser son mot de passe, qui lui sera envoyé par e-mail avec son nouvel identifiant.';

$txt['show_member_posts'] = 'Voir les Messages du membre';
$txt['show_member_topics'] = 'Voir les Sujets du membre';
$txt['show_member_attachments'] = 'Voir les Fichiers joints du membre';
$txt['show_posts_none'] = 'Aucun message n\'a encore été envoyé.';
$txt['show_topics_none'] = 'Aucun sujet n\'a encore été posté.';
$txt['unwatched_topics_none'] = 'You don\'t have any topic in the unwatched list.';
$txt['show_attachments_none'] = 'Aucun fichier joint n\'a encore été envoyé.';
$txt['show_attach_filename'] = 'Nom de fichier';
$txt['show_attach_downloads'] = 'Téléchargements';
$txt['show_attach_posted'] = 'Posté';

$txt['showPermissions'] = 'Montrer les permissions';
$txt['showPermissions_status'] = 'Statut de la permission';
$txt['showPermissions_help'] = 'Cette section vous permet de voir toutes les permissions de ce membre (les permissions refusées sont <del>barrées</del>).';
$txt['showPermissions_given'] = 'Attribué par';
$txt['showPermissions_denied'] = 'Interdit par';
$txt['showPermissions_permission'] = 'Permission (les permissions refusées sont <del>barrées</del>)';
$txt['showPermissions_none_general'] = 'Aucune permission générale n\'a été enregistrée pour ce membre.';
$txt['showPermissions_none_board'] = 'Aucune permission spécifique n\'a été enregistrée pour ce membre.';
$txt['showPermissions_all'] = 'En tant qu\'administrateur, ce membre a toutes les permissions possibles.';
$txt['showPermissions_select'] = 'Montrer les permissions pour';
$txt['showPermissions_general'] = 'Permissions générales';
$txt['showPermissions_global'] = 'Toutes les sections';
$txt['showPermissions_restricted_boards'] = 'Sections à accès restreint';
$txt['showPermissions_restricted_boards_desc'] = 'Les sections suivantes ne sont pas accessibles pour cet utilisateur';

$txt['local_time'] = 'Temps local';
$txt['posts_per_day'] = 'par jour';

$txt['buddy_ignore_desc'] = 'Cette section vous permet de tenir à jour vos listes d\'amis et d\'utilisateurs à ignorer sur ce forum. En ajoutant des membres à ces listes, vous pourrez contrôler votre trafic d\'e-mails et de messages personnels selon vos préférences.';

$txt['buddy_add'] = 'Ajouter à la liste d\'amis';
$txt['buddy_remove'] = 'Enlever de la liste d\'amis';
$txt['buddy_add_button'] = 'Ajouter';
$txt['no_buddies'] = 'Votre liste actuelle d\'amis est vide';

$txt['ignore_add'] = 'Ajouter aux Ignorés';
$txt['ignore_remove'] = 'Retirer des Ignorés';
$txt['ignore_add_button'] = 'Ajouter';
$txt['no_ignore'] = 'Votre liste d\'Ignorés est actuellement vide';

$txt['regular_members'] = 'Membres inscrits';
$txt['regular_members_desc'] = 'Tous les membres du forum sans badge ou titre, sont des membres de ce groupe.';
$txt['group_membership_msg_free'] = 'Votre appartenance à un groupe a été mise à jour avec succès.';
$txt['group_membership_msg_request'] = 'Votre demande a été transmise, veuillez patienter le temps qu\'elle soit étudiée.';
$txt['group_membership_msg_primary'] = 'Votre groupe principal a été mis à jour';
$txt['current_membergroups'] = 'Groupes de membres actuels';
$txt['available_groups'] = 'Groupes disponibles';
$txt['join_group'] = 'Rejoindre ce groupe';
$txt['leave_group'] = 'Quitter ce groupe';
$txt['request_group'] = 'Demander à rejoindre ce groupe';
$txt['approval_pending'] = 'Approbations en attente';
$txt['make_primary'] = 'Définir en tant que groupe principal';

$txt['request_group_membership'] = 'Demande d\'adhésion à un groupe';
$txt['request_group_membership_desc'] = 'Avant de pouvoir rejoindre ce groupe, votre adhésion doit être approuvée par le modérateur. Merci de donner la raison pour laquelle vous voulez le rejoindre&nbsp;';
$txt['submit_request'] = 'Envoyer la demande';

$txt['profile_updated_own'] = 'Votre profil a été mis à jour avec succès';
$txt['profile_updated_else'] = 'Le profil de %1$s a été mis à jour avec succès.';

$txt['profile_error_signature_max_length'] = 'Votre signature ne doit pas dépasser %1$d caractères';
$txt['profile_error_signature_max_lines'] = 'Votre signature ne doit pas dépasser %1$d lignes';
$txt['profile_error_signature_max_image_size'] = 'Les images de votre signature ne doivent pas être plus grandes que %1$dx%2$d pixels';
$txt['profile_error_signature_max_image_width'] = 'Les images de votre signature ne doivent pas avoir plus de %1$d pixels de largeur';
$txt['profile_error_signature_max_image_height'] = 'Les images de votre signature ne doivent pas avoir plus de %1$d pixels de hauteur';
$txt['profile_error_signature_max_image_count'] = 'Vous ne pouvez pas avoir plus de %1$d images dans votre signature';
$txt['profile_error_signature_max_font_size'] = 'La taille de la police du texte de votre signature ne doit pas dépasser %1$d';
$txt['profile_error_signature_allow_smileys'] = 'Vous n\'avez pas l\'autorisation d\'utiliser des smileys dans votre signature';
$txt['profile_error_signature_max_smileys'] = 'Vous n\'êtes pas autorisé à utiliser plus de %1$d smileys dans votre signature';
$txt['profile_error_signature_disabled_bbc'] = 'Le code BBC suivant n\'est pas autorisé dans votre signature&nbsp;: %1$s';

$txt['profile_view_warnings'] = 'Voir les Avertissements';
$txt['profile_issue_warning'] = 'Envoyer un Avertissement';
$txt['profile_warning_level'] = 'Niveau d\'Avertissement&nbsp;';
$txt['profile_warning_desc'] = 'D\'ici, vous pouvez ajuster le niveau d\'avertissement d\'un utilisateur et lui donner une explication si nécessaire. Vous pouvez aussi suivre son historique d\'avertissements et voir les effets de son niveau d\'avertissement actuel, tels que définis par l\'administrateur.';
$txt['profile_warning_name'] = 'Nom du Membre&nbsp;';
$txt['profile_warning_impact'] = 'Résultat';
$txt['profile_warning_reason'] = 'Raison de l\'Avertissement&nbsp;';
$txt['profile_warning_reason_desc'] = 'Elle est obligatoire, et sera archivée.';
$txt['profile_warning_effect_none'] = 'Aucun.';
$txt['profile_warning_effect_watch'] = 'L\'utilisateur sera ajouté à la liste de surveillance des modérateurs.';
$txt['profile_warning_effect_own_watched'] = 'Vous êtes sur la liste de surveillance des modérateurs.';
$txt['profile_warning_is_watch'] = 'sous surveillance';
$txt['profile_warning_effect_moderation'] = 'Tous les messages de l\'utilisateur seront soumis à une prémodération avant publication.';
$txt['profile_warning_effect_own_moderated'] = 'Tous vos messages seront modérés.';
$txt['profile_warning_is_moderation'] = 'voit ses messages modérés avant publication';
$txt['profile_warning_effect_mute'] = 'L\'utilisateur n\'aura plus la possibilité de poster.';
$txt['profile_warning_effect_own_muted'] = 'Vous ne pouvez plus poster.';
$txt['profile_warning_is_muted'] = 'ne peut pas poster';
$txt['profile_warning_effect_text'] = 'Niveau >= %1$d: %2$s';
$txt['profile_warning_notify'] = 'Envoyer une Notification&nbsp;';
$txt['profile_warning_notify_template'] = 'Sélectionner un modèle&nbsp;:';
$txt['profile_warning_notify_subject'] = 'Titre de la Notification&nbsp;';
$txt['profile_warning_notify_body'] = 'Message de Notification&nbsp;';
$txt['profile_warning_notify_template_subject'] = 'Vous avez reçu un avertissement';
// Use numeric entities in below string.
$txt['profile_warning_notify_template_outline'] = '{MEMBER},' . "\n\n" . 'Vous avez re&#231;u un avertissement pour %1$s. Merci de cesser ces activit&#233;s et de respecter les r&#232;gles du forum, sans quoi nous devrons prendre d\'autres mesures.' . "\n\n" . '{REGARDS}';
$txt['profile_warning_notify_template_outline_post'] = '{MEMBER},' . "\n\n" . 'Vous avez re&#231;u un avertissement pour %1$s en rapport avec ce message :' . "\n" . '{MESSAGE}.' . "\n\n" . 'Merci de cesser ces activit&#233;s et de respecter les r&#232;gles du forum, sans quoi nous devrons prendre d\'autres mesures.' . "\n\n" . '{REGARDS}';
$txt['profile_warning_notify_for_spamming'] = 'spam';
$txt['profile_warning_notify_title_spamming'] = 'Spam';
$txt['profile_warning_notify_for_offence'] = 'messages au contenu inapproprié';
$txt['profile_warning_notify_title_offence'] = 'Messages au contenu inapproprié';
$txt['profile_warning_notify_for_insulting'] = 'insultes envers d\'autres utilisateurs ou membres de l\'équipe';
$txt['profile_warning_notify_title_insulting'] = 'Insultes Utilisateurs/Equipe';
$txt['profile_warning_issue'] = 'Donner un Avertissement';
$txt['profile_warning_max'] = '(Max. 100)';
$txt['profile_warning_limit_attribute'] = 'Notez que vous ne pouvez pas ajuster ce niveau d\'utilisateur plus de %1$d%% fois par période de 24 heures.';
$txt['profile_warning_errors_occured'] = 'L\'avertissement n\'a pas pu être envoyé pour les erreurs suivantes';
$txt['profile_warning_success'] = 'Avertissement envoyé avec succès';
$txt['profile_warning_new_template'] = 'Nouveau Modèle';

$txt['profile_warning_previous'] = 'Avertissements précédents';
$txt['profile_warning_previous_none'] = 'Cet utilisateur n\'a jamais reçu d\'avertissement à ce jour.';
$txt['profile_warning_previous_issued'] = 'Donné par';
$txt['profile_warning_previous_time'] = 'Date';
$txt['profile_warning_previous_level'] = 'Points';
$txt['profile_warning_previous_reason'] = 'Raison';
$txt['profile_warning_previous_notice'] = 'Voir les remarques envoyées au membre';

$txt['viewwarning'] = 'Voir les Avertissements';
$txt['profile_viewwarning_for_user'] = 'Avertissements pour %1$s';
$txt['profile_viewwarning_no_warnings'] = 'Aucun avertissement n\'a été donné.';
$txt['profile_viewwarning_desc'] = 'Vous trouverez ci-dessous la liste des avertissements qui ont été donnés par l\'équipe de modération du forum.';
$txt['profile_viewwarning_previous_warnings'] = 'Avertissements antérieurs';
$txt['profile_viewwarning_impact'] = 'Impact de l\'avertissement';

$txt['subscriptions'] = 'Abonnements payants';

$txt['pm_settings_desc'] = 'D\'ici, vous pouvez modifier les options pour vos messages personnels, comme leur affichage et qui peut vous en envoyer.';
$txt['email_notify'] = 'Notifier par e-mail à chaque fois que vous recevez un message personnel:';
$txt['email_notify_buddies'] = 'Amis uniquement';
$txt['email_notify_all'] = 'All members';

$txt['pm_receive_from'] = 'Recevoir des messages personnels de&nbsp;:';
$txt['pm_receive_from_everyone'] = 'Tous les membres';
$txt['pm_receive_from_ignore'] = 'Tous les membres, sauf ceux que j\'ignore';
$txt['pm_receive_from_admins'] = 'Les administrateurs seulement';
$txt['pm_receive_from_buddies'] = 'Mes amis et les administrateurs seulement';

$txt['popup_messages'] = 'Afficher une fenêtre pop-up lorsque je reçois de nouveaux messages';
$txt['pm_remove_inbox_label'] = 'Supprimer le label &quot;Boîte de réception&quot; lors de l\'ajout d\'un autre label';
$txt['pm_display_mode'] = 'Afficher les messages personnels';
$txt['pm_display_mode_all'] = 'Tous à la fois';
$txt['pm_display_mode_one'] = 'Un à la fois';
$txt['pm_display_mode_linked'] = 'Triés par conversations';

$txt['tracking'] = 'Suivi';
$txt['tracking_description'] = 'Cette section vous permet de vérifier certaines actions faites sur le profil de ce membre, mais aussi de suivre son adresse IP.';

$txt['trackEdits'] = 'Modifications du Profil';
$txt['trackEdit_deleted_member'] = 'Membre supprimé';
$txt['trackEdit_no_edits'] = 'Aucune modification n\'a été enregistrée pour ce membre.';
$txt['trackEdit_action'] = 'Champ';
$txt['trackEdit_before'] = 'Valeur précédente';
$txt['trackEdit_after'] = 'Valeur suivante';
$txt['trackEdit_applicator'] = 'Changée par';

$txt['trackEdit_action_real_name'] = 'Nom du Membre';
$txt['trackEdit_action_usertitle'] = 'Titre personnalisé';
$txt['trackEdit_action_member_name'] = 'Identifiant';
$txt['trackEdit_action_email_address'] = 'Adresse e-mail';
$txt['trackEdit_action_id_group'] = 'Groupe de membres principal';
$txt['trackEdit_action_additional_groups'] = 'Groupes de membres additionnels';

$txt['trackGroupRequests'] = 'Group Requests';
$txt['trackGroupRequests_title'] = 'Group Requests for %1$s';
$txt['requested_group'] = 'Requested Group';
$txt['requested_group_reason'] = 'Reason Given';
$txt['requested_group_time'] = 'Date';
$txt['requested_group_outcome'] = 'Outcome';
$txt['requested_none'] = 'There are no requests made by this user.';
$txt['outcome_pending'] = 'Open';
$txt['outcome_approved'] = 'Approved by %1$s on %2$s';
$txt['outcome_refused'] = 'Refused by %1$s on %2$s';
$txt['outcome_refused_reason'] = 'Refused by %1$s on %2$s, reason given: %3$s';

$txt['report_profile'] = 'Report This Member';
$txt['notification_remove_pref'] = 'Use default preference';

$txt['tfa_profile_label'] = 'Two-Factor Authentication';
$txt['tfa_profile_desc'] = 'TFA allows you to have a secondary layer of security by assigning a dedicated device without which no one would be able to log into your account even if they have your username and password';
$txt['tfa_profile_enable'] = 'Enable Two-Factor Authentication';
$txt['tfa_profile_enabled'] = 'Two-Factor Authentication is enabled. <a href="%s">Disable</a>';
$txt['tfa_profile_disabled'] = 'Two-Factor Authentication is disabled';
$txt['tfa_title'] = 'Enable Two-Factor Authentication via compatible application';
$txt['tfa_desc'] = 'In order to have Two-Factor Authentication, you would need a compatible app such as Google Authenticator on your device. Once you have enabled 2FA for your account, you will be required to enter a code on login via the paired device alongside your username and password in order to successfully login. After you have enabled 2FA, a backup code will be provided should you lose your paired device.';
$txt['tfa_forced_desc'] = 'Administrator has forced 2FA to be enabled on all accounts, please enable 2FA here in order to resume';
$txt['tfa_step1'] = '1. Enter your current password';
$txt['tfa_step2'] = '2. Enter the secret';
$txt['tfa_step2_desc'] = 'In order to setup the app, either scan the QR code on the right side or enter the following code manually: ';
$txt['tfa_step3'] = '3. Enter the code generated by the app';
$txt['tfa_enable'] = 'Enable';
$txt['tfa_pass_invalid'] = 'Entered password is invalid, please try again';
$txt['tfa_code_invalid'] = 'Entered code is invalid, please try again';
$txt['tfa_backup_invalid'] = 'Entered backup code is invalid, please try again';
$txt['tfa_backup_title'] = 'Save this Two-Factor Authentication Backup code somewhere safe!';
$txt['tfa_backup_desc'] = 'In case you have lost your device or authentication app, you can use the backup code provided to you when 2FA was setup. In case you have lost that as well, please contact the administrator';
$txt['tfa_backup_used_desc'] = 'Your backup code has been successfully entered and 2FA details have been reset, if you wish to use 2FA again you need to enable it from here';
$txt['tfa_login_desc'] = 'Enter code generated by authenticating application from your paired device below';
$txt['tfa_backup'] = 'Or use backup code';
$txt['tfa_code'] = 'Code';
$txt['tfa_preserve'] = 'Remember this computer for 30 days (not recommended on shared computers)';
$txt['tfa_backup_code'] = 'Backup code';
$txt['tfa_wait'] = 'Please wait for about 2 minutes before attempting to log in via 2FA again';

?>