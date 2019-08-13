<?php


namespace Geschkenet;

use \PHPMailer\PHPMailer\PHPMailer;
use \PHPMailer\PHPMailer\Exception;
use Monolog\Logger;


/**
 * Send feedback mail with AWS SES (Simple Email Service) using SMTP
 */
class SendFeedback
{
    private $logger;


    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Send mail to website owner
     * 
     * Most of the code is taken from a PHPMailer example.
     */
    public function send($formEmail, $formName, $formMessage)
    {
        $text = "Feedback von $formName <$formEmail>\n\n" . $formMessage; 
       
        // Get parameters from environment defined in serverless.yml
        $fromMail = getenv('ENV_FROM_MAIL'); // important!
        $replyToMail = getenv('ENV_REPLY_TO_MAIL');
        $fromName = getenv('ENV_FROM_NAME');
        $subject = getenv('ENV_SUBJECT');
        $toMail = getenv('ENV_TO_MAIL');
        $toName = getenv('ENV_TO_NAME');
        $replyToName = getenv('ENV_REPLY_TO_NAME');
        $smtpHost = getenv('ENV_SMTP_HOST');

        $mail = new PHPMailer(true);                    // Passing `true` enables exceptions
        try {
            //Server settings
            //$mail->SMTPDebug = 2;                     // Enable verbose debug output. Warning! The output is displayed directly on the stdout!
            $mail->isSMTP();                            // Set mailer to use SMTP
            $mail->Host = $smtpHost;                    // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                     // Enable SMTP authentication

            // Get SMTP user and password. These values are stored in AWS Systems Manager -> Parameter Store and passed on environment section in the serverless.yml file
            $mail->Username = getenv('ENV_SMTP_USER');  // SMTP username
            $mail->Password = getenv('ENV_SMTP_PASSWORD'); // SMTP password
            $mail->SMTPSecure = 'tls';                  // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;                          // TCP port to connect to
            //$mail->Port = 25;
            //$mail->SMTPSecure = false;
            //$mail->SMTPAutoTLS = false;
            // Tells PHPMailer to use SMTP authentication
            $mail->SMTPAuth = true;

            //Recipients
            $mail->setFrom($fromMail, $fromName);
            $mail->addAddress($toMail, $toName);        // Add a recipient
           
            $mail->addReplyTo($replyToMail, $replyToName);
            //$mail->addCC('cc@example.com');
            //$mail->addBCC('bcc@example.com');

            //Attachments
            //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
            //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

            //Content
            $mail->isHTML(false);                       // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $text;
            //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();
            $this->logger->info('Mail has been sent successfully.');
            return true;
        } catch (Exception $e) {
            $this->logger->info('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo);
            return false; 
        }
    }
}