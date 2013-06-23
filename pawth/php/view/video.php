<?php
/**
 * Video View class
 *
 * Used to display a photo to a user. Always assumes
 * that the user has the correct permissions to view 
 * the photo. Other access level restrictions must
 * be varified before this view is used
 *
 * @todo	pass in mime time and file size as well so we aren't computing it every time
 *
 * @package    View
 */
namespace View;

class Video extends View {
	
	/**
	 * Display the the photo
	 */
	public function display() {
		$img = $this->data['video'];
		
		$file = $img['file'];

		switch($img['type']) {
			case "mp4" : { 
				$mine = "video/mp4";
				break;
			}
			case "flv" : { 
				$mine = "video/x-flv";
				break;
			}
			default : {
				echo 'Bad video type!';
				return false;
			} 

		}
		
		
		
		
		//stop current session while we fetch huge data
		session_write_close();
		
		//set up default values
		if(!isset($options['allow_partial'])) //i want isset, if they set anything then honor it
			$options['allow_partial'] = false;
		if(!isset($options['bandwidth_limit']))
			$options['bandwidth_limit'] = 1024;
		//default to most common command 'start'.  some flv players might require a different value
		if(!isset($options['stream_position']))
			$options['stream_position'] = 'start';
		if(!isset($options['show_errors']))
			$options['show_errors'] = false;
		
		
		if(!file_exists($file)) {
			if($options['show_errors']) {
				echo "ERROR 1130: The file \"{$file}\" does not exist";
			}
			return false;
		}
		
		//Gather relevent info about file
		$size = sprintf("%u", filesize($file));
		$fileinfo = pathinfo($file);
		$filename = basename($file);

		
		//check if http_range is set
		if($options['allow_partial'] && isset($_SERVER['HTTP_RANGE'])) {
			$range = '';
			$seek_start = 0;
			$seek_end = 0;
			list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if ($size_unit == 'bytes')  {  //only ever use the first range (we dont support multipole ranges since not needed)
				list($range, $extra_ranges) = explode(',', $range_orig, 2);
			}
	
			//figure out real download range
			list($seek_start, $seek_end) = explode('-', $range, 2);
			//check for valid ranges
			if(empty($seek_end)) {
				$seek_end = ($size - 1);
			}
			else {
				$seek_end = min(abs(intval($seek_end)), ($size - 1));
			}
	
			if(empty($seek_start) || $seek_end < abs(intval($seek_start))) {
				$seek_start = 0;
			}
			else {
				$seek_start = max(abs(intval($seek_start)),0);
			}
		}
		else {
			$seek_end = ($size - 1);
		 
			if(isset($_GET[$options['stream_position']])) {
				$seek_start = intval($_GET[$options['stream_position']]);
			}
			else {
				$seek_start = 0;
			}
		}
	
		if($options['use_xsendfile']) {
			if(($_SERVER['FCGI_ROLE'] == 'RESPONDER') && (substr($_SERVER['SERVER_SOFTWARE'], 8) == 'lighttpd')) {
				$xsend = 'X-LIGHTTPD-send-file:';
			}
			else {
				$xsend = 'X-Sendfile:';
			}
		}
		else {
			$xsend = FALSE;
		}
		
			//add headers if resumable
		if($options['allow_partial'] && !$xsend) { //for now going to assume $xsend is not compatiable.  (some one should test later)
			//Only send partial content header if needed (resolves bug in ie)
			if($seek_start > 0 || $seek_end < ($size - 1)) {
				header('HTTP/1.1 206 Partial Content');
			}
			header('Accept-Ranges: bytes');
			header("Content-Range: bytes {$seek_start}-{$seek_end}/{$size}");
		}
		
		header("Content-Type: {$mime}");
		
	//	if($options['download']) {
	//		header("Content-Disposition: attachment; filename={$filename}");
	//	}
	//	else {
			header("Content-Disposition: inline; filename={$filename}");
		
			header('Content-Length: '.($seek_end - $seek_start + 1));
	//	}
		
		if($xsend) {
			header("{$xsend} {$video}");
			exit;
		}
		 
		if($seek_start != 0) {
			if($options['stream_flv'] && (($mime == "video/x-flv") || ($mime == 'video/mp4') || ($mime == 'video/x-m4v'))) {  //add other mime types as needed (mp4...?)
				// FLV file format header (http://osflash.org/flv)
				echo 'FLV';        // (Signature) Always \93FLV\94
				echo pack('C', 1); // (Version) Currently 1 for known FLV files
				echo pack('C', 1); // (Flags) [5, audio+video] Bitmask: 4 is audio, 1 is video
				echo pack('N', 9); // (Offset)
				echo pack('N', 9);
			}
			else if($options['stream_mp4']) {
			}
			else if($options['stream_wmv']) {
			}
		}
		
		 
		$fp = fopen($file, 'rb');
		//seek to the start we set above
		fseek($fp, $seek_start);
		// output file
		while(!feof($fp))  {
				//DO NOT fread all of filesize at once. for large file this causes noticeable lag when starting the download
			echo fread($fp, 1024*$options['bandwidth_limit']);  //need to rewrite this code, very weak protections should expand later to include a usleep()
			flush();
			ob_flush();
			set_time_limit(60);
		}
		
		return true;
	}
	
	
	
}  

	
