<style type="text/css">
    img.emblem {
		position: relative;
		left: 20px;
		height: 181px;
		width: 200px;
	}
	div.score {
		color: #3873B6;
		font-weight: bold;
		text-align:center;
	}
	div.kudos {
		color:#FF9900;
		text-align:center;
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
	$total_score = $marketing_score[0] + $marketing_score[1] + $developer_score[0] + $developer_score[1];
	$title = $badge." (".$total_score." Points)";
	$img_class = 'class = emblem';
	
?>
<!-- displapy the user's badge  -->
<div class = "emblem">
	&nbsp;&nbsp;&nbsp;<img <?php echo $img_class; ?> src="<?php echo elgg_format_url($vars['url']."mod/karma/default_icons/".$badge.".jpg");?>" border="0"/>
</div>
<div class = "score">
	<?php
		echo $title;
		echo '<br>';
		echo "Tweets : ".$activity[0];
		echo '<br>';
		echo "Bug Fixes : ".$activity[1];
		echo '<br>';
		echo "Planet OpenSUSE posts : ".$activity[2];	
		echo '<br>';
		echo "Build Service Commits : ".$activity[3];
		
	?>
</div>
<?php
	echo '<div class = "kudos">';
	$kudos = $entity->kudos;
	if(!is_null($kudos))
		echo $kudos." - Kudos to You!";
	echo '</div>';
?>
<?php 
	if($guid != $vars['user']->guid) {
?>
		<form action = "<?php echo $vars['url']; ?>action/karma/kudos" method="post">
		<?php 
			echo elgg_view('input/securitytoken'); 
			echo elgg_view('input/hidden',array('internalname' => 'guid','value' => $karma_entity_guid));
			echo elgg_view('input/hidden',array('internalname' => 'kudos','value' => $kudos));
			echo elgg_view('input/submit', array('value' => elgg_echo('Kudos')));
		?>
		</form>
<?php
	}
?>
