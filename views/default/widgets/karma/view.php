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
	
	$guid = get_loggedin_userid();
	
	//get karma instance for loggedin user.
	$entities = get_entities('object','karma',$guid);
	$entity = $entities[0];
	
	$badge = $entity->title;
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


