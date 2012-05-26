<?php
function karma_init(){
		global $CONFIG;
		add_widget_type('karma',"karma","Find your Karma score");
		register_entity_type('object','karma');
	}
	register_elgg_event_handler('init','system','karma_init'); 
	register_action("karma/calculate_karma", false, $CONFIG->pluginspath . "karma/actions/calculate_karma.php");
	

?>
