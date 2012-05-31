<?php
	function karma_init(){
		global $CONFIG;
		add_widget_type('karma','karma','Find your Karma score');
		//register cron hook to trigger function karma_cron weekly 
		register_plugin_hook('cron','weekly','karma_cron');
	}
	//cron function 
	function karma_cron($hook, $entity_type, $returnvalue, $params) {
		//get all existing users on connect;
		$entities = get_entities('user');
		//for each user calculate karma(this is done weekly).
		foreach ($entities as $entity) {
			//email of each user 
			$email = $entity->email;
			$score  = 0;
			//call to Bugzilla to return resolved bugs
			$csv_url = "https://bugzilla.novell.com/buglist.cgi?bug_status=RESOLVED&bug_status=VERIFIED&email1=$email&emailassigned_to1=1&emailinfoprovider1=1&emailtype1=exact&query_format=advanced&title=Bug%20List&ctype=csv";
			$xmls = file_get_contents($csv_url);
			$arr = explode("\n",$xmls);
			array_shift($arr);
			$max_score = 0;
			$num_of_bugs = 0;
			//now for each bug, check its severity.
			foreach ($arr as $line) {
				$d = explode(',' ,$line, 8);
				$severity = $d[1];
				echo $severity;
				if (strcasecmp($severity,"Blocker") == 0 ) {
					$score += 10;
				} 
				else if (strcasecmp($severity,"Critical") == 0 ) {
					$score += 8;
				}
				else if (strcasecmp($severity,"Major") == 0 ) {
					$score += 7;
				}
				else if (strcasecmp($severity,"Normal") == 0 ) {
					$score += 5;
				}
				else if (strcasecmp($severity,"Minor") == 0 ) {
					$score += 3;
				}
				else if (strcasecmp($severity,"Enhancement") == 0 ){
					$score += 2;
				}
				$num_of_bugs ++;
			}
	
			/*For now the title Bug Squasher is assigned for securing greater than 85% of maximum
	 *  	score, Master Ninja for securing greater than 70% but less than 85% and Novice for the rest.*/ 
			$max_score = $num_of_bugs*10;
			if ($max_score == 0)
				$badge = "Novice";
			else if ($score >= 0.85*$max_score && $score < 0.7*$max_score && $max_score > 0)
			$badge = "Bug Squasher";
				else if ($score >=0.7*$max_score && $score < 0.5*$max_score && $max_score > 0)
			$badge = "Master Ninja";
			
			//create an instance ElggObject classs to store karma details for ach user. 
			$karma = new ElggObject();
			$guid = $entity->guid;
			
			//check if karma object exists for user, if it does then update it.
			$entities = get_entities('object','karma',$guid);
			if(isset($entities)) {
				$karma = $entities[0];
			}
			else {
				$karma->description = "karma score";//TODO:score is not being used, so assign score and work out description.
				$karma->subtype="karma";
				$karma->access_id = ACCESS_PUBLIC;
				$karma->owner_guid = $entity->guid;
			}
			$karma->title = $badge;	
			$karma->save();
		}//end of foreach user
		return true;
	}
	register_elgg_event_handler('init','system','karma_init'); 
?>
