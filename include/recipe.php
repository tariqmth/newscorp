<?php
class recipe{
	private static $json_file_path;
	public function __construct($json_file_path) {
			self::$json_file_path=$json_file_path;
	}
	
	public function json_to_array(){
		try {
			$json = file_get_contents(self::$json_file_path);
			$receipes = json_decode($json, TRUE);
			return $receipes ;
		} catch (Exception $e) {
			echo $e->getMessage()."\n";
			return false;
		}
	}
	public function get_recipes(){
		$recipes = array();
		$ar = $this->json_to_array() ;
		foreach ($ar as $recipe=>$value){
				$recipes[]  = $value['name'];
		}
		return $recipes;
	}
	public function get_ingredients($ing){
		$ingredients = array();
		foreach (self::json_to_array() as $recipe=>$value){
			//$ingredients  = $value['ingredients'];
			if ( $value['name']== $ing){
			foreach ($value['ingredients'] as $ingredient)
				$ingredients[] = $ingredient;
			}
		}
		return $ingredients;
	}
	
	// get recipe
	public static function get_recpie($csv_file, $json_file){
		try {
	
			echo 'Reading and Validating CSV '.$csv_file. "\n";
				
			if (fridge::validate_csv($csv_file)){
				echo 'Done'. "\n";
				$fridge = new fridge($csv_file);
				//print_r($fridge->get_fresh_items());
			}else {
				echo 'CSV file not validated'. "\n";
				return false;
			}
				
			echo 'Reading and validating JSON '.$json_file. "\n";
				
			$obj_recipe = new recipe($json_file);
			// validate json
			if ($obj_recipe->json_to_array() !== false){
				echo 'Done'. "\n";
			}else {
				echo 'Invalid JSON data'. "\n";
				return false;
			}
	
			// get all available recipes
			$recpies = $obj_recipe->get_recipes();
	
			echo "\n\n".count($recpies).' Recipe(s) found'."\n";
	
			// check each recipe with ingriedents
			$can_cook=array();
			foreach ($recpies as $recipe){
				$ing = $obj_recipe->get_ingredients($recipe);
				echo "\n\n".'Recipe: '.$recipe. "\n Ingriedents \n";
	
				// if ingriedents available in fridge and not expired
				$available = false;
				$use_by_dates = array();
				foreach ($ing as $i){
					$use_by_dates[]= $fridge->get_use_by_date($i['item']);
					echo $i['item'];
					if ($fridge->check_in_fridge($i['item'], $i['amount'],$i['unit'])){
						echo '.... available '."\n";
						$available=true;
					}else{
						echo '....NOT available '."\n";
						$available = false;
						break 1;
					}
				}
				if ($available){
					// sort the date, so, the neareast expiry date will be on first element
					sort($use_by_dates);
					$can_cook[] = array('name'=>$recipe,'use_by'=>$use_by_dates[0],'use_by_date'=>date("d/m/Y",$use_by_dates[0]));
				}
			}
	
	
	
			// if have multiple options then check use by date of ingridents
			if (count($can_cook)>1){
				$cook = array();
				// iterate and store use by date and name
				foreach ($can_cook as $c){
					$cook[$c['use_by']]=$c['name'];
				}
				// sort on use by date so the receipe near expiry will be on top, first element of array
				//sort($cook);
				ksort($cook);
				$cook = array_shift($cook);
			}elseif (count($can_cook)==1){
				$cook=$can_cook[0]['name'];
			}else {
				$cook='Order Takeout';
			}
			echo "\n\n\n Recommendation to cook for tonight is \n\n";
			echo strtoupper( $cook);
			echo "\n***************************************** \n\n";
				
			return $cook;
				
		} catch (Exception $e) {
			echo $e->getMessage()."\n";
			return false;
		}
	}
}
