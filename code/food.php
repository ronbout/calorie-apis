<?php
// food.php
// the api code for RESTful CRUD operations of the food
// table and related. 

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get ( '/food/nutrients/{id}', function (Request $request, Response $response) {
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
	$recipe_servings = $food_array['servings'];
	$nutrient_array = get_nutrients($db, $request, $response, $id, $ingredient_flag, $errCode, $recipe_servings);
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

function get_nutrients($db, $request, $response, $food_id, $ingredient_flag, &$errCode, $recipe_servings) {
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
	$query = 'SELECT f.id, f.ingredient_flag, f.servings, fr.num_servings 
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
		$rec_servings = $ingred['servings'];
		$ingred_nutrients = get_nutrients($db, $request, $response, $ingred_id, $ingred_flag, $errCode, $rec_servings);
		if ($errCode) {
			return;
		}
		foreach($nutrient_array as $key => &$nutrient) {
			$nutrient += $ingred_nutrients[$key] * $num_servings / $recipe_servings;
		}
	}
	return $nutrient_array;
}

/**
 * create a new basic food (not a recipe)
 */
$app->post ( '/foods/basic', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	$return_data = array();

	$name = isset($data['foodName']) ? filter_var($data['foodName'], FILTER_SANITIZE_STRING) : '' ;
	$desc = isset($data['foodDesc']) ? filter_var($data['foodDesc'], FILTER_SANITIZE_STRING) : '' ;
	$owner = isset($data['owner']) ? filter_var($data['owner'], FILTER_SANITIZE_STRING) : '' ;
	$size = isset($data['servSize']) ? filter_var($data['servSize'], FILTER_SANITIZE_STRING) : null ;
	$units = isset($data['servUnits']) ? filter_var($data['servUnits'], FILTER_SANITIZE_STRING) : 1 ;
	$servings = isset($data['servings']) ? filter_var($data['servings'], FILTER_SANITIZE_STRING) : 1 ;
	$calories = isset($data['calories']) ? filter_var($data['calories'], FILTER_SANITIZE_STRING) : '' ;
	$fat_grams = isset($data['fat']) ? filter_var($data['fat'], FILTER_SANITIZE_STRING) : '' ;
	$carb_grams = isset($data['carbs']) ? filter_var($data['carbs'], FILTER_SANITIZE_STRING) : '' ;
	$protein_grams = isset($data['protein']) ? filter_var($data['protein'], FILTER_SANITIZE_STRING) : '' ;
	$fiber_grams = isset($data['fiber']) ? filter_var($data['fiber'], FILTER_SANITIZE_STRING) : 0 ;
	$points = isset($data['points']) ? filter_var($data['points'], FILTER_SANITIZE_STRING) : 0 ;
	$api = isset($data['apiKey']) ? filter_var($data['apiKey'], FILTER_SANITIZE_STRING) : '' ;

	// check required fields
	if (!$name || !$owner || $calories === '' || $fat_grams === '' || $carb_grams === '' || $protein_grams === '' ) {
		$data['error'] = true;
		$data['message'] = 'Required field is missing.  Please see api docs.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode, $api );
	if ($errCode) {
		return;
	}

	// need to check if name/owner combo already exists.  Cannot include unique key because 
	// fast food entries allow that (with category entry).  For all other foods, the
	// owner/name combo must be unique.  Cannot add trigger as would prevent adding 
	// more fast foods in the future.
	$query = 'SELECT id 
						FROM food 
						WHERE name = ?
						AND owner = ?';

	$response_data = pdo_exec( $request, $response, $db, $query, array($name, $owner), 'Creating Food', $errCode, false, false, true );
	if ($errCode) {
		return $response_data;
	}

	if ($response_data) {
		// we have a duplicate
		$return_data ['error'] = true;
		$return_data ['errorCode'] = 45001; // we will base our custom errors (outside of the actual db) on 45000 and up
		$return_data ['message'] = 'Duplicate owner - name combination';
		$data = array('data' => $return_data);
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// create food item and get insert id
	$query = 'INSERT INTO food 
							(name, description, owner, serving_size, serving_units, servings) 
							VALUES  (?, ?, ?, ?, ?, ?)';

	$insert_data = array($name, $desc, $owner, $size, $units, $servings);							

	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Food', $errCode, false, false, false );
	if ($errCode) {
		return $response_data;
	}

	if ( ! $food_id = $db->lastInsertId() ) {
		// unknown insert error - should NOT get here
		$return_data ['error'] = true;
		$return_data ['errorCode'] = 45002; // unknown error
		$return_data ['message'] = 'Unknown error creating Food entry';
		$data = array('data' => $return_data);
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// use food id to insert nutrients into food detail
	// create food item and get insert id
	$query = 'INSERT INTO food_detail
							(id, calories, points, fat_grams, carb_grams, protein_grams, fiber_grams) 
							VALUES  (?, ?, ?, ?, ?, ?, ?)';

	$insert_data = array($food_id, $calories, $points, $fat_grams, $carb_grams, $protein_grams, $fiber_grams);							

	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Food Detail', $errCode, false, false, false );
	if ($errCode) {
		// we have to rollback the insert of the food table as it does not have transactions
		$query = 'DELETE FROM food
								WHERE id = ?';						

		$tmp = pdo_exec( $request, $response, $db, $query, array($food_id), 'Deleting Food', $errCode, false, false, false );
		return $response_data;
	}

	$return_data = array('foodId' => $food_id);
	$return_data = array('data' => $return_data);
	$newResponse = $response->withJson($return_data, 201, JSON_NUMERIC_CHECK );
	return $newResponse; 
});