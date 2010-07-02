<?php

class task_halt extends Task {
	
	public function exec() {

		echo "Terminating deployment\n";
		echo 'Your session root is: ', SESSION_ROOT, "/\n";
		exit;	
	}
}


