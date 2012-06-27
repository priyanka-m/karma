<?php
	function karma_init(){
		add_widget_type('karma','Karma','Find your Karma score');
		//override permissions for the karma context
		register_plugin_hook('permissions_check', 'all', 'karma_permissions_check');
		//register cron hook to trigger function karma_cron every five minutes. 
		register_plugin_hook('cron','fiveminute','karma_cron');
	}
	
	//cron function 
	function karma_cron($hook, $entity_type, $returnvalue, $params) {
		global $CONFIG;
		
		//get 5 user entities who have been longest without updating.
		$entities = elgg_get_entities_from_metadata(array(
					'types' => 'user',
					'limit' => '5',
					'metadata_names' => 'karma_update_time',
					'order_by_metadata' => array(
					'name' => 'karma_update_time',
					'direction' => 'ASC',
					'as' => integer) ));
		
		//for each user update karma score(this is done every five minutes).
		foreach ($entities as $entity) {
			karma_update($entity->guid);
		}

		$result = "karma updated";
		return $result;
	}
	
	//function to update karma for each user, also on widget view.
	function karma_update($guid) {
		//get current context and set context to karma_update_for_user so that karma has write permissions.
		$context = get_context();
		set_context('karma_update_for_user');	
		//allow karma_update for read access
		$access = elgg_set_ignore_access(true);
		
		//get the user entity and then fetch twitter screen name, email id and blog url.
		$user = get_entity($guid);
		$email = $user->email;
		$twitter_screen_name = $user->twitter;
		$blog_url = $user->blog;
		
		//bugzilla score
		$bugzilla = bugzilla_score($email);
		$bugzilla_score = $bugzilla[0];
		$num_of_bugs_fixed = $bugzilla[1];
			
		//twitter score
		$twitter = twitter_score($twitter_screen_name,$guid);
		$twitter_score = $twitter[0];
		$num_of_tweets = $twitter[1];
			
		//planet opensuse score
		$planet_opensuse = planet_opensuse_score($blog_url,$guid);
		$planet_opensuse_score = $planet_opensuse[0];
		$num_of_posts = $planet_opensuse[1];
			
		//check if karma object exists for user, if it does then update it.
		$entities = get_entities('object','karma',$guid);
		
		if(isset($entities[0])) {
			$karma = $entities[0];
			$old_activity = $karma->activity;
			$old_marketing_score = $karma->marketing_score;
		}
		//when karma details do not exist before
		else {
			//create an instance of ElggObject class to store karma score for each user. 
			$karma = new ElggObject();
			
			$karma->title = $current_user->name;
			$karma->description = "Karma Score";
			$karma->subtype="karma";
			$karma->access_id = ACCESS_PUBLIC;
			$karma->owner_guid = $guid;
			
			$old_marketing_score = array(0,0);
			$old_activity = array(0,0,0);
		}
		//update marketing and developer score.
		$karma->developer_score = $bugzilla_score;
		$karma->marketing_score = array($old_marketing_score[0] + $twitter_score, $old_marketing_score[1] + $planet_opensuse_score);
		$karma->activity = array($num_of_tweets + $old_activity[0],$num_of_bugs_fixed,$num_of_posts + $old_activity[2]);	
		
		//pass developer and marketing score to check if current user score is max score, and return max score.
		$max_score = calculate_max_score($karma->developer_score,$karma->marketing_score);
		
		//assign badge to user with the help of user score and max score.
		$badge = assign_badge($bugzilla_score,$karma->marketing_score,$max_score);
		$karma->badge = $badge;
		$karma->save();
		
		//update metadata field to keep track of last updation.
		$user->karma_update_time = time();
		$user->save();
		
		//set context and acsess rights back to what they were originally.
		set_context($context);
		elgg_set_ignore_access($access);
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
		//call to twitter, returns user's last 5 tweets.
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
	
	//finding maximum developer and marketing score.
	function calculate_max_score($developer_score,$marketing_score) {
		$marketing_score = $marketing_score[0] + $marketing_score[1];
		
		//get maximum karma score object.
		$entities = elgg_get_entities(array('type'=>'object', 'subtype'=>'max_karma'));
		
		if(!isset($entities[0])) {
			$max_karma = new ElggObject();
			$max_karma->subtype="max_karma";
			$max_karma->access_id = '0';
			$max_karma->max_developer = $developer_score;
			$max_karma->max_marketing = $marketing_score;
		}
		/*if maximum karma score object exists then find maximum of the score passed as 
		 * argument and previously existing maximum score.*/
		else {
			$max_karma = $entities[0];
			$max_karma->max_developer = max($max_karma->max_developer,$developer_score);
			$max_karma->max_marketing = max($max_karma->max_marketing,$marketing_score);
		}
		$max_karma->save();
		
		return array($max_karma->max_developer,$max_karma->max_marketing); 
	}
	
	//checks if time of publishing is greater than last update.
	function check_date($date,$guid) {
		$timestamp = strtotime($date);
		$entity = get_entity($guid);
		if(isset($entity)) {
			$last_updated = $entity->karma_update_time;
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
	function assign_badge($developer,$marketing,$max_score) {
		
		$bugzilla_score = $developer;
		$twitter_score = $marketing[0];
		$planet_opensuse_score = $marketing[1];
		
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
