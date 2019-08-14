<?php


require_once __DIR__ . "/vendor/autoload.php";

use DI\Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Factory\AppFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use \Geschkenet\FeedbackAction;

// We're using Slim 4 without internal DI container, so create Container using PHP-DI
$container = new Container();

AppFactory::setContainer($container);

$app = AppFactory::create();

// Create Monolog logger with output on stderr (display in Cloud)
$container->set('logger', function () {
  
    $logger = new Logger('logger');
    $logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
  
    return $logger;
});


// for tests only...
$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $logger = $this->get('logger');
    $logger->info('Hello, ' . $name . '!');

    $response->getBody()->write('Hello, ' . $name . '!');

    return $response;
});

// Handle send mail request
$app->post('/mail', function (Request $request, Response $response, array $args) {
    $logger = $this->get('logger');
   
    $fba = new FeedbackAction($response,$logger);
    $feedbackResponse = $fba->handleFeedback($request);
    return $feedbackResponse;

});

// Default output to prevent error message when requesting root path 
$app->get('/', function (Request $request, Response $response, array $args) {
    $logger = $this->get('logger');
    $logger->error('Nothing here. Really.');

    $logger->error($request->getAttribute('ENV_FROM_MAIL'));

    $response->getBody()->write("Nothing here.");

    return $response;
});

// Add CORS stuff
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
            ->withHeader('Access-Control-Allow-Origin', 'https://www.geschke.net')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});


$app->run();
