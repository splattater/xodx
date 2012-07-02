<?php

$main_dir = rtrim(dirname(__FILE__), '/\\');

# Set include paths
$includePath  = get_include_path() . PATH_SEPARATOR;

$includePath .= $main_dir . '/classes/' . PATH_SEPARATOR;
$includePath .= $main_dir . '/classes/Xodx/' . PATH_SEPARATOR;
$includePath .= $main_dir . '/libraries/' . PATH_SEPARATOR;
$includePath .= $main_dir . '/libraries/Erfurt/' . PATH_SEPARATOR;
$includePath .= $main_dir . '/libraries/Erfurt/Erfurt/' . PATH_SEPARATOR;

set_include_path($includePath);

# Include Zend Autoloader
require_once 'Zend/Loader/Autoloader.php';
require_once 'Application.php';

# Configure Zend Autoloader
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Erfurt_');
$autoloader->registerNamespace('Xodx_');

$app = Application::getInstance();
$app->run();
