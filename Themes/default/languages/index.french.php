<?php
// Version: 2.1 Beta 2; Index

global $forum_copyright, $webmaster_email, $scripturl, $context, $boardurl;

// Locale (strftime, pspell_new) and spelling. (pspell_new, can be left as '' normally.)
// For more information see:
//   - http://www.php.net/function.pspell-new
//   - http://www.php.net/function.setlocale
// Again, SPELLING SHOULD BE '' 99% OF THE TIME!!  Please read this!
$txt['lang_locale'] = 'fr_FR';
$txt['lang_dictionary'] = 'fr';
$txt['lang_spelling'] = 'french';

// Ensure you remember to use uppercase for character set strings.
$txt['lang_character_set'] = 'ISO-8859-1';
// Character set and right to left?
$txt['lang_rtl'] = false;
// Number format.
$txt['number_format'] = '1,234.00 ';

$txt['days_title'] = 'Jours';
$txt['days'] = array('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi');
$txt['days_short'] = array('Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam');
// Months must start with 1 => 'January'. (or translated, of course.)
$txt['months_title'] = 'Mois';
$txt['months'] = array(1 => 'Janvier', 'F�vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao�t', 'Septembre', 'Octobre', 'Novembre', 'D�cembre');
$txt['months_titles'] = array(1 => 'Janvier', 'F�vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao�t', 'Septembre', 'Octobre', 'Novembre', 'D�cembre');
$txt['months_short'] = array(1 => 'Jan', 'F�v', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Ao�t', 'Sep', 'Oct', 'Nov', 'D�c');

$txt['minutes_label'] = 'Minutes';
$txt['hours_label'] = 'Heures';
$txt['years_title'] = 'Ann�es';

$txt['time_am'] = 'am';
$txt['time_pm'] = 'pm';

$txt['admin'] = 'Admin';
$txt['moderate'] = 'Mod�rer';

$txt['save'] = 'Sauvegarder';
$txt['upload'] = 'Upload';

$txt['modify'] = 'Modifier';
$txt['forum_index'] = '%1$s - Accueil';
$txt['members'] = 'Membres';
$txt['board_name'] = 'Nom de la section';
$txt['posts'] = 'Messages';

$txt['member_postcount'] = 'Messages';
$txt['no_subject'] = '(Pas de titre)';
$txt['view_profile'] = 'Voir le profil';
$txt['guest_title'] = 'Invit�';
$txt['author'] = 'Auteur';
$txt['on'] = 'le';
$txt['remove'] = 'Enlever';
$txt['start_new_topic'] = 'Nouveau sujet';

$txt['login'] = 'Identifiez-vous';
// Use numeric entities in the below string.
$txt['username'] = 'Identifiant';
$txt['password'] = 'Mot de passe';

$txt['username_no_exist'] = 'Cet identifiant n\'existe pas.';
$txt['no_user_with_email'] = 'Il n\'y a pas d\'identifiants associ�s � cette adresse e-mail.';

$txt['board_moderator'] = 'Mod�rateur';
$txt['remove_topic'] = 'Effacer le sujet';
$txt['topics'] = 'Sujets';
$txt['modify_msg'] = 'Modifier le message';
$txt['name'] = 'Nom';
$txt['email'] = 'E-mail';
$txt['user_email_address'] = 'Adresse Email';
$txt['subject'] = 'Titre';
$txt['message'] = 'Message';
$txt['redirects'] = 'Redirections';
$txt['quick_modify'] = 'Modification rapide';
$txt['reason_for_edit'] = 'Reason for editing';

$txt['choose_pass'] = 'Choisir un mot de passe';
$txt['verify_pass'] = 'V�rifier le mot de passe';
$txt['notify_announcements'] = 'Allow the administrators to send me important news by email';

$txt['position'] = 'Rang';

$txt['profile_of'] = 'Voir le profil de';
$txt['total'] = 'Total';
$txt['website'] = 'Site Web';
$txt['register'] = 'Inscrivez-vous';
$txt['warning_status'] = '�tat d\'avertissement';
$txt['user_warn_watch'] = 'L\'utilisateur est sur la liste de surveillance des mod�rateurs';
$txt['user_warn_moderate'] = 'Les messages de l\'utilisateur doivent �tre approuv�s avant publication';
$txt['user_warn_mute'] = 'Suite � un bannissement, l\'utilisateur ne peut plus poster';
$txt['warn_watch'] = 'Surveill�';
$txt['warn_moderate'] = 'Mod�r�';
$txt['warn_mute'] = 'Mis au silence';

$txt['message_index'] = 'Index des messages';
$txt['news'] = 'Nouvelles';
$txt['home'] = 'Accueil';
$txt['page'] = 'Page';
$txt['prev'] = 'Page pr�c�dente';
$txt['next'] = 'Page suivante';

$txt['lock_unlock'] = '(D�)bloquer le sujet';
$txt['post'] = 'Soumettre';
$txt['error_occured'] = 'Une erreur s\'est produite&nbsp;!';
$txt['at'] = '�';
$txt['by'] = 'par';
$txt['logout'] = 'D�connexion';
$txt['started_by'] = 'D�marr� par';
$txt['topic_started_by'] = 'D�marr� par <strong>%1$s</strong> dans <em>%2$s</em>';
$txt['replies'] = 'R�ponses';
$txt['last_post'] = 'Dernier message';
$txt['first_post'] = 'Premier message';
$txt['last_poster'] = 'Dernier message par';
$txt['last_post_message'] = '<strong>Dernier message: </strong>%3$s<span class="postby">%2$s par %1$s</span> ';
$txt['last_post_topic'] = '%1$s<br>by %2$s';
$txt['post_by_member'] = '<strong>%1$s</strong> by <strong>%2$s</strong><br>';
$txt['boardindex_total_posts'] = '%1$s Messages dans %2$s Sujets par %3$s Membres';
$txt['show'] = 'Afficher';
$txt['hide'] = 'Cacher';

$txt['admin_login'] = 'Connexion Admin';
// Use numeric entities in the below string.
$txt['topic'] = 'Sujet';
$txt['help'] = 'Aide';
$txt['terms_and_rules'] = 'Terms and Rules';
$txt['watch_board'] = 'Watch this Board';
$txt['unwatch_board'] = 'Stop Watching Board';
$txt['watch_topic'] = 'Watch this Topic';
$txt['unwatch_topic'] = 'Stop Watching Topic';
$txt['watching_this_topic'] = 'You are watching this topic, and will receive notifications about it.';
$txt['notify'] = 'Notifier';
$txt['unnotify'] = 'Se d�sinscrire';
$txt['notify_request'] = 'Voulez-vous recevoir un e-mail de notification si quelqu\'un r�pond � ce sujet&nbsp;?';
// Use numeric entities in the below string.
$txt['regards_team'] = "Cordialement,\nL'&#233;quipe ". $context['forum_name'];
$txt['notify_replies'] = 'Notification de r�ponse';
$txt['move_topic'] = 'D�placer le sujet';
$txt['move_to'] = 'D�placer vers';
$txt['pages'] = 'Pages';
$txt['users_active'] = 'Membres actifs dans les %1$d derni�res minutes';
$txt['personal_messages'] = 'Messages personnels';
$txt['reply_quote'] = 'Citer en r�ponse';
$txt['reply'] = 'R�pondre';
$txt['reply_noun'] = 'R�ponse';
$txt['reply_number'] = 'R�ponse #%1$s';
$txt['approve'] = 'Approuver';
$txt['unapprove'] = 'D�sapprouver';
$txt['approve_all'] = 'Tout approuver';
$txt['issue_warning'] = 'Issue Warning';
$txt['awaiting_approval'] = 'En attente d\'approbation';
$txt['attach_awaiting_approve'] = 'Fichiers joints en attente d\'approbation';
$txt['post_awaiting_approval'] = 'Ce message est en attente d\'approbation par un mod�rateur.';
$txt['there_are_unapproved_topics'] = 'Il y a %1$s sujets et %2$s messages en attente d\'approbation dans cette section. Cliquez <a href="%3$s">ici</a> pour tous les voir.';
$txt['send_message'] = 'Envoyer un message';

$txt['msg_alert_no_messages'] = 'Vous n\'avez aucun message';
$txt['msg_alert_one_message'] = 'vous avez <a href="%1$s">1 message</a>';
$txt['msg_alert_many_message'] = 'vous avez <a href="%1$s">%2$d messages</a>';
$txt['msg_alert_one_new'] = '1 est nouveau';
$txt['msg_alert_many_new'] = '%1$d sont nouveaux';
$txt['new_alert'] = 'New Alert';
$txt['remove_message'] = 'Effacer ce message';
$txt['remove_message_question'] = 'Remove this post?';

$txt['topic_alert_none'] = 'Pas de messages...';
$txt['pm_alert_none'] = 'Pas de messages...';
$txt['no_messages'] = 'No messages';

$txt['online_users'] = 'Membres en ligne';
$txt['jump_to'] = 'Aller �';
$txt['go'] = ' Allons-y&nbsp;! ';
$txt['are_sure_remove_topic'] = '�tes-vous s�r de vouloir effacer ce sujet&nbsp;?';
$txt['yes'] = 'Oui';
$txt['no'] = 'Non';

$txt['search_end_results'] = 'Fin des r�sultats';
$txt['search_on'] = 'le';

$txt['search'] = 'Rechercher';
$txt['all'] = 'Toutes';
$txt['search_entireforum'] = 'Forum entier';
$txt['search_thisbrd'] = 'Cette section';
$txt['search_thistopic'] = 'Ce sujet';
$txt['search_members'] = 'Membres';

$txt['back'] = 'Retour';
$txt['continue'] = 'Continuer';
$txt['password_reminder'] = 'Rappel de mot de passe';
$txt['topic_started'] = 'Discussion d�marr�e par';
$txt['title'] = 'Titre';
$txt['post_by'] = 'Post� par';
$txt['memberlist_searchable'] = 'Liste de tous les membres inscrits sur ce forum.';
$txt['welcome_newest_member'] = 'Veuillez accueillir %1$s, notre nouveau membre.';
$txt['admin_center'] = 'Centre d\'administration';
$txt['last_edit_by'] = '<span class="lastedit">Derni�re �dition</span>: %1$s par %2$s';
$txt['last_edit_reason'] = '<span class="lastedit">Reason</span>: %1$s';
$txt['notify_deactivate'] = 'Voulez-vous d�sactiver la notification pour ce sujet&nbsp;?';

$txt['recent_posts'] = 'Messages r�cents';

$txt['location'] = 'Localisation';
$txt['gender'] = 'Sexe';
$txt['personal_text'] = 'Personal Text';
$txt['date_registered'] = 'Inscrit le';

$txt['recent_view'] = 'Voir les plus r�cents messages du forum.';
$txt['recent_updated'] = 'est le dernier sujet mis � jour';
$txt['is_recent_updated'] = '%1$s est le dernier sujet mis � jour';

$txt['male'] = 'Homme';
$txt['female'] = 'Femme';

$txt['error_invalid_characters_username'] = 'Caract�re invalide dans l\'identifiant/pseudonyme.';

$txt['welcome_guest'] = 'Bienvenue, <strong>%1$s</strong>. Merci de <a href="%3$s" onclick="%4$s">vous connecter</a>.';

//$txt['welcome_guest_register'] = 'Welcome, <strong>%1$s</strong>. Please <a href="' . $scripturl . '?action=login">login</a> or <a href="' . $scripturl . '?action=register">register</a>.';
$txt['welcome_guest_register'] = 'Bienvenue sur <strong>%2$s</strong>. Veuillez vous <a href="%3$s" onclick="%4$s">connecter</a> ou vous <a href="%5$s">enregistrer</a>.';

$txt['please_login'] = 'Veuillez vous <a href="' . $scripturl . '?action=login">connecter</a>.';
$txt['login_or_register'] = 'Merci de <a href="' . $scripturl . '?action=login">vous connecter</a> ou de <a href="' . $scripturl . '?action=register">vous inscrire</a>.';
$txt['welcome_guest_activate'] = '<br />Avez-vous perdu votre <a href="' . $scripturl . '?action=activate">e-mail d\'activation</a>&nbsp;?';
// @todo the following to sprintf
$txt['hello_member'] = 'Coucou,';
// Use numeric entities in the below string.
$txt['hello_guest'] = 'Bienvenue,';
$txt['welmsg_hey'] = 'Coucou,';
$txt['welmsg_welcome'] = 'Bienvenue,';
$txt['welmsg_please'] = 'S\'il vous pla�t';
$txt['select_destination'] = 'Merci de choisir une destination';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['posted_by'] = 'Post� par';

$txt['icon_smiley'] = 'Sourit';
$txt['icon_angry'] = 'F�ch�';
$txt['icon_cheesy'] = 'D�lire';
$txt['icon_laugh'] = 'Rit';
$txt['icon_sad'] = 'Triste';
$txt['icon_wink'] = 'Clin d\'oeil';
$txt['icon_grin'] = 'Grimace';
$txt['icon_shocked'] = 'Choqu�';
$txt['icon_cool'] = 'Cool';
$txt['icon_huh'] = 'Hein ?';
$txt['icon_rolleyes'] = 'Roule des yeux';
$txt['icon_tongue'] = 'Tire la langue';
$txt['icon_embarrassed'] = 'Embarrass�';
$txt['icon_lips'] = 'Bouche cousue';
$txt['icon_undecided'] = 'Ind�cis';
$txt['icon_kiss'] = 'Bisou';
$txt['icon_cry'] = 'Pleure';

$txt['moderator'] = 'Mod�rateur';
$txt['moderators'] = 'Mod�rateurs';

$txt['mark_board_read'] = 'Marquer les sujets comme lus pour cette section';
$txt['views'] = 'Vues';
$txt['new'] = 'Nouveau';

$txt['view_all_members'] = 'Voir tous les membres';
$txt['view'] = 'Voir';

$txt['viewing_members'] = 'Voir les membres de %1$s � %2$s';
$txt['of_total_members'] = 'sur %1$s au total';

$txt['forgot_your_password'] = 'Mot de passe oubli�&nbsp;?';

$txt['date'] = 'Date';
// Use numeric entities in the below string.
$txt['from'] = 'De';
$txt['check_new_messages'] = 'V�rifier les nouveaux messages';
$txt['to'] = '�';

$txt['board_topics'] = 'Sujets';
$txt['members_title'] = 'Membres';
$txt['members_list'] = 'Liste des membres';
$txt['new_posts'] = 'Nouveaux messages';
$txt['old_posts'] = 'Pas de nouveau message';
$txt['redirect_board'] = 'Redirection';

$txt['sendtopic_send'] = 'Envoyer';
$txt['report_sent'] = 'Votre rapport a �t� envoy� avec succ�s.';
$txt['post_becomesUnapproved'] = 'Your message became unapproved because it was posted on an unapproved topic, once the topic gets approved your message will be approved too.';

$txt['time_offset'] = 'D�calage horaire';
$txt['or'] = 'ou';

$txt['no_matches'] = 'D�sol�, aucune correspondance trouv�e';

$txt['notification'] = 'Notification';

$txt['your_ban'] = 'D�sol� %1$s, vous �tes banni de ce forum&nbsp;!';
$txt['your_ban_expires'] = 'Ce bannissement devrait expirer le %1$s';
$txt['your_ban_expires_never'] = 'Ce bannissement n\'a pas de date d\'expiration pr�vue.';
$txt['ban_continue_browse'] = 'Vous pouvez cependant continuer � naviguer sur le forum en mode invit�.';

$txt['mark_as_read'] = 'Marquer TOUS les messages comme lus';

$txt['locked_topic'] = 'Sujet bloqu�';
$txt['normal_topic'] = 'Sujet normal';
$txt['participation_caption'] = 'Sujet dans lequel vous �tes intervenu';
$txt['moved_topic'] = 'Sujet d�plac�';

$txt['go_caps'] = 'Aller';

$txt['print'] = 'Imprimer';
$txt['profile'] = 'Profil';
$txt['topic_summary'] = 'R�sum� de la discussion';
$txt['not_applicable'] = 'N/A';
$txt['name_in_use'] = 'Ce nom est d�j� utilis� par un autre membre.';

$txt['total_members'] = 'Total des membres';
$txt['total_posts'] = 'Total des messages';
$txt['total_topics'] = 'Total des sujets';

$txt['mins_logged_in'] = 'Dur�e de connexion (en minutes)&nbsp;';

$txt['preview'] = 'Pr�visualiser';
$txt['always_logged_in'] = 'Toujours connect�';

$txt['logged'] = 'IP archiv�e';
// Use numeric entities in the below string.
$txt['ip'] = 'IP';

$txt['www'] = 'WWW';

$txt['hours'] = 'heures';
$txt['minutes'] = 'minutes';
$txt['seconds'] = 'secondes';

// Used upper case in Paid subscriptions management
$txt['hour'] = 'Heure';
$txt['days_word'] = 'jours';

$txt['search_for'] = 'Rechercher';
$txt['search_match'] = 'Correspondance';

$txt['forum_in_maintainence'] = 'Your forum is in Maintenance Mode. Only administrators can currently log in.';
$txt['maintenance_page'] = 'You can turn off Maintenance Mode from the <a href="%1$s">Server Settings</a> area.';

$txt['read_one_time'] = 'Lu 1 fois';
$txt['read_many_times'] = 'Lu %1$d fois';

$txt['forum_stats'] = 'Stats du forum';
$txt['latest_member'] = 'Dernier membre';
$txt['total_cats'] = 'Total des cat�gories';
$txt['latest_post'] = 'Dernier message';

$txt['total_boards'] = 'Total des sections';

$txt['print_page'] = 'Imprimer la page';
$txt['print_page_text'] = 'Texte seulement';
$txt['print_page_images'] = 'Texte avec images';

$txt['valid_email'] = 'Ce doit �tre une adresse e-mail valide.';

$txt['geek'] = 'Je suis un geek&nbsp;!!';
$txt['info_center_title'] = '%1$s - Centre d\'informations';

$txt['watch'] = 'Watch';
$txt['unwatch'] = 'Stop watching';

$txt['check_all'] = 'Tout cocher';

// Use numeric entities in the below string.
$txt['database_error'] = 'Erreur de base de donn&#233;es';
$txt['try_again'] = 'Merci de r�essayer.  Si l\'erreur se reproduit, signalez cette erreur � un administrateur.';
$txt['file'] = 'Fichier';
$txt['line'] = 'Ligne';
// Use numeric entities in the below string.
$txt['tried_to_repair'] = 'SMF a d&#233;tect&#233; et a automatiquement essay&#233; de r&#233;parer une erreur dans votre base de donn&#233;es. Si le probl&#232;me persiste ou si vous continuez de recevoir ces e-mails, contactez votre h&#233;bergeur.';
$txt['database_error_versions'] = '<strong>Note</strong>&nbsp;: Il est <em>possible</em> que votre base de donn�es n�cessite une mise � jour. Actuellement, la version des fichiers du forum est %1$s, alors que votre base de donn�es est en version SMF %2$s. L\'erreur signal�e ci-dessus pourrait peut-�tre corrig�e en ex�cutant la derni�re version de upgrade.php.';
$txt['template_parse_error'] = 'Erreur de traitement du mod�le&nbsp;!';
$txt['template_parse_error_message'] = 'Il semble que le forum rencontre actuellement quelques difficult�s avec un de ses mod�les graphiques. Le probl�me pourrait n\'�tre que temporaire, essayez de revenir plus tard. Si vous continuez � voir ce message, contactez l\'administrateur.<br /><br />Vous pouvez aussi essayer de <a href="javascript:location.reload();">recharger la page</a>.';
$txt['template_parse_error_details'] = 'Un probl�me s\'est produit durant le chargement du mod�le ou du fichier de langue <tt><strong>%1$s</strong></tt>. V�rifiez la syntaxe et r�essayez. Rappelez-vous que les apostrophes (<tt>\'</tt>) doivent souvent �tre �chapp�es via une barre oblique (<tt>\\</tt>). Pour obtenir plus de d�tails sur ces erreurs par PHP, essayez d\'<a href="' . $boardurl . '%1$s">acc�der directement au fichier</a>.<br /><br />Vous pouvez aussi <a href="javascript:location.reload();">recharger la page</a> ou <a href="' . $scripturl . '?theme=1">utiliser le th�me par d�faut</a>.';
$txt['template_parse_errmsg'] = 'Unfortunately more information is not available at this time as to exactly what is wrong.';

$txt['today'] = '<strong>Aujourd\'hui</strong> � ';
$txt['yesterday'] = '<strong>Hier</strong> � ';
$txt['new_poll'] = 'Nouveau sondage';
$txt['poll_question'] = 'Question';
$txt['poll_vote'] = 'Soumettre mon vote';
$txt['poll_total_voters'] = 'Total des votants';
$txt['shortcuts'] = 'Raccourcis&nbsp;: tapez [ALT]+[S] pour soumettre/poster ou [ALT]+[P] pour pr�visualiser';
$txt['shortcuts_firefox'] = 'Raccourcis&nbsp;: tapez [SHIFT]+[ALT]+[S] pour soumettre/poster ou [SHIFT]+[ALT]+[P] pour pr�visualiser';
$txt['shortcuts_drafts'] = ', alt+d sauvegarder le brouillon';
$txt['shortcuts_drafts_firefox'] = ', shift+alt+d sauvegarder le brouillon';
$txt['poll_results'] = 'Voir les r�sultats';
$txt['poll_lock'] = 'Bloquer les votes';
$txt['poll_unlock'] = 'D�bloquer les votes';
$txt['poll_edit'] = 'Modifier le sondage';
$txt['poll'] = 'Sondage';
$txt['one_day'] = '1 jour';
$txt['one_week'] = '1 semaine';
$txt['two_weeks'] = '2 Semaines';
$txt['one_month'] = '1 mois';
$txt['two_months'] = '2 Mois';
$txt['forever'] = 'Toujours';
$txt['quick_login_dec'] = 'Connexion avec identifiant, mot de passe et dur�e de la session';
$txt['one_hour'] = '1 heure';
$txt['moved'] = 'D�PLAC�';
$txt['move_why'] = 'Please enter a brief description as to<br>why this topic is being moved.';
$txt['board'] = 'Section';
$txt['in'] = 'dans';
$txt['sticky_topic'] = 'Sujet �pingl�';

$txt['delete'] = 'Effacer';

$txt['your_pms'] = 'Vos messages personnels';

$txt['kilobyte'] = 'ko';
$txt['megabyte'] = 'Mo';

$txt['more_stats'] = '[plus de stats]';

// Use numeric entities in the below three strings.
$txt['code'] = 'Code';
$txt['code_select'] = '[S�lectionner]';
$txt['quote_from'] = 'Citation de';
$txt['quote'] = 'Citer';
$txt['quote_action'] = 'Quote';
$txt['quote_selected_action'] = 'Quote selected text';
$txt['fulledit'] = 'Edition&nbsp;compl�te';
$txt['edit'] = 'Editer';
$txt['quick_edit'] = 'Edition rapide';
$txt['post_options'] = 'Plus...';

$txt['merge_to_topic_id'] = 'ID du sujet de destination';
$txt['split'] = 'S�parer';
$txt['merge'] = 'Fusionner des sujets';
$txt['target_id'] = 'Select target by topic ID';
$txt['target_below'] = 'Select target from the list below';
$txt['subject_new_topic'] = 'Titre du nouveau sujet';
$txt['split_this_post'] = 'Ne s�parer du sujet que ce message-ci.';
$txt['split_after_and_this_post'] = 'S�parer du sujet ce message et tous ceux qui le suivent.';
$txt['select_split_posts'] = 'Choisir les messages � s�parer du sujet.';
$txt['new_topic'] = 'Nouveau sujet';
$txt['split_successful'] = 'Ce sujet a �t� s�par� en deux avec succ�s.';
$txt['origin_topic'] = 'Sujet d\'origine';
$txt['please_select_split'] = 'Merci de choisir quels messages vous voulez s�parer.';
$txt['merge_successful'] = 'Sujet fusionn�s avec succ�s.';
$txt['new_merged_topic'] = 'Sujet apr�s fusion';
$txt['topic_to_merge'] = 'Sujets � fusionner';
$txt['target_board'] = 'Section de destination';
$txt['target_topic'] = 'Sujet de destination';
$txt['merge_confirm'] = '�tes-vous s�r de vouloir fusionner';
$txt['with'] = 'avec';
$txt['merge_desc'] = 'Cette fonction r�unira les messages de deux sujets en un seul. Les messages seront class�s par date de publication. Le plus ancien message deviendra le premier du nouveau sujet fusionn�.';

$txt['set_sticky'] = '�pingler le sujet';
$txt['set_nonsticky'] = 'D�pingler le sujet';
$txt['set_lock'] = 'Bloquer le sujet';
$txt['set_unlock'] = 'D�bloquer le sujet';

$txt['search_advanced'] = 'Recherche avanc�e';

$txt['security_risk'] = 'RISQUE DE S�CURIT� MAJEUR';
$txt['not_removed'] = 'Vous n\'avez pas enlev� ';
$txt['not_removed_extra'] = '%1$s est une copie de %2$s qui n\'a pas �t� g�n�r�e par SMF. Il est accessible directement et peut �tre exploit� pour obtenir un acc�s non autoris� � votre forum. Vous devriez le supprimer imm�diatement.';
$txt['generic_warning'] = 'Avertissement';
$txt['agreement_missing'] = 'Vous demandez aux nouveaux utilisateurs d\'accepter un accord d\'enregistrement, alors que le fichier (agreement.txt) n\'existe pas.';

$txt['cache_writable'] = 'Le r�pertoire cache est prot�g� en �criture - cela affectera grandement la performance de votre forum.';

$txt['page_created_full'] = 'Page cr��e en %1$.3f secondes avec %2$d requ�tes.';

$txt['report_to_mod_func'] = 'Utilisez cette fonction pour informer les mod�rateurs et administrateurs d\'un message abusif ou erron�.<br /><em>Veuillez noter que votre adresse e-mail sera r�v�l�e aux mod�rateurs si vous utilisez cette fonction.</em>';
$txt['report_profile_func'] = 'Use this function to inform the administrators of abusive profile content, such as spam or inappropriate images.';

$txt['online'] = 'En ligne';
$txt['member_is_online'] = '%1$s est en ligne';
$txt['offline'] = 'Hors ligne';
$txt['member_is_offline'] = '%1$s est hors-ligne';
$txt['pm_online'] = 'Message personnel (En ligne)';
$txt['pm_offline'] = 'Message personnel (Hors ligne)';
$txt['status'] = '�tat';

$txt['go_up'] = 'En haut';
$txt['go_down'] = 'En bas';

$forum_copyright = '<a href="' . $scripturl . '?action=credits" title="Simple Machines Forum" target="_blank" class="new_win">%1$s</a> |
<a href="http://www.simplemachines.org/about/smf/license.php" title="License" target="_blank" class="new_win">SMF &copy; 2013</a>, <a href="http://www.simplemachines.org" title="Simple Machines" target="_blank" class="new_win">Simple Machines</a>';

$txt['birthdays'] = 'Anniversaires&nbsp;:';
$txt['events'] = '�v�nements&nbsp;:';
$txt['birthdays_upcoming'] = 'Prochains anniversaires&nbsp;:';
$txt['events_upcoming'] = 'Prochains �v�nements&nbsp;:';
// Prompt for holidays in the calendar, leave blank to just display the holiday's name.
$txt['calendar_prompt'] = '';
$txt['calendar_month'] = 'Mois&nbsp;:';
$txt['calendar_year'] = 'Ann�e&nbsp;:';
$txt['calendar_day'] = 'Jour&nbsp;:';
$txt['calendar_event_title'] = 'Titre de l\'�v�nement';
$txt['calendar_event_options'] = 'Options de l\'�v�nement';
$txt['calendar_post_in'] = 'Poster dans&nbsp;:';
$txt['calendar_edit'] = 'Modifier l\'�v�nement';
$txt['calendar_export'] = 'Exporter un �v�nement';
$txt['calendar_view_week'] = 'Voir une semaine';
$txt['event_delete_confirm'] = 'Effacer cet �v�nement&nbsp;?';
$txt['event_delete'] = 'Effacer l\'�v�nement';
$txt['calendar_post_event'] = 'Poster un �v�nement';
$txt['calendar'] = 'Calendrier';
$txt['calendar_link'] = 'Lien vers le calendrier';
$txt['calendar_upcoming'] = 'Prochains �v�nements';
$txt['calendar_today'] = '�v�nements du jour';
$txt['calendar_week'] = 'Semaine';
$txt['calendar_week_title'] = 'Semaine %1$d sur %2$d';
// %1$s is the month, %2$s is the day, %3$s is the year. Change to suit your language.
$txt['calendar_week_beginning'] = 'Week beginning %1$s %2$s, %3$s';
$txt['calendar_numb_days'] = 'Nombre de jours&nbsp;:';
$txt['calendar_how_edit'] = 'comment modifier ces �v�nements&nbsp;?';
$txt['calendar_link_event'] = 'Lier vers l\'�v�nement&nbsp;:';
$txt['calendar_confirm_delete'] = 'Voulez-vous vraiment supprimer cet �v�nement&nbsp;?';
$txt['calendar_linked_events'] = '�v�nements li�s';
$txt['calendar_click_all'] = 'cliquer pour voir tous les %1$s';

$txt['movetopic_change_subject'] = 'Change the topic\'s subject';
$txt['movetopic_new_subject'] = 'New subject';
$txt['movetopic_change_all_subjects'] = 'Change every message\'s subject';
$txt['move_topic_unapproved_js'] = 'Attention ! Ce sujet n\\\'a pas encore �t� approuv�.\\n\\nIl n\\\'est pas recommand� de cr�er un message de redirection � moins que vous n\\\'ayez l\\\'intention d\\\'approuver le message imm�diatement apr�s le d�placement.';
$txt['movetopic_auto_board'] = '[SECTION]';
$txt['movetopic_auto_topic'] = '[LIEN DE LA DISCUSSION]';
$txt['movetopic_default'] = 'Ce sujet a �t� d�plac� vers ' . $txt['movetopic_auto_board'] . ".\n\n" . $txt['movetopic_auto_topic'];
$txt['movetopic_redirect'] = 'Rediriger vers le sujet d�plac�';

$txt['post_redirection'] = 'Post a redirection topic';
$txt['redirect_topic_expires'] = 'Automatically remove the redirection topic';
$txt['mergetopic_redirect'] = 'Redirect to the merged topic';
$txt['merge_topic_unapproved_js'] = 'Warning! This topic has not yet been approved.\\n\\nIt is not recommended that you create a redirection topic unless you intend to approve the post immediately following the merge.';

$txt['theme_template_error'] = 'Impossible de charger le mod�le \'%1$s\'.';
$txt['theme_language_error'] = 'Impossible de charger le fichier de langues \'%1$s\'.';

$txt['sub_boards'] = 'Sub-Boards';
$txt['restricted_board'] = 'Restricted Board';

$txt['smtp_no_connect'] = '�chec de connexion au serveur SMTP';
$txt['smtp_port_ssl'] = 'Le port SMTP est incorrect. Il doit �tre mis � 465 pour un serveur SSL.';
$txt['smtp_bad_response'] = 'Erreur en r�ception des codes de r�ponse du serveur mail';
$txt['smtp_error'] = 'Incident survenu lors de l\'envoi d\'e-mail. Erreur&nbsp;: ';
$txt['mail_send_unable'] = 'Impossible d\'envoyer un e-mail � l\'adresse \'%1$s\'.';

$txt['mlist_search'] = 'Rechercher des membres';
$txt['mlist_search_again'] = 'Chercher � nouveau';
$txt['mlist_search_filter'] = 'Options de recherche';
$txt['mlist_search_email'] = 'Recherche par adresse e-mail';
$txt['mlist_search_messenger'] = 'Recherche par identifiant Messenger';
$txt['mlist_search_group'] = 'Recherche par rang';
$txt['mlist_search_name'] = 'Recherche par pseudonyme';
$txt['mlist_search_website'] = 'Recherche par site web';
$txt['mlist_search_results'] = 'R�sultats de la recherche pour';
$txt['mlist_search_by'] = 'Rechercher par %1$s';
$txt['mlist_menu_view'] = 'Voir la liste des membres';

$txt['attach_downloaded'] = 't�l�charg�';
$txt['attach_viewed'] = 'vu';

$txt['settings'] = 'Param�tres';
$txt['never'] = 'Jamais';
$txt['more'] = 'plus';

$txt['hostname'] = 'Nom d\'h�te';
$txt['you_are_post_banned'] = 'D�sol� %1$s, vous n\'avez plus le droit de poster ou d\'envoyer des messages personnels sur ce forum.';
$txt['ban_reason'] = 'Raison';

$txt['tables_optimized'] = 'Tables de donn�es optimis�es';

$txt['add_poll'] = 'Ajouter un sondage';
$txt['poll_options6'] = 'Vous ne pouvez pas choisir plus de %1$s options.';
$txt['poll_remove'] = 'Retirer le sondage';
$txt['poll_remove_warn'] = '�tes-vous s�r de vouloir retirer ce sondage du sujet&nbsp;?';
$txt['poll_results_expire'] = 'Les r�sultats seront affich�s � la cl�ture du vote';
$txt['poll_expires_on'] = 'Cl�ture du vote';
$txt['poll_expired_on'] = 'Vote cl�tur�';
$txt['poll_change_vote'] = 'Retirer mon vote';
$txt['poll_return_vote'] = 'Options de vote';
$txt['poll_cannot_see'] = 'Vous ne pouvez pas voir les r�sultats de ce sondage pour le moment.';

$txt['quick_mod_approve'] = 'Approuver la s�lection';
$txt['quick_mod_remove'] = 'Supprimer la s�lection';
$txt['quick_mod_lock'] = '(D�)bloquer la s�lection';
$txt['quick_mod_sticky'] = '(D)�pingler la s�lection';
$txt['quick_mod_move'] = 'D�placer la s�lection vers';
$txt['quick_mod_merge'] = 'Fusionner la s�lection';
$txt['quick_mod_markread'] = 'Marquer la s�lection comme lue';
$txt['quick_mod_markunread'] = 'Mark selected unread';
$txt['quick_mod_selected'] = 'With the selected options do';
$txt['quick_mod_go'] = ' Allons-y&nbsp;! ';
$txt['quickmod_confirm'] = '�tes-vous s�r de vouloir faire cela ?';

$txt['spell_check'] = 'V�rification orthographique';

$txt['quick_reply'] = 'R�ponse Rapide';
$txt['quick_reply_desc'] = 'Dans la <em>R�ponse Rapide</em>, vous pouvez utiliser du BBCode et des smileys comme sur un message normal, mais � partir d\'une interface plus rapide d\'acc�s.';
$txt['quick_reply_warning'] = 'Attention, ce sujet est actuellement bloqu� ! Seuls les administrateurs et les mod�rateurs peuvent y r�pondre.';
$txt['quick_reply_verification'] = 'Apr�s avoir soumis votre message, vous serez redirig� vers la page habituelle d\'�criture pour v�rifier votre message %1$s.';
$txt['quick_reply_verification_guests'] = '(requis pour tous les invit�s)';
$txt['quick_reply_verification_posts'] = '(requis pour tous les utilisateurs ayant moins de %1$d messages)';
$txt['wait_for_approval'] = 'Note&nbsp;: ce message ne s\'affichera qu\'apr�s avoir �t� approuv� par un mod�rateur.';

$txt['notification_enable_board'] = '�tes-vous s�r de vouloir activer la notification des nouveaux sujets pour cette section&nbsp;?';
$txt['notification_disable_board'] = '�tes-vous s�r de vouloir d�sactiver la notification des nouveaux sujets pour cette section&nbsp;?';
$txt['notification_enable_topic'] = '�tes-vous s�r de vouloir activer la notification des nouvelles r�ponses pour ce sujet&nbsp;?';
$txt['notification_disable_topic'] = '�tes-vous s�r de vouloir d�sactiver la notification des nouvelles r�ponses pour ce sujet&nbsp;?';

// Mentions
$txt['mentions'] = 'Mentions';

// Likes
$txt['likes'] = 'Likes';
$txt['like'] = 'Like';
$txt['unlike'] = 'Unlike';
$txt['like_success'] = 'Your content was successfully liked.';
$txt['like_delete'] = 'Your content was successfully deleted.';
$txt['like_insert'] = 'Your content was successfully inserted.';
$txt['like_error'] = 'There was an error with your request.';
$txt['like_disable'] = 'Likes feature is disabled.';
$txt['not_valid_like_type'] = 'The liked type is not a valid type.';
// Translators, if you need to make more strings to suit your language, e.g. $txt['likes_2'] = 'Two people like this', please do so.
$txt['likes_1'] = '<a href="%1$s">%2$s person</a> likes this.';
$txt['likes_n'] = '<a href="%1$s">%2$s people</a> like this.';
$txt['you_likes_0'] = 'You like this.';
$txt['you_likes_1'] = 'You and <a href="%1$s">%2$s other person</a> like this.';
$txt['you_likes_n'] = 'You and <a href="%1$s">%2$s other people</a> like this.';

$txt['report_to_mod'] = 'Signaler au mod�rateur';
$txt['report_profile'] = 'Report profile of %1$s';

$txt['unread_topics_visit'] = 'Discussions r�centes non lues';
$txt['unread_topics_visit_none'] = 'Aucun sujet non lu trouv� depuis votre derni�re visite.  <a href="' . $scripturl . '?action=unread;all">Cliquez ici pour lire tous les sujets non lus</a>.';
$txt['unread_topics_all'] = 'Tous les sujets non lus';
$txt['unread_replies'] = 'Sujets mis � jour';

$txt['who_title'] = 'Qui est en ligne';
$txt['who_and'] = ' et ';
$txt['who_viewing_topic'] = ' sur ce sujet';
$txt['who_viewing_board'] = ' dans cette section.';
$txt['who_member'] = 'Membre';

// No longer used by default theme, but for backwards compat
$txt['powered_by_php'] = 'Propuls� par PHP';
$txt['powered_by_mysql'] = 'Propuls� par MySQL';
$txt['valid_css'] = 'CSS valide&nbsp;!';

// Current footer strings, no longer used
$txt['valid_html'] = 'HTML 4.01 valide&nbsp;!';
$txt['valid_xhtml'] = 'XHTML 1.0 valide&nbsp;!';
$txt['wap2'] = 'WAP2';
$txt['rss'] = 'Flux RSS';
$txt['atom'] = 'Atom';
$txt['xhtml'] = 'XHTML';
$txt['html'] = 'HTML';

$txt['guest'] = 'Invit�';
$txt['guests'] = 'Invit�s';
$txt['user'] = 'Membre';
$txt['users'] = 'Membres';
$txt['hidden'] = 'Cach�';
// Plural form of hidden for languages other than English
$txt['hidden_s'] = 'Cach�';
$txt['buddy'] = 'Ami';
$txt['buddies'] = 'Amis';
$txt['most_online_ever'] = 'Record de connexions absolu';
$txt['most_online_today'] = 'Record de connexions aujourd\'hui';

$txt['merge_select_target_board'] = 'Choisir la section de destination pour le sujet fusionn�';
$txt['merge_select_poll'] = 'Choisir quel sondage le sujet fusionn� poss�dera';
$txt['merge_topic_list'] = 'Choisir les sujets � fusionner';
$txt['merge_select_subject'] = 'Choisir le titre du sujet fusionn�';
$txt['merge_custom_subject'] = 'Titre personnel';
$txt['merge_include_notifications'] = 'Inclure les notifications&nbsp;?';
$txt['merge_check'] = 'Fusionner&nbsp;?';
$txt['merge_no_poll'] = 'Pas de sondage';
$txt['merge_why'] = 'Please entere a brief description as to<br>why these topics are being merged.';
$txt['merged_subject'] = '[MERGED] %1$s';
$txt['mergetopic_default'] = 'This topic has been merged into ' . $txt['movetopic_auto_topic'] . '.';

$txt['response_prefix'] = 'Re&nbsp;: ';
$txt['current_icon'] = 'Ic�ne actuelle';
$txt['message_icon'] = 'Ic�ne du message';

$txt['smileys_current'] = 'Jeu de smileys actuel';
$txt['smileys_none'] = 'Pas de smileys';
$txt['smileys_forum_board_default'] = 'D�faut du forum / de la section';

$txt['search_results'] = 'R�sultats de la recherche';
$txt['search_no_results'] = 'D�sol�, aucune correspondance trouv�e';

$txt['totalTimeLogged2'] = ' jours, ';
$txt['totalTimeLogged3'] = ' heures et ';
$txt['totalTimeLogged4'] = ' minutes.';
$txt['totalTimeLogged5'] = 'j ';
$txt['totalTimeLogged6'] = 'h ';
$txt['totalTimeLogged7'] = 'm';

$txt['approve_members_waiting'] = 'Membres en attente d\'approbation.';

$txt['notifyboard_turnon'] = 'Voulez-vous recevoir un e-mail de notification quand quelqu\'un poste un nouveau sujet dans cette section&nbsp;?';
$txt['notifyboard_turnoff'] = '�tes-vous s�r de vouloir d�sactiver les e-mails de notification des nouveaux sujet pour cette section&nbsp;?';

$txt['activate_code'] = 'Votre code d\'activation est';

$txt['find_members'] = 'Trouver des membres';
$txt['find_username'] = 'Identifiant, pseudonyme ou adresse e-mail';
$txt['find_buddies'] = 'Ne montrer que les amis';
$txt['find_wildcards'] = 'Jokers autoris�s&nbsp;: *, ?';
$txt['find_no_results'] = 'Pas de r�sultat';
$txt['find_results'] = 'R�sultats';
$txt['find_close'] = 'Fermer';

$txt['unread_since_visit'] = 'Messages non lus depuis votre derni�re visite.';
$txt['show_unread_replies'] = 'R�ponses � vos messages.';

$txt['change_color'] = 'Changer de couleur';

$txt['quickmod_delete_selected'] = 'Effacer la s�lection';
$txt['quickmod_split_selected'] = 'Division s�lectionn�e';

$txt['show_personal_messages_heading'] = 'Nouveaux messages';
$txt['show_personal_messages'] = 'Vous avez re�u un ou plusieurs nouveaux messages personnels.\\nVoulez-vous ouvrir une nouvelle fen�tre pour les consulter ?';

$txt['help_popup'] = 'Un peu perdu? Laissez moi vous expliquer:';

$txt['previous_next_back'] = '&laquo; pr�c�dent';
$txt['previous_next_forward'] = 'suivant &raquo;';

$txt['mark_unread'] = 'Marquer non lu';

$txt['ssi_not_direct'] = 'Veuillez ne pas acc�der directement � SSI.php par l\'URL; utilisez plut�t le chemin (%1$s) ou ajoutez ?ssi_function=quelquechose.';
$txt['ssi_session_broken'] = 'SSI.php n\'a pas pu charger une session&nbsp;! Cela peut causer des probl�mes pour la d�connexion et d\'autres fonctions - veuillez vous assurer que SSI.php est inclus avant *tout* le reste de votre code dans vos scripts&nbsp;!';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['preview_title'] = 'Pr�visualiser le message';
$txt['preview_fetch'] = 'Chargement de la pr�visualisation&hellip;';
$txt['preview_new'] = 'Nouveau message';
$txt['pm_error_while_submitting'] = 'La ou les erreurs suivantes se sont produites pendant l\'envoi de ce message personnel:';
$txt['error_while_submitting'] = 'L\'erreur ou les erreurs suivantes sont apparues durant la soumission de ce message&nbsp;:';
$txt['error_old_topic'] = 'Attention, il n\'y a pas eu de r�ponse � ce sujet depuis au moins %1$d jours.<br />� moins que vous ne soyez s�r de vouloir r�pondre, pensez �ventuellement � cr�er un nouveau sujet.';

$txt['split_selected_posts'] = 'Messages s�lectionn�s';
$txt['split_selected_posts_desc'] = 'Les messages suivants formeront un nouveau sujet apr�s la scission.';
$txt['split_reset_selection'] = 'recommencer la s�lection';

$txt['modify_cancel'] = 'Annuler';
$txt['mark_read_short'] = 'Marquer lu';

$txt['alerts'] = 'Alerts';

$txt['pm_short'] = 'Messagerie';
$txt['pm_menu_read'] = 'Lire mes messages';
$txt['pm_menu_send'] = 'Envoyer un message';

$txt['unapproved_posts'] = 'Messages non approuv�s (Sujets: %1$d, Messages: %2$d)';

$txt['ajax_in_progress'] = 'Chargement...';

$txt['mod_reports_waiting'] = 'Il y a actuellement %1$d rapport(s) de mod�rateur ouvert(s).';

$txt['view_unread_category'] = 'Messages non lus';
$txt['new_posts_in_category'] = 'Cliquez pour voir les nouveaux messages dans %1$s';
$txt['verification'] = 'V�rification';
$txt['visual_verification_hidden'] = 'Please leave this box empty';
$txt['visual_verification_description'] = 'Taper les lettres montr�es dans l\'image';
$txt['visual_verification_sound'] = 'Ecouter les lettres';
$txt['visual_verification_request_new'] = 'Demander une autre image';

// Sub menu labels
$txt['summary'] = 'R�sum�';
$txt['account'] = 'Param�tres du Compte';
$txt['theme'] = 'Affichage et disposition';
$txt['forumprofile'] = 'Profil de base et avatar';
$txt['activate_changed_email_title'] = 'Email Address Changed';
$txt['activate_changed_email_desc'] = 'You\'ve changed your email address. In order to validate this address you will receive an email. Click the link in that email to reactivate your account.';
$txt['modSettings_title'] = 'Fonctionnalit�s et Options';
$txt['package'] = 'Gestionnaire de paquets';
$txt['errlog'] = 'Journal d\'Erreurs';
$txt['edit_permissions'] = 'Permissions';
$txt['mc_unapproved_attachments'] = 'Fichiers joints non approuv�s';
$txt['mc_unapproved_poststopics'] = 'Messages et Sujets non approuv�s';
$txt['mc_reported_posts'] = 'Messages rapport�s';
$txt['mc_reported_members'] = 'Reported Members';
$txt['modlog_view'] = 'Journal de Mod�ration';
$txt['calendar_menu'] = 'Voir le calendrier';

// @todo Send email strings - should move?
$txt['send_email'] = 'Envoyer un e-mail';
$txt['send_email_disclosed'] = 'Notez que ce sera visible pour le destinataire.';

$txt['ignoring_user'] = 'Vous ignorez cet utilisateur.';
$txt['show_ignore_user_post'] = 'Me montrer le message.';

$txt['spider'] = 'Robot';
$txt['spiders'] = 'Robots';

$txt['downloads'] = 'T�l�chargements';
$txt['filesize'] = 'Taille de fichier';

// Restore topic
$txt['restore_topic'] = 'Restaurer ce sujet';
$txt['restore_message'] = 'Restaurer ce message';
$txt['quick_mod_restore'] = 'Restaurer les messages s�lectionn�s';

// Editor prompt.
$txt['prompt_text_email'] = 'Indiquez l\'adresse e-mail.';
$txt['prompt_text_ftp'] = 'Indiquez l\'adresse FTP.';
$txt['prompt_text_url'] = 'Indiquez l\'adresse Web du lien.';
$txt['prompt_text_img'] = 'Indiquez l\'adresse Web de l\'image';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['autosuggest_delete_item'] = 'Supprimer cet �l�ment';

// Debug related - when $db_show_debug is true.
$txt['debug_templates'] = 'Mod�les - ';
$txt['debug_subtemplates'] = 'Sous-mod�les - ';
$txt['debug_language_files'] = 'Fichiers de langue - ';
$txt['debug_stylesheets'] = 'Feuilles de style - ';
$txt['debug_files_included'] = 'Fichiers inclus - ';
$txt['debug_memory_use'] = 'Memory used: ';
$txt['debug_kb'] = 'Ko';
$txt['debug_show'] = 'montrer';
$txt['debug_cache_hits'] = 'Appel au cache - ';
$txt['debug_cache_seconds_bytes'] = '%1$ss - %2$s octets';
$txt['debug_cache_seconds_bytes_total'] = '%1$ss pour %2$s octets';
$txt['debug_queries_used'] = 'Requ�tes utilis�es&nbsp;: %1$d.';
$txt['debug_queries_used_and_warnings'] = 'Requ�tes utilis�es&nbsp;: %1$d, %2$d avertissements.';
$txt['debug_query_in_line'] = 'Appel�e dans <em>%1$s</em>, � la ligne <em>%2$s</em>, ';
$txt['debug_query_which_took'] = 'et ex�cut�e en %1$s secondes.';
$txt['debug_query_which_took_at'] = 'et ex�cut�e en %1$s secondes, � %2$s secondes du lancement du script.';
$txt['debug_show_queries'] = '[Montrer les Requ�tes]';
$txt['debug_hide_queries'] = '[Cacher les Requ�tes]';
$txt['debug_tokens'] = 'Tokens:';
$txt['debug_browser'] = 'ID du Navigateur:';
$txt['debug_hooks'] = 'Crochets appel�s:';
$txt['debug_instances'] = 'Instances created: ';
$txt['are_sure_mark_read'] = 'Are you sure you want to mark messages as read?';

// Accessibility
$txt['hide_category'] = 'Hide Category';
$txt['show_category'] = 'Show Category';
$txt['hide_infocenter'] = 'Hide Info Center';
$txt['show_infocenter'] = 'Show Info Center';

// Notification post control
$txt['notify_topic_0'] = 'Not Following';
$txt['notify_topic_1'] = 'No Alerts or Emails';
$txt['notify_topic_2'] = 'Receive Alerts';
$txt['notify_topic_3'] = 'Receive Emails and Alerts';
$txt['notify_topic_0_desc'] = 'You will not receive any emails or alerts for this post and it will also not show up in your unread replies and topics list. You will still receive @mentions for this topic.';
$txt['notify_topic_1_desc'] = 'You will not receive any emails or alerts but only @mentions by other members.';
$txt['notify_topic_2_desc'] = 'You will receive alerts for this topic.';
$txt['notify_topic_3_desc'] = 'You will receive both alerts and e-mails for this topic.';
$txt['notify_board_1'] = 'No Alerts or Emails';
$txt['notify_board_2'] = 'Receive Alerts';
$txt['notify_board_3'] = 'Receive Emails and Alerts';
$txt['notify_board_1_desc'] = 'You will not receive any emails or alerts for new topics';
$txt['notify_board_2_desc'] = 'You will receive alerts for this board.';
$txt['notify_board_3_desc'] = 'You will receive both alerts and e-mails for this board.';

?>