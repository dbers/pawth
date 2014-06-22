<?php
/**
 * File manipulation class
 * 
 * Static methods used for manipultating files
 *
 * @package	Helper
 */
namespace Helper;

class File {

	/**
	 * Save an uploaded file to a temporary place
	 *
	 * @param	array	$file_data  Specific $_FILE data for this 1 file
	 * @param	string	&$new_file	The location of the new uploade dfile
	 * @param	string	$format
	 * @param	integer	$max_width
	 * @param	integer	$max_height
	 *
	 * @return	string
	 */
	public static function save_image_upload($file_data, &$new_file, $format=null, $max_width=null, $max_height=null) {

		$new_file = null;
		
		$err = self::upload_error($file_data);
			//see if there was an upload error
		if($err) {
			return $err;
		}  //make sure this is a valid file
		else if(!is_uploaded_file($file_data['tmp_name'])) {
			return 'invalid';
		}

		$tmp_file = TMP_FILE_DIR . uniqid(rand());
		
		if(!move_uploaded_file($file_data["tmp_name"], $tmp_file)) {
			return 'move_error';
		}

			//lets make sure that this is an actual image
		if((list($width, $height, $type_number) = @getimagesize($tmp_file)) === FALSE) {
			return 'invalid';
		}
		
			//make sure we have a valid image type
		switch($type_number) {
			case 1 : $type = 'gif'; break;
			case 2 : $type = 'jpg'; break;
			case 3 : $type = 'png'; break;
			case 6 : $type = 'bmp'; break;
			default: {
				return 'format';
			}
		}

			//see if we need to convert the image
		if($format || $max_width || $max_height) {
			
				//need to convert type as well (first)
			if($format != $type) {
				if(!self::convert_image($tmp_file, $type, $tmp_file, $format) || !file_exists($tmp_file)) {
					self::remove($tmp_file);
					return 'convert';
				}
			}
		
			if(($width > $max_width) || ($height > $max_height)) {
					//we want to overwrite current tmp file with the new one (hence the same var twice)        
				if(!self::resize_image($tmp_file, $tmp_file, $type, false, false, $max_width, $max_height) || !file_exists($tmp_file)) {
					self::remove($tmp_file);
					
					return 'resize';
				}
			}
		}

		$new_file = $tmp_file;
		return true;
	}
	
	
	/**
	 * Save an uploaded file to a temporary place
	 *
	 * @param	array 	$byte_array	Specific $_FILE data for this 1 file
	 * @param	string	&$new_file	The location of the new uploade dfile
	 * @param	string	$format
	 * @param	integer	$max_width
	 * @param	integer	$max_height
	 *
	 * @return	bool|string
	 */
	public static function save_image_webcam($byte_array, &$tmp_file, $format=null, $max_width=null, $max_height=null) {

		$tmp_name = uniqid(rand()).'.jpg';
		$tmp_file = TMP_FILE_DIR . $tmp_name;
		
		if(!file_put_contents($tmp_file, $byte_array)) {
			return 'save_error';
		}
		
			//lets make sure that this is an actual image
		if((list($width, $height, $type_number) = @getimagesize($tmp_file)) === FALSE) {
			return 'invalid';
		}

			//make sure we have a valid image type
		switch($type_number) {
			case 1 : $type = 'gif'; break;
			case 2 : $type = 'jpg'; break;
			case 3 : $type = 'png'; break;
			case 6 : $type = 'bmp'; break;
			default: {
				return 'format';
			}
		}

			//see if we need to convert the image
		if($format || $max_width || $max_height) {
			
				//need to convert type as well (first)
			if($format != $type) {
				if(!self::convert_image($tmp_file, $type, $tmp_file, $format) || !file_exists($tmp_file)) {
					self::remove($tmp_file);
					return 'convert';
				}
			}
		
			if(($width > $max_width) || ($height > $max_height)) {
					//we want to overwrite current tmp file with the new one (hence the same var twice)        
				if(!self::resize_image($tmp_file, $tmp_file, $type, false, false, $max_width, $max_height) || !file_exists($tmp_file)) {
					self::remove($tmp_file);
					
					return 'resize';
				}
			}
		}

		return true;
	}

	
	/**
	 * Convert an image from 1 format to another
	 *
	 * @param	string	$source_file
	 * @param	string	$source_format
	 * @param	string	$target_file
	 * @param	string	$target_format
	 *
	 * @return boolean
	 */
	public static function convert_image($source_file, $source_format, $target_file, $target_format) {
		
		if(!file_exists($source_file)) {
			return false;
		}
			//don't waste time
		if($source_format == $target_format) {
			return true;
		}

			//load image into memory
		switch($source_format) {
			case 'gif' : {
				$image = imagecreatefromgif($source_file);
				break;
			}
			case 'png' : {
				$image = imagecreatefrompng($source_file);
				break;
			}
			case 'bmp' : {
				$image = imagecreatefromwbmp($source_file);
				break;
			}
			case 'jpg' : {
				$image = imagecreatefromjpeg($source_file);
				break;
			}
			default : {
				return false;
			}
		}
			//make sure we have an image
		if(!$image) {
			return false;
		}
		
			//convert image to desired type
		switch($target_format) {
			case 'gif' : {
				if(!imagegif($image, $target_file)) {
					return false;
				}
				break;
			}
			case 'png' : {
					//@todo find way to set this config of png quality
				if(!imagepng($image, $target_file, 8)) {
					return false;
				}
				break;
			}
			case 'bmp' : {
				if(!imagewbmp($image, $target_file)) {
					return false;
				}
				break;
			}
			case 'jpeg' :
			case 'jpg' : {
					//@todo find way to set this config of jpeg quality
				if(!imagejpeg($image, $target_file, 70)) {
					return false;
				}
				break;
			}
			default : {
				return false;
			}
		}
		
		
			//destroy image in memory
		imagedestroy($image);
		
		
		return true;
	}

	
	/**
	 * Resize image
	 *
	 * @param	string	$source_file  Full physical path to image
	 * @param	string	$target_file  Full physical path to image
	 * @param	string	$type         Type of image
	 * @param	integer	$width        Desire image width. Set to 0 to ignore (default: 0)
	 * @param	integer	$height       Desired image height. Set to 0 to ignore (default: 0)
	 * @param	integer	$max_width    Max size for the image width. Set to 0 to ignore (default: 0)
	 * @param	integer	$max_height   Max size for the image height. Set to 0 to ignore (default: 0)
	 *
	 * @return	boolean
	 */
	public static function resize_image($source_file, $target_file, $type, $width=0, $height=0, $max_width=0, $max_height=0) {

			//make sure bad things cant happen
		if(empty($width) && empty($height) && empty($max_width) && empty($max_height)) {
			return true;
		}
			
			//get the current image size  
		if(false !== (list($file_width, $file_height) = @getimagesize($source_file))) {
				//now figure out what we want to do (max is always honored first)
			if(($max_width > 0) || ($max_height > 0)) {
						//figure out what ratio to use if both are set
				if(($max_width > 0) && ($max_height > 0)) {
					$ratio1 = ((float)$max_width) / $file_width;
					$ratio2 = ((float)$max_height) / $file_height;  
						//choose the lesser ratio, since we want to resize lower then both max_height and max_width
					$ratio = ($ratio1 < $ratio2) ? $ratio1 : $ratio2;
				}
				else {
					$ratio = ($max_width > 0) ? (((float)$max_width) / $file_width) : (((float)$max_height) / $file_height);
				}
					
				$new_width = $file_width * $ratio;
				$new_height = $file_height * $ratio;
			}
			else {
				
				 //they want an 'actual' dimension so lets resize it, keep the ratio, and crop whats not needed
				$new_width = ($width > 0) ? $width : $file_width;
				$new_height = ($height > 0) ? $height : $file_height;
			}
			
			
			if(($new_width != $file_width) || ($new_height != $file_height)) {
					//@todo add more image types as needed
				switch($type) {
					case 'jpg': {
							//get source image
						$source = imagecreatefromjpeg($source_file);
			
							//see if the ratios are off, in which case crop resize
						if(($file_width/$file_height) != ($new_width/$new_height)) {
							
							$scale = min((float)($file_width/$new_width), (float)($file_height/$new_height));
							
							$crop_x = (float)($file_width-($scale*$new_width));
							$crop_y = (float)($file_height-($scale*$new_height));
							
							$crop_width = (float)($file_width-$crop_x);
							$crop_height = (float)($file_height-$crop_y);
							
							$crop_tmp = imagecreatetruecolor($crop_width, $crop_height);
							
							imagecopy($crop_tmp, $source, 0, 0, ($crop_x/2), ($crop_y/2), $crop_width, $crop_height);
							imagedestroy($source);
							
							 //lets use this new image as the src, rest of the code doesn't need to know
							$source = $crop_tmp;
							$file_width = $crop_width;
							$file_height = $crop_height;
							
						}
						
						$tmp_image = imagecreatetruecolor($new_width, $new_height);
						
						imagecopyresampled($tmp_image, $source, 0, 0, 0, 0, $new_width, $new_height, $file_width, $file_height);
							//copy image resource to physical file
						imagejpeg($tmp_image, $target_file, 70);
						imagedestroy($source);
						imagedestroy($tmp_image);
						break;
					}
					default: {
						return false;
					}
				}
			} //end if
		}
		else { //could not get image size for whatever reason
			//@todo log error
			return false;
		}


			//check if image exsts
		if(!file_exists($target_file)) {
			\Core\Application::log_msg('could not create image '. $target_file.' from '.$source_file, 1, __FILE__, __LINE__);
			return false;
		}
			//return the new image path to the calling function
		return true;
	}

		
	/**
	 * Delete a file
	 *
	 * @param	string	$file
	 *
	 * @return  boolean
	 */
	public static function remove($file) {
		
			//first excape file path (be safe)
		//@todo expland tests


		if(file_exists($file)) {
			if(@unlink($file) === FALSE) {
				return false;
			}
		}

		return true;
	}

	
	/**
	 * Move a file
	 *
	 * @param	string	$old_fname
	 * @param	string	$new_fname
	 *
	 * @return  boolean
	 */
	public static function move($old_fname, $new_fname) {
		
		if(@rename($old_fname, $new_fname) === FALSE) {
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * See if we have any errors for an upload file
	 * @param	array 	$file_data	File data from $_FILES arrray
	 * @return	false | string
	 */
	public static function upload_error($file_data) {
			//if we have a file upload error
		if((isset($file_data['error'])) && $file_data['error'] != UPLOAD_ERR_OK)  {
			switch($file_data['error']) {
				case UPLOAD_ERR_INI_SIZE :   return 'size';
				case UPLOAD_ERR_FORM_SIZE :  return 'size';
				case UPLOAD_ERR_PARTIAL :    return 'partial';
				case UPLOAD_ERR_NO_FILE :    return 'missing';
				case UPLOAD_ERR_NO_TMP_DIR : return 'internal';
				case UPLOAD_ERR_CANT_WRITE : return 'internal';
				case UPLOAD_ERR_EXTENSION :  return 'internal';
				default :                    return 'unknown';        
			}
		}
		return false;
	}
	
		
	/**
	 * Save an uploaded file
	 * @param	string	$new_file  The location to save uploaded file
	 * @param	array 	$file_data  Specific $_FILE data for this 1 file
	 * @param	string	$create_path  Set to true to attempt to create target dir
	 * @return	boolean
	 */
	public function save_upload($new_file, $file_data, $create_path=true) {


		//@todo see why this function no longer usese two variables and adjust if needed

		if($err = self::upload_error($file_data)) {
			\Core\Errors::add('file', $err);
			return false;
		}
		else if(!is_uploaded_file($file_data['tmp_name'])) {

			\Core\Errors::add('file', 'invalid');
			return false;
		}
	
		return true;
	}
	
	
	/**
	 * Create a unique file id in a given directory
	 * @param	string	$path	Directory to create file in
	 * @param	string	$ext	Files extention
	 * @return	integer
	 */
	public static function unique_name($path, $ext) {
		
		
		//all files are just integers.  so we start at 1 and look up
		//if we start allowing a lot of files, we'll need to change this
		$name = 1;
		while(file_exists("{$path}{$name}.{$ext}")) {
			$name++;
		}
			//reserve files name
		@touch("{$path}/{$name}.{$ext}");
		
		return $name;
	}
	
	
	
	
	/**
	 * Save an uploaded file to a temporary place
	 * @param	array	$file_data  Specific $_FILE data for this 1 file
	 * @param	string	&$new_file	The location of the new uploade dfile
	 * @param	string	$format
	 * @return	false | new file location
	 */
	public static function save_video_upload($file_data, &$new_file, $format=false) {
	
		//see if there was an upload error
		if($err = self::upload_error($file_data)) {
			return $err;
		}  //make sure this is a valid file
		else if(!is_uploaded_file($file_data['tmp_name'])) {
			return 'invalid';
		}
	
		$tmp_file = TMP_FILE_DIR . uniqid(rand());
	
		if(!move_uploaded_file($file_data["tmp_name"], $tmp_file)) {
			return 'move_error';
		}
	
		//lets make sure that this is an actual image
		if((list($width, $height, $type_number) = @getimagesize($tmp_file)) === FALSE) {
			return 'invalid';
		}
	
		//make sure we have a valid image type
		switch($type_number) {
			case 1 : $type = 'gif'; break;
			case 2 : $type = 'jpg'; break;
			case 3 : $type = 'png'; break;
			case 6 : $type = 'bmp'; break;
			default: {
				return 'format';
			}
		}
	
		//see if we need to convert the image
		if($format || $max_width || $max_height) {
				
			//need to convert type as well (first)
			if($format != $type) {
				if(!self::convert_image($tmp_file, $type, $tmp_file, $format) || !file_exists($tmp_file)) {
					self::remove($tmp_file);
					return 'convert';
				}
			}
	
			if(($width > $max_width) || ($height > $max_height)) {
				//we want to overwrite current tmp file with the new one (hence the same var twice)
				if(!self::resize_image($tmp_file, $tmp_file, $type, false, false, $max_width, $max_height) || !file_exists($tmp_file)) {
					self::remove($tmp_file);
						
					return 'resize';
				}
			}
		}
	
		$new_file = $tmp_file;
		return true;
	}
	
	
	/**
	 * Save an uploaded file to a temporary place
	 * @param	array 	$byte_array	Specific $_FILE data for this 1 file
	 * @param	string	&$new_file	The location of the new uploade dfile
	 * @param	string	$format
	 * @return	false | new file location
	 */
	public static function save_video_webcam($byte_array, &$tmp_file, $format=false) {
	
		$tmp_name = uniqid(rand()).'.flv';
		$tmp_file = TMP_FILE_DIR . $tmp_name;
	
		if(!file_put_contents($tmp_file, $byte_array)) {
			return 'save_error';
		}
	
		/*
		//lets make sure that this is an actual image
		if((list($width, $height, $type_number) = @getimagesize($tmp_file)) === FALSE) {
			return 'invalid';
		}
	
			//make sure we have a valid image type
		switch($type_number) {
			case 1 : $type = 'gif'; break;
			case 2 : $type = 'jpg'; break;
			case 3 : $type = 'png'; break;
			case 6 : $type = 'bmp'; break;
			default: {
				return 'format';
			}
		}
	*/

		return true;
	}
	
}







