<?php
/**
 * View class
 *
 * Main view class that all others inherent from and provides basic factory methods
 *
 * @param	array	$data	Array of view variables to make accessible to view_display functions
 * @param	array	$errors	Array of error messages for the view
 * @param	array	$extra
 * @param	boolean	$is_internal    True if application is handling an authenticated user
 * @param	string	$layout_type	The type of layout to use for templates
 * @package    View
 */
namespace View;

use \Core\Errors as Errors;

abstract class View {
	
		//hold reference to controller
	protected $data = array();
	protected $errors = array();
	protected $extra = array();
	protected $is_internal = false;

	/**
	 * Class constructor
	 * @internal	Must use 'create' function
	 */
	private function __construct() {
		
	}

	
	/**
	 * Run the init procedures
	 * @param	array 	$data 	Array of date (default: array())
	 * @param	array 	$extra	Optional layout for the view to use (default: false)
	 */
	public function init($data=array(), $extra=array()) {

			//get a copy of the view data from the controller class 
		$this->data = $data;
		$this->extra = $extra;
		$this->errors = Errors::fetch(true);
		$this->is_internal = (isset($data['user']) && $data['user']) ? true : false;
		
	}


	/**
	 * Run this before rendering display
	 */
	public function pre_display() {
	}

	
	/**
	 * Run this after rendering display
	 */    
	public function post_display() {
	}

	
	/**
	 * Return an instances of the view system
	 * @param	string	$type 	View type to create
	 * @param	array 	$data 	Array of date (default: array())
	 * @param	array 	$extra	Optional extra data for the view to use (default: array())
	 * @return	object
	 * @static
	 * @public
	 */
	public static function create($type, $data=array(), $extra=array()) {

		$path = PHP_PATH . 'view/'. strtolower($type) .'.php';
		if(!file_exists($path)) {
			\Core\Application::log_msg("Invalid view requests: $type", 1, __FILE__, __LINE__);
			return false;	
		}
			//include class
		require_once($path);
		
		$view_name = 'View\\' . ucfirst($type);

		$view = new $view_name();
			//run the view init function
		$view->init($data, $extra);
	
		return $view;
	}
	
	
	/**
	 * Render the view class
	 * @return	boolean
	 */
	final public function render() {
		
		//render the display
		$this->pre_display();
		
		$ret = $this->display();
		
		$this->post_display();
		
		return $ret;
	}
	
	
	/**
	 * Render display to user
	 * the display method that must be overwritten by all child classes
	 */
	abstract public function display();


}  

	
