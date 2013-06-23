<?php
/**
 * Debug class
 *
 * Debugging funcitons for dev
 * 
 * @todo put database debugging functions in here
 *
 * @package	Core
 */
namespace Core;

class Debug {

	/**
	 * Output array
	 * @param	array	$array	The array to output
	 * @internal	Only useable when DEBEUG_LEVEL is set
	 */
	public static function _array($array)  {
		if(DEBUG_LEVEL) {
			echo '<pre style="text-align:left;">';
			print_r($array);
			echo '</pre>';
		}
	}

	
	/**
	 * Output	variable info
	 * @param	mix	$var	Variable to output
	 * @internal	Only useable when DEBEUG_LEVEL is set
	 */
	public static function _var($var)  {
		if(DEBUG_LEVEL) {
			echo '<pre style="text-align:left;">';
			var_dump($var);
			echo '</pre>';
		}
	}  
	
	/**
	 * Output data
	 * @param	string	$str  Data to output
	 * @internal	Only useable when DEBEUG_LEVEL is set
	 */
	public static function _echo($str) {
		if(DEBUG_LEVEL) {
			echo '<pre style="text-align:left;">';
			echo $str;
			echo '</pre>';
		}
	}  
}
