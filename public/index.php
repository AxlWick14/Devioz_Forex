<?php
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/core/Router.php';

$router = new Router();
$router->dispatch();
