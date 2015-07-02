<?php
// include classes
require_once str_replace('cli','include',dirname(__FILE__)).'/C1Iterator.php';
require_once str_replace('cli','include',dirname(__FILE__)).'/fridge.php';
require_once str_replace('cli','include',dirname(__FILE__)).'/recipe.php';
	
class datecalcTest extends PHPUnit_Framework_TestCase{
	public $test;
	public function setup(){
		//No need for setting up anything right now
	}
	public function test_cook(){
		$csv_file = 'sample.csv';
		$json_file = 'data.json';
		$expected = 'Order Takeout';
		$cook_tonight = recipe::get_recpie($csv_file, $json_file);
		$this->assertEquals($expected, $cook_tonight,$cook_tonight);
	}
	
	
}

?>