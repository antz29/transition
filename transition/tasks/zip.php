<?php

class task_zip extends Task {
	
	function exec() {
		
		$source = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['source']);
		$target = $this->_opts['target'];		
		$target = str_replace('{DATE}',date('Ymd'),$target);
		$target = str_replace('{PROJECT}',$this->_opts['_project'],$target);
		$target = str_replace('{TARGET}',$this->_opts['_target'],$target);
		$target = str_replace('{RELEASE}',$this->_opts['release'],$target);
		
		echo "Creating ZIP Archive {$source} -> {$target} ...\n";
		
		$zip_cmd = '"'.ZIP.'"';
		
		// execute SVN and test the return value for any errors
		$exec = $zip_cmd." \"{$source}\" \"{$target}\"";
		
		echo $exec;
		echo $this->_wsh->Exec($exec)->StdOut->ReadAll;
		
		echo "Created ZIP Archive...\n";
	}
}