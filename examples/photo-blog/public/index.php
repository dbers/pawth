<?php
/**
 * Index file.
 *
 * All web requests go to here. This file sets the global configuration for a site and then loads up the core system
 *
 * @package public
 */


	// get root of code
define('ROOT', dirname(dirname(__FILE__)) . '/');

	// set database info
define('DB_HOST', $_SERVER['DB_HOST']);
define('DB_NAME', $_SERVER['DB_NAME']);
define('DB_USER', $_SERVER['DB_USER']);
define('DB_PASS', $_SERVER['DB_PASS']);

define('EMAIL_FROM', 'webmaster@pawth.com');
define('EMAIL_NO_REPLY', 'no-reply@pawth.com');

define('MAX_PHOTO_COUNT', 30);
define('MAX_PHOTO_SIZE', 2097152);
define('MAX_PHOTO_WIDTH', 625);
define('MAX_PHOTO_HEIGHT', 500);
define('MAX_PHOTO_RESIZE', 1000); //if a request tries to make a photo larger then this value we 404


	// set path info
define('TEMPLATES', ROOT . 'templates/');
define('DATA_PATH', ROOT . 'data/');
define('PHP_PATH', ROOT . 'php/');
define('LOG_FILE', DATA_PATH . 'error.log');
define('TMP_FILE_DIR', DATA_PATH . 'tmp/');

	//set debug status
define('DEBUG_LEVEL', 1);
define('LOG_LEVEL', 3);


	//should be defined in php.ini  Here for easy testing purposes only
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
ini_set('error_log', DATA_PATH . '/error.log');


	// pages guests can access
$guests_allowed = array(
	'/' => true,
	'pages/terms_of_use' => true,
	'pages/contact_us' => true,
	'pages/about_us' => true,
	'pages/privacy_policy' => true,
	'pages/home' => true,
	'pages/not_found' => true,
	'users/login' => true,
    'users/logout' => true,
    'users/register' => true,
    'users/forgot_password' => true,
    'photos/view' => true
);

	// pages logged in users can't access
$users_forbidden = array(
	'users/login' => true,
	'users/register' => true,
	'users/forgot_password' => true
);


	//set link alias
$link_alias = array(
	/*
	'other-name' => 'real/path'
	*/
);


	// special layouts
$special_layouts = array(
	/*
	'path' => 'layout-name'
	*/
);

	// special layouts
$dynamic_paths = array(
	/*
	'root-path' => true
	*/
);

	//load up core system
include(PHP_PATH . 'init.php');


	//get an instance of the controller
$app = new \Core\Application();

$app->set_user_func(function() {
	return \Model\User::get_active_user();
});
	//run application
$app->run();











