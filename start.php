<?php
	function karma_init(){
		add_widget_type('karma','Karma','Find your Karma score');
		//override permissions for the karma context
		register_plugin_hook('permissions_check', 'all', 'karma_permissions_check');
		//register cron hook to trigger function karma_cron daily 
		register_plugin_hook('cron','daily','karma_cron');
	}
	
	//cron function 
	function karma_cron($hook, $entity_type, $returnvalue, $params) {
		global $CONFIG;
		//get current context and set context to karma_cron so that cron has write permissions. 
		$context = get_context();
		set_context('karma_cron');	
		//allow cron for read access
		$access = elgg_set_ignore_access(true);	
		//get all existing users on connect;
		$entities = get_entities('user');
		
		//for each user assign karma score(this is done daily).
		foreach ($entities as $entity) {
			//email of each user 
			$email = $entity->email;
			//twitter screen name of each user
			$twitter_screen_name = $entity->twitter;
			
			//bugzilla score
			$bugzilla = bugzilla_score($email);
			$bugzilla_score = $bugzilla[0];
			$num_of_bugs_fixed = $bugzilla[1];
			
			//twitter score
			$twitter = twitter_score($twitter_screen_name);
			$twitter_score = $twitter[0];
			$num_of_tweets = $twitter[1];
			
			//total score
			$total_score = array();
			$total_score[0] = $twitter_score;
			$total_score[1] = $bugzilla_score;
			
			//create an instance of ElggObject class to store karma score for each user. 
			$karma = new ElggObject();
			$guid = $entity->guid;
			
			//check if karma object exists for user, if it does then update it.
			$entities = get_entities('object','karma',$guid);
			if(isset($entities[0])) {
				$karma = $entities[0];
			}
			//when karma details do not exist
			else {
				$karma->title = $entity->name;
				$karma->description = "Karma Score";
				$karma->subtype="karma";
				$karma->access_id = ACCESS_PUBLIC;
				$karma->owner_guid = $guid;
			}	
			$karma->score = $total_score;
			$karma->activity = array($num_of_tweets,$num_of_bugs_fixed);
			$karma->save();	
		}//end of foreach user
		
		//after calculating score for all users, assign badges to each.
		$karma_entities = get_entities('object','karma');
		foreach ($karma_entities as $karma_entity) {
			//send each user's score to assign badge.
			$badge = assign_badge($karma_entity->score);
			$karma_entity->badge = $badge;
			$karma_entity->save();
		}
		//set context and acsess rights back to what they were originally.
		set_context($context);
		elgg_set_ignore_access($access);

		$result = "karma updated";
		return $result;
	}
	
	//calculate score through Bugzilla
	function bugzilla_score($email) {
		$score  = 0;
		$num_of_bugs_fixed = 0;
		//call to Bugzilla to return resolved bugs
		$csv_url = "https://bugzilla.novell.com/buglist.cgi?bug_status=RESOLVED&bug_status=VERIFIED&email1=$email&emailassigned_to1=1&emailinfoprovider1=1&emailtype1=exact&query_format=advanced&title=Bug%20List&ctype=csv";
		$xmls = file_get_contents($csv_url);
		$arr = explode("\n",$xmls);
		array_shift($arr);
			
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
			$num_of_bugs_fixed ++;
		}
		//if the user has not confirmed his email id on bugzilla, file_get_contents returns a page, 
		//not a list of bugs.
		if ($score == 0) {
				$num_of_bugs_fixed = 0;
		}
		
		$bugzilla_score = array();
		$bugzilla_score[0] = $score;
		$bugzilla_score[1] = $num_of_bugs_fixed;
		
		return $bugzilla_score;
	}
	
	//calculates score based on user-tweets on twitter.
	function twitter_score($twitter_screen_name) {
		$score = 0;
		$num_of_tweets = 0;
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
						{
							$score += 2;
							$num_of_tweets ++;
						}	
					}		
				}
			}
		}
		$twitter_score = array();
		$twitter_score[0] = $score;
		$twitter_score[1] = $num_of_tweets;
		
		return $twitter_score;
	}
	
	//finding maximum bugzilla score.
	function find_max_score() {
		//form an array of bugzilla scores and call max function.
		$score = array();
		$entities = get_entities('object','karma');
		if(is_null($entities[0])) {
			return 0;
		}
		else {
			foreach ($entities as $entity) {
				$score_array = $entity->score;
				$score[] = $score_array[1]; //bugzilla score.
			}
			$max_score = max($score);
			return $max_score;
		}
	}
	
	function assign_badge($total_score) {
		$max_score = find_max_score();//max_score is maximum bugzilla score of all users.
		/* For now the title SUSE Spartan is assigned for securing maximum bug fixing score 
		 * and making the most tweets, Bug squasher is assigned for securing maximum bug fixing score 
		* Bugzilla Viking is assigned for securing greater than 85% of maximum
		* score, Master Ninja for securing greater than 70% but less than 85% and Enthusiast for
		* tweeting but not making any bug fixes and Novice for the rest.*/ 
		
		$bugzilla_score = $total_score[1];
		$twitter_score = $total_score[0];
		
		if ($bugzilla_score == 0 && $twitter_score == 0)
			$badge = "Novice";
		else if ($twitter_score > 0 && $bugzilla_score == 0)
			$badge = "Enthusiast";
		else if ($bugzilla_score >= $max_score && $twitter_score >= 30)
			$badge = "SUSE Spartan";
		else if ($bugzilla_score >= $max_score)
			$badge = "Bug Squasher";
		else if ($bugzilla_score >= 0.85*$max_score && $bugzilla_score < $max_score)
			$badge = "Bugzilla Viking";
		else if ($bugzilla_score >=0.5*$max_score && $bugzilla_score < 0.7*$max_score)
			$badge = "Master Ninja";
		else if ($bugzilla_score < 0.5*$max_score && $bugzilla_score > 0 )
			$badge = "Novice";
		
		return $badge;
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
