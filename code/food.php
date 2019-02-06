<?php
// food.php
// the api code for RESTful CRUD operations of the food
// table and related. 

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

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
	$notes = isset($data['notes']) ? filter_var($data['notes'], FILTER_SANITIZE_STRING) : 0 ;
	$fav = isset($data['foodFav']) ? filter_var($data['foodFav'], FILTER_SANITIZE_STRING) : false ;
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
		return $db;
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

	// if this is a food fav, update the member_food_favs table
	if ($fav) {
		$query = 'INSERT INTO member_food_favs 
		(member_id, food_id) 
		VALUES  (?, ?)';

		$insert_data = array($owner, $food_id);							

		$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Food Favorite', $errCode, false, false, false );
		if ($errCode) {
		return $response_data;
		}
	}

	
	// if notes exist, update the member_food_favs table
	if ($notes) {
		$query = 'INSERT INTO food_notes 
		(food_id, note) 
		VALUES  (?, ?)';

		$insert_data = array($food_id, $notes);							

		$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Food Note', $errCode, false, false, false );
		if ($errCode) {
		return $response_data;
		}
	}

	$return_data = array('foodId' => $food_id);
	$return_data = array('data' => $return_data);
	$newResponse = $response->withJson($return_data, 201, JSON_NUMERIC_CHECK );
	return $newResponse; 
});



/**
 * create a food recipe/meal (made of ingredients instead of nutrient listing)
 */
$app->post ( '/foods/recipe', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	$return_data = array();

	$name = isset($data['foodName']) ? filter_var($data['foodName'], FILTER_SANITIZE_STRING) : '' ;
	$desc = isset($data['foodDesc']) ? filter_var($data['foodDesc'], FILTER_SANITIZE_STRING) : '' ;
	$owner = isset($data['owner']) ? filter_var($data['owner'], FILTER_SANITIZE_STRING) : '' ;
	$size = isset($data['servSize']) ? filter_var($data['servSize'], FILTER_SANITIZE_STRING) : null ;
	$units = isset($data['servUnits']) ? filter_var($data['servUnits'], FILTER_SANITIZE_STRING) : 1 ;
	$servings = isset($data['servings']) ? filter_var($data['servings'], FILTER_SANITIZE_STRING) : 1 ;
	$notes = isset($data['notes']) ? filter_var($data['notes'], FILTER_SANITIZE_STRING) : 0 ;
	$fav = isset($data['foodFav']) ? filter_var($data['foodFav'], FILTER_SANITIZE_STRING) : false ;
	$api = isset($data['apiKey']) ? filter_var($data['apiKey'], FILTER_SANITIZE_STRING) : '' ;
	$ingreds = isset($data['ingreds']) ? $data['ingreds'] : array();

	// check required fields
	if (!$name || !$owner || ! count($ingreds) ) {
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
		return $db;
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

	// trouble inserting null serving size so just remove and let default fill it
	if ($size) {
		$servSize = ', serving_size';
		$values = '(?, ?, ?, ?, ?, ?, ?)';
		$insert_data = array($name, $desc, $owner, 1, $size, $units, $servings);	
	} else {
		$servSize = '';
		$values = '(?, ?, ?, ?, ?, ?)';
		$insert_data = array($name, $desc, $owner, 1, $units, $servings);	
	}

	// create food item and get insert id
	$query = 'INSERT INTO food 
							(name, description, owner, ingredient_flag' . $servSize . ', serving_units, servings) 
							VALUES  ' . $values;
						

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

	// use food id to insert ingredients into food recipe
	// have to build string from ingreds array



 	$query = 'INSERT INTO food_recipe
							VALUES ';

	$insert_data = array();	
	
	// loop through ingreds and build SQL placeholders and array of data parameters
	foreach($ingreds as $ingred) {
		$query .= ' (? , ?, ?),';
		$insert_data[] = $food_id;
		$insert_data[] = $ingred['ingredId'];
		$insert_data[] = $ingred['ingredServings'];
	}

	// have to remove final comma
	$query = trim($query, ',');

/* echo $query, '   ';
var_dump($insert_data);
die(); */

	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Food Recipe', $errCode, false, false, false );
	if ($errCode) {
		// we have to rollback the insert of the food table as it does not have transactions
		$query = 'DELETE FROM food
								WHERE id = ?';						

		$tmp = pdo_exec( $request, $response, $db, $query, array($food_id), 'Deleting Food', $errCode, false, false, false );
		return $response_data;
	}

	// if this is a food fav, update the member_food_favs table
	if ($fav) {
		$query = 'INSERT INTO member_food_favs 
		(member_id, food_id) 
		VALUES  (?, ?)';

		$insert_data = array($owner, $food_id);							

		$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Food Favorite', $errCode, false, false, false );
		if ($errCode) {
		return $response_data;
		}
	}

	
	// if notes exist, update the food_notes table
	if ($notes) {
		$query = 'INSERT INTO food_notes 
		(food_id, note) 
		VALUES  (?, ?)';

		$insert_data = array($food_id, $notes);							

		$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Food Note', $errCode, false, false, false );
		if ($errCode) {
		return $response_data;
		}
	}

	$return_data = array('foodId' => $food_id);
	$return_data = array('data' => $return_data);
	$newResponse = $response->withJson($return_data, 201, JSON_NUMERIC_CHECK );
	return $newResponse; 
});


/**
 * EDIT a new basic food (not a recipe)
 */
$app->put ( '/foods/basic/{id}', function (Request $request, Response $response) {
	$food_id = $request->getAttribute ( 'id' );
	$data = $request->getParsedBody();
	$return_data = array();

	$owner = isset($data['owner']) ? filter_var($data['owner'], FILTER_SANITIZE_STRING) : '' ;
 	$name = isset($data['foodName']) ? filter_var($data['foodName'], FILTER_SANITIZE_STRING) : '' ;
/*	$desc = isset($data['foodDesc']) ? filter_var($data['foodDesc'], FILTER_SANITIZE_STRING) : '' ;
	$size = isset($data['servSize']) ? filter_var($data['servSize'], FILTER_SANITIZE_STRING) : null ;
	$units = isset($data['servUnits']) ? filter_var($data['servUnits'], FILTER_SANITIZE_STRING) : 1 ;
	$servings = isset($data['servings']) ? filter_var($data['servings'], FILTER_SANITIZE_STRING) : 1 ;
	$calories = isset($data['calories']) ? filter_var($data['calories'], FILTER_SANITIZE_STRING) : '' ;
	$fat_grams = isset($data['fat']) ? filter_var($data['fat'], FILTER_SANITIZE_STRING) : '' ;
	$carb_grams = isset($data['carbs']) ? filter_var($data['carbs'], FILTER_SANITIZE_STRING) : '' ;
	$protein_grams = isset($data['protein']) ? filter_var($data['protein'], FILTER_SANITIZE_STRING) : '' ;
	$fiber_grams = isset($data['fiber']) ? filter_var($data['fiber'], FILTER_SANITIZE_STRING) : 0 ;
	$points = isset($data['points']) ? filter_var($data['points'], FILTER_SANITIZE_STRING) : 0 ; */
	$notes = isset($data['notes']) ? filter_var($data['notes'], FILTER_SANITIZE_STRING) : null ;
	$fav = isset($data['foodFav']) ? filter_var($data['foodFav'], FILTER_SANITIZE_STRING) : null ;
	$api = isset($data['apiKey']) ? filter_var($data['apiKey'], FILTER_SANITIZE_STRING) : '' ;

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode, $api );
	if ($errCode) {
		return $db;
	}

	// if the name or owner exists, we need to check that it is not going to 
	// create a duplicate record for name/owner
	if ($name || $owner) {

		// need to get the original record 
		$query = 'SELECT owner, name
							FROM food 
							WHERE id = ?';

		$response_data = pdo_exec( $request, $response, $db, $query, array($food_id), 'Retrieving Food', $errCode, true, false, true );
		if ($errCode) {
			return $response_data;
		}
	
		if (($name && $response_data['name'] !== $name) || ($owner && $response_data['owner'] !== $owner) ) {
			// either name or owner is new, so test
			// need to check if name/owner combo already exists. 
			$test_name = $name ? $name : $response_data['name'];
			$test_owner = $owner ? $owner : $response_data['owner'];

			$query = 'SELECT id 
								FROM food 
								WHERE name = ?
								AND owner = ?';

			$response_data = pdo_exec( $request, $response, $db, $query, array($test_name, $test_owner), 'Updating Food', $errCode, false, false, true );
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
		}
	}

	// set up fields to make a more generic update routine

	$food_fields = array(
		'name' 					=> 'foodName',
		'description' 	=> 'foodDesc',
		'owner' 				=> 'owner',
		'serving_size' 	=> 'servSize',
		'serving_units' => 'servUnits',
		'servings' 			=> 'servings'
	);

	$food_detail_fields = array(
		'calories' 			=> 'calories',
		'fat_grams' 		=> 'fat',
		'carb_grams'		=> 'carbs',
		'protein_grams'	=> 'protein',
		'fiber_grams'		=> 'fiber',
		'points'				=> 'points'
	);

	$update_info = build_update_sql('food', $food_fields, $data, 'id', $food_id);
	$query = $update_info[0];
	$insert_data = $update_info[1];
					
	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Updating Food', $errCode, true, false, false );
	if ($errCode) {
		return $response_data;
	}

	$update_info = build_update_sql('food_detail', $food_detail_fields, $data, 'id', $food_id);
	$query = $update_info[0];
	$insert_data = $update_info[1];

	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Updating Food Detail', $errCode, false, false, false );
	if ($errCode) {
		return $response_data;
	}

	
	// if food fav exists, update the member_food_favs table using a db proc
	if ($fav !== null) {
		$query = "CALL  update_food_favs( ?, ?, ? )";

		$insert_data = array($owner, $food_id, $fav);							

		$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Updating Food Favorite', $errCode, false, false, false );
		if ($errCode) {
		return $response_data;
		}
	}

	// if notes exist, update the food_notes table using db proc
	if ($notes !== null) {
		$query = "CALL update_food_notes( ?, ? )";

		$insert_data = array($food_id, $notes);							

		$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Updating Food Note', $errCode, false, false, false );
		if ($errCode) {
		return $response_data;
		}
	}

	$return_data = array('foodId' => $food_id);
	$return_data = array('data' => $return_data);
	$newResponse = $response->withJson($return_data, 201, JSON_NUMERIC_CHECK );
	return $newResponse; 
});

function build_update_sql ($table, $fields, $data, $id_field, $id) {
	$parms = array();
	$set_str = '';
	foreach($fields as $field => $api_field) {
		if (isset($data[$api_field]) ) {
			$set_str .= " $field = ?,";
			$parms[] = filter_var($data[$api_field]);
		}
	}
	$set_str = trim($set_str, ',');
	$query = "UPDATE $table SET $set_str WHERE $id_field = $id";
	return array($query, $parms);
}