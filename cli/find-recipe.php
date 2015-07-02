<?php

ini_set('display_errors', '1');
// make sure we got valid arguments
if ($argc == 3) {

	// include classes
	require_once str_replace('cli','include',dirname(__FILE__)).'/C1Iterator.php';
	require_once str_replace('cli','include',dirname(__FILE__)).'/fridge.php';
	require_once str_replace('cli','include',dirname(__FILE__)).'/recipe.php';
	$csv_file = $argv[1];
	$json_file = $argv[2];

	
	$cook_tonight = recipe::get_recpie($csv_file, $json_file);	
	
	
	
	
	
	
	
	//require_once '../../../../../core_fe.php';

	//invalid argument help user by showing syntax
} else {
	echo "Syntax:\n";
	echo 'php5 find-recipe.php <file.csv> <data.json>' . "\n";
	echo 'valid CSV file must follow Format: item, amount, unit, use­by'. "\n";
	echo 'valid JSON file must be in valid format'. "\n";
}