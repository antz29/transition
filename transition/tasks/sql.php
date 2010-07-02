<?php

class task_sql extends Task {
	
	function exec() {
		$versions = CONFIG.'db_versioning'.DS.'versions.xml';
		
		if (!file_exists($versions)) {
			$vers = simplexml_load_string("<projects></projects>");
			$from = 1;
		}
		else {
			$vers = simplexml_load_file($versions);	
		}

		$to = null;
		$update_latest = true;
		
		$search = $vers->xpath("//project[@name='{$this->_opts['_project']}']");
		if (!isset($search[0]) || !$project = $search[0]) {
			$project = $vers->addChild('project');
			$project['name'] = $this->_opts['_project'];
			$project['latest'] = 1;
			$from = 1;	
		}
		
		$search = $project->xpath("//release[@version='{$this->_opts['release']}']");
		if (!isset($search[0]) || !$release = $search[0]) {
			$release = $project->addChild('release');
			$release['version'] = $this->_opts['release'];
			$from = (int) $project['latest'];
			$release['from'] = $from;
		}	
		else {
			$from = (int) $release['from'];
			$to = (int) $release['to'];
			$update_latest = false;				
		}
		
		$source = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['source']);
		$target = str_replace('{SESSION_ROOT}',SESSION_ROOT,$this->_opts['target']);

		if (!file_exists($target)) {
			mkdir($target,0777,true);
		}

		$up = $this->concatPatchFiles($source,'up',$from,$to);
		$upfile = $target."{$this->_opts['_project']}_sql_patch_{$from}_{$to}.up.sql";		
		if (!file_put_contents($upfile,$up)) {
			throw new Exception('Failed to write up patch data to sql file.');
		}
		
		$down = $this->concatPatchFiles($source,'down',$from,$to);
		$downfile = $target."{$this->_opts['_project']}_sql_patch_{$to}_{$from}.down.sql";		
		if (!file_put_contents($downfile,$down)) {
			throw new Exception('Failed to write down patch data to sql file.');
		}
		
		if ($update_latest) {
			$project['latest'] = $to;
		} 
		
		$release['to'] = $to;
		
		if (!file_put_contents($versions,$vers->asXML())) {
			throw new Exception('Failed to write version data to db_versions.xml file.');
		}
	}
	
	private function concatPatchFiles($source,$dir,$from,&$to=null) {
		$files = array();
		foreach (new DirectoryIterator($source) as $file) {
			if (!preg_match("!patch_([0-9])+_{$dir}\.sql!",$file->getBasename(),$match)) continue;
			if (!isset($match[1])) continue;
			$ver = (int) $match[1];
			if (isset($to) && $ver > $to) continue;
			if ($ver < $from) continue;
			
			$files[$ver] = $file->getPathname();
		}
		
		ksort($files,(SORT_ASC | SORT_NUMERIC));
		
		$to = isset($to) ? $to : max(array_keys($files)); 
		
		if ($dir == 'up') {
			echo "Starting SQL Patch Generation from patch level {$from} -> {$to} ...\n";
		}
		else {
			echo "Starting SQL Rollback Patch Generation from patch level {$to} -> {$from} ...\n";
		}		
			
		if ($dir == 'down') {
			$files = array_reverse($files,true);			
		};
		
		$patch = "";
		$dir = strtoupper($dir);
		foreach ($files as $ver => $file) {
			$file = file_get_contents($file);
			$patch .= "-- {$dir} PATCH {$ver} START --\r\n{$file}\r\n-- {$dir} PATCH {$ver} END --\r\n";
		}
		
		echo "Completed SQL Patch Generation...\n";
		
		return $patch;
	}
}