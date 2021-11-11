<?php

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
    define('JPATH_BASE', dirname(dirname(__DIR__)));
    require_once(JPATH_BASE . '/includes/defines.php');
    require_once(JPATH_BASE . '/includes/framework.php');
    defined('DS') or define('DS', DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/migration.php';

$app = JFactory::getApplication('site');