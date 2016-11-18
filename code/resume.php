<!-- template.html  -->
<!DOCTYPE html>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Resume</title>
	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" 
		integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
		
	<link href="https://fonts.googleapis.com/css?family=Signika" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css?family=Cantarell" rel="stylesheet">
	<link rel="stylesheet" media="all" href="styles/resume.css"/>


</head>
<body>
<?php

set_time_limit(10 * 60);

// get id from GET if present, otherwise use 7 as default for practice

$id = ( isset($_GET['id']) && $_GET['id'] ) ? $_GET['id'] : 7;

$url = "http://www.ronboutilier.com/api/candidates/$id?api_cc=three&api_key=fj49fk390gfk3f50";

$ret = curl_load_file($url, array(), 'GET');

// echo  var_dump($ret);

$tmp = json_decode($ret);

$candidate = $tmp->data;

//echo '<br><h1>', $candidate->person->personFormattedName, '</h1>';

$tech_skills = build_tech_skills( $candidate );

build_resume( $candidate, $tech_skills );

////*************************************************************

function build_tech_skills( $c ) {
	// build the list of skills, grouped by tags for display
	// in the Technical Skills section
	$tech_skills = array();
	
	foreach ( $c->jobs as $job ) {
		foreach ( $job->jobSkills as $jobSkill ) {
			if ( ! array_key_exists($jobSkill->skillTags, $tech_skills) ) {
				$tech_skills[$jobSkill->skillTags] = array('name' => $jobSkill->skillTagNames, 
															'skills' => array());
			}
			// add the skill to the tag, if not there already
			if ( ! array_search($jobSkill->skillName, $tech_skills[$jobSkill->skillTags]['skills']) ) {
				$tech_skills[$jobSkill->skillTags]['skills'][] = $jobSkill->skillName;
			}
		}
	}
	return $tech_skills;
}

function build_resume( $c, $tech_skills ) {
	?>
	<div class="container-fluid">
		<div class="row" id="resume-container">
			<div class="col-md-2"></div>
			<div class="col-md-8 full-resume">
				<div class="red-bar"></div>
				<div class="grey-bar"></div>
				<div id="resume-header-container">
					<span id="resume-header">
						<span id="header-name"><?php echo $c->person->personFormattedName; ?></span>
						<span id="header-title"><?php echo $c->jobs[0]->jobTitle; ?></span>
					</span>
				</div>
				<div class="row" id="pro-summary-container">
					<div class="col-md-2 left-title">
						Professional<br>Summary
					</div>
					<div class="col-md-9">
						<ul class="highlight-list">
							<?php 
								foreach( $c->candidateHighlights as $highlight ) {
									echo '<li>', $highlight->highlight, '</li>';
								}
							?>
						</ul>
					</div>
				</div><!--  end of pro-summary -->
				<div class="row" id="tech-skills-container">
					<div class="col-md-2 left-title">
						Technical<br>Skills
					</div>
					<div class="col-md-9 tech-skills-container">
					<table class="table table-bordered">
						<!--  no thead for this table as there are no headings -->
						<tbody>
							<?php 
								foreach ( $tech_skills as $tech_skill ) {
									echo '<tr><td>', $tech_skill['name'], '</td><td>', implode(', ', $tech_skill['skills']), '</td></tr>';
								}
							?>
						</tbody>
					</table>
					</div>
				</div><!--  end of tech-skills -->
				<div class="row" id="experience-container">
					<div class="col-md-2 left-title">
						Experience
					</div>
					<div class="col-md-9" id="job-list">
						<?php 
							foreach( $c->jobs as $job ) {
								display_job( $job );
							}
						?>
					</div>
				</div><!--  end of experience -->
				<div class="row" id="education-container">
					<div class="col-md-2 left-title">
						Education<br>& Training
					</div>
					<div class="col-md-9" id="education-list">
						<?php
							foreach( $c->education as $ed ) {
								display_education( $ed );
							}
						?>
					</div>
				</div><!-- end of certifications -->
				<div class="row" id="certification-container">
					<div class="col-md-2 left-title">
						Certifications
					</div>
					<div class="col-md-9" id="certifications-list">
						<ul>
							<?php
								foreach( $c->certifications as $cert ) {
									echo '<li>', $cert->name, '</li>';
								}
							?>
						</ul>
					</div>
				</div><!-- end of certifications -->
			</div>
			<div class="col-md-2"></div>
		</div>
	</div>
	<?php
}


function display_job( $job ) {
	?>
	<div class="job-title"><?php echo $job->jobTitle; ?></div>
	<div class="job-location-dates">
		<span class="job-location"><?php echo build_job_loc($job); ?></span>
		<span class="job-dates"><?php echo build_job_dates($job); ?></span>
	</div>
	<div class="job-highlight-title">Responsibilities & Achievements</div>
	<div>
		<?php 
		if (property_exists($job, 'jobHighlights')) {
			echo '<ul class="highlight-list">';
			foreach( $job->jobHighlights as $highlight ) {
				echo '<li>', $highlight->highlight, '</li>';
			}
			echo '</ul>';
		}
		?>
	</div>
	<div class="row job-environment-container">
		<div class="col-md-1 job-environment-title">
			Environment:
		</div>
		<div class="col-md-11 environment-list">
			<?php 
				echo build_job_environment( $job );
			?>
		</div>
	</div>
	
	<?php 
}

function build_job_environment( $job ) {
	$skills = array_column($job->jobSkills, 'skillName');
	return implode(', ', $skills);
}

function build_job_loc( $job ) {
	$city = property_exists($job, 'municipality') ? ', ' . $job->municipality : '';
	$state = property_exists($job, 'region') ? ', ' . $job->region : '';
	$country = property_exists($job, 'countryCode') ? ', ' . $job->countryCode : '';
	return $job->name . $city . $state . $country;
}

function build_job_dates( $job ) {
	$ret = '';
	if (property_exists($job, 'startDate')) {
		$start = new DateTime($job->startDate);
		$startMonth = $start->format('M/Y');
		if (property_exists($job, 'endDate')) {
			$end = new DateTime($job->endDate);
			$endMonth = $start->format('M/Y');
		} else {
			$endMonth = 'Present';
		}
		$ret = $startMonth . ' - ' . $endMonth;
	}
	return $ret;
}

function display_education( $ed ) {
	echo '<div class="ed-title">';
	echo $ed->degreeName;
	echo '</div>';
	echo '<div class="ed-school">';
	if ( property_exists($ed, 'schoolName') ) {
		echo $ed->schoolName;
	}
	echo '</div>';
}

function curl_load_file( $url, $post_string = null, $request_type = 'POST' ) {
	// create curl resource
	$ch = curl_init();

	// set url
	curl_setopt($ch, CURLOPT_URL, $url);

	//return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	curl_setopt($ch, CURLOPT_TIMEOUT, 180);

	curl_setopt($ch, CURLOPT_USERAGENT, 'localhost test');

	if ($request_type == 'POST') {
		curl_setopt($ch, CURLOPT_POST, 1);
	} else {
		// request_type could be PUT or DELETE
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
	}

	if ($request_type != 'DELETE') {
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_string) );
	}

	// set up http header fields

	$headers = array(
			'Accept: text/json',
			'Pragma: no-cache',
			'Content-Type: application/x-www-form-urlencoded',
			'Connection: keep-alive'
	);

	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	// add code to accept https certificate
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	// $output contains the output string
	$output = curl_exec($ch);
	// close curl resource to free up system resources
	curl_close($ch);
	return $output;
}


?>
</body>
</html>
