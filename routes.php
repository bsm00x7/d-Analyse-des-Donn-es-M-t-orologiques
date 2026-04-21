<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {
    $r->addRoute('GET', '/', 'home');
    $r->addRoute('GET', '/login', 'login');
    $r->addRoute('POST', '/login', 'login');
    $r->addRoute('GET', '/sign-up', 'signUp');
    $r->addRoute('POST', '/sign-up', 'signUp');
    $r->addRoute('GET', '/dashboard', 'dashboard');
    $r->addRoute('GET', '/logout', 'logout');
};
