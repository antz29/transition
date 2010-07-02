<?php
/**
 * task_remote_exec
 *
 * Executes a script on the remote system.
 */
class task_remote_exec extends Task {
	
	function exec() {
		$host = $this->_opts['host'];
		$command = $this->_opts['command'];
		
		echo "Executing remote command {$command} on {$host} ...\n";
		
		$exec = SSH." {$host} -- {$command}";
		
		$ret = 0;
		passthru($exec,$ret);		
		if ($ret) {
			throw new Exception('Failed with return '.$ret);
		}
		
		echo "Completed remote command...\n";
	}
}
