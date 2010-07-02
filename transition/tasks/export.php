<?php
/**
 * task_export 
 * 
 * Allows you to perform an SVN export
 *
 */
class task_export extends Task {
	
	function exec() {		
		// sets the target by replacing the string {SESSION_ROOT} with
		// the defined path.		
		$target = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['target']);

		if (!file_exists($target)) mkdir($target,0777,true);
		
		// get the SVN source path from the options 	
		$source = str_replace('{SVN_ROOT}',SVN_ROOT,$this->_opts['source']);
		
		// if the source is not defined as absolute append the path to the
		// tag for this release. Otherwise, use the full path provided.  
		if (!isset($this->_opts['abs'])) {
			$source = "{$source}tags/{$this->_opts['release']}";
		} 
		
		// execute SVN and test the return value for any errors
		$this->svnExport($source,$target);	
		
		if (isset($this->_opts['subprojects'])) {
			$s_projects = explode(',',$this->_opts['subprojects']);
			
			$source = str_replace('{SVN_ROOT}',SVN_ROOT,$this->_opts['source']);
			$target = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['subprojects_target']);
			
			foreach ($s_projects as $s_project) {
				$sp_source = "{$source}subprojects/{$s_project}/tags/{$this->_opts['release']}"; 
				$sp_target = "{$target}{$s_project}/";
				$this->svnExport($sp_source,$sp_target);			
			}
		}
	}
	
	private function svnExport($source,$target) {
		echo "Starting SVN Export {$source} ...\n";
		$svn_cmd = '"'.SVN.'"';
		$user = SVN_USER;
		$pass = SVN_PASS;
		$exec = $svn_cmd." export --username {$user} --password {$pass} --force -q --non-interactive \"{$source}\" \"{$target}\"";
		echo shell_exec($exec);
		echo "Completed SVN Export...\n";		
	}
}
