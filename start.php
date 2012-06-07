<?php
	function karma_init(){
		add_widget_type('karma','karma','Find your Karma score');
		//override permissions for the karma context
		register_plugin_hook('permissions_check', 'all', 'karma_permissions_check');
		//register cron hook to trigger function karma_cron hourly 
		register_plugin_hook('cron','hourly','karma_cron');
	}
	
	//cron function 
	function karma_cron($hook, $entity_type, $returnvalue, $params) {
		global $CONFIG;
		$total_score = 0;
		//get current context and set context to karma_cron so that cron has write permissions. 
		$context = get_context();
		set_context('karma_cron');	
		//allow cron for read access
		$access = elgg_set_ignore_access(true);	
		//get all existing users on connect;
		$entities = get_entities('user');
		
		//for each user calculate karma(this is done hourly).
		foreach ($entities as $entity) {
			//email of each user 
			$email = $entity->email;
			//twitter screen name of each user
			$twitter_screen_name = $entity->twitter;
			
			$bugzilla_score = bugzilla_score($email);
			$badge = $bugzilla_score[0];
			$score = $bugzilla_score[1];
			
			$twitter_score = twitter_score($twitter_screen_name);
			
			//total score
			$total_score = $twitter_score + $score;
			
			//create an instance of ElggObject class to store karma details for ach user. 
			$karma = new ElggObject();
			$guid = $entity->guid;
			
			//check if karma object exists for user, if it does then update it.
			$entities = get_entities('object','karma',$guid);
			if(isset($entities[0])) {
				$karma = $entities[0];
			}
			//when karma details do not exist
			else {
				$karma->description = "karma score";//TODO:work out description.
				$karma->subtype="karma";
				$karma->access_id = ACCESS_PUBLIC;
				$karma->owner_guid = $guid;
			}	
			$karma->title = $badge;	
			$karma->score = $total_score;
			$karma->save();
			
		}//end of foreach user
		set_context($context);
		elgg_set_ignore_access($access);

		$result = "karma updated";
		return $result;
	}
	
	function find_max_score() {
		//form an array of scores and call max function
		$score = array();
		$entities = get_entities('object','karma');
		if(is_null($entities[0])) {
			return 0;
		}
		else {
			foreach ($entities as $entity) {
				$score[] = $entity->score;
			}
			$max_score = max($score);
			return $max_score;
		}
	}
	
	//calculate score through Bugzilla
	function bugzilla_score($email) {
		$score  = 0;
		//call to Bugzilla to return resolved bugs
		$csv_url = "https://bugzilla.novell.com/buglist.cgi?bug_status=RESOLVED&bug_status=VERIFIED&email1=$email&emailassigned_to1=1&emailinfoprovider1=1&emailtype1=exact&query_format=advanced&title=Bug%20List&ctype=csv";
		$xmls = file_get_contents($csv_url);
		$arr = explode("\n",$xmls);
		array_shift($arr);
		$max_score = find_max_score();//max_score is maximum score of all users.
			
		//now for each bug, check its severity.
		foreach ($arr as $line) {
			$d = explode(',' ,$line, 8);
			$severity = $d[1];
			if (strcasecmp($severity,'"Blocker"') == 0 ) {
				$score += 10;
			} 
			else if (strcasecmp($severity,'"Critical"') == 0 ) {
				$score += 8;
			}
			else if (strcasecmp($severity,'"Major"') == 0 ) {
				$score += 7;
			}
			else if (strcasecmp($severity,'"Normal"') == 0 ) {
				$score += 5;
			}
			else if (strcasecmp($severity,'"Minor"') == 0 ) {
				$score += 3;
			}
			else if (strcasecmp($severity,'"Enhancement"') == 0 ){
				$score += 2;
			}
		}
	
		/* For now the title Bug squasher is assigned for securing maximum score 
		* Bugzilla Viking is assigned for securing greater than 85% of maximum
		* score, Master Ninja for securing greater than 70% but less than 85% and 
		* Novice for the rest.*/ 
		if ($max_score == 0 && $score == 0)
			$badge = "Novice";
		else if ($score >= $max_score && $max_score >= 0 && $score != 0)
			$badge = "Bug Squasher";
		else if ($score >= 0.85*$max_score && $score < $max_score && $max_score > 0)
			$badge = "Bugzilla Viking";
		else if ($score >=0.5*$max_score && $score < 0.7*$max_score && $max_score > 0)
			$badge = "Master Ninja";
		else if ($score < 0.5*$max_score || $score == 0 )
			$badge = "Novice";
		
		$bugzilla_score = array();
		$bugzilla_score[0] = $badge;
		$bugzilla_score[1] = $score;
		
		return $bugzilla_score;
	}
	
	//calculates score based on user-tweets on twitter.
	function twitter_score($twitter_screen_name) {
		$score = 0;
		//call to twitter, returns user's last 50 tweets.
		$json = file_get_contents("https://api.twitter.com/1/statuses/user_timeline.json?include_entities=true&include_rts=true&screen_name=$twitter_screen_name&count=50");
		$file = json_decode($json,'true');
		foreach($file as $key=>$value) {
			foreach ( $value['entities'] as $entity=>$entity_array )
			{	
				//look for opensuse hashtag
				if (strcmp($entity,"hashtags") == 0 )
				{
					foreach ($entity_array as $index=>$hashtag)
					{
						if(stripos($hashtag['text'],"openSUSE") !== false)
							$score += 2;
					}		
				}
			}
		}
		return $score;
	}
	
	//Overrides default permissions for the karma context
	function karma_permissions_check($hook_name, $entity_type, $return_value, $parameters) {	
		if (get_context() == 'karma_cron') {
			return true;
		}
		return null;
	} 
	
	//Initialize plugin.
	register_elgg_event_handler('init','system','karma_init'); 
?>
