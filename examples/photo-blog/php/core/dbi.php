<?php
/**
 * Database Interface
 *
 * This class  is used to interface with the database
 *
 * @param  resource  $db
 * @param  string  $last_query
 * @package	Core
 */
namespace Core;
use PDO;

class DBI {
		//hold the last query ran (for debugging puposes)
	private static $last_query = '';
	private static $db = null;    //hold current connection. Interaction with dbi must be through methods in this class
	
	
	/**
	 * Add a new row.  
	 * @param	string	$tbl  The name of the table
	 * @param	array	$items  The data to add.  Array in format of (field => value) format
	 * @param	bool	$replace  Set to true to do a REPLACE instead of INSERT
	 * @param	bool	$add_ignore  Set to true to do a INSERT IGNORE instead of just INSERT
	 */
	public static function insert($tbl, $items, $replace=false, $add_ignore=false) {
		
		if(empty($tbl) || !is_array($items) || !count($items)) {
			return false;
		}
		$field_names = array();
		$field_placeholder = array();
		$field_values = array();
			//split format input
		foreach($items as $field=>$value) {
			$field_names[] = "`{$field}`";
			$field_placeholder[] = '?';
			$field_values[] = $value;
		}

			//create sql versions of arrays
		$field_name_sql = implode(', ', $field_names);
		$field_placeholder_sql = implode(', ', $field_placeholder);
		

		if($replace) {
			$sql = 'REPLACE ';
		}
		else if($add_ignore){
			$sql = 'INSERT IGNORE ';
		}
		else {
			$sql = 'INSERT ';
		}
		
			//build rest of query
		$sql .= " INTO `{$tbl}` ({$field_name_sql}) VALUES({$field_placeholder_sql})";

			//run query
		$sth = self::query($sql, $field_values);

			//run the queries
		if($sth === false) { 
			return false;
		}
			//if query is ran ok, the lets return the new id
		$id = self::$db->lastInsertId();
		return ($id) ? $id : true;
	}

	
	/**
	 * Add a new row.  Replace it if it already exists
	 * @param	string	$tbl	The name of the table
	 * @param	array	$items	The data to add.  Array in format of (field => value) format
	 */
	public static function replace($tbl, $items) {
			//this is just a special kind of insert
		return self::insert($tbl, $items, true);
	}
	
	
	/**
	 * Add a new row.  Ignore if it exists
	 * @param	string	$tbl	The name of the table
	 * @param	array	$items	The data to add.  Array in format of (field => value) format
	 */
	public static function insert_ignore($tbl, $items) {
			//this is just a special kind of insert
		return self::insert($tbl, $items, false, true);
	}


	/**
	 * Safe and easy way to update database rows
	 * @param	string	$tbl  The table name 
	 * @param	array	$items  Items to update 
	 * @param	array	$conditions  Conditions to match
	 * @return	database resource or false on error
	 */
	public static function update($tbl, $items, $conditions=false) {
		
		if(empty($tbl) || !count($items)) {
			return false;
		}

		$tmp_update = array();
		$value_replace = array();
		
		foreach($items as $field=>$value) {
			$tmp_update[] = $field.'=?';
			$value_replace[] = $value;
		}

			//build where clause
		self::build_where_clause($conditions, $where_sql, $value_replace);
		
			//create sql versions of arrays
		$update_sql = implode(', ', $tmp_update);
		
		$sql = "UPDATE {$tbl} SET {$update_sql} {$where_sql}";

			//run query
		$sth = self::query($sql, $value_replace);

		if($sth === false) { 
			return false;
		}
			//function should always return true value
		return ($sth->rowCount()) ? $sth->rowCount() : true;
	}
	
	
	
	/**
	 * Safe and easy method to increment a value
	 * @param	string	$table
	 * @param	string	$field
	 * @param	array 	$conditions
	 * @param	integer	$amount	The amount to increase by
	 * @return	Single columns value | false
	 */
	public static function increment($table, $field, $conditions=false, $amount=1) {
		//dont allow empty where conditions
		if(empty($table) || empty($field)) {
			return false;
		}
		
		if(!is_numeric($amount)) {
			return false;	
		}
		
			//generate where clause safely
		self::build_where_clause($conditions, $where_sql, $value_replace);
	
		$sql = "UPDATE `{$table}` SET `{$field}`=`{$field}`+{$amount}  {$where_sql}";

		//run query
		$sth = self::query($sql, $value_replace);

		if($sth === false) { 
			return false;
		}
			//function should always return true value
		return ($sth->rowCount()) ? $sth->rowCount() : true;
	}

	 
	/**
	 * Safe and easy way to remove rows from table based on given condition
	 * @param	string	$tbl  The table name  
	 * @param	array	$conditions  Conditions to match
	 * @return	database resource or false on error
	 */
	public static function remove($tbl, $conditions=false) {
		
		if(empty($tbl)) {
			return false;
		}

			//build where conditions
		self::build_where_clause($conditions, $where_sql, $value_replace);
		
		$sql = "DELETE FROM {$tbl} {$where_sql}";
			//run query
		$sth = self::query($sql, $value_replace);

		if($sth === false) { 
			return false;
		}
		
		return ($sth->rowCount()) ? $sth->rowCount() : true;
	}

	
	
	/**
	 * Safe and easy method to get a single value from 1 row
	 * @param	string	$table
	 * @param	string	$get_field
	 * @param	array	$conditions
	 * @param	array	$order  An array of values to set the order by
	 * @return	Single columns value | false
	 */
	public static function value($table, $get_field, $conditions=false, $order=false) {
			//dont allow empty where conditions
		if(empty($table) || empty($get_field)) {
			return false;
		}

			//generate where clause safely
		self::build_where_clause($conditions, $where_sql, $value_replace);

		if($order) {
			if(!is_array($order)) {
				$order = array($order);
			}
			$order_by = 'ORDER BY '. implode(', ', $order);
		}
		else {
			$order_by = '';
		}
		
		$sql = "SELECT `{$get_field}` FROM `{$table}`  {$where_sql} {$order_by} LIMIT 1";

			//run query
		$sth = self::query($sql, $value_replace);

		if(($sth === false) || !$sth->rowCount()) {			
			return false;
		}
		
			//get row as an array
		$value = $sth->fetch(PDO::FETCH_NUM);

			//return single column as a value
		return $value[0]; 
	}

	

	/**
	 * Get a flatten array from a single column 
	 * @param	string	$table
	 * @param	string	$get_field
	 * @param	array 	$conditions
	 * @param	array 	$order	An array of values to set the order by
	 * @return	array | false
	 */
	public static function values($table, $get_field, $conditions=false, $order=false) {
			//dont allow empty where conditions
		if(empty($table) || empty($get_field)) {
			return false;
		}
	
			//generate where clause safely
		self::build_where_clause($conditions, $where_sql, $value_replace);
	
		if($order) {
			if(!is_array($order)) {
				$order = array($order);
			}
			$order_by = 'ORDER BY '. implode(', ', $order);
		}
		else {
			$order_by = '';
		}
	
		$sql = "SELECT `{$get_field}` FROM `{$table}`  {$where_sql} {$order_by}";
	
			//run query
		$sth = self::query($sql, $value_replace);

		if(($sth === false) || !$sth->rowCount()) {
			return false;
		}
	
			//get row as an array
		$data = $sth->fetchAll(PDO::FETCH_NUM);

		$values = array();
		foreach($data as $e) {
			$values[] = $e[0];	
		}
			
		return $values;
	}
	
	
	/**
	 * Safe and easy method to get total row count
	 * @param	string	$table
	 * @param	array	$conditions
	 * @param	bool	$do_cache   Set to true if you want results cacheds
	 * @param	integer
	 */
	public static function total($table, $conditions=false, $do_cache=false) {

			//dont allow empty where conditions
		if(empty($table)) {
			return false;
		}
		
			//generate where clause safely
		self::build_where_clause($conditions, $where_sql, $value_replace);

		
		$sql = "SELECT count(*) FROM `{$table}` {$where_sql}";

			//run query
		$sth = self::query($sql, $value_replace);

		if(($sth === false) || !$sth->rowCount()) { 
			return false;
		}
		
			//get row as an array
		$value = $sth->fetch(PDO::FETCH_NUM);

			//return single column as a value
		return $value[0]; 
	}  
	
		 
	/**
	 * safe and easy method to find data from a table
	 * @param	string	$table	Name of table
	 * @param	array	$conditions	Query constraint conditions
	 * @param	integer	$start	Row offeset (Default: 0)
	 * @param	integer	$count	Row count to return (Default: 50)
	 * @param	array	$order	An array of values to set the order by
	 * @param	boolean	$numeric_index	Return rows with an indexed
	 * @return	array of columns | false
	 */
	public static function rows($table, $conditions=false, $start=0, $count=50, $order=false, $numeric_index=false) {
			

			//generate where clause safely
		self::build_where_clause($conditions, $where_sql, $value_replace);

			//see if we need to set a limit for the query
		if($count && (is_numeric($start) && is_numeric($count))) {
			 $limit_sql = " LIMIT {$start}, {$count} ";
		}
		else {
			$limit_sql =  '';
		}
		
		if($order) {
			if(is_array($order)) {
				$order_by = 'ORDER BY '. implode(', ', $order);
			}
			else {
				$order_by = 'ORDER BY '. $order;
			}
		}
		else {
			$order_by = '';
		}
		
			//write query
		$sql = "SELECT * FROM `{$table}` {$where_sql} {$order_by} {$limit_sql}";
		
			//run query
		$sth = self::query($sql, $value_replace);

		if(($sth === false) || !$sth->rowCount()) { 
			return false;
		}
	
		if($numeric_index) {
			$rows = $sth->fetchAll(PDO::FETCH_NUM);
		}
		else {
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
		}

		return $rows; 
	}
	
		 
	/**
	 * safe and easy method to get specific columns from one or more rows
	 * @param	string	$table	Name of table
	 * @param	array	$fields	Array of column names to fetch
	 * @param	array	$conditions	Query constraint conditions
	 * @param	integer	$start	Row offeset (Default: 0)
	 * @param	integer	$count	Row count to return (Default: 50)
	 * @param	array	$order	An array of values to set the order by
	 * @param	boolean	$numeric_index	Return rows with an indexed
	 * @return	array of columns | false
	 */
	public static function columns($table, $fields, $conditions=false, $start=0, $count=50, $order=false, $numeric_index=false) {
			

			//generate where clause safely
		self::build_where_clause($conditions, $where_sql, $value_replace);

			//see if we need to set a limit for the query
		if($count && (is_numeric($start) && is_numeric($count))) {
			 $limit_sql = " LIMIT {$start}, {$count} ";
		}
		else {
			$limit_sql =  '';
		}
			//get field sql    
		if(is_array($fields)) {
			$fields_sql = '`'.implode('`, `', $fields).'`'; 
		}
		else { 
			$fields_sql = "`{$fields}`";
		}
		
		if($order) {
			if(is_array($order)) {
				$order_by = 'ORDER BY '. implode(', ', $order);
			}
			else {
				$order_by = 'ORDER BY '. $order;
			}
		}
		else {
			$order_by = '';
		}
		
			//write query
		$sql = "SELECT {$fields_sql} FROM `{$table}` {$where_sql} {$order_by} {$limit_sql}";
		
			//run query
		$sth = self::query($sql, $value_replace);

		if(($sth === false) || !$sth->rowCount()) { 
			return false;
		}
	
		if($numeric_index) {
			$rows = $sth->fetchAll(PDO::FETCH_NUM);
		}
		else {
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
		}

		return $rows; 
	}


	/**
	 * safe and easy method to get a single value from 1 row
	 * @param	string	$table
	 * @param	array	$conditions
	 * @param	array	$order  An array of values to set the order by
	 * @return	array of columns | false
	 */
	public static function row($table, $conditions=false, $order=false) {
			//dont allow empty where conditions
		if(empty($table)) {
			return false;
		}

			//generate where clause safely
		self::build_where_clause($conditions, $where_sql, $value_replace);
		
		if($order) {
			if(!is_array($order)) {
				$order = array($order);
			}
			$order_by = 'ORDER BY '. implode(', ', $order);
		}
		else {
			$order_by = '';
		}
		
			//write query
		$sql = "SELECT * FROM `{$table}` {$where_sql} {$order_by} LIMIT 1";
		
			//run query
		$sth = self::query($sql, $value_replace);

		if(($sth === false) || !$sth->rowCount()) { 
			return false;
		}

		return $sth->fetch(PDO::FETCH_ASSOC); 
	}


	/**
	 * Run a query in a safe manner
	 * @param	string	$sql	Query to run after safe variable replacement
	 * @param	array	$data	Values to updated. Format: array('find' => 'replace')
	 * @param	boolean	$check_errors	Setting to false will supress all errors messages/logging		
	 * @return	false OR PDOStatement object
	 */
	public static function query($sql, $data=array(), $check_errors=true) {
		
			//see if we need to restart connection, fail if we can't
		if(!self::$db && !self::connect()) {
			Application::log_msg('Could not (re)connect to database');
			return false;
		}

		self::$last_query = $sql;
		
		if(DEBUG_LEVEL == 3) {
			echo "<div style='text-align:left; padding:2px;'>SQL Query: $sql </div>\n";
		}

		$time1 =  microtime(true);
		$sth = self::$db->prepare($sql);
		$success = $sth->execute($data);
		$time2 = microtime(true); 

			//@todo  make $_REQUEST['SHOW_SQL'] a class property thats set on class init
		if(((DEBUG_LEVEL == 1) && !empty($_REQUEST['show_sql'])) || (DEBUG_LEVEL == 2)) {
			echo "<div style='text-align:left; padding:2px;'>SQL Query: $sql<br> Query Time:".sprintf('%01.4f sec',($time2-$time1))."</div>\n";
		}
		if(!$success) {
			if($check_errors) {
				$error = $sth->errorInfo();
				if(DEBUG_LEVEL) {
					trigger_error('Query failed : ' . $error[2] ."\n  SQL: ".$sql.' PARAMS: '.print_r($data, true), E_USER_WARNING);
				}
				Application::log_msg('Query failed : ' . $error[2]."\n  SQL: ".$sql. ' PARAMS: '.print_r($data, true), 1, __FILE__, __LINE__);
			}
			return false;
		}  
		return $sth;
	} 	

	
	/**
	 * Connect to the database server
	 * @return  true or false
	 */    
	public static function connect() {
		 
		try {  
			  // MySQL with PDO_MYSQL  
			self::$db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);  
		}  
		catch(PDOException $e) {  
			echo $e->getMessage();  
		} 			
	
		return true;
	}


	/**
	 * Disconnect from database
	 * @return  true or false
	 */    
	public static function disconnect() {
		 
		self::$db = null; 
	
		return true;
	}
	
	
	/**
	 * Common function to build where clause
	 * @param	array	$conditions	Conditions to parse
	 * @param	string	&$where_sql	Return sql WHERE clause
	 * @param	array	&$values	Array of data to replace in bind command
	 */
	public static function build_where_clause($conditions, &$where_sql, &$values) {
		
		if(!isset($values) || !is_array($values)) {
			$values = array();
		}
		
		if(is_array($conditions) && count($conditions)) {
			$tmp_where = array();
			foreach($conditions as $f=>$v) {
					//see if there is a special tyype specifed
				$tmp = explode(':', $f);
				$f = $tmp[0];
				$t = (isset($tmp[1])) ? $tmp[1] : false; 
				
				switch($t) {
					case 'in' : {
							// pasrse in clause
						if(!is_array($v)) {
							$v = array($v);
						}
						$tmp = array();
						foreach($v as $v2) {
							$values[] = $v2;
							$tmp[] = '?';
						}
						$tmp_where[] = "`{$f}` IN (".implode(', ', $tmp).")";
						break;
					}
					case 'like' : {
						$tmp_where[] = "`{$f}` LIKE ? ";
						$values[] = $v;
						break;
					}
					case 'func' : {
							// pass in function to mysql
						$tmp_where[] = "`{$f}`={$v}";
						break;
					}
					case 'or' : {
							// $v should be an array of or conditions
						if(!is_array($v)) {
							$v = array($v);
						}
						$ors = array();
						foreach($v as $f2=>$v2) {
							$ors[] = "`{$f2}`=?";
							$values[] = $v2;
						}
						$tmp_where[] = '('.implode(' OR ', $ors).')';
						break;
					}
					default : {
						$tmp_where[] = "`{$f}`=?";
						$values[] = $v;
						break;
					}
				}
			}
			$where_sql = ' WHERE '.implode(' AND ', $tmp_where);
		}
		else {
			$where_sql = '';
		}		
		
		return;
	}
}


