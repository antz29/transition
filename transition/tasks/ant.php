<?php
/**
 * task_ant
 * 
 * Allows you to run an ant build
 *
 */
class task_ant extends Task {
	
	function exec() {
		echo "Starting Ant...\n";
		
		// sets the source and target by replacing the string {SESSION_ROOT} with
		// the defined path. 
		$target = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['target']);
		$source = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['source']);
		
		// unsets the options
		unset($this->_opts['target']);
		unset($this->_opts['source']);
		
		// prepares an array of params to be passed to ant, forcing deploy.home
		// to be set to the $target. All params defined in the XML config other
		// than source and target are passed to ant using -D. 
		$params = array_merge($this->_opts,array('deploy.home'=>$target));
		$antpars = array();
		foreach ($params as $name => $value) {
			$antpars[] = "-D{$name}={$value}";
		}
		$antpars = implode(' ',$antpars);

		// execute ant and test the return value for any errors 
		$exec = ANT." {$antpars} -f {$source}build.xml prodeng";
		passthru($exec,$ret);
	
		if ($ret) {
			throw new Exception('Ant failed with return '.$ret);
		}
		
		echo "Completed Ant...\n";
	}
}
