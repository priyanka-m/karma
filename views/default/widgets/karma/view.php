<style type="text/css">
    img.emblem {
		position:relative;
		left:10px;
		top:5px;
		height: 181px;
		width: 220px;
		border:1px solid #f5f5f5;
	}
	
	img.star {
		float:right;
		height:15px;
		width:15px;
	}
	
	div.score {
		color: #3873B6;
		font-weight: bold;
		text-align:center;
		border-bottom: 1px solid #DDD;
		padding:5px 0 0 0;
	}
	
	b {
		color:#444;
	}
	
	#kudos {
		display:inline;
	}
	
	input.kudos_button {
		overflow:visible; 
		margin:0;
		padding:0;
		border:0;
		font-weight:bold;
		color:#690;
		background:transparent;
		line-height:normal; 
		text-decoration:none; 
		cursor:pointer; 
		-moz-user-select:text; 
	}
	
	#score_details {
		font-family:helvetica, arial, sans-serif;
		margin-bottom:1em;
	}
	
	.badge_suggestion {
		color:#069;
	}
	
</style>

<?php 
	//guid of the user to which the widget belongs.
	$guid = $vars['entity']->owner_guid;
	$user_entity = get_entity($guid);
	
	//find the time passed since karma was last updated for current user.
	$last_updated = $user_entity->karma_update_time;
	$current_time = time();
	$time_diff_in_hours = round(abs($current_time - $last_updated) / 3600,2);
	
	//if time passed since last update is more than one hour, then update on widget view.
	if ($time_diff_in_hours > 1 ) {
		karma_update($guid,'0','0');
	}
	//get karma instance for user.
	$entities = get_entities('object','karma',$guid);
	
	//karma details exists, print them.	
	$entity = $entities[0];
	$karma_entity_guid = $entity->guid;
	$badge = $entity->badge;
	$activity = $entity->activity;
	$developer_score = $entity->developer_score;
	$marketing_score = $entity->marketing_score;
	$wiki_score = $entity->wiki_score;
	$total_score = $marketing_score[0] + $marketing_score[1] + $developer_score[0] + $developer_score[1];
	$title = $badge." - ".$total_score." Points";
	$img_class = 'class = emblem';
	
?>
	<!-- display the kudos button -->
	<div id = "kudos">
<?php 
		$kudos = $entity->kudos;
		if($guid != $vars['user']->guid) {
?>
			<form action = "<?php echo $vars['url']; ?>action/karma/kudos" method="post">
<?php 
				echo elgg_view('input/securitytoken'); 
				echo elgg_view('input/hidden',array('internalname' => 'guid','value' => $karma_entity_guid));
				echo elgg_view('input/hidden',array('internalname' => 'kudos','value' => $kudos));
				echo elgg_view('input/submit', array('value' => elgg_echo('Extend your Kudos') ,'class' => elgg_echo('kudos_button'),'borderbrush'=>elgg_echo('transparent'), 'borderthickness'=>elgg_echo('0')));
?>
			</form>
<?php
		}
		//for every 20 kudos a gold star is awarded.
		$gold_stars = floor($kudos/20);
		//display stars.
		for ($i=1;$i<=$gold_stars;$i++) {
			echo '<img class = "star" src="'.elgg_format_url($vars['url']."mod/karma/default_icons/star.jpg").'" border="0"/>';
		}
		for($j=1;$j<=5-$gold_stars;$j++) {
			echo '<img class = "star" src="'.elgg_format_url($vars['url']."mod/karma/default_icons/grey.jpg").'" border="0"/>';
		}
?>
	</div>
	
<!-- displapy the user's badge  -->
<div class = "emblem">
	&nbsp;&nbsp;&nbsp;<img <?php echo $img_class; ?> src="<?php echo elgg_format_url($vars['url']."mod/karma/default_icons/".$badge.".jpg");?>" />
</div>
<div class = "score">
	<?php
		echo '<br>';
		echo $title;
		echo '<br>';
	?>
</div>

<?php
	//display score details.
		echo '<div class = "search_listing">';
			echo '<div id  = "score_details">';
				echo '<b style = "color:#690;">'."Score Details".'</b>';
				//display why a certain badge was awarded to the user.
				echo '<div class = "badge_suggestion">';
					$suggestion = load_badge_suggestion($badge);
					echo $suggestion;	
				echo '</div>';
				//diplay activity 
				echo '<br>';
				echo "<b>Bug Fixes</b> : ".$activity[1];
				echo '<br>';
				echo "<b>Build Service Commits</b> : ".$activity[3];
				echo '<br>';
				echo "<b>Planet openSUSE posts</b> : ".$activity[2];	
				echo '<br>';
				echo "<b>Tweets</b> : ".$activity[0];
				echo '<br>';
				echo "<b>Wiki Edits</b> :".$activity[4];
				echo '<br>';
				//display what percentage of maximum score is the current user's score.
				$perc = round(calculate_percent_of_score($developer_score,$marketing_score),2);
				if($perc != 1) {
					echo "Your score is ".($perc*100)."% of the Max score.";
					echo '<br>';
				}
				echo '<br>';
				//display kudos
				if(!is_null($kudos)) {
					echo "<b>Kudos - </b>".$kudos;
					echo '<br>';
				}	
				//display number of kudos needed for a higher star.
				$kudos_needed = kudos_needed_for_higher_star($kudos);
				echo "You need ".$kudos_needed." kudos to be awarded with a star.";
				echo '<br>';
			echo '</div>';
		echo '</div>';
	
?>
