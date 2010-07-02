<?php

/**
 * task_replace
 *
 * Used to perform string replacements on a files during deployment.
 */
class task_replace extends Task {
	
	function exec() {
		// set the source file path by replacing the '{SESSION_ROOT}' string with the
		// defined SESSION_ROOT constant.
		$file = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['file']);
		
		$replace = str_replace('{TARGET}',$this->_opts['_target'],$this->_opts['replace']);
		$replace = str_replace('{PROJECT}',$this->_opts['_project'],$this->_opts['replace']);
		$replace = str_replace('{RELEASE}',$this->_opts['release'],$this->_opts['replace']);
		
		echo "Starting Replace {$file} : {$this->_opts['search']} -> {$this->_opts['replace']} ...\n";
		
		if (!file_exists($file)) {
			throw new Exception('Could not complete replace operation: '.$file.' does not exist.');		
		}
		
		// get the contents of the file
		if (!($contents = file_get_contents($file))) {
			throw new Exception('Could not complete replace operation: Failed on file_get_contents.');
		}
		
		// perform the replacement using the options provided in the XML config 
		$contents = str_replace($this->_opts['search'],$replace,$contents);
		
		// save the content back to the source file
		if (!file_put_contents($file,$contents)) {
			throw new Exception('Could not complete replace operation: Failed on file_put_contents.');
		}
		
		echo "Completed Replace...\n";
	}
}
