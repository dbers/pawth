<?
/**
 * Helper Text class.
 *
 * This function contains method for validating user input format and cleaning text:
 *
 * @package	Helper
 */
namespace Helper;

class Text {


	/**
	 * Validate an email address
	 * @param	string	$input
	 * @return	boolean
	 */
	public static function is_email($input) {
		if(preg_match('/^[-_a-z0-9]+(\.[-_a-z0-9]+)*@[-a-z0-9]+(\.[-a-z0-9]+)*\.[a-z]{2,6}$/', $input) <= 0) {
			return false;
		}

		return true;
	}


	/**
	 * Validate that input is only alpanum plus underscore
	 * @param	string	$input
	 * @param	boolean	$allow_spaces
	 * @return	boolean
	 */
	public static function only_words($input, $allow_spaces=false) {

		//@note	Doing a quick and cheap of compare... should change later
		if($allow_spaces) {
			if(self::clean_text($input, array('-', '_', ' ')) != $input) {
				return false;
			}
		}
		else {
			if(self::clean_text($input, array('-', '_')) != $input) {
				return false;
			}
		}

/*
		if($allow_spaces) {
			if(preg_match('/^(\w,\s)+$/',  $input) <= 0) {
				return false;
			}
		}
		else {
			if(preg_match('/^(\w,\s)+$/',  $input) <= 0) {
				return false;
			}
		}
*/
		return true;
	}


	/**
	 * Make users supplied data safe for manipulations. This will strip tags and entity encode html chars.
	 * @param	string  &$data  Pass by reference string
	 * @return	void
	 */
	public static function safe_input(&$data) {
			//strip out ALL html tags
		$data = strip_tags($data);
			//encode html entities but make sure its not double encoded
		$data = htmlentities($data, ENT_QUOTES, "UTF-8", FALSE);
		return;
	}


	/**
	 * Use these function to strip all characters except alphanumeric and some special exemptions
	 * @param	string	$string
	 * @param	array	$allowed  Array of characters that are allowed (default: '-', '_', ' ')
	 * @return	string
	 */
	public static function clean_text($string, $allowed = array('-', '_', ' ')) {

			//first strip any html
		$string = strip_tags($string);
			//allow these characters in addition to alphanumeric (maybe make configurable through function options)

		$new_string = '';
		$len = strlen($string);

		for($i=0; $i<$len; $i++) {
			if(ctype_alnum($string[$i])  || in_array($string[$i], $allowed)) {
				$new_string .= $string[$i];
			}
		}

		return $new_string;
	}


}



