<?php
class task_version extends Task {
	
	function exec() {
		echo "Creating version file...\n";
		
		$target = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['target']); 
		
		file_put_contents($target,$this->_opts['release']);
		
		echo "Created version file...\n";
	}
}
