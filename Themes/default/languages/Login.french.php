<?php
// Version: 2.1 Beta 2; Login

global $context;

// Registration agreement page.
$txt['registration_agreement'] = 'Accord d\'inscription';
$txt['agreement_agree'] = 'J\'accepte les conditions de ce document.';
$txt['agreement_agree_coppa_above'] = 'J\'accepte les conditions de ce document et j\'ai au moins %1$d ans.';
$txt['agreement_agree_coppa_below'] = 'J\'accepte les conditions de ce document et j\'ai moins de %1$d ans.';
$txt['agree_coppa_above'] = 'J\'ai au moins %1$d ans.';
$txt['agree_coppa_below'] = 'Je suis plus jeune que %1$d ans.';

// Registration form.
$txt['registration_form'] = 'Formulaire d\'inscription';
$txt['need_username'] = 'Vous devez mettre un nom d\'utilisateur.';
$txt['no_password'] = 'Vous n\'avez entr� aucun mot de passe';
$txt['incorrect_password'] = 'Mot de passe incorrect';
$txt['choose_username'] = 'Choisissez un identifiant';
$txt['maintain_mode'] = 'Mode Maintenance';
$txt['registration_successful'] = 'Inscription r�ussie';
$txt['now_a_member'] = 'Vous �tes maintenant un membre du forum.';
// Use numeric entities in the below string.
$txt['your_password'] = 'et votre mot de passe est';
$txt['valid_email_needed'] = 'Merci d\'entrer une adresse e-mail valide, %1$s.';
$txt['required_info'] = 'Informations Requises';
$txt['identification_by_smf'] = 'Utilis� uniquement pour la connexion � SMF.';
$txt['additional_information'] = 'Informations suppl�mentaires';
$txt['warning'] = 'Attention&nbsp;!';
$txt['only_members_can_access'] = 'Seuls les membres inscrits sont autoris�s � acc�der � cette section.';
$txt['login_below'] = 'Merci de vous connecter ci-dessous ou';
$txt['login_below_or_register'] = 'Veuillez vous identifier ci-dessous ou <a href="%1$s">vous inscrire pour un compte</a> avec %2$s';

// Use numeric entities in the below two strings.
$txt['may_change_in_profile'] = 'Vous pouvez le changer apr&#232;s vous &#234;tre connect&#233; en allant sur votre page de profil, ou en visitant cette page apr&#232;s identification&#160;:';
$txt['your_username_is'] = 'Votre identifiant est';

$txt['login_hash_error'] = 'La s�curit� des mots de passe a r�cemment �t� accrue.  Veuillez entrer de nouveau votre mot de passe.';

$txt['ban_register_prohibited'] = 'D�sol�, vous n\'�tes pas autoris� � vous inscrire sur ce forum';

$txt['activate_account'] = 'Activation de Compte';
$txt['activate_success'] = 'Votre compte a �t� activ� avec succ�s. Vous pouvez maintenant vous connecter.';
$txt['activate_not_completed1'] = 'Votre adresse e-mail doit �tre valid�e avant que vous puissiez vous connecter.';
$txt['activate_not_completed2'] = 'Un autre e-mail d\'activation&nbsp;?';
$txt['activate_after_registration'] = 'Merci de vous �tre inscrit. Vous recevrez rapidement un e-mail contenant un lien pour activer votre compte. Si vous ne recevez rien apr�s un certain temps, v�rifiez votre dossier de courriers ind�sirables (spams).';
$txt['invalid_userid'] = 'L\'utilisateur n\'existe pas';
$txt['invalid_activation_code'] = 'Code d\'activation invalide';
$txt['invalid_activation_username'] = 'Identifiant ou adresse e-mail';
$txt['invalid_activation_new'] = 'Si vous vous �tes inscrit avec une adresse e-mail incorrecte, entrez-en une nouvelle ainsi que votre mot de passe ici.';
$txt['invalid_activation_new_email'] = 'Nouvelle adresse e-mail';
$txt['invalid_activation_password'] = 'Ancien mot de passe';
$txt['invalid_activation_resend'] = 'Envoyez de nouveau le code d\'activation';
$txt['invalid_activation_known'] = 'Si vous connaissez d�j� votre code d\'activation, tapez-le ici.';
$txt['invalid_activation_retry'] = 'Code d\'activation';
$txt['invalid_activation_submit'] = 'Activer';

$txt['coppa_no_concent'] = 'L\'administrateur n\'a toujours re�u aucune autorisation parentale pour votre compte.';
$txt['coppa_need_more_details'] = 'Plus de d�tails&nbsp;?';

$txt['awaiting_delete_account'] = 'Votre compte a �t� marqu� pour une suppression!<br>Si vous voulez restaurer votre compte, veuillez cocher la case &quot;R�activer mon compte&quot;, et connectez-vous � nouveau.';
$txt['undelete_account'] = 'R�activer mon compte';

// Use numeric entities in the below three strings.
$txt['change_password'] = 'Nouveaux d&#233;tails de connexion';
$txt['change_password_login'] = 'Vos informations de connexion sur ';
$txt['change_password_new'] = 'ont &#233;t&#233; chang&#233;es et votre mot de passe chang&#233; Voici vos nouveaux d&#233;tails de connexion.';

$txt['in_maintain_mode'] = 'Ce forum est en Mode Maintenance.';

// These two are used as a javascript alert; please use international characters directly, not as entities.
$txt['register_agree'] = 'Merci de lire et accepter les termes de l\'accord avant de vous inscrire.';
$txt['register_passwords_differ_js'] = 'Les deux mots de passe entr�s sont diff�rents !';

$txt['approval_after_registration'] = 'Merci de vous �tre inscrit. L\'administrateur doit approuver votre inscription avant que vous puissiez commencer � utiliser votre compte. Vous allez bient�t recevoir un e-mail vous informant de la d�cision de l\'administrateur.';

$txt['admin_settings_desc'] = 'Ici vous pouvez changer une vari�t� de param�tres concernant l\'inscription des nouveaux membres.';

$txt['setting_registration_method'] = 'M�thode d\'inscription employ�e pour les futurs membres';
$txt['setting_registration_disabled'] = 'Inscription d�sactiv�e';
$txt['setting_registration_standard'] = 'Inscription imm�diate';
$txt['setting_registration_activate'] = 'Activation par e-mail';
$txt['setting_registration_approval'] = 'Approbation par un admin';
$txt['setting_send_welcomeEmail'] = 'Envoyer un e-mail de bienvenue aux nouveaux membres';

$txt['setting_coppaAge'] = '�ge en dessous duquel appliquer des restrictions � l\'inscription';
$txt['setting_coppaType'] = 'Action � faire lorsqu\'un utilisateur en dessous de l\'�ge minimum s\'inscrit';
$txt['setting_coppaType_reject'] = 'Rejeter son inscription';
$txt['setting_coppaType_approval'] = 'Requ�rir une autorisation parentale';
$txt['setting_coppaPost'] = 'Adresse postale � laquelle le formulaire d\'autorisation doit �tre envoy�';
$txt['setting_coppaPost_desc'] = 'Ne s\'applique que si la restriction d\'�ge est en place';
$txt['setting_coppaFax'] = 'Num�ro de fax auquel le formulaire d\'autorisation doit �tre fax�';
$txt['setting_coppaPhone'] = 'Num�ro de t�l�phone � appeler pour les parents ayant des questions sur les restrictions d\'�ge';

$txt['admin_register'] = 'Inscription d\'un nouveau membre';
$txt['admin_register_desc'] = 'D\'ici vous pouvez inscrire des nouveaux membres sur votre forum, et si vous le d�sirez, leur envoyer leurs informations de connexion par e-mail.';
$txt['admin_register_username'] = 'Nouvel identifiant';
$txt['admin_register_email'] = 'Adresse e-mail';
$txt['admin_register_password'] = 'Mot de passe';
$txt['admin_register_username_desc'] = 'Identifiant pour le nouveau membre';
$txt['admin_register_email_desc'] = 'Adresse e-mail associ�e � ce compte membre';
$txt['admin_register_password_desc'] = 'Nouveau mot de passe du membre';
$txt['admin_register_email_detail'] = 'Envoyer le mot de passe par e-mail';
$txt['admin_register_email_detail_desc'] = 'Adresse e-mail requise m�me si d�coch�';
$txt['admin_register_email_activate'] = 'N�cessite l\'activation du compte par le membre';
$txt['admin_register_group'] = 'Groupe principal';
$txt['admin_register_group_desc'] = 'Groupe de membre principal auquel le nouveau membre appartiendra';
$txt['admin_register_group_none'] = '(pas de groupe principal)';
$txt['admin_register_done'] = 'Le membre %1$s s\'est inscrit avec succ�s&nbsp;!';

$txt['coppa_title'] = 'Forum avec restriction d\'�ge';
$txt['coppa_after_registration'] = 'Merci de vous �tre inscrit sur ' . $context['forum_name_html_safe'] . '.<br><br>Parce que vous �tes �g� de moins de {MINIMUM_AGE} ans, il est l�galement requis
	que vous obteniez une autorisation de vos parents ou tuteurs l�gaux avant que vous puissiez utiliser votre compte.  Pour arranger l\'activation de votre compte, veuillez imprimer le formulaire ci-dessous:';
$txt['coppa_form_link_popup'] = 'Charger le formulaire dans une nouvelle fen�tre';
$txt['coppa_form_link_download'] = 'T�l�charger le formulaire en tant que fichier texte';
$txt['coppa_send_to_one_option'] = 'Ensuite, demandez � vos parents ou tuteurs de l\'envoyer compl�t� par&nbsp;:';
$txt['coppa_send_to_two_options'] = 'Puis arrangez-vous pour que votre parent ou tuteur envoie le formulaire rempli par&nbsp;:';
$txt['coppa_send_by_post'] = 'Voie postale � l\'adresse suivante&nbsp;:';
$txt['coppa_send_by_fax'] = 'Fax au num�ro suivant&nbsp;:';
$txt['coppa_send_by_phone'] = 'Alternativement, demandez-leur de contacter l\'administrateur par t�l�phone au num�ro {PHONE_NUMBER}.';

$txt['coppa_form_title'] = 'Formulaire d\'autorisation d\'inscription au forum ' . $context['forum_name_html_safe'];
$txt['coppa_form_address'] = 'Adresse';
$txt['coppa_form_date'] = 'Date';
$txt['coppa_form_body'] = 'Je soussign�, {PARENT_NAME},<br><br>Donne la permission � {CHILD_NAME} (nom de l\'enfant) de devenir un membre � part enti�re du forum : ' . $context['forum_name_html_safe'] . ', sous l\'identifiant : {USER_NAME}.<br><br>Je comprends que certaines informations personnelles entr�es par {USER_NAME} peuvent �tres affich�es � d\'autres visiteurs du forum.<br><br>Sign�:<br>{PARENT_NAME} (Parent/Tuteur l�gal).';

$txt['visual_verification_sound_again'] = 'Recommencer';
$txt['visual_verification_sound_close'] = 'Fermer la fen�tre';
$txt['visual_verification_sound_direct'] = 'Un probl�me pour �couter ceci? Essayez avec ce lien direct.';

// Use numeric entities in the below.
$txt['registration_username_available'] = 'Ce nom d\'utilisateur est disponible';
$txt['registration_username_unavailable'] = 'Ce nom d\'utilisateur n\'est pas disponible';
$txt['registration_username_check'] = 'V�rifier si le nom d\'utilisateur est disponible';
$txt['registration_password_short'] = 'Le mot de passe est trop court';
$txt['registration_password_reserved'] = 'Le mot de passe contient votre e-mail ou nom d\'utilisateur';
$txt['registration_password_numbercase'] = 'Le mot de passe doit contenir � la fois des majuscules, des minuscules et des chiffres';
$txt['registration_password_no_match'] = 'Les mots de passe ne correspondent pas';
$txt['registration_password_valid'] = 'Le mot de passe est valide';

$txt['registration_errors_occurred'] = 'Les erreurs suivantes ont �t� d�tect�es lors de votre inscription. Merci de les corriger pour pouvoir continuer&nbsp;:';

?>