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
			//guid of each user
			$guid = $entity->guid;
			//email of each user 
			$email = $entity->email;
			//twitter screen name of each user
			$twitter_screen_name = $entity->twitter;
			
			//bugzilla score
			$bugzilla = bugzilla_score($email);
			$bugzilla_score = $bugzilla[0];
			$num_of_bugs_fixed = $bugzilla[1];
			
			//twitter score
			$twitter = twitter_score($twitter_screen_name,$guid);
			$twitter_score = $twitter[0];
			$num_of_tweets = $twitter[1];
			
			//planet opensuse score
			$planet_opensuse = planet_opensuse_score($entity->blog,$guid);
			$planet_opensuse_score = $planet_opensuse[0];
			$num_of_posts = $planet_opensuse[1];
			
			
			//create an instance of ElggObject class to store karma score for each user. 
			$karma = new ElggObject();
			
			//check if karma object exists for user, if it does then update it.
			$entities = get_entities('object','karma',$guid);
			if(isset($entities[0])) {
				$karma = $entities[0];
				$old_activity = $karma->activity;
				$old_marketing_score = $karma->marketing_score;
				$karma->developer_score = $bugzilla_score;
				$karma->marketing_score = array($old_marketing_score[0] + $twitter_score, $old_marketing_score[1] + $planet_opensuse_score);
				$karma->activity = array($num_of_tweets + $old_activity[0],$num_of_bugs_fixed,$num_of_posts + $old_activity[2]);	
			}
			//when karma details do not exist before
			else {
				$karma->title = $entity->name;
				$karma->description = "Karma Score";
				$karma->subtype="karma";
				$karma->marketing_score = array($twitter_score,$planet_opensuse_score);
				$karma->developer_score = $bugzilla_score;
				$karma->activity = array($num_of_tweets,$num_of_bugs_fixed,$num_of_posts);
				$karma->access_id = ACCESS_PUBLIC;
				$karma->owner_guid = $guid;
			}	
			$karma->last_updated = time();
			$karma->save();	
		}//end of foreach user
		
		//after calculating score for all users, assign badges to each.
		$karma_entities = get_entities('object','karma');
		foreach ($karma_entities as $karma_entity) {
			//send each user's score to assign badge.
			$badge = assign_badge($karma_entity->developer_score,$karma_entity->marketing_score);
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
	function twitter_score($twitter_screen_name,$guid) {
		$score = 0;
		$num_of_tweets = 0;
		//call to twitter, returns user's last 50 tweets.
		$json = file_get_contents("https://api.twitter.com/1/statuses/user_timeline.json?include_entities=true&include_rts=true&screen_name=$twitter_screen_name&count=5");
		$file = json_decode($json,'true');
		foreach ($file as $key=>$value) {
			foreach ($value['entities'] as $entity=>$entity_array) {	
				//look for opensuse hashtag
				if (strcmp($entity,"hashtags") == 0 ) {
					foreach ($entity_array as $index=>$hashtag)	{
						if (stripos($hashtag['text'],"openSUSE") !== false) {
							//checks the time of publishing, should be after the last update.
							$check = check_date($value['created_at'],$guid);
							if ($check == true) {
								$score += 2;
								$num_of_tweets ++;
							}
						}	
					}		
				}
			}
		}
		$twitter_score = array($score,$num_of_tweets);
		
		return $twitter_score;
	}
	
	//planet opensuse score
	function planet_opensuse_score($blog_url,$guid) {
		$score = 0;
		$num_of_posts = 0;
		$rss = new SimpleXMLElement('http://planet.opensuse.org/en/rss20.xml', null, true);
		$item = $rss->xpath('channel/item');
		foreach($rss->xpath('channel/item') as $item) {
			if(stripos($item->link,$blog_url) !== False) {
				//checks the time of publishing, should be after the last update.
				$check = check_date($item->pubDate,$guid);
				if ($check == true) {
					$score += 5;
					$num_of_posts ++;
				}
			}
		}
		$planet_opensuse = array($score,$num_of_posts);
		
		return $planet_opensuse;
	}
	
	//finding maximum bugzilla and marketing score.
	function find_max_score() {
		$bugzilla_scores = array();
		$marketing_scores = array();
		$entities = get_entities('object','karma');
		if(is_null($entities[0])) {
			return 0;
		}
		else {
			foreach ($entities as $entity) {
				$developer_scores[] = $entity->developer_score;//bugzilla score.
				$marketing_array = $entity->marketing_score;//marketing score, array of twitter and planet openSUSE scores.
				$marketing_scores[] = $marketing_array[0] + $marketing_array[1];
			}
			$max_developer = max($developer_scores);
			$max_marketing = max($marketing_scores);
			
			$max_score = array($max_developer,$max_marketing);
			
			return $max_score;
		}
	}
	
	//checks if time of publishing is greater than last update.
	function check_date($date,$guid) {
		$timestamp = strtotime($date);
		$entities = get_entities('object','karma',$guid);
		if(isset($entities[0])) {
			$karma = $entities[0];
			$last_updated = $karma->last_updated;
			if ($timestamp > $last_updated)
				return true;
			else
				return false;
		}
		else {
			return true;
		}	
	}
	
	//assigns badge given marketing and developer score
	function assign_badge($developer,$marketing) {
		
		$bugzilla_score = $developer;
		$twitter_score = $marketing[0];
		$planet_opensuse_score = $marketing[1];
		
		$max_score = find_max_score();
		$max_developer = $max_score[0];
		$max_marketing = $max_score[1];
		
		$marketing_score = $twitter_score + $planet_opensuse_score;
		if($bugzilla_score == 0 && $planet_opensuse_score == 0 && $twitter_score == 0 )
			$badge = "Novice";

		else if ($bugzilla_score >= $marketing_score) {
			if ($bugzilla_score >= 0.75*$max_developer && $bugzilla_score <= $max_developer)
				$badge = "SUSE Samurai";
			else if ($bugzilla_score >=0.5*$max_developer && $bugzilla_score < 0.7*$max_developer)
				$badge = "Bug Buster";
			else if ($bugzilla_score < 0.5*$max_developer && $bugzilla_score > 0 )
				$badge = "Notable Endeavor";
		}
	
		else if ($marketing_score > $bugzilla_score) {
			if ($marketing_score >= 0.75*$max_marketing && $marketing_score <= $max_marketing)
				$badge = "SUSE Herald";
			else if ($marketing_score >= 0.5*$max_marketing && $marketing_score < 0.75*$max_marketing)
				$badge = "Enthusiast";
			else {
				if ($planet_opensuse_score > $twitter_score)
					$badge = "Blog-o-Manic";
				else
					$badge = "Twitteratti";
			}

		}
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
