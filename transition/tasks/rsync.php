<?php
/**
 * task_rsync
 *
 * Runs an rsync process to copy files from the source to the target.
 */
class task_rsync extends Task {
	
	function exec() {
		// sets the source and target by replacing the string {SESSION_ROOT} with
		// the defined path.		
		$source = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['source']);
		$target = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['target']);
		
		echo "Starting Rsync {$source} -> {$target} ...\n";
		
		// execute rsync and test the return value for any errors
		$exec = RSYNC." -rvz --delete --progress -e ssh {$source} {$target}";
		$ret = 0;
		passthru($exec,$ret);		
		if ($ret) {
			throw new Exception('rsync failed with return '.$ret);
		}
		
		echo "Completed Rsync...\n";
	}
}
