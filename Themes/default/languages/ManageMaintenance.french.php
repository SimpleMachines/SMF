<?php
// Version: 2.1 Beta 2; ManageMaintenance

$txt['repair_zero_ids'] = 'Des fils et/ou messages portant l\'ID 0 ont �t� trouv�s.';
$txt['repair_missing_topics'] = 'Le message #%1$d est dans un sujet non existant (#%2$d).';
$txt['repair_missing_messages'] = 'Le sujet #%1$d ne contient aucun message r�el.';
$txt['repair_stats_topics_1'] = 'Le sujet #%1$d d�bute par un message dont l\'ID (%2$d) est incorrect.';
$txt['repair_stats_topics_2'] = 'Le sujet #%1$d se termine par un message dont l\'ID (%2$d) est incorrect.';
$txt['repair_stats_topics_3'] = 'Le sujet #%1$d indique un nombre de r�ponses incorrect, %2$d.';
$txt['repair_stats_topics_4'] = 'Le sujet #%1$d indique un nombre de messages non approuv�s incorrect, %2$d.';
$txt['repair_stats_topics_5'] = 'Le sujet #%1$d indique une valeur &quot;approuv�&quot; incorrecte.';
$txt['repair_missing_boards'] = 'Le sujet #%1$d a pour section parente #%2$d, qui n\'existe pas.';
$txt['repair_missing_categories'] = 'La section #%1$d a la valeur #%2$d comme cat�gorie parente, qui n\'existe pas.';
$txt['repair_missing_posters'] = 'Le message #%1$d est post� par le membre #%2$d, qui n\'existe pas ou plus.';
$txt['repair_missing_parents'] = 'La section #%1$d a pour parente la section #%2$d, qui n\'existe pas.';
$txt['repair_missing_polls'] = 'Le sujet #%1$d est li� � un sondage introuvable (#%2$d).';
$txt['repair_polls_missing_topics'] = 'Le sondage #%1$d est li� � un sujet inexistant, #%2$d.';
$txt['repair_poll_options_missing_poll'] = 'Poll #%1$d has %2$d voting options but no poll attached.';
$txt['repair_missing_calendar_topics'] = 'L\'�v�nement #%1$d est li� � un sujet inexistant, #%2$d.';
$txt['repair_missing_log_topics'] = 'Le sujet #%1$d est indiqu� comme �tant lu par une ou plusieurs personnes alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_log_topics_members'] = 'Le membre #%1$d est indiqu� comme ayant lu un ou plusieurs sujets alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_log_boards'] = 'La section #%1$d est indiqu�e comme ayant �t� lue par une ou plusieurs personnes alors qu\'elle n\'existe pas ou plus.';
$txt['repair_missing_log_boards_members'] = 'Le membre #%1$d est indiqu� comme ayant lu une ou plusieurs sections alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_log_mark_read'] = 'La section #%1$d est indiqu�e comme ayant �t� lue par une ou plusieurs personnes alors qu\'elle n\'existe pas ou plus.';
$txt['repair_missing_log_mark_read_members'] = 'Le membre #%1$d est indiqu� comme ayant lu une ou plusieurs sections qui n\'existent pas ou plus.';
$txt['repair_missing_pms'] = 'Le message personnel #%1$d a �t� envoy� � une ou plusieurs personnes, alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_recipients'] = 'Le membre #%1$d a re�u un ou plusieurs messages personnels alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_senders'] = 'Le message personnel #%1$d a �t� envoy� par le membre #%2$d, qui n\'existe pas ou plus.';
$txt['repair_missing_notify_members'] = 'Des notifications ont �t� demand�es par le membre #%1$d, qui n\'existe pas ou plus.';
$txt['repair_missing_cached_subject'] = 'Le titre du sujet #%1$d n\'a pas �t� mis en cache.';
$txt['repair_missing_topic_for_cache'] = 'Le mot en cache \'%1$s\' est rattach� � un sujet inexistant.';
$txt['repair_missing_log_poll_member'] = 'Le sondage #%1$d a enregistr� un vote par le membre #%2$d, qui n\'existe pas ou plus.';
$txt['repair_missing_log_poll_vote'] = 'Le membre #%1$d a particip� au sondage #%2$d, qui n\'existe pas ou plus.';
$txt['repair_missing_thumbnail_parent'] = 'Une vignette existe, nomm�e %1$s, mais elle n\'a pas d\'image parente.';
$txt['repair_report_missing_comments'] = 'Le rapport #%1$d concernant le sujet &quot;%2$s&quot; n\'a pas de commentaire associ�.';
$txt['repair_comments_missing_report'] = 'Le commentaire de rapport #%1$d soumis par %2$s n\'a pas de rapport associ�.';
$txt['repair_group_request_missing_member'] = 'Une demande d\'adh�sion � un groupe a �t� retrouv�e pour un membre qui n\'existe plus, #%1$d.';
$txt['repair_group_request_missing_group'] = 'Une demande d\'adh�sion � un groupe qui n\'existe plus (#%1$d) a �t� retrouv�e.';

$txt['repair_currently_checking'] = 'V�rification de &quot;%1$s&quot;';
$txt['repair_currently_fixing'] = 'R�paration de &quot;%1$s&quot;';
$txt['repair_operation_zero_topics'] = 'Fils dont la valeur id_topic est � z�ro par erreur';
$txt['repair_operation_zero_messages'] = 'Messages dont la valeur id_msg est � z�ro par erreur';
$txt['repair_operation_missing_topics'] = 'Messages sans sujet associ�';
$txt['repair_operation_missing_messages'] = 'Sujets sans aucun message';
$txt['repair_operation_stats_topics'] = 'Sujets o� le premier ou dernier message n\'est pas le bon';
$txt['repair_operation_stats_topics2'] = 'Sujets o� le nombre de r�ponses est erron�';
$txt['repair_operation_stats_topics3'] = 'Sujets o� le nombre de r�ponses non approuv�es est erron�';
$txt['repair_operation_missing_boards'] = 'Sujets dans une section inexistante';
$txt['repair_operation_missing_categories'] = 'Sections dans une cat�gorie inexistante';
$txt['repair_operation_missing_posters'] = 'Messages li�s � des membres inexistants';
$txt['repair_operation_missing_parents'] = 'Sections li�es � une section parente inexistante';
$txt['repair_operation_missing_polls'] = 'Sujets li�s � un sondage inexistant';
$txt['repair_operation_missing_calendar_topics'] = '�v�nements li�s � un sujet inexistant';
$txt['repair_operation_missing_log_topics'] = 'Journaux de sujets li�s � un sujet inexistant';
$txt['repair_operation_missing_log_topics_members'] = 'Journaux de sujets li�s � un membre inexistant';
$txt['repair_operation_missing_log_boards'] = 'Journaux de sections li�s � des sections inexistantes';
$txt['repair_operation_missing_log_boards_members'] = 'Journaux de sections li�s � des membres inexistants';
$txt['repair_operation_missing_log_mark_read'] = 'Informations sur les messages lus li�es &agrave une section inexistante';
$txt['repair_operation_missing_log_mark_read_members'] = 'Informations sur les messages lus li�es � un membre inexistant';
$txt['repair_operation_missing_pms'] = 'Messages priv�s manquants malgr� l\'existence de destinataires';
$txt['repair_operation_missing_recipients'] = 'Destinataires de MP li�s � un membre inexistant';
$txt['repair_operation_missing_senders'] = 'Messages personnels li�s � un membre inexistant';
$txt['repair_operation_missing_notify_members'] = 'Journaux de notification li�s � un membre inexistant';
$txt['repair_operation_missing_cached_subject'] = 'Sujets absents du cache de recherche';
$txt['repair_operation_missing_topic_for_cache'] = 'Cache de recherche li� � un sujet inexistant';
$txt['repair_operation_missing_member_vote'] = 'Votes de sondage li�s � des membres inexistants';
$txt['repair_operation_missing_log_poll_vote'] = 'Votes de sondage li�s � des sondages inexistants';
$txt['repair_operation_report_missing_comments'] = 'Rapports de sujet sans commentaire associ�';
$txt['repair_operation_comments_missing_report'] = 'Commentaires de rapport sans sujet associ�';
$txt['repair_operation_group_request_missing_member'] = 'Demandes d\'adh�sion sans membre associ�';
$txt['repair_operation_group_request_missing_group'] = 'Demandes d\'adh�sion � un groupe inexistant';

$txt['salvaged_category_name'] = 'Zone de R�cup�ration';
$txt['salvaged_category_error'] = 'Impossible de cr�er la cat�gorie Zone de R�cup�ration&nbsp;!';
$txt['salvaged_board_name'] = 'Sujets r�cup�r�s';
$txt['salvaged_board_description'] = 'Sujets cr��s pour les messages sans sujet (fil de discussion)';
$txt['salvaged_board_error'] = 'Impossible de cr�er le sujet r�cup�r�&nbsp;!';
$txt['salvaged_poll_topic_name'] = 'Sondage r�cup�r�';
$txt['salvaged_poll_message_body'] = 'Ce sondage a perdu son sujet associ�.';
$txt['salvaged_poll_question'] = 'This poll was found without a question.';

$txt['database_optimize'] = 'Optimiser la base de donn�es';
$txt['database_numb_tables'] = 'Votre base de donn�es contient %1$d tables.';
$txt['database_optimize_attempt'] = 'Tente d\'optimiser votre base de donn�es&#133;';
$txt['database_optimizing'] = 'Optimise %1$s&#133; %2$01.2f Ko optimis�s.';
$txt['database_already_optimized'] = 'Toutes les tables �taient d�j� optimis�es.';
$txt['database_opimize_unneeded'] = 'Il n\'�tait pas n�cessaire d\'optimiser les tables.';
$txt['database_optimized'] = ' table(s) optimis�e(s).';
$txt['database_no_id'] = 'a un ID de membre inexistant';

$txt['apply_filter'] = 'Appliquer le filtre';
$txt['applying_filter'] = 'Application du filtre';
$txt['filter_only_member'] = 'Afficher les messages d\'erreurs pour ce membre seulement';
$txt['filter_only_ip'] = 'Afficher les messages d\'erreurs pour cette adresse IP seulement';
$txt['filter_only_session'] = 'Afficher les messages d\'erreurs pour cette session seulement';
$txt['filter_only_url'] = 'Afficher les messages d\'erreurs pour cet URL seulement';
$txt['filter_only_message'] = 'Montrer les erreurs qui ont un message identique';
$txt['session'] = 'Session';
$txt['error_url'] = 'URL de la page causant l\'erreur';
$txt['error_message'] = 'Message d\'erreur';
$txt['clear_filter'] = 'Vider le filtre';
$txt['remove_selection'] = 'Enlever la s�lection';
$txt['remove_filtered_results'] = 'Enlever tous les r�sultats filtr�s';
$txt['sure_about_errorlog_remove'] = '�tes-vous s�r de vouloir enlever tous les messages d\\\'erreur&nbsp;?';
$txt['remove_selection_confirm'] = 'Etes-vous s�r de vouloir effacer les entr�es s�lectionn�es ?';
$txt['remove_filtered_results_confirm'] = 'Etes-vous s�r de vouloir effacer les entr�es filtr�es ?';
$txt['reverse_direction'] = 'Inverser l\'ordre chronologique de la liste';
$txt['error_type'] = 'Type d\'erreur';
$txt['filter_only_type'] = 'Ne montrer que les erreurs de ce type';
$txt['filter_only_file'] = 'Ne montrer que les erreurs provenant de ce fichier';
$txt['apply_filter_of_type'] = 'Appliquer le filtre de type&nbsp;';

$txt['errortype_all'] = 'Toutes les erreurs';
$txt['errortype_general'] = 'G�n�rale';
$txt['errortype_general_desc'] = 'Erreurs g�n�rales inclassables.';
$txt['errortype_critical'] = '<span style="color:red;">Critiques</span>';
$txt['errortype_critical_desc'] = 'Erreurs critiques. Elles n�cessitent votre attention imm�diate. Si vous ignorez ces erreurs, il se peut que votre forum soit en danger.';
$txt['errortype_database'] = 'Base de donn�es';
$txt['errortype_database_desc'] = 'Erreurs caus�es par des req�tes malform�es. Notez-les et rapportez-les � l\'�quipe de d�veloppement de SMF.';
$txt['errortype_undefined_vars'] = '�l�ments ind�finis';
$txt['errortype_undefined_vars_desc'] = 'Erreurs caus�es par l\'utilisation d\'une variable, d\'un index ou d\'un offset ind�finis.';
$txt['errortype_ban'] = 'Bannissements';
$txt['errortype_ban_desc'] = 'Un journal des utilisateurs bannis essayant d’acc�der � votre forum. ';
$txt['errortype_template'] = 'Mod�les';
$txt['errortype_template_desc'] = 'Erreurs li�es au chargement d\'un mod�le.';
$txt['errortype_user'] = 'Utilisateurs';
$txt['errortype_user_desc'] = 'Erreurs li�es au comportement des utilisateurs. Par exemple, mot de passe erron�, tentative de connexion malgr� un bannissement, et absence de permission pour une action sp�cifique.';
$txt['errortype_cron'] = 'Cron';
$txt['errortype_cron_desc'] = 'Errors resulting from background tasks.';
$txt['errortype_paidsubs'] = 'Paid Subs';
$txt['errortype_paidsubs_desc'] = 'Errors resulting from paid subscriptions, which can include notification of payment failures.';
$txt['errortype_backup'] = 'Backups';
$txt['errortype_backup_desc'] = 'Errors resulting from backing up files, which are usually messages explaining why the proceedure failed.';

$txt['maintain_recount'] = 'Recompter les totaux et statistiques des sections';
$txt['maintain_recount_info'] = 'Si le nombre total de messages sur un sujet ou dans votre bo�te de r�ception est incorrect, cette fonction peut corriger tous les d�comptes et statistiques pour vous.';
$txt['maintain_errors'] = 'Chercher et r�parer les erreurs';
$txt['maintain_errors_info'] = 'Si, par exemple, des messages ou sujets ont disparu apr�s un plantage serveur, cette fonction vous permettra peut-�tre de les r�cup�rer.';
$txt['maintain_logs'] = 'Vider les journaux triviaux.';
$txt['maintain_logs_info'] = 'Cette fonction videra tous les journaux d\'importance mineure. � n\'utiliser qu\'en cas de probl�me, mais sans danger dans tous les cas.';
$txt['maintain_cache'] = 'Vider le cache de fichiers.';
$txt['maintain_cache_info'] = 'Cette fonction videra le cache de fichiers en cas de besoin.';
$txt['maintain_optimize'] = 'Optimiser toutes les tables pour une meilleure performance.';
$txt['maintain_optimize_info'] = 'Cette fonction vous permet d\'optimiser toutes les tables. Elle supprime les espaces inutilis�s et am�liore la taille et la vitesse de votre forum.';
$txt['maintain_version'] = 'Comparer tous les fichiers avec la version la plus r�cente.';
$txt['maintain_version_info'] = 'Cette t�che de maintenance vous permet de faire une comparaison d�taill�e des versions des fichiers du forum par rapport aux derniers fichiers officiels.';
$txt['maintain_run_now'] = 'Lancer maintenant';
$txt['maintain_return'] = 'Retourner � la maintenance du forum';

$txt['maintain_backup'] = 'Sauvegarder la base de donn�es';
$txt['maintain_backup_info'] = 'T�l�charger une copie de sauvegarde de la base de donn�es de votre forum en cas d\'urgence.';
$txt['maintain_backup_struct'] = 'Sauvegarder la structure.';
$txt['maintain_backup_data'] = 'Sauver les donn�es des tables (le contenu important).';
$txt['maintain_backup_gz'] = 'Compresser le fichier avec gzip.';
$txt['maintain_backup_save'] = 'T�l�charger';

$txt['maintain_old'] = 'Enlever les anciens messages';
$txt['maintain_old_since_days1'] = 'Supprimer les sujets sans nouveaux messages depuis ';
$txt['maintain_old_since_days2'] = ' jours, qui sont&nbsp;:';
$txt['maintain_old_nothing_else'] = 'De n\'importe quel type.';
$txt['maintain_old_are_moved'] = 'Uniquement des notifications de d�placement.';
$txt['maintain_old_are_locked'] = 'Bloqu�s.';
$txt['maintain_old_are_not_stickied'] = 'Mais ne pas compter les sujets �pingl�s.';
$txt['maintain_old_all'] = 'Toutes les sections (cliquez pour choisir des sections sp�cifiques)';
$txt['maintain_old_choose'] = 'Choisir une section sp�cifique (cliquez pour les s�lectionner toutes)';
$txt['maintain_old_remove'] = 'Enlever maintenant';
$txt['maintain_old_confirm'] = '�tes-vous s�r de vouloir supprimer maintenant les anciens messages&nbsp;?\\n\\nLe processus ne peut �tre invers�&nbsp;!';

$txt['maintain_old_drafts'] = 'Supprimer les brouillons anciens';
$txt['maintain_old_drafts_days'] = 'Supprimer tous les brouillons plus anciens que';
$txt['maintain_old_drafts_confirm'] = 'Etes-vous s�r de vouloir effacer les vieux brouillons maintenant ?\\n\\nIl n\\\'y aura pas de retour en arri�re possible !';
$txt['maintain_members'] = 'Effacer les comptes inactifs';
$txt['maintain_members_ungrouped'] = 'Membres non group�s <span class="smalltext">(qui ne font partie d\'aucun groupe)</span>';
$txt['maintain_members_since1'] = 'Effacer les comptes des membres qui n\'ont pas';
$txt['maintain_members_since2'] = '&nbsp;depuis';
$txt['maintain_members_since3'] = '&nbsp;jours.<br />';
$txt['maintain_members_activated'] = 'activ� leur compte';
$txt['maintain_members_logged_in'] = 'cherch� � se connecter';
$txt['maintain_members_all'] = 'Tous les groupes de membres';
$txt['maintain_members_choose'] = 'Groupes s�lectionn�s';
$txt['maintain_members_confirm'] = '�tes-vous s�r de vouloir supprimer ces comptes ?\\n\\nVous ne pourrez pas annuler cette op�ration !';

$txt['utf8_title'] = 'Convertir la base de donn�es et les donn�es en UTF-8';
$txt['utf8_introduction'] = 'L\'UTF-8 est un jeu de caract�res international couvrant presque toutes les langues du monde. Convertir votre base de donn�es et vos donn�es en UTF-8 peut vous permettre un support plus facile de langues multiples sur le m�me forum. Cela peut aussi am�liorer la recherche avec des langues bas�es sur des caract�res non latins.';
$txt['utf8_warning'] = 'Si vous voulez convertir vos donn�es et votre base en UTF-8, veuillez faire attention � ce qui suit&nbsp;:
<ul class="normallist">
	<li>Convertir des jeux de caract�res peut �tre <em>nocif</em> pour vos donn�es&nbsp;! Soyez s�r d\'avoir fait une sauvegarde de votre base de donn�es <em>avant</em> de convertir.</li>
	<li>Comme l\'UTF-8 est un jeu de caract�res plus riche que les autres, il est impossible de revenir en arri�re. � part restaurer la sauvegarde de votre base de donn�es faite avant la conversion.</li>
	<li>Apr�s avoir converti votre base de donn�es et vos donn�es en UTF-8, vous devrez utiliser des fichiers de langue compatibles UTF-8.</li>
</ul>';
$txt['utf8_charset_not_supported'] = 'La conversion en UTF-8 de %1$s n\'est pas support�e.';
$txt['utf8_detected_charset'] = 'En se basant sur vos fichiers de langue par d�faut (\'%1$s\'), le jeu de caract�res de vos donn�es devrait �tre \'%2$s\'.';
$txt['utf8_already_utf8'] = 'Votre base de donn�es et vos donn�es semblent d�j� �tre de type UTF-8. Aucune conversion n\'est n�cessaire.';
$txt['utf8_source_charset'] = 'Jeu de caract�res des donn�es';
$txt['utf8_proceed'] = 'Lancer';
$txt['utf8_database_charset'] = 'Jeu de caract�res de la base de donn�es';
$txt['utf8_target_charset'] = 'Convertir les donn�es et la base de donn�es en';
$txt['utf8_utf8'] = 'UTF-8';
$txt['utf8_db_version_too_low'] = 'La version de MySQL que votre serveur de base de donn�es utilise n\'est pas assez r�cente pour supporter l\'UTF-8 convenablement. La version 4.1.2 (ou au-del�) est requise.';
$txt['utf8_cannot_convert_fulltext'] = 'Votre table de messages utilise un index fulltext pour les recherches. Vous ne pourrez la convertir en UTF-8 qu\'apr�s suppression de cet index. Vous pourrez le recr�er apr�s la conversion.';

$txt['text_title'] = 'Convertir en TEXT';
$txt['mediumtext_title'] = 'Convertir en MEDIUMTEXT';
$txt['mediumtext_introduction'] = 'La table par d�faut des messages peut contenir des messages allant au-del� de 65535 caract�res. Afin de pouvoir stocker des textes plus grands, la colonne doit �tre convertie en "MEDIUMTEXT". Il est �galement possible de reconvertir la colonne en TEXT (cette op�ration r�duirait l\'espace occup�e), mais <strong>seulement si</strong> aucun message de votre base de donn�es ne d�passe 65535 caract�res. Cette condition sera v�rifi�e avant la conversion.';
$txt['body_checking_introduction'] = 'Cette fonction va convertir la colonne de votre base de donn�es qui contient le texte de vos messages vers le format "TEXT" (actuellement il est en "MEDIUMTEXT"). Cette op�ration va permettre de r�duire l�g�rement la quantit� d\'espace occup�e par chaque message (1 octet par message). Si un message stock� dans la base d�passe les 65535 caract�res, il sera tronqu� et une partie du texte sera perdu.';
$txt['exceeding_messages'] = 'Les messages suivants ont plus de 65535 caract�res et seront tronqu�s par le processus :';
$txt['exceeding_messages_morethan'] = 'Et d\'autres %1$d';
$txt['convert_to_text'] = 'Aucun message ayant plus de 65535 caract�res. Vous pouvez effectuer la conversion en toute s�curit� sans perdre une partie du texte.';
$txt['convert_to_suggest_text'] = 'La colonne contenant le corps des messages de votre base de donn�es et actuellement en MEDIUMTEXT, sauf que la longueur maximale autoris�e qui est configur�e pour les messages est en dessous des 65535 caract�res. Vous pouvez gagner un peu d\'espace en convertissant la colonne en type TEXT.';

$txt['entity_convert_title'] = 'Convertir les entit�s HTML au format UTF-8';
$txt['entity_convert_only_utf8'] = 'La base de donn�es a besoin d\'�tre au format UTF-8 avant de pouvoir convertir les entit�s HTML en UTF-8';
$txt['entity_convert_introduction'] = 'Cette fonction va convertir tous les caract�res qui sont enregistr�s dans la base de donn�es avec des entit�s HTML, vers le format UTF-8. C\'est notamment utile lorsque vous venez de convertir votre forum � partir d\'un jeu de caract�res comme l\'ISO-8859-1 alors que des caract�res de type non latins sont utilis�s sur le forum, le navigateur renvoyant tous ces caract�res comme des entit�s HTML. Par exemple, l\'entit� HTML &amp;#945; correspond � la lettre grecque &#945; (alpha). Convertir les entit�s en UTF-8 am�liorera les r�sultats de recherche tout en r�duisant la taille de stockage.';
$txt['entity_convert_proceed'] = 'Lancer';

// Move topics out.
$txt['move_topics_maintenance'] = 'D�placer les sujets';
$txt['move_topics_select_board'] = 'Choisir la section';
$txt['move_topics_from'] = 'D�placer les fils de';
$txt['move_topics_to'] = 'vers';
$txt['move_topics_now'] = 'D�placer maintenant';
$txt['move_topics_confirm'] = '�tes-vous s�r de vouloir d�placer TOUS les sujets de &quot;%board_from%&quot; vers &quot;%board_to%&quot;&nbsp;?';
$txt['move_topics_older_than'] = 'Move topics not posted in for ';
$txt['move_type_sticky'] = 'Sujets �pingl�s';
$txt['move_type_locked'] = 'Sujets bloqu�s';
$txt['move_zero_all'] = 'Entrer 0 pour d�placer tous les sujets';

$txt['maintain_reattribute_posts'] = 'R�attribuer des messages aux utilisateurs';
$txt['reattribute_guest_posts'] = 'Attribuer les messages <em>invit�</em> utilisant&nbsp;';
$txt['reattribute_email'] = 'pour adresse e-mail';
$txt['reattribute_username'] = 'pour nom';
$txt['reattribute_current_member'] = 'Les attribuer � ce membre&nbsp;';
$txt['reattribute_increase_posts'] = 'Les compter dans le nombre de messages envoy�s par le membre';
$txt['reattribute'] = 'R�attribuer';
// Don't use entities in the below string.
$txt['reattribute_confirm'] = '�tes-vous s�r de vouloir attribuer tous les messages d\\\'invit�s utilisant %type% "%find%" au membre "%member_to%" ?';
$txt['reattribute_confirm_username'] = 'pour nom d\'utilisateur';
$txt['reattribute_confirm_email'] = 'pour adresse e-mail';
$txt['reattribute_cannot_find_member'] = 'Impossible de trouver le membre � qui r�attribuer les messages.';

$txt['maintain_recountposts'] = 'Recompter les messages utilisateur';
$txt['maintain_recountposts_info'] = 'Ex�cuter cette t�che de maintenance pour mettre � jour le compteur du total des messages des utilisateurs. Il recomptera tous les messages (comptables) cr��s par chaque utilisateur et mettra � jour leur total du nombre de messages sur leur profil';

$txt['safe_mode_enabled'] = '<a href="http://php.net/manual/en/features.safe-mode.php">safe_mode</a> est actif sur votre serveur !<br />La sauvegarde effectu�e avec cet utilitaire ne peut �tre consid�r�e comme fiable !';
$txt['use_external_tool'] = 'Veuillez pensez � utiliser un outil externe pour sauvegarder votre base de donn�es, toute sauvegarde cr��e avec cet utilitaire ne pourra �tre consid�r�e comme 100% fiable.';
$txt['zipped_file'] = 'Si vous le voulez, vous pouvez cr�er une sauvegarde compress�e (zipp�e).';
$txt['plain_text'] = 'La meilleure m�thode pour sauvegarder votre base de donn�es et de cr�er un fichier texte brut, une archive compress�e peut ne pas �tre compl�tement fiable.';
$txt['enable_maintenance1'] = 'Vu la taille de votre forum, il est recommand� de mettre votre forum en "mode maintenance" avant de d�marrer la sauvegarde.';
$txt['enable_maintenance2'] = 'Pour continuer, � cause de la taille de votre forum, veuillez mettre votre forum en "mode maintenance".';

?>