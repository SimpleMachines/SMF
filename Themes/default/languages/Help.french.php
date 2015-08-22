<?php
// Version: 2.1 Beta 2; Help

global $helptxt;

$txt['close_window'] = 'Fermer la fen�tre';

$helptxt['manage_boards'] = '
	<strong>Gestion des Sections et des Cat�gories</strong><br />
	Dans ce menu, vous pouvez cr�er/r�organiser/supprimer des sections et les cat�gories
	les concernant. Par exemple, si vous avez un gros site offrant des informations vari�es
	sur plusieurs sujets tels que &quot;Sports&quot; et &quot;Voitures&quot; et &quot;Musique&quot;, ces
	titres seraient ceux des cat�gories que vous cr�eriez. Sous chacune de ces cat�gories vous voudriez assur�ment ins�rer, de mani�re hi�rarchique, des <em>sous-cat�gories</em>,
	ou &quot;sections&quot;, pour des sujets les concernant. C\'est une simple hi�rarchie, avec cette structure&nbsp;: <br />
	<ul class="normallist">
		<li>
			<strong>Sports</strong>
			&nbsp;- Une &quot;cat�gorie&quot;
		</li>
		<ul class="normallist">
			<li>
				<strong>Baseball</strong>
				&nbsp;- Une section de la cat�gorie &quot;Sports&quot;
			</li>
			<ul class="normallist">
				<li>
					<strong>Stats</strong>
					&nbsp;- Une sous-section de la section &quot;Baseball&quot;
				</li>
			</ul>
			<li><strong>Football</strong>
			&nbsp;- Une section de la cat�gorie &quot;Sports&quot;</li>
		</ul>
	</ul>
	Les cat�gories vous permettent de s�parer votre forum en diff�rents sujets (&quot;Voitures,
	Sports&quot;), et les &quot;sections&quot; en dessous sont les sujets dans lesquels
	vos membres peuvent poster. Un utilisateur int�ress� par les Twingo
	voudra poster un message dans &quot;Voitures->Twingo&quot;. Les cat�gories permettent aux gens
	de rapidement trouver ce qui les int�resse&nbsp;: au lieu d\'un &quot;Magasin&quot;, vous avez
	un &quot;Magasin d\'informatique&quot; et un &quot;Magasin de chaussures&quot; o� vous pouvez aller. Cela simplifie
	votre recherche d\'un &quot;disque dur&quot;, parce que vous pouvez aller directement au &quot;Magasin d\'informatique&quot;
	plut�t qu\'au &quot;Magasin de chaussures&quot; (o� vous ne trouverez sans doute pas votre disque dur ;) ).
	<br />
	Comme pr�cis� plus haut, une section est un sujet cl� sous une cat�gorie m�re.
	Si vous voulez discuter de &quot;Twingo&quot;, vous irez � la cat�gorie &quot;Voitures&quot; et
	irez � la section &quot;Twingo&quot; pour y poster votre avis � propos de cette automobile.<br />
	Les fonctions administratives possibles ici sont de cr�er des nouvelles sections
	sous chaque cat�gorie, r�ordonner les sections (placer &quot;Twingo&quot; sous &quot;Renault&quot;), ou
	supprimer une section enti�rement.';

$helptxt['edit_news'] = '
	<ul class="normallist">
		<li>
			<strong>Nouvelles</strong><br />
			Cette partie vous permet de d�finir du contenu pour les news de la page d\'accueil.
			Mettez-y ce que vous voulez (par ex., &quot;Ne manquez pas la conf�rence de mardi prochain&quot;). Les news sont affich�es de mani�re al�atoire et doivent �tre plac�es dans des bo�tes s�par�es.
		</li>
		<li>
			<strong>Infolettres</strong><br />
			Cette partie vous permet d\'envoyer des newsletters (infolettres) aux membres du forum par message personnel ou e-mail. Choisissez d\'abord les groupes auxquels envoyer l\'infolettre, puis ceux dont vous ne voulez pas qu\'ils la re�oivent. Si vous le d�sirez, vous pouvez ajouter des membres et adresses e-mail individuellement. Enfin, mettez le contenu du message � envoyer, et choisissez le type d\'envoi (message personnel sur le forum, ou e-mail).
		</li>
		<li>
			<strong>Param�tres</strong><br />
				Cette partie contient des r�glages li�s aux news et aux infolettres, par exemple le choix des groupes qui peuvent modifier les news ou envoyer des infolettres. Vous pouvez �galement param�trer l\'activation des flux RSS sur le forum, mais aussi choisir la longueur maximale des messages (en caract�res) dans ces m�mes flux RSS.
		</li>
	</ul>';

$helptxt['view_members'] = '
	<ul class="normallist">
		<li>
			<strong>Voir tous les membres</strong><br />
			Voir tous les membres dans votre forum. Il vous est pr�sent� une liste d\'hyperliens avec les pseudos des membres. Vous pouvez cliquer
			sur n\'importe lequel de ces pseudos pour trouver plus de d�tails � propos du membre (site web, �ge, etc.), et en tant qu\'administrateur
			vous avez la possibilit� de modifier ces param�tres. Vous avez un contr�le sur vos membres, incluant la possibilit�
			de les supprimer de votre forum.<br /><br />
		</li>
		<li>
			<strong>En attente d\'approbation</strong><br />
			Cette rubrique n\'est affich� que si vous avez activ� l\'approbation par un administrateur des nouvelles inscriptions � votre forum. Peu importe qui s\'inscrit pour rejoindre votre
			forum, il ne sera un membre complet qu\'une fois son compte approuv� par un admin. La rubrique liste tous ces membres qui
			sont encore en attente d\'approbation, de m�me que leur adresse e-mail et leur adresse IP. Vous pouvez choisir d\'accepter ou de rejeter (supprimer)
			n\'importe quel membre dans la liste en cochant la case suivant le nom du membre et en choisissant l\'action correcte � appliquer dans le menu d�roulant au bas
			de l\'�cran. Lorsque vous rejetez un membre, vous pouvez choisir de le supprimer en l\'avertissant ou non de votre d�cision.<br /><br />
		</li>
		<li>
			<strong>En attente d\'activation</strong><br />
			Cette rubrique n\'est visible que si vous avez choisi l\'activation des comptes des membres sur votre forum. Cette section liste tous
			les membres qui n\'ont pas encore activ� leur nouveau compte. Depuis cet �cran, vous pouvez choisir de les accepter, de les rejeter ou de leur rappeler
			l\'activation de leur compte. Comme pour le param�tre pr�c�dent, vous avez la possibilit� d\'informer ou non le membre
			des actions que vous avez effectu�es.<br /><br />
		</li>
	</ul>';

$helptxt['ban_members'] = '<strong>Bannir des membres</strong><br />
	SMF offre la possibilit� de &quot;bannir&quot; des utilisateurs, afin d\'emp�cher le retour de personnes ayant d�rang�
	l\'atmosph�re de votre forum par du pollupostage (spamming), des d�viations de sujets (trolling), etc. En tant qu\'administrateur,
	lorsque vous voyez un message, vous pouvez voir l\'adresse IP du posteur au moment de l\'envoi du message incrimin�. Dans la liste de bannissement,
	vous entrez simplement cette adresse IP, sauvegardez, et l\'utilisateur banni ne pourra plus poster depuis son ordinateur. <br />Vous pouvez aussi
	bannir des gens par leur adresse e-mail.';

$helptxt['featuresettings'] = '<strong>Modifier les Options et Fonctionnalit�s</strong><br />
	Il y a plusieurs fonctionnalit�s dans cette section qui peuvent �tre chang�es � votre pr�f�rence.';

$helptxt['modsettings'] = '<strong>Modifier les Caract�ristiques et les Options</strong><br />
	Plusieurs options peuvent �tre modifi�es ici selon vos pr�f�rences. Les options pour les modifications (mods) install�es vont g�n�ralement appara�tre ici.';

$helptxt['time_format'] = '<strong>Format de l\'heure</strong><br />
	Vous avez la possibilit� d\'ajuster la mani�re dont le temps et les dates seront affich�s sur votre forum. Il y a beaucoup de lettres, mais c\'est relativement simple. La convention d\'�criture s\'accorde avec celle de la fonction <tt>strftime</tt> de PHP et est d�crite ci-dessous (plus de d�tails peuvent �tre trouv�s sur <a href="http://www.php.net/manual/fr/function.strftime.php" target="_blank">php.net</a>).<br />
	<br />
	Les caract�res suivants sont reconnus en tant qu\'entr�es dans la cha�ne du format de l\'heure&nbsp;: <br />
	<span class="smalltext">
	&nbsp;&nbsp;%a - Nom du jour (abr�g�)<br />
	&nbsp;&nbsp;%A - Nom du jour (complet)<br />
	&nbsp;&nbsp;%b - Nom du mois (abr�g�)<br />
	&nbsp;&nbsp;%B - Nom du mois (complet)<br />
	&nbsp;&nbsp;%d - Jour du mois (01 � 31)<br />
	&nbsp;&nbsp;%D - La m�me chose que %m/%d/%y *<br />
	&nbsp;&nbsp;%e - Jour du mois (1 � 31) *<br />
	&nbsp;&nbsp;%H - Heure au format 24 heures (de 00 � 23)<br />
	&nbsp;&nbsp;%I - Heure au format 12 heures (de 01 � 12)<br />
	&nbsp;&nbsp;%m - Num�ro du mois (01 � 12)<br />
	&nbsp;&nbsp;%M - Minutes en chiffres<br />
	&nbsp;&nbsp;%p - Met &quot;am&quot; ou &quot;pm&quot; selon la p�riode de la journ�e<br />
	&nbsp;&nbsp;%R - Heure au format 24 heures *<br />
	&nbsp;&nbsp;%S - Secondes en chiffres<br />
	&nbsp;&nbsp;%T - Temps en ce moment, la m�me chose que %H:%M:%S *<br />
	&nbsp;&nbsp;%y - Ann�e au format 2 chiffres (00 to 99)<br />
	&nbsp;&nbsp;%Y - Ann�e au format 4 chiffres<br />
	&nbsp;&nbsp;%% - Le symbole \'%\' en lui-m�me<br />
	<br />
	<em>* Ne fonctionnent pas sur les serveurs Windows.</em></span>';

$helptxt['live_news'] = '<strong>En direct de Simple Machines...</strong><br />
	Cette bo�te affiche les derni�res d�p�ches en provenance de <a href="http://www.simplemachines.org/" target="_blank">www.simplemachines.org</a>.
	Vous devriez y surveiller les annonces concernant les mises � jour, nouvelles versions de SMF et informations importantes de Simple Machines.';

$helptxt['registrations'] = '<strong>Gestion des inscriptions</strong><br />
	Cette section contient toutes les fonctions n�cessaires pour la gestion des nouvelles inscriptions sur votre forum. Elle peut contenir jusqu\'� quatre
	rubriques, visibles selon vos param�tres de forum. Celles-ci sont d�taill�s ci-dessous&nbsp;:<br /><br />
	<ul class="normallist">
		<li>
			<strong>Inscrire un nouveau membre</strong><br />
			� partir de cet �cran, vous pouvez inscrire un nouveau membre � sa place. Cette option peut �tre utile lorsque les nouvelles inscriptions sur le forum sont d�sactiv�es,
			ou lorsque l\'administrateur souhaite se cr�er un compte de test. Si l\'activation du nouveau compte par le membre est s�lectionn�e,
			le nouveau membre recevra un e-mail contenant un lien d\'activation, sur lequel il devra cliquer avant de pouvoir utiliser son compte. De m�me, vous pouvez choisir d\'envoyer
			le nouveau mot de passe � l\'adresse e-mail sp�cifi�e.
		</li>
			<strong>Modifier l\'accord d\'inscription</strong><br />
			Ceci vous permet de sp�cifier le texte de l\'accord d\'inscription affich� lors de l\'inscription d\'un membre sur votre forum.
			Vous pouvez ajouter ou enlever ce que vous souhaitez au texte d\'accord inclus par d�avec SMF.<br /><br />
		</li>
		<li>
			<strong>Choisir les noms r�serv�s</strong><br />
			En utilisant cette interface, vous pouvez sp�cifier des mots ou des noms qui ne seront pas utilis�s librement par vos membres comme identifiants ou pseudonymes.<br /><br />
		</li>
		<li>
			<strong>Param�tres</strong><br />
			Cette section ne sera visible que si vous avez la permission d\'administrer le forum. Depuis cette interface, vous pouvez choisir la m�thode d\'inscription
			en vigueur sur votre forum et configurer quelques autres r�glages relatifs � l\'inscription.
		</li>
	</ul>';

$helptxt['modlog'] = '<strong>Journal de Mod�ration</strong><br />
	Cette section permet � l\'�quipe des administrateurs de conserver des traces de chaque action de mod�ration effectu�e sur le forum par un mod�rateur ou un administrateur (voire m�me par un membre). Afin que
	les mod�rateurs ne puissent enlever les r�f�rences aux actions entreprises, les entr�es ne pourront �tre supprim�es que 24 heures apr�s leur application.
	La colonne \'Objet\' liste les variables associ�es � l\'action.';
$helptxt['adminlog'] = '<strong>Journal d\'Administration</strong><br />
	Cette section permet aux membres de l\'�quipe d\'administration de pister les actions effectu�es par tout administrateur sur le forum. Afin que les administrateurs ne puissent enlever les r�f�rences aux actions entreprises, les entr�es ne pourront �tre supprim�es que 24 heures apr�s leur application.';
$helptxt['userlog'] = '<strong>Profile Edits Log</strong><br>
	This page allows members of the admin team to view changes users make to their profiles, and is available from inside a user\'s profile area.';
$helptxt['warning_enable'] = '<strong>Syst�me d\'avertissement utilisateur</strong><br />
	Cette fonctionnalit� permet aux membres des �quipes d\'administration et de mod�ration d\'envoyer des avertissements aux utilisateurs, et d\'utiliser un niveau d\'avertissement pour d�terminer leurs actions possibles au niveau du forum. Apr�s avoir activ� cette fonctionnalit�, un nouveau param�tre sera disponible dans les permissions par section pour d�finir quels groupes pourront assigner des avertissements aux utilisateurs. Les niveaux d\'avertissement pourront �tre ajust�s � partir du profil des utilisateurs. Les options suivantes sont disponibles :
	<ul class="normallist">
		<li>
			<strong>Niveau d\'avertissement pour la mise sous surveillance d\'un utilisateur</strong><br />
			Ce r�glage d�finit le pourcentage de niveau d\'avertissement qu\'un utilisateur doit atteindre pour �tre automatiquement mis &quot;sous surveillance&quot;.
			Tous les utilisateurs qui sont &quot;sous surveillance&quot; apparaitront dans l\'endroit ad�quat du centre de mod�ration.
		</li>
		<li>
			<strong>Niveau d\'avertissement pour la mod�ration de messages</strong><br />
			Si ce niveau d\'avertissement est atteint par un utilisateur, ces messages devront �tre valid�s par un mod�rateur pour appara�tre sur le forum. Cela �crasera toutes les permissions par section qui pourront exister en relation avec la mod�ration des messages.
		</li>
		<li>
			<strong>Niveau d\'avertissement pour rendre muet un utilisateur</strong><br />
			Si ce niveau d\'avertissement est atteint par un utilisateur, il lui sera impossible d\'envoyer des messages. L\'utilisateur perdra ainsi tous ses droits pour poster.
		</li>
		<li>
			<strong>Points d\'avertissement maximum re�us d\'un utilisateur par jour</strong><br />
			Ce r�glage limite le nombre de points qu\'un mod�rateur peut ajouter/retirer � un utilisateur particulier sur une p�riode de vingt-quatre heures. Cela pourra �tre utile pour limiter ce que peut faire un mod�rateur sur une courte p�riode de temps. Ce r�glage peut �tre d�sactiv� en mettant cette valeur � z�ro. Notez que tout utilisateur avec des permissions d\'administration n\'est pas affect� par cette valeur.
		</li>
	</ul>';
$helptxt['warning_watch'] = 'This setting defines the percentage warning level a member must reach to automatically assign a &quot;watch&quot; to the member. Any member who is being &quot;watched&quot; will appear in the watched members list in the moderation center.';
$helptxt['warning_moderate'] = 'Any member passing the value of this setting will find all their posts require moderator approval before they appear to the forum community. This will override any local board permissions which may exist related to post moderation.';
$helptxt['warning_mute'] = 'If this warning level is passed by a member they will find themselves under a post ban. The member will lose all posting rights.';
$helptxt['user_limit'] = 'This setting limits the amount of points a moderator may add/remove to any particular member in a twenty four hour period. This
			can be used to limit what a moderator can do in a small period of time. This can be disabled by setting it to a value of zero. Note that
			any members with administrator permissions are not affected by this value.';

$helptxt['error_log'] = '<strong>Journal d\'Erreurs</strong><br />
	Le journal d\'erreurs conserve des traces de toutes les erreurs s�rieuses rencontr�es lors de l\'utilisation de votre forum. Il liste toutes les erreurs par date, qui peuvent �tre r�cup�r�es
	en cliquant sur la fl�che noire accompagnant chaque date. De plus, vous pouvez filtrer les erreurs en s�lectionnant l\'image accompagnant les statistiques des erreurs. Ceci
	vous permet, par exemple, de filtrer les erreurs par nom de membre. Lorsqu\'un filtre est actif les seuls r�sultats affich�s seront ceux correspondants aux crit�res du filtre.';
$helptxt['theme_settings'] = '<strong>R�glages du Th�me</strong><br />
	L\'�cran des r�glages vous permet de modifier certains r�glages sp�cifiques � un th�me. Ces r�glages incluent des options telles que le r�pertoire du th�me et l\'URL du th�me, mais
	aussi des options affectant le rendu � l\'�cran de votre forum. La plupart des th�mes poss�dent une vari�t� d\'options configurables par l\'utilisateur, vous permettant d\'adapter un th�me
	� vos besoins individuels.';
$helptxt['smileys'] = '<strong>Gestionnaire de smileys</strong><br />
	Ici, vous pouvez ajouter et supprimer des smileys et des jeux de smileys. Note importante&nbsp;: si un smiley est pr�sent dans un jeu, il l\'est aussi dans tous les autres - autrement, cela pourrait pr�ter �
	confusion pour les utilisateurs utilisant des jeux diff�rents.<br /><br />

	Vous pouvez aussi modifier les ic�nes de message depuis cette interface, si vous les avez activ�s sur la page des param�tres.';
$helptxt['calendar'] = '<strong>G�rer le calendrier</strong><br />
	Ici vous pouvez modifier les r�glages courants du calendrier, ou ajouter et supprimer des f�tes qui apparaissent dans le calendrier.';
$helptxt['cal_export'] = 'Exporte un fichier texte au format iCal pour importer vers d\'autres applications de calendrier';
$helptxt['cal_highlight_events'] = 'This setting allows you to highlight events on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_highlight_holidays'] = 'This setting allows you to highlight holidays on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_highlight_birthdays'] = 'This setting allows you to highlight birthdays on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_disable_prev_next'] = 'If this setting is checked, the three month blocks on the left hand side of the page will be disabled.';
$helptxt['cal_display_type'] = 'This setting allows you to change the display type of the calendar.<br><br><strong>Comfortable:</strong> makes the rows of the calendar big.<br><strong>Compact:</strong> makes the rows of the calendar small.';
$helptxt['cal_week_links'] = 'If this setting is checked, links will be added alongside each week in the calendar.';
$helptxt['cal_prev_next_links'] = 'If this setting is checked, previous month and next month links will be added to the top of each month for easy navigation.';
$helptxt['cal_short_months'] = 'If this setting is checked, month names within the calendar will be shortened.<br><br><strong>Enabled:</strong> ' . $txt['months_short'][1] . ' 1<br><strong>Disabled:</strong> ' . $txt['months_titles'][1] . ' 1';
$helptxt['cal_short_days'] = 'If this setting is checked, day names within the calendar will be shortened.<br><br><strong>Enabled:</strong> ' . $txt['days_short'][1] . '<br><strong>Disbaled:</strong> ' . $txt['days'][1];

$helptxt['serversettings'] = '<strong>Param�tres Serveur</strong><br />
	Ici, vous pouvez r�gler la configuration de votre serveur. Cette section comprend la base de donn�es et les chemins des dossiers, ainsi que d\'autres
	options de configuration importantes tels que les param�tres d\'e-mail et de cache. Faites attention lors de la modification de ces param�tres,
	ils pourraient rendre le forum inaccessible';
$helptxt['manage_files'] = '
	<ul class="normallist">
		<li>
			<strong>Parcourir les Fichiers</strong><br />
			Parcourir � travers tous les fichiers joints, avatars et miniatures stock�s par SMF.<br /><br />
		</li><li>
			<strong>R�glages des Fichiers Joints</strong><br />
			Configurer o� sont stock�s les fichiers joints et mettre les restrictions sur les types de fichiers joints.<br /><br />
		</li><li>
			<strong>R�glages des Avatars</strong><br />
			Configurer o� sont stock�s les avatars et g�rer le redimensionnement des avatars.<br /><br />
		</li><li>
			<strong>Maintenance des Fichiers</strong><br />
			Contr�ler et r�parer toute erreur dans le r�pertoire des fichiers joints et effacer les fichiers joints s�lectionn�s.<br /><br />
		</li>
	</ul>';

$helptxt['topicSummaryPosts'] = 'Ceci vous permet de r�gler le nombre de messages pr�c�demment post�s affich�s dans le sommaire du sujet sur l\'�cran de r�ponse � un sujet.';
$helptxt['enableAllMessages'] = 'Mettez ici le nombre <em>maximum</em> de messages qu\'un sujet aura lors de l\'affichage par le lien &quot;Tous&quot;. Le r�gler au-dessous du &quot;Nombre de messages � afficher lors du visionnement d\'un sujet:&quot; signifiera simplement que le lien ne sera jamais affich�, et indiquer une valeur trop �lev�e peut ralentir votre forum.';
$helptxt['allow_guestAccess'] = 'D�cocher cette option limitera les actions possibles des invit�s aux seules op�rations de base - connexion, inscription, rappel du mot de passe, etc. - sur votre forum. Ce n\'est pas comme d�sactiver l\'acc�s aux sections pour les invit�s.';
$helptxt['userLanguage'] = 'Activer cette option permettra aux utilisateurs de s�lectionner la langue dans laquelle le forum leur sera affich�.
	Cela n\'affectera pas la langue par d�faut.';
$helptxt['trackStats'] = 'Stats&nbsp;:<br />Ceci permettra aux visiteurs de voir les derniers messages post�s et les sujets les plus populaires sur votre forum.
	Cela affichera aussi plusieurs autres statistiques, comme le record d\'utilisateurs en ligne au m�me moment, les nouveaux membres et les nouveaux sujets.<hr />
	Pages vues&nbsp;:<br />Ajoute une autre colonne � la page des statistiques contenant le nombre de pages vues sur votre forum.';
$helptxt['titlesEnable'] = 'Activer les titres personnels permettra aux membres poss�dant les permissions suffisantes de s\'attribuer un titre sp�cial pour eux-m�mes.
		Il sera affich� sous leur pseudonyme.<br /><em>Par exemple :</em><br />Loulou<br />Oui, c\'est moi';
$helptxt['onlineEnable'] = 'Ceci affichera une image indiquant si l\'utilisateur est connect� ou non en ce moment.';
$helptxt['todayMod'] = 'Cette option affichera &quot;Aujourd\'hui&quot; ou &quot;Hier&quot; � la place de la date.<br /><br />
		<strong>Exemples&nbsp;:</strong><br /><br />
		<dt>
			<dt>D�sactiv�</dt>
			<dd>3 Octobre 2009 � 00:59:18</dd>
			<dt>Seulement Aujourd\'hui</dt>
			<dd>Aujourd\'hui � 00:59:18</dd>
			<dt>Aujourd\'hui &amp; Hier</dt>
			<dd>Hier � 21:36:55</dd>
		</dt>';
$helptxt['disableCustomPerPage'] = 'Cocher cette option pour emp�cher les utilisateurs de personnaliser le nombre de messages et de sujets par page � afficher, respectivement sur l\'index des messages et la page d\'affichage du sujet.';
$helptxt['enablePreviousNext'] = 'Cette option affichera un lien vers le sujet pr�c�dent et le sujet suivant.';
$helptxt['pollMode'] = 'Ceci d�termine si les sondages sont activ�s ou non. Si les sondages sont d�sactiv�s, tous les sondages actuels sont cach�s sur la liste des sujets. Vous pouvez choisir de continuer � afficher la partie sujet des sondages en s�lectionnant &quot;Montrer les sondages existants comme des sujets&quot;.<br /><br />Pour choisir qui peut poster et voir des sondages (et similaires), vous pouvez autoriser ou refuser ces permissions. Rappelez-vous de ceci si les sondages sont d�sactiv�s.';
$helptxt['enableCompressedOutput'] = 'Cette option compressera les donn�es envoy�es, afin de diminuer la consommation de bande passante, mais requiert que zlib soit install� sur le serveur.';
$helptxt['disableTemplateEval'] = 'Par d�faut, les mod�les de th�me sont �valu�s au lieu d\'�tre simplement inclus, afin de pouvoir afficher plus d\'informations en cas d\'erreur du traitement.<br /><br />Toutefois, sur des forums de grande taille, ce processus peut ralentir sensiblement le traitement. Les utilisateurs aguerris peuvent donc pr�f�rer le d�sactiver.';
$helptxt['httponlyCookies'] = 'Cookies won\'t be accessible by scripting languages, such as JavaScript. This setting can help to reduce identity theft through XSS attacks. This can cause issues with third party scripts but should be on wherever possible.';
$helptxt['databaseSession_enable'] = 'Cette fonction utilise la base de donn�es pour le stockage des sessions - c\'est mieux pour des serveurs � charge balanc�e, mais aide � r�gler tous les probl�mes de fin de session ind�sir�e et peut aider le forum � fonctionner plus rapidement.';
$helptxt['databaseSession_loose'] = 'Activer cette option diminuera la bande passante utilis�e par le forum, et fait en sorte que lorsque l\'utilisateur revient sur ses pas, la page n\'est pas recharg�e - le point n�gatif de cette option est que les (nouvelles) ic�nes ne seront pas mises � jour, ainsi que quelques autres choses. (Sauf si vous rechargez cette page plut�t que de retourner sur vos pas.)';
$helptxt['databaseSession_lifetime'] = 'Ceci est le temps en secondes au bout duquel la session se termine automatiquement apr�s le dernier acc�s de l\'utilisateur. Si une session n\'a pas �t� acc�d�e depuis trop longtemps, un message &quot;Session termin�e&quot; est affich�. Tout ce qui est au-dessus de 2400 secondes est recommand�.';
$helptxt['tfa_mode'] = 'You can add a second level of security to your forum by enabling <a href="http://en.wikipedia.org/wiki/Two_factor_authentication">Two Factor Authentication</a>. 2FA forces your users to add a enter a machine-generated code after the regular login. You need to configure 2FA to yourself before you are able to force it to other users!';
$helptxt['frame_security'] = 'Modern browsers now understand a security header presented by servers called X-Frame-Options. By setting this option you specify how you want to allow your site to be framed inside a frameset or a iframe. Disable will not send any header and is the most unsecure, however allows the most freedom. Deny will prevent all frames completely and is the most restrictive and secure. Allowing the Same Origin will only allow your domain to issue any frames and provides a middle ground for the previous two options.';
$helptxt['cache_enable'] = 'SMF g�re plusieurs niveaux de cache. Plus le niveau de cache activ� est �lev�, plus le CPU prendra de temps pour r�cup�rer les informations cach�es. Si le cache est disponible sur votre machine, il est recommand� que vous essayiez le niveau 1 en premier.';
$helptxt['cache_memcached'] = 'Veuillez noter que l\'utilisation de memcache n�cessite que vous donniez quelques indications sur votre serveur dans les r�glages � effectuer ci-dessous. Elles doivent �tre entr�es sous forme de liste, dont les �l�ments sont s�par�s par une virgule, comme dans l\'exemple suivant :
<br /><br/> &quot;"serveur1,serveur2,serveur3:port,serveur4"&quot;<br /><br />
Si aucun port n\'est sp�cifi�, SMF utilisera le port 11211 par d�faut. SMF �quilibrera de mani�re al�atoire la charge sur les serveurs. 

';
$helptxt['cache_cachedir'] = 'This setting is only for the smf file-based cache system. It specifies the path to the cache directory. It is recommended that you place this in /tmp/ if you are going to use this, although it will work in any directory';
$helptxt['enableErrorLogging'] = 'Ceci indexera toutes les erreurs rencontr�es, comme les connexions non r�ussies, afin que vous puissiez les consulter lorsque quelque chose ne va pas.';
$helptxt['enableErrorQueryLogging'] = 'Ceci incluera la requ�te compl�te envoy�e � la base de donn�es lors d\'une erreur de cette derni�re, dans le journal d\'erreurs. Requiert l\'activation du journal d\'erreurs.<br /><br /><strong>Attention, cela modifiera la capacit� de filtrage du journal d\'erreurs par message d\'erreur.</strong>';
$helptxt['log_ban_hits'] = 'If enabled, every time a banned user tries to access the site, this will be logged in the error log. If you do not care whether, or how often, banned users attempt to access the site, you can turn this off for a performance boost.';
$helptxt['allow_disableAnnounce'] = 'Ceci permettra aux utilisateurs de d�s�lectionner la r�ception des annonces du forum que vous envoyez en cochant &quot;Annoncer le sujet&quot; lorsque vous postez un message.';
$helptxt['disallow_sendBody'] = 'Cette option supprime l\'option permettant de recevoir le texte des r�ponses et les messages dans les e-mails de notification.<br /><br />Souvent, les membres vont r�pondre � l\'e-mail de notification, ce qui peut saturer, dans bien des cas, la bo�te e-mail du webmestre.';
$helptxt['enable_ajax_alerts'] = 'This option allows your members to receive AJAX notifications. This means that members don\'t need to refresh the page to get new notifications.<br><b>DO NOTE:</b> This option might cause a severe load at your server with many users online.';
$helptxt['jquery_source'] = 'This will determine the source used to load the jQuery Library. <em>Auto</em> will use the CDN first and if not available fall back to the local source. <em>Local</em> will only use the local source. <em>CDN</em> will only load it from Google CDN network';
$helptxt['compactTopicPagesEnable'] = 'Ceci est le nombre de pages interm�diaires � afficher lors du visionnement d\'un sujet.<br /><em>Exemple&nbsp;:</em>
		&quot;3&quot; pour afficher&nbsp;: 1 ... 4 [5] 6 ... 9 <br />
		&quot;5&quot; pour afficher&nbsp;: 1 ... 3 4 [5] 6 7 ... 9';
$helptxt['timeLoadPageEnable'] = 'Ceci affichera au bas du forum le temps en secondes utilis� par SMF pour g�n�rer la page en cours.';
$helptxt['removeNestedQuotes'] = 'Ceci effacera les citations imbriqu�es dans les messages que vous citez en cliquant sur le bouton Citer.';
$helptxt['max_image_width'] = 'Cette option vous permet de sp�cifier une taille maximale pour les images post�es. Les images plus petites ne seront pas affect�es.';
$helptxt['mail_type'] = 'Cette option vous permet d\'utiliser soit le r�glage par d�faut de PHP ou de l\'outrepasser en utilisant le protocole SMTP. PHP ne supporte pas l\'authentification (que plusieurs FAI requi�rent maintenant) donc vous devriez vous renseigner avant d\'utiliser cette option. Notez que SMTP peut �tre plus lent que sendmail et que certains serveurs ne prendront pas en compte les identifiants et mot de passe.<br /><br />Vous n\'avez pas � renseigner les informations SMTP si vous utilisez la configuration par d�faut de PHP.';
$helptxt['attachment_manager_settings'] = 'Les fichiers joints sont des fichiers que les membres peuvent uploader, et attacher � un message.<br /><br />
		<strong>Contr�ler les extensions de fichier joint</strong>:<br /> Voulez vous contr�ler l\'extension des fichiers&nbsp;?<br />
		<strong>Extensions de fichier autoris�es</strong>:<br /> Vous pouvez mettre les extensions de fichiers joints autoris�es.<br />
		<strong>R�pertoire des fichiers joints</strong>:<br /> Le chemin vers le dossier de fichiers joints<br />(exemple: /home/sites/yoursite/www/forum/attachments)<br />
		<strong>Espace Max dossier fichiers joints</strong> (en Ko):<br /> S�lectionnez de quelle taille le dossier de fichiers joints peut t\'il �tre, en incluant tous les fichiers contenus.<br />
		<strong>Taille Max de fichiers joints par message</strong> (en Ko):<br /> S�lectionnez la taille de fichier maximum de tous les fichiers joints d\'un m�me message. Si elle est inf�rieure � la limite de taille de fichier joint, cela sera la limite.<br />
		<strong>Taille maximum par fichier joint</strong> (en Ko):<br /> S�lectionnez la taille de fichier maximum de chaque fichier joint.<br />
		<strong>Nombre maximum de fichiers joints par message</strong>:<br /> S�lectionnez le nombre de fichiers joints qu\'une personne peut mettre par message.<br />
		<strong>Afficher un fichier joint comme une image dans les messages</strong>:<br /> Si le fichier upload� est une image, elle sera affich�e sous le message.<br />
		<strong>Redimensionner les images quand affich�es sous les messages</strong>:<br /> Si l\'option au-dessus est s�lectionn�e, cela sauvegardera une copie (plus petite) du fichier joint pour la miniature afin d\'�conomiser la bande passante.<br />
		<strong>Taille et hauteur maximum des miniatures</strong>:<br /> Seulement utilis� avec l\'option &quot;Redimensionner les images quand affich�es sous les messages&quot;, sp�cifie la taille et la hauteur maximales des miniatures cr��es pour les fichiers joints. Elles seront redimensionn�es proportionnellement.';
$helptxt['attachmentCheckExtensions'] = 'For some communities, you may wish to limit the types of files that users can upload by checking the extension: e.g. myphoto.jpg has an extension of jpg.';
$helptxt['attachmentExtensions'] = 'If "check attachment\'s extension" above is ticked, these are the extensions that will be permitted for new attachments.';
$helptxt['attachmentUploadDir'] = 'The path to your attachment folder on the server<br>(example: /home/sites/yoursite/www/forum/attachments)';
$helptxt['attachmentDirSizeLimit'] = ' Select how large the attachment folder can be, including all files within it.';
$helptxt['attachmentPostLimit'] = ' Select the maximum filesize (in KB) of all attachments made per post. If this is lower than the per-attachment limit, this will be the limit.';
$helptxt['attachmentSizeLimit'] = 'Select the maximum filesize of each separate attachment.';
$helptxt['attachmentNumPerPostLimit'] = ' Select the number of attachments a person can make per post.';
$helptxt['attachmentShowImages'] = 'If the uploaded file is a picture, it will be displayed underneath the post.';
$helptxt['attachmentThumbnails'] = 'If the above setting is selected, this will save a separate (smaller) attachment for the thumbnail to decrease bandwidth.';
$helptxt['attachmentThumbWidth'] = 'Only used with the &quot;Resize images when showing under posts&quot; setting the maximum width to resize attachments down from. They will be resized proportionally.';
$helptxt['attachmentThumbHeight'] = 'Only used with the &quot;Resize images when showing under posts&quot; setting the maximum height to resize attachments down from. They will be resized proportionally.';
$helptxt['attachmentDirFileLimit'] = 'Max number of files per directory';
$helptxt['attachmentEnable'] = 'This setting enables you to configure how attachments can be made.<br><br>
	<ul class="normallist">
		<li>
			<strong>Disable all attachments</strong><br>
			All attachments are disabled. Existing attachments are not deleted, but they are hidden from view (even administrators cannot see them). New attachments cannot be made either, regardless of permissions.<br><br>
		</li>
		<li>
			<strong>Enable all attachments</strong><br>
			Everything behaves as normal, users who are permitted to view attachments can do so, users who are permitted to upload can do so.<br><br>
		</li>
		<li>
			<strong>Disable new attachments</strong><br>
			Existing attachments are still accessible, but no new attachments can be added, regardless of permission.
		</li>
	</ul>';
$helptxt['attachment_image_paranoid'] = 'Choisissez cette option pour mettre en place des contr�les de s�curit� tr�s stricts sur les images envoy�es en fichier joint. Attention, ces contr�les peuvent parfois �chouer sur des images sans danger. Nous vous recommandons de ne l\'utiliser qu\'en association avec l\'option de r�encodage, auquel cas SMF essaiera de recr�er et de mettre en ligne des images saines si le contr�le �choue. Si le r�encodage n\'est pas activ�, les fichiers joints �chouant au contr�le seront rejet�s.';
$helptxt['attachment_image_reencode'] = 'Choisissez cette option pour permettre le r�encodage des fichier joints envoy�s par les utilisateurs. Le r�encodage vous garantit une meilleure s�curit�, mais il supprime �galement les animations des images anim�es.<br />Cette fonctionnalit� n\'est disponible que si le module GD est install� sur votre serveur.';
$helptxt['attachment_thumb_memory'] = 'The larger the source image (size & width x height), the higher the memory requirements are for the system to successfully create a thumbnail image.<br>Checking this setting, the system will estimate the required memory and will then request that amount. If successful, only then will it attempt to create the thumbnail.<br>This will result in fewer white page errors but may result in fewer thumbnails being created. Leaving this unchecked will always cause the system to try to create the thumbnail (with a fixed amount of memory). This may result in more white page errors.';
$helptxt['attachmentRecodeLineEndings'] = 'The script will re-code line endings according to your server.';
$helptxt['automanage_attachments'] = 'By default, SMF puts new attachments into a single folder. For most sites this is not a problem, but as a site grows it can be useful to have multiple folders to store attachments in.<br><br>This setting allows you to set whether you manage these folders yourself (e.g. creating a second folder and moving to it when you are ready) or whether you let SMF do it, based on criteria, such as when the current directory reaches a given size, or breaking down folders by years or even months on very busy sites.';
$helptxt['use_subdirectories_for_attachments'] = 'Create new directories.';
$helptxt['max_image_height'] = 'As with the maximum width, this setting indicates the maximum height a posted image can be.';
$helptxt['avatar_paranoid'] = 'Choisissez cette option pour mettre en place des contr�les de s�curit� tr�s stricts sur les avatars au moment o� les utilisateurs les envoient. Attention, ces contr�les peuvent parfois �chouer sur des images sans danger. Nous vous recommandons de ne l\'utiliser qu\'en association avec l\'option de r�encodage, auquel cas SMF essaiera de recr�er et de mettre en ligne des images saines si le contr�le �choue. Si le r�encodage n\'est pas activ�, les avatars �chouant au contr�le seront rejet�s.';
$helptxt['avatar_reencode'] = 'Choisissez cette option pour permettre le r�encodage des avatars envoy�s par les utilisateurs. Le r�encodage vous garantit une meilleure s�curit�, mais il supprime �galement les animations des images anim�es.<br />Cette option n\'est disponible que si le module GD est install� sur votre serveur.';
$helptxt['cal_enabled'] = 'Le calendrier peut �re utilis� afin d\'afficher les anniversaires et des dates importantes � votre communaut�.<br /><br />
		<strong>Montrer les jours en tant que liens vers \'Poster un �v�nement\'</strong>&nbsp;:<br />Ceci permettra � vos membres de poster des �v�nements pour ce jour, lorsqu\'ils cliquent sur la date.<br />
		<strong>Jours d\'avance max. sur l\'accueil</strong>:<br />Si cette option est mise � 7, tous les �v�nements de la semaine � venir seront montr�s.<br />
		<strong>Montrer les jours de f�te sur l\'accueil du forum</strong>&nbsp;:<br />Montre les jours de f�te dans une barre sur l\'accueil du forum.<br />
		<strong>Afficher les anniversaires sur l\'accueil du forum</strong>&nbsp;:<br />Montre les anniversaires du jour dans une barre sur l\'accueil du forum.<br />
		<strong>Montrer les �v�nements sur l\'accueil du forum</strong>&nbsp;:<br />Affiche les �v�nements du jour dans une barre sur l\'accueil du forum.<br />
		<strong>Section o� Poster par D�faut</strong>:<br />Quelle est la section par d�faut pour poster les �v�nements&nbsp;?<br />
		<strong>Permettre les �v�nements qui ne sont li�s � aucun message</strong>&nbsp;:<br />Permet aux membres de poster des �v�nements sans n�cessiter la cr�ation d\'un nouveau sujet dans le forum.<br />
		<strong>Ann�e minimale</strong>&nbsp;:<br />S�lectionne la &quot;premi�re&quot; ann�e dans la liste du calendrier.<br />
		<strong>Ann�e maximale</strong>&nbsp;:<br />S�lectionne la &quot;derni�re&quot; ann�e dans la liste du calendrier<br />
		<strong>Permettre aux �v�nements de durer plusieurs jours</strong>&nbsp;:<br />S�lectionnez pour permettre aux �v�nements de durer plusieurs jours.<br />
		<strong>Dur�e maximale (en jours) d\'un �v�nement</strong>&nbsp;:<br />S�lectionnez le nombre maximal de jours pour la dur� d\'un �v�nement.<br /><br />
		Rappelez-vous que l\'usage du calendrier (poster des �v�nements, voir des �v�nements, etc.) est contr�lable par les r�glages des permissions � partir de l\'�cran de gestion des permissions.';
$helptxt['localCookies'] = 'SMF utilise des t�moins (&quot;cookies&quot;) pour conserver les informations de connexion d\'un membre. Les t�moins peuvent �tre stock�s dans un dossier global (monserveur.com) ou localement (monserveur.com/chemin/vers/mon/forum).<br />
	Cochez cette option si vous constatez certains probl�mes avec des utilisateurs d�connect�s automatiquement.<hr />
	Les t�moins stock�s dans un dossier global sont moins s�curis�s lorsqu\'ils sont utilis�s sur un serveur mutualis� (comme Multimania/Lycos, Free, OVH, ...).<hr />
	Les t�moins stock�s localement ne fonctionnent pas � l\'ext�rieur du dossier du forum. Donc, si votre forum est install� dans le r�pertoire www.monserveur.com/forum, les pages telles que www.monserveur.com/index.php ne pourront pas acc�der aux t�moins. Lors de l\'utilisation de SSI.php, il est recommand� de stocker les t�moins dans un dossier global.';
$helptxt['enableBBC'] = 'Activer cette fonction autorisera vos membres � utiliser les balises (BBCodes) sur votre forum, afin de permettre la mise en forme du texte, l\'insertion d\'images et plus.';
$helptxt['time_offset'] = 'Tous les propri�taires de forums ne souhaitent pas forc�ment utiliser le fuseau horaire du serveur sur lequel ils sont h�berg�s. Utilisez cette fonction pour sp�cifier un temps de d�calage (en heures) sur lequel le forum devrait se baser pour les dates et heures. Les temps n�gatifs et d�cimaux sont permis.';
$helptxt['default_timezone'] = 'La zone horaire du serveur indique � PHP o� il se trouve. Assurez-vous que ladite zone soit correctement renseign�e, notamment le pays voire la ville. Vous trouverez plus d\'informations sur <a href="http://www.php.net/manual/fr/timezones.php" target="_blank">le site de PHP</a>.';
$helptxt['spamWaitTime'] = 'Ici vous pouvez sp�cifier le temps minimal requis entre deux envois de messages en provenance d\'un m�me utilisateur. Cette option peut �tre utilis�e afin de contrer le pollupostage (&quot;spamming&quot;).';

$helptxt['enablePostHTML'] = 'Ceci permet l\'utilisation de quelques balises HTML basiques&nbsp;:
	<ul class="normallist" style="margin-bottom: 0;">
		<li>&lt;b&gt;, &lt;u&gt;, &lt;i&gt;, &lt;s&gt;, &lt;em&gt;, &lt;ins&gt;, &lt;del&gt;</li>
		<li>&lt;a href=&quot;&quot;&gt;</li>
		<li>&lt;img src=&quot;&quot; alt=&quot;&quot; /&gt;</li>
		<li>&lt;br /&gt;, &lt;hr /&gt;</li>
		<li>&lt;pre&gt;, &lt;blockquote&gt;</li>
	</ul>';

$helptxt['themes'] = 'Ici vous pouvez choisir si le th�me par d�faut peut �tre utilis� ou non, quel th�me les invit�s verront ainsi que plusieurs autres options. Cliquez sur un th�me � droite pour changer ses propri�t�s sp�cifiques.';
$helptxt['theme_install'] = 'Ceci vous permet d\'installer des nouveaux th�mes. Vous pouvez proc�der en partant d\'un dossier d�j� cr��, en transf�rant une archive d\'un th�me ou en copiant le th�me par d�faut.<br /><br />Notez bien que les archives de th�mes doivent contenir un fichier de d�finition <tt>theme_info.xml</tt>.';
$helptxt['enableEmbeddedFlash'] = 'Cette option permettra � vos visiteurs d\'ins�rer des animations Flash directement dans leurs messages, comme des images. Ceci peut pr�senter un s�rieux risque de s�curit�, bien que peu nombreux soient ceux qui ont r�ussi l\'exploitation de ce risque.<br /><br />UTILISEZ CETTE OPTION � VOS PROPRES RISQUES&nbsp;!';
$helptxt['xmlnews_enable'] = 'Permet aux gens de faire r�f�rence aux <a href="%1$s?action=.xml;sa=news" target="_blank">derni�res nouvelles</a>
	et autres donn�es similaires. Il est recommand� de limiter la taille des messages puisque certains clients
	tels que Trillian pr�f�rent afficher des messages tronqu�s.';
$helptxt['globalCookies'] = 'Permet l\'utilisation de t�moins (<em>cookies</em>) ind�pendants du sous-domaine. Par exemple, si...<br />
	Votre site est situ� sur http://www.simplemachines.org/,<br />
	Et votre forum est situ� sur http://forum.simplemachines.org/,<br />
	Activer cette fonction vous permettra d\'utiliser les t�moins de votre forum sur votre site (gr�ce � SSI.php, par exemple).';
$helptxt['globalCookiesDomain'] = 'When using subdomain independent cookies (global cookies), you can specify which domain should be used for them. This should, of course, be set to your main domain - for example, if you are using <em>forum.example.com</em> and <em>www.example.com</em>, the domain is <em>example.com</em> in this case. You should not put the <em>http://</em> part in front of it.';
$helptxt['secureCookies'] = 'Activer cette option forcera la s�curisation des t�moins (cookies) cr��s pour les utilisateurs de votre forum. Ne l\'activez que si vous utilisez le protocole HTTPS sur tout votre site, faute de quoi la gestion des t�moins sera fortement perturb�e&nbsp;!';
$helptxt['securityDisable'] = 'Ceci <em>d�sactive</em> la v�rification suppl�mentaire du mot de passe pour acc�der � la zone d\'administration. �a n\'est pas recommand�&nbsp;!';
$helptxt['securityDisable_why'] = 'Ceci est votre mot de passe courant. (Le m�me que vous utilisez pour vous connecter au forum quoi.)<br /><br />Avoir � le taper de nouveau permet de v�rifier que vous voulez bien effectuer quelque op�ration d\'administration, et que c\'est bien <strong>vous</strong> qui le faites.';
$helptxt['securityDisable_moderate'] = 'This <em>disables</em> the additional password check for the moderation page. This is not recommended!';
$helptxt['securityDisable_moderate_why'] = 'This is your current password. (the same one you use to login).<br><br>The requirement to enter this helps ensure that you want to do whatever moderation you are doing, and that it is <strong>you</strong> doing it.';
$helptxt['proxy_ip_header'] = 'This is the server header that will be trusted by SMF for containing the actual users IP address. Changing this setting can cause unexpected IP results on members. Please check with your server administrator, CDN provider or proxy administrator prior to changing these settings. Most providers will understand and use HTTP_X_FORWARDED_FOR. You should fill out the list of Servers sending the reverse proxy headers for security to ensure these headers only come from valid sources.';
$helptxt['email_members'] = 'In this message you can use a few &quot;variables&quot;. These are:<br>
	{$board_url} - The URL to your forum.<br>
	{$current_time} - The current time.<br>
	{$member.email} - The current member\'s email.<br>
	{$member.link} - The current member\'s link.<br>
	{$member.id} - The current member\'s id.<br>
	{$member.name} - The current member\'s name. (for personalization).<br>
	{$latest_member.link} - The most recently registered member\'s link.<br>
	{$latest_member.id} - The most recently registered member\'s id.<br>
	{$latest_member.name} - The most recently registered member\'s name.';

$helptxt['failed_login_threshold'] = 'Sp�cifiez le nombre maximal de tentatives de connexion avant de rediriger l\'utilisateur vers la fonction &quot;Rappel de Mot de Passe&quot;.';
$helptxt['loginHistoryDays'] = 'The number of days to keep login history under user profile tracking. The default is 30 days.';
$helptxt['oldTopicDays'] = 'Si cette option est activ�e, un avertissement sera affich� aux utilisateurs qui tenteront de r�pondre dans un sujet dans lequel il n\'y a eu aucune intervention apr�s un certain laps de temps, en jours, sp�cifi� par ce param�tre. R�glez-la � 0 pour d�sactiver cette fonction.';
$helptxt['edit_wait_time'] = 'Temps en secondes permis pour la modification d\'un message avant que la mention &quot;Derni�re �dition&quot; apparaisse.';
$helptxt['edit_disable_time'] = 'Nombre de minutes accord�es � un utilisateur pour qu\'il puisse modifier ses messages. Mettre sur 0 pour d�sactiver. <br /><br /><em>Note: Cela n\'affectera pas l\'utilisateur qui a la permission de modifier les messages des autres.</em>';
$helptxt['preview_characters'] = 'This setting sets the number of available characters for the first and last message topic preview.';
$helptxt['posts_require_captcha'] = 'Ce r�glage forcera les utilisateurs � rentrer un code affich� sur une image de v�rification � chaque fois qu\'ils posteront un message. Seul les utilisateurs avec un compteur de messages en dessous du nombre choisi auront � entrer le code - Cela devrait aider � combattre les scripts automatiques de spam.';
$helptxt['enableSpellChecking'] = 'Active la v�rification orthographique. Vous DEVEZ avoir la librairie pspell install�e sur votre serveur et PHP doit �tre configur� de telle sorte qu\'il utilise cette librairie. Votre serveur ' . (function_exists('pspell_new') ? '<span style="color: green">semble</span>' : '<span style="color: red">NE SEMBLE PAS</span>') . ' avoir la librairie pspell.';
$helptxt['disable_wysiwyg'] = 'Ce r�glage d�sactivera l\'utilisation du WYSIWYG (acronyme de la locution anglaise &quot;What you see is what you get&quot;, signifiant litt�ralement en fran�ais &quot;vous aurez ce que vous voyez&quot;�) sur la page de r�daction des messages chez tous les utilisateurs.';
$helptxt['lastActive'] = 'S�lectionnez le nombre de minutes � afficher dans &quot;Membres actifs dans les X derni�res minutes&quot;, sur l\'accueil du forum. Par d�faut, la valeur est 15 minutes.';

$helptxt['customoptions'] = 'Cette section d�finit les options qu\'un utilisateur peut choisir � partir d\'une liste d�roulante. Il y a quelques points cl�s � noter pour cette section:
	<ul class="normallist">
		<li><strong>Option par d�faut:</strong> L\'option que vous aurez choisie ici sera celle d�finie par d�faut pour l\'utilisateur lorsqu\'il enregistrera son profil.</li>
		<li><strong>Options � retirer:</strong> Pour retirer une option, laisser simplement vide la boite de texte de cette option - celle-ci sera automatiquement supprim�e pour tous les utilisateurs l\'ayant pr�c�demment s�lectionn�e.</li>
		<li><strong>R�ordonner les Options:</strong> Vous pouvez modifier l\'ordre des options en les d�placant. Remarque importante - Assurez-vous de ne <strong>pas</strong> modifier le texte de ces options lorsque vous en modifiez l\'ordre, sinon vous perdrez les donn�es pr�lablement enregistr�es par vos utilisateurs pour ces options.</li>
	</ul>';

$helptxt['autoFixDatabase'] = 'Ceci r�parera automatiquement les tables pr�sentant des erreurs et ainsi, le forum continuera de fonctionner comme si rien ne s\'�tait produit. Ceci peut �tre utile, car la seule fa�on de r�gler le probl�me est de R�PARER la table en question, et gr�ce � cette option, le forum ne sera pas hors service en attendant que vous preniez les mesures n�cessaires. Un e-mail vous est envoy� lorsqu\'un tel probl�me se pr�sente.';

$helptxt['enableParticipation'] = 'Cette fonction affiche une ic�ne sp�ciale sur les sujets dans lesquels un utilisateur est pr�c�demment intervenu.';

$helptxt['db_persist'] = 'Conserve une connexion permanente avec la base de donn�es afin d\'accro�tre les performances du forum. Si vous �tes sur un serveur mutualis� (Lycos, Free / Online, OVH, Celeonet, Lewis Media...), l\'activation de cette fonction peut occasionner des probl�mes avec votre h�bergeur, car cela consomme beaucoup de ressources syst�me.';
$helptxt['ssi_db_user'] = 'R�glage optionnel pour utiliser un nom d\'utilisateur et un mot de passe de base de donn�es diff�rents quand vous utilisez SSI.php.';

$helptxt['queryless_urls'] = 'Ceci modifie un peu la structure des URLs afin que les moteurs de recherche tels Google et Yahoo! les r�f�rencent mieux. Les URLs ressembleront � index.php/topic,1.0.html.<br /><br />Cette option ' . (isset($_SERVER['SERVER_SOFTWARE']) && (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false || strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false) ? '<span style="color: green">est support�e</span>' : '<span style="color: red">n\'est pas support�e</span>') . ' par votre serveur.';
$helptxt['countChildPosts'] = 'S�lectionner cette option signifie que les messages et les sujets dans une section parente seront compt�s dans leur totalit� sur la page d\'index.<br /><br />Cela rendra les choses notablement plus lentes, mais signifiera qu\'une parente avec aucun message ne montrera pas \'0\'.';
$helptxt['allow_ignore_boards'] = 'Cocher cette option permettra aux utilisateurs de s�lectionner les sections qu\'ils veulent ignorer.';
$helptxt['deny_boards_access'] = 'Checking this setting will allow you to deny access to certain boards based on membergroup access';

$helptxt['who_enabled'] = 'Cette option vous permet d\'activer ou non la possibilit� de voir qui est en ligne sur le forum et ce qu\'il y fait.';

$helptxt['recycle_enable'] = '&quot;Recycle&quot; les sujets et messages supprim�s vers une section sp�cifique, souvent une section cach� aux utilisateurs normaux.';

$helptxt['enableReportPM'] = 'Cette option permet aux utilisateurs de rapporter des messages personnels qu\'ils ont re�us � l\'�quipe d\'administration. Ceci peut �tre pratique pour aider � traquer les abus effectu�s � l\'aide du syst�me de messagerie personnelle.';
$helptxt['max_pm_recipients'] = 'Cette option vous permet de limiter la quantit� maximale de messages priv�s envoy� par un membre du forum. Cette option permet de lutter contre le pollupostage (&quot;spam&quot;) du syst�me de MP. Notez que les utilisateurs ayant la permission d\'envoyer des bulletins d\'informations ne sont pas concern�s par cette restriction. R�glez-la � 0 pour d�sactiver la fonction.';
$helptxt['pm_posts_verification'] = 'Cette option forcera les utilisateurs � entrer un code affich� sur une image de v�rification � chaque fois qu\'ils envoient un message personnel. Seuls les utilisateurs avec un compteur de messages en dessous de l\'ensemble de nombres auront besoin de saisir le code - Cela devrait aider � lutter contre les robots spammeurs.';
$helptxt['pm_posts_per_hour'] = 'Cette option limitera le nombre de messages personnels qui pourront �tre envoy�s par un utilisateur en une heure de temps. Cela n\'affecte pas les admins ou mod�rateurs.';

$helptxt['default_personal_text'] = 'Choisit le texte personnel qu\'un nouvel utilisateur aura par d�faut.';

$helptxt['registration_method'] = 'Cette fonction d�termine quelle m�thode d\'inscription doit �tre adopt�e pour les gens d�sirant rejoindre votre forum. Vous pouvez s�lectionner un de ces choix&nbsp;:<br /><br />
	<ul class="normallist">
		<li>
			<strong>Inscription d�sactiv�e</strong><br />
				D�sactive les proc�dures d\'inscription, ce qui signifie que personne ne peut plus s\'inscrire sur votre forum.<br />
		</li><li>
			<strong>Inscription imm�diate</strong><br />
				Les nouveaux membres peuvent se connecter et poster sur votre forum imm�diatement apr�s la proc�dure d\'inscription.<br />
		</li><li>
			<strong>Activation par e-mail</strong><br />
				Lorsque cette option est s�lectionn�e, tous les membres qui s\'inscrivent au forum recevront un e-mail contenant un lien pour activer leur compte. Ils ne pourront utiliser leur compte que lorsque celui-ci aura �t� activ�.<br />
		</li><li>
			<strong>Approbation par un Admin</strong><br />
				Lorsque cette option est s�lectionn�e, l\'inscription de tous les nouveaux utilisateurs de votre forum devra d\'abord �tre approuv�e par les administrateurs pour �tre ensuite effective et leur permettre ainsi de rejoindre votre communaut�.
		</li>
	</ul>';

$helptxt['send_validation_onChange'] = 'Lorsque cette option est coch�e, tous les membres qui modifient leur adresse e-mail dans leur profil devront r�activer leur compte gr�ce � un e-mail envoy� � leur nouvelle adresse.';
$helptxt['approveAccountDeletion'] = 'When this setting is checked, any user request to delete his own account has to be approved by an administrator';

$helptxt['send_welcomeEmail'] = 'Lorsque cette option est activ�e, tous les nouveaux membres recevront un e-mail leur souhaitant la bienvenue sur votre communaut�.';
$helptxt['password_strength'] = 'Ce r�glage d�termine le niveau de s�curit� requis pour les mots de passe s�lectionn�s par les membres de votre forum. Plus ce niveau est &quot;�lev�&quot;, plus il devrait �tre difficile de d�couvrir le mot de passe et de pirater leurs comptes.
	Les niveaux possibles sont&nbsp;:
	<ul>
		<li><strong>Bas&nbsp;:</strong> Le mot de passe doit �tre compos� d\'au moins quatre caract�res.</li>
		<li><strong>Moyen&nbsp;:</strong> Le mot de passe doit �tre form� d\'au moins huit caract�res, et ne peut contenir des parties de l\'identifiant ou de l\'adresse e-mail.</li>
		<li><strong>�lev�&nbsp;:</strong> Comme pour le niveau pr�c�dent, et le mot de passe doit aussi contenir des lettres majuscules et minuscules et au moins un chiffre.</li>
	</ul>';
$helptxt['enable_password_conversion'] = 'By enabling this setting, SMF will attempt to detect passwords stored in other formats and convert them to the format SMF uses. Typically this is used for forums converted to SMF, but may have other uses as well. Disabling this prevents a user from logging in using their password after a conversion and they would need to reset their password.';

$helptxt['coppaAge'] = 'La valeur sp�cifi�e dans ce champ d�termine l\'�ge minimum que doit avoir un membre pour avoir un acc� imm�diat aux sections.
	� l\'inscription, il sera demand� aux membres de confirmer s\'ils ont plus que cet �ge. Si ce n\'est pas le cas, leur inscription sera rejet�e ou suspendue en attente d\'une autorisation parentale - en fonction des restrictions que vous sp�cifiez.
	Si la valeur est 0 pour cette option toutes les restrictions d\'�ge pour les prochaines inscriptions seront ignor�es.';
$helptxt['coppaType'] = 'Si la restriction d\'�ge est active, ce param�tre d�finira ce qui se produit lorsqu\'un membre n\'ayant pas l\'�ge minimum requis tente de s\'inscrire sur votre forum. Il existe deux choix possibles&nbsp;:
	<ul class="normallist">
		<li>
			<strong>Rejeter son inscription&nbsp;:</strong><br />
				N\'importe quel nouvel adh�rent n\'ayant pas l\'�ge requis verra son inscription rejet�e imm�diatement.<br />
		</li><li>
			<strong>N�cessiter l\'approbation d\'un parent/tuteur l�gal</strong><br />
				N\'importe quel nouvel adh�rent n\'ayant pas l\'�ge requis et qui tente de s\'inscrire sur votre forum verra son compte marqu� en attente d\'approbation et il lui sera remis un formulaire � faire remplir par ses parents ou tuteurs avant de pouvoir devenir membre de votre forum.
				Il lui sera aussi pr�sent� les informations de contact du forum enregistr�es sur la page des param�tres, afin que le formulaire d\'approbation parentale soit envoy�e � l\'administrateur par la poste ou par t�l�fax.
		</li>
	</ul>';
$helptxt['coppaPost'] = 'Les champs de contact doivent �tre complet�es afin que les formulaires d\'autorisation parentale pour les membres n\'ayant pas l\'�ge requis soient envoy�s � l\'administrateur. Ces d�tails seront affich�s � tous les mineurs et il leur est n�cessaire d\'obtenir une approbation parentale. Une adresse postale ou un num�ro de t�l�fax est le minimum requis.';

$helptxt['allow_hideOnline'] = 'En activant cette option, les membres peuvent cacher leur statut de connexion au forum aux autres visiteurs (sauf aux administrateurs). Si elle est d�sactiv�e, seuls les utilisateurs qui peuvent mod�rer le forum entier peuvent cacher leur pr�sence. Notez bien que d�sactiver cette option ne changera rien dans le statut des membres connect�s en ce moment - cela ne leur emp�chera la manœuvre que pour les futures connexions.';
$helptxt['meta_keywords'] = 'Ces mots-cl�s sont plac�s dans les ent�tes de chaque page pour indiquer aux robots le type de contenu de votre site (mais cette technique n\'est plus tr�s efficace de nos jours, NDT). S�parez les mots par des virgules, et n\'utilisez pas de HTML.';

$helptxt['latest_themes'] = 'Cette zone vous montre quelques-uns des derniers th�mes et les plus populaires en provenance de <a href="http://www.simplemachines.org/" target="_blank">www.simplemachines.org</a>. Cela peut n�anmoins ne pas s\'afficher correctement si votre ordinateur a du mal � se connecter � <a href="http://www.simplemachines.org/" target="_blank">www.simplemachines.org</a>.';

$helptxt['secret_why_blank'] = 'Pour votre s�curit�, la r�ponse � votre question (de m�me que votre mot de passe) est encrypt�e de telle mani�re que SMF ne peut que v�rifier si vous entrez la bonne valeur, ainsi il ne peut jamais vous r�v�ler (ni � vous ni � personne d\'autre, heureusement&nbsp;!) quelle est votre r�ponse ou votre mot de passe.';
$helptxt['moderator_why_missing'] = 'Puisque la mod�ration est d�finie ind�pendamment pour chaque section, vous devrez assigner les membres en tant que mod�rateurs � partir de <a href="javascript:window.open(\'%1$s?action=manageboards\'); self.close();">l\'interface de gestion des sections</a>.';

$helptxt['permissions'] = 'Les permissions permettent de d�finir les droits accord�s (ou non) aux membres pour effectuer une action particuli�re. Ces droits sont d�finis sur la base des groupes de membres <br /><br />Vous pouvez modifier ces droits sur plusieurs sections en m�me temps en utilisant les cases � cocher, ou modifier les permissions d\'un groupe particulier en cliquant sur le lien \'Modifier\'';
$helptxt['permissions_board'] = 'Si \'Global\' est s�lectionn�, cela signifie que cette section ne poss�dera aucune permission particuli�re, et aura celles g�n�rales de votre forum. \'Local\' signifie qu\'elle aura ses propres permissions - ind�pendamment des permissions globales. Ceci vous permet d\'avoir des sections avec plus ou moins de permissions que d\'autres, sans navoir � r�gler toutes les permissions pour chaque section.';
$helptxt['permissions_quickgroups'] = 'Ceci vous permet d\'utiliser les r�glages de permissions par &quot;d�faut&quot; - standard signifie &quot;rien de sp�cial&quot;, restreint signifie &quot;comme un invit�&quot;, mod�rateur signifie &quot;les m�mes droits qu\'un mod�rateur&quot;, et enfin maintenance signifie &quot;des permissions tr�s proches de celles d\'un administrateur&quot;.';
$helptxt['permissions_deny'] = 'Interdire des permissions peut �tre utile quand vous voulez enlever des permissions � certains membres. Vous pouvez ajouter un groupe de membres avec une permission \'interdite\' pour les membres auxquels vous voulez interdire une permission.<br /><br />� utiliser avec pr�caution, une permission interdite restera interdite peu importe dans quels autres groupes de membres le membre fait partie.';
$helptxt['permissions_postgroups'] = 'Activer les permissions pour les groupes posteurs vous permettra d\'attribuer des permissions aux membres ayant post� un certain nombre de messages. Les permissions du groupe posteur sont <em>ajout�es</em> aux permissions des membres inscrits.';
$helptxt['membergroup_guests'] = 'Le groupe de membres Invit�s contient tous les utilisateurs qui ne sont pas connect�s � un compte membre sur votre forum.';
$helptxt['membergroup_regular_members'] = 'Les membres inscrits correspondent � tous les utilisateurs ayant un compte membre sur votre forum, mais � qui aucun groupe permanent n\'a �t� assign�.';
$helptxt['membergroup_administrator'] = 'L\'administrateur peut, par d�finition, faire tout ce qu\'il veut et voir toutes les sections. Il n\'y a aucun r�glage de permissions pour les administrateurs.';
$helptxt['membergroup_moderator'] = 'Le groupe Mod�rateur est un groupe sp�cial. Les permissions et r�glages pour ce groupe s\'appliquent aux mod�rateurs mais uniquement <em>dans la (ou les) section(s) qu\'ils mod�rent</em>. Au dehors de ces sections, ils sont consid�r�s comme n\'importe quel autre membre r�gulier.';
$helptxt['membergroups'] = 'Dans SMF il y a deux types de groupes auxquels vos membres peuvent appartenir. Ce sont&nbsp;:
	<ul class="normallist">
		<li><strong>Groupes permanents&nbsp;:</strong> Un groupe permanent est un groupe dans lequel un membre n\'est pas assign� automatiquement. Pour assigner un membre dans un groupe permanent, allez simplement dans son profil et cliquez sur &quot;Param�tres relatifs au compte&quot;. Ici vous pouvez param�trer les diff�rents groupes permanents auxquels les membres peuvent appartenir.</li>
		<li><strong>Groupes posteurs&nbsp;:</strong> Au contraire des groupes permanents, un membre ne peut �tre manuellement assign� � un groupe posteur, bas� sur le nombre de message. Les membres sont plut�t assign�s automatiquement � un groupe posteur lorsqu\'ils ont atteint le nombre minimum de messages requis pour faire partie de ce groupe.</li>
	</ul>';

$helptxt['calendar_how_edit'] = 'Vous pouvez modifier ces �v�nements en cliquant sur l\'ast�risque (*) rouge accompagnant leur nom.';

$helptxt['maintenance_backup'] = 'Cette section vous permettra de faire une copie de sauvegarde des messages, des r�glages, des membres et autres informations utiles de votre forum dans un gros fichier.<br /><br />Il est recommand� d\'effectuer cette op�ration souvent, par exemple hebdomadairement, pour plus de s�curit� et de protection.';
$helptxt['maintenance_rot'] = 'Ceci vous permet de supprimer <strong>compl�tement</strong> et <strong>irr�vocablement</strong> les vieux sujets. Vous devriez effectuer une copie de sauvegarde de votre base de donn�es avant de proc�der � cette action, au cas o� vous enleveriez quelque chose que vous ne vouliez pas supprimer.<br /><br />� utiliser avec pr�caution.';
$helptxt['maintenance_members'] = 'Ceci vous permet d\'effacer <strong>compl�tement</strong> et <strong>irr�vocablement</strong> des comptes de membres de votre forum. Vous devriez <strong>absolument</strong> faire une sauvegarde avant, juste au cas o� vous effaceriez quelque chose que vous ne vouliez pas effacer.<br /><br />Utilisez cette option avec pr�caution.';

$helptxt['avatar_server_stored'] = 'Ceci permet � vos membres de choisir leur avatar parmi ceux pr�alablement install�s sur votre serveur. Ils sont, g�n�ralement, au m�me endroit que votre forum SMF, dans le dossier des avatars.<br />Un conseil, si vous cr�ez des r�pertoires dans ce dossier, vous pouvez faire des &quot;cat�gories&quot; d\'avatars.';
$helptxt['avatar_external'] = 'Ceci permet � vos membres d\'ins�rer l\'adresse URL de leur propre avatar. L\'inconv�nient est que, dans certains cas, ils pourraient utiliser des avatars beaucoup trop gros ou des images que vous ne voulez pas voir sur votre forum.';
$helptxt['avatar_download_external'] = 'Ceci permet au forum de t�l�charger l\'avatar choisi par l\'utilisateur via l\'URL donn�e par celui-ci. Si l\'op�ration r�ussit, l\'avatar sera trait� comme un avatar transf�r�.';
$helptxt['avatar_action_too_large'] = 'This setting therefore lets you reject images (from other sites) that are too big, or tells the user\'s browser to resize them, or to download them to your server.<br><br>If users put in very large images as their avatars and resize in the browser, it could cause very slow loading for your users - it does not actually resize the file, it just displays it smaller. So a digital photo, for example, would still be loaded in full and then resized only when displayed - so for users this could get quite slow and use a lot of bandwidth.<br><br>On the other hand, downloading them means using your bandwidth and server space, but you also ensure that images are smaller, so it should be faster for users. (Note: downloading and resizing requires either the GD library, or ImageMagick using either the Imagick or MagickWand extensions)';
$helptxt['avatar_upload'] = 'Cette option est pratiquement la m�me chose que &quot;Permettre aux membres de s�lectionner un avatar externe&quot;, sauf que vous avez un meilleur contr�le sur les avatars, plus de facilit� pour les redimensionner, et vos membres n\'ont pas � avoir un endroit o� mettre leurs avatars.<br /><br />Mais l\'inconv�nient est que cela peut prendre beaucoup d\'espace sur votre serveur.';
$helptxt['avatar_download_png'] = 'Les images au format PNG sont plus lourdes, mais offrent un rendu de meilleure qualit�. Si la case est d�coch�e, le format JPEG sera utilis� � la place - ce qui donne des fichiers moins lourds, mais de moindre qualit�, surtout les dessins, lesquels peuvent devenir assez flous.';

$helptxt['disableHostnameLookup'] = 'Ceci d�sactive la recherche du nom de l\'h�te, fonction parfois lente sur certains serveurs. Notez que sa d�sactivation rend le syst�me de bannissement moins efficace.';

$helptxt['search_weight_frequency'] = 'Des facteurs de pertinence sont utilis�s pour d�terminer l\'int�r�t des r�sultats de recherche. Changez ces facteurs pour les faire correspondre � des valeurs int�ressantes pour votre forum. Par exemple, un forum d\'actualit�s aura un facteur d\'anciennet� du message relativement bas. Toutes les valeurs sont en relation avec les autres et doivent �tre des valeurs positives.<br /><br />Ce facteur compte le nombre de messages correspondants et divise ce r�sultat par le nombre de messages dans un sujet.';
$helptxt['search_weight_age'] = 'Des facteurs de pertinence sont utilis�s pour d�terminer l\'int�r�t des r�sultats de recherche. Changez ces facteurs pour les faire correspondre � des valeurs int�ressantes pour votre forum. Par exemple, un forum d\'actualit�s aura un relativement grand facteur de \'�ge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent �tre des valeurs positives.<br /><br />Ce facteur v�rifie l\'�ge des derniers messages d\'un sujet. Plus r�cent est le message, le plus haut dans la liste il est positionn�.';
$helptxt['search_weight_length'] = 'Des facteurs de pertinence sont utilis�s pour d�terminer l\'int�r�t des r�sultats de recherche. Changez ces facteurs pour les faire correspondre � des valeurs int�ressantes pour votre forum. Par exemple, un forum d\'actualit�s aura un relativement grand facteur de \'�ge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent �tre des valeurs positives.<br /><br />Ce facteur est bas� sur la longueur du sujet. Plus le sujet contient de r�ponses, plus le pointage est �lev�.';
$helptxt['search_weight_subject'] = 'Des facteurs de pertinence sont utilis�s pour d�terminer l\'int�r�t des r�sultats de recherche. Changez ces facteurs pour les faire correspondre � des valeurs int�ressantes pour votre forum. Par exemple, un forum d\'actualit�s aura un relativement grand facteur de \'�ge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent �tre des valeurs positives.<br /><br />Ce facteur v�rifie si le terme recherch� peut �tre trouv� ou non dans le titre du sujet.';
$helptxt['search_weight_first_message'] = 'Des facteurs de pertinence sont utilis�s pour d�terminer l\'int�r�t des r�sultats de recherche. Changez ces facteurs pour les faire correspondre � des valeurs int�ressantes pour votre forum. Par exemple, un forum d\'actualit�s aura un relativement grand facteur de \'�ge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent �tre des valeurs positives.<br /><br />Ce facteur v�rifie si le terme recherch� peut �tre trouv� ou non dans le premier message du sujet.';
$helptxt['search_weight_sticky'] = 'Des facteurs de pertinence sont utilis�s pour d�terminer l\'int�r�t des r�sultats de recherche. Changez ces facteurs pour les faire correspondre � des valeurs int�ressantes pour votre forum. Par exemple, un forum d\'actualit�s aura un relativement grand facteur de \'�ge du dernier message\'. Toutes les valeurs sont en relation avec les autres et doivent �tre des valeurs positives.<br /><br />Ce facteur v�rifie si un sujet est populaire et augmente le score de pertinence si il l\'est.';
$helptxt['search'] = 'Ajustez ici tous les r�glages de la fonction recherche.';
$helptxt['search_why_use_index'] = 'Un index de recherche peut consid�rablement am�liorer l\'ex�cution des recherches sur votre forum. En particulier lorsque le nombre de messages sur un forum est de plus en plus grand, la recherche sans index peut prendre un bon moment et augmenter la pression sur votre base de donn�es. Si votre forum a plus de 50.000 messages, vous devriez penser � cr�er un index de recherche pour assurer un fonctionnement optimal de votre forum.<br /><br />� noter qu\'un index de recherche peut prendre un certain espace... Un index � texte int�gral est un index g�r� par MySQL. C\'est relativement compact (approximativement la m�me taille que la table message), mais beaucoup de mots ne sont pas index�s et il se peut que quelques recherches s\'av�rent tr�s lentes. L\'index personnalis� est souvent plus grand (selon votre configuration, cela peut �tre plus de 3 fois la taille de la table des messages) mais la performance est meilleure qu\'en texte int�gral et relativement stable.';

$helptxt['see_admin_ip'] = 'Les adresses IP sont affich�es aux administrateurs et aux mod�rateurs afin de faciliter la mod�ration et de rendre plus efficace la surveillance des personnes se conduisant mal sur ce forum.  Rappelez-vous que les adresses IP ne peuvent pas toujours �tre identifi�es, et que la plupart des adresses changent p�riodiquement.<br /><br />Les membres sont aussi autoris�s � voir leur adresse IP, mais pas celle des autres.';
$helptxt['see_member_ip'] = 'Votre adresse IP est affich�e seulement � vous et aux mod�rateurs.  Rappelez-vous que cette information ne permet pas de vous identifier en tant qu\'individu, et que la plupart des adresses changent p�riodiquement.<br /><br />Vous ne pouvez pas voir l\'adresse IP des autres, et les autres ne peuvent pas voir la v�tre.';
$helptxt['whytwoip'] = 'SMF utilise plusieurs m�thodes pour d�tecter les adresses IP d\'un utilisateur. Habituellement ces deux m�thodes donnent la m�me adresse mais dans certains cas plus d\'une adresse peut �tre d�tect�e. Dans ce cas SMF conserve les adresses, et les utilise par exemple lors des contr�les de bannissement. Vous pouvez cliquer sur chaque adresse pour traquer cette IP et la bannir si n�cessaire.';

$helptxt['ban_cannot_post'] = 'La restriction \'Ne peut pas poster\' a pour cons�quence que le forum n\'est accessible qu\'en lecture seule pour l\'utilisateur banni. L\'utilisateur ne peut pas cr�er de nouveaux sujets ou r�pondre � ceux existants, envoyer des messages personnels ou voter dans les sondages. L\'utilisateur banni peut toutefois encore lire ses messages personnels et les sujets.<br /><br />Un message d\'avertissement est affich� aux utilisateurs qui sont bannis avec cette restriction.';

$helptxt['posts_and_topics'] = '
	<ul>
		<li>
			<strong>Param�tres des messages</strong><br />
			Modifie les param�tres relatifs au postage des messages et la fa�on dont ceux-ci sont affich�s. Vous pouvez aussi activer le correcteur orthographique ici.
		</li><li>
			<strong>Code d\'affichage</strong><br />
			Active le code montrant les messages dans un rendu correct. Ajuste aussi quels codes sont permis et ceux qui sont d�sactiv�s.
		</li><li>
			<strong>Mots censur�s</strong>
			Afin de conserver un registre de langage convenable sur votre forum, vous pouvez censurer certains mots. Cette fonction vous permet de convertir des mots interdits en d\'autres mots innocents. D\'o� une possibilit� d�riv� de remplacement de termes choisis.
		</li><li>
			<strong>Param�tres des sujets</strong>
			Modifie les param�tres relatifs aux sujets&nbsp;: le nombre de sujets par page, l\'activation ou non des sujets �pingl�s, le nombre minimal de messages par sujet pour qu\'il soit not� comme populaire, etc.
		</li>
	</ul>';

$helptxt['spider_mode'] = 'Sets the logging level.<br>
Standard - Logs minimal spider activity.<br>
Moderate - Provides more accurate statistics.<br>
Agressive - As for &quot;Moderate&quot; but logs data about each page visited.';

$helptxt['spider_group'] = 'En s�lectionnant un groupe restrictif, lorsqu\'un invit� est identifi� comme moteur de recherche, certaines permissions lui seront ni�es (autrement dit &quot;Interdites&quot;), par rapport aux permissions normales d\'un invit�. Vous pouvez utiliser ceci pour donner moins d\'acc�s � un moteur de recherche par rapport � un invit� normal. Vous pouvez par exemple vouloir cr�er un nouveau groupe appel� &quot;Robots&quot; et le s�lectionner ici. Vous pourriez donc interdire � ce groupe la permission de voir les profils pour emp�cher l\'indexation par les robots des profils de vos membres.<br />Note: La d�tection des robots n\'est pas parfaite et peut �tre simul�e par les utilisateurs, donc cette fonctionnalit� n\'est pas garantie pour restreindre le contenu aux seuls moteurs de recherche que vous avez ajout�s.';
$helptxt['show_spider_online'] = 'Ce param�tre vous permet de choisir si les robots seront montr�s ou pas sur la liste des utilisateurs en ligne et la page &quot;Qui est en ligne&quot;. Les options&nbsp;:
	<ul class="normallist">
		<li>
			<strong>Pas du tout</strong><br />
			Les robots seront montr�s en tant qu\'invit�s aux autres utilisateurs.
		</li><li>
			<strong>Montrer le nombre de robots</strong><br />
			L\'accueil du forum indiquera le nombre de robots visitant actuellement le forum.
		</li><li>
			<strong>Montrer le nom des robots</strong><br />
			Les noms des robots seront montr�s, les utilisateurs sauront ainsi combien de chaque type de robot visite le forum - valable � la fois pour l\'accueil du forum et la page Qui est en ligne.
		</li><li>
			<strong>Montrer le nom des robots, mais juste � l\'administrateur</strong><br />
			Comme ci-dessus, mais seuls les Administrateurs pourront voir le statut des robots - pour les autres utilisateurs, les robots seront affich�s comme �tant des invit�s.
		</li>
	</ul>';

$helptxt['birthday_email'] = 'Choisissez le mod�le du message d\'anniversaire par e-mail � utiliser. Une pr�visualisation sera affich�e dans le sujet de l\'e-mail et les champs du corps de l\'e-mail.<br /><strong>Attention</strong>, r�gler cette option n\'active pas automatiquement les e-mails d\'anniversaire. Pour activer les e-mails d\'anniversaire, utilisez la page <a href="%1$s?action=admin;area=scheduledtasks;%3$s=%2$s" target="_blank" class="new_win">T�ches Programm�es</a> et activez la t�che E-mail d\'anniversaire.';
$helptxt['pm_bcc'] = 'Lorsque vous envoyez un message personnel vous pouvez choisir d\'ajouter comme destinataire un BCC (soit &quot;Blind Carbon Copy&quot;). L\'existence et l\'identit� des destinataires BCC seront cach�es aux autres destinataires du message.';

$helptxt['move_topics_maintenance'] = 'Ceci vous permet de d�placer tous les sujets d\'une section vers une autre.';
$helptxt['maintain_reattribute_posts'] = 'Vous pouvez utiliser cette fonction pour attribuer des messsages d\'invit�s de votre forum � un membre inscrit. Ceci est tr�s utile par exemple si un utilisateur a effac� son compte, a chang� d\'id�e et veut r�cup�rer les anciens messages associ� � son compte.';
$helptxt['chmod_flags'] = 'Vous pouvez choisir manuellement les permissions que vous voulez appliquer aux fichiers s�lectionn�s. Pour ce faire, entrez la valeur du chmod en valeur num�rique (en base 8). Note - ces indicateurs n\'auront aucun effet sur les syst�mes d\'exploitation Microsoft Windows.';

$helptxt['postmod'] = 'Cette section permet aux membres de l\'�quipe de mod�ration disposant des permissions n�cessaires, d\'approuver les messages et sujets avant leur apparition en ligne.';

$helptxt['field_show_enclosed'] = 'Entoure le texte entr� par l\'utilisateur par du texte ou du HTML, vous permettant d\'ajouter des fournisseurs de messagerie instantan�e suppl�mentaires, des images ou int�grations multim�dia, etc. Par exemple&nbsp;:<br /><br />
		&lt;a href="http://website.com/{INPUT}"&gt;&lt;img src="{DEFAULT_IMAGES_URL}/icon.gif" alt="{INPUT}" /&gt;&lt;/a&gt;<br /><br />
		� noter que vous pouvez utiliser les variables suivantes&nbsp;:<br />
		<ul class="normallist">
			<li>{INPUT} - Le texte entr� par l\'utilisateur.</li>
			<li>{SCRIPTURL} - Adresse web (URL) du forum.</li>
			<li>{IMAGES_URL} - URL du dossier images dans le th�me actuel de l\'utilisateur.</li>
			<li>{DEFAULT_IMAGES_URL} - URL du dossier images dans le th�me par d�faut.</li>
		</ul>';

$helptxt['custom_mask'] = 'Le masque d\'entr�e est important pour la s�curit� de votre forum. Valider le texte entr� par un utilisateur peut vous permettre d\'�viter que ses donn�es ne soient pas utilis�es de mani�re inattendue. Vous pouvez utiliser des expressions r�guli�res pour vous y aider.<br /><br />
	<div class="smalltext" style="margin: 0 2em">
		&quot;[A-Za-z]+&quot; - Correspond � toutes les lettres de l\'alphabet, minuscules et majuscules.<br />
		&quot;[0-9]+&quot; - Correspond � tous les chiffres.<br />
		&quot;[A-Za-z0-9]{7}&quot; - Correspond � une suite de sept chiffres et/ou lettres de l\'alphabet, minuscules ou majuscules.<br />
		&quot;[^0-9]?&quot; - Emp�che la pr�sence � cet endroit d\'un chiffre.<br />
		&quot;^([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$&quot; - N\'autoriser que 3 ou 6 caract�res hexad�cimaux.<br />
	</div><br /><br />
	De plus, vous pouvez utiliser les m�ta-caract�res sp�ciaux ?+*^$ et {xx}.
	<div class="smalltext" style="margin: 0 2em">
		? - Rien, ou une occurrence de l\'expression qui pr�c�de.<br />
		+ - Au moins une occurrence de l\'expression qui pr�c�de.<br />
		* - Rien, ou au moins une occurrence de l\'expression qui pr�c�de.<br />
		{xx} - xx occurrences de l\'expression qui pr�c�de.<br />
		{xx,} - xx occurrences, ou plus, de l\'expression qui pr�c�de.<br />
		{,xx} - Jusqu\'� xx occurrences de l\'expression qui pr�c�de.<br />
		{xx,yy} - Entre xx et yy occurrences de l\'expression qui pr�c�de.<br />
		$ - D�but de cha�ne.<br />
		^ - Fin de cha�ne.<br />
		\\ - �chappe le caract�re suivant.<br />
	</div><br /><br />
	Vous pourrez trouver plus d\'informations et d\'exemples sur le Net.';

$helptxt['topic_move_any'] = 'If checked, users will be allowed to move topics to any board they can see. Otherwise, they will only be able to move them to boards where they can post new topics.';

$helptxt['alert_pm_new'] = 'Notifications of new personal messages do not appear in the Alerts pane, but appear in the "My Messages" list instead.';
$helptxt['alert_event_new'] = 'This will send out an alert or email as requested if there is a new calendar event added. However, if that event is posted and a topic is added, you will not get an alert for the event if you\'re already following that board - the alert from following the board would cover this.';

$helptxt['force_ssl'] = '<b>Test SSL and HTTPS on your server properly before enabling this, it may cause your forum to become inaccessible.</b> Enable maintenance mode if you are unable to access the forum after enabling this';
$helptxt['image_proxy_enabled'] = 'Required for embedding external images when in full SSL';
$helptxt['image_proxy_secret'] = 'Keep this a secret, protects your forum from hotlinking images. Change it in order to render current hotlinked images useless';
$helptxt['image_proxy_maxsize'] = 'Maximum image size that the SSL image proxy will cache: bigger images will be not be cached. Cached images are stored in your SMF cache folder, so make sure you have enough free space.';

?>