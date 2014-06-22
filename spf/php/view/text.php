<?php
/**
 * JSON View class
 *
 * View used to output data in a json format
 *
 * @package    View
 */
namespace View;

class Text extends View {

	/**
	 * Output a JSON object of view_data
	 */
	public function display() {
		echo $this->data;
		return;
	}
}  

	
