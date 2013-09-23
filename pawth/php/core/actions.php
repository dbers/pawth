<?php
/**
 * Base Action class
 *
 * @param    array  $view_data    An array of data to be proccessed by type specific type
 * @param    string $view_type    Holds the type of return data. (template, json, image, xml,....)
 *
 * @package    Actions
 */
namespace Core;

class Actions {

	private $view_data = array();
	private $view_type = 'template';
	private $view_options = array();

	protected $app = null;
	//holds access level for function
	protected $access_level = array();

	protected $user_id = 0;
	protected $is_guest = true;

	protected $path_argc = 0;
	protected $path_argv = array();
	protected $path_ext = '';


	/**
	 * Init Action class
	 * @param integer $user_id
	 * @param array $path_data
	 */
	final public function __construct($user_id=0, $path_data=array()) {


		if(empty($user_id)) {
			$this->is_guest = true;
			$this->user_id = 0;
		}
		else {
			$this->is_guest = false;
			$this->user_id = $user_id;
		}

		if(isset($path_data['argv'])) {
			$this->path_argv = $path_data['argv'];
		}
		if(isset($path_data['argc'])) {
			$this->path_argc = $path_data['argc'];
		}
		if(isset($path_data['ext'])) {
			$this->path_ext = $path_data['ext'];
		}



		// $this->user_id,     path_data

		$this->app =& $app;
		$this->init();
	}


	/**
	 * Init action. Specific actions can override this as need be
	 * @return    void
	 */
	public function init() {
	}


	/**
	 * Initialize action return data
	 * @param    string $type
	 * @param    mixed    $data
	 * @return    void
	 */
	public function init_return($type, $data=array()) {
		$this->view_type = $type;
		$this->view_data = $data;
	}


	/**
	 * Get return data
	 * @param    string $type
	 * @param    mixed  $data
	 * @param    array  $options
	 * @return    void
	 */
	public function get_return(&$type, &$data, &$options) {
		$type = $this->view_type;
		$data = $this->view_data;
		$options = $this->view_options;
	}


	/**
	 * Set return type
	 * @param    string $type
	 * @param    array  $options
	 * @return    void
	 * @internal    Only should be used by actions that want to
	 * @internal    change json to something like: xml, csv,...
	 */
	public function set_data_type($type, $options = array()) {
		$this->view_type = $type;
		$this->view_options = $options;
	}


	/**
	 * Set return data
	 * @param    mixed $data
	 * @return    void
	 */
	public function set_data($data) {
		$this->view_data = $data;
	}


	/**
	 * Set view options
	 * @param    mixed $options
	 * @return    void
	 */
	public function set_view_options($options) {
		$this->view_options = $options;
	}


	/**
	 * Assign data safely to return data array
	 * @param    string $name    Key name for data
	 * @param    string $value
	 * @return    void
	 */
	public function assign($name, $value) {
		if(!is_array($this->view_data)) {
			$this->view_data = array();
		}

		$this->view_data[$name] = $value;
	}


	/**
	 * Forward all requests for data to the main application class
	 * @param  string $var
	 */
	public function __get($var) {

		return $this->app->{$var};
	}

}



