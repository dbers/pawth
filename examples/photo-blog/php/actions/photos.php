<?
/**
 * Photo Actions
 *
 * Show photos and photo albums 
 * @todo maybe add width/height params.  could just do dash speerated file name
 *
 * @package     Actions
 */
namespace Actions;

use \Core\Request as Request;
use \Core\Errors as Errors;
use \Helper\File as File;
use \Core\Actions as Actions;

class Photos extends Actions {

	
	/**
	 * Upload new photo
	 */
	public function upload() {

		$this->set_data_type('json');
	
		$album_id = Request::all('album_id', false);
		
		if(empty($album_id)) {
			$album = (Request::all('freestyle', false)) ? 'freestyle' : 'default';
			$album_id = \Model\Albums::id($this->user_id, $album);
		}
		
		$album = isset($_SESSION['current_album']) ? $_SESSION['current_album'] :  'default';
		$album_id = isset($_SESSION['current_album_id']) ? $_SESSION['current_album_id'] :  0;

			// upload new photo
		if(isset($_FILES['photo'])) {

			if(isset($_FILES['photo']['name'][0]) && is_array($_FILES['photo']['name'][0])) {
				$data = array();
				foreach($_FILES['photo'] as $k=>$v) {
					$data[$k] = $v[0];	
				}
			}
			else {
				$data = $_FILES['photo'];	
			}

			$caption = Request::post('caption', '');
			$set_main = Request::post('set_main', false);

			// upload the file to a tmp location and check for file errors
			// force it to be converted to jpg and dimensions MAX_PHOTO_WIDTH by MAX_PHOTO_HEIGHT
			if(($error = File::save_image_upload($data, $tmp_file, 'jpg', MAX_PHOTO_WIDTH, MAX_PHOTO_HEIGHT)) !== true) {
				Errors::add('photo', $error);
				return false;
			}
			
			if($photo_id = \Model\Photos::add($this->user_id, $tmp_file, $album_id, $caption)) {
	
				if($set_main) {
					\Model\Photos::set_main($this->user_id, $photo_id);
				}
				
				if(Request::all('get_id', false)) {
					$this->set_data_type('text');
					$this->set_data($photo_id);	
				}
				if(Request::all('jquery_file_upload', false)) {
					$this->set_data_type('text');
					
					$data = array(
						'files' => array(
							array(
								'success' => true,
								'name' => $caption,
								'size' => $data['size'],
								'url' => '/photos/view/'.$this->user_id.'/'.$photo_id.'.jpg',
								'thumbnail_url' => '/photos/view/'.$this->user_id.'/'.$photo_id.'-a70x75.jpg',
								'delete_url' => 'http:\/\/example.org\/files\/picture2.jpg',
								'delete_type' => 'DELETE'
							)
						)
					);

										
					
					
					$this->set_data(json_encode($data));
				}
				else {
					$this->set_data(true);
				}
			}
			else {
				Errors::add('submit', 'unknown');
			}
		}
		else if(isset($GLOBALS["HTTP_RAW_POST_DATA"])){
	
			
				// upload the file to a tmp location and check for file errors
				// force it to be converted to jpg and dimensions MAX_PHOTO_WIDTH by MAX_PHOTO_HEIGHT
			if(($error = File::save_image_webcam($GLOBALS["HTTP_RAW_POST_DATA"], $tmp_file, 'jpg', MAX_PHOTO_WIDTH, MAX_PHOTO_HEIGHT)) !== true) {
				Errors::add('photo', $error);
				return false;		
			}
			
			if($photo_id = \Model\Photos::add($this->user_id, $tmp_file, $album_id)) {
				
				$this->set_data_type('text');
				if(Request::param('photo', false)) {
					$this->set_data(json_encode(array('success'=>true)));
				}
				else {
					$this->set_data($photo_id);
				}
			}		

		}
		else {
			//report error
			Errors::add('photo', 'file_missing');
		}

	}
	
	
	/**
	 * Move a photo to the left
	 */
	public function move_left() {
	
		$album_id = $_SESSION['current_album_id'];
		\Model\Photos::move_left($this->user_id, $album_id, Request::param('id'));
		
		//always redirect back
		$this->assign('url', '/account/photos');
	}

	
	/**
	 * Move a photo to the right
	 */
	public function move_right() {
	
		$album_id = $_SESSION['current_album_id'];
		\Model\Photos::move_right($this->user_id, $album_id, Request::param('id'));

		//always redirect back
		$this->assign('url', '/account/photos');
	}

	
	/**
	 * Edit a photo
	 */
	public function edit() {
	
		$photo_id = Request::post('photo_id', false, true);
		if($photo_id) {
			$caption = Request::post('caption', '');
				
			if(\Model\Photos::edit($this->user_id, $photo_id, $caption)) {
				$this->set_data(true);
			}
			else {
				Errors::add('submit', 'unknown');
			}
		}
		else {
			Errors::add('submit', 'missing_id');
		}
	}
	
	
	/**
	 * Delete a photo
	 */
	public function delete() {
		if(is_numeric(Request::param('photo_id'))) {
			//@todo  probably should have a confirmation page, but i dont care.  maybe just put one on the page using js
			\Model\Photos::remove($this->user_id, Request::param('photo_id'));
		}
		$this->set_data(true);
	}
	
	
	/**
	 * Set a photo as the main photo
	 */
	public function set_main() {
	
		if(\Model\Photos::is_valid($this->user_id, Request::all('id'))) {
			//@todo  probably should have a confirmation page, but i dont care.  maybe just put one on the page using js
			\Model\Photos::set_main($this->user_id, Request::all('id'));
		}
		$this->set_data(true);
	}
	
	/**
	 * Set a photo as the profile photo
	 */
	public function set_banner() {

		if(\Model\Photos::is_valid($this->user_id, Request::all('id'))) {
			//@todo  probably should have a confirmation page, but i dont care.  maybe just put one on the page using js
			\Model\Photos::set_banner($this->user_id, Request::all('id'));
		}
		$this->set_data(true);
	}
	

	/** 
	 * View the physical requested photo 
	 * @return bool
	 */
	public function view() {

		
		$album_id = false;
		$photo_id = false;
		$profile_id = false;
		$profile_file = false;
		$width = 0;
		$height = 0;
		$max_width = 0; 
		$max_height = 0;
		$record_view = false;
		
		
		// this can not be called (currently) with out the correct number of arguments
		// if this is a browse request then this should never of been called..
		if($this->path_argc < 4 || $this->path_argc > 5) {
			\Core\Application::redirect_404(__FILE__, __LINE__);
		}
	
		
		//do a real chop of the path_vars
		//if this is numeric then we are looking at the default album
			
		if(is_numeric($this->path_argv[2])) {
			$album = 'default';
			$profile_id = $this->path_argv[2];
			$profile_file = (isset($this->path_argv[3])) ? $this->path_argv[3] : '';
		}
		else {
			$album = $this->path_argv[2];
			$profile_id = $this->path_argv[3];
			$profile_file = (isset($this->path_argv[4])) ? $this->path_argv[4] : '';
		}
		
		$album_id = \Model\Albums::id($profile_id, $album);
		

		if($this->path_ext != 'jpg') {
			\Core\Application::redirect_404(__FILE__, __LINE__);
		}
		
		$photo_name = $profile_file;
		
			//now we need to see if there are any resizing flags
		$parts = explode('_', $photo_name);
		$photo_id = $parts[0];
		if(isset($parts[1]) && !empty($parts[1])) {
			$type = substr($parts[1], 0, 1);
			if($type == 'm') {  //max width/height
				list($max_width, $max_height) = explode('x', ltrim($parts[1], $type));
			}
			else if($type == 'a') { //actualy width/height
				list($width, $height) = explode('x', ltrim($parts[1], $type));
			}
			else  {
					//send them away
				\Core\Application::redirect_404(__FILE__, __LINE__);
			}
		 
				//check upper and lower bonds of widths and heights 
			if(($width < 0) || ($height < 0) || ($max_width < 0) || ($max_height < 0)) {
				\Core\Application::redirect_404(__FILE__, __LINE__);
			}
			if(($width > MAX_PHOTO_RESIZE) || ($height > MAX_PHOTO_RESIZE) || ($max_width > MAX_PHOTO_RESIZE) || ($max_height > MAX_PHOTO_RESIZE)) {
				\Core\Application::redirect_404(__FILE__, __LINE__);
			}
		}

			//special condition for main profile photo
		if($photo_id == 'main') {
			$photo_id = \Model\Photos::get_main($profile_id);
			$is_main = true;
		}
		else {
			$is_main = false;
		}

		if($photo_id == 'banner') {
			$photo_id = \Model\Photos::get_banner($profile_id);
				// if this is astring then redirect to photo
			if(empty($photo_id)) {
				\Core\Application::redirect('/images/default-banner.jpg', false, __FILE__, __LINE__);
			}
		}
		
			//lets do a quick file name check
		if(!is_numeric($photo_id)) {
			\Core\Application::redirect_404(__FILE__, __LINE__);
		}
		
			//get photo data
		$photo_data = \Model\Photos::load($profile_id, $photo_id);

		if(!$photo_data) {
			\Core\Application::redirect_404(__FILE__, __LINE__);
		}

				//see if we got a string returned,  this is just an image to load up (/images/<file>) ussually for default gender images.
		if(is_string($photo_data)) {
			\Core\Application::redirect('/images/'.$photo_data, false, __FILE__, __LINE__);
		}		//non members can only see the main photo
		else if($this->is_guest && !$is_main) { 
				//302 redirect show the members only photo
			\Core\Application::redirect('/images/members_only.jpg', false, __FILE__, __LINE__);
		} 		  //check photo access restrictions
		/*
		else if($photo_data['network_only'] && ($this->user_id != $profile_id) && !\Model\Network::in_network($this->user_id, $profile_id)) {
				//302 redirct to show the network only photo
			\Core\Application::redirect('/images/network_only.jpg', false, __FILE__, __LINE__);
		}
		*/
			//get users path
			//dont resize main photo
		if($width || $height || $max_width || $max_height) {
				//just send photo data
			$photo_data['file']  = \Model\Photos::get_thumbnail($profile_id, false, $photo_data, $width, $height, $max_width, $max_height);
			if(!$photo_data['file']) {
				\Core\Application::log_msg("could not create thumbnail", 1, __FILE__, __LINE__);
				\Core\Application::redirect_404(__FILE__, __LINE__);
			}
			
			if(($width >= 250) || ($height >= 250) || ($max_width >= 250) || ($max_height >= 250)) {
				$record_view = true;
			}
		}
		else {
			$record_view = true;	
		}
		
			// we need to record view.  We do this if its a full size or at least any dimmension above 250...
		if($record_view) {
			\Model\Photos::record_view($this->user_id, $profile_id, $photo_id);
		}
		
		
		
		//@todo add function into albums model that will get just this data maybe..

			//switch view type  
		$this->set_data_type('Image');
		$this->assign('image', $photo_data);
		return;
	}  

	/**
	 * Add a new album
	 */
	public function add_album() {
		
		$name = Request::post('name');
		if(\Model\Albums::add($this->user_id, $name)) {
			$this->set_data(true);
		}
		
		
	}
	
	/**
	 * Change a photos album
	 */
	public function change_album() {
		
		$photo_id = Request::all('photo_id');
		$album_id = Request::all('album_id');
		
		if(!$album_id) {
				// check for album name
			if($name = Request::all('name')) {
				$album_id = \Model\Albums::id($this->user_id, $name);
			}	
		}
		
			// see if we are still empty
		if(empty($album_id)) {
			Errors::add('album', 'invalid');
			return;	
		}
		
		if(\Model\Photos::change_album($this->user_id, $photo_id, $album_id)) {
			$this->set_data(true);
		}	
		
	}
	
	
}



