<?php
/**
 * Application class.
 *
 * This class contains all basic functionality that controls the application
 *
 * @property    array   $path_data  Array of path data (argv, argc, url, ext)
 * @property    mixed   $user
 * @property    boolean $is_ajax
 * @property    string  $request_method
 * @property    mixed   $user_func
 * @package Core
 */
namespace Core;

use \View\View as View;
use \Core\Errors as Errors;

final class Application {

	private $path_data = null;
	private $request_method = false;
	private $is_ajax = false;
	private $user = false;

	private $user_func = null;

	/**
	 * Constructor for class.
	 */
	function __construct() {

		$this->path_data = array(
			'argv' => array(),
			'argc' => 0,
			'ext' => false,
			'url' => ''
		);

		$this->request_method = strtolower($_SERVER['REQUEST_METHOD']);

			// init user function, can be overridden in index.php
		$this->user_func = function() {
			return false;
		};
	}


	/**
	 * Runs the application
	 */
	public function run() {
			//see if this is an ajax request
		$this->is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'xmlhttprequest')) ? true : false;

			//parse path data
		$this->parse_path();

			//load up user (if possible)
		if(is_callable($this->user_func, false)) {
			$this->user = call_user_func($this->user_func);
		}

			//check permissions
		if($this->check_access() == false) {
			if($this->is_ajax) {
				$this->view_render('json', 'invalid_access');
				return;
			}
			else {
				Application::redirect('/', false, __FILE__, __LINE__);
				return;
			}
		}

		$data_type = 'template';


			//run (possible) action
		$this->run_action($data_type, $data, $options);

		switch(strtolower($data_type)) {
			case 'redirect' : {
				if(empty($data['url'])) {
					trigger_error("Invalid redirect request", E_USER_ERROR);
				}
				if(empty($data['params'])) {
					$data['params'] = false;
				}
					//if we have any errors, lets save them for the next session
				if(Errors::has()) {
					Errors::store();
				}

					//store posted data for later use
				if($this->request_method == 'post') {
					Request::store();
				}

				Application::redirect($data['url'], $data['params'], __FILE__, __LINE__);
				break;
			}
			case 'json' : {

				if(Errors::has() && !(isset($options['no_errors']) && $options['no_errors'])) {
						//add errors to data array
					$data['errors'] = Errors::get_all();
				}
				break;
			}
			case 'template' : {
					// set basic data
				$data['path_data'] = $this->path_data;
				$data['user'] = $this->user;
				break;
			}
			case 'xml' : {
				$data_type = 'xml';
				break;
			}
			case 'text' : {
				$data_type = 'text';
				break;
			}
			case 'image' : {
				break;
			}
			case 'audio' : {
				break;
			}
			case 'video' : {
				break;
			}
			default : {
				Application::log_msg('invalid data type: ' . $data_type . ' ' . print_r($data, true), 2, __FILE__, __LINE__);
			}
		}

			//render the view
		$this->view_render($data_type, $data, $options);

		return;
	}


	/**
	 * Render the loaded view
	 * @param   string  $data_type    View type to render
	 * @param   mixed   $data         Data to render
	 * @param   array   $options
	 * @return  object
	 */
	public function view_render($data_type, $data, $options=array()) {

		$view = View::create($data_type, $data, $options);
		return $view->render();
	}


	/**
	 * Parse extra path info to see what page and/or action we should be using
	 */
	public function parse_path() {
		global $link_alias;

		if($d = Request::param('d', false)) {
				// get rid of any leading or trailing slashes
			$our_path = trim($d, '/');

				//see if we have a link alias
			if(isset($link_alias[$our_path])) {
				$our_path = $link_alias[$our_path];
			}

				// replace dashes with underscores
			$our_path = str_replace('-', '_', $our_path);

				//special check cause of shitty flash that does relative calls for service.xml
			$t = explode('/', $our_path);
			if(array_pop($t) == 'service.xml') {
				self::redirect('/service.xml');
			}

				//see if url has an extension on it
			$parts = explode('.', $our_path);

			if(isset($parts[1])) {
				$this->path_data['ext'] = $parts[1];
					//get path with out extension
				$our_path = $parts[0];
			}

			$this->path_data['argv'] = explode('/', strtolower($our_path));
				// save arg count for easy use later
			$this->path_data['argc'] = count($this->path_data['argv']);

				// validate path_argv
			foreach($this->path_data['argv'] as $part) {
				if(!preg_match('/^[\w-]+$/', $part)) {
					Application::redirect_404(__FILE__, __LINE__);
				}
			}

		}
			// save 'clean' request path for use later
		$this->path_data['url'] = '/' . implode('/', $this->path_data['argv']);

		return;
	}


	/**
	 * Run requested action
	 * @param    string $data_type
	 * @param    array  $data
	 * @param    array  $options
	 * @return  boolean
	 */
	public function run_action(&$data_type, &$data, &$options) {


		// if we are 'post' or 'get' then we want to add data or get data.
		// however, 'get' method must be sent through ajax

		if(($this->request_method == 'post') || (($this->request_method == 'get') && $this->path_data['ext'])) {

			$name = $this->path_data['argv'][0];
			$method = $this->path_data['argv'][1];

			$action_class_name = 'Actions\\' . ucfirst($name) ;
			$action_class_file = PHP_PATH . "actions/{$name}.php";

				//see if file exists before including it
			if(!file_exists($action_class_file)) {
				Application::redirect_404(__FILE__, __LINE__);
			}
				//include action class
			require(PHP_PATH . "core/actions.php");
			require($action_class_file);


				//get instance of action
			$action = new $action_class_name($this->user, $this->path_data);

				//see if requested action exists
			if(!method_exists($action, $method)) {
				Application::redirect_404(__FILE__, __LINE__);
			}

			$action->init_return('json');

				//run the action
			$action->{$method}();
				//get return type/data (its 'possible' for type to change)
			$action->get_return($data_type, $data, $options);

				//action ran
			return true;
		}

			//no action ran
		return false;
	}


	/**
	 * Check if this is an internal or external request
	 * @return  boolean
	 */
	public function is_internal() {
		return (($this->user) ? true : false);
	}


	/**
	 * Check if this user has permissions to use this function
	 * @return    boolean
	 */
	private function check_access() {
		global $guests_allowed, $users_forbidden;

		if($this->path_data['argc'] > 1) {
			$base_url = $this->path_data['argv'][0] . '/' . $this->path_data['argv'][1];
		}
		else if($this->path_data['argc'] == 1) {
			$base_url = $this->path_data['argv'][0];
		}
		else {
			$base_url = '/';
		}

		//lets see if the current page is allowed for guests
		if($this->is_internal()) {
			if(isset($users_forbidden) && isset($users_forbidden[$base_url])) {
				return false;
			}
		}
		else {
			if(isset($guests_allowed) && !isset($guests_allowed[$base_url])) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Log a message
	 * @param string $msg    The message to log
	 * @param string $level  The log level for this message
	 * @param string $file   The file the error happen in
	 * @param string $line   The line number where the error happen
	 * @return    void
	 */
	public static function log_msg($msg, $level, $file, $line) {

		if(LOG_LEVEL >= $level) {
			error_log(date('Y-m-d H:i:s') . "\t{$file}\t{$line}\n{$msg}\n", 3, LOG_FILE);
		}

		return;
	}


	/**
	 * Redirect to the file not found page. This is a convenience method
	 * that should be used in case the page and/or actions change
	 * @param    string  $debug_file    The file that called this function    (Default: false)
	 * @param    integer $debug_line    The line of code where this function was called from (Default: false)
	 * @return    void
	 */
	public static function redirect_404($debug_file=null, $debug_line=null) {
		//@todo log 404 error (maybe log in the not_found method of pages)

		$path = Request::param('d', '[unknown]');

		error_log("404 redirect for request {$path} in {$debug_file} on line {$debug_line}\n", 3, LOG_FILE);

		self::redirect('/not-found', false, $debug_file, $debug_line);

		return;
	}


	/**
	 * Redirect a user to their previous page.  If non exists, send to $fall_back
	 * @param    string  $fall_back     URL to send them to if no referring url exists (default: /)
	 * @param    string  $debug_file    The file that called this function    (Default: false)
	 * @param    integer $debug_line    The line of code where this function was called from (Default: false)
	 * @return    void
	 */
	public static function redirect_referring($fall_back = '/', $debug_file=null, $debug_line=null) {

		if(empty($_SERVER['HTTP_REFERER'])) {
			$url = (empty($fall_back)) ? '/' : $fall_back;
		}
		else {
			$url = $_SERVER['HTTP_REFERER'];
		}

		self::redirect($url, false, $debug_file, $debug_line);
	}


	/**
	 * Redirect to the user to another page. This is a convenience method
	 * that makes sure the 'exit;' is called after the header output so spiders
	 * don't bypass security redirects
	 * @param    string  $url           The url target to send the user
	 * @param    array   $params        Any parameters that needed to be appended to the URL
	 * @param    string  $debug_file    The file that called this function    (Default: false)
	 * @param    integer $debug_line    The line of code where this function was called from (Default: false)
	 * @return    void
	 */
	public static function redirect($url, $params=null, $debug_file=null, $debug_line=null) {

		if(is_array($params) && count($params)) {
			$append = array();
			foreach($params as $field => $value) {
				$append[] = $field . '=' . urlencode($value);
			}
			if(strpos($url, '?') === false) {
				$url = $url . '?' . implode('&', $append);
			}
			else {
				$url = $url . '&' . implode('&', $append);
			}
		}


		//for debugging
		if(DEBUG_LEVEL && Request::param('no_redirect', false)) {
			Debug::_echo("canceled redirect to '$url' from: {$debug_file} at {$debug_line}");
			if(Request::param('redirect_exit', false)) {
				exit;
			}

			return false;
		}

		header('Location: ' . $url);
		exit;
	}


	/**
	 * Forward all requests for data to the main application class
	 * @param  string $var
	 */
	public function __get($var) {

		return $this->{$var};
	}


	/**
	 * Set user access function. Should return data that is boolean true when valid user is logged in
	 * @param   closure $func
	 */
	public function set_user_func($func) {
		$this->user_func = $func;
	}



}


