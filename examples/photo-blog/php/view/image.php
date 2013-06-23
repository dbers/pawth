<?php
/**
 * Image View class
 *
 * Used to display a photo to a user. Always assumes
 * that the user has the correct permissions to view 
 * the photo. Other access level restrictions must
 * be varified before this view is used
 *
 * @todo	pass in mime time and file size as well so we aren't computing it every time
 *  
 *
 * @package    View
 */
namespace View;

class Image extends View {
	
	/**
	 * Display the the photo
	 */
	public function display() {
		$img = $this->data['image'];
		
		$file = $img['file'];

		switch($img['type']) {
			case "jpeg":
			case "jpg" : { 
				$mine = "image/jpeg";
				break;
			}
			case "gif" : { 
				$mine = "image/gif";
				break;
			}
			case "png" : { 
				$mine = "image/png";
				break;
			}
			default : {
				echo 'Bad image type!';
				return false;
			} 
		}
		//stop current session while we fetch huge data
		session_write_close();
		
			//display the image to the user
		header("Cache-Control: private, max-age=10800, pre-check=10800");
		header("Pragma: public"); // required
		header("Expires: " . date(DATE_RFC822,  mktime(0, 0, 0, 1, 1, date('Y')+1)));

		$file_last_modified = filemtime($file);
				
			//@todo might need a way to flush this if image is modfied in the future
		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $file_last_modified)) {
			// send 304 to tell browser to use exsting image
			header('Last-Modified: '.$_SERVER['HTTP_IF_MODIFIED_SINCE'], true, 304);
			exit;
		}
		
		header('Content-type: '.$mine);
		header('Content-length: '.sprintf("%u", filesize($file)));
		header("Accept-Ranges: bytes");
		header("Pragma: public");
		header('Expires: '.gmdate('D, d M Y H:i:s', mktime(0, 0, 0, 1, 1, date('Y')+1)) . ' GMT');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', $file_last_modified) . ' GMT');
		header('Content-Disposition: inline; filename='.basename($file));
		
		if(X_SENDFILE) {
			header('X-Sendfile: ' . $file);
			exit;
		}

		readfile($file);
		
		return true;
	}
	
	
	
}  

	
