<?php
/**
 * Cache system
 *
 * This classes provides methods for the system to intereact with the caching system.
 * 
 * @internal	There are two arrays of cache types because session should never be a default.
 * @internal	This is because $data can be to large for session.  Should be explicit
 *
 * @package	Core
 */
namespace Core;

use \Dbi\MySQL as DBI;

class Cache {
	
		//@todo add apc/xcache ...?
	
	private static $types_default = array('memcache', 'database');
	private static $types_available = array('session', 'memcache', 'database');
	
	private static $memcache = null;
	
	/**
	 * Load value from cache
	 * @param	string	$name	Name of the cache data
	 * @param	string	$cache_key	Cache key used if data needs to be cached per user or other data
	 * @return	mix | null on failure
	 */
	public static function load($name, $cache_key=false) {
		
		if(DEBUG_LEVEL) {
			return null;	
		}
		
		$key = ($cache_key) ? $name.'_'.$cache_key : $name;
		
		$data = null;
		
		foreach(self::$types_available as $type) {
			$func = "self::load_{$type}";
			if($data = call_user_func($func, $key)) {
				return $data;
			}
		}	
		return null;
	}


	/**
	 * Save value into the cache
	 * @param	mix   	$data	The data to store
	 * @param	string	$name	Name of the cache data
	 * @param	string	$cache_key	Cache key used if data needs to be cached per user or other data
	 * @param	integer	$cache_live	Seconds to let cache live
	 * @param	array 	$use_types	The types of cache to use. See self::$type_avilable
	 * @return	boolean
	 * @internal	Session cache type must be specified
	 */
	public static function save($data, $name, $cache_key=false, $cache_live=86400, $use_types=false) {
		
		if(empty($use_types)) {
			$use_types = self::$types_default;
		}
		
		if(!is_numeric($cache_live)) {
			$cache_live = 86400;
		}

		$key = ($cache_key) ? $name.'_'.$cache_key : $name;
		
		foreach($use_types as $type) {
			$func = "self::save_{$type}";
			if(call_user_func($func, $data, $key, $cache_live)) {
				return true;
			}
		}
		
		return false;
	}

	
	/**
	 * Clear cache entry
	 * @param	string	$name	Name of the cache
	 * @param	string	$cache_key	Set to something if this needs to be different then others of type $name
	 * @return	boolean
	 */
	public static function clear($name, $cache_key=false) {

		$key = ($cache_key) ? $name.'_'.$cache_key : $name;

		$match = array(
			'model' => $name,
			'name' => $key
		);
		
		return DBI::remove('cache_model', $match);
	}
	
	
	/**
	 * Load up cache from database backend
	 * @param	string	$name
	 * @return	mix | false
	 */
	private static function load_database($name) {
	
		$match = array(
			':name' => $name,
			':expires' => date('Y-m-d H:i:s')
		);
		
		$sql = "SELECT data FROM `cache_model` WHERE name= :name AND expires >= :expires LIMIT 1";
		
		$sth = DBI::query($sql, $match);
		
		if(($sth === false) || !$sth->rowCount()) {
			return false;
		}
		
		$value = $sth->fetch(PDO::FETCH_NUM);
		
		return unserialize($value[0]);
	}
	
	
	/**
	 * Load up cache from session backend
	 * @param	mix   	$data	The data to store
	 * @param	string	$name	Name of cache entry
	 * @param	mix   	$data	Data to save
	 * @param	integer	$live	Lifetime of the cache
	 * @return	boolean
	 */
	private static function save_database($data, $name, $live) {
		
		$new_data = array(
			'name' => $name,
			'data' => serialize($data),
			'expires' => date('Y-m-d H:i:s', time()+$live)
		);
			
		return DBI::replace('cache_model', $new_data);
	}
		

	
	private static function memcache_init() {
	
		if(!MEMCACHE_USE) {
			return false;
		}
	
		self::$memcache = new \memcached();
		self::$memcache->addServer(MEMCACHE_HOST, MEMCACHE_PORT);
	
		return true;
	}
		
	
	/**
	 * Load up cache from database backend
	 * @param	string	$name
	 * @return	mix | false
	 */
	private static function load_memcache($name) {
		
		if((self::$memcache == null) && !self::memcache_init()) {
			return false;
		}
		
		return self::$memcache->get($name);
	}	
	
	
	/**
	 * Load up cache from session backend
	 * @param	mix   	$data	The data to store
	 * @param	string	$name	Name of cache entry
	 * @param	mix   	$data	Data to save
	 * @param	integer	$live	Lifetime of the cache
	 * @return	boolean
	 */
	private static function save_memcache($data, $name, $live) {
		
		if((self::$memcache == null) && !self::memcache_init()) {
			return false;
		}
		
		return self::$memcache->set($name, $data, 0 ,$live);
	}
		
	
	/**
	 * Load up cache from session backend
	 * @param	string	$name
	 * @return	mix | false
	 */
	private static function load_session($name) {
		
		if(isset($_SESSION['cache'][$name])) {
			if($_SESSION['cache'][$name]['timeout'] >= time()) {
				return $_SESSION['cache'][$name]['data'];
			}
			
			unset($_SESSION['cache'][$name]);
		}
		
		return false;
	}	
	
	
	/**
	 * Load up cache from session backend
	 * @param	mix   	$data	The data to store
	 * @param	string	$key	Name of cache entry
	 * @param	mix   	$data	Data to save
	 * @param	integer	$live	Lifetime of the cache
	 * @return	boolean
	 */
	private static function save_session($data, $name, $live) {
		$_SESSION['cache'][$name] = array(
			'timeout' => time()+$live,
			'data' => $data
		);
		
		return true;
	}
	
}
