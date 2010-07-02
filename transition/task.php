<?php
/**
 * Task
 * 
 * This is the base class for all Transition tasks. It defines the constructor 
 * and an abstract exec function.
 *
 */
abstract class Task {
	
	protected $_opts = array();
	
	final function __construct(array $opts=array()) {
		$this->_opts = $opts;				
	}
	
	abstract function exec();
	
}