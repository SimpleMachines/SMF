<?php
// Version: 2.1 Beta 2; ManageMaintenance

$txt['repair_zero_ids'] = 'Des fils et/ou messages portant l\'ID 0 ont été trouvés.';
$txt['repair_missing_topics'] = 'Le message #%1$d est dans un sujet non existant (#%2$d).';
$txt['repair_missing_messages'] = 'Le sujet #%1$d ne contient aucun message réel.';
$txt['repair_stats_topics_1'] = 'Le sujet #%1$d débute par un message dont l\'ID (%2$d) est incorrect.';
$txt['repair_stats_topics_2'] = 'Le sujet #%1$d se termine par un message dont l\'ID (%2$d) est incorrect.';
$txt['repair_stats_topics_3'] = 'Le sujet #%1$d indique un nombre de réponses incorrect, %2$d.';
$txt['repair_stats_topics_4'] = 'Le sujet #%1$d indique un nombre de messages non approuvés incorrect, %2$d.';
$txt['repair_stats_topics_5'] = 'Le sujet #%1$d indique une valeur &quot;approuvé&quot; incorrecte.';
$txt['repair_missing_boards'] = 'Le sujet #%1$d a pour section parente #%2$d, qui n\'existe pas.';
$txt['repair_missing_categories'] = 'La section #%1$d a la valeur #%2$d comme catégorie parente, qui n\'existe pas.';
$txt['repair_missing_posters'] = 'Le message #%1$d est posté par le membre #%2$d, qui n\'existe pas ou plus.';
$txt['repair_missing_parents'] = 'La section #%1$d a pour parente la section #%2$d, qui n\'existe pas.';
$txt['repair_missing_polls'] = 'Le sujet #%1$d est lié à un sondage introuvable (#%2$d).';
$txt['repair_polls_missing_topics'] = 'Le sondage #%1$d est lié à un sujet inexistant, #%2$d.';
$txt['repair_poll_options_missing_poll'] = 'Poll #%1$d has %2$d voting options but no poll attached.';
$txt['repair_missing_calendar_topics'] = 'L\'événement #%1$d est lié à un sujet inexistant, #%2$d.';
$txt['repair_missing_log_topics'] = 'Le sujet #%1$d est indiqué comme étant lu par une ou plusieurs personnes alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_log_topics_members'] = 'Le membre #%1$d est indiqué comme ayant lu un ou plusieurs sujets alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_log_boards'] = 'La section #%1$d est indiquée comme ayant été lue par une ou plusieurs personnes alors qu\'elle n\'existe pas ou plus.';
$txt['repair_missing_log_boards_members'] = 'Le membre #%1$d est indiqué comme ayant lu une ou plusieurs sections alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_log_mark_read'] = 'La section #%1$d est indiquée comme ayant été lue par une ou plusieurs personnes alors qu\'elle n\'existe pas ou plus.';
$txt['repair_missing_log_mark_read_members'] = 'Le membre #%1$d est indiqué comme ayant lu une ou plusieurs sections qui n\'existent pas ou plus.';
$txt['repair_missing_pms'] = 'Le message personnel #%1$d a été envoyé à une ou plusieurs personnes, alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_recipients'] = 'Le membre #%1$d a reçu un ou plusieurs messages personnels alors qu\'il n\'existe pas ou plus.';
$txt['repair_missing_senders'] = 'Le message personnel #%1$d a été envoyé par le membre #%2$d, qui n\'existe pas ou plus.';
$txt['repair_missing_notify_members'] = 'Des notifications ont été demandées par le membre #%1$d, qui n\'existe pas ou plus.';
$txt['repair_missing_cached_subject'] = 'Le titre du sujet #%1$d n\'a pas été mis en cache.';
$txt['repair_missing_topic_for_cache'] = 'Le mot en cache \'%1$s\' est rattaché à un sujet inexistant.';
$txt['repair_missing_log_poll_member'] = 'Le sondage #%1$d a enregistré un vote par le membre #%2$d, qui n\'existe pas ou plus.';
$txt['repair_missing_log_poll_vote'] = 'Le membre #%1$d a participé au sondage #%2$d, qui n\'existe pas ou plus.';
$txt['repair_missing_thumbnail_parent'] = 'Une vignette existe, nommée %1$s, mais elle n\'a pas d\'image parente.';
$txt['repair_report_missing_comments'] = 'Le rapport #%1$d concernant le sujet &quot;%2$s&quot; n\'a pas de commentaire associé.';
$txt['repair_comments_missing_report'] = 'Le commentaire de rapport #%1$d soumis par %2$s n\'a pas de rapport associé.';
$txt['repair_group_request_missing_member'] = 'Une demande d\'adhésion à un groupe a été retrouvée pour un membre qui n\'existe plus, #%1$d.';
$txt['repair_group_request_missing_group'] = 'Une demande d\'adhésion à un groupe qui n\'existe plus (#%1$d) a été retrouvée.';

$txt['repair_currently_checking'] = 'Vérification de &quot;%1$s&quot;';
$txt['repair_currently_fixing'] = 'Réparation de &quot;%1$s&quot;';
$txt['repair_operation_zero_topics'] = 'Fils dont la valeur id_topic est à zéro par erreur';
$txt['repair_operation_zero_messages'] = 'Messages dont la valeur id_msg est à zéro par erreur';
$txt['repair_operation_missing_topics'] = 'Messages sans sujet associé';
$txt['repair_operation_missing_messages'] = 'Sujets sans aucun message';
$txt['repair_operation_stats_topics'] = 'Sujets où le premier ou dernier message n\'est pas le bon';
$txt['repair_operation_stats_topics2'] = 'Sujets où le nombre de réponses est erroné';
$txt['repair_operation_stats_topics3'] = 'Sujets où le nombre de réponses non approuvées est erroné';
$txt['repair_operation_missing_boards'] = 'Sujets dans une section inexistante';
$txt['repair_operation_missing_categories'] = 'Sections dans une catégorie inexistante';
$txt['repair_operation_missing_posters'] = 'Messages liés à des membres inexistants';
$txt['repair_operation_missing_parents'] = 'Sections liées à une section parente inexistante';
$txt['repair_operation_missing_polls'] = 'Sujets liés à un sondage inexistant';
$txt['repair_operation_missing_calendar_topics'] = 'Événements liés à un sujet inexistant';
$txt['repair_operation_missing_log_topics'] = 'Journaux de sujets liés à un sujet inexistant';
$txt['repair_operation_missing_log_topics_members'] = 'Journaux de sujets liés à un membre inexistant';
$txt['repair_operation_missing_log_boards'] = 'Journaux de sections liés à des sections inexistantes';
$txt['repair_operation_missing_log_boards_members'] = 'Journaux de sections liés à des membres inexistants';
$txt['repair_operation_missing_log_mark_read'] = 'Informations sur les messages lus liées &agrave une section inexistante';
$txt['repair_operation_missing_log_mark_read_members'] = 'Informations sur les messages lus liées à un membre inexistant';
$txt['repair_operation_missing_pms'] = 'Messages privés manquants malgré l\'existence de destinataires';
$txt['repair_operation_missing_recipients'] = 'Destinataires de MP liés à un membre inexistant';
$txt['repair_operation_missing_senders'] = 'Messages personnels liés à un membre inexistant';
$txt['repair_operation_missing_notify_members'] = 'Journaux de notification liés à un membre inexistant';
$txt['repair_operation_missing_cached_subject'] = 'Sujets absents du cache de recherche';
$txt['repair_operation_missing_topic_for_cache'] = 'Cache de recherche lié à un sujet inexistant';
$txt['repair_operation_missing_member_vote'] = 'Votes de sondage liés à des membres inexistants';
$txt['repair_operation_missing_log_poll_vote'] = 'Votes de sondage liés à des sondages inexistants';
$txt['repair_operation_report_missing_comments'] = 'Rapports de sujet sans commentaire associé';
$txt['repair_operation_comments_missing_report'] = 'Commentaires de rapport sans sujet associé';
$txt['repair_operation_group_request_missing_member'] = 'Demandes d\'adhésion sans membre associé';
$txt['repair_operation_group_request_missing_group'] = 'Demandes d\'adhésion à un groupe inexistant';

$txt['salvaged_category_name'] = 'Zone de Récupération';
$txt['salvaged_category_error'] = 'Impossible de créer la catégorie Zone de Récupération&nbsp;!';
$txt['salvaged_board_name'] = 'Sujets récupérés';
$txt['salvaged_board_description'] = 'Sujets créés pour les messages sans sujet (fil de discussion)';
$txt['salvaged_board_error'] = 'Impossible de créer le sujet récupéré&nbsp;!';
$txt['salvaged_poll_topic_name'] = 'Sondage récupéré';
$txt['salvaged_poll_message_body'] = 'Ce sondage a perdu son sujet associé.';
$txt['salvaged_poll_question'] = 'This poll was found without a question.';

$txt['database_optimize'] = 'Optimiser la base de données';
$txt['database_numb_tables'] = 'Votre base de données contient %1$d tables.';
$txt['database_optimize_attempt'] = 'Tente d\'optimiser votre base de données&#133;';
$txt['database_optimizing'] = 'Optimise %1$s&#133; %2$01.2f Ko optimisés.';
$txt['database_already_optimized'] = 'Toutes les tables étaient déjà optimisées.';
$txt['database_opimize_unneeded'] = 'Il n\'était pas nécessaire d\'optimiser les tables.';
$txt['database_optimized'] = ' table(s) optimisée(s).';
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
$txt['remove_selection'] = 'Enlever la sélection';
$txt['remove_filtered_results'] = 'Enlever tous les résultats filtrés';
$txt['sure_about_errorlog_remove'] = 'Êtes-vous sûr de vouloir enlever tous les messages d\\\'erreur&nbsp;?';
$txt['remove_selection_confirm'] = 'Etes-vous sûr de vouloir effacer les entrées sélectionnées ?';
$txt['remove_filtered_results_confirm'] = 'Etes-vous sûr de vouloir effacer les entrées filtrées ?';
$txt['reverse_direction'] = 'Inverser l\'ordre chronologique de la liste';
$txt['error_type'] = 'Type d\'erreur';
$txt['filter_only_type'] = 'Ne montrer que les erreurs de ce type';
$txt['filter_only_file'] = 'Ne montrer que les erreurs provenant de ce fichier';
$txt['apply_filter_of_type'] = 'Appliquer le filtre de type&nbsp;';

$txt['errortype_all'] = 'Toutes les erreurs';
$txt['errortype_general'] = 'Générale';
$txt['errortype_general_desc'] = 'Erreurs générales inclassables.';
$txt['errortype_critical'] = '<span style="color:red;">Critiques</span>';
$txt['errortype_critical_desc'] = 'Erreurs critiques. Elles nécessitent votre attention immédiate. Si vous ignorez ces erreurs, il se peut que votre forum soit en danger.';
$txt['errortype_database'] = 'Base de données';
$txt['errortype_database_desc'] = 'Erreurs causées par des reqêtes malformées. Notez-les et rapportez-les à l\'équipe de développement de SMF.';
$txt['errortype_undefined_vars'] = 'Éléments indéfinis';
$txt['errortype_undefined_vars_desc'] = 'Erreurs causées par l\'utilisation d\'une variable, d\'un index ou d\'un offset indéfinis.';
$txt['errortype_ban'] = 'Bannissements';
$txt['errortype_ban_desc'] = 'Un journal des utilisateurs bannis essayant d’accéder à votre forum. ';
$txt['errortype_template'] = 'Modèles';
$txt['errortype_template_desc'] = 'Erreurs liées au chargement d\'un modèle.';
$txt['errortype_user'] = 'Utilisateurs';
$txt['errortype_user_desc'] = 'Erreurs liées au comportement des utilisateurs. Par exemple, mot de passe erroné, tentative de connexion malgré un bannissement, et absence de permission pour une action spécifique.';
$txt['errortype_cron'] = 'Cron';
$txt['errortype_cron_desc'] = 'Errors resulting from background tasks.';
$txt['errortype_paidsubs'] = 'Paid Subs';
$txt['errortype_paidsubs_desc'] = 'Errors resulting from paid subscriptions, which can include notification of payment failures.';
$txt['errortype_backup'] = 'Backups';
$txt['errortype_backup_desc'] = 'Errors resulting from backing up files, which are usually messages explaining why the proceedure failed.';

$txt['maintain_recount'] = 'Recompter les totaux et statistiques des sections';
$txt['maintain_recount_info'] = 'Si le nombre total de messages sur un sujet ou dans votre boîte de réception est incorrect, cette fonction peut corriger tous les décomptes et statistiques pour vous.';
$txt['maintain_errors'] = 'Chercher et réparer les erreurs';
$txt['maintain_errors_info'] = 'Si, par exemple, des messages ou sujets ont disparu après un plantage serveur, cette fonction vous permettra peut-être de les récupérer.';
$txt['maintain_logs'] = 'Vider les journaux triviaux.';
$txt['maintain_logs_info'] = 'Cette fonction videra tous les journaux d\'importance mineure. À n\'utiliser qu\'en cas de problème, mais sans danger dans tous les cas.';
$txt['maintain_cache'] = 'Vider le cache de fichiers.';
$txt['maintain_cache_info'] = 'Cette fonction videra le cache de fichiers en cas de besoin.';
$txt['maintain_optimize'] = 'Optimiser toutes les tables pour une meilleure performance.';
$txt['maintain_optimize_info'] = 'Cette fonction vous permet d\'optimiser toutes les tables. Elle supprime les espaces inutilisés et améliore la taille et la vitesse de votre forum.';
$txt['maintain_version'] = 'Comparer tous les fichiers avec la version la plus récente.';
$txt['maintain_version_info'] = 'Cette tâche de maintenance vous permet de faire une comparaison détaillée des versions des fichiers du forum par rapport aux derniers fichiers officiels.';
$txt['maintain_run_now'] = 'Lancer maintenant';
$txt['maintain_return'] = 'Retourner à la maintenance du forum';

$txt['maintain_backup'] = 'Sauvegarder la base de données';
$txt['maintain_backup_info'] = 'Télécharger une copie de sauvegarde de la base de données de votre forum en cas d\'urgence.';
$txt['maintain_backup_struct'] = 'Sauvegarder la structure.';
$txt['maintain_backup_data'] = 'Sauver les données des tables (le contenu important).';
$txt['maintain_backup_gz'] = 'Compresser le fichier avec gzip.';
$txt['maintain_backup_save'] = 'Télécharger';

$txt['maintain_old'] = 'Enlever les anciens messages';
$txt['maintain_old_since_days1'] = 'Supprimer les sujets sans nouveaux messages depuis ';
$txt['maintain_old_since_days2'] = ' jours, qui sont&nbsp;:';
$txt['maintain_old_nothing_else'] = 'De n\'importe quel type.';
$txt['maintain_old_are_moved'] = 'Uniquement des notifications de déplacement.';
$txt['maintain_old_are_locked'] = 'Bloqués.';
$txt['maintain_old_are_not_stickied'] = 'Mais ne pas compter les sujets épinglés.';
$txt['maintain_old_all'] = 'Toutes les sections (cliquez pour choisir des sections spécifiques)';
$txt['maintain_old_choose'] = 'Choisir une section spécifique (cliquez pour les sélectionner toutes)';
$txt['maintain_old_remove'] = 'Enlever maintenant';
$txt['maintain_old_confirm'] = 'Êtes-vous sûr de vouloir supprimer maintenant les anciens messages&nbsp;?\\n\\nLe processus ne peut être inversé&nbsp;!';

$txt['maintain_old_drafts'] = 'Supprimer les brouillons anciens';
$txt['maintain_old_drafts_days'] = 'Supprimer tous les brouillons plus anciens que';
$txt['maintain_old_drafts_confirm'] = 'Etes-vous sûr de vouloir effacer les vieux brouillons maintenant ?\\n\\nIl n\\\'y aura pas de retour en arrière possible !';
$txt['maintain_members'] = 'Effacer les comptes inactifs';
$txt['maintain_members_ungrouped'] = 'Membres non groupés <span class="smalltext">(qui ne font partie d\'aucun groupe)</span>';
$txt['maintain_members_since1'] = 'Effacer les comptes des membres qui n\'ont pas';
$txt['maintain_members_since2'] = '&nbsp;depuis';
$txt['maintain_members_since3'] = '&nbsp;jours.<br />';
$txt['maintain_members_activated'] = 'activé leur compte';
$txt['maintain_members_logged_in'] = 'cherché à se connecter';
$txt['maintain_members_all'] = 'Tous les groupes de membres';
$txt['maintain_members_choose'] = 'Groupes sélectionnés';
$txt['maintain_members_confirm'] = 'Êtes-vous sûr de vouloir supprimer ces comptes ?\\n\\nVous ne pourrez pas annuler cette opération !';

$txt['utf8_title'] = 'Convertir la base de données et les données en UTF-8';
$txt['utf8_introduction'] = 'L\'UTF-8 est un jeu de caractères international couvrant presque toutes les langues du monde. Convertir votre base de données et vos données en UTF-8 peut vous permettre un support plus facile de langues multiples sur le même forum. Cela peut aussi améliorer la recherche avec des langues basées sur des caractères non latins.';
$txt['utf8_warning'] = 'Si vous voulez convertir vos données et votre base en UTF-8, veuillez faire attention à ce qui suit&nbsp;:
<ul class="normallist">
	<li>Convertir des jeux de caractères peut être <em>nocif</em> pour vos données&nbsp;! Soyez sûr d\'avoir fait une sauvegarde de votre base de données <em>avant</em> de convertir.</li>
	<li>Comme l\'UTF-8 est un jeu de caractères plus riche que les autres, il est impossible de revenir en arrière. à part restaurer la sauvegarde de votre base de données faite avant la conversion.</li>
	<li>Après avoir converti votre base de données et vos données en UTF-8, vous devrez utiliser des fichiers de langue compatibles UTF-8.</li>
</ul>';
$txt['utf8_charset_not_supported'] = 'La conversion en UTF-8 de %1$s n\'est pas supportée.';
$txt['utf8_detected_charset'] = 'En se basant sur vos fichiers de langue par défaut (\'%1$s\'), le jeu de caractères de vos données devrait être \'%2$s\'.';
$txt['utf8_already_utf8'] = 'Votre base de données et vos données semblent déjà être de type UTF-8. Aucune conversion n\'est nécessaire.';
$txt['utf8_source_charset'] = 'Jeu de caractêres des données';
$txt['utf8_proceed'] = 'Lancer';
$txt['utf8_database_charset'] = 'Jeu de caractères de la base de données';
$txt['utf8_target_charset'] = 'Convertir les données et la base de données en';
$txt['utf8_utf8'] = 'UTF-8';
$txt['utf8_db_version_too_low'] = 'La version de MySQL que votre serveur de base de données utilise n\'est pas assez récente pour supporter l\'UTF-8 convenablement. La version 4.1.2 (ou au-delà) est requise.';
$txt['utf8_cannot_convert_fulltext'] = 'Votre table de messages utilise un index fulltext pour les recherches. Vous ne pourrez la convertir en UTF-8 qu\'après suppression de cet index. Vous pourrez le recréer après la conversion.';

$txt['text_title'] = 'Convertir en TEXT';
$txt['mediumtext_title'] = 'Convertir en MEDIUMTEXT';
$txt['mediumtext_introduction'] = 'La table par défaut des messages peut contenir des messages allant au-delà de 65535 caractères. Afin de pouvoir stocker des textes plus grands, la colonne doit être convertie en "MEDIUMTEXT". Il est également possible de reconvertir la colonne en TEXT (cette opération réduirait l\'espace occupée), mais <strong>seulement si</strong> aucun message de votre base de données ne dépasse 65535 caractères. Cette condition sera vérifiée avant la conversion.';
$txt['body_checking_introduction'] = 'Cette fonction va convertir la colonne de votre base de données qui contient le texte de vos messages vers le format "TEXT" (actuellement il est en "MEDIUMTEXT"). Cette opération va permettre de réduire légèrement la quantité d\'espace occupée par chaque message (1 octet par message). Si un message stocké dans la base dépasse les 65535 caractères, il sera tronqué et une partie du texte sera perdu.';
$txt['exceeding_messages'] = 'Les messages suivants ont plus de 65535 caractères et seront tronqués par le processus :';
$txt['exceeding_messages_morethan'] = 'Et d\'autres %1$d';
$txt['convert_to_text'] = 'Aucun message ayant plus de 65535 caractères. Vous pouvez effectuer la conversion en toute sécurité sans perdre une partie du texte.';
$txt['convert_to_suggest_text'] = 'La colonne contenant le corps des messages de votre base de données et actuellement en MEDIUMTEXT, sauf que la longueur maximale autorisée qui est configurée pour les messages est en dessous des 65535 caractères. Vous pouvez gagner un peu d\'espace en convertissant la colonne en type TEXT.';

$txt['entity_convert_title'] = 'Convertir les entités HTML au format UTF-8';
$txt['entity_convert_only_utf8'] = 'La base de données a besoin d\'être au format UTF-8 avant de pouvoir convertir les entités HTML en UTF-8';
$txt['entity_convert_introduction'] = 'Cette fonction va convertir tous les caractères qui sont enregistrés dans la base de données avec des entités HTML, vers le format UTF-8. C\'est notamment utile lorsque vous venez de convertir votre forum à partir d\'un jeu de caractères comme l\'ISO-8859-1 alors que des caractères de type non latins sont utilisés sur le forum, le navigateur renvoyant tous ces caractères comme des entités HTML. Par exemple, l\'entité HTML &amp;#945; correspond à la lettre grecque &#945; (alpha). Convertir les entités en UTF-8 améliorera les résultats de recherche tout en réduisant la taille de stockage.';
$txt['entity_convert_proceed'] = 'Lancer';

// Move topics out.
$txt['move_topics_maintenance'] = 'Déplacer les sujets';
$txt['move_topics_select_board'] = 'Choisir la section';
$txt['move_topics_from'] = 'Déplacer les fils de';
$txt['move_topics_to'] = 'vers';
$txt['move_topics_now'] = 'Déplacer maintenant';
$txt['move_topics_confirm'] = 'Êtes-vous sûr de vouloir déplacer TOUS les sujets de &quot;%board_from%&quot; vers &quot;%board_to%&quot;&nbsp;?';
$txt['move_topics_older_than'] = 'Move topics not posted in for ';
$txt['move_type_sticky'] = 'Sujets épinglés';
$txt['move_type_locked'] = 'Sujets bloqués';
$txt['move_zero_all'] = 'Entrer 0 pour déplacer tous les sujets';

$txt['maintain_reattribute_posts'] = 'Réattribuer des messages aux utilisateurs';
$txt['reattribute_guest_posts'] = 'Attribuer les messages <em>invité</em> utilisant&nbsp;';
$txt['reattribute_email'] = 'pour adresse e-mail';
$txt['reattribute_username'] = 'pour nom';
$txt['reattribute_current_member'] = 'Les attribuer à ce membre&nbsp;';
$txt['reattribute_increase_posts'] = 'Les compter dans le nombre de messages envoyés par le membre';
$txt['reattribute'] = 'Réattribuer';
// Don't use entities in the below string.
$txt['reattribute_confirm'] = 'Êtes-vous sûr de vouloir attribuer tous les messages d\\\'invités utilisant %type% "%find%" au membre "%member_to%" ?';
$txt['reattribute_confirm_username'] = 'pour nom d\'utilisateur';
$txt['reattribute_confirm_email'] = 'pour adresse e-mail';
$txt['reattribute_cannot_find_member'] = 'Impossible de trouver le membre à qui réattribuer les messages.';

$txt['maintain_recountposts'] = 'Recompter les messages utilisateur';
$txt['maintain_recountposts_info'] = 'Exécuter cette tâche de maintenance pour mettre à jour le compteur du total des messages des utilisateurs. Il recomptera tous les messages (comptables) créés par chaque utilisateur et mettra à jour leur total du nombre de messages sur leur profil';

$txt['safe_mode_enabled'] = '<a href="http://php.net/manual/en/features.safe-mode.php">safe_mode</a> est actif sur votre serveur !<br />La sauvegarde effectuée avec cet utilitaire ne peut être considérée comme fiable !';
$txt['use_external_tool'] = 'Veuillez pensez à utiliser un outil externe pour sauvegarder votre base de données, toute sauvegarde créée avec cet utilitaire ne pourra être considérée comme 100% fiable.';
$txt['zipped_file'] = 'Si vous le voulez, vous pouvez créer une sauvegarde compressée (zippée).';
$txt['plain_text'] = 'La meilleure méthode pour sauvegarder votre base de données et de créer un fichier texte brut, une archive compressée peut ne pas être complètement fiable.';
$txt['enable_maintenance1'] = 'Vu la taille de votre forum, il est recommandé de mettre votre forum en "mode maintenance" avant de démarrer la sauvegarde.';
$txt['enable_maintenance2'] = 'Pour continuer, à cause de la taille de votre forum, veuillez mettre votre forum en "mode maintenance".';

?>