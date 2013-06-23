<?php
/**
 * JSON View class
 *
 * View used to output data in a json format
 *
 * @package    View
 */
namespace View;

class JSON extends View {

	/**
	 * Ouput a JSON object of view_data
	 */
	public function display() {

		if(isset($this->extra['no_errors']) && $this->extra['no_errors']) {
			$return = $this->data;
		}
		else {
			$return = array('data' => $this->data,
			                'errors' => (is_array($this->errors) && count($this->errors)) ? $this->errors : false
			);
		}

		echo json_encode($return);

		return;
	}
}


