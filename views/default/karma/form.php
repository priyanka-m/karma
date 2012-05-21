<div class="contentWrapper">
	<form action="<?php echo $vars['url']; ?>action/karma/calculate_karma" method="post">
 
		<p><?php echo elgg_echo("Twitter Username"); ?><br />
		<?php echo elgg_view('input/text',array('internalname' => 'twitter_username')); ?></p>
 
		<p><?php echo elgg_echo("Planet openSUSE Username"); ?><br />
		<?php echo elgg_view('input/text',array('internalname' => 'planet_username')); ?></p>
 
		<p><?php echo elgg_echo("Bugzilla Username"); ?><br />
		<?php echo elgg_view('input/text',array('internalname' => 'bugzilla_username')); ?></p>
 
		<?php echo elgg_view('input/securitytoken'); ?>
 
		<p><?php echo elgg_view('input/submit', array('value' => elgg_echo('Find My Karma Score'))); ?></p>
 
	</form>
</div>
