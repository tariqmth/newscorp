<?php
/*
 * This class will take care of all operation neede to perform in Fridge, main functions includes:
 * 	- Validate CSV file
 *  - Read data from CSV
 *  - Convert Data from CSV to Array
 * 	- Process only fresh items
 * 	- Provide date of any item in timestamp format
 */

class fridge{
	private static $csv_file_path;
	public function __construct($csv_file_path) {
		if (self::validate_csv($csv_file_path)!==false)
			self::$csv_file_path=$csv_file_path;
	}
	/**
	 * Read CSV file and validate colunms.
	 * @param	string csv file path needed to read file from
	 * @return	boolean true if validated false if not
	 */
	public static function validate_csv($csv_file_path){
		try {
			$iterator = new C1CSVIterator($csv_file_path, false);
			$csv_data=array();
			// validate colunm count should be 4 as per CSV format
			// Format: item, amount, unit, use­by
			foreach ($iterator as $cols) {
				if (count($cols)<>4){
					echo 'CSV format not match should follow Format: item, amount, unit, use­by '."\n";
					return false;
				}
			}
			return true;
		} catch (Exception $e) {
			echo $e->getMessage()."\n";
			return false;
		}
	}
	/**
	 * Read CSV file and returns array.
	 * @return	array $csv_date collection of csv collnms data
	 */
	
	private function csv_to_array(){
		$iterator = new C1CSVIterator(self::$csv_file_path, false);
		$csv_data=array();
		// validate colunm count should be 4 as per CSV format
		// Format: item, amount, unit, use­by
		foreach ($iterator as $cols) {
			$csv_data[]=array("item"=>$cols[0],"amount"=>$cols[1],"unit"=>$cols[2],"use_by"=>$cols[3]);
		}
		return $csv_data;
	}
	
	/**
	 * Read CSV file and validate use by date for each item.
	 * @return	array  $fresh_items array of items where use by date is not passed.
	 */	
	
	public function get_fresh_items(){
		$items = self::csv_to_array();
		$fresh_item = array();
		
		foreach ($items as $item){
			$use_by_date = explode("/", trim($item['use_by']));
			if (strtotime("now") > strtotime($use_by_date[2].'-'.$use_by_date[1].'-'.$use_by_date[0])){
				//echo 'Expired item '.$item['item']."\n";
			}else{
				//echo 'Fresh item '.$item['item']."\n";
				$fresh_item[]=$item;
				
			}
		}
		return $fresh_item;
	}
	
	/**
	 * Iterate through each fresh items and validate parameter data .
	 * @param	string $item item name
	 * @param 	int	$qty quantity required
	 * @param	string $unit unit required
	 * @return	boolean true if validated false if not
	 */
	public function check_in_fridge($item,$qty,$unit){
		$fresh_items = $this->get_fresh_items();
		
		foreach ($fresh_items as $i){
			// match item and validate item with qty and unit
			
			if ($i['item'] == $item){ 
					//&& $item['amount']>=$qty && $item['unit'] == $unit ){
				return true;
				
			}
		}
		return false;
		
	}

	/**
	 * Iterate through items and return use by date of matching item only
	 * @param	string $item_name name of item which use by date required
	 * @return	timestamp $use_by_date if item match otherwise return false
	 */
	public function get_use_by_date($item_name){
		$items = self::csv_to_array();
		foreach ($items as $item){
			if ($item['item']== $item_name){
				$use_by_date = explode("/", trim($item['use_by']));
				return strtotime($use_by_date[2].'-'.$use_by_date[1].'-'.$use_by_date[0]);
			}
		}
		return false;
	}
	
}