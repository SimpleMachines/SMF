<?php
// Version: 2.1 Beta 2; Post

global $context;

$txt['post_reply'] = 'Répondre';
$txt['message_icon'] = 'Icône du message';
$txt['subject_not_filled'] = 'Le titre n\'a pas été renseigné. Il est requis.';
$txt['message_body_not_filled'] = 'Le corps du message n\'a pas été rempli. Il est requis.';
// Use numeric entities in the below string.
$txt['add_bbc'] = 'Ajouter des tags BBC';

$txt['disable_smileys'] = 'Désactiver les smileys';
$txt['dont_use_smileys'] = 'Ne pas utiliser de smiley.';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['posted_on'] = 'Posté le';
$txt['standard'] = 'Standard';
$txt['thumbs_up'] = 'Pouce en haut';
$txt['thumbs_down'] = 'Pouce en bas';
$txt['exclamation_point'] = 'Point d\'exclamation';
$txt['question_mark'] = 'Point d\'interrogation';
$txt['icon_poll'] = 'Sondage';
$txt['lamp'] = 'Lampe';
$txt['add_smileys'] = 'Ajouter des smileys';
$txt['topic_notify_no'] = 'Aucun sujet avec notification.';
// post_too_long seems unused (duplicate in Errors: error_post_too_long
$txt['post_too_long'] = 'Votre message est trop long. Merci de le raccourcir avant de l\'envoyer de nouveau.';

// Use numeric entities in the below five strings.
$txt['notifyUnsubscribe'] = 'Cliquez ici pour vous d&#233;sabonner de ce sujet';

$txt['lock_after_post'] = 'Bloquer après ce message.';
$txt['notify_replies'] = 'Suivre les réponses de ce sujet';
$txt['lock_topic'] = 'Bloquer ce sujet.';
$txt['shortcuts'] = 'Raccourcis&nbsp;: tapez [ALT]+[S] pour poster ou [ALT]+[P] pour prévisualiser';
$txt['shortcuts_drafts'] = 'raccourcis: alt+s soumettre/poster, alt+p prévisualisation ou alt+d enregistrer brouillon';
$txt['shortcuts_firefox'] = 'Raccourcis&nbsp;: tapez [SHIFT]+[ALT]+[S] pour poster ou [SHIFT]+[ALT]+[P] pour prévisualiser';
$txt['shortcuts_drafts_firefox'] = 'raccourcis: Ctrl+alt+s soumettre/poster, ctrl+alt+p prévisualisation ou ctrl+alt+d enregistrer brouillon';
$txt['option'] = 'Option';
$txt['reset_votes'] = 'Décompte des votes à 0';
$txt['reset_votes_check'] = 'Cochez ici pour mettre tous les décomptes de votes à 0.';
$txt['votes'] = 'votes';
$txt['attach'] = 'Joindre';
$txt['clean_attach'] = 'Nettoyer les fichiers joints';
$txt['attached'] = 'Joints';
$txt['allowed_types'] = 'Types de fichier autorisés';
$txt['cant_upload_type'] = 'Vous ne pouvez pas envoyer ce type de fichier. Les extensions autorisées sont';
$txt['uncheck_unwatchd_attach'] = 'Décochez les fichiers joints que vous voulez supprimer';
$txt['restricted_filename'] = 'Nom de fichier réservé. Merci de le renommer.';
$txt['topic_locked_no_reply'] = 'Attention! Ce sujet est actuellement ou sera bloqué<br>Seuls les administrateurs et les modérateurs peuvent y répondre.';
$txt['awaiting_approval'] = 'En attente d\'approbation';
$txt['attachment_requires_approval'] = 'Notez que les fichiers joints ne seront affichés qu\'après approbation d\'un modérateur.';
$txt['error_temp_attachments'] = 'Des fichiers ont été joints à ce message, mais jamais publiés. Ce problème a été corrigé. Si vous ne souhaitez pas les mettre en ligne, vous pouvez les supprimer <a href="#postAttachment">ici</a>.';
// Use numeric entities in the below string.
$txt['js_post_will_require_approval'] = 'Attention, ce sujet n\\\'appara&#238;tra qu\\\'apr&#232;s avoir &#233;t&#233; approuv&#233; par un mod&#233;rateur.';

$txt['enter_comment'] = 'Entrez un commentaire';
// Use numeric entities in the below two strings.
$txt['reported_post'] = 'Message signal&#233;';
$txt['reported_to_mod_by'] = 'par';
$txt['rtm10'] = 'Envoyer';
// Use numeric entities in the below four strings.
$txt['report_following_post'] = 'Le message \'%1$s\', par';
$txt['reported_by'] = ', a &#233;t&#233; signal&#233; par';
$txt['board_moderate'] = 'sur un forum que vous mod&#233;rez';
$txt['report_comment'] = 'Le rapporteur a fait la remarque suivante';

// Use numeric entities in the below three strings.
$txt['report_profile'] = 'Signaler le profil de \'%1$s\' ';
$txt['reported_profile'] = 'Utilisateur signalé';
$txt['report_following_user'] = 'Le profil de "%1$s" a %2$s ';

$txt['attach_restrict_attachmentPostLimit'] = 'taille totale maximale %1$dKo';
$txt['attach_restrict_attachmentPostLimit_MB'] = 'taille totale maximale %1$d MB ';
$txt['attach_restrict_attachmentSizeLimit'] = 'taille individuelle maximale %1$dKo';
$txt['attach_restrict_attachmentSizeLimit_MB'] = 'taille individuelle maximum %1$d MB';
$txt['attach_restrict_attachmentNumPerPostLimit'] = '%1$d par message';
$txt['attach_restrictions'] = 'Restrictions&nbsp;:';

$txt['post_additionalopt_attach'] = 'Fichiers joints et autres options';
$txt['post_additionalopt'] = 'Fichiers joints et autres options&#133;';
$txt['sticky_after'] = 'Épingler ce sujet';
$txt['move_after2'] = 'Déplacer ce sujet';
$txt['back_to_topic'] = 'Retourner au sujet';
$txt['approve_this_post'] = 'Approuver ce message';

$txt['retrieving_quote'] = 'Récupération de citation&#133;';

$txt['post_visual_verification_label'] = 'Vérification';
$txt['post_visual_verification_desc'] = 'Veuillez entrer le code contenu dans l\'image ci-dessus pour valider ce message.';

$txt['poll_options'] = 'Options de sondage';
$txt['poll_run'] = 'Lancer le sondage pour';
$txt['poll_run_limit'] = '(Laissez vide si pas de limite.)';
$txt['poll_results_visibility'] = 'Visibilité des résultats';
$txt['poll_results_anyone'] = 'Montrer les résultats du sondage à tous.';
$txt['poll_results_voted'] = 'Montrer les résultats aux seuls votants.';
$txt['poll_results_after'] = 'Ne montrer les résultats qu\'après l\'expiration du sondage.';
$txt['poll_max_votes'] = 'Votes maximum par membre';
$txt['poll_do_change_vote'] = 'Permettre à l\'utilisateur de changer d\'avis';
$txt['poll_too_many_votes'] = 'Vous avez choisi trop d\'options. Pour ce sondage, vous ne pouvez sélectionner que %1$s options';
$txt['poll_add_option'] = 'Ajouter une option';
$txt['poll_guest_vote'] = 'Permettre aux invités de voter';

$txt['spellcheck_done'] = 'Correction d\'orthographe terminée.';
$txt['spellcheck_change_to'] = 'Changer par&nbsp;:';
$txt['spellcheck_suggest'] = 'Suggestions&nbsp;:';
$txt['spellcheck_change'] = 'Changer';
$txt['spellcheck_change_all'] = 'Changer tout';
$txt['spellcheck_ignore'] = 'Ignorer';
$txt['spellcheck_ignore_all'] = 'Ignorer Tout';

$txt['more_attachments'] = 'ajouter un fichier';
// Don't use entities in the below string.
$txt['more_attachments_error'] = 'Vous ne pouvez plus ajouter d\'autres fichiers joints.';

$txt['more_smileys'] = 'plus';
$txt['more_smileys_title'] = 'Smileys additionnels';
$txt['more_smileys_pick'] = 'Choisir un smiley';
$txt['more_smileys_close_window'] = 'Fermer la fenêtre';

$txt['error_new_reply'] = 'Attention &#151; une nouvelle réponse a été postée pendant que vous rédigiez votre message. Vous devriez peut-être relire votre message avant de l\'envoyer, pour éviter toute redondance.';
$txt['error_new_replies'] = 'Attention &#151; %1$d nouvelles réponses ont été postées pendant que vous rédigiez votre message. Vous devriez peut-être relire votre message avant de l\'envoyer, pour éviter toute redondance.';
$txt['error_new_reply_reading'] = 'Attention &#151; une nouvelle réponse a été postée pendant que vous lisiez votre message. Vous devriez peut-être relire votre message avant de l\'envoyer, pour éviter toute redondance.';
$txt['error_new_replies_reading'] = 'Attention &#151; %1$d nouvelles réponses ont été postées pendant que vous lisiez votre message. Vous devriez peut-être relire votre message avant de l\'envoyer, pour éviter toute redondance.';

$txt['error_topic_locked'] = 'Attention! Pendant que vous écriviez le sujet a été bloqué. Veuillez cocher la case "Bloquer ce sujet" sous "Fichiers et autres options" en dessous si vous ne désirez pas annuler cette action.';
$txt['error_topic_unlocked'] = 'Attention! Pendant que vous écriviez le sujet a été débloqué. Veuillez décocher la case "Bloquer ce sujet" sous "Fichiers et autres options" en dessous si vous ne désirez pas annuler cette action.';
$txt['error_topic_stickied'] = 'Attention! Pendant que vous écriviez le sujet a été épingle. Veuillez cocher la case "Épingler ce sujet" sous "Fichiers et autres options" en dessous si vous ne désirez pas annuler cette action.';
$txt['error_topic_unstickied'] = 'Attention! Pendant que vous écriviez le sujet a été désepinglé. Veuillez décocher la case "Épingler ce sujet" sous "Fichiers et autres options" en dessous si vous ne désirez pas annuler cette action.';

$txt['announce_this_topic'] = 'Envoyer une annonce à propos de ce sujet aux membres&nbsp;:';
$txt['announce_title'] = 'Envoyer une annonce';
$txt['announce_desc'] = 'Ce formulaire vous permet d\'envoyer une annonce aux groupes de membres sélectionnés à propos de ce sujet.';
$txt['announce_sending'] = 'Envoi de l\'annonce de ce sujet';
$txt['announce_done'] = 'terminé';
$txt['announce_continue'] = 'Continuer';
$txt['announce_topic'] = 'Annonce d\'un sujet.';
$txt['announce_regular_members'] = 'Membres inscrits';

$txt['digest_subject_daily'] = 'Résumé du jour';
$txt['digest_subject_weekly'] = 'Résumé de la semaine';
$txt['digest_intro_daily'] = 'Ci-dessous se trouve le résumé pour aujourd\'hui des activités dans les sections et sujets que vous suivez par abonnement sur %1$s. Pour vous désabonner, veuillez visiter le lien ci-dessous.';
$txt['digest_intro_weekly'] = 'Ci-dessous se trouve le résumé de cette semaine des activités dans les sections et sujets que vous suivez par abonnement sur %1$s. Pour vous désabonner, veuillez visiter le lien ci-dessous.';
$txt['digest_new_topics'] = 'Les sujets suivants ont été démarrés';
$txt['digest_new_topics_line'] = '"%1$s" dans "%2$s"';
$txt['digest_new_replies'] = 'Des réponses ont été postées dans les sujets suivants';
$txt['digest_new_replies_one'] = '1 réponse dans "%1$s"';
$txt['digest_new_replies_many'] = '%1$d réponses dans "%2$s"';
$txt['digest_mod_actions'] = 'Les actions de modération suivantes ont été accomplies';
$txt['digest_mod_act_sticky'] = '"%1$s" a été épinglé';
$txt['digest_mod_act_lock'] = '"%1$s" a été verrouillé';
$txt['digest_mod_act_unlock'] = '"%1$s" a été déverrouillé';
$txt['digest_mod_act_remove'] = '"%1$s" a été effacé';
$txt['digest_mod_act_move'] = '"%1$s" a été déplacé';
$txt['digest_mod_act_merge'] = '"%1$s" a été fusionné';
$txt['digest_mod_act_split'] = '"%1$s" a été séparé en deux sujets';

$txt['attach_error_title'] = 'Erreur lors de l\'envois de fichier joint.';
$txt['attach_warning'] = 'Erreur durant le téléchargement de <strong>%1$s</strong>.';
$txt['attach_check_nag'] = 'Impossible de continuer à cause d\'une donnée incomplète (%1$s).';
$txt['attach_max_total_file_size'] = 'Désolé, vous êtes en dehors de la limite d\'espace de fichiers joins. La limite d\'espace total par message est de %1$s KB. L\'espace restant est de %2$s kB.';
$txt['attach_folder_warning'] = 'Le dossier des fichiers joints ne peut être localisé. Veuillez notifier un administrateur au sujet de ce problème.';
$txt['attach_folder_admin_warning'] = 'Le chemin vers le dossier de fichiers joints (%1$s) est incorrect. Veuillez le corriger dans les réglages de fichiers joints de votre panneau d\'administration.';
$txt['attach_limit_nag'] = 'Vous avez atteint le nombre maximum de fichiers joints autorisés par message.';
$txt['attach_no_upload'] = 'Un problème est survenu et votre fichier n\'a pu être téléchargé';
$txt['attach_remaining'] = '%1$d restant';
$txt['attach_available'] = '%1$s KB disponible';
$txt['attach_kb'] = '(%1$s KB)';
$txt['attach_0_byte_file'] = 'Le fichier semble être vide. Veuillez contacter l\'administrateur du forum si ce problème persiste';
$txt['attached_files_in_session'] = '<em>Le(s) fichier(s) soulignés ci-dessus ont étés téléchargés mais ne seront pas attachés à ce message avant qu\'il ne soit envoyé.</em>';

$txt['attach_php_error'] = 'Dû à une erreur, votre fichier n\'a pu être téléchargé. Veuillez contacter l\'administrateur du forum si ce problème persite.';
$txt['php_upload_error_1'] = 'Le fichier téléchargé excède la directive upload_max_filesize dans php.ini. Veuillez contacter votre hébergeur si vous ne pouvez corriger ce problème.';
$txt['php_upload_error_3'] = 'Le fichier envoyé n\'a été téléchargé que partiellement. Ceci est une erreur PHP. Veuillez contacter votre hébergeur si le problème persiste.';
$txt['php_upload_error_4'] = 'Aucun fichier n\'a été téléchargé. Ceci est une erreur PHP. Veuillez contacter votre hébergeur si ce problème persiste.';
$txt['php_upload_error_6'] = 'Échec d\'enregistrement. Dossier temporaire manquant. Veuillez contacter votre hébergeur si vous ne pouvez corriger ce problème.';
$txt['php_upload_error_7'] = 'Échec d\'écriture sur le disque. Ceci est une erreur PHP. Veuillez contacter votre hébergeur si ce problème persiste.';
$txt['php_upload_error_8'] = 'Une extension PHP a interrompu le téléchargement. Ceci est une erreur PHP. Veuillez contacter votre hébergeur si le problème persiste.';
$txt['error_temp_attachments_new'] = 'Des fichiers joints que vous avez précédemment soumis n\'ont pas étés postés. Ces fichiers sont toujours joints à ce message. Ce message doit être envoyé avant que ces fichiers puissent être enregistrés ou supprimés. Vous pouvez faire ceci <a href="#postAttachment">ici</a>';
$txt['error_temp_attachments_found'] = 'Les fichiers joints suivants ont étés attachés à un autre message mais non postés. Il est recommandé de ne pas les poster avant qu\'ils soient supprimés ou que ce message ait été soumis. <br>Cliquez <a href="%1$s">ici</a> pour supprimer ces fichiers joints. Ou <a href="%2$s">ici</a> pour retourner sur ce message.%3$s';
$txt['error_temp_attachments_lost'] = 'Les fichiers joints suivants ont précédemment étés attachés à un autre message mais non postés. Il est recommandé de ne pas envoyer d\'autres fichiers avant que ceux-ci soient supprimés ou que le message ait été soumis.<br>Cliquez <a href="%1$s">ici</a>pour supprimer ces fichiers joints.%2$s';
$txt['error_temp_attachments_gone'] = 'Ces fichiers joints ont maintenant étés supprimés et vous avez été renvoyé à la page où vous étiez précédemment.';
$txt['error_temp_attachments_flushed'] = 'Veuillez noter que tout fichier qui aura été attaché précédemment mais non posté est maintenant supprimé.';
$txt['error_topic_already_announced'] = 'Veuillez noter que ce sujet a déjà été annoncé.';

$txt['cant_access_upload_path'] = 'Ne peut accéder au chemin des fichiers joints!';
$txt['file_too_big'] = 'Votre fichier est trop grand. La taille maximum du fichier joint autorisé est de %1$d KB.';
$txt['attach_timeout'] = 'Votre fichier n\'a pu être enregistré. Ceci a pu être causé par un temps de téléchargement trop long ou le fichier est plus gros que ne l\'accepte le serveur.<br><br>Veuillez contacter votre administrateur serveur pour plus d\'informations.';
$txt['bad_attachment'] = 'Votre fichier n\'a pas passé la vérification de sécurité and n\'a pu être téléchargé. Veuillez contacter l\'administrateur du forum.';
$txt['ran_out_of_space'] = 'Le dossier de téléchargement est plein. Veuillez contacter un administrateur au sujet de ce problème.';
$txt['attachments_no_write'] = 'Le dossier de fichier joints n\'a pas de permissions en écriture. Votre fichier joint ou avatar ne peut être sauvegardé.';
$txt['attachments_no_create'] = 'Impossible de créer un nouveau dossier de fichiers joints. Votre fichier joint ou avatar ne peut être sauvegardé.';
$txt['attachments_limit_per_post'] = 'Vous ne pouvez pas joindre plus de %1$d fichiers joints par message';

?>