<?php
/**
 * foodGets.php
 * the api code for RESTful CRUD operations of the food
 * table and related. 
 * Broke out the gets to keep file size more manageable
 * Get nutrients, recipe, and search api's
 */


use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

 // GET FOODS WITH NUTRIENT RESULTS - BOTH BASIC & RECIPE

$app->get ( '/food/nutrients/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute ( 'id' );
	$data = array ();

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}
	
	$food_array = get_food($db, $request, $response, $id, $errCode);
	if ($errCode) {
		return $food_array;
	}

	$ingredient_flag = $food_array['ingredient_flag'];
	$recipe_servings = $food_array['servings'];
	$nutrient_array = get_nutrients($db, $request, $response, $id, $ingredient_flag, $errCode, $recipe_servings);
	if ($errCode) {
		return $nutrient_array;
	}

	$food_array['nutrients'] = $nutrient_array;

	$response_data = $food_array;
	
	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );



function get_food($db, $request, $response, $id, &$errCode) {
	$query = 'SELECT f.*, fu.description as food_units FROM food f, food_units fu WHERE fu.id = f.serving_units AND f.id = ?';
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

// SEARCH FOODS BY NAME AND POSSIBLY OWNER/FAV
$app->get ( '/foods/search', function (Request $request, Response $response) {
	$getquery = $request->getQueryParams();

	// check for owner and searchFoodOption being sent
	if (  !isset($getquery['owner']) || !isset($getquery['searchFoodOption']) ) {
		$data['error'] = true;
		$data['message'] = 'Owner and searchFoodOption parameters are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}

	$owner = $getquery['owner'];
	$searchOpt = $getquery['searchFoodOption'];
	$keyword = (isset($getquery['keyword']) && $getquery['keyword']) ? filter_var($getquery['keyword'], FILTER_SANITIZE_STRING) : false;

	// build query based on search food option: all, owner+fav, fav
	// query for recipe and basic food as well as add keyword option

	// add keyword clause if included
	$keywordSql = $keyword ? " AND (f.name like :keyword OR f.description like :keyword) " : "";

	$basic_select = "(SELECT f.id as foodId, f.name as foodName, f.description as foodDesc, f.owner as ownerId, IFNULL(ROUND(f.serving_size,2),'') as servSize, 
										f.serving_units as servUnits, ROUND(fd.calories,1) as calories, ROUND(fd.fat_grams,1) as fat, ROUND(fd.carb_grams,1) as carbs,
										ROUND(fd.protein_grams,1) as protein, ROUND(fd.fiber_grams,1) as fiber, ROUND(fd.points,1) as points,
										m.user_name as owner, 'Basic Food' as foodType";
	$recipe_select = "(SELECT f.id as foodId, f.name as foodName, f.description as foodDesc, f.owner as ownerId, IFNULL(ROUND(f.serving_size,2),'') as servSize, 
											f.serving_units as servUnits, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, m.user_name as owner, 'Food Recipe' as foodType";

	$select_owner_basic = $basic_select . "
													FROM food f, food_detail fd, member m
													WHERE f.id = fd.id 
														AND f.owner = m.member_id
														AND f.owner = :owner " . $keywordSql . ")";

	$select_owner_recipe = $recipe_select . "
														FROM food f, member m
														WHERE f.ingredient_flag = 1
															AND f.owner = m.member_id
															AND f.owner = :owner " . $keywordSql . ")";

	$select_fav_basic = $basic_select . "
													FROM food f, food_detail fd, member m, member_food_favs mf
													WHERE f.id = fd.id 
														AND f.owner = m.member_id
														AND mf.food_id = f.id
														AND mf.member_id = :owner " . $keywordSql . ")";

	$select_fav_recipe = $recipe_select . "
													FROM food f, member m, member_food_favs mf
													WHERE f.ingredient_flag = 1 
														AND f.owner = m.member_id
														AND mf.food_id = f.id
														AND mf.member_id = :owner " . $keywordSql . ")";

	$select_all_basic = $basic_select . "
													FROM food f, food_detail fd, member m
													WHERE f.id = fd.id 
														AND f.owner = m.member_id"  . $keywordSql . ")";

	$select_all_recipe = $recipe_select . "
														FROM food f, member m
														WHERE f.ingredient_flag = 1
															AND f.owner = m.member_id " . $keywordSql . ")";

	// figure out which mode we are using
	switch($searchOpt) {
		case 'ownerFoods':
			// set up pdo parm array
			$sql_parms = array(':owner' => $owner);
			$keyword && $sql_parms[':keyword'] = "%{$keyword}%";
			// both owner and favs
			$query = $select_owner_basic . 
								" UNION " .
							 $select_owner_recipe .
							 " UNION " .
							 $select_fav_basic .
							 " UNION " .
							 $select_fav_recipe . 
							 " ORDER by foodName";
			break;
		case 'favFoods':
			$sql_parms = array(':owner' => $owner);
			$keyword && $sql_parms[':keyword'] = "%{$keyword}%";
			// favs only
			$query = $select_fav_basic .
							" UNION " .
							$select_fav_recipe . 
							" ORDER by foodName";
			break;
		case 'allFoods':
			$sql_parms = array();
			$keyword && $sql_parms[':keyword'] = "%{$keyword}%";
			// all
			$query = $select_all_basic .
							" UNION " .
							$select_all_recipe . 
							" ORDER by foodName";

	}

/* 	echo 'query: ', $query;
	var_dump($sql_parms);
	die(); */

	$response_data = pdo_exec( $request, $response, $db, $query, $sql_parms, 'Searching Foods', $errCode, true, true );
	if ($errCode) {
		return $response_data;
	}

	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
}); 
