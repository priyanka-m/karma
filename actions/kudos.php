<?php
	//allow for read, write access.
	$context = get_context();
	set_context('extend_kudos');	
	$access = elgg_set_ignore_access(true);
	
	//increase number of kudos
	$guid = get_input('guid');
	$loggedin_karma_guid = get_input('loggedin_karma_guid');
	$max_kudos = get_input('max_kudos');
	$kudos = get_input('kudos');
	$sender = get_input('kudos_giver');
	
	$entity = get_entity($guid);
	$loggedin_karma_entity = get_entity($loggedin_karma_guid);
	
	//array of time values whenever a kudos was awarded to the user today. 
	$last_kudos_awarded = $loggedin_karma_entity->last_kudos_awarded;
	//check if kudos extenders array exists or not or has a single record.
	if (!is_array($entity->kudos_sender)) {
		if (is_null($entity->kudos_sender))
			$senders = array();
		else
			$senders = array($entity->kudos_sender);
	}
	else
		$senders = $entity->kudos_sender;
	
	//if the first kudo was awarded 24 hours back, create a new last_awarded kudos array.
	if (abs(time() - $last_kudos_awarded[0])/86400 > 1) {
		/*create array with max_kudos number of elements so that no more 
		 * than max_kudos, can be awarded to the user.*/
		$last_kudos_awarded = array_fill(0, $max_kudos, 0);
	}
	//find if array has space for time values so that a kudos can be extended.
	$index = array_search('0',$last_kudos_awarded);
	
	/*if max_kudos number of kudos have not yet been extended then allow 
	 * to do so, else not.*/
	if (is_int($index)) {
		$last_kudos_awarded[$index] = time();
		$kudos = $kudos + 1;
		//check if the current kudos extender is in the list of kudos extenders or not.
		$search = array_search($sender, $senders);
		//if not then make an entry.
		if (!is_int($search))
			$senders[] = $sender;
	}
	//entry of the time at which kudos was awarded.
	$loggedin_karma_entity->last_kudos_awarded = $last_kudos_awarded;
	//entry of the kudos extenders.
	$entity->kudos_sender = $senders;
	$entity->kudos = $kudos;
	//each kudos fetches 1 point which is added in the total score.
	$entity->total_score += 1;
	$entity->save();
	$loggedin_karma_entity->save();
	
	//set permissions back to what they were.
	set_context($context);
	elgg_set_ignore_access($access);
	forward(REFERER);
?>
