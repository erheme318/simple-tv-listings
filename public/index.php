<?php

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use TVListings\Domain\Service\DoctrineEntityManager;
use TVListings\Domain\Repository\ChannelRepository;

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $file = __DIR__ . $_SERVER['REQUEST_URI'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$configuration = array(
    'settings' => array(
        'displayErrorDetails' => true,
        'doctrine' => array(
            'driver' => 'pdo_mysql',
            'dbname' => 'mn_tv_listings',
            'user' => 'root',
            'password' => '$secret',
            'host' => 'localhost',
        ),
        'view' => array(
            'template_path' => __DIR__ . '/../app/templates',
            'twig' => array(
                'cache' => __DIR__ . '/../app/cache/twig',
                'debug' => true,
                'auto_reload' => true,
            ),
        ),
    )
);

$app = new \Slim\App($configuration);

$container = $app->getContainer();
$container['hello_service'] = function ($container) {
    return new TVListings\Domain\Service\HelloService();
};

// Register component on container
$container['view'] = function ($c) {
    $settings = $c->get('settings');
    $view = new \Slim\Views\Twig($settings['view']['template_path'], $settings['view']['twig']);
    $view->addExtension(new \Slim\Views\TwigExtension(
        $c->get('router'),
        $c->get('request')->getUri()
    ));
    $view->addExtension(new Twig_Extension_Debug());

    return $view;
};

$container['doctrine.orm.entity_manager'] = function ($container) {
    $settings = $container->get('settings');
    $isDevMode = true;
    $config = Setup::createAnnotationMetadataConfiguration(
        array(
            $settings['doctrine']['mapping_path'],
            $isDevMode,
        )
    );

    $config->setCustomDatetimeFunctions(array(
        'DATE'  => 'DoctrineExtensions\Query\Mysql\Date',
    ));


    $conn = array(
        'driver' => $settings['doctrine']['driver'],
        'host' => $settings['doctrine']['host'],
        'dbname' => $settings['doctrine']['dbname'],
        'user' => $settings['doctrine']['user'],
        'password' => $settings['doctrine']['password'],
    );

    return EntityManager::create($conn, $config);
};

$container['tvlistings.channel.repository'] = function ($container) {
    $em = new DoctrineEntityManager($container->get('doctrine.orm.entity_manager'));

    return new ChannelRepository($em);
};

$app->get('/', function ($request, $response, $args) {
    $container = $this->getContainer();

    $channel = $container->get('tvlistings.channel.repository')->findOneBySlug('mnb');
    $listings = $container->get('tvlistings.channel.repository')->getTodayListings($channel);

    $this->view->render(
        $response,
        'base.html.twig',
        array(
            'channel' => $channel,
            'listings' => $listings,
        )
    );

    return $response->write($body);
});

// Run!
$app->run();
