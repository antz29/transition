<?php

class task_copy extends Task {
	
	function exec() {
		
		$source = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['source']);
		$target = $this->_opts['target'];
		
		echo "Starting Copy {$source} -> {$target} ...\n";
		
		// execute SVN and test the return value for any errors
		$exec = "cp -r {$source} {$target}";
		passthru($exec,$ret);
		
		if ($ret) {
			throw new Exception('copy failed with return '.$ret);
		}
		
		echo "Completed Copy...\n";
	}
}