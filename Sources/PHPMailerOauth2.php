<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Sends mail, like mail() but over SMTP, with OAuth2 authentication, using PHPMailer.
 *
 * @internal
 *
 * @param array $mail_to_array Array of strings (email addresses)
 * @param string $subject Email subject
 * @param string $message Email message
 * @return boolean Whether it sent or not.
 */
function smtp_mail($mail_to_array, $subject, $message, $headers)
{
	global $modSettings, $webmaster_email, $mbname;

	$smtp_host     = trim($modSettings['smtp_host']);
	$smtp_port     = (int)trim($modSettings['smtp_port']);
	$smtp_username = trim($modSettings['smtp_username']);
	$smtp_password = base64_decode(trim($modSettings['smtp_password']));

	// modified from: https://github.com/PHPMailer/PHPMailer/tree/master?tab=readme-ov-file#a-simple-example
	require __DIR__ . '/../vendor/autoload.php';

	$mail = new PHPMailer();

	try {
		$mail->isSMTP();

		//SMTP::DEBUG_OFF = off (for production use)
		//SMTP::DEBUG_CLIENT = client messages
		//SMTP::DEBUG_SERVER = client and server messages
		//$mail->SMTPDebug = SMTP::DEBUG_SERVER;

		$mail->Host = $smtp_host;
		$mail->Port = $smtp_port;
		$mail->SMTPAuth = true;
		$mail->Username = $smtp_username;
		$mail->Password = $smtp_password;
		$mail->setFrom($webmaster_email, $mbname);

		$mail_to_array = array_values($mail_to_array);
		foreach ($mail_to_array as $i => $mail_to)
		{
			$mail->addAddress($mail_to);
		}

		$mail->Subject = $subject;
		$mail->AltBody = $message;
		$mail->send();
	} catch (Exception $e) {
		log_error('PHP Mailer error: ' . $mail->ErrorInfo);
		return false;
	}
	return true;
}

?>