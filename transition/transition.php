<?php

/**
 * Transition
 *
 * This is the main Transition class that extends the Rebound library
 * which handles the command line interaction.
 *
 */
class Transition extends Rebound {
	
	/**
	 * Configure the given SETTING with the given VALUE
	 * 
	 * Settings: 
	 * 
	 * CONFIG - Path to the transition config root.
	 * SVN_ROOT - The path to the project root of the SVN repository.
	 * DEV_GROUP - The unix group that all developers are a member of.
	 * 
	 * This command should be run as root.
	 */
	protected function action_set_config($setting,$value)
	{
		if (!file_exists(ROOT.'config.php')) {
			$settings = array();			
		}
		else {
			$settings = include(ROOT.'config.php');
		}	
				
		$settings[$setting] = $value;

		echo "Set {$setting} to '{$value}'\n";
		file_put_contents(ROOT.'config.php','<?php return '.var_export($settings,true).';');
	}
	
	/**
	 * Return the current value of the given SETTING
	 * 
	 * Settings: 
	 * 
	 * CONFIG - Path to the transition config root.
	 * 
	 * SVN_ROOT - The path to the project root of the SVN repository.
	 * 
	 * DEV_GROUP - The unix group that all developers are a member of.
	 * 
	 */
	protected function action_get_config($setting)
	{
		$val = defined($setting) ? constant($setting) : 'Not Set';
		echo "{$setting}: {$val}\n";
	}
	
	/**
	 * Deploy a specific release of a project to the specified target.
	 *
	 * @param string $project
	 * @param string $release
	 * @param string $target
	 */
	protected function action_deploy($project,$release,$target) {
		
		if (!isset($project)) $this->error('You must specify a project.');
		if (!isset($release)) $this->error('You must specify a release.');
		if (!isset($target)) $this->error('You must specify a target.');
		
		$project_name = $project;
		
		// retrieve the project information and test to ensure that it has tasks to execute
		$project = $this->getProject($project);
		if (!count($project['tasks']))
		{
			$this->error("No tasks defined in project: '{$project['name']}'\nEdit the project XML config and add some deployment tasks.");
		}

		// define a tasks array and populate it with the tasks needed for the current target
		$tasks = array();
		foreach ($project['tasks'] as $task)
		{
			// explode the target string to get an array of targets
			$task['target'] = explode(',',$task['target']);
			
			// ensure there are some targets defined and that the current target is in the array
			if (!count($task['target'])) continue;
			if (!in_array($target,$task['target'])) continue;
			
			
			$task['params']['_target'] = $target;
			$task['params']['_project'] = $project_name;
			
			// add the task to the array
			$tasks[] = $task;
		}
			
		// iterate through the tasks until all the tasks are gone, removing a task
		// from the top of the array on each iteration
		while (count($tasks))
		{
			// remove a task from the array
			$task = array_shift($tasks);
			
			// execute the task, passing the current $release and if this is the 
			// last task is the array. 
			$this->execTask($task,$release,(count($tasks) == 0));
		}
	}

	/**
	 * execTask
	 *
	 * Executes a given $task passing over the current $release and if the task is the $final task.
	 * This $final parameter is used by the replicate task when copying to multiple targets to ensure
	 * it only removes the source files after it has finished copying to all targets.
	 *
	 * @param string $task
	 * @param string $release
	 * @param boolean $final
	 */
	private function execTask($task,$release,$final=false) 
	{
		$task['params']['release'] = $release;
		$task['params']['_final'] = $final;
		$class = 'task_'.$task['type'];
		if (!class_exists($class)) 
		{
			$this->error("Task is undefined: '{$task['type']}'\nCheck the project XML config is correct.");
		}
		$t = new $class($task['params']);
		
		try 
		{
			$t->exec();
		}
		
		catch (Exception $e) 
		{
			$this->error("Task ended in error: '{$task['type']}'\n".$e->getMessage());
		}
	}

	/**
	 * Show the configuration details of a project.
	 */
	protected function action_show($project,$target=null) {
		if (!isset($project)) $this->error('You must specify a project.');

		$project = $this->getProject($project);

		print "Name: {$project['name']}\n";
		print "Tasks:\n";
		foreach ($project['tasks'] as $task) {
			if (isset($target)) {
				$task['target'] = explode(',',$task['target']);
				if (!in_array($target,$task['target'])) {
					continue;
				}
				$task['target'] = implode(',',$task['target']);
			}
			print "  - {$task['type']}";
			print " / {$task['target']}\n";
			if (!isset($task['params'])) continue;
			foreach ($task['params'] as $name => $value) {
				print "    - {$name} = {$value}\n";
			}
		}
	}


	/**
	 * Returns the $project configuration information either as an associative array
	 * or by providing bool true to $xml, the raw XML.
	 *
	 * @param string $project
	 * @param bool $xml
	 * @return string
	 */
	private function getProject($project,$xml=null) {

		// generate the path the the project XML file
		$file = CONFIG.DS.$project.'.xml';

		// check if the project has been defined
		if (!file_exists($file)) {
			$this->error("Unknown project: '{$project}'\nType '{$this->_name} list' to list all projects.");
		}

		// if requesting raw XML, display this and return
		if (isset($xml)) {
			return file_get_contents($file);
		}

		// generate an associative array from the project xml file
		$project = array('name'=>$project);
		$xml = simplexml_load_file($file);
		$cnt = 0;
		foreach ($xml->task as $task) {
			$cnt++;
			$project['tasks'][$cnt]['type'] = (string) $task['type'];
			if (isset($task['target'])) {
				$project['tasks'][$cnt]['target'] = (string) $task['target'];
			}
			foreach ($task->param as $param) {
				if (!isset($param['value'])) {
					$project['tasks'][$cnt]['params'][(string) $param['name']] = (string) $param->value;
				}
				else {
					$project['tasks'][$cnt]['params'][(string) $param['name']] = (string) $param['value'];	
				}
			}
		}
		return $project;
	}

	/**
	 * Returns an of all the project.
	 *
	 * @return array
	 */
	private function getProjects() {
		// create a glob
		$files = CONFIG.DS.'*.xml';

		// get the matching config files
		$list = glob($files);

		//iterate through the $list array getting value as a reference
		foreach ($list as &$project) {
			//explode the file basename on '.' to separate the file extension
			$project = explode('.',basename($project));
				
			//remove the file extension
			array_pop($project);
				
			//implode to get the project name
			$project = implode($project);
				
			//this process is designed to work with files with multiple '.' ie. somefile.something.xml
		}
		return $list;
	}

	/**
	 * List all configured projects.
	 */
	protected function action_list() {
		$list = $this->getProjects();
		if (!count($list)) {
			return;
		}
		echo implode("\n",$list)."\n";
	}

	/**
	 * Tags and branches a new release as appropriate, optionally adding a comment
	 * to describe the release.
	 *
	 * You should specify the project name ie. my_project
	 * 
	 * Subprojects are automatically released with the release of a parent project.  
	 *
	 * If the release is a bugfix release, there must be
	 * an existing release branch where the fixes have been committed to HEAD.
	 */
	protected function action_release($project,$release,$comment="") {
		
		if (!isset($project)) $this->error('You must specify an project.');
		if (!isset($release)) $this->error('You must specify a release.');

		$spl = explode('.',$release);

		if (count($spl) != 3) $this->error('Incorrectly formatted release string, must be of the format x.y.z');

		// For a bugfix release
		if ($spl[2] != 0)
		{
			// create the branch name from the requested release by forcing the third element to be 0.
			// this would convert 1.1.1 to 1.1.0 or 1.0.1 to 1.0.0.
			$spl[2] = 0;
			$branch = implode('.',$spl);
				
			//create the svn path tag and set the source path the be the branch
			$root = SVN_ROOT.'/'.$project.'/';
			$path = $root.'/branches/'.$branch;
			$t_target = $root.'/tags/'.$release;
			
			echo "Tagging project...\n";
			$this->svnCopy($path,$t_target,"Creating tag for {$release} release. {$comment}");
			
			$list = $this->svnList($root);
			$s_projects = array();
			if (in_array('subprojects/',$list)) {
				$list  = $this->svnList($root.'/subprojects/');
				foreach ($list as $s_project) {
					$s_project = substr($s_project,0,-1);
					$s_projects[$s_project] = array(
						'path' => $root.'/subprojects/'.$s_project.'/branches/'.$branch,
						'tag' => $root.'/subprojects/'.$s_project.'/tags/'.$release
					);
				}
			}

			if (count($s_projects)) {
				echo "Tagging subprojects...\n";
				foreach ($s_projects as $name => $s_project) {
					echo "  {$name}...\n";
					$this->svnCopy($s_project['path'],$s_project['tag'],"Creating subproject tag for {$name} {$release} release. {$comment}");
				}
			}	
		}

		// For a major or minor release
		else
		{
			// create the svn paths for the branch and tag with the source path being trunk		
			
			$root	   = SVN_ROOT.$project.'/';
			$path	   = $root.'trunk';
			$b_target  = $root.'branches/'.$release;
			$t_target  = $root.'tags/'.$release;
			
			$list = $this->svnList($root);
			$s_projects = array();
			
			if (in_array('subprojects/',$list)) {
				$list  = $this->svnList($root.'/subprojects/');
				foreach ($list as $s_project) {
					$s_project = substr($s_project,0,-1);
					$s_projects[$s_project] = array(
						'path' => $root.'/subprojects/'.$s_project.'/trunk',
						'tag' => $root.'/subprojects/'.$s_project.'/tags/'.$release,
						'branch' => $root.'/subprojects/'.$s_project.'/branches/'.$release
					);
				}
			}			
			
			//root project
			echo "Branching / Tagging root project...\n";
			$this->svnCopy($path,$t_target,"Creating tag for {$release} release. {$comment}");
			$this->svnCopy($path,$b_target,"Creating branch for {$release} release. {$comment}");
			
			if (count($s_projects)) {
				echo "Branching / Tagging subprojects...\n";
				foreach ($s_projects as $name => $s_project) {
					echo "  {$name}...\n";
					$this->svnCopy($s_project['path'],$s_project['tag'],"Creating subproject tag for {$name} {$release} release. {$comment}");
					$this->svnCopy($s_project['path'],$s_project['branch'],"Creating subproject branch for {$name} {$release} release. {$comment}");				
				}
			}
		}
	}

	/**
	 * Updates the transition config from svn. 
	 */
	protected function action_update() {
		$this->svnUpdate(CONFIG);
	}

	/**
	 * Initializes the transition config from svn. Should be run as root.
	 */
	protected function action_init($svn) {
		if (!isset($svn)) $this->error('You must specify an SVN path for the configuretion.');
		
		if (!stristr($svn,'://')) {
			$svn = SVN_ROOT.DS.$svn;
		}
		
		$this->svnCo($svn,CONFIG);
		$grp = DEV_GROUP;
		
		$CONFIG = CONFIG;
		shell_exec("chown -Rc root:{$grp} '{$CONFIG}'");
		shell_exec("chmod -Rc 775 '{$CONFIG}'");
	}	
	
	private function svnCo($source,$target) {
		$svn_cmd = '"'.SVN.'"';
		$un = SVN_USER;
		$pw = SVN_PASS;
		$cmd = "{$svn_cmd} co --username \"{$un}\" --password \"{$pw}\" {$source} {$target}";
		passthru($cmd);
	}	
	
	private function svnUpdate($path) {
		$svn_cmd = '"'.SVN.'"';		
		$un = SVN_USER;
		$pw = SVN_PASS;
		$cmd = "{$svn_cmd} update --username \"{$un}\" --password \"{$pw}\" {$path}";
		passthru($cmd);
	}	
	
	private function svnList($path) {
		$svn_cmd = '"'.SVN.'"';		
		$un = SVN_USER;
		$pw = SVN_PASS;
		$list = "{$svn_cmd} list --username \"{$un}\" --password \"{$pw}\" {$path}";
		$list = shell_exec($list);
		$list = array_filter(explode("\n",$list));
		foreach ($list as &$item) {
			$item = trim($item);
		}
		return $list;		
	}	
	
	private function svnCopy($source,$target,$comment) {
		$svn_cmd = '"'.SVN.'"';
		$un = SVN_USER;
		$pw = SVN_PASS;
		$exec = $svn_cmd." copy --username \"{$un}\" --password \"{$pw}\" {$source} {$target} -m \"{$comment}\"";
		$out = shell_exec($exec);
		if (!stristr($out,"Committed revision")) {
			$this->error("Failed on svn copy. {$source} {$target}:\n{$out}");
		}
		return true;
	}
	
	/**
	 * The init function call by Rebound at the start of a session.
	 * It configures the Transition environment to execute tasks.
	 *
	 */
	protected function init() {}
	
	protected function preAction($action,$args)
	{
		if ($action == 'set_config') return;

		if (!file_exists(ROOT.'config.php')) {
			$this->error("This transition installation has not been configured.\nPlease configure the base settings with transition set_config.");			
		}
		else {
			$settings = include(ROOT.'config.php');
			if (!isset($settings['CONFIG'])) $this->error('The CONFIG has not been set, please configure.');
			if (!isset($settings['SVN_ROOT'])) $this->error('The SVN_ROOT has not been set, please configure.'); 
			if (!isset($settings['DEV_GROUP'])) $this->error('The DEV_GROUP has not been set, please configure.');
			
			foreach ($settings as $setting => $value) {
				define($setting,$value);
			}
		}
		
		if (!defined('SVN')) {
			$svn = trim(shell_exec('which svn'));
			if (!$svn) $this->error('Could not find svn on path, please ensure it is installed correctly or set the SVN setting to define the full path.');
		}
		
		if (!defined('RSYNC')) {
			$svn = trim(shell_exec('which rsync'));
			if (!$svn) $this->error('Could not find rsync on path, please ensure it is installed correctly or set the RSYNC setting to define the full path.');
		}
		
		// If required attempt to create the TMP_ROOT root path.
		if (!file_exists(TMP_ROOT)) {
			if (!@mkdir(TMP_ROOT)) {
				$this->error('Failed to create temp folder at '.TMP_ROOT);
			}
		}

		// Create a unique session root path for this session
		define('SESSION_ROOT',TMP_ROOT.md5(uniqid()));

		// If there is an existing session root folder existing, remove it.
		if (file_exists(SESSION_ROOT)) {
			rmdir(SESSION_ROOT);
		}

		// Create the session root folder
		if (!mkdir(SESSION_ROOT)) {
			$this->error('Failed to create session root folder');
		}		
	}
	
	/**
	 * The shutdown function is executed by rebound at the end of the session.
	 * Here we delete the session root.
	 *
	 */
	protected function shutdown() {
		system('rm -rf "'.SESSION_ROOT.'"');
	}
}
