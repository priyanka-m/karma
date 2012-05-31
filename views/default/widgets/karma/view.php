<style type="text/css">
    img.emblem{
		position:relative;
		left:30px;
		height:181px;
		width:150px;
	}
	div.emblem{
		position:relative;
		top:-10px;
	}
</style>
<?php 
	//guid of the user to which the widget belongs.
	$guid = $vars['entity']->owner_guid;
	
	//get karma instance for user.
	$entities = get_entities('object','karma',$guid);
	
	/*isset($entities[0]) should always return true, since the plugin is enabled and the cron 
	 * is being triggered hourly for every user, even if he uses the widget or not */ 
	
	if(isset($entities[0]))
	{
		//karma details exists, print them.	
		$entity = $entities[0];
		$badge = $entity->title;
	}
	$img_class = 'class = emblem';
?>
<!-- displapy the user's badge  -->
<div class = "emblem">
	&nbsp;&nbsp;&nbsp;<img <?php echo $img_class; ?> src="<?php echo elgg_format_url($vars['url']."mod/karma/default_icons/".$badge.".gif");?>" border="0"/>
</div>
<div class = "Score">
	<?php
	echo elgg_view_title($badge);?>
</div>


