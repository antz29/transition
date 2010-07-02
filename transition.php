#!/usr/bin/php

<?php
/**
 * An alias of the php constant DIRECTORY_SEPARATOR 
 */
define('DS',DIRECTORY_SEPARATOR);

/**
 * The path the this application.
 */
define('ROOT',realpath(dirname(__FILE__)).DS);

/**
 * This is the folder which stores files created during the sessions. SVN exports etc.  
 */
define('TMP_ROOT',realpath(sys_get_temp_dir()).DS);

$path = ROOT.'lib'.DS.'tapped.php';
require $path;

$t = smp_Tapped::getInstance()->setCache(0)->addPath(ROOT);
$t->registerAutoloader();

$user = posix_getlogin();

$config = "/home/{$user}/.transition";

if (!file_exists($config)) {
	$settings['svn_user'] = trim(readline("SVN User ({$user}):"));
	$settings['svn_user'] = $settings['svn_user'] ? $settings['svn_user'] : $user;
	$settings['svn_pass'] = trim(readline("SVN Password:"));
	
	$str = "<?php return ".var_export($settings,true).";";
	file_put_contents($config,$str);
}
else {
	$settings = include($config);	
}

define('SVN_USER',$settings['svn_user']);
define('SVN_PASS',$settings['svn_pass']);

//create a new instance of transition and pass over a configuration array.
$deploy = new Transition();

//strip out the first command line argument (contains the file name)
array_shift($argv);

//run transition
$deploy->run($argv);
