<?php
// Version: 2.1 Beta 2; Search

$txt['set_parameters'] = 'R�gler les param�tres de recherche';
$txt['choose_board'] = 'Choisir une section dans laquelle rechercher, ou chercher dans toutes les sections';
$txt['all_words'] = 'contenant tous ces mots';
$txt['any_words'] = 'contenant n\'importe lequel de ces mots';
$txt['by_user'] = 'Par l\'utilisateur';

$txt['search_post_age'] = '�ge du message';
$txt['search_between'] = 'entre';
$txt['search_and'] = 'et';
$txt['search_options'] = 'Options';
$txt['search_show_complete_messages'] = 'Afficher les r�sultats comme des messages';
$txt['search_subject_only'] = 'Titres des sujets seulement';
$txt['search_relevance'] = 'Pertinence';
$txt['search_date_posted'] = 'Date d\'�criture';
$txt['search_order'] = 'Ordre de recherche';
$txt['search_orderby_relevant_first'] = 'Les r�sultats les plus pertinents en premier';
$txt['search_orderby_large_first'] = 'Les plus longs sujets en premier';
$txt['search_orderby_small_first'] = 'Les plus courts sujets en premier';
$txt['search_orderby_recent_first'] = 'Les plus r�cents sujets en premier';
$txt['search_orderby_old_first'] = 'Les plus anciens sujets en premier';
$txt['search_visual_verification_label'] = 'V�rification';
$txt['search_visual_verification_desc'] = 'Veuillez entrer le code contenu dans l\'image ci-dessus pour utiliser la recherche.';

$txt['search_specific_topic'] = 'Chercher les messages seulement dans le sujet';

$txt['mods_cat_search'] = 'Rechercher';
$txt['groups_search_posts'] = 'Groupes de membres ayant acc�s � la fonction de recherche';
$txt['search_results_per_page'] = 'Nombre de r�sultats par page';
$txt['search_weight_frequency'] = 'R�sultats affich�s selon un facteur de fr�quence de correspondance';
$txt['search_weight_age'] = 'R�sultats affich�s selon un facteur d\'anciennet� du dernier message corespondant dans un sujet';
$txt['search_weight_length'] = 'R�sultats affich�s selon un facteur de longueur du sujet';
$txt['search_weight_subject'] = 'R�sultats affich�s selon un facteur de correspondance avec le sujet';
$txt['search_weight_first_message'] = 'R�sultats affich�s selon un facteur de correspondance dans le premier message d\'une discussion';
$txt['search_weight_sticky'] = 'R�sultats affich�s selon un facteur de correspondance dans un sujet �pingl�';

$txt['search_settings_desc'] = 'Ici vous pouvez changer les r�glages de base de la fonction de recherche.';
$txt['search_settings_title'] = 'Fonction de recherche - r�glages';

$txt['search_weights_desc'] = 'Ici vous pouvez changer les �l�ments prioritaires dans la recherche par pertinence.';
$txt['search_weights_title'] = 'Recherche - facteurs';
$txt['search_weights_total'] = 'Total';
$txt['search_weights_save'] = 'Enregistrer';

$txt['search_method_desc'] = 'Ici vous pouvez param�trer la fa�on dont la recherche est faite.';
$txt['search_method_title'] = 'Recherche - m�thode';
$txt['search_method_save'] = 'Enregistrer';
$txt['search_method_messages_table_space'] = 'Espace utilis� par les messages du forum dans la base de donn�es';
$txt['search_method_messages_index_space'] = 'Espace utilis� pour l\'indexation des messages dans la base de donn�es';
$txt['search_method_kilobytes'] = 'Ko';
$txt['search_method_fulltext_index'] = 'Indexation du texte';
$txt['search_method_no_index_exists'] = 'n\'existe pas actuellement';
$txt['search_method_fulltext_create'] = 'cr�er un index en texte complet';
$txt['search_method_fulltext_cannot_create'] = 'ne peut �tre cr�� car le message d�passe la taille de 65,535 caract�res ou la table n\'est pas du type MyISAM';
$txt['search_method_index_already_exists'] = 'existe d�j�';
$txt['search_method_fulltext_remove'] = 'enlever l\'index de texte';
$txt['search_method_index_partial'] = 'partiellement cr��';
$txt['search_index_custom_resume'] = 'reprendre';
// This string is used in a javascript confirmation popup; don't use entities.
$txt['search_method_fulltext_warning'] = 'Afin de pouvoir utiliser la recherche en texte, vous devrez d\\\'abord cr�er un index !';

$txt['search_index'] = 'Index de recherche';
$txt['search_index_none'] = 'Aucun index';
$txt['search_index_custom'] = 'Index personnalis�';
$txt['search_index_sphinx'] = 'Sphinx';
$txt['search_index_sphinx_desc'] = 'Le panneau administrateur permet uniquement de basculer entre les index de recherche. Pour ajuster plus en d�tail les r�glages Sphinx, utilisez l\'utilitaire sphinx_config.php.';
$txt['search_index_label'] = 'Index';
$txt['search_index_size'] = 'Taille';
$txt['search_index_create_custom'] = 'cr�er un index personnalis�';
$txt['search_index_custom_remove'] = 'supprimer un index personnalis�';
// This string is used in a javascript confirmation popup; don't use entities.
$txt['search_index_custom_warning'] = 'Pour pouvoir utiliser un index de recherche personnalis�, vous devez d\\\'abord cr�er un index personnalis� !';

$txt['search_force_index'] = 'Forcer l\'utilisation d\'un index personnalis�';
$txt['search_match_words'] = 'Ne chercher que sur des mots complets';
$txt['search_max_results'] = 'Nombre maximal de r�sultats � afficher';
$txt['search_max_results_disable'] = '(0&nbsp;: aucune limite)';
$txt['search_floodcontrol_time'] = 'D�lai n�cessaire entre les recherches d\'un m�me membre';
$txt['search_floodcontrol_time_desc'] = '(en secondes, 0 pour pas de limite)';

$txt['search_create_index'] = 'Cr�er un index';
$txt['search_create_index_why'] = 'Pourquoi cr�er un index de recherche&nbsp;?';
$txt['search_create_index_start'] = 'Cr�er';
$txt['search_predefined'] = 'Profil pr�-d�fini';
$txt['search_predefined_small'] = 'Index de petite taille';
$txt['search_predefined_moderate'] = 'Index de taille moyenne';
$txt['search_predefined_large'] = 'Index de taille importante';
$txt['search_create_index_continue'] = 'Poursuivre';
$txt['search_create_index_not_ready'] = 'SMF est en train de cr�er un index de recherche de vos messages. Afin d\'�viter une surcharge du serveur, le processus a �t� temporairement interrompu. Il devrait reprendre automatiquement dans quelques secondes. Si ce n\'est pas le cas, veuillez cliquer sur &quot;Poursuivre&quot;.';
$txt['search_create_index_progress'] = 'Progression';
$txt['search_create_index_done'] = 'Index de recherche personnalis� cr��&nbsp;!';
$txt['search_create_index_done_link'] = 'Poursuivre';
$txt['search_double_index'] = 'Vous avez cr�� deux index pour la table des messages. Pour de meilleures performances, il est conseill� de supprimer l\'un de ces deux index.';

$txt['search_error_indexed_chars'] = 'Nombre invalide de caract�res ind�x�s. Pour un index performant, au moins 3 caract�res sont n�cessaires.';
$txt['search_error_max_percentage'] = 'Pourcentage invalide de termes � ignorer. Veuillez utiliser une valeur d\'au moins 5%.';
$txt['error_string_too_long'] = 'La cha�ne de caract�res � rechercher doit �tre plus petite que %1$d caract�res.';

$txt['search_adjust_query'] = 'Ajuster les param�tres de recherche';
$txt['search_warning_ignored_word'] = 'Le terme suivant a �t� ignor� dans votre recherche parce qu\'il est trop court';
$txt['search_warning_ignored_words'] = 'Les termes suivants ont �t�s ignor�s parce qu\'ils sont trop courts';
$txt['search_adjust_submit'] = 'R�viser la recherche';
$txt['search_did_you_mean'] = 'Vous avez peut-�tre voulu chercher';

$txt['search_example'] = '<em>ex&nbsp;:</em> Orwell "La Ferme des animaux" -film';

$txt['search_engines_description'] = 'D\'ici, vous pouvez d�cider dans quelle mesure vous voulez surveiller les moteurs de recherche lors de leur indexation du forum, ainsi que consulter le journal des visites de ces moteurs.';
$txt['spider_mode'] = 'Surveillance des Moteurs de recherche<div class="smalltext">Note&nbsp;: une surveillance plus �lev�e augmente les ressources serveur n�cessaires.</div>';
$txt['spider_mode_note'] = 'Notez qu\'un niveau "�lev�" ou "tr�s �lev�" d\'indexation enregistrera toutes les actions des robots. Seul le niveau "tr�s �lev�" enregistrera les d�tails des actions de tous les robots.';
$txt['spider_mode_off'] = 'D�sactiv�e';
$txt['spider_mode_standard'] = 'Standard - Le journal note l\'activit� de base du robot.';
$txt['spider_mode_high'] = 'Haute - Fournit des statistiques plus pr�cises.';
$txt['spider_mode_vhigh'] = 'Agressive';
$txt['spider_settings_desc'] = 'Vous pouvez changer les r�glages de surveillance des robots � partir de cette page. Notez que si vous voulez activer le d�lestage automatique des journaux de visites, c\'est par <a href="%1$s">ici</a> que �a se passe';

$txt['spider_group'] = 'Utiliser les permissions restrictives du groupe<div class="smalltext">Pour emp�cher les robots d\'indexer certaines pages.</div>';
$txt['spider_group_note'] = 'Pour vous permettre d’arr�ter l\'indexation de certaines pages par les robots.';
$txt['spider_group_none'] = 'D�sactiv�';

$txt['show_spider_online'] = 'Montrer les robots sur la page &quot;Qui est en ligne&quot;';
$txt['show_spider_online_no'] = 'Pas du tout';
$txt['show_spider_online_summary'] = 'Montrer le nombre de robots';
$txt['show_spider_online_detail'] = 'Montrer le nom des robots';
$txt['show_spider_online_detail_admin'] = 'Montrer le nom des robots, mais juste � l\'administrateur';

$txt['spider_name'] = 'Nom du Robot';
$txt['spider_last_seen'] = 'Derni�re activit�';
$txt['spider_last_never'] = 'Jamais';
$txt['spider_agent'] = 'User-Agent';
$txt['spider_ip_info'] = 'Adresses IP';
$txt['spiders_add'] = 'Ajouter un nouveau Robot';
$txt['spiders_edit'] = 'Modifier';
$txt['spiders_remove_selected'] = 'Supprimer les robots s�lectionn�s';
$txt['spider_remove_selected_confirm'] = '�tes-vous s�r de vouloir supprimer ces robots?-n-Toutes les statistiques associ�es seront aussi effac�es!';
$txt['spiders_no_entries'] = 'Aucun robot configur� pour le moment.';

$txt['add_spider_desc'] = 'D\'ici, vous pouvez modifier les param�tres permettant de reconna�tre un robot. Si le User-Agent ou l\'adresse IP d\'un invit� correspond � ce qui est entr� ci-dessous, il sera consid�r� comme un robot de moteur de recherche et surveill� comme demand� dans les pr�f�rences du forum.';
$txt['spider_name_desc'] = 'Nom avec lequel le robot sera r�f�renc�.';
$txt['spider_agent_desc'] = 'User-Agent associ� � ce robot.';
$txt['spider_ip_info_desc'] = 'S�par�es par des virgules, la liste des adresses IP associ�es � ce robot.';

$txt['spider'] = 'Robot';
$txt['spider_time'] = 'Heure';
$txt['spider_viewing'] = 'A visit�';
$txt['spider_logs_empty'] = 'Le journal de robots est vide pour le moment.';
$txt['spider_logs_info'] = 'Notez que l\'archivage des actions de robot ne se produit que si le suivi est r�gl� sur "�lev�" ou "tr�s �lev�". Le d�tail de toutes les actions n\'est enregistr� que si le suivi est fix� � "tr�s �lev�".';
$txt['spider_disabled'] = 'D�sactiv�';
$txt['spider_log_empty_log'] = 'Vider le journal';
$txt['spider_log_empty_log_confirm'] = '�tes vous certain de vouloir vider le journal completement';

$txt['spider_logs_delete'] = 'Effacer les Entr�es';
$txt['spider_logs_delete_older'] = 'Effacer toutes les entr�es ant�rieures �';
$txt['spider_logs_delete_day'] = 'jours.';
$txt['spider_logs_delete_submit'] = 'Effacer';

$txt['spider_stats_delete_older'] = 'Supprimer toutes statistiques concernant les robots non vus en %1$s jours.';

// Don't use entities in the below string.
$txt['spider_logs_delete_confirm'] = '�tes-vous s�r de vouloir vider le journal d\'activit� des robots ?';

$txt['spider_stats_select_month'] = 'Aller au mois de';
$txt['spider_stats_page_hits'] = 'Pages visit�es';
$txt['spider_stats_no_entries'] = 'Pas de statistiques disponibles sur les robots pour le moment.';

?>