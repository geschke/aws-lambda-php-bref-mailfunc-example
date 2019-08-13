<?php

namespace Geschkenet;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Monolog\Logger;

use \Geschkenet\SendFeedback;

/**
 * Feedback handle class with helper methods to validate and sanitize formular values
 */
class FeedbackAction
{

    public $response;

    private $logger;


    public function __construct(Response $response, Logger $logger)
    {
        $this->response = $response;
        $this->logger = $logger;
    }

    public function sanitizeEmail($text)
    {
        $result = filter_var($text, FILTER_VALIDATE_EMAIL);
        if ($result === false) {
            return '';
        }
        $result = filter_var($result,FILTER_SANITIZE_EMAIL);
        if ($result === false) {
            return '';
        }
        return $result;
    }

    public function sanitizeText($text)
    {
        $result = filter_var($text,FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_BACKTICK | FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH | FILTER_FLAG_ENCODE_AMP);
        if ($result === false) {
            return '';
        }
        return $result;

    }

    public function sanitizeName($text)
    {
        return $this->sanitizeText($text);
    }

    public function sanitizeMessage($text)
    {
        return $this->sanitizeText($text);
    }

    /**
     * Helper method to handle ReCaptcha validation request.
     */
    public function validateRecaptcha($gRecaptchaResponse, $remoteIp)
    {
        $this->logger->info(__METHOD__);

        // Get ReCaptcha secret and host name from environment variables defined in serverless.yml 
        $secret = getenv('ENV_RECAPTCHA_SECRET');
        $hostname = getenv('ENV_RECAPTCHA_HOST'); // current http server
        
        $recaptcha = new \ReCaptcha\ReCaptcha($secret);
        $resp = $recaptcha->setExpectedHostname($hostname)->verify($gRecaptchaResponse, $remoteIp);
       
        if ($resp->isSuccess()) {
            return true;

        } else {
            $errors = $resp->getErrorCodes();
            $this->logger->warning(json_encode($errors));
            return $errors;
        }
    }

    /**
     * Main handler: Get formular values, check them, validate ReCaptcha stuff, send mail on success and generate JSON response
     */
    public function handleFeedback(Request $request)
    {
        $this->logger->info(__METHOD__);

        // filter stuff
        $errors = [];

        $data = $request->getParsedBody();
    
        $email = isset($data['formemail']) ? $this->sanitizeEmail($data['formemail']) : '';
    
        if ($email === '') {
            $errors[] = ['email' => 'Ungültige oder leere E-Mail-Adresse'];
        }
    
        $message = isset($data['formmessage']) ? $this->sanitizeMessage($data['formmessage']) : '';
        if ($message === '') {
            $errors[] = ['message' => 'Probleme bei der Validierung Ihrer Nachricht - bitte senden Sie ausschließlich Text.'];
        }
    
        $name = isset($data['formname']) ? $this->sanitizeName($data['formname']) : '';
        if ($name === '') {
            $errors[] = ['name' => 'Ungültiger oder leerer Name'];
        }
       
        
        $serverParams = $request->getServerParams();
        
        // Usual way to get the remote IP address. But this value is 127.0.0.1 in AWS Lambda environment.
        if (isset($serverParams['REMOTE_ADDR'])) {
            $remoteIp = $serverParams['REMOTE_ADDR'];
            $this->logger->info('Remote IP in REMOTE_ADDR: ' . $remoteIp);
      
        }
         
       
        $headers = $request->getHeaders();
               
        // AWS Lambda with Bref
        // Attention! There are two IPs in this entry! Surprisingly, ReCaptcha works as well.
        // The first IP is from the access provider (e.g. Telekom, NetCologne...), the second comes from Amazon or Cloudflare.
        // Unfortunately, the name of this entry is also dependent on the framework (or PSR-7 library).
        if (isset($headers['X-Forwarded-For']) && $headers['X-Forwarded-For']) {
            $this->logger->info('X-Forwarded-For is set.');
            $remoteIp = $headers['X-Forwarded-For'][0];
            $this->logger->info('Remote IP in X-Forwarded-For: ' . $remoteIp);
        }


        
        $gRecaptchaResponse = isset($data['g-recaptcha-response']) && $data['g-recaptcha-response'] ?  $data['g-recaptcha-response'] : null;
        $recaptchaResult = $this->validateRecaptcha($gRecaptchaResponse, $remoteIp);
        if ($recaptchaResult !== true) {
            $errors[] = ['recaptcha' => 'ReCaptcha-Prüfung nicht erfolgreich. Sie wurden als Bot identifiziert!',
            ];
        }

        if (count($errors) === 0) {
            $mailAction = new SendFeedback($this->logger);
            $mailResult = $mailAction->send($email, $name, $message);
            if ($mailResult === false) {
                $errors[] = ['sendmail' => 'Fehler beim Versenden der Nachricht.'];
            }
        }

        if (count($errors)) {
            $this->logger->info('Function call ends with errors: ' . json_encode($errors));

            $result = ['success' => false,
            'message' => 'Fehler bei der Verarbeitung Ihrer Anfrage',
            'errors' => $errors];
        } else {
            $this->logger->info('Function was run successfully');
            $result = ['success' => true,
            //'vars' => $data, // for checking purposes
            'message' => 'Vielen Dank - Ihre Nachricht wurde übermittelt.'];
        }

        $payload = json_encode($result);

        $this->response->getBody()->write($payload);
        return $this->response
            ->withHeader('Content-Type', 'application/json');

    }

}