<?php
// Version: 2.1 Beta 2; Search

$txt['set_parameters'] = 'Régler les paramètres de recherche';
$txt['choose_board'] = 'Choisir une section dans laquelle rechercher, ou chercher dans toutes les sections';
$txt['all_words'] = 'contenant tous ces mots';
$txt['any_words'] = 'contenant n\'importe lequel de ces mots';
$txt['by_user'] = 'Par l\'utilisateur';

$txt['search_post_age'] = 'Âge du message';
$txt['search_between'] = 'entre';
$txt['search_and'] = 'et';
$txt['search_options'] = 'Options';
$txt['search_show_complete_messages'] = 'Afficher les résultats comme des messages';
$txt['search_subject_only'] = 'Titres des sujets seulement';
$txt['search_relevance'] = 'Pertinence';
$txt['search_date_posted'] = 'Date d\'écriture';
$txt['search_order'] = 'Ordre de recherche';
$txt['search_orderby_relevant_first'] = 'Les résultats les plus pertinents en premier';
$txt['search_orderby_large_first'] = 'Les plus longs sujets en premier';
$txt['search_orderby_small_first'] = 'Les plus courts sujets en premier';
$txt['search_orderby_recent_first'] = 'Les plus récents sujets en premier';
$txt['search_orderby_old_first'] = 'Les plus anciens sujets en premier';
$txt['search_visual_verification_label'] = 'Vérification';
$txt['search_visual_verification_desc'] = 'Veuillez entrer le code contenu dans l\'image ci-dessus pour utiliser la recherche.';

$txt['search_specific_topic'] = 'Chercher les messages seulement dans le sujet';

$txt['mods_cat_search'] = 'Rechercher';
$txt['groups_search_posts'] = 'Groupes de membres ayant accès à la fonction de recherche';
$txt['search_results_per_page'] = 'Nombre de résultats par page';
$txt['search_weight_frequency'] = 'Résultats affichés selon un facteur de fréquence de correspondance';
$txt['search_weight_age'] = 'Résultats affichés selon un facteur d\'ancienneté du dernier message corespondant dans un sujet';
$txt['search_weight_length'] = 'Résultats affichés selon un facteur de longueur du sujet';
$txt['search_weight_subject'] = 'Résultats affichés selon un facteur de correspondance avec le sujet';
$txt['search_weight_first_message'] = 'Résultats affichés selon un facteur de correspondance dans le premier message d\'une discussion';
$txt['search_weight_sticky'] = 'Résultats affichés selon un facteur de correspondance dans un sujet épinglé';

$txt['search_settings_desc'] = 'Ici vous pouvez changer les réglages de base de la fonction de recherche.';
$txt['search_settings_title'] = 'Fonction de recherche - réglages';

$txt['search_weights_desc'] = 'Ici vous pouvez changer les éléments prioritaires dans la recherche par pertinence.';
$txt['search_weights_title'] = 'Recherche - facteurs';
$txt['search_weights_total'] = 'Total';
$txt['search_weights_save'] = 'Enregistrer';

$txt['search_method_desc'] = 'Ici vous pouvez paramétrer la façon dont la recherche est faite.';
$txt['search_method_title'] = 'Recherche - méthode';
$txt['search_method_save'] = 'Enregistrer';
$txt['search_method_messages_table_space'] = 'Espace utilisé par les messages du forum dans la base de données';
$txt['search_method_messages_index_space'] = 'Espace utilisé pour l\'indexation des messages dans la base de données';
$txt['search_method_kilobytes'] = 'Ko';
$txt['search_method_fulltext_index'] = 'Indexation du texte';
$txt['search_method_no_index_exists'] = 'n\'existe pas actuellement';
$txt['search_method_fulltext_create'] = 'créer un index en texte complet';
$txt['search_method_fulltext_cannot_create'] = 'ne peut être créé car le message dépasse la taille de 65,535 caractères ou la table n\'est pas du type MyISAM';
$txt['search_method_index_already_exists'] = 'existe déjà';
$txt['search_method_fulltext_remove'] = 'enlever l\'index de texte';
$txt['search_method_index_partial'] = 'partiellement créé';
$txt['search_index_custom_resume'] = 'reprendre';
// This string is used in a javascript confirmation popup; don't use entities.
$txt['search_method_fulltext_warning'] = 'Afin de pouvoir utiliser la recherche en texte, vous devrez d\\\'abord créer un index !';

$txt['search_index'] = 'Index de recherche';
$txt['search_index_none'] = 'Aucun index';
$txt['search_index_custom'] = 'Index personnalisé';
$txt['search_index_sphinx'] = 'Sphinx';
$txt['search_index_sphinx_desc'] = 'Le panneau administrateur permet uniquement de basculer entre les index de recherche. Pour ajuster plus en détail les réglages Sphinx, utilisez l\'utilitaire sphinx_config.php.';
$txt['search_index_label'] = 'Index';
$txt['search_index_size'] = 'Taille';
$txt['search_index_create_custom'] = 'créer un index personnalisé';
$txt['search_index_custom_remove'] = 'supprimer un index personnalisé';
// This string is used in a javascript confirmation popup; don't use entities.
$txt['search_index_custom_warning'] = 'Pour pouvoir utiliser un index de recherche personnalisé, vous devez d\\\'abord créer un index personnalisé !';

$txt['search_force_index'] = 'Forcer l\'utilisation d\'un index personnalisé';
$txt['search_match_words'] = 'Ne chercher que sur des mots complets';
$txt['search_max_results'] = 'Nombre maximal de résultats à afficher';
$txt['search_max_results_disable'] = '(0&nbsp;: aucune limite)';
$txt['search_floodcontrol_time'] = 'Délai nécessaire entre les recherches d\'un même membre';
$txt['search_floodcontrol_time_desc'] = '(en secondes, 0 pour pas de limite)';

$txt['search_create_index'] = 'Créer un index';
$txt['search_create_index_why'] = 'Pourquoi créer un index de recherche&nbsp;?';
$txt['search_create_index_start'] = 'Créer';
$txt['search_predefined'] = 'Profil pré-défini';
$txt['search_predefined_small'] = 'Index de petite taille';
$txt['search_predefined_moderate'] = 'Index de taille moyenne';
$txt['search_predefined_large'] = 'Index de taille importante';
$txt['search_create_index_continue'] = 'Poursuivre';
$txt['search_create_index_not_ready'] = 'SMF est en train de créer un index de recherche de vos messages. Afin d\'éviter une surcharge du serveur, le processus a été temporairement interrompu. Il devrait reprendre automatiquement dans quelques secondes. Si ce n\'est pas le cas, veuillez cliquer sur &quot;Poursuivre&quot;.';
$txt['search_create_index_progress'] = 'Progression';
$txt['search_create_index_done'] = 'Index de recherche personnalisé créé&nbsp;!';
$txt['search_create_index_done_link'] = 'Poursuivre';
$txt['search_double_index'] = 'Vous avez créé deux index pour la table des messages. Pour de meilleures performances, il est conseillé de supprimer l\'un de ces deux index.';

$txt['search_error_indexed_chars'] = 'Nombre invalide de caractères indéxés. Pour un index performant, au moins 3 caractères sont nécessaires.';
$txt['search_error_max_percentage'] = 'Pourcentage invalide de termes à ignorer. Veuillez utiliser une valeur d\'au moins 5%.';
$txt['error_string_too_long'] = 'La chaîne de caractères à rechercher doit être plus petite que %1$d caractères.';

$txt['search_adjust_query'] = 'Ajuster les paramètres de recherche';
$txt['search_warning_ignored_word'] = 'Le terme suivant a été ignoré dans votre recherche parce qu\'il est trop court';
$txt['search_warning_ignored_words'] = 'Les termes suivants ont étés ignorés parce qu\'ils sont trop courts';
$txt['search_adjust_submit'] = 'Réviser la recherche';
$txt['search_did_you_mean'] = 'Vous avez peut-être voulu chercher';

$txt['search_example'] = '<em>ex&nbsp;:</em> Orwell "La Ferme des animaux" -film';

$txt['search_engines_description'] = 'D\'ici, vous pouvez décider dans quelle mesure vous voulez surveiller les moteurs de recherche lors de leur indexation du forum, ainsi que consulter le journal des visites de ces moteurs.';
$txt['spider_mode'] = 'Surveillance des Moteurs de recherche<div class="smalltext">Note&nbsp;: une surveillance plus élevée augmente les ressources serveur nécessaires.</div>';
$txt['spider_mode_note'] = 'Notez qu\'un niveau "élevé" ou "très élevé" d\'indexation enregistrera toutes les actions des robots. Seul le niveau "très élevé" enregistrera les détails des actions de tous les robots.';
$txt['spider_mode_off'] = 'Désactivée';
$txt['spider_mode_standard'] = 'Standard - Le journal note l\'activité de base du robot.';
$txt['spider_mode_high'] = 'Haute - Fournit des statistiques plus précises.';
$txt['spider_mode_vhigh'] = 'Agressive';
$txt['spider_settings_desc'] = 'Vous pouvez changer les réglages de surveillance des robots à partir de cette page. Notez que si vous voulez activer le délestage automatique des journaux de visites, c\'est par <a href="%1$s">ici</a> que ça se passe';

$txt['spider_group'] = 'Utiliser les permissions restrictives du groupe<div class="smalltext">Pour empêcher les robots d\'indexer certaines pages.</div>';
$txt['spider_group_note'] = 'Pour vous permettre d’arrêter l\'indexation de certaines pages par les robots.';
$txt['spider_group_none'] = 'Désactivé';

$txt['show_spider_online'] = 'Montrer les robots sur la page &quot;Qui est en ligne&quot;';
$txt['show_spider_online_no'] = 'Pas du tout';
$txt['show_spider_online_summary'] = 'Montrer le nombre de robots';
$txt['show_spider_online_detail'] = 'Montrer le nom des robots';
$txt['show_spider_online_detail_admin'] = 'Montrer le nom des robots, mais juste à l\'administrateur';

$txt['spider_name'] = 'Nom du Robot';
$txt['spider_last_seen'] = 'Dernière activité';
$txt['spider_last_never'] = 'Jamais';
$txt['spider_agent'] = 'User-Agent';
$txt['spider_ip_info'] = 'Adresses IP';
$txt['spiders_add'] = 'Ajouter un nouveau Robot';
$txt['spiders_edit'] = 'Modifier';
$txt['spiders_remove_selected'] = 'Supprimer les robots sélectionnés';
$txt['spider_remove_selected_confirm'] = 'Êtes-vous sûr de vouloir supprimer ces robots?-n-Toutes les statistiques associées seront aussi effacées!';
$txt['spiders_no_entries'] = 'Aucun robot configuré pour le moment.';

$txt['add_spider_desc'] = 'D\'ici, vous pouvez modifier les paramètres permettant de reconnaître un robot. Si le User-Agent ou l\'adresse IP d\'un invité correspond à ce qui est entré ci-dessous, il sera considéré comme un robot de moteur de recherche et surveillé comme demandé dans les préférences du forum.';
$txt['spider_name_desc'] = 'Nom avec lequel le robot sera référencé.';
$txt['spider_agent_desc'] = 'User-Agent associé à ce robot.';
$txt['spider_ip_info_desc'] = 'Séparées par des virgules, la liste des adresses IP associées à ce robot.';

$txt['spider'] = 'Robot';
$txt['spider_time'] = 'Heure';
$txt['spider_viewing'] = 'A visité';
$txt['spider_logs_empty'] = 'Le journal de robots est vide pour le moment.';
$txt['spider_logs_info'] = 'Notez que l\'archivage des actions de robot ne se produit que si le suivi est réglé sur "élevé" ou "très élevé". Le détail de toutes les actions n\'est enregistré que si le suivi est fixé à "très élevé".';
$txt['spider_disabled'] = 'Désactivé';
$txt['spider_log_empty_log'] = 'Vider le journal';
$txt['spider_log_empty_log_confirm'] = 'Êtes vous certain de vouloir vider le journal completement';

$txt['spider_logs_delete'] = 'Effacer les Entrées';
$txt['spider_logs_delete_older'] = 'Effacer toutes les entrées antérieures à';
$txt['spider_logs_delete_day'] = 'jours.';
$txt['spider_logs_delete_submit'] = 'Effacer';

$txt['spider_stats_delete_older'] = 'Supprimer toutes statistiques concernant les robots non vus en %1$s jours.';

// Don't use entities in the below string.
$txt['spider_logs_delete_confirm'] = 'Êtes-vous sûr de vouloir vider le journal d\'activité des robots ?';

$txt['spider_stats_select_month'] = 'Aller au mois de';
$txt['spider_stats_page_hits'] = 'Pages visitées';
$txt['spider_stats_no_entries'] = 'Pas de statistiques disponibles sur les robots pour le moment.';

?>