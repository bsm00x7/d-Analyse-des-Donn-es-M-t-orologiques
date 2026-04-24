<?php

require 'vendor/autoload.php';

use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

$dispatcher = simpleDispatcher(require 'routes.php');

$uri = $_SERVER['REQUEST_URI'];

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}

$basePath = '/analyseM';

if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
if ($uri === '' || $uri === false) {
    $uri = '/';
}

$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $uri);

switch ($routeInfo[0]) {

    case Dispatcher::NOT_FOUND:
        http_response_code(404);
        include __DIR__ . '/app/view/error/404.php';
        break;
    case Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        include __DIR__ . '/app/view/error/405.php';
        break;
    case Dispatcher::FOUND:

        $page = $routeInfo[1];

        if ($page === 'home') {
            include_once __DIR__ . '/app/view/home/home.php';
        } elseif ($page === 'login') {
            include __DIR__ . '/app/view/auth/login.php';
        } elseif ($page === 'signUp') {
            include __DIR__ . '/app/view/auth/sign_up.php';
        } elseif ($page === 'dashboard') {
            include __DIR__ . '/app/view/dashboard/dashboard.php';
        } elseif ($page === 'logout') {
            include __DIR__ . '/app/view/logout.php';
        } elseif ($page === 'gestionError') {
            include __DIR__ . '/app/view/dashboard/gestion_error.php';
        } elseif ($page === 'gestionUsers') {
            include __DIR__ . '/app/view/dashboard/gestion_users.php';
        }

        break;
}
