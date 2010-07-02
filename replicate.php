#!/usr/bin/php
<?php
/**
 * executed on prdnfs01, this process checks to see if there are any projects waiting
 * to be replicated and runs the replicate tasks for those projects. 
 */

// get the configuration array defined in the config file. This defines each 
// target with its source path. 
$config = require 'replicate_config.php';

// use transition to get a list of all the projects and split it into an array 
$projects = array_filter(explode("\n",`transition list`));

// iterate through each project target and its defined paths
foreach ($config as $target => $paths) {
	foreach ($paths as $path) {
		echo "{$target} -> {$path}\n";
		//list the folders in the path
		$folders = glob("{$path}*");
		// iterate through each folder
		foreach ($folders as $project) {
			// user the file basename as project name
			$project = basename($project);
			echo "  - {$project}...";
			// check if this is a defined project
			if (!in_array($project,$projects)) 
			{ 
				echo " Invalid project!\n";
				continue;
			}
			// execute the replicate tasks for the project 
			// note: it does not require a release number.
			passthru("transition deploy {$project} 0.0.0 {$target}");
			echo " Done!\n";
		}
	}
}
