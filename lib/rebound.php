<?php
/**
 * Rebound
 * 
 * This is the abstract class to be extended by any application looking to implement a simple
 * CLI interface. It creates an interface based on the SVN interface and also uses Reflection
 * to generate usage information including their doc comments. 
 *
 */
abstract class Rebound {
	
	const WRAP = 75;
	
	private $_actions = array();
	protected $_name = "";
	
	protected $_opts = array();
	
	public function __construct($opts = array()) 
	{
		$this->_opts = $opts;
	}
	
	public function __destruct() 
	{
		$this->shutdown();
	}
	
	protected function preAction($action,$args)
	{
		return true;
	}
	
	/**
	 * Initiate the Rebound session. 
	 *
	 * @param unknown_type $args
	 * @return void
	 */	
	public function run($args) 
	{	
		// set the name (used in help messages) to the class name.
		$this->_name = strtolower(get_class($this));
		$this->getActions();
		
		ini_set('display_errors',0);
		error_reporting(-1);
		set_error_handler(array($this,'handleError'));
		set_exception_handler(array($this,'handleException'));
		
		$this->init();
		
		// if the user has called the command without any arguments, show the usage information. 
		if (!count($args)) 
		{
			return $this->callAction('help',array());
		}
		
		// take the first argument as the action
		$action = array_shift($args);
		
		if ($action != 'help') $this->preAction($action,$args);
		
		// execute the action passing the remaining arguments
		$this->callAction($action,$args);		
	}
	
	public function handleError($errno,$errstr,$errfile,$errline)
	{
		restore_error_handler();
		
		if (stristr($errstr,'Missing argument') && stristr($errstr,'action_')) return;

		throw new Exception("{$errno}: {$errstr} on line {$errline} in file {$errfile}",$errno);
	}
	
	public function handleException(Exception $e)
	{
		restore_error_handler();
		restore_exception_handler();

		$this->error($e->getMessage());
	}
	
	/**
	 * init
	 * 
	 * This method should be overriden and is called before an action is executed. 
	 *
	 */
	protected function init() {}
	
	/**
	 * shutdown
	 * 
	 * This method should be overriden and is called after an action is executed. 
	 *
	 */
	protected function shutdown() {}
	
	
	/**
	 * callAction
	 * 
	 * Calls a requested action
	 *
	 * @param string $action
	 * @param array $args
	 */
	private function callAction($action,$args=array()) 
	{
		// generate the method name by prepending 'action_' and make sure it is defined
		$method = 'action_'.$action;
		if (!method_exists($this,$method)) {
			$this->error("Unknown command: '{$action}'\nType '{$this->_name} help' for usage.");
		}
		
		// call the action passing the $args array as function parameters
		call_user_func_array(array($this,$method),$args);
	}
	
	/**
	 * getActions
	 * 
	 * analyze the class and generate an array of actions and their
	 * arguments. This then set to the actions property and used
	 * to generate usage information.
	 *
	 */	
	private function getActions() 
	{
		// create a new ReflectionClass instance for the current class   
		$c = new ReflectionClass(get_class($this));
		
		// get all the defined methods
		$methods = $c->getMethods();
		
		$actions = array();
		
		// iterate through the methods filtering out any that are not
		// prefixed with action_.
		foreach ($methods as $method) 
		{			
			if (stristr($method->getName(),'action_')) 
			{
				// remove the prefix to get the action name
				$action = str_replace('action_','',$method->getName());
				
				// iterate through the parameters adding each to an array
				// wrapping optional params. with []
				$actions[$action] = array();
				foreach ($method->getParameters() as $param) 
				{
					$name = strtoupper($param->getName());
					if ($param->isOptional()) 
					{
						$name = '['.$name.']';	
					} 
					$actions[$action][] = $name;
				}
			}
		}
		
		$this->_actions = $actions;
	}
	
	/**
	 * Displays help summary or more detailed 
	 * help for a 
	 * specific sub command.
	 * 
	 * @somethinng
	 * @something else
	 */
	private function action_help($subcommand=null) 
	{
		if (isset($subcommand)) 
		{
			return $this->subcommandHelp($subcommand);
		}
		return $this->defaultHelp();
	}
	
	/**
	 * defaultHelp
	 * 
	 * Generates the default usage information listing
	 * all commands with their detected arguments. 
	 *
	 */
	private function defaultHelp() 
	{
		echo "usage: {$this->_name} SUBCOMMAND [options] [args]\n";
		echo "Type '{$this->_name} help SUBCOMMAND' for help on a specific subcommand.\n\n";
		echo "Available subcommands:\n";
		$actions = $this->_actions;
		foreach ($actions as $action => $args) 
		{
			$args = implode(' ',$args);	
			echo "  $action $args\n";
		}		
	}
	
	/**
	 * subcommandHelp
	 * 
	 * Generates usage information for a specific command, displaying the items
	 * doc comment.
	 *
	 * @param string $subcommand
	 */
	private function subcommandHelp($subcommand) 
	{
		if (!isset($this->_actions[$subcommand])) 
		{
			$this->error("Unknown command: '{$subcommand}'\nType '{$this->_name} help' for usage.");		
		}
		$args = implode(' ',$this->_actions[$subcommand]);	
		echo "usage: {$this->_name} {$subcommand} {$args}\n";
		$method = 'action_'.$subcommand;
		$m = new ReflectionMethod(get_class($this),$method);
		$desc = $m->getDocComment();
		$desc = explode("\n",$desc);
		$description = "";
		$blanks = 0;
		foreach ($desc as $des) 
		{
			if (trim($des) == '/**' || trim($des) == '*/') continue;
			
			preg_match('/\s*\*\s*(.*)/',$des,$match);
			$match[1] = trim($match[1]);
			if (!strlen($match[1])) 
			{
				$blanks++;
				if ($blanks > 1) 
				{
					continue;
				} 
				else 
				{
					$description .= "\n";
					continue;
				}
			}
			
			if (substr($match[1],0,1) == '@') continue;
			
			$description .= $match[1]." ";
		}
		echo "\n".wordwrap($description,self::WRAP)."\n";				
	}
	
	/**
	 * error
	 * 
	 * This handles errors
	 * appropriatly.
	 *
	 * @param unknown_type $msg
	 */
	protected function error($msg) 
	{
		echo "\n{$msg}\n";
		die(1);	
	}
}
