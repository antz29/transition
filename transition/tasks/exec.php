<?php
/**
 * task_export 
 * 
 * Allows you to execute an arbitary command
 *
 */
class task_exec extends Task {
	
	function exec() {
		$command = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['command']);
		$command = str_replace('{TARGET}',$this->_opts['_target'],$command);
		$command = str_replace('{PROJECT}',$this->_opts['_project'],$command);
		$command = str_replace('{RELEASE}',$this->_opts['_target'],$command);
		
		$desc = isset($this->_opts['desc']) ? $this->_opts['desc'] : false; 
		
		if ($desc) {
			echo "{$desc}...\n";
		}
		else {
			echo "Executing command {$command} ...\n";	
		}
		
		$ret = 0;
		passthru($command,$ret);		
		if ($ret) {
			throw new Exception('Failed with return '.$ret);
		}
		
		echo "Completed command...\n";
	}
}
