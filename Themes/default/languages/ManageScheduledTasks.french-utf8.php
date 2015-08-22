<?php
// Version: 2.1 Beta 2; ManageScheduledTasks

$txt['scheduled_tasks_title'] = 'Tâches planifiées';
$txt['scheduled_tasks_header'] = 'Toutes les tâches planifiées';
$txt['scheduled_tasks_name'] = 'Nom de la tâche';
$txt['scheduled_tasks_next_time'] = 'Prochaine exécution';
$txt['scheduled_tasks_regularity'] = 'Fréquence';
$txt['scheduled_tasks_enabled'] = 'Activée';
$txt['scheduled_tasks_run_now'] = 'Lancer maintenant';
$txt['scheduled_tasks_save_changes'] = 'Sauvegarder les changements';
$txt['scheduled_tasks_time_offset'] = '<strong>NB&nbsp;:</strong> Toutes les heures données ci-dessous sont <em>à l\'heure du serveur</em> et ne tiennent pas compte des décalages de temps intégrés à SMF.';
$txt['scheduled_tasks_were_run'] = 'Toutes les tâches sélectionnées sont terminées.';
$txt['scheduled_tasks_were_run_errors'] = 'Toutes les tâches ont été accomplies mais certaines ont eues des erreurs:';

$txt['scheduled_tasks_na'] = 'N/A';
$txt['scheduled_task_approval_notification'] = 'Notifications d\'approbation';
$txt['scheduled_task_desc_approval_notification'] = 'Envoie des e-mails à tous les modérateurs avec la liste des messages nécessitant une approbation.';
$txt['scheduled_task_auto_optimize'] = 'Optimiser la base';
$txt['scheduled_task_desc_auto_optimize'] = 'Optimise la base de données pour résoudre les problèmes de fragmentation.';
$txt['scheduled_task_daily_maintenance'] = 'Maintenance quotidienne';
$txt['scheduled_task_desc_daily_maintenance'] = 'Lance des opérations de maintenance quotidiennes essentielles pour le forum &ndash; à ne pas désactiver.';
$txt['scheduled_task_daily_digest'] = 'Résumé quotidien des notifications';
$txt['scheduled_task_desc_daily_digest'] = 'Envoie par e-mail le résumé quotidien des notifications demandées par les utilisateurs.';
$txt['scheduled_task_weekly_digest'] = 'Résumé hebdomadaire des notifications';
$txt['scheduled_task_desc_weekly_digest'] = 'Envoie par e-mail le résumé hebdomadaire des notifications demandées par les utilisateurs.';
$txt['scheduled_task_fetchSMfiles'] = 'Télécharger les fichiers Simple Machines';
$txt['scheduled_task_desc_fetchSMfiles'] = 'Télécharge les fichiers Javascript sur le serveur SMF contenant des notifications de mise à jour et autres informations.';
$txt['scheduled_task_birthdayemails'] = 'Souhaiter les anniversaires';
$txt['scheduled_task_desc_birthdayemails'] = 'Envoie des e-mails souhaitant un joyeux anniversaire aux membres.';
$txt['scheduled_task_weekly_maintenance'] = 'Maintenance hebdomadaire';
$txt['scheduled_task_desc_weekly_maintenance'] = 'Lance des opérations de maintenance hebdomadaires essentielles pour le forum &ndash; à ne pas désactiver.';
$txt['scheduled_task_paid_subscriptions'] = 'Maintenance des comptes payants';
$txt['scheduled_task_desc_paid_subscriptions'] = 'Envoie les rappels éventuels pour le renouvellement des comptes payants, et retire du groupe les membres dont l\'abonnement a expiré.';
$txt['scheduled_task_remove_topic_redirect'] = 'Retire les sujets de redirection DÉPLACÉ:';
$txt['scheduled_task_desc_remove_topic_redirect'] = 'Supprime les notifications de sujets de redirection "DÉPLACÉ:" tel que spécifié quand la notification de déplacement a été créée.';
$txt['scheduled_task_remove_temp_attachments'] = 'Supprimer les fichiers joints temporaires';
$txt['scheduled_task_desc_remove_temp_attachments'] = 'Supprime les fichiers temporaires crées pendant la soumission d\'un message et qui pour quelques raisons que ce soit n\'ont pas étés renommés ou supprimés avant. ';

$txt['scheduled_task_reg_starting'] = 'Démarrage à %1$s';
$txt['scheduled_task_reg_repeating'] = 'fréquence de %1$d %2$s';
$txt['scheduled_task_reg_unit_m'] = 'minute(s)';
$txt['scheduled_task_reg_unit_h'] = 'heure(s)';
$txt['scheduled_task_reg_unit_d'] = 'jour(s)';
$txt['scheduled_task_reg_unit_w'] = 'semaine(s)';

$txt['scheduled_task_edit'] = 'Modifier la tâche planifiée';
$txt['scheduled_task_edit_repeat'] = 'Répéter la tâche tous les';
$txt['scheduled_task_edit_interval'] = 'Intervalle';
$txt['scheduled_task_edit_start_time'] = 'Heure de départ';
$txt['scheduled_task_edit_start_time_desc'] = 'Heure à laquelle doit s\'effectuer le premier lancement de la journée (heures:minutes)';
$txt['scheduled_task_time_offset'] = 'Veuillez noter que l\'heure de départ doit être adaptée par rapport à la date locale du serveur, qui est actuellement&nbsp;: %1$s';

$txt['scheduled_view_log'] = 'Voir les journaux';
$txt['scheduled_log_empty'] = 'Aucune entrée actuellement dans le journal des tâches.';
$txt['scheduled_log_time_run'] = 'Date du lancement';
$txt['scheduled_log_time_taken'] = 'Temps d\'exécution';
$txt['scheduled_log_time_taken_seconds'] = '%1$d secondes';
$txt['scheduled_log_empty_log'] = 'Vider les journaux';
$txt['scheduled_log_empty_log_confirm'] = 'Êtes vous sûr de vouloir complètement effacer le journal?';

$txt['scheduled_task_remove_old_drafts'] = 'Supprimer les vieux brouillons';
$txt['scheduled_task_desc_remove_old_drafts'] = 'Supprime les brouillons plus anciens que le nombre de jours définis dans les réglages de brouillons du panneau d\'administration.';

?>