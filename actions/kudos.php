<?php
	$guid = get_input('guid');
	$kudos_given = get_input('kudos');
	$kudos_given = $kudos_given+1;
	$entity = get_entity($guid);
	$entity->kudos = $kudos_given;
	$entity->save();
?>
