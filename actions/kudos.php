<?php
	//allow for read, write access.
	$context = get_context();
	set_context('extend_kudos');	
	$access = elgg_set_ignore_access(true);
	
	//increase number of kudos
	$guid = get_input('guid');
	$kudos_given = get_input('kudos');
	$kudos_given = $kudos_given+1;
	$entity = get_entity($guid);
	$entity->kudos = $kudos_given;
	$entity->save();
	
	//set permissions back to what they were.
	set_context($context);
	elgg_set_ignore_access($access);
	forward(REFERER);
?>
