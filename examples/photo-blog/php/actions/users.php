<?
/**
 * Users controller
 *
 * This controller is used for all controller actions in regard
 * to users. Creating accounts, logging in/out, changing password,
 * resetting passwords, or anything else that is user account
 * involved but is not related to a users profile.
 *
 * @package     Actions
 */
namespace Actions;
use \Core\Request as Request;
use \Core\Errors as Errors;
use \Core\Actions as Actions;

class Users extends Actions {


	/**
	 * show the registration page
	 */
	public function signup() {

		$this->init_return('redirect', array('url' => '/', 'params' => array('msg' => 'signup-failed')));

		$email = Request::post('email');
		$password = Request::post('password');
		$handle = Request::post('handle');
		$full_name = Request::post('full_name');

			//make sure full name is valid
		if(empty($full_name)) {
			Errors::add('full_name', 'missing');
		}

			//make sure email address is valid
		if(empty($email)) {
			Errors::add('email', 'missing');
		}
		else if(!\Helper\Text::is_email($email)) {
			Errors::add('email', 'invalid');
		}
		else if(\Model\User::get_id($email)) {
			Errors::add('email', 'exists');
		}


		if(empty($handle)) {
			Errors::add('handle', 'missing');
		}
		else if(\Model\Profile::get_id($handle)) {
			Errors::add('handle', 'exists');
		}


		if(empty($password)) {
			Errors::add('password', 'missing');
		} //check password is the minimum length
		else if(strlen($password) < 5) {
			Errors::add('password', 'length');
		}


		//only continue if there were no errors
		if(Errors::has()) {
			return false;
		}

		//build array of new user record
		$new_user = array('email' => $email, 'password' => $password
		);
		//attempt to create new user
		if(!($user_id = \Model\User::create($new_user))) {
			\Core\Application::log_msg('Could not create user: ' . serialize($new_user), 0, __FILE__, __LINE__);
			Errors::add('submit', 'unknown');

			return false;
		}

		$new_profile = array('full_name' => $full_name, 'handle' => $handle, 'display_name' => $full_name);

		//attempt to create new profile entry
		if(!\Model\Profile::create($user_id, $new_profile)) {
			\Core\Application::log_msg('Could not create profile: ' . serialize($new_user), 0, __FILE__, __LINE__);
			Errors::add('submit', 'unknown');

			return false;
		}

		//attempt to create new profile entry
		if(!\Model\Albums::add($user_id, 'default', false)) {
			\Core\Application::log_msg('Could not create default album: ' . serialize($new_user), 0, __FILE__, __LINE__);
			Errors::add('submit', 'unknown');

			return false;
		}

		//attempt to create new profile entry
		if(!\Model\Albums::add($user_id, 'freestyles', false)) {
			\Core\Application::log_msg('Could not create freestyle album: ' . serialize($new_user), 0, __FILE__, __LINE__);
			Errors::add('submit', 'unknown');

			return false;
		}

		//@todo switch to view class system...
		if(\Helper\Email::send($new_user['email'], 'new_user', $new_user)) {
			\Core\Application::log_msg('Could not send join email to: ' . $new_user['email'], 0, __FILE__, __LINE__);
		}

		if(Errors::has()) {
			return false;
		}


		//now auto log them in
		$user_id = \Model\User::check_login_data($email, $password);
		$profile = \Model\Profile::load($user_id);
		$_SESSION['profile'] = $profile;

		//set redirect to edit the profile
		$this->login(true);

		$this->init_return('redirect', array('url' => '/account/setup'));
	}


	/**
	 * This action is used to see if unique user data already exists
	 */
	public function allowed() {


		$email = Request::all('email', false);
		$handle = Request::all('handle', false);

		$return = true;

		if($email && \Model\User::get_id($email)) {
			$return = false;
		}
		else if($handle && \Model\Profile::get_id($handle)) {
			$return = false;
		}

		$this->set_data($return);
		$this->set_view_options(array('no_errors' => true));
	}


	/**
	 *
	 * Process login request or show the use the login page
	 * @param    boolean $internal
	 */
	public function login($internal = false) {

		if(!$internal) {
				//people can log in from multiple places... so make sure redirect for errors got to login page
			$this->init_return('redirect', array('url' => '/', 'params' => array('msg' => 'login-failed')));
		}

		if(Request::post('email') && Request::post('password')) {

			$email = Request::post('email');
			$password = Request::post('password');

			if(!empty($email) && !empty($password)) {
					//	check the credentials
				$user_id = \Model\User::check_login_data($email, $password);

					//make sure valid user id
				if($user_id) {
						//set session data
					$_SESSION = array();
					$_SESSION['auth']['user_id'] = $user_id;
						//change the rest of this request to operate like a member
					$this->is_guest = false;

					//load profile for short cuts into session
					$profile = \Model\Profile::load($user_id);

					//	//make sure all fields that we need are filled out
					//\Model\Profile::set_vars($profile);

					$_SESSION['profile'] = $profile;

						//set last login
					\Model\Profile::update_last_login($user_id);
						//redirect to main page
					//@todo redirect to page loged in from (if on site)

					if($profile['last_active'] == '0000-00-00') {

						if(!$internal) {
							$this->init_return('redirect', array('url' => '/account/setup', 'params' => false));
						}
					}
					else {
						if(!$internal) {
							$this->set_data(array('url' => '/'));
						}
					}

				}
				else {
					Errors::add('submit', 'not_found');
				}
			}
		}
	}


	/**
	 *
	 * Log a user out of the site
	 */
	public function logout() {

		//update last login to the more recent time
		if(is_numeric($this->user_id)) { //saftey check for user_id
			\Model\Profile::update_last_login($this->user_id);
		}

		// destroy auth session
		$_SESSION = array();
		if(isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time() - 42000, '/');
		}
		// Finally, destroy the session.
		session_destroy();
		//@todo add code to re-init session
		$this->is_guest = true;


		$this->set_data_type('redirect');

		$this->init_return('redirect', array('url' => '/', 'params' => array('msg' => 'good-bye')));
	}


	/**
	 * Show the forgot password request form and process submisions
	 */
	public function forgot_password() {

		//they are trying to reset password
		if(Request::post('email') && Request::post('code')) { //user sent in an email address
			//get variables
			$email = Request::post('email');
			$code = Request::post('code');
			$password = Request::post('password');
			$verify_password = Request::post('verify_password');

			//get user id
			$user_id = \Model\User::get_id($email);
			//add reset code
			if(!$user_id) {
				Errors::add('email', 'invalid');

				return;
			}

			//recheck the code
			if(!\Model\User::reset_code_check($user_id, $code)) {
				Errors::add('email', 'invalid');
				Errors::add('code', 'invalid');

				return;
			}

			//check password is the minimum length
			if(strlen($password) < 5) {
				Errors::add('password', 'length');

				return;
			}
			else if($password == $verify_password) {
				Errors::add('password', 'match');

				return;
			}

			//reset the password
			if(!\Model\User::update($user_id, array('password' => $password))) {
				Errors::add('submit', 'unknown');
				\Core\Application::log_msg('Could not reset password for: ' . $user_id, 0, __FILE__, __LINE__);

				return;
			}

			//@todo figure out better way to set subject
			$subject = "Password Reset";
			if(!\Helper\Email::send($email, $subject, 'password_reset', array('code' => $code))) {
				\Core\Application::log_msg('Could not send password reset email to: ' . $email, 0, __FILE__, __LINE__);
			}


			$this->assign('params', array('p' => 1));

			return;
		} //they gave us verification code and email
		else if(Request::post('email')) {

			$email = Request::post('email');

			if(!\Helper\Text::is_email($email)) {
				Errors::add('email', 'invalid');

				return;
			}
			//get user id
			$user_id = \Model\User::get_id($email);
			//add reset code
			if(!$user_id) {
				Errors::add('email', 'invalid');

				return;
			}

			//create the reset code
			$code = \Model\User::reset_code_create($user_id);
			if(!$code) {
				Errors::add('submit', 'unknown');
				\Core\Application::log_msg('Could not generate reset code for user: ' . $user_id, 0, __FILE__, __LINE__);

				return;
			}

			if(!\Helper\Email::send($email, 'forgot_password', array('code' => $code, 'email' => $email))) {
				\Core\Application::log_msg('Could not send password reset code email to: ' . $email, 0, __FILE__, __LINE__);
			}

			$this->assign('params', array('c' => 1));
		}
	}


}
