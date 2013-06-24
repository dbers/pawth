<?php
/**
 * User model.
 *
 * User model is used to perform tasks on a user or to fetch data on a user
 *
 * @property    string    $algorithm
 * @package     Model
 */
namespace Model;

use \DBI\MySQL as DBI;

class User {

	private static $algorithm = 'sha256';

	/**
	 * Check if a user id is valid
	 * @param   integer $user_id
	 * @return  boolean
	 */
	public static function is_valid($user_id) {

		$match = array('user_id' => $user_id
		);

		//check for a valid row
		$is_there = DBI::value('users', 'user_id', $match);

		if(!$is_there) {
			return false;
		}

		return true;
	}


	/**
	 * Look up user's id based on email
	 * @param   string  $email  Email address of user
	 * @return  string
	 */
	public static function get_id($email) {

		$match = array();

		$match['email'] = $email;

			//check for a valid row
		$user_id = DBI::value('users', 'user_id', $match);

		if(empty($user_id)) {
			return false;
		}

		return $user_id;
	}


	/**
	 * Look up user's email based on id
	 * @param   integer  $user_id    Users' ID
	 * @return  string
	 */
	public static function get_email($user_id) {

		$match = array('user_id' => $user_id
		);

		return DBI::value('users', 'email', $match);
	}


	/**
	 * create a new user
	 * @param  array $data  An array of new user data to create
	 * @return  boolean
	 */
	public static function create(&$data) {

		//all fields are optional except password and email
		if(empty($data['email']) || empty($data['password'])) {
			Application::log_msg('Missing data in call to User->create(): ' . serialize($data), 1, __FILE__, __LINE__);

			return false;
		}

		//generate one way password
		$salt = self::create_salt($data['email'], $data['password']);
		$pass_enc = self::encrypt_password($data['password'], $salt);

		//expand user data array to 'real' structure data
		$data = array('email' => (string)$data['email'], 'password' => $pass_enc, 'salt' => $salt,
		              'user_status' => 0
		);

		//create basic user account
		$user_id = DBI::insert('users', $data);

		if(!$user_id) {
			Application::log_msg('User->create() call to insert() failed: ' . serialize($data), 1, __FILE__, __LINE__);

			return false;
		}

		return $user_id;
	}


	/**
	 * Update a users account
	 * @param   integer $user_id   The user id of the user that needs the code
	 * @param   array   $data
	 * @return  boolean
	 */
	public static function update($user_id, $data) {

		//we need to replace with the encrypted version
		if(isset($data['password'])) {

			//if they're changing email address then use the new one instead
			$email = self::get_email($user_id);

			$salt = self::create_salt($email, $data['password']);
			$pass_enc = self::encrypt_password($data['password'], $salt);

			$data['password'] = $pass_enc;
			$data['salt'] = $salt;
		}


		//@todo verify fields....
		return DBI::update('users', $data, array('user_id' => $user_id));
	}


	/**
	 * Load user data
	 * @param    string $user_id
	 * @return    array
	 */
	public static function load($user_id) {

		if(!is_numeric($user_id)) {
			return false;
		}

		$match = array('user_id' => $user_id
		);

		return DBI::row('users', $match);

	}


	/**
	 * Check email and password.  Will look up password salt/encryption automaticlly
	 * @param  string $email
	 * @param  string $password
	 * @return  the user id or false
	 */
	public static function check_login_data($email, $password) {

		$user_id = false;

		$match = array('email' => $email
		);
			//need salt first
		$salt = DBI::value('users', 'salt', $match);

		if($salt) {
				//encrypt pass with this users salt
			$pass_enc = self::encrypt_password($password, $salt);
				// see if this email and this encrypted password match
			$match = array('email' => $email, 'password' => $pass_enc
			);
			$user_id = DBI::value('users', 'user_id', $match);
		}

		return $user_id;
	}


	/**
	 * Get the encrypted password
	 * @param  string $password  The plain text password
	 * @param  string $salt      The encryption salt that should be used
	 * @return  string  The encrypted password
	 */
	public static function encrypt_password($password, $salt) {
		return hash(self::$algorithm, $salt . $password . $salt);
	}


	/**
	 * Generate random salt with users email as the salt. Will always be unique even on
	 * @param   string   $email
	 * @param   string   $password
	 * @return  string
	 */
	public static function create_salt($email, $password) {
			// init hash
		$salt_src = md5($email . $password);
			// get random length of salt
		$len = rand(10, 20);
			// get rand start position but make sure its withing $len
		$start = rand(0, strlen($salt_src) - $len);

			//get salt from hash
		$salt = substr($salt_src, $start, $len);

		return $salt;
	}


	/**
	 * Check password reset code
	 * @param    string $email    Email address
	 * @param    string $code     Hash string to check agains
	 * @return    boolean
	 */
	public static function reset_code_check($email, $code) {

		if(empty($code) || empty($email)) {
			return false;
		}

		list($check_email, $check_time) = self::_reset_code_split($code);

		if($check_time < (time() - (60 * 60 * 24 * 7))) { //make sure that timestamp is within X amount of time
			\Core\Errors::add('code', 'old');

			return false;
		}
		$check_code = self::reset_code_create($check_email, $check_time);

		if($check_code != $code) {
			return false;
		}

		//if get to this point then its true
		return true;
	}


	/**
	 * Check password reset code
	 * @param   string  $email       Email address
	 * @param   integer $use_time    Time to force (defaults to current time)
	 * @return  string
	 */
	public static function reset_code_create($email, $use_time=null) {

		if(empty($use_time)) {
			$use_time = time();
		}
			//maybe simply change this to two way encryption
		$hash = md5('s4szglfe[pa;sljt' . $email . $use_time . 'oqy38^1,fa1)!');

		return self::_reset_code_join($email, $use_time, $hash);
	}


	/**
	 * Create encoded data for reset code.  Used internally
	 * @param    string  $email
	 * @param    integer $time
	 * @param    string  $hash
	 * @return    string
	 */
	private static function _reset_code_join($email, $time, $hash) {

		return base64_encode($email . '81Ha2' . $time . '81Ha2' . $hash);
	}


	/**
	 * Get reset codes parts
	 * @param    string $code
	 * @return    array($email, $time, $hash)
	 */
	private static function _reset_code_split($code) {

		list($email, $time, $hash) = explode('81Ha2', base64_decode($code));
			// validate code
		if(empty($email) || empty($time) || empty($hash)) {
			return false;
		}

		if(!is_numeric($time)) {
			return false;
		}

		return array($email, $time, $hash);
	}


	/**
	 * Get user data if visitor is logged in user
	 * @return  mixed
	 */
	public static function get_active_user() {

			//lets see if we are logged in
		if(isset($_SESSION['auth']['user_id'])) {
			return $_SESSION['auth']['user_id'];
		}

		return false;
	}

}



