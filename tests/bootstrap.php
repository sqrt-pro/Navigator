<?php

/**
 * Файл инициализации для запуска тестов
 *
 * Перед запуском сделать composer install
 * http://getcomposer.org/download/
 */

require_once __DIR__ . '/../vendor/autoload.php';

define('TEST_MIGR', realpath(__DIR__ . '/../migrations'));
define('TEST_HOST', 'localhost');
define('TEST_USER', 'test');
define('TEST_PASS', 'test');
define('TEST_DB', 'test');