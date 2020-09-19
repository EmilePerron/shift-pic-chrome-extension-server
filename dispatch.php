<?php

define('ROOT_DIR', getcwd() . '/');

require_once 'envmode.php';
require_once 'autoload.php';
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$controllerName = ucfirst(preg_replace('~\W~', '', $_GET['controller'] ?? null)) . 'Controller';
$controllerClassname = 'App\\Controller\\' . $controllerName;

if (class_exists($controllerClassname)) {
    $controller = new $controllerClassname();

    $actionName = preg_replace('~\W~', '', $_GET['action'] ?? null);
    if ($actionName && method_exists($controller, $actionName)) {
        $reflectionMethod = new ReflectionMethod($controller, $actionName);
        if ($reflectionMethod->isPublic()) {
            $controller->$actionName();
        } else {
            http_response_code(404);
        }
    } else {
        http_response_code(404);
    }
} else {
    http_response_code(404);
}
