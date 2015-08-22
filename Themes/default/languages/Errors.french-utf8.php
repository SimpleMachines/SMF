<?php
// Version: 2.1 Beta 2; Errors

global $scripturl, $modSettings;

$txt['no_access'] = 'Vous n\'êtes pas autorisé à accéder à cette section';
$txt['not_found'] = 'Sorry, this section isn\'t available at this time.';
$txt['wireless_error_notyet'] = 'Désolé, cette action n\'est pas disponible en mode mobile pour l\'instant.';

$txt['mods_only'] = 'Seuls les modérateurs peuvent utiliser la fonction d\'effacement direct, vous pouvez effacer ce message par la fonction Modifier.';
$txt['no_name'] = 'Vous n\'avez pas rempli le champ IDENTIFIANT. Il est requis.';
$txt['no_email'] = 'Vous n\'avez pas rempli le champ e-mail. Il est requis.';
$txt['topic_locked'] = 'Ce sujet est bloqué, vous n\'êtes pas autorisé à poster ou modifier un message ici...';
$txt['no_password'] = 'Champ MOT DE PASSE vide';
$txt['already_a_user'] = 'L\'identifiant ou pseudonyme que vous essayez d\'utiliser existe déjà.';
$txt['cant_move'] = 'Vous n\'êtes pas autorisé à déplacer les sujets.';
$txt['login_to_post'] = 'Pour poster, vous devez être identifié. Si vous n\'avez pas encore de compte, <a href="' . $scripturl . '?action=register">inscrivez-vous</a> maintenant.';
$txt['passwords_dont_match'] = 'Les mots de passe diffèrent.';
$txt['register_to_use'] = 'Désolé, vous devez vous inscrire avant d\'utiliser cette fonction.';
$txt['password_invalid_character'] = 'Caractère invalide dans le mot de passe.';
$txt['name_invalid_character'] = 'Caractère invalide dans l\'identifiant / pseudonyme.';
$txt['email_invalid_character'] = 'Caractère invalide dans l\'adresse e-mail.';
$txt['username_reserved'] = 'L\'identifiant ou pseudonyme que vous essayez d\'utiliser contient le nom réservé \'%1$s\'. Merci d\'essayer une autre alternative.';
$txt['numbers_one_to_nine'] = 'Ce champ n\'accepte que des chiffres de 0-9';
$txt['not_a_user'] = 'L\'utilisateur dont vous essayez de consulter le profil n\'existe pas.';
$txt['not_a_topic'] = 'Ce sujet n\'existe pas dans ce forum.';
$txt['email_in_use'] = 'Cette adresse e-mail (%1$s) est déjà utilisée par un membre inscrit. Si vous pensez que c\'est une erreur, allez sur la page de connexion et demandez le rappel de votre mot de passe en indiquant cette adresse.';
$txt['attachments_no_write'] = 'Le dossier de destination des fichiers joints est en lecture seule.  Votre fichier joint ou avatar ne peut pas être sauvegardé.';

$txt['didnt_select_vote'] = 'Vous n\'avez pas choisi d\'option de vote.';
$txt['poll_error'] = 'Soit ce sondage n\'existe pas, soit il est bloqué, soit vous avez essayé de voter 2 fois.';
$txt['members_only'] = 'Cette option est réservée aux membres...';
$txt['locked_by_admin'] = 'Bloqué par un administrateur. Vous ne pouvez pas le débloquer.';
$txt['feature_disabled'] = 'Désolé, cette fonction est désactivée.';
$txt['feature_no_exists'] = 'Désolé, cette fonction n\'existe pas.';
$txt['couldnt_connect'] = 'Échec de la connexion au serveur, ou fichier non trouvé';
$txt['no_board'] = 'La section spécifiée est introuvable';
$txt['no_message'] = 'Le message n\'est plus disponible';
$txt['cant_split'] = 'Vous n\'êtes pas autorisé à séparer des sujets';
$txt['cant_merge'] = 'Vous n\'êtes pas autorisé à fusionner des sujets';
$txt['no_topic_id'] = 'ID de sujet invalide.';
$txt['split_first_post'] = 'Vous ne pouvez pas séparer un sujet au premier message.';
$txt['topic_one_post'] = 'Ce sujet ne contient qu\'un seul message et ne peut pas être séparé.';
$txt['no_posts_selected'] = 'Aucun message sélectionné';
$txt['selected_all_posts'] = 'Impossible de séparer. Vous avez sélectionné tous les messages.';
$txt['cant_find_messages'] = 'Messages introuvables';
$txt['cant_find_user_email'] = 'Impossible de trouver l\'adresse e-mail de l\'utilisateur.';
$txt['cant_insert_topic'] = 'Impossible d\'insérer le sujet';
$txt['already_a_mod'] = 'Vous avez choisi l\'identifiant ou pseudonyme d\'un modérateur existant. Merci de choisir autre chose.';
$txt['session_timeout'] = 'Votre session s\'est terminée pendant que vous postiez. Retournez en arrière et réessayez.';
$txt['session_verify_fail'] = 'Échec de vérification de session. Veuillez tenter de vous déconnecter et reconnecter, puis réessayez.';
$txt['verify_url_fail'] = 'Impossible de vérifier l\'adresse référente. Merci de revenir en arrière et de réessayer.';
$txt['token_verify_fail'] = 'Échec de vérification de Token. Veuillez revenir et essayer à nouveau.';
$txt['guest_vote_disabled'] = 'Les invités ne peuvent pas participer à ce sondage.';

$txt['cannot_like_content'] = 'Vous n\'avez pas les permissions pour aimer ce contenu.';
$txt['cannot_view_likes'] = 'You are not able to view who liked that content.';
$txt['cannot_access_mod_center'] = 'Vous n\'avez pas la permission d\'accéder au centre de modération.';
$txt['cannot_admin_forum'] = 'Vous n\'êtes pas autorisé à administrer ce forum.';
$txt['cannot_announce_topic'] = 'Vous n\'êtes pas autorisé à annoncer vos sujets dans cette section.';
$txt['cannot_approve_posts'] = 'Vous n\'avez pas la permission d\'approuver quoi que ce soit.';
$txt['cannot_post_unapproved_attachments'] = 'Vous n\'avez pas la permission de poster des fichiers joints non approuvés.';
$txt['cannot_post_unapproved_topics'] = 'Vous n\'avez pas la permission de poster des sujets non approuvés.';
$txt['cannot_post_unapproved_replies_own'] = 'Vous n\'avez pas la permission de poster des réponses non approuvées dans vos sujets.';
$txt['cannot_post_unapproved_replies_any'] = 'Vous n\'avez pas la permission de poster des réponses non approuvées dans les sujets des autres utilisateurs.';
$txt['cannot_calendar_edit_any'] = 'Vous ne pouvez pas modifier les événements du calendrier.';
$txt['cannot_calendar_edit_own'] = 'Vous n\'avez pas les privilèges requis pour modifier vos propres événements.';
$txt['cannot_calendar_post'] = 'Désolé, l\'ajout d\'événements n\'est pas autorisé.';
$txt['cannot_calendar_view'] = 'Désolé, mais vous n\'êtes pas autorisé à voir le calendrier.';
$txt['cannot_remove_any'] = 'Désolé, mais vous n\'avez pas les privilèges requis pour supprimer un sujet. Veuillez vérifier pour vous assurer que ce sujet n\'a pas été déplacé dans une autre section.';
$txt['cannot_remove_own'] = 'Vous ne pouvez pas effacer vos propres sujets dans ce forum. Veuillez vous assurer que ce sujet n\'a pas été déplacé dans une autre section.';
$txt['cannot_edit_news'] = 'Vous n\'êtes pas autorisé à modifier les nouvelles de ce forum.';
$txt['cannot_pm_read'] = 'Désolé, vous ne pouvez pas lire vos messages personnels.';
$txt['cannot_pm_send'] = 'Vous n\'êtes pas autorisé à envoyer des messages personnels';
$txt['cannot_lock_any'] = 'Vous n\'êtes pas autorisé à verrouiller les sujets ici.';
$txt['cannot_lock_own'] = 'Désolé, mais vous ne pouvez pas bloquer vos propres sujets ici.';
$txt['cannot_make_sticky'] = 'Vous n\'avez pas la permission d\'épingler ce sujet.';
$txt['cannot_manage_attachments'] = 'Vous n\'avez pas les autorisations requises pour gérer les avatars et fichiers joints.';
$txt['cannot_manage_bans'] = 'Vous n\'avez pas les permissions nécessaires pour modifier la liste des bannissements.';
$txt['cannot_manage_boards'] = 'Vous n\'avez pas les autorisations requises pour gérer les sections et catégories.';
$txt['cannot_manage_membergroups'] = 'Vous n\'avez pas les autorisations requises pour modifier ou assigner des groupes de membres.';
$txt['cannot_manage_permissions'] = 'Vous n\'avez pas les autorisations requises pour gérer les permissions.';
$txt['cannot_manage_smileys'] = 'Vous n\'avez pas les autorisations requises pour gérer les smileys et les icônes de messages.';
$txt['cannot_merge_any'] = 'Vous n\'êtes pas autorisé à fusionner des sujets sur une des sections sélectionnées.';
$txt['cannot_merge_redirect'] = 'One or more of the topics you have selected is a redirect topic and cannot be merged.';
$txt['cannot_moderate_forum'] = 'Vous n\'êtes pas autorisé à modérer cette section.';
$txt['cannot_moderate_board'] = 'Vous n\'êtes pas autorisé à modérer cette section.';
$txt['cannot_modify_any'] = 'Vous n\'êtes autorisé à modifier un message.';
$txt['cannot_modify_own'] = 'Désolé, mais vous n\'êtes pas autorisé à modifier vos propres messages.';
$txt['cannot_modify_replies'] = 'Bien que ce message soit une réponse à votre sujet, vous ne pouvez pas le modifier.';
$txt['cannot_move_own'] = 'Vous n\'êtes pas autorisé à déplacer vos propres sujets dans cette section.';
$txt['cannot_move_any'] = 'Vous n\'êtes pas autorisé à déplacer les sujets de cette section.';
$txt['cannot_poll_add_own'] = 'Désolé, vous n\'êtes pas autorisé à ajouter des sondages à vos propres sujets dans cette section.';
$txt['cannot_poll_add_any'] = 'Vous n\'avez pas les permissions pour ajouter un sondage dans ce sujet.';
$txt['cannot_poll_edit_own'] = 'Vous ne pouvez pas modifier ce sondage, bien que ce soit le votre.';
$txt['cannot_poll_edit_any'] = 'On vous a refusé l\'accès à la modification des sondages dans cette section.';
$txt['cannot_poll_lock_own'] = 'Vous n\'êtes pas autorisé à bloquer vos propres sondages dans cette section.';
$txt['cannot_poll_lock_any'] = 'Désolé, vous ne pouvez bloquer aucun sondage.';
$txt['cannot_poll_post'] = 'Vous n\'êtes pas autorisé à poster des sondages dans la section courante.';
$txt['cannot_poll_remove_own'] = 'Vous n\'êtes pas autorisé à supprimer ce sondage dans votre sujet.';
$txt['cannot_poll_remove_any'] = 'Vous ne pouvez retirer aucun sondage de cette section.';
$txt['cannot_poll_view'] = 'Vous n\'êtes pas autorisé à voir les sondages de cette section.';
$txt['cannot_poll_vote'] = 'Désolé, mais vous ne pouvez pas voter dans les sondages de cette section.';
$txt['cannot_post_attachment'] = 'Vous n\'avez pas la permission de poster des fichiers joints ici.';
$txt['cannot_post_new'] = 'Désolé, vous ne pouvez pas poster de nouveau sujet sur cette section.';
$txt['cannot_post_reply_any'] = 'Vous n\'êtes pas autorisé à poster des réponses aux sujets de cette section.';
$txt['cannot_post_reply_own'] = 'Vous n\'êtes pas autorisé à poster des réponses, même à vos propres sujets, dans cette section.';
$txt['cannot_post_redirect'] = 'Vous ne pouvez pas poster dans les sections de redirection.';
$txt['cannot_profile_remove_own'] = 'Désolé, mais vous n\'avez pas l\'autorisation d\'effacer votre profil.';
$txt['cannot_profile_remove_any'] = 'Vous n\'avez pas l\'autorisation de supprimer les profils des membres.';
$txt['cannot_profile_extra_any'] = 'Vous n\'êtes autorisé à modifier un paramètre du profil.';
$txt['cannot_profile_identity_any'] = 'Vous n\'êtes pas autorisé à modifier les paramètres relatifs au compte.';
$txt['cannot_profile_title_any'] = 'Vous ne pouvez pas modifier les pseudonymes des membres.';
$txt['cannot_profile_extra_own'] = 'Désolé, mais vous n\'avez pas les permissions nécessaires pour modifier votre profil.';
$txt['cannot_profile_identity_own'] = 'Vous ne pouvez pas changer votre identifiant pour l\'instant.';
$txt['cannot_profile_title_own'] = 'Vous n\'êtes pas autorisé à changer votre pseudonyme.';
$txt['cannot_profile_server_avatar'] = 'Vous n\'êtes pas autorisé à utiliser des serveurs de stockage d\'avatars.';
$txt['cannot_profile_upload_avatar'] = 'Vous n\'avez pas la permission pour transférer un avatar.';
$txt['cannot_profile_remote_avatar'] = 'Vous n\'avez pas les permissions nécessaires pour utiliser un avater distant.';
$txt['cannot_profile_view'] = 'Many apologies, but you can\'t view just any profile.';
$txt['cannot_delete_own'] = 'Vous n\'êtes pas autorisé à effacer vos propres messages dans cette section.';
$txt['cannot_delete_replies'] = 'Désolé, mais vous ne pouvez pas effacer ces messages, bien qu\'ils soient des réponses à votre sujet.';
$txt['cannot_delete_any'] = 'Effacer n\'importe quel message sur ce forum n\'est pas autorisé.';
$txt['cannot_report_any'] = 'Vous n\'êtes pas autorisé à rapporter des messages de cette section.';
$txt['cannot_search_posts'] = 'Vous n\'êtes pas autorisé à rechercher des messages dans cette section.';
$txt['cannot_send_mail'] = 'Vous n\'avez pas le privilège d\'envoyer des e-mails à quiconque.';
$txt['cannot_issue_warning'] = 'Désolé, vous n\'avez pas la permission de donner des avertissements aux utilisateurs.';
$txt['cannot_send_email_to_members'] = 'Désolé mais l\'administrateur a désactivé l\'envois d\'emails sur ce forum.';
$txt['cannot_split_any'] = 'Séparer un sujet n\'est pas autorisé dans cette section.';
$txt['cannot_view_attachments'] = 'Il semble que vous n\'êtes pas autorisé à télécharger ou voir les fichiers joints de cette section.';
$txt['cannot_view_mlist'] = 'Vous ne pouvez pas voir la liste des membres.';
$txt['cannot_view_stats'] = 'Vous ne pouvez pas voir les statistiques du forum.';
$txt['cannot_who_view'] = 'Désolé - vous n\'avez pas les permissions nécessaires pour voir la liste des membres en ligne.';

$txt['no_theme'] = 'Ce thème n\'existe pas.';
$txt['theme_dir_wrong'] = 'Le répertoire du thème par défaut est erroné, veuillez le corriger en cliquant sur ce texte.';
$txt['registration_disabled'] = 'Désolé, l\'inscription est actuellement désactivée.';
$txt['registration_agreement_missing'] = 'Le fichier d\'accord d\'inscription, agreement.txt, est soit manquant, soit vide. 
Les inscriptions ont étés désactivées jusqu\'à ce que se soit réparé.';
$txt['registration_no_secret_question'] = 'Désolé, il n\'y a aucune question secrète programmée par ce membre.';
$txt['poll_range_error'] = 'Désolé, le sondage doit être validé pour plus de 0 jour.';
$txt['delFirstPost'] = 'Vous n\'êtes pas autorisé à effacer le premier message d\'un sujet.<p>Si vous voulez effacer ce sujet, cliquez sur le lien Effacer le sujet, ou demandez à un modérateur/administrateur de le faire pour vous.</p>';
$txt['parent_error'] = 'Impossible de créer une section&nbsp;!';
$txt['login_cookie_error'] = 'Erreur de connexion.  Veuillez vérifier vos paramètres des témoins.';
$txt['login_ssl_required'] = 'You can only login via HTTPS';
$txt['register_ssl_required'] = 'You can only register via HTTPS';
$txt['incorrect_answer'] = 'Désolé, mais vous n\'avez pas répondu correctement à votre question.  Veuillez cliquer sur \'Retour\' et réessayer, ou cliquez Retour 2 fois afin d\'utiliser la méthode par défaut pour retrouver votre mot de passe.';
$txt['no_mods'] = 'Aucun modérateur trouvé&nbsp;!';
$txt['parent_not_found'] = 'La structure de la section est corrompue&nbsp;: impossible de trouver la section parente';
$txt['modify_post_time_passed'] = 'Vous ne pouvez pas modifier ce message puisque le temps limite pour les modifications est dépassé.';

$txt['calendar_off'] = 'Vous ne pouvez pas accéder au calendrier pour l\'instant car il est désactivé.';
$txt['calendar_export_off'] = 'Vous ne pouvez exporter des évènements de calendrier parce que cette fonction est actuellement désactivée.';
$txt['invalid_month'] = 'Mois invalide.';
$txt['invalid_year'] = 'Année invalide.';
$txt['invalid_day'] = 'Jour invalide.';
$txt['event_month_missing'] = 'Mois de l\'événement manquant.';
$txt['event_year_missing'] = 'Année de l\'événement manquante.';
$txt['event_day_missing'] = 'Jour de l\'événement manquant.';
$txt['event_title_missing'] = 'Titre de l\'événement manquant.';
$txt['invalid_date'] = 'Date invalide.';
$txt['no_event_title'] = 'Titre d\'événement manquant.';
$txt['missing_event_id'] = 'ID d\'événement manquant.';
$txt['cant_edit_event'] = 'Vous n\'êtes pas autorisé à modifier cet événement.';
$txt['missing_board_id'] = 'ID de section manquant.';
$txt['missing_topic_id'] = 'ID de sujet manquant.';
$txt['topic_doesnt_exist'] = 'Le sujet n\'existe pas.';
$txt['not_your_topic'] = 'Ce sujet n\'est pas le vôtre.';
$txt['board_doesnt_exist'] = 'La section n\'existe pas.';
$txt['no_span'] = 'La fonction d\'étalement est désactivée.';
$txt['invalid_days_numb'] = 'Nombre de jours d\'étalement invalide.';

$txt['moveto_noboards'] = 'Il n\'y a pas de forum où déplacer ce sujet';
$txt['topic_already_moved'] = 'Ce sujet %1$s a été déplacé dans la section %2$s, veuillez vérifier sa nouvelle localisation avant de le déplacer à nouveau.';

$txt['already_activated'] = 'Votre compte a déjà été activé.';
$txt['still_awaiting_approval'] = 'Votre compte est encore en attente d\'approbation par un administrateur.';

$txt['invalid_email'] = 'Adresse e-mail invalide.<br>Exemple d\'une adresse e-mail valide: votreemail@votrefai.com.<br>Exemple d\'une plage d\'adresses e-mail valide : *@*.votrefai.com';
$txt['invalid_expiration_date'] = 'Date d\'expiration non valide';
$txt['invalid_hostname'] = 'Nom/plage de domaine invalide.<br>Exemple de domaine valide : proxy4.grosmechantloup.com<br>Exemple de plage de domaine valide: *.grosmechantloup.com';
$txt['invalid_ip'] = 'IP ou plage d\'IP non valide.<b>Exemple d\'une adresse IP valide : 127.0.0.1<br>Exemple d\'une plage d\'adresses IP valide : 127.0.0-20.*';
$txt['invalid_tracking_ip'] = 'Adresse IP ou bloc d\'adresses IP invalide.<br>Exemple d\'adresse IP valide: 127.0.0.1<br>Exemple de bloc valide: 127.0.0.*';
$txt['invalid_username'] = 'Identifiant du membre introuvable';
$txt['no_user_selected'] = 'Membre non trouvé';
$txt['no_ban_admin'] = 'Vous ne pouvez pas bannir un administrateur - vous devez d\'abord le rétrograder&nbsp;!';
$txt['no_bantype_selected'] = 'Type de bannissement non sélectionné';
$txt['ban_not_found'] = 'Bannissement introuvable';
$txt['ban_unknown_restriction_type'] = 'Type de restriction inconnu';
$txt['ban_name_empty'] = 'Le nom du bannissement a été laissé vide';
$txt['ban_id_empty'] = 'Ban id not found';
$txt['ban_no_triggers'] = 'No ban triggers specified';
$txt['ban_ban_item_empty'] = 'Ban trigger not found';
$txt['impossible_insert_new_bangroup'] = 'An error occurred while inserting the new ban';

$txt['ban_name_exists'] = 'Le nom de ce bannissement (%1$s) existe déjà. Veuillez choisir un autre nom.';
$txt['ban_trigger_already_exists'] = 'Le déclencheur de bannissement %1$s existe déjà dans %2$s.';

$txt['recycle_no_valid_board'] = 'Aucune section valide n\'a été choisie pour stocker les sujets recyclés';
$txt['post_already_deleted'] = 'Ce sujet ou message a déjà été déplacé dans la section de recyclage. Etes vous certain de vouloir le supprimer définitivement?<br>Si oui, suivez <a href="%1$s">ce lien</a>';

$txt['login_threshold_fail'] = 'Désolé, nombre de tentatives de connexion dépassé. Revenez essayer plus tard.';
$txt['login_threshold_brute_fail'] = 'Désolé, nombre de tentatives de connexion dépassé. Patientez 30 secondes et réessayez.';

$txt['who_off'] = 'La liste des membres en ligne est indisponible pour le moment.';

$txt['merge_create_topic_failed'] = 'Erreur pendant la création du nouveau sujet.';
$txt['merge_need_more_topics'] = 'Fusionner des sujets nécessite au moins 2 sujets...';

$txt['post_WaitTime_broken'] = 'Le dernier envois de message depuis votre adresse IP est de moins de %1$d secondes. Veuillez essayer à nouveau plus tard.';
$txt['register_WaitTime_broken'] = 'Vous vous êtes déjà enregistré il y a seulement %1$d secondes!';
$txt['login_WaitTime_broken'] = 'Vous devrez attendre environ %1$d secondes pour vous identifier à nouveau, désolé.';
$txt['pm_WaitTime_broken'] = 'Le dernier message personnel envoyé depuis votre adresse IP est de moins de %1$d secondes. Veuillez réessayer plus tard.';
$txt['reporttm_WaitTime_broken'] = 'Le dernier sujet rapporté depuis votre adresse IP est de moins de %1$d secondes. Veuillez réessayer à nouveau plus tard.';
$txt['sendmail_WaitTime_broken'] = 'Le dernier email posté depuis votre adresse IP date d\'il y a moins de %1$d secondes. Veuillez réessayer plus tard.';
$txt['search_WaitTime_broken'] = 'Votre dernière recherche date d\'il y a moins de %1$d secondes. Veuillez réessayer plus tard.';
$txt['remind_WaitTime_broken'] = 'Votre dernier avertissement date d\'il y a moins de %1$d secondes. Veuillez réessayer plus tard.';

$txt['email_missing_data'] = 'Vous devez entrer quelque chose à la fois dans le champ &quot;titre&quot; et la boîte de texte.';

$txt['topic_gone'] = 'Le sujet ou la section que vous recherchez à l\'air d\'être manquant ou inaccessible pour vous.';
$txt['theme_edit_missing'] = 'Le fichier que vous essayez de modifier... est introuvable&nbsp;!';

$txt['no_dump_database'] = 'Seul les administrateurs peuvent faire des copies de sauvegarde des bases de données&nbsp;!';
$txt['pm_not_yours'] = 'Le message personnel que vous tentez de citer n\'est pas de vous ou n\'existe pas, retournez d\'où vous venez et réessayez.';
$txt['mangled_post'] = 'Données du formulaire erronées - veuillez retourner à la page précédente et réessayer.';
$txt['too_many_groups'] = 'Désolé, vous avez sélectionné trop de groupes, veuillez en retirer.';
$txt['post_upload_error'] = 'Les données du message sont manquantes. Cette erreur peut être causée en essayant de soumettre un dossier plus grand que la taille autorisée par le serveur. S\'il vous plaît contactez votre administrateur si ce problème persiste.';
$txt['quoted_post_deleted'] = 'Le message que vous essayez de citer, soit n\'existe pas, soit à été supprimé, ou n\'est plus accessible pour vous.';
$txt['pm_too_many_per_hour'] = 'Vous avez dépassé la limite de %1$d messages personnels par heure.';

$txt['register_only_once'] = 'Désolé, mais vous ne pouvez pas inscrire plus d\'un compte en même temps depuis le même ordinateur.';
$txt['admin_setting_coppa_require_contact'] = 'Si l\'approbation d\'un parent/tuteur est requise, vous devez entrer un moyen de contact soit postal, soit par fax.';

$txt['error_long_name'] = 'Le pseudonyme que vous avez tenté d\'utiliser est trop long.';
$txt['error_no_name'] = 'Aucun pseudonyme n\'a été fourni.';
$txt['error_bad_name'] = 'Ce pseudonyme ne peut être utilisé, car c\'est un mot réservé, ou il le contient.';
$txt['error_no_email'] = 'Aucune adresse e-mail n\'a été indiquée.';
$txt['error_bad_email'] = 'Une adresse e-mail invalide a été indiquée.';
$txt['error_no_event'] = 'Aucun nom d\'événement n\'a été donné.';
$txt['error_no_subject'] = 'Aucun titre de sujet n\'a été indiqué.';
$txt['error_no_question'] = 'Aucune question n\'a été posée pour ce sondage.';
$txt['error_no_message'] = 'Le corps du message est vide.';
$txt['error_long_message'] = 'Le message dépasse la limite de caractères autorisée (%1$d caractères permis).';
$txt['error_no_comment'] = 'Le champ de commentaire est vide.';
// duplicate of post_too_long in Post.{language}.php
$txt['error_post_too_long'] = 'Votre message est trop long. Veuillez revenir en arrière et essayer à nouveau.';
$txt['error_session_timeout'] = 'Votre session s\'est terminée alors que vous postiez. Veuillez tenter de resoumettre votre message.';
$txt['error_no_to'] = 'Aucun destinataire spécifié.';
$txt['error_bad_to'] = 'Un ou plusieurs destinataires \'À\' n\'ont pu être trouvés.';
$txt['error_bad_bcc'] = 'Un ou plusieurs destinataires \'Bcc\' n\'ont pu être trouvés.';
$txt['error_form_already_submitted'] = 'Vous avez déjà soumis ce message&nbsp;!  Vous avez sans doute accidentellement double-cliqué sur le bouton de soumission, ou vous avez rafraîchi la page.';
$txt['error_poll_few'] = 'Vous devez avoir au moins deux (2) choix&nbsp;!';
$txt['error_poll_many'] = 'Vous ne pouvez avoir plus de 256 choix.';
$txt['error_need_qr_verification'] = 'Merci de remplir le formulaire de vérification ci-dessous avant d\'envoyer votre message.';
$txt['error_wrong_verification_code'] = 'Les lettres que vous avez tapées ne correspondent pas aux lettres montrées sur l\'image.';
$txt['error_wrong_verification_answer'] = 'Vous n\'avez pas répondu aux questions de vérification correctement.';
$txt['error_need_verification_code'] = 'Veuillez entrer le code de vérification ci-dessous pour passer aux résultats.';
$txt['error_bad_file'] = 'Désolé mais le fichier spécifié n\'a pas pu être ouvert&nbsp;: %1$s';
$txt['error_bad_line'] = 'La ligne indiquée est invalide.';
$txt['error_draft_not_saved'] = 'Une erreur est intervenue pendant l\'enregistrement du brouillon';

$txt['smiley_not_found'] = 'Smiley introuvable.';
$txt['smiley_has_no_code'] = 'Aucun code pour ce smiley n\'a été donné.';
$txt['smiley_has_no_filename'] = 'Aucun fichier pour ce smiley n\'a été donné.';
$txt['smiley_not_unique'] = 'Un smiley ayant ce code existe déjà.';
$txt['smiley_set_already_exists'] = 'Un jeu de smileys ayant cette URL existe déjà.';
$txt['smiley_set_not_found'] = 'Jeu de smileys introuvable.';
$txt['smiley_set_dir_not_found'] = 'Le répertoire du jeu de smileys suivant  %1$s est soit invalide ou interdit d\'accès.';
$txt['smiley_set_path_already_used'] = 'L\'URL de ce jeu de smileys est déjà utilisée par un autre jeu.';
$txt['smiley_set_unable_to_import'] = 'Impossible d\'importer le jeu de smileys. Le répertoire est soit invalide, soit interdit d\'accès.';

$txt['smileys_upload_error'] = 'Échec lors du chargement du fichier.';
$txt['smileys_upload_error_blank'] = 'Tous les jeux de smileys doivent avoir une image&nbsp;!';
$txt['smileys_upload_error_name'] = 'Tous les smileys doivent avoir les mêmes noms de fichier&nbsp;!';
$txt['smileys_upload_error_illegal'] = 'Format d\'image interdit.';

$txt['search_invalid_weights'] = 'La pertinence des recherches n\'est pas bien configurée. Au moins un dispositif devrait être configuré afin d\'être différent de zéro.  Veuillez rapporter cette erreur à l\'administrateur.';
$txt['unable_to_create_temporary'] = 'L\'outil de recherche a été incapable de créer des tables temporaires.  Veuillez réessayer.';

$txt['package_no_file'] = 'Impossible de trouver le fichier de paquet&nbsp;!';
$txt['packageget_unable'] = 'Impossible de se connecter au serveur. Veuillez réessayer en utilisant plutôt <a href="%1$s" target="_blank" class="new_win">cette URL</a>.';
$txt['not_on_simplemachines'] = 'Désolé, les paquets ne peuvent être téléchargés que de cette façon depuis le serveur simplemachines.org.';
$txt['package_cant_uninstall'] = 'Ce paquet n\'a jamais été installé ou a déjà été désinstallé - vous ne pouvez pas le désinstaller maintenant.';
$txt['package_cant_download'] = 'Vous ne pouvez pas installer ou télécharger de paquets parce que le répertoire /Packages ou un de ses fichiers est bloqué en écriture&nbsp;!';
$txt['package_upload_error_nofile'] = 'Vous n\'avez pas choisi de paquet à envoyer.';
$txt['package_upload_error_failed'] = 'Échec d\'envoi du paquet. Veuillez vérifier les droits d\'accès du répertoire&nbsp;!';
$txt['package_upload_error_exists'] = 'Le fichier que vous transférez existe déjà sur le serveur. Veuillez tout d\'abord le supprimer, puis réessayer.';
$txt['package_upload_error_supports'] = 'Le gestionnaire de paquets permet actuellement uniquement ces types de fichier&nbsp;: %1$s.';
$txt['package_upload_error_broken'] = 'Le paquet n\'a pas pu être téléchargé pour la raison suivante:<br>&quot;%1$s&quot;';
$txt['package_theme_upload_error_broken'] = 'Theme upload failed due to the following error:<br>&quot;%1$s&quot;';

$txt['package_get_error_not_found'] = 'Impossible de trouver le paquet que vous essayez d\'installer. Essayez d\'uploader le paquet manuellement dans votre répertoire Packages.';
$txt['package_get_error_missing_xml'] = 'Le fichier package-info.xml est introuvable dans le paquet que vous essayez d\'installer. Il doit se trouver à la racine du répertoire du paquet.';
$txt['package_get_error_is_zero'] = 'Le paquet a été téléchargé avec succès, mais il semble être vide. Vérifiez que le répertoire Packages et son sous-répertoire &quot;temp&quot; sont inscriptibles. Si le problème persiste, essayez d\'extraire le paquet sur votre PC et d\'uploader les fichiers extraits manuellement vers un sous-répertoire de Packages. Par exemple, si le paquet s\'appelle shout.tar.gz, vous devriez&nbsp;:<br />1) Télécharger le fichier sur votre machine et le décompresser.<br />2) Utiliser un client FTP pour créer un nouveau répertoire dans le répertoire &quot;Packages&quot;, par exemple ici &quot;shout&quot;.<br />3) Y placer tous les fichiers du paquet extrait.<br />4) Revenir sur la page du gestionnaire de paquets, et le paquet devrait s\'y trouver automatiquement.';
$txt['package_get_error_packageinfo_corrupt'] = 'SMF n\'a pas trouvé d\'informations valables dans le fichier package-info.xml inclus dans le paquet. Le paquet comporte peut-être une erreur, ou il est corrompu.';
$txt['package_get_error_is_theme'] = 'Vous ne pouvez pas installer de Thème à partir de cette section, s\'il vous plaît utilisez le <a href ="{MANAGETHEMEURL}"> Thèmes et Disposition</a> Rubrique "Installer un Nouveau Thème" pour le mettre en ligne dans le répertoire approprié.';
$txt['package_get_error_is_mod'] = 'You can\'t install a mod from this section, please use the <a href="{MANAGEMODURL}">Package manager</a> page to upload it';
$txt['package_get_error_theme_not_compatible'] = 'Your theme does not show it has compatibility with %1$s. Please contact the theme author.';
$txt['package_get_error_theme_no_based_on_found'] = 'The theme you\'re trying to install depends on another theme: %1$s, you need to install that theme first.';
$txt['package_get_error_theme_no_new_version'] = 'The theme you\'re trying to install is already installed or is an outdated version of it. The version you\'re trying to install is: %1$s and the version already installed is: %2$s.';

$txt['no_membergroup_selected'] = 'Aucun groupe de membre sélectionné';
$txt['membergroup_does_not_exist'] = 'Le groupe de membres n\'existe pas ou est invalide.';

$txt['at_least_one_admin'] = 'Il doit y avoir au moins un administrateur sur ce forum&nbsp;!';

$txt['error_functionality_not_windows'] = 'Désolé, cette fonctionnalité n\'est actuellement pas disponible pour les serveurs tournant sous Windows.';

// Don't use entities in the below string.
$txt['attachment_not_found'] = 'Fichier joint introuvable';

$txt['error_no_boards_selected'] = 'Aucune section valide n\'a été sélectionnée&nbsp;!';
$txt['error_no_boards_available'] = 'Sorry, there are no boards available to you at this time.';
$txt['error_invalid_search_string'] = 'Avez-vous oublié de spécifier quelque chose à rechercher&nbsp;?';
$txt['error_invalid_search_string_blacklist'] = 'Votre requête comporte des mots trop communs. Réessayez en modifiant votre requête.';
$txt['error_search_string_small_words'] = 'Chaque mot doit faire au moins deux caractères de long.';
$txt['error_query_not_specific_enough'] = 'Votre requête n\'est pas assez précise. Essayez de nouveau avec des mots ou des phrases moins vagues.';
$txt['error_no_messages_in_time_frame'] = 'Aucun message trouvé dans cet intervalle de temps.';
$txt['error_no_labels_selected'] = 'Aucune étiquette n\'a été sélectionnée&nbsp;!';
$txt['error_no_search_daemon'] = 'Impossible d\'accéder au daemon (processus) de recherche';

$txt['profile_errors_occurred'] = 'Les erreurs suivantes sont survenues lorsque vous tentiez de sauvegarder votre profil';
$txt['profile_error_bad_offset'] = 'Le décalage horaire est hors normes';
$txt['profile_error_bad_timezone'] = 'The timezone specified is invalid';
$txt['profile_error_no_name'] = 'Le pseudonyme est resté vide';
$txt['profile_error_digits_only'] = 'Le champ \'nombre de messages\' ne peut contenir que des chiffres.';
$txt['profile_error_name_taken'] = 'L\'identifiant/pseudonyme choisi est déjà utilisé';
$txt['profile_error_name_too_long'] = 'Le nom sélectionné est trop long. Il ne doit pas faire plus de 60 caractères';
$txt['profile_error_no_email'] = 'Le champ d\'adresse e-mail est resté vide';
$txt['profile_error_bad_email'] = 'Vous n\'avez pas spécifié une adresse e-mail valide';
$txt['profile_error_email_taken'] = 'Un autre utilisateur s\'est déjà inscrit avec cette adresse e-mail';
$txt['profile_error_no_password'] = 'Vous n\'avez pas entré votre mot de passe';
$txt['profile_error_bad_new_password'] = 'Les nouveaux mots de passe que vous avez entrés ne correspondent pas';
$txt['profile_error_bad_password'] = 'Le mot de passe que vous avez entré est incorrect';
$txt['profile_error_bad_avatar'] = 'L\'avatar que vous avez choisi n\'est pas une image valide';
$txt['profile_error_bad_avatar_invalid_url'] = 'L\'URL que vous avez spécifiée est invalide, veuillez la vérifier.';
$txt['profile_error_bad_avatar_too_large'] = 'The image you\'re trying to use surpasses the max width/height settings, please use a smaller one.';
$txt['profile_error_bad_avatar_fail_reencode'] = 'The image you uploaded was corrupted and the attempt to recover it failed.';
$txt['profile_error_password_short'] = 'Votre mot de passe doit contenir au moins ' . (empty($modSettings['password_strength']) ? 4 : 8) . ' caractères.';
$txt['profile_error_password_restricted_words'] = 'Votre mot de passe de doit pas contenir votre identifiant, adresse e-mail ou autre mot couramment utilisé.';
$txt['profile_error_password_chars'] = 'Votre mot de passe doit contenir un mélange de lettres majuscules et minuscules, de même que des numéros.';
$txt['profile_error_already_requested_group'] = 'Vous avez déjà une demande en instance pour ce groupe&nbsp;!';
$txt['profile_error_signature_not_yet_saved'] = 'La signature n\'a pas été enregistrée.';
$txt['profile_error_personal_text_too_long'] = 'Le texte personnel est trop long.';
$txt['profile_error_user_title_too_long'] = 'Le nom personnalisé est trop long.';
$txt['profile_error_custom_field_mail_fail'] = 'The mail validation check returned an error, you need to enter a valid email format (user@domain).';
$txt['profile_error_custom_field_regex_fail'] = 'The regex verification returned an error, if you are unsure about what to type here, please contact the forum administrator.';

// Registration form.
$txt['under_age_registration_prohibited'] = 'Sorry, but users under the age of %1$d are not allowed to register on this forum.';
$txt['error_too_quickly'] = 'You went through registration a bit too quickly, faster than should normally be possible. Please give it a moment and try again.';
$txt['mysql_error_space'] = ' - vérifiez la taille de votre base de données ou contactez un administrateur du serveur.';

$txt['icon_not_found'] = 'L\'icône de message n\'a pu être trouvée pour le thème par défaut - veuillez vous assurer que l\'image a bien été transférée et essayez de nouveau.';
$txt['icon_after_itself'] = 'L\'icône ne peut pas être positionnée après elle-même&nbsp;!';
$txt['icon_name_too_long'] = 'Les noms de fichier des icônes ne peuvent dépasser 16 caractès de long';

$txt['name_censored'] = 'Désolé, le nom que vous tentez d\'utiliser, %1$s, contient des mots qui ont été censurés. Veuillez en choisir un autre.';

$txt['poll_already_exists'] = 'Un sujet ne peut avoir qu\'un seul sondage associé&nbsp;!';
$txt['poll_not_found'] = 'Il n\'y a aucun sondage associé à ce sujet&nbsp;!';

$txt['error_while_adding_poll'] = 'L\'erreur ou les erreurs suivantes sont apparues lorsque vous ajoutiez ce sondage';
$txt['error_while_editing_poll'] = 'L\'erreur ou les erreurs suivantes sont apparues lorsque vous modifiez ce sondage';

$txt['loadavg_search_disabled'] = 'Dû à une charge élevée sur le serveur, la fonction de recherche a été automatiquement et temporairement désactivée. Réessayez un peu plus tard.';
$txt['loadavg_generic_disabled'] = 'Dé dû à une charge élevée sur le serveur, cette fonction est actuellement indisponible.';
$txt['loadavg_allunread_disabled'] = 'Les ressources du serveur sont temporairement surchargées&nbsp;; impossible de trouver tous les sujets que vous n\'avez pas lus.';
$txt['loadavg_unreadreplies_disabled'] = 'Le serveur est actuellement sous une charge élevée.  Veuillez essayer de nouveau dans quelques instants.';
$txt['loadavg_show_posts_disabled'] = 'Réessayer à nouveau plus tard.  Les messages de ce membre ce sont pas disponibles actuellement du fait d\'une surcharge du serveur.';
$txt['loadavg_unread_disabled'] = 'Le serveur est temporairement trop stressé pour permettre d\'afficher les sujets non lus.';
$txt['loadavg_userstats_disabled'] = 'Veuillez essayer à nouveau. Les statistiques de ce membre sont actuellement indisponibles à cause d\'une surcharge du serveur.';

$txt['cannot_edit_permissions_inherited'] = 'Vous ne pouvez pas modifier les permissions de type hérité directement. Vous devez modifier soit le groupe parent, soit l\'héritage du groupe de membres.';

$txt['mc_no_modreport_specified'] = 'Vous devez spécifier quel rapport vous voulez voir.';
$txt['mc_no_modreport_found'] = 'Le rapport spécifié n\'existe pas, ou il est hors de portée pour vous';

$txt['st_cannot_retrieve_file'] = 'Impossible de récupérer le fichier %1$s.';
$txt['admin_file_not_found'] = 'Impossible de charger le fichier demandé&nbsp;: %1$s.';

$txt['themes_none_selectable'] = 'Au moins un thème doit pouvoir être sélectionné.';
$txt['themes_default_selectable'] = 'Le thème par défaut global du forum doit être un thème sélectionnable.';
$txt['ignoreboards_disallowed'] = 'L\'option pour ignorer les sections n\'a pas été activée.';

$txt['mboards_delete_error'] = 'Pas de catégorie sélectionnée&nbsp;!';
$txt['mboards_delete_board_error'] = 'Pas de section sélectionnée&nbsp;!';

$txt['mboards_parent_own_child_error'] = 'Impossible de faire un parent de son propre enfant!';
$txt['mboards_board_own_child_error'] = 'Impossible de faire une section son propre enfant!';

$txt['smileys_upload_error_notwritable'] = 'Les répertoires de smileys suivants ne sont pas inscriptibles&nbsp;: %1$s';
$txt['smileys_upload_error_types'] = 'Les fichiers des smileys sont limités à ces extensions&nbsp;: %1$s.';

$txt['change_email_success'] = 'Votre adresse e-mail a été changée, et un nouvel e-mail d\'activation a été envoyé.';
$txt['resend_email_success'] = 'L\'e-mail d\'activation a été renvoyé avec succès.';

$txt['custom_option_need_name'] = 'L\'option du profil doit avoir un nom&nbsp;!';
$txt['custom_option_not_unique'] = 'Le nom du champ n\'est pas unique&nbsp;!';
$txt['custom_option_regex_error'] = 'Le regex entré n\'est pas valide';

$txt['warning_no_reason'] = 'Vous devez entrer une raison pour modifier l\'état d\'avertissement d\'un membre.';
$txt['warning_notify_blank'] = 'Vous avez choisi de notifier l\'utilisateur, mais vous n\'avez pas rempli les champs de titre/message.';

$txt['cannot_connect_doc_site'] = 'Impossible de se connecter au manuel en ligne de Simple Machines. Veuillez vous assurer que votre configuration serveur permet les connexions Internet externes et réessayez plus tard.';

$txt['movetopic_no_reason'] = 'Vous devez préciser une raison pour le déplacement du sujet, ou désactiver l\'option \'Poster un message de redirection\'.';

$txt['error_custom_field_too_long'] = 'Le champ &quot;%1$s&quot; ne peut faire plus de %2$d caractères.';
$txt['error_custom_field_invalid_email'] = 'Le champ &quot;%1$s&quot; doit être une adresse e-mail valide.';
$txt['error_custom_field_not_number'] = 'Le champ &quot;%1$s&quot; doit être un nombre.';
$txt['error_custom_field_inproper_format'] = 'Le format du champ &quot;%1$s&quot; est invalide.';
$txt['error_custom_field_empty'] = 'Le champ &quot;%1$s&quot; ne peut être laissé vide.';

$txt['email_no_template'] = 'Le modèle d\'e-mail &quot;%1$s&quot; est introuvable.';

$txt['search_api_missing'] = 'L\'API de recherche est introuvable&nbsp;! Veuillez contacter l\'administrateur pour vérifier s\'ils ont mis les bons fichiers sur le serveur.';
$txt['search_api_not_compatible'] = 'L\'API de recherche sélectionnée n\'est pas à jour - SMF utilisera la recherche standard à la place. Veuillez vérifier le fichier %1$s.';

// Handling hook calls
$txt['hook_fail_loading_file'] = 'Hook call: The file at path: %s could not be loaded.';
$txt['hook_fail_call_to'] = 'Hook call: function "%1$s" in file %2$s could not be called.';

// SubActions failed attempt.
$txt['subAction_fail'] = 'The callable %s could not be called.';

// Restore topic/posts
$txt['cannot_restore_first_post'] = 'Vous ne pouvez restaurer le premier message d\'un sujet.';
$txt['parent_topic_missing'] = 'Le sujet auquel appartenait le message que vous essayez de restaurer a été supprimé entretemps.';
$txt['restored_disabled'] = 'La restauration de sujets a été désactivée.';
$txt['restore_not_found'] = 'Les messages suivants n\'ont pas pu être restaurés; le sujet original a peut-être été supprimé entretemps&nbsp;:<ul style="margin-top: 0px;">%1$s</ul>Vous devrez les déplacer manuellement.';

$txt['error_invalid_dir'] = 'Le répertoire que vous avez spécifié est invalide.';

?>