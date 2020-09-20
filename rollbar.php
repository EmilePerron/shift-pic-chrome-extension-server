<?php

use \Rollbar\Rollbar;

$config = array(
    'access_token' => $_ENV['rollbar_post_server_access_token'],
    'environment' => 'production',
    'root' => ROOT_DIR
);
Rollbar::init($config);
