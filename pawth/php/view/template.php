<?php
/**
 * Template View class
 *
 * View used to load templates and to output html
 * to the user.  Also includes template functions
 *
 * @package    View
 */
namespace View;

use \Core\Application as Application;

class Template extends View {


	/**
	 * Override default pre-hook to get the template layout prefix name
	 */
	public function pre_display() {
		parent::pre_display();

		global $dynamic_paths;
		global $special_layouts;

			//build up default template array of data

		if(empty($this->data['template'])) {

			if(!$this->data['path_data']['argc']) {
                    // if no path then use default
                if($this->data['user']) {
                    $this->data['template'] = 'account/home';
                }
                else {
                    $this->data['template'] = 'pages/home';
                }
			}
            else if($this->data['path_data']['argc'] < 2) {
                    // go to template aries homepage
                $this->data['template'] = $this->data['path_data']['argv'][0] . '/' . $this->data['path_data']['argv'][0];
            }
            else if(isset($dynamic_paths[$this->data['path_data']['argv'][0]]) || is_numeric($this->data['path_data']['argv'][1])) {
                    // this page has custom drivers on its home page to re-route to sub templates
                $this->data['template'] = $this->data['path_data']['argv'][0] . '/' . $this->data['path_data']['argv'][0];
            }
			else {
					// templates will be named
				$this->data['template'] = $this->data['path_data']['argv'][0] . '/' . $this->data['path_data']['argv'][1];
			}
		}


        if(empty($this->data['layout'])) {

            if(isset($special_layouts[$this->data['template']])) {
                $this->data['layout'] = $special_layouts[$this->data['template']];
            }
            else {
                $this->data['layout'] = 'default';
            }
        }

			//want this to be a local variable in templates
		$this->data['errors'] = $this->errors;


        if($this->data['user']) {
            $this->data['is_guest'] = false;
            $this->data['my_profile'] = $_SESSION['profile'];
        }
        else {
            $this->data['is_guest'] = true;
            $this->data['my_profile'] = array(
                'full_name' => 'Guest',
                'display_name' => 'Guest',
                'handle' => 'Guest'
            );
        }

	        //not a system error, just an invalid URL
        if(!self::get_template_path($this->data['template'], $this->data['is_guest'])) {
            Application::redirect_404(__FILE__, __LINE__);
        }

            //not a system error, just an invalid URL
        if(!self::get_template_path('_layouts/'.$this->data['layout'], $this->data['is_guest'])) {
            Application::redirect_404(__FILE__, __LINE__);
        }


    }


	/**
	 * Display a specific template
	 *
	 * @return boolean
	 */
	public function display() {



		
		$layout_file = self::get_template_path('_layouts/' . $this->data['layout'], $this->data['is_guest']);
		if(empty($layout_file)) {
			Application::log_msg("No template found for ".$this->data['layout'], 1, __FILE__, __LINE__);
			return false;
		}

		
		extract($GLOBALS, EXTR_REFS);
		extract($this->data, EXTR_REFS);
		
		include($layout_file);

		return true;
	}
	
	
	/**
	 * Fetch template
	 * @param	string	$name	Template name
	 * @param	array 	$vars	Vars to be used in template
	 * @return	boolean | string
	 */
	public static function fetch_tpl($name, $vars) {
	
		$tpl_file = self::get_template_path('emails', $name);
	
		if(!file_exists($tpl_file)) {
			return false;
		}
	
			//extract variables templates assume will be available
		extract($GLOBALS, EXTR_REFS);
		extract($vars, EXTR_REFS);
	
			//start buff capture
		ob_start();
			//include the file
		include($tpl_file);
			//get output
		$paresed_tpl = ob_get_contents();
			//close capture buffer
		ob_end_clean();
			//return txt
		return $paresed_tpl;
	}


	/**
	 * Get the template path
	 * @param	string	$template_name	This is the template that should be loaded
	 * @param	boolean	$is_guest	Set to true if a guest is viewing template (default: false)
	 * @param	string	$tpl_ext	The extension of the template file (Default: .tpl.php)
	 * @param	boolean	$full_path	Set this to false to just return the relative template path
	 * @return  string
	 */
	public static function get_template_path($template_name, $is_guest=false, $tpl_ext='.tpl.php', $full_path=true) {


		$auth_type = ($is_guest) ? 'external':'internal';

		//check for site specific templates, if that fails we check for build in default templates

		if(file_exists(TEMPLATES . $template_name.'.'.$auth_type.$tpl_ext)) {
			$path =  (($full_path) ? TEMPLATES : '' ) . $template_name.'.'.$auth_type.$tpl_ext;
		}
		else if(file_exists(TEMPLATES . $template_name.$tpl_ext)) {
			$path =  (($full_path) ? TEMPLATES : '' ) .$template_name.$tpl_ext;
		}
		else {
			$path = false;
		}

		return $path;
	}


}  
