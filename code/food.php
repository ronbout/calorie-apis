<?php
// food.php
// the api code for RESTful CRUD operations of the food
// table and related. 

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get ( '/food/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute ( 'id' );
	$data = array ();

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return;
	}
	
	$food_array = get_food($db, $request, $response, $id, $errCode);
	if ($errCode) {
		return;
	}

	$ingredient_flag = $food_array['ingredient_flag'];
	$nutrient_array = get_nutrients($db, $request, $response, $id, $ingredient_flag, $errCode);
	if ($errCode) {
		return;
	}

	$food_array['nutrients'] = $nutrient_array;

	$response_data = $food_array;
	
	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );

function get_food($db, $request, $response, $id, &$errCode) {
	$query = 'SELECT * FROM food WHERE id = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, 
				array($id), 'Retrieving Food', $errCode, true );

	return $response_data;
}

function get_nutrients($db, $request, $response, $food_id, $ingredient_flag, &$errCode) {
	if ( !$ingredient_flag) {
		// get the nutrients from food_detail and be done
		$query = 'SELECT calories, points, fat_grams, carb_grams, protein_grams, fiber_grams 
							FROM food_detail WHERE id=?';
	
	$nutrient_array = pdo_exec( $request, $response, $db, $query, 
				array($food_id), 'Retrieving Food Details', $errCode, true );
		return $nutrient_array;
	}

	$nutrient_array = array(
		'calories'=> 0,
		'points' => 0,
		'fat_grams' => 0,
		'carb_grams' => 0,
		'protein_grams' => 0,
		'fiber_grams' => 0
	); 

	// we have a food recipe,  need to look up ingredients recursively
	$query = 'SELECT f.id, f.ingredient_flag, fr.num_servings 
						FROM food f, food_recipe fr
						WHERE f.id = fr.ingredient_id
						AND fr.food_id = ?';

	$ingredient_list = pdo_exec( $request, $response, $db, $query, 
				array($food_id), 'Retrieving Food Ingredients', $errCode, true, true );
	if ($errCode) {
		return;
	}

	foreach($ingredient_list as $ingred) {
		$ingred_id = $ingred['id'];
		$ingred_flag = $ingred['ingredient_flag'];
		$num_servings = $ingred['num_servings'];
		$ingred_nutrients = get_nutrients($db, $request, $response, $ingred_id, $ingred_flag, $errCode);
		if ($errCode) {
			return;
		}
		foreach($nutrient_array as $key => &$nutrient) {
			$nutrient += $ingred_nutrients[$key] * $num_servings;
		}
	}

	return $nutrient_array;

}