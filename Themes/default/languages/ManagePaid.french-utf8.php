<?php
// Version: 2.1 Beta 2; ManagePaid

global $boardurl;

// Some payment gateways need language specific information.
$txt['lang_paypal'] = 'US';

// Symbols.
$txt['usd_symbol'] = '$%1.2f';
$txt['eur_symbol'] = '&euro;%1.2f';
$txt['gbp_symbol'] = '&pound;%1.2f';
$txt['cad_symbol'] = 'C$%1.2f';
$txt['aud_symbol'] = 'A$%1.2f';

$txt['usd'] = 'USD ($)';
$txt['eur'] = 'EURO (&euro;)';
$txt['gbp'] = 'GBP (&pound;)';
$txt['cad'] = 'CAD (C$)';
$txt['aud'] = 'AUD (A$)';
$txt['other'] = 'Autre';

$txt['paid_username'] = 'Identifiant';

$txt['paid_subscriptions_desc'] = 'Dans cette section, vous pourrez ajouter, supprimer et modifier les méthodes d\'abonnement payant de votre forum.';
$txt['paid_subs_settings'] = 'Réglages';
$txt['paid_subs_settings_desc'] = 'Ici vous pouvez modifier les méthodes de paiement disponibles pour vos utilisateurs.';
$txt['paid_subs_view'] = 'Voir les Abonnements';
$txt['paid_subs_view_desc'] = 'Dans cette section, vous pouvez voir tous les abonnements que vous proposez.';

// Setting type strings.
$txt['paid_enabled'] = 'Activer les Abonnements Payants';
$txt['paid_enabled_desc'] = 'Ceci doit être coché pour que les abonnements payants soient utilisés sur le forum.';
$txt['paid_email'] = 'Envoyer des e-mails de notification';
$txt['paid_email_desc'] = 'Informer l\'admin lorsqu\'un abonnement change automatiquement.';
$txt['paid_email_to'] = 'Adresse e-mail pour la correspondance';
$txt['paid_email_to_desc'] = 'Liste d\'adresses e-mail, séparées par des virgules, auxquelles envoyer des notifications en plus des administrateurs du forum.';
$txt['paidsubs_test'] = 'Activer le mode test';
$txt['paidsubs_test_desc'] = 'Place les abonnements payants en mode &quot;test&quot;, qui utilisera, si elles existent, les méthodes test (&quot;bac à sable&quot;) de paiement de PayPal et autres. Ne l\'activez pas à moins de savoir ce que vous faites&nbsp;!';
$txt['paidsubs_test_confirm'] = 'Êtes-vous sûr de vouloir activer le mode test ?';
$txt['paid_email_no'] = 'Ne pas envoyer de notification';
$txt['paid_email_error'] = 'Informer lorsqu\'un abonnement échoue';
$txt['paid_email_all'] = 'Informer de tout changement automatique sur les abonnements';
$txt['paid_currency'] = 'Choisissez la devise';
$txt['paid_currency_code'] = 'Code Devise';
$txt['paid_currency_code_desc'] = 'Code utilisé par les sites gérant les paiements';
$txt['paid_currency_symbol'] = 'Symbole utilisé par la méthode de paiement';
$txt['paid_currency_symbol_desc'] = 'Utiliser \'%1.2f\' pour spécifier où le nombre sera placé, par exemple <em>$</em>%1.2f, %1.2f<em>DM</em>, etc.';
$txt['paypal_email'] = 'Adresse e-mail PayPal';
$txt['paypal_email_desc'] = 'Laissez vide si vous ne souhaitez pas utiliser PayPal.';
$txt['paypal_sandbox_email'] = 'Paypal sandbox email address';
$txt['paypal_sandbox_email_desc'] = 'Can be left blank if test mode is disabled or not using PayPal.';
$txt['worldpay_id'] = 'ID d\'installation WorldPay';
$txt['worldpay_id_desc'] = 'L\'identifiant d\'installation généré par WorldPay. Laissez vide si vous n\'utilisez pas WorldPay.';
$txt['worldpay_password'] = 'Mot de passe de rappel WorldPay';
$txt['worldpay_password_desc'] = 'Assurez-vous, lorsque vous mettez ce mot de passe WorldPay, qu\'il est unique et non le même que le mot de passe de votre compte WorldPay/admin&nbsp;!';
$txt['authorize_id'] = 'ID d\'installation Authorize.Net';
$txt['authorize_id_desc'] = 'L\'identifiant d\'installation généré par Authorize.Net. Laissez vide si vous n\'utilisez pas Authorize.Net.';
$txt['authorize_transid'] = 'ID de transaction Authorize.Net';
$txt['2co_id'] = 'ID d\'installation 2co.com';
$txt['2co_id_desc'] = 'L\'identifiant d\'installation généré par 2co.com. Laissez vide si vous n\'utilisez pas 2co.com.';
$txt['2co_password'] = 'Mot secret 2co.com';
$txt['2co_password_desc'] = 'Votre mot secret 2checkout.';
$txt['nochex_email'] = 'Adresse e-mail Nochex';
$txt['nochex_email_desc'] = 'Adresse e-mail du compte marchant chez Nochex. Laissez vide si vous n\'utilisez pas Nochex.';
$txt['paid_settings_save'] = 'Sauvegarder';

$txt['paid_note'] = '<strong class="alert">Note</strong>&nbsp;:<br />Pour que les abonnements soient mis à jour automatiquement pour vos utilisateurs, vous
	aurez besoin de mettre en place une URL de retour pour chacune de vos méthodes de paiement. Pour tous les types de paiement, cette URL de retour doit
	être équivalente à&nbsp;:<br /><br />&nbsp;&nbsp;&bull;&nbsp;&nbsp;<strong>' . $boardurl . '/subscriptions.php</strong><br /><br />
	Vous pouvez modifier le lien pour PayPal directement, en cliquant <a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-ipn-notify" target="_blank">ici</a>.<br />
	Pour les autres passerelles (si installées), vous pouvez normalement les trouver dans votre panneau client, habituellement sous le terme &quot;URL de Retour&quot; (<em>Return URL</em>) ou &quot;URL de Rappel&quot; (<em>Callback URL</em>).';

// View subscription strings.
$txt['paid_name'] = 'Nom';
$txt['paid_status'] = 'État';
$txt['paid_cost'] = 'Prix';
$txt['paid_duration'] = 'Durée';
$txt['paid_active'] = 'Actif';
$txt['paid_pending'] = 'Paiement en attente';
$txt['paid_finished'] = 'Expiré';
$txt['paid_total'] = 'Total';
$txt['paid_is_active'] = 'Disponible';
$txt['paid_none_yet'] = 'Pas d\'abonnement pour le moment.';
$txt['paid_payments_pending'] = 'Paiements en attente';
$txt['paid_order'] = 'Commander';

$txt['yes'] = 'Oui';
$txt['no'] = 'Non';

// Add/Edit/Delete subscription.
$txt['paid_add_subscription'] = 'Ajouter un Abonnement';
$txt['paid_edit_subscription'] = 'Modifier l\'Abonnement';
$txt['paid_delete_subscription'] = 'Effacer l\'Abonnement';

$txt['paid_mod_name'] = 'Nom de l\'Abonnement';
$txt['paid_mod_desc'] = 'Description';
$txt['paid_mod_reminder'] = 'Envoyer un E-mail de Rappel';
$txt['paid_mod_reminder_desc'] = 'Jours avant que l\'abonnement n\'expire pour envoyer le rappel. (En jours, 0 pour désactiver)';
$txt['paid_mod_email'] = 'E-mail à envoyer après confirmation de la souscription';
$txt['paid_mod_email_desc'] = 'Où {NAME} est le nom du membre, et {FORUM} est le nom de la communauté. Le sujet de l\'e-mail doit être sur la première ligne. Laissez vide pour ne pas envoyer de notification e-mail.';
$txt['paid_mod_cost_usd'] = 'Prix (USD)';
$txt['paid_mod_cost_eur'] = 'Prix (EUR)';
$txt['paid_mod_cost_gbp'] = 'Prix (GBP)';
$txt['paid_mod_cost_cad'] = 'Cost (CAD)';
$txt['paid_mod_cost_aud'] = 'Cost (AUD)';
$txt['paid_mod_cost_blank'] = 'Laissez vide pour ne pas proposer cette devise.';
$txt['paid_mod_span'] = 'Durée d\'Abonnement';
$txt['paid_mod_span_days'] = 'Jours';
$txt['paid_mod_span_weeks'] = 'Semaines';
$txt['paid_mod_span_months'] = 'Mois';
$txt['paid_mod_span_years'] = 'Années';
$txt['paid_mod_active'] = 'Disponible';
$txt['paid_mod_active_desc'] = 'Un abonnement doit être disponible pour que les membres puissent y souscrire.';
$txt['paid_mod_prim_group'] = 'Groupe de base de l\'Abonnement';
$txt['paid_mod_prim_group_desc'] = 'Groupe de base dans lequel l\'utilisateur sera placé pendant la durée de son abonnement.';
$txt['paid_mod_add_groups'] = 'Groupes additionnels de l\'Abonnement';
$txt['paid_mod_add_groups_desc'] = 'Groupes additionnels dans lesquels l\'utilisateur sera placé pendant la durée de son abonnement.';
$txt['paid_mod_no_group'] = 'Ne rien changer';
$txt['paid_mod_edit_note'] = 'Notez que ce groupe étant lié à des abonnements existants, certains réglages ne peuvent être changés&nbsp;!';
$txt['paid_mod_delete_warning'] = '<strong>ATTENTION</strong><br /><br />Si vous supprimez cet abonnement, tous les utilisateurs y souscrivant actuellement perdront leurs droits d\'accès garantis par l\'abonnement en question. Sauf si vous êtes sûr de vouloir faire cela, il est recommandé de simplement désactiver un abonnement plutôt que de le supprimer.<br />';
$txt['paid_mod_repeatable'] = 'Permettre à l\'utilisateur de renouveler automatiquement son abonnement';
$txt['paid_mod_allow_partial'] = 'Permettre un abonnement partiel';
$txt['paid_mod_allow_partial_desc'] = 'Si cette option est activée, dans le cas où l\'utilisateur paie moins que ce qui est demandé, il lui sera garanti une durée d\'abonnement en rapport avec le pourcentage pour lequel il aura payé.';
$txt['paid_mod_fixed_price'] = 'Abonnement avec prix et durée fixes';
$txt['paid_mod_flexible_price'] = 'Abonnement dont le prix dépend de la durée commandée';
$txt['paid_mod_price_breakdown'] = 'Détails sur le Prix Flexible';
$txt['paid_mod_price_breakdown_desc'] = 'Définissez ici combien l\'abonnement coûtera selon la durée pour laquelle on y souscrit. Par exemple, il pourrait coûter 8&euro; pour un abonnement d\'un mois, mais seulement 50&euro; pour un an. Si vous ne voulez pas définir un prix pour une durée particulière, laissez vide.';
$txt['flexible'] = 'Flexible';

$txt['paid_per_day'] = 'Prix par Jour';
$txt['paid_per_week'] = 'Prix par Semaine';
$txt['paid_per_month'] = 'Prix par Mois';
$txt['paid_per_year'] = 'Prix par An';
$txt['day'] = 'Jour';
$txt['week'] = 'Semaine';
$txt['month'] = 'Mois';
$txt['year'] = 'An';

// View subscribed users.
$txt['viewing_users_subscribed'] = 'Liste des Abonnés';
$txt['view_users_subscribed'] = 'Voir les utilisateurs abonnés à&nbsp;: &quot;%1$s&quot;';
$txt['no_subscribers'] = 'Actuellement, personne n\'a souscrit à cet abonnement&nbsp;!';
$txt['add_subscriber'] = 'Ajouter un Nouveau Souscripteur';
$txt['edit_subscriber'] = 'Modifier le Souscripteur';
$txt['delete_selected'] = 'Effacer la Sélection';
$txt['complete_selected'] = 'Achever pour la Sélection';

// @todo These strings are used in conjunction with JavaScript. Use numeric entities.
$txt['delete_are_sure'] = '&#202;tes-vous s&#369;r de vouloir effacer tous les enregistrements des abonnements s&#233;lectionn&#233;s ?';
$txt['complete_are_sure'] = '&#202;tes-vous s&#369;r de vouloir achever les processus de souscription s&#233;lectionn&#233;s ?';

$txt['start_date'] = 'Date de Début';
$txt['end_date'] = 'Date de Fin';
$txt['start_date_and_time'] = 'Date et Heure de Début';
$txt['end_date_and_time'] = 'Date et Heure de Fin';
$txt['edit'] = 'MODIFIER';
$txt['one_username'] = 'Veuillez n\'entrer qu\'un seul identifiant.';
$txt['minute'] = 'Minute';
$txt['error_member_not_found'] = 'Le membre entré est introuvable';
$txt['member_already_subscribed'] = 'Ce membre a déjà souscrit à cet abonnement. Veuillez modifier son abonnement existant.';
$txt['search_sub'] = 'Trouver un utilisateur';

// Make payment.
$txt['paid_confirm_payment'] = 'Confirmer le Paiement';
$txt['paid_confirm_desc'] = 'Avant de procéder au paiement, veuillez vérifier les détails ci-dessous et cliquer sur &quot;Commander&quot;.';
$txt['paypal'] = 'PayPal';
$txt['paid_confirm_paypal'] = 'Pour payer en utilisant <a href="http://www.paypal.com">PayPal</a>, veuillez cliquer sur le bouton ci-dessous. Vous serez redirigé sur le site de PayPal pour effectuer le paiement.';
$txt['paid_paypal_order'] = 'Payer avec PayPal';
$txt['worldpay'] = 'WorldPay';
$txt['paid_confirm_worldpay'] = 'Pour payer en utilisant <a href="http://www.worldpay.com">WorldPay</a>, veuillez cliquer sur le bouton ci-dessous. Vous serez redirigé sur le site de WorldPay pour effectuer le paiement.';
$txt['paid_worldpay_order'] = 'Payer avec WorldPay';
$txt['nochex'] = 'Nochex';
$txt['paid_confirm_nochex'] = 'Pour payer en utilisant <a href="http://www.nochex.com">Nochex</a>, veuillez cliquer sur le bouton ci-dessous. Vous serez redirigé sur le site de Nochex pour effectuer le paiement.';
$txt['paid_nochex_order'] = 'Payer avec Nochex';
$txt['authorize'] = 'Authorize.Net';
$txt['paid_confirm_authorize'] = 'Pour payer en utilisant <a href="http://www.authorize.net">Authorize.Net</a>, veuillez cliquer sur le bouton ci-dessous. Vous serez redirigé sur le site d\'Authorize.Net pour effectuer le paiement.';
$txt['paid_authorize_order'] = 'Payer avec Authorize.Net';
$txt['2co'] = '2checkout';
$txt['paid_confirm_2co'] = 'Pour payer en utilisant <a href="http://www.2co.com">2co.com</a>, veuillez cliquer sur le bouton ci-dessous. Vous serez redirigé sur le site de 2co.com pour effectuer le paiement.';
$txt['paid_2co_order'] = 'Payer avec 2co.com';
$txt['paid_done'] = 'Paiement Effectué';
$txt['paid_done_desc'] = 'Merci pour votre paiement. Une fois que la transaction aura été vérifiée, la souscription sera activée.';
$txt['paid_sub_return'] = 'Retourner aux abonnements';
$txt['paid_current_desc'] = 'Vous trouverez ci-dessous la liste de tous vos abonnements actuels et passés. Pour prolonger un abonnement déjà existant, sélectionnez-le simplement dans la liste ci-dessus.';
$txt['paid_admin_add'] = 'Ajouter cet Abonnement';

$txt['paid_not_set_currency'] = 'Vous n\'avez pas encore choisi votre devise. Veuillez le faire à partir du menu des <a href="%1$s">réglages</a> avant de continuer';
$txt['paid_no_cost_value'] = 'Vous devez entrer un prix et une durée d\'abonnement.';
$txt['paid_invalid_duration'] = 'You must enter a valid duration for this subscription.';
$txt['paid_invalid_duration_D'] = 'If putting in a subscription length measured in days, you can only use 1 to 90 days. If you want a subscription that long, you should use weeks, months or years.';
$txt['paid_invalid_duration_W'] = 'If putting in a subscription length measured in weeks, you can only use 1 to 52 weeks. If you want a subscription that long, you should use months or years';
$txt['paid_invalid_duration_M'] = 'If putting in a subscription length measured in months, you can only use 1 to 24 months. If you want a subscription that long, you should use years';
$txt['paid_invalid_duration_Y'] = 'If putting in a subscription length measured in years, you can only use 1 to 5 years.';
$txt['paid_all_freq_blank'] = 'Vous devez entrer un prix pour au moins l\'une des quatre durées.';

// Some error strings.
$txt['paid_no_data'] = 'Aucune donnée valide n\'a été envoyée au script.';

$txt['paypal_could_not_connect'] = 'Impossible de se connecter au serveur PayPal';
$txt['paid_sub_not_active'] = 'Cet abonnement n\'accepte plus de nouveaux souscripteurs&nbsp;!';
$txt['paid_disabled'] = 'Les abonnements payants sont actuellement désactivés&nbsp;!';
$txt['paid_unknown_transaction_type'] = 'Type de transaction inconnu pour les abonnements payants.';
$txt['paid_empty_member'] = 'Le formulaire d\'abonnement payant n\'a pas pu retrouver l\'identifiant du membre';
$txt['paid_could_not_find_member'] = 'Le formulaire d\'abonnement payant n\'a pas pu retrouver le membre ayant l\'identifiant&nbsp;: %1$d';
$txt['paid_count_not_find_subscription'] = 'Le formulaire d\'abonnement payant n\'a pas trouvé d\'abonnement pour l\'ID membre&nbsp;: %1$s, ID d\'abonnement&nbsp;: %2$s';
$txt['paid_count_not_find_subscription_log'] = 'Le formulaire d\'abonnement payant n\'a pas pu trouver d\'entrée du journal des abonnements pour l\'ID membre&nbsp;: %1$s, ID d\'abonnement&nbsp;: %2$s';
$txt['paid_count_not_find_outstanding_payment'] = 'Impossible de trouver un paiement en attente pour l\'ID membre&nbsp;: %1$s, ID d\'abonnement&nbsp;: %2$s, donc à ignorer';
$txt['paid_admin_not_setup_gateway'] = 'Désolé, mais l\'administrateur n\'a pas encore mis d\'abonnement payant en place - veuillez réessayer plus tard.';
$txt['paid_make_recurring'] = 'Rendre le paiement récurrent';

$txt['subscriptions'] = 'Abonnements';
$txt['subscription'] = 'Abonnement';
$txt['paid_subs_desc'] = 'Vous trouverez ci-dessous la liste des abonnements disponibles pour ce site.';
$txt['paid_subs_none'] = 'Il n\'y a actuellement aucun abonnement payant en place&nbsp;!';

$txt['paid_current'] = 'Abonnements existants';
$txt['pending_payments'] = 'Paiements en attente';
$txt['pending_payments_desc'] = 'Ce membre a tenté d\'effectuer les paiements suivants pour cet abonnement, mais la confirmation n\'a pas été reçue par le forum. Si vous êtes sûr que le paiement a bien été reçu, cliquez sur &quot;Accepter&quot; pour confirmer l\'abonnement. Sinon vous pouvez aussi cliquer sur &quot;Supprimer&quot; pour effacer toute référence au paiement.';
$txt['pending_payments_value'] = 'Valeur';
$txt['pending_payments_accept'] = 'Accepter';
$txt['pending_payments_remove'] = 'Supprimer';

?>