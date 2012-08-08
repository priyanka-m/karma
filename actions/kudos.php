<?php
	//allow for read, write access.
	$context = get_context();
	set_context('extend_kudos');	
	$access = elgg_set_ignore_access(true);
	
	//increase number of kudos
	$guid = get_input('guid');
	$max_kudos = get_input('max_kudos');
	$kudos = get_input('kudos');
	
	$entity = get_entity($guid);
	
	//array of time values whenever a kudos was awarded to the user today. 
	$last_kudos_awarded = $entity->last_kudos_awarded;
	
	//if the first kudo was awarded 24 hours back, create a new last_awarded kudos array.
	if(abs(time() - $last_kudos_awarded[0])/86400 > 1) {
		//create array with max_kudos number of elements so that no more than max_kudos , 
		//can be awarded to the user.
		$last_kudos_awarded = array_fill(0, $max_kudos, 0);
	}
	//find if array has space for time values so that a kudos can be extended.
	$index = array_search('0',$last_kudos_awarded);
	
	//if max_kudos number of kudos have not yet been extending then allow to do so, else not.
	if(is_int($index)) {
		$last_kudos_awarded[$index] = time();
		$kudos = $kudos + 1;
	}
	
	$entity->last_kudos_awarded = $last_kudos_awarded;
	$entity->kudos = $kudos;
	$entity->save();
	
	//set permissions back to what they were.
	set_context($context);
	elgg_set_ignore_access($access);
	forward(REFERER);
?>
