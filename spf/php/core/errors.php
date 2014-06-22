<?php
/**
 * Error handling
 *
 * Handle user error messages.  This is only meant for errors involving 
 * form submition or access restrictions.  
 * PHP, MySQL, and other system errors should be handled with trigger_error
 *
 * Errots are stored in an array of arrays. array(<field>, <error>)
 * They should containe on alphanumeric characters and underscores
 * Templates use these strings to display the appropiate messages 
 *
 * @param  string  $errors	Holds the error mesages
 * @package    Core
 */
namespace Core;

class Errors {

	private static $errors = array();


	/**
	 * Set an error message.
	 * @param	string  $field  The field/element the error occured on or the error type
	 * @param	string  $error  This is the type of error
	 * @return void
	 */
	public static function add($field, $error) {

		self::$errors[] = array($field, $error);

		return;
	}


	/**
	 * Store errors message to be handled by next request
	 * @return void
	 */
	public static function store() {
		$_SESSION['errors'] = self::$errors;
	}


	/**
	 * Fetch errors message from previous request
	 * @param	boolean	$reset	Switch to clear or save errors after fetching
	 * @param   boolean $format Format array into field=>error format
	 * @return array
	 */
	public static function fetch($reset=true, $format=true) {
		
		if(isset($_SESSION['errors']) && count($_SESSION['errors'])) {
			$e = $_SESSION['errors'];
			if($reset) {
				$_SESSION['errors'] = array();
			}
			return $e;
		}
		
		if(isset(self::$errors) && count(self::$errors)) {
			$e = self::$errors;
			if($reset) {
				self::$errors = array();
			}

			if($format) {
				$index = array();
				foreach($e as $entry) {
					$index[$entry[0]] = ((isset($entry[1])) ? $entry[1] : $entry[0]);
				}

				return $index;
			}

			return $e;

		}
		
		return false;
	}


	/**
	 * Clear existing errors
	 * @return void
	 */
	public static function clear() {
		$_SESSION['errors'] = array();
		self::$errors = array();
	}


	/**
	 * Get the current error list
	 * @param	boolean $reset	Should the errors be cleared after retrieving
	 * @return	array
	 */
	public static function get_all($reset=false) {
		$e = self::$errors;
		if($reset) {
			self::$errors = array();
		}
		return $e;
	}


	/**
	 * Get the last  error
	 * @param   boolean $reset  Should the errors be cleared after retrieving
	 * @param   integer    $error_num  The index spot of the error message
	 * @return	array(<field>, <error>)
	 */
	public static function last($reset=false, $error_num=0) {
		if(!isset(self::$errors[$error_num])) {
			return false;
		}

		$e = self::$errors[$error_num];
		if($reset) { //remove the array from the list
			if($error_num === 0) {
				array_shift(self::$errors);
			}
			else {
					//loop for total -1
				$loop = (count(self::$errors)-1);
				for($x=$error_num; $x<$loop; $x++) {
					self::$errors[$x] = self::$errors[$x+1];
				}
					//remove last element
				array_pop(self::$errors);
			}
		}

		return $e;
	}


	/**
	 * Do we have any errors
	 * @return boolean
	 */
	public static function has() {
		if(count(self::$errors)) {
			return true;
		}
		return false;
	}



}  

	
