<?php
	elgg_extend_view('metatags', 'karma/metatags_ext'); 
	
	$badge = $vars['entity']->title;?>
	<div class = "badge">
		
	<?php echo elgg_view_title($badge);?>
	</div>
	<?php
	
	if ($icontime = $vars['entity']->icontime) {
		$icontime = "{$icontime}";
	} else {
		$icontime = "default";
	}

	if (!empty($vars['align'])) {
		$align = " align=\"{$vars['align']}\" ";
	} else {
		$align = "";
	}
		
	$img_class = '';		
?>

<br/>
<div class="usericon">
	&nbsp;&nbsp;&nbsp;<img <?php echo $img_class; ?> src="<?php echo elgg_format_url($vars['url']."mod/karma/default_icons/".$badge.".jpg");?>" border="0" <?php echo $align; ?> title="<?php echo $vars['entity']->title; ?>" <?php echo $vars['js']; ?> />
</div>
<br/>

<div class = "contentWrapper">
	<div class = "scoreDescription">
		<p><?php echo $vars['entity']->description; ?></p>
	</div>
</div>
<div class = "scoreBoard">
	<?php echo elgg_view_title("Score Board");?>
</div>

