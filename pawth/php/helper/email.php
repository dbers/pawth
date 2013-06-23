<?php
/**
 * Email class.
 *
 * This class contains functions for sending out emails
 *
 * @package	Helper
 */
namespace Helper;

class Email {
	
	
	/**
	 * Send an email
	 * @param	string	$send_to
	 * @param	string	$subject
	 * @param	string	$template
	 * @param 	array 	$vars
	 * @return	boolean
	 */
	public static function send($send_to, $template, $vars) {
		


		//@todo convert this into a view...  View::render('email'....)

		$headers = 'From: '. EMAIL_FROM . "\r\n" .
							 'Reply-To: '. EMAIL_NO_REPLY . "\r\n";

		$message = \View\Template::fetch_tpl($template.'-message', $vars);
		$subject = \View\Template::fetch_tpl($template.'-subject', $vars);

		if(mail($send_to, $subject, $message , $headers)) {
			return true;
		}

		return false;
	}

	

}



