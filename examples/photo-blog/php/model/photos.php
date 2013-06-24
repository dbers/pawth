<?php
/**
 * Photos model.
 *
 * This model is used to interact with the users album and photo data
 *
 * @package     Model
 */

namespace Model;

use \DBI\MySQL as DBI;
use \Helper\File as File;

class Photos {


	const NO_PHOTOS = -1;


	/**
	 * Add a new photo
	 * @param	integer	$user_id
	 * @param	string	$tmp_file
	 * @param	integer	$album_id	(Default: 0)
	 * @param	string	$caption	(Default: '')
	 * @return	boolean
	 */
	public static function add($user_id, $tmp_file, $album_id=0, $caption='') {

		if(!is_numeric($user_id) || !is_numeric($album_id)) {
			return false;
		}
		
		if($album_id && !Albums::is_valid($user_id, $album_id)) {
			\Core\Errors::Add('ablum', 'invalid');
			return false;
		}
		else if(!$album_id) {
			$album_id = Albums::id($user_id, 'default');
		}

		
		$path = Profile::get_data_path($user_id, false);

		if(empty($path)) {
			\Core\Errors::add('photo', 'no_path');
			return false;
		}
		else if(!is_dir(DATA_PATH.$path)) {
			\Core\Errors::add('photo', 'invalid_path');
			return false;
		}
			//sanitize text
		\Helper\Text::safe_input($caption);
		
			//use a weight of photo count +1. will always add to end
		$weight = 1 + self::total($user_id, $album_id);

			//create new photo data
		$photo_data = array(
			'user_id' => $user_id,
			'album_id' => $album_id,
			'caption' => (isset($caption)) ? $caption : '',
			'type' => 'jpg',
			'weight' => $weight,
		);
		
			//insert new photo record
		if(!($photo_id = DBI::insert('photos', $photo_data))) {
			\Core\Errors::add('db', 'could not insert');
			return false;
		}
		
			//we've added record and gotten photo id, so safe to move into place
		$photo_name = $path . $photo_id . '.jpg';
		if(!File::move($tmp_file, DATA_PATH.$photo_name)) {
			\Core\Errors::add('photo', 'move');
			return false;
		}
		
		$match = array(
			'user_id' => $user_id,
			'photo_id' => $photo_id
		);
		
			//now that we know the file name/path lets update db (this makes look ups for files quickier)
		DBI::update('photos', array('file'=>$photo_name), $match);
		
		return $photo_id;
	}
	

	/**
	 * Edit a photo's data
	 * @param	integer	$user_id
	 * @param	integer	$photo_id	
	 * @param	string	$caption	Photo caption (Default: '')
	 * @return	boolean
	 */
	public static function edit($user_id, $photo_id, $caption='') {
				//check for valid input
		if(!is_numeric($user_id) || !is_numeric($photo_id)) {
			return false;
		}
		
		\Helper\Text::safe_input($caption);

		$photo_data = array(
			'caption' => $caption
		);
		
		$match = array(
			'user_id' => $user_id,
			'photo_id' => $photo_id
		);
		
		return DBI::update('photos', $photo_data, $match);
	}  


	/**
	 * Remove a users photo
	 * @param	integer	$user_id
	 * @param	integer	$photo_id
	 * @return	boolean
	 */
	public static function remove($user_id, $photo_id) {

			//check for valid input
		if(!is_numeric($user_id) || !is_numeric($photo_id)) {
			return false;
		}

		$photo_data = self::load($user_id, $photo_id);
		if(!$photo_data) {
			\Core\Errors::add('photo', 'invalid');
			return false;
		}
		
		$match = array(
			'user_id' => $user_id,
			'photo_id' => $photo_id
		);
		
			//remove database info
		if(!DBI::remove('photos', $match)){
			return false;
		}
		
			//need to remove $photo_data['file']  AND $photo_data['file']-*
		$expr = dirname($photo_data['file'])."/{$photo_id}*";
		$file_list = glob($expr);
		if($file_list) {
			foreach($file_list as $file) {  
				if(@unlink($file) === FALSE) {
					Application::log_msg("Unable to delete {$file}", 0, __FILE__, __LINE__);
				}
			}
		}

		return true;
	}

	
	/**
	 * Set an image as the main profile photo
	 * @param	integer	$user_id
	 * @param  integer  $photo_id
	 * @return  true | false
	 */
	public static function set_main($user_id, $photo_id) {
		
		if(!is_numeric($user_id) || !is_numeric($photo_id)) {
			return false;
		}
		
		$match = array(
			'user_id' => $user_id,
			'is_main' => 1
		);
		
			//make sure only one photo is set as main photo
		DBI::update('photos', array('is_main' => 0), $match);
		
		$match = array(
			'user_id' => $user_id,
			'photo_id' => $photo_id
		);
				
		return DBI::update('photos', array('is_main' => 1), $match);
	}
	
	
	/**
	 * Set an image as the profile banner
	 * @param	integer	$user_id
	 * @param	integer  $photo_id
	 * @return	boolean
	 */
	public static function set_banner($user_id, $photo_id) {
	
		if(!is_numeric($user_id) || !is_numeric($photo_id)) {
			return false;
		}
	
		$match = array(
				'user_id' => $user_id,
				'photo_id' => $photo_id
		);
	
		return DBI::update('photos', array('is_banner' => 1), $match);
	}
	
	
	/**
	 * Unset an image as the profile banner
	 * @param	integer	$user_id
	 * @param	integer  $photo_id
	 * @return	boolean
	 */
	public static function unset_banner($user_id, $photo_id) {
		
		if(!is_numeric($user_id) || !is_numeric($photo_id)) {
			return false;
		}
		
	
		$match = array(
			'user_id' => $user_id,
			'photo_id' => $photo_id
		);
		
		return DBI::update('photos', array('is_main' => 0), $match);
	}
	

	/**
	 * Get users main profile photo (or get randome profile photo)
	 * @param	integer	$user_id
	 * @return	integer
	 */
	public static function get_main($user_id) {

		if(!is_numeric($user_id)) {
			return false;
		}
		
		$match = array(
			'user_id' => $user_id,
			'is_main' => 1
		);		
		
		$photo_id = DBI::value('photos', 'photo_id', $match);
		
		if(!$photo_id) {
				//@todo make sure this order by rand() is not a performance hit
			$match = array(
				'user_id' => $user_id
			);
			
			if(!($photo_id = DBI::value('photos', 'photo_id', $match))) {
					//this must mean that they do not have any photos. lets send back -1 so we know to fetch the default image
				$photo_id = self::NO_PHOTOS;
			}
		}
		
		return $photo_id;
	}
	
	
	/**
	 * Get users main profile banner (or get default)
	 * @param	integer	$user_id
	 * @return	array
	 */
	public static function get_banners($user_id) {
	
		if(!is_numeric($user_id)) {
			return false;
		}
	
		$match = array(
			'user_id' => $user_id,
			'is_banner' => 1
		);
	
		return DBI::columns('photos', array('photo_id', 'type'), $match);
	}
	
	/**
	 * Change a photo's album
	 * @param	integer	$user_id
	 * @param	integer	$photo_id
	 * @param	string	$album_id
	 * @return	boolean
	 */
	public static function change_album($user_id, $photo_id, $album_id) {

			//check for valid input
		if(!is_numeric($user_id) || !is_numeric($photo_id) || !is_numeric($album_id)) {
			return false;
		}
		
		if(!Albums::is_valid($user_id, $album_id)) {
			\Core\Errors::add('album', 'invalid');
			return false;
		}

		$data = array(
			'album_id' => $album_id
		);
		
		$match = array(
			'user_id' => $user_id,
			'photo_id' => $photo_id
		);

		return DBI::update('photos',  $data, $match);
	}


	/**
	 * Move photo to the left
	 * @param	integer	$user_id
	 * @param	integer	$album_id
	 * @param	integer	$photo_id
	 * @return	boolean
	 */
	public static function move_left($user_id, $album_id, $photo_id) {
			//check for valid input
		if(!is_numeric($photo_id)) {
			return false;
		}

			//get all photos in current order
		$photos = self::get_all($user_id, $album_id, false, false);

		if(!$photos) {
			return false;
		}

			//make sure not first
		if($photos[0]['photo_id'] == $photo_id) {
			return false;
		}

		$total = count($photos);
		$found = true;
		for($x=1; $x<$total; $x++) {
			if($photos[$x]['photo_id'] == $photo_id) {
				 //the weight and id of photo to the left
				$left_photos_weight = $photos[$x]['weight'];
				$left_photos_id = $photos[($x-1)]['photo_id'];
					//this photos new weight
				$move_photo_weight = $photos[($x-1)]['weight'];
				$found = true;
				break;
			}
		}
		
		if(!$found) {
			\Core\Errors::add('photo', 'invalid');
			return false;
		}
			//this condition should no longer be needed. leave in case situation happens again in creating inital weight for photos
		if($move_photo_weight == $left_photos_weight) {
				//make weight one bigger
			$left_photos_weight += 1;
				//update all in database so we no there will be no colitions
			DBI::query("UPDATE photos SET weight=weight+2 WHERE user_id={$user_id} AND photo_id!={$photo_id} AND photo_id!={$left_photos_id} AND weight>={$move_photo_weight}");
			
		}
		
			//move photo 1
		$data1 = array(
			'weight' => $move_photo_weight
		);
		$match1 = array(
			'user_id' => $user_id,
			'album_id' => $album_id,
			'photo_id' => $photo_id
		);
		
			//move photo 2
		$data2 = array(
			'weight' => $left_photos_weight
		);
		$match2 = array(
			'user_id' => $user_id,
			'album_id' => $album_id,
			'photo_id' => $left_photos_id
		);

		$ret1 = DBI::update('photos', $data1, $match1);
		$ret2 = DBI::update('photos', $data2, $match2);
		
		return ($ret1 && $ret2);
	}

	
	/**
	 * Move album photo to the right
	 * @param	integer	$user_id
	 * @param	integer	$album_id
	 * @param	integer	$photo_id
	 * @return	boolean
	 */
	public static function move_right($user_id, $album_id, $photo_id) {
			//check for valid input
		if(!is_numeric($user_id) || !is_numeric($album_id) || !is_numeric($photo_id)) {
			return false;
		}

			//get all photos in current order
		$photos = self::get_all($user_id, $album_id, false, false);

		if(!$photos) {
			return false;
		}

			//get total photos
		$total = count($photos);
		
			//make sure not last
		if($photos[($total-1)]['photo_id'] == $photo_id) {
			return false;
		}
		
		$found = true;
		for($x=($total-2); $x>=0; $x--) {
			if($photos[$x]['photo_id'] == $photo_id) {
				 //the weight and id of photo to the left
				$right_photos_weight = $photos[$x]['weight'];
				$right_photos_id = $photos[($x+1)]['photo_id'];
					//this photos new weight
				$move_photo_weight = $photos[($x+1)]['weight'];
				$found = true;
				break;
			}
		}
		
		if(!$found) {
			return false;
		}
		
	
			//this condition should no longer be needed. leave in case situation happens again in creating inital weight for photos
		if($move_photo_weight == $right_photos_weight) {
			 //make weight one bigger
			$right_photos_weight -= 1;
			 //update all in database so we no there will be no colitions
			DBI::query("UPDATE photos SET weight=weight-2 WHERE user_id={$user_id} AND photo_id!={$photo_id} AND photo_id!={$right_photos_id} AND weight<={$move_photo_weight} AND weight!=0");  
		}
		
			//move photo 1
		$data1 = array(
			'weight' => $move_photo_weight
		);
		$match1 = array(
			'user_id' => $user_id,
			'album_id' => $album_id,
			'photo_id' => $photo_id
		);
		
			//move photo 2
		$data2 = array(
			'weight' => $right_photos_weight
		);
		$match2 = array(
			'user_id' => $user_id,
			'album_id' => $album_id,
			'photo_id' => $right_photos_id
		);

		$ret1 = DBI::update('photos', $data1, $match1);
		$ret2 = DBI::update('photos', $data2, $match2);
		
		return ($ret1 && $ret2);
	}


	/**
	 * Get a users photo count
	 * @param	integer	$user_id
	 * @param	integer	$album_id	Limit total to specific album
	 * @return	integer
	 */
	public static function total($user_id, $album_id=false) {
			//check for valid input
		if(!is_numeric($user_id) || ($album_id && !is_numeric($album_id))) {
			return false;
		}
			//at this point this is either false or an integer
		$match = array(
			'user_id' => $user_id
		);
		
		if($album_id !== false) {
			$match['album_id'] = $album_id;
		}
		
		$total = DBI::total('photos', $match);

		return $total;
	}

	
	/**
	 * See if a given photo is valid
	 * @param	integer	$user_id 
	 * @param	integer	$photo_id
	 * @param	integer	$album_id  (default: false)
	 * @return	boolean
	 */
	public static function is_valid($user_id, $photo_id, $album_id=false) {
		return (self::load($user_id, $photo_id, $album_id) ? true : false);
	}  
	
	
	/**
	 * Get the data for a photo
	 * @param	integer	$user_id 
	 * @param	integer	$photo_id
	 * @param	integer	$album_id	(default: false)
	 * @return  false | array
	 */
	public static function load($user_id, $photo_id, $album_id=false) {
		
		if(!is_numeric($user_id) || !is_numeric($photo_id)) {
			return false;
		}
		
		if($album_id && !is_numeric($album_id)) {
			return false;
		}
			
			//they dont have any photos, get the generic default image
		if($photo_id == self::NO_PHOTOS) {
			$match = array(
				'user_id' => $user_id
			);
			$gender_id = DBI::value('profiles', 'gender', $match);
			if($gender_id) {
				$gender = Profile::option_text($gender_id);
			}
			if(empty($gender)) {
				$gender = 'male';
			}
			$photo_data = 'default_'.strtolower($gender). '.jpg';
		}
		else {
			$data = array(
				'user_id' => $user_id,  
				'photo_id' => $photo_id
			);
			
			if($album_id !== false) {
				$data['album_id'] = $album_id;
			}
		
			$photo_data = DBI::row('photos', $data);
				
			if(!$photo_data) {
				return false;
			}
				
			$photo_data['file'] =  DATA_PATH . $photo_data['file'];
		}
		
		return $photo_data;
	}  
	
	
	/**
	 * Get all photo data for a user
	 * @param	integer	$user_id
	 * @param	integer	$album_id	(default: 0)
	 * @param	integer	$start   	(default: 0)
	 * @param	integer	$count   	(default: 20)
	 * @param	string	$order   	
	 * @return	array
	 */
	public static function get_all($user_id, $album_id=false, $start=0, $count=20, $order=false) {
		
		if(!is_numeric($user_id) || ($album_id && !is_numeric($album_id))) {
			return false;
		}
		
		switch($order) {
			case 'popular' : {
				$order_by = 'views DESC';
				break;
			}
			case 'recent' : {
				$order_by = 'created DESC';
				break;
			}
			case 'oldest' : {
				$order_by = 'created ASC';
				break;
			}
			case 'weight' : 
			default : {
				$order_by = 'weight ASC';
				break;
			}
			
		}
		
		$data = array('user_id' => $user_id);
		
		if($album_id !== false) {
			$data['album_id'] = $album_id;
		}
		
		//@todo add fetch all mthods...
			//get all rows
		$photos = DBI::rows('photos', $data, $start, $count, $order_by);
		
		if(!$photos) {
			return false;
		}
		
		$count = count($photos);

		return $photos;
	}  
	
	
	/**
	 * Get most popular photos for a user
	 * @param	integer	$user_id
	 * @param	integer	$album_id	(default: 0)
	 * @param	integer	$start   	(default: 0)
	 * @param	integer	$count   	(default: 20)
	 * @return	array
	 */
	public static function popular($user_id, $album_id=false, $start=0, $count=20) {
		// structure may change in the future. for now use a wrapper funtion
		return self::get_all($user_id, $album_id, $start, $count,  'popular');
	}
	
	/**
	 * Get most popular photos for a user
	 * @param	integer	$user_id
	 * @param	integer	$album_id	(default: 0)
	 * @param	integer	$start   	(default: 0)
	 * @param	integer	$count   	(default: 20)
	 * @return	array
	 */
	public static function recent($user_id, $album_id=false, $start=0, $count=20) {
			// structure may change in the future. for now use a wrapper funtion
		return self::get_all($user_id, $album_id, $start, $count,  'recent');
	}
	
	
	/**
	 * Get a samller verison of a thumbnail
	 * @param	integer	$user_id
	 * @param	integer	$photo_id
	 * @param	array	$photo_data
	 * @param	integer	$width
	 * @param	integer	$height
	 * @param	integer	$max_width
	 * @param	integer	$max_height
	 */
	public static function get_thumbnail($user_id, $photo_id=false, $photo_data=false, $width=0, $height=0, $max_width=0, $max_height=0) {
		
		if(!is_numeric($user_id)) {
			return false;
		}
		
		if($photo_id && is_numeric($photo_id)) {
			$photo_data = self::load($user_id, $photo_id);
		}
	
				
		if(!$photo_data) {
			return false;
		}
		
			//make sure variables exists
		if(!isset($width)) {
			$width = 0;
		}
		if(!isset($height)) {
			$height = 0;
		}
		if(!isset($max_width)) {
			$max_width = 0;
		}
		if(!isset($max_height)) {
			$max_height = 0;
		}
		
			//special condition to handle default images (no sense creating multiple thumbnails of same image for each member)
		if($photo_data['photo_id'] == self::NO_PHOTOS) {
			$new_file = DATA_PATH.basename($photo_data['file'])."_{$width}-{$height}-{$max_width}-{$max_height}.{$photo_data['type']}";
		}
		else {
			$new_file = dirname($photo_data['file']).'/'.$photo_data['photo_id']."_{$width}-{$height}-{$max_width}-{$max_height}.{$photo_data['type']}";
		}
		
		if(file_exists($new_file)) {
				//use cached file
			return $new_file;
		}
		
			//get the resized image
		if(!File::resize_image($photo_data['file'], $new_file, $photo_data['type'], $width, $height, $max_width, $max_height)) {
				//quit now if there was an issue
			return false;
		}

		return $new_file;
	}

	
	/**
	 * Record a photo view
	 * @param	integer	$user_id
	 * @param	integer	$profile_id
	 * @return	void
	 */
	public static function record_view($user_id, $profile_id, $photo_id) {
	
		if(!is_numeric($user_id) || !is_numeric($profile_id) || !is_numeric($photo_id)) {
			return false;
		}
		
		if(!self::is_valid($profile_id, $photo_id)) {
			return false;	
		}
		
			//only record 1 view a login session
		if(!isset($_SESSION['photo_visit'][$profile_id][$photo_id])) {
				
			$data = array(
				'user_id' => $user_id,
				'profile_id' => $profile_id,
				'photo_id' => $photo_id,
				'visited' => date('Y-m-d')
			);
			DBI::replace('photo_views', $data);
			
				// now update quick count column
			$match = array(
				'user_id' => $profile_id,
				'photo_id' => $photo_id		
			);
			
			DBI::increment('photos', 'views', $match, 1);
			
			$_SESSION['photo_visit'][$profile_id][$photo_id] = true;
		}
	}
	
	
	
	/**
	 * Get most recent photos of friends
	 * @param	integer	$user_id
	 * @param	integer	$start
	 * @param	integer	$count
	 */
	public function people_recent($user_id, $start=0, $count=10) {
		
		if(!is_numeric($user_id) || !is_numeric($start) || !is_numeric($count)) {
			return false;	
		}
		
		$match = array(
			':user_id' => $user_id
		);
				
		$sql = 'SELECT p.photo_id, p.user_id as profile_id, p.type FROM photos p, mypeople m WHERE m.user_id=:user_id AND m.profile_id=p.user_id ORDER BY created DESC LIMIT '.$start.','.$count;

		$sth = DBI::query($sql, $match);
		
		if(($sth === false) || !$sth->rowCount()) {
			return false;
		}
		$rows = $sth->fetchAll(\PDO::FETCH_ASSOC);
		
		Profile::quick_view($user_id, $rows);
		return $rows;
	}
	
	
}










