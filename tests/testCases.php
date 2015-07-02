<?php
// include reuired classes
require_once str_replace('tests','include',dirname(__FILE__)).'/C1Iterator.php';
require_once str_replace('tests','include',dirname(__FILE__)).'/fridge.php';
require_once str_replace('tests','include',dirname(__FILE__)).'/recipe.php';


class datecalcTest extends PHPUnit_Framework_TestCase{
	public $test;
	//Note: Defininf directory seprator, I am using windows, it might give error in linex OS because of \ need to replace with /
	private static  $sep="\\"; 
	
	
	public function setup(){
		//No need for setting up anything right now
	}
	
	public function test_invalid_csv(){
		// first row contain 3 colunms required 4 colunms
		$csv_file = 'invalid_sample.csv';
		$json_file = 'data.json';
		$expected = false;
	
		$cook_tonight = recipe::get_recpie(dirname(__FILE__).self::$sep.$csv_file,dirname(__FILE__).self::$sep.$json_file);
	
		$this->assertFalse($expected, $cook_tonight);
	}
	
	public function test_order_takeout(){
		// all items are expired in csv dated 2014 so, result must be order takeout
		$csv_file = 'sample.csv';
		$json_file = 'data.json';
		$expected = 'Order Takeout';
		
		$cook_tonight = recipe::get_recpie(dirname(__FILE__).self::$sep.$csv_file,dirname(__FILE__).self::$sep.$json_file);
		
		$this->assertEquals($expected, $cook_tonight,$cook_tonight);
	}
	public function test_cook1(){
		$csv_file = 'sample2.csv';
		$json_file = 'data2.json';
		// only grilled cheese on toast can be cook because other has expired ingrident
		$expected = 'grilled cheese on toast';
	
		$cook_tonight = recipe::get_recpie(dirname(__FILE__).self::$sep.$csv_file,dirname(__FILE__).self::$sep.$json_file);
	
		$this->assertEquals($expected, $cook_tonight,$cook_tonight);
	}
	public function test_cook_multiple(){
		$csv_file = 'sample3.csv';
		$json_file = 'data3.json';
		// peanut butter has expiry of 2/8/2015 which is near
		$expected = 'salad sandwich with peanut butter';
		$cook_tonight = recipe::get_recpie(dirname(__FILE__).self::$sep.$csv_file,dirname(__FILE__).self::$sep.$json_file);
		$this->assertEquals($expected, $cook_tonight,$cook_tonight);
	}
	
}

?>