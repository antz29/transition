<?php
/**
 * task_replicate
 * 
 * Replicates the source files using rsync to the specified target removing
 * the source files when complete. If multiple replicate tasks are used, the
 * source files are preserved until the last replicate task completed as long
 * it is the last task in the task queue.
 *
 */
class task_replicate extends Task {
	
	function exec() {
		
		// sets the source and target by replacing the string {SESSION_ROOT} with
		// the defined path. 		
		$source = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['source']);
		$target = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['target']);
		
		echo "Starting Replication {$source} -> {$target} ...\n";
		
		// execute rsync and test the return value for any errors
		$exec = RSYNC." -rvz --progress -e ssh {$source} {$target}";
		$ret = 0;
		passthru($exec,$ret);		
		if ($ret) {
			throw new Exception('rsync failed with return '.$ret);
		}
		

		// check if this is the final task and if required
		// remove the source files 
		if ($this->_opts['_final']) {
			echo "Files copied successfully, removing source file...";

			$exec = "rm -rvf {$source}";
			$ret = 0;

			system($exec,$ret);		
		
			if ($ret) {
				throw new Exception('rm failed with return '.$ret);
			}
		}

		echo "Completed Replication.";		
	}
}
