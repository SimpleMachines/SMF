<?php
// Version: 2.1 Beta 2; PersonalMessage

global $context;

// Things for the popup
$txt['pm_unread'] = 'Non lu';
$txt['pm_sent_short'] = 'Envoy�';
$txt['pm_new_short'] = 'Nouveau';
$txt['pm_drafts_short'] = 'Brouillons';
$txt['pm_settings_short'] = 'R�glages';
$txt['pm_no_unread'] = 'Aucun message non lu.';
$txt['pm_was_sent_to_you'] = 'Vous avez re�u un message.';
$txt['pm_you_were_replied_to'] = 'Une r�ponse � un de vos message a �t� envoy�e.';

$txt['pm_inbox'] = 'Accueil des messages personnels';
$txt['send_message'] = 'Envoyer un message';
$txt['pm_add'] = 'Ajouter';
$txt['make_bcc'] = 'Ajouter un BCC';
$txt['pm_to'] = '�&nbsp;';
$txt['pm_bcc'] = 'Bcc&nbsp;';
$txt['inbox'] = 'R�ception';
$txt['conversation'] = 'Conversation';
$txt['messages'] = 'Messages';
$txt['sent_items'] = 'Messages envoy�s';
$txt['new_message'] = 'Nouveau message';
$txt['delete_message'] = 'Effacer des messages';
// Don't translate "PMBOX" in this string.
$txt['delete_all'] = 'Effacer tous les messages dans votre PMBOX';
$txt['delete_all_confirm'] = '�tes vous s�r de vouloir effacer tous les messages&nbsp;?';
$txt['recipient'] = 'Destinataire';

$txt['delete_selected_confirm'] = '�tes-vous s�r de vouloir effacer tous les messages personnels s�lectionn�s&nbsp;?';

$txt['sent_to'] = 'Envoy� �';
$txt['reply_to_all'] = 'R�pondre � tous';
$txt['delete_conversation'] = 'Supprimer Conversation';
$txt['remove_conversation'] = 'Supprimer tous les messages dans cette conversation?';

$txt['pm_capacity'] = 'Capacit�';
$txt['pm_currently_using'] = '%1$s messages, %2$s%% pleine.';
$txt['pm_sent'] = 'Votre message a �t� envoy�.';

$txt['pm_error_user_not_found'] = 'Impossible de trouver le membre \'%1$s\'.';
$txt['pm_error_ignored_by_user'] = 'Le membre \'%1$s\' a bloqu� votre message personnel.';
$txt['pm_error_data_limit_reached'] = 'Le message n\'a pas pu �tre envoy� � \'%1$s\' car sa bo�te de r�ception est pleine&nbsp;!';
$txt['pm_error_user_cannot_read'] = 'L\'utilisateur \'%1$s\' ne peut pas recevoir de messages personnels.';
$txt['pm_successfully_sent'] = 'Le message a �t� envoy� � \'%1$s\'.';
$txt['pm_send_report'] = 'Rapport d\'envoi';
$txt['pm_undisclosed_recipients'] = 'Destinataires non r�v�l�s';
$txt['pm_too_many_recipients'] = 'Vous ne pouvez pas envoyer de messages personnels � plus de %1$d destinataire(s) � la fois.';

$txt['pm_read'] = 'Lu';
$txt['pm_replied'] = 'R�pondu �';

// Message Pruning.
$txt['pm_prune'] = 'Purger la bo�te';
$txt['pm_prune_desc1'] = 'Effacer tous les messages personnels ant�rieurs �';
$txt['pm_prune_desc2'] = 'jours.';
$txt['pm_prune_warning'] = '�tes-vous certain de vouloir purger vos messages personnels? Ils ne pourront plus �tre restaur�s.';
$txt['pm_remove_all'] = 'Supprimer tous vos messages personnels (ceci videra votre boite de r�ception et d\'envois). ';
$txt['pm_remove_all_warning'] = '�tes vous absolument certain de vouloir supprimer tous vos messages personnels? Ils ne pourront plus �tre restaur�s.';
$txt['delete_all_prune'] = 'Tout supprimer';

// Actions Drop Down.
$txt['pm_actions_title'] = 'Actions additionnelles';
$txt['pm_actions_delete_selected'] = 'Effacer la s�lection';
$txt['pm_actions_filter_by_label'] = 'Filtrer par label';
$txt['pm_actions_go'] = 'Ex�cuter';

// Manage Labels Screen.
$txt['pm_apply'] = 'Appliquer';
$txt['pm_manage_labels'] = 'G�rer les labels';
$txt['pm_labels_delete'] = '�tes-vous s�r de vouloir effacer les labels s�lectionn�s&nbsp;?';
$txt['pm_labels_desc'] = 'Ici vous pouvez ajouter, modifier et supprimer les labels utilis�s dans votre centre de messagerie personnelle.';
$txt['pm_label_add_new'] = 'Ajouter un nouveau label';
$txt['pm_label_name'] = 'Nom du label';
$txt['pm_labels_no_exist'] = 'Vous n\'avez actuellement aucun label param�tr�&nbsp;!';

// Labeling Drop Down.
$txt['pm_current_label'] = 'Label';
$txt['pm_msg_label_title'] = 'Attribuer un label au message';
$txt['pm_msg_label_apply'] = 'Ajouter un label';
$txt['pm_msg_label_remove'] = 'Enlever un label';
$txt['pm_msg_label_inbox'] = 'Bo�te de r�ception';
$txt['pm_sel_label_title'] = 'Label s�lectionn�';

// Menu headings.
$txt['pm_labels'] = 'Labels';
$txt['pm_messages'] = 'Messages';
$txt['pm_actions'] = 'Actions';
$txt['pm_preferences'] = 'Pr�f�rences';

$txt['pm_is_replied_to'] = 'Vous avez transf�r� ou r�pondu � ce message.';

// Reporting messages.
$txt['pm_report_to_admin'] = 'Rapporter � l\'administrateur';
$txt['pm_report_title'] = 'Rapporter un message personnel';
$txt['pm_report_desc'] = 'Depuis cette page vous pouvez rapporter le message personnel que vous avez re�u � l\'�quipe d\'administration du forum. Veuillez vous assurer d\'inclure une description de la raison de ce rapport de message, puisque �a sera envoy� avec le contenu du message original.';
$txt['pm_report_admins'] = 'Administrateur � aviser';
$txt['pm_report_all_admins'] = 'Envoyer � tous les administrateurs';
$txt['pm_report_reason'] = 'Raison du rapport de ce message';
$txt['pm_report_message'] = 'Rapporter le message';

// Important - The following strings should use numeric entities.
$txt['pm_report_pm_subject'] = '[RAPPORT] ';
// In the below string, do not translate "{REPORTER}" or "{SENDER}".
$txt['pm_report_pm_user_sent'] = '{REPORTER} a rapport&#233; le message personnel suivant, envoy&#233; par {SENDER}, pour la raison suivante :';
$txt['pm_report_pm_other_recipients'] = 'Les autres destinataires de ce message sont :';
$txt['pm_report_pm_hidden'] = '%1$d destinataire(s) cach&#233;(s)';
$txt['pm_report_pm_unedited_below'] = 'Ci-dessous se trouve le contenu original du message personnel rapport&#233; :';
$txt['pm_report_pm_sent'] = 'Envoy&#233; :';

$txt['pm_report_done'] = 'Merci d\'avoir soumis ce rapport. Vous devriez recevoir des nouvelles des administrateurs rapidement.';
$txt['pm_report_return'] = 'Retourner � la bo�te de r�ception';

$txt['pm_search_title'] = 'Rechercher dans la bo�te de messages personnels';
$txt['pm_search_bar_title'] = 'Rechercher des messages';
$txt['pm_search_text'] = 'Entrez le texte � rechercher&nbsp;';
$txt['pm_search_go'] = 'Rechercher';
$txt['pm_search_advanced'] = 'Recherche avanc�e';
$txt['pm_search_user'] = 'Par utilisateur';
$txt['pm_search_match_all'] = 'Correspondre tous les mots';
$txt['pm_search_match_any'] = 'Correspondre n\'importe quel mot';
$txt['pm_search_options'] = 'Options';
$txt['pm_search_post_age'] = '�ge du message';
$txt['pm_search_show_complete'] = 'Montrer tout le message dans les r�sultats.';
$txt['pm_search_subject_only'] = 'Chercher par titre et auteur seulement.';
$txt['pm_search_between'] = 'entre';
$txt['pm_search_between_and'] = 'et';
$txt['pm_search_between_days'] = 'jours';
$txt['pm_search_order'] = 'Ordre de recherche';
$txt['pm_search_choose_label'] = 'Choisir les labels � rechercher, ou rechercher partout';

$txt['pm_search_results'] = 'R�sultats des Recherches';
$txt['pm_search_none_found'] = 'Aucun Message Trouv�';

$txt['pm_search_orderby_relevant_first'] = 'Plus significatif en premier';
$txt['pm_search_orderby_recent_first'] = 'Plus r�cent en premier';
$txt['pm_search_orderby_old_first'] = 'Plus ancien en premier';

$txt['pm_visual_verification_label'] = 'V�rification';
$txt['pm_visual_verification_desc'] = 'Veuillez saisir le code contenu dans l\'image ci-dessus pour envoyer ce message priv�.';

$txt['pm_settings'] = 'Changer les r�glages';
$txt['pm_change_view'] = 'Changer d\'Affichage';

$txt['pm_manage_rules'] = 'G�rer les r�gles';
$txt['pm_manage_rules_desc'] = 'Les r�gles de message vous permettent de traiter automatiquement les messages personnels selon des crit�res que vous aurez d�finis. Ci-dessous, la liste des r�gles que vous avez actuellement. Pour modifier une r�gle, cliquez simplement sur son nom.';
$txt['pm_rules_none'] = 'Vous n\'avez pas encore cr�� de r�gle de message.';
$txt['pm_rule_title'] = 'R�gle';
$txt['pm_add_rule'] = 'Ajouter une Nouvelle R�gle';
$txt['pm_apply_rules'] = 'Appliquer les R�gles maintenant';
// Use entities in the below string.
$txt['pm_js_apply_rules_confirm'] = 'Etes-vous s�r de vouloir appliquer les r�gles actuelles � tous les messages personnels&nbsp;?';
$txt['pm_edit_rule'] = 'Modifier la R�gle';
$txt['pm_rule_save'] = 'Sauvegarder la R�gle';
$txt['pm_delete_selected_rule'] = 'Effacer les r�gles s�lectionn�es';
// Use entities in the below string.
$txt['pm_js_delete_rule_confirm'] = 'Etes-vous s�r de vouloir effacer les r�gles s�lectionn�es&nbsp;?';
$txt['pm_rule_name'] = 'Nom';
$txt['pm_rule_name_desc'] = 'Le nom de la r�gle, pour s\'en souvenir plus facilement';
$txt['pm_rule_name_default'] = '[NOM]';
$txt['pm_rule_description'] = 'Description';
$txt['pm_rule_not_defined'] = 'Ajoutez un crit�re pour commencer � mettre en place la description de cette r�gle.';
$txt['pm_rule_js_disabled'] = '<span class="alert"><strong>Attention</strong>, il semblerait que Javascript soit d�sactiv�. Nous vous recommandons fortement d\'activer Javascript pour utiliser cette fonctionnalit�.</span>';
$txt['pm_rule_criteria'] = 'Crit�res';
$txt['pm_rule_criteria_add'] = 'Ajouter un crit�re';
$txt['pm_rule_criteria_pick'] = 'Choisissez un crit�re';
$txt['pm_rule_mid'] = 'Nom de l\'exp�diteur';
$txt['pm_rule_gid'] = 'Groupe de l\'exp�diteur';
$txt['pm_rule_sub'] = 'Le titre du message contient';
$txt['pm_rule_msg'] = 'Le corps du message contient';
$txt['pm_rule_bud'] = 'L\'exp�diteur est un ami';
$txt['pm_rule_sel_group'] = 'S�lectionner un groupe';
$txt['pm_rule_logic'] = 'Lors de la v�rification des crit�res&nbsp;';
$txt['pm_rule_logic_and'] = 'Tous les crit�res doivent �tre satisfaits';
$txt['pm_rule_logic_or'] = 'Au moins un crit�re doit �tre satisfait';
$txt['pm_rule_actions'] = 'Actions';
$txt['pm_rule_sel_action'] = 'Choisissez une action';
$txt['pm_rule_add_action'] = 'Ajouter une action';
$txt['pm_rule_label'] = 'Mettre ce label sur le message';
$txt['pm_rule_sel_label'] = 'Choisissez le label';
$txt['pm_rule_delete'] = 'Effacer le Message';
$txt['pm_rule_no_name'] = 'Vous avez oubli� d\'entrer un nom pour la r�gle.';
$txt['pm_rule_no_criteria'] = 'Une r�gle doit avoir au moins un crit�re et une action.';
$txt['pm_rule_too_complex'] = 'La r�gle que vous �tes en train de cr�er est trop longue � enregistrer pour SMF. Essayez de la diviser en r�gles plus petites.';

$txt['pm_readable_and'] = '<em>et</em>';
$txt['pm_readable_or'] = '<em>ou</em>';
$txt['pm_readable_start'] = 'Si ';
$txt['pm_readable_end'] = '.';
$txt['pm_readable_member'] = 'message vient de &quot;{MEMBER}&quot;';
$txt['pm_readable_group'] = 'l\'exp�diteur est du groupe &quot;{GROUP}&quot;';
$txt['pm_readable_subject'] = 'le titre du message contient &quot;{SUBJECT}&quot;';
$txt['pm_readable_body'] = 'le corps du message contient &quot;{BODY}&quot;';
$txt['pm_readable_buddy'] = 'l\'exp�diteur est un ami';
$txt['pm_readable_label'] = 'appliquer le label &quot;{LABEL}&quot;';
$txt['pm_readable_delete'] = 'effacer le message';
$txt['pm_readable_then'] = '<strong>puis</strong>';
$txt['pm_remove_message'] = 'Supprimer ce message';

?>