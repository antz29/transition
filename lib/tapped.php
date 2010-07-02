<?php

/**
 * smp_Tapped
 *
 * A caching autoloader designed to make your classes available 'on-tap'.
 *
 * @author John Le Drew <jp@antz29.com>
 * @copyright Copyright (c) 2009, John Le Drew
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @version 2.0.0-alpha
 * 
 */
class smp_Tapped
{
	static private $_instance;

	private $_init = false;
	private $_tmp;
	private $_paths = array();
	private $_classes = array();
	private $_types = array();
	private $_map = array();
	private $_cache = false;
	private $_archive_support = false;

	private function __construct()
	{

	}
	
	private function init()
	{
		$tmp = defined('TMP') ? TMP : sys_get_temp_dir().DS;
		$tmp = $tmp.md5($_SERVER['SCRIPT_FILENAME'].__FILE__).'.tapped'.DIRECTORY_SEPARATOR;
		if ($this->_cache && !file_exists($tmp)) mkdir($tmp);

		$this->_archive_support = class_exists('smp_Archive');
		$this->_tmp = $tmp;
		$this->_init = true;
	}
	
	private function __clone() {}

	/**
	 * addPath
	 * 
	 * Add a path from which to autoload classes.
	 *
	 * @return smp_Tapped
	 */
	public function addPath($path)
	{	
		if (!is_dir($path))
		{
			throw new Exception("Failed to add smp_Tapped path '{$path}'. It does not exist or is not a directory.");
		}
		
		if (!$this->_init) $this->init();

		$path = realpath($path);

		$this->_paths[$path] = $path;
		
		$this->_classes = array_merge($this->_classes,$this->getClasses($path));

		return $this;
	}

	public function getPaths()
	{
		return array_values($this->_paths);
	}
	
	/**
	 * getClassList
	 * 
	 * Return an array of all available classes.
	 * 
	 * @return array
	 */
	public function getClassList()
	{
		return array_keys($this->_classes);
	}
	
	/**
	 * setCache
	 * 
	 * Set the cache timeout in seconds.
	 *
	 * Tapped will cache the class index for this many seconds. By default this
	 * is one day or 86400 seconds.
	 *
	 * @param $timeout
	 * @return unknown_type
	 */
	public function setCache($timeout)
	{
		$this->_cache = $timeout;
		return $this;
	}

	/**
	 * Cache the given $data to a $file.
	 *
	 * @param $data mixed
	 * @param $file string
	 * @return boolean
	 */
	private function setCacheToFile($data,$file)
	{
		if (!$this->_cache) return true;
		$data = array('timeout'=>time()+$this->_cache,'data'=>$data);
		$data =  var_export($data,true);
		$code = "<?php return {$data}; ?>";
		return file_put_contents($file,$code);
	}

	/**
	 * Retrieve cached data from a $file
	 *
	 * @param $file string
	 * @return mixed
	 */
	private function getCacheFromFile($file)
	{
		if (!$this->_cache) return null;
		if (!($data = @include($file))) return null;
		if (!isset($data['timeout']) || !isset($data['data'])) return null;
		if (time() > $data['timeout'])
		{
			unlink($file);
			return null;
		}

		return $data['data'];
	}

	/**
	 * Return an array of classes found at a given path.
	 *
	 * @param $path
	 * @return array
	 */
	private function getClasses($path)
	{
		$cache = $this->_tmp.md5($path);
		$paths = $this->getCacheFromFile($cache);
		if (!is_array($paths))
		{
			$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
			$paths = array();
			foreach ($it as $file)
			{
				if (substr(basename($file),-4) == 'smpa')
				{
					$a = new smp_Archive($file);
					$classes = $a->getClasses();
					foreach ($classes as $class) {
						$paths[$class]['arc'] = true;
						$paths[$class]['path'] = (string) $file;
					}
				}
				elseif (substr(basename($file),-3) != 'php') continue;

				$classes = $this->parseFile((string) $file);
				foreach ($classes as $type => $classes)
				{
					foreach ($classes as $class)
					{
						if (!isset($paths[$class]))
						{
							$paths[$class]['type'] = $type;
							$paths[$class]['arc'] = false;
							$paths[$class]['path'] = (string) $file;
						}
					}
				}
			}
			$this->setCacheToFile($paths,$cache);
		}
		return $paths;
	}

	/**
	 * Parse a file and return any defined classes or interfaces.
	 *
	 * @param $file
	 * @return array
	 */
	private function parseFile($file)
	{
		$tokens = token_get_all(file_get_contents($file));
		$classes = array();
		foreach ($tokens as $i => $token)
		{
			if ($token[0] == T_CLASS || $token[0] == T_INTERFACE)
			{
				$i += 2;
				$type = ($token[0] == T_CLASS) ? 'class' : 'interface';
				$classes[$type][$tokens[$i][1]] = $tokens[$i][1];
			}
		}
		return $classes;
	}

	/**
	 * getInstance
	 * 
	 * Return the smp_Tapped instance
	 *
	 * @return smp_Tapped
	 */
	static public function getInstance()
	{
		if (!(self::$_instance instanceof smp_Tapped))
		{
			self::$_instance = new smp_Tapped();
		}
		return self::$_instance;
	}

	/**
	 * registerAutoloader
	 * 
	 * Register the 'load' method with spl_autoload_register to handle loading classes.
	 *
	 */
	public function registerAutoloader()
	{
		spl_autoload_register(array($this,'load'));

		$this->getClassMap();
	}

	/**
	 * unregisterAutoloader
	 * 
	 * Unregister the 'load' method with spl_autoload_unregister.
	 *
	 */
	public function unregisterAutoloader()
	{
		spl_autoload_unregister(array($this,'load'));
	}

	/**
	 * load
	 * 
	 * Load the source file for the specified class.
	 *
	 * @param string $class
	 * @return bool	Will return true on success, false on failure.
	 */
	public function load($class)
	{
		if (!isset($this->_classes[$class]['path'])) return false;

		if (isset($this->_classes[$class]['arc']) && $this->_classes[$class]['arc']	) {
			$a = new smp_Archive($this->_classes[$class]['path']);
			return $a->loadClass($class);
		}
		else {
			$file = $this->_classes[$class]['path'];
			return include $file;
		}
	}

	/**
	 * Return an array describing the relationships between all available classes and interfaces.
	 *
	 * @return array
	 */
	private function getClassMap()
	{
		$cache = $this->_tmp.'map';
		if (!($map = $this->getCacheFromFile($cache)))
		{
			$map = array();
			foreach ($this->_classes as $class => $info)
			{
				$implements = class_implements($class);
				foreach ($implements as $interface)
				{
					$map['implements'][$interface][$class] = $class;
					$map['classes'][$class]['parents'][$interface] = $interface;
				}

				$extends = class_parents($class);
				foreach ($extends as $parent)
				{
					$map['parents'][$parent][$class] = $class;
					$map['classes'][$class]['parents'][$parent] = $parent;
				}
			}

			$this->setCacheToFile($map,$cache);
		}
		$this->_map = $map;
	}

	/**
	 * getChildren
	 * 
	 * Return an array of all classes that extend the specified $class.
	 *
	 * @param string $class
	 * @return array
	 */
	public function getChildren($class)
	{
		switch (self::getType($class)) {
			case 'interface':
				return isset($this->_map['implements'][$class]) ? $this->_map['implements'][$class] : array();
				break;
			case 'class':
				return isset($this->_map['parents'][$class]) ? $this->_map['parents'][$class] : array();
				break;		
		}
		
		return false;
	}

	/**
	 * getParents
	 * 
	 * Return an array of all classes that this class depends on.
	 *
	 * @param string $class
	 * @return array
	 */
	public function getParents($class)
	{
		if (self::getType($class) != 'class') return array();
		return isset($this->_map['classes'][$class]['parents']) ? $this->_map['classes'][$class]['parents'] : array();		
	}
	
	/**
	 * exists
	 * 
	 * Return true if a class or interface is available to autoload if required.
	 *
	 * @param $class string
	 * @return boolean
	 */
	public function exists($class)
	{
		return isset($this->_classes[$class]);
	}

	/**
	 * getType
	 * 
	 * Return if a given name is a class or interface, or false if the class does not exist.
	 *
	 * @param $class string
	 * @return string
	 */
	public function getType($class)
	{
		return isset($this->_classes[$class]['type']) ? $this->_classes[$class]['type'] : $this->getTypeFromClass($class);
	}


	/**
	 * isArchive
	 * 
	 * Return true / false if the given class is currently located in a Simplicity Archive.
	 *
	 * @see smp_Archive
	 * @param $class string
	 * @return boolean
	 */
	public function isArchive($class)
	{
		return isset($this->_classes[$class]) ? (isset($this->_classes[$class]['arc']) && $this->_classes[$class]['arc']) : false;
	}

	/**
	 * getPath
	 * 
	 * Return the current full path of the given class.
	 *
	 * @param $class
	 * @return string
	 */
	public function getPath($class)
	{
		return isset($this->_classes[$class]['path']) ? $this->_classes[$class]['path'] : false;
	}

	/**
	 *  Returns if the given name is a class or interface.
	 *
	 * @param $class
	 * @return string
	 */
	private function getTypeFromClass($class)
	{
		if (!isset($this->_classes[$class])) return false;

		if (isset($this->_classes[$class]['type'])) return $this->_classes[$class]['type'];

		if (interface_exists($class))
		{
			return $this->_classes[$class]['type'] = 'interface';
		}
		elseif (class_exists($class)) {
			return $this->_classes[$class]['type'] = 'class';
		} else {
			return false;
		}
	}
	
	/**
	 * isA
	 * 
	 * Returns boolean true or false if the given $class implements or extends the given $type.
	 * 
	 * @param $class string
	 * @param $type string
	 * @return boolean
	 */
	public function isA($class,$type)
	{
		if (!isset($this->_map['classes'][$class])) return false;
		return isset($this->_map['classes'][$class]['parents'][$type]);
	}  
	
	public function mock($class,$deep = false)
	{
		$path = $this->getPath($class);
		$tokens = token_get_all(file_get_contents($path));
		$in_class = false;

		if ($deep) {
			foreach (smp_Tapped::getInstance()->getParents($class) as $parent) {
				if (!class_exists($parent,false)) $this->mock($parent);			
			}
		}
		
		$cnt = count($tokens);
		for ($i=0;$i < $cnt;$i++) {
			if (!$in_class) {
				if ($tokens[$i][0] != T_CLASS) continue;	
				$i++;
				
				if ($tokens[$i][0] != T_WHITESPACE) continue;
				$i++;
				
				if ($tokens[$i][0] != T_STRING) continue;
				if ($tokens[$i][1] != $class) continue;
				
				$i++;
				$deps = "";
				for ($i=$i;$i<$cnt;$i++) {
					if ($tokens[$i] == '{') break;
					$deps .= $tokens[$i][1];
				}
				$deps = trim($deps);
				
				$eval = "class {$class} {$deps} { \n";
				
				$in_class = true;
			}
			
			if ($tokens[$i][0] != T_FUNCTION) continue;
			$i++;
			
			if ($tokens[$i][0] != T_WHITESPACE) continue;
			$i++;
			
			if ($tokens[$i][0] != T_STRING) continue;
			
			$method = $tokens[$i][1];		
			$meval = "";

			$abs = false;
			for ($s = ($i - 10);$s < $i;$s++) {
				
				switch ($tokens[$s][0]) {
					case T_ABSTRACT:
						$abs = true;
						$meval .= ' abstract';
						break;
					case T_PUBLIC:
						$meval .= ' public';
						break;
					case T_PRIVATE:
						continue(3);
						break;
					case T_PROTECTED:
						$meval .= ' protected';
						$prt = true;
						break;
					case T_STATIC:
						$meval .= ' static';
						$sta = true;
						break;
				}
			}
			
			if ($abs) {
				$eval .= "{$meval} function {$method}();\n";
				$eval = "abstract {$eval}";
			}
			else {
				$eval .= "{$meval} function {$method}() {}\n";
			}
		}
		
		$eval .= "}";	

		eval($eval);
	}
}