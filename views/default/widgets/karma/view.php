<style type="text/css">
    img.emblem {
		position:relative;
		left:10px;
		top:5px;
		height: 181px;
		width: 220px;
		border:1px solid #f5f5f5;
	}
	
	.rank {
		float:right;
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
	
	#kudos_senders {
		color: #3873B6;
		font-weight: bold;
		text-align:justified;
	}
</style>

<?php 
	//Guid of the user to which the widget belongs.
	$guid = $vars['entity']->owner_guid;
	$user_entity = get_entity($guid);
	
	//Find the time passed since karma was last updated for current user.
	$last_updated = $user_entity->karma_update_time;
	$current_time = time();
	$time_diff_in_hours = round(abs($current_time - $last_updated) / 3600,2);
	
	//If time passed since last update is more than one hour, then update on widget view.
	if ($time_diff_in_hours > 1 ) {
		karma_update($guid, '0', '0');
	}
	//Get karma instance for user.
	$entities = get_entities('object', 'karma', $guid);
	
	//Karma details exists, print them.	
	$entity = $entities[0];
	$karma_entity_guid = $entity->guid;
	$badge = $entity->badge;
	$activity = $entity->activity;
	$developer_score = $entity->developer_score;
	$marketing_score = $entity->marketing_score;
	$title = $badge." - ".$entity->total_score." Points";
	$img_class = 'class = emblem';
	
?>
	<!-- Display the kudos button -->
	<div id = "kudos">
<?php 
		//In a day a user can extended kudos only max_kudos number of times.
		$loggedin_userid = get_loggedin_userid();
		$loggedin_karma_entity = get_entities('object', 'karma', $loggedin_userid);
		$loggedin_karma_entity = $loggedin_karma_entity[0];
		$total_score = $loggedin_karma_entity->total_score;
		$loggedin_karma_guid = $loggedin_karma_entity->guid;
		
		$max_kudos = max(2, $total_score/500);
		$kudos = $entity->kudos;
		if ($guid != $vars['user']->guid) {
?>
			<form action = "<?php echo $vars['url']; ?>action/karma/kudos" method="post">
<?php 
				echo elgg_view('input/securitytoken'); 
				echo elgg_view('input/hidden', array('internalname' => 
					'guid', 'value' => $karma_entity_guid));
				echo elgg_view('input/hidden', array('internalname' => 
					'loggedin_karma_guid', 'value' => $loggedin_karma_guid));
				echo elgg_view('input/hidden', array('internalname' => 
					'max_kudos', 'value' => $max_kudos));
				echo elgg_view('input/hidden',array('internalname' => 
					'kudos','value' => $kudos));
				echo elgg_view('input/hidden', array('internalname' => 
					'kudos_giver', 'value' => $vars['user']->name));
				echo elgg_view('input/submit', array('value' => 
					elgg_echo('Extend your Kudos') , 'class' => elgg_echo
					('kudos_button'), 'borderbrush'=>elgg_echo('transparent'), 
					'borderthickness'=>elgg_echo('0')));
?>
			</form>
<?php
		}
		//Display rank of the user, based on overall score.
		if (!is_null($entity->rank))
			echo '<div class = "rank"><i>'."Rank ".'</i><b>'.$entity->rank.'</b></div>';
?>
	</div>
	
<!-- Display the user's badge  -->
<div class = "emblem">
	&nbsp;&nbsp;&nbsp;<img <?php echo $img_class; ?> src="<?php echo 
	elgg_format_url($vars['url']."mod/karma/default_icons/".$badge.".jpg");?>" />
</div>
<div class = "score">
	<?php
		echo '<br>';
		echo $title;
		echo '<br>';
	?>
</div>

<?php
	//Display score details.
		echo '<div class = "search_listing">';
			echo '<div id  = "score_details">';
				echo '<b style = "color:#690;">'."Score Details".'</b>';
				//Display why a certain badge was awarded to the user.
				echo '<div class = "badge_suggestion">';
					$suggestion = load_badge_suggestion($badge);
					echo $suggestion;	
				echo '</div>';
				//Diplay activity 
				echo '<br>';
				echo "<b>Bug Fixes</b>: ".$activity[1];
				echo '<br>';
				echo "<b>Build Service Commits</b>: ".$activity[3];
				echo '<br>';
				echo "<b>Planet openSUSE posts</b>: ".$activity[2];	
				echo '<br>';
				echo "<b>Tweets</b>: ".$activity[0];
				echo '<br>';
				echo "<b>Wiki Edits</b>: ".$activity[4];
				echo '<br>';
				/*Display what percentage of maximum score is the current 
				 * user's score.*/
				$perc = round(calculate_percent_of_score($developer_score,
					$marketing_score), 4);
				if($perc != 1) {
					echo "Your score is ".($perc*100)."% of the Max score.";
					echo '<br>';
				}
				echo '<br>';
				//Display kudos
				if (!is_null($kudos)) {
					echo "<b>Kudos: </b>".$kudos;
					echo '<br>';
					if (!is_null($entity->kudos_sender)) {
						//Display who all extended kudos to the current logged in user.
						echo "<b>Kudos Given By: </b>";
						echo '<br>';
						if (is_array($entity->kudos_sender)) {
							//Display only recent 10 or less results.
							$senders = $entity->kudos_sender;
							$size = count($senders);
							$limit = min(10, $size);
							for ($i = 0; $i < $limit; $i++) {
								echo '<a id = "kudos_senders" href = "'.$vars['url'].
									"pg/profile/".$senders[$size - $i - 1].'">'.
									$senders[$size - $i - 1].'</a>'." | ";
							}
							if (($size - 10) > 0 )
								echo "And ".($size - 10)." more";
						}
						//if there is only a single kudos sender
						else if( !is_array($entity->kudos_sender) && 
							!is_null($entity->kudos_sender))
								echo '<a id = "kudos_senders" href = "'.$vars['url'].
								"pg/profile/".$entity->kudos_sender.'">'.$entity->
								kudos_sender.'</a>';
					}
				}	
			echo '</div>';
		echo '</div>';
	
?>
