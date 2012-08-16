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
		$context = get_context();
		set_context('karma_update');	
		//allow karma_update for read access
		$access = elgg_set_ignore_access(true);
		//get 5 user entities who have been longest without updating.
		$entities = elgg_get_entities_from_metadata(array(
					'types' => 'user',
					'limit' => '5',
					'metadata_names' => 'karma_update_time',
					'order_by_metadata' => array(
					'name' => 'karma_update_time',
					'direction' => 'ASC',
					'as' => integer) ));
		
		//call function to update karma score for latest updated packages.
		build_service_update();
	
		//for each user update karma score(this is done every five minutes).
		foreach ($entities as $entity) {
			karma_update($entity->guid,'0','0');
		}
		//find user ranks based on overall score.
		karma_rank();
		
		//set context and read access rights back to what they were originally.
		set_context($context);
		elgg_set_ignore_access($access);
		
		$result = "karma updated";
		return $result;
	}
	
	//function to update karma for each user, also on widget view.
	function karma_update($guid, $obs_score , $commit ) {
		/*get current context and set context to karma_update_for_user 
		 * so that karma has write permissions.*/
		$context = get_context();
		set_context('karma_update_for_user');	
		//allow karma_update for read access
		$access = elgg_set_ignore_access(true);
		
		/*get the user entity and then fetch username, twitter screen name, 
		 * email id and blog url.*/
		$user = get_entity($guid);
		$email = $user->email;
		$twitter_screen_name = $user->twitter;
		$blog_url = $user->blog;
		$username = $user->username;
		
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
		
		//openSUSE wiki score
		$wiki = wiki_score($username,$guid);
		$wiki_score = $wiki[0];
		$num_of_edits = $wiki[1];	
			
		//check if karma object exists for user, if it does then update it.
		$entities = get_entities('object','karma',$guid);
		if(isset($entities[0])) {
			$karma = $entities[0];
			$old_activity = $karma->activity;
			$old_developer_score = $karma->developer_score;
			$old_marketing_score = $karma->marketing_score;
			$old_wiki_score = $karma->wiki_score;
		}
		//when karma details do not exist before
		else {
			//create an instance of ElggObject class to store karma score for each user. 
			$karma = new ElggObject();
			
			$karma->title = $user->name;
			$karma->description = "Karma Score";
			$karma->subtype="karma";
			$karma->access_id = ACCESS_PUBLIC;
			$karma->owner_guid = $guid;
			
			$old_developer_score = array(0,0);
			$old_marketing_score = array(0,0);
			$old_wiki_score = 0;
			$old_activity = array(0,0,0,0,0);
		}
		//update marketing and developer score.
		$karma->wiki_score = $old_wiki_score + $wiki_score;
		$karma->developer_score = array($bugzilla_score , $obs_score + $old_developer_score[1]);
		$karma->marketing_score = array($old_marketing_score[0] + $twitter_score, $old_marketing_score[1] + $planet_opensuse_score);
		$karma->activity = array($num_of_tweets + $old_activity[0],$num_of_bugs_fixed,$num_of_posts + $old_activity[2], $commit + $old_activity[3], $num_of_edits + $old_activity[4]);	
		$karma->total_score = $karma->developer_score[0] + $karma->developer_score[1] + $karma->marketing_score[0] + $karma->marketing_score[1] + $wiki_score + $karma->kudos;
		
		//pass developer and marketing score to check if current user score is max score, and return max score.
		$max_score = calculate_max_score($karma->developer_score,$karma->marketing_score);
		
		//assign badge to user with the help of user score and max score.
		$badge = assign_badge($karma->developer_score,$karma->marketing_score,$karma->wiki_score,$max_score);
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
		$rss = new SimpleXMLElement('http://planet.opensuse.org/global/rss20.xml', null, true);
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
	
	//function to update karma score on making commits in Build service.
	function build_service_update() {
		//call the build service api to get latest updated packages.
		$url = "https://api.opensuse.org/statistics/latest_updated?limit=10";
		$data = curl_request_to_api($url);
		//Use the DOMDocument class to represent the fetched document in XML.
		$dom = new DOMDocument;
		$dom->loadXML($data); 
		$xpath = new DOMXPath($dom);
		$result_packages = $xpath->query('/latest_updated/package');
		foreach($result_packages as $r) {
			$score = 0;
			$commits = 0;
			$project = $r->getAttribute('project');
			$package = $r->getAttribute('name');
			//call the build service api to fetch source files of the package.
			$url = "https://api.opensuse.org/source/".$project."/".$package;
			$source_files = curl_request_to_api($url);
			$dom_doc = new DOMDocument;
			$dom_doc->loadXML($source_files); 
			$xp = new DOMXPath($dom_doc);
			//read the list to source files to find the .changes file.
			$result_source_files = $xp->query('/directory/entry');
			//emails of users who are to be rewarded for their commits.
			$array_emails = array();
			foreach ($result_source_files as $result_source_file) {
				if (strpos ( $result_source_file->getAttribute('name'),".changes" )) {
					$name_of_changes_file = $result_source_file->getAttribute('name');
					//cal the build api to read the changes file.
					$url = "https://api.opensuse.org/source/".$project."/".$package."/".$name_of_changes_file;
					$data = curl_request_to_api($url);
					//break all file into separate results.
					$commit_details = explode("-------------------------------------------------------------------",$data);
					foreach ($commit_details as  $commit_detail) {
						/*process changes file to fetch email of users who have made commits 
						 * after the last update. */
						$changes = process_changes_file($commit_detail);
						if(!is_null($changes))
							$arr[] = $changes; //put the returned emails in an array.
					}
					/*use array_count_values to return how many times each user has made a commit.
					 * (A user can make commits more than one time). results are of the form 
					 * arr['email_id'] => number of occurences after the last update in the changes file  */
					$count = array_count_values($arr);
					foreach($count as $key=>$value) {
						$users = get_user_by_email($key);
						$user = $users[0];
						$score = $value*5; //multiply number of commits by 5 to return score.
						karma_update($user->guid,$score,$value); //update karma for that user.
					}
				}
			}
		}
	}
	
	//function to make cURL GET requests to the OBS API.
	function curl_request_to_api($url) {
		$headers = array("Authorization: Basic cHJpeWFua2FfbToxMjNhYmNkY29kZXIxMjM=");
		$ch = curl_init($url); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);               
		curl_setopt($ch,CURLOPT_HEADER,0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);  
		$data = curl_exec($ch); 
		if (curl_errno($ch)) 
			print "Error: " . curl_error($ch);
		else 
			curl_close($ch);
		return $data;
	}
	
	//function to return the position of nth occurence of needle in the haystack.
	function nthstrpos($haystack, $needle, $nth) {
		$place = -1;
		for ($i = 0; $i < $nth; $i++) 
			$place = strpos($haystack, $needle, $place + 1);
		return $place;
	}
	
	/*process the changes file to fetch emails of those users who have made commits 
	 * after their respective last update time. */
	function process_changes_file($commit_detail) {
		$pos = strpos($commit_detail,"-");
		if($pos == 0)
			$pos_ = nthstrpos($commit_detail,"-",3);
		else	
			$pos_ = nthstrpos($commit_detail, "-", 2);
		if($pos_ == -1 )
			$pos_ = nthstrpos($commit_detail,"+",1);
		$len = $pos_ + 1;
		$str = substr($commit_detail,0,$len);
		$str = ltrim($str,"-");
		$line = explode("-",$str);
		$time = $line[0];
		$email = $line[1];
		$users = get_user_by_email($email);
		$user = $users[0];
		if(isset($user)) {
			$guid = $user->guid;
			$check_date = check_date($time,$guid);
		}
		//check if time is greater than last update only then return the email.
		if($check_date == true)
			return $email;
		else
			return null;
	}
	
	//openSUSE wiki score
	function wiki_score($username,$guid) {
		$total_score = 0;
		$total_num_of_edits = 0;
		//brute-forced from http://i18n.opensuse.org/stats/trunk/index.php
		$locales = array('cs','cz','de','el','en','es','fi','fr','hu','it','ja','nl','pl','pt','ru','sv','tr','vi','zh');
		foreach ($locales as $locale) {
			$score = 0;
			$num_of_edits = 0;
			$url = "http://$locale.opensuse.org/index.php?title=Special:Contributions/".$username."&feed=atom&deletedOnly=&limit=10&target=".$username."&topOnly=&year=&month=";
			$dom_doc = new DOMDocument();
			$html_file = file_get_contents($url);
			$dom_doc->loadHTML( $html_file );
			// Get all references to <updated> tag
			$tags_updated = $dom_doc->getElementsByTagName('updated');
			// Extract text value and replace with something else
			foreach($tags_updated as $tag) {
				$tag_value = $tag->nodeValue;
				// get translation of tag_value
				$time = str_replace("T"," ",str_replace("Z","",$tag_value));
				$check = check_date($time,$guid);
				if ($check == true) {
					$score += 2;
					$num_of_edits ++;
				}
			}
			//subtract on account of an extra update tag outside all entry tags, which is the feed's 'updated' tag.
			if ($num_of_edits > 0 && $score > 0) {
				$num_of_edits -= 1;
				$score -= 2;
			}
			
			$total_score += $score;
			$total_num_of_edits += $num_of_edits;
		} // end locales loop
		
		$wiki_score = array($score,$total_num_of_edits);
		return $wiki_score;
	}
	
	//finding maximum developer and marketing score.
	function calculate_max_score($developer_score,$marketing_score) {
		$marketing_score = $marketing_score[0] + $marketing_score[1];
		$developer_score = $developer_score[0] + $developer_score[1];
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
	function assign_badge($developer,$marketing,$wiki_score,$max_score) {
		$bugzilla_score = $developer[0];
		$build_service_score = $developer[1];
		$twitter_score = $marketing[0];
		$planet_opensuse_score = $marketing[1];
		
		$max_developer = $max_score[0];
		$max_marketing = $max_score[1];
		
		$marketing_score = $twitter_score + $planet_opensuse_score;
		$developer_score = $bugzilla_score + $build_service_score;
		if($bugzilla_score == 0 && $planet_opensuse_score == 0 && $twitter_score == 0 && $build_service_score == 0 && $wiki_score == 0)
			$badge = "Novice";

		else if ($developer_score >= $marketing_score && $developer_score >= $wiki_score) {
			if ($developer_score == $max_developer)
				$badge = "Numero Uno";
			else if ($developer_score >= 0.85*$max_developer && $developer_score <= $max_developer)
				$badge = "Elite Stallion";
			else if ($developer_score >= 0.75*$max_developer && $developer_score < 0.85*$max_developer)
				$badge = "SUSE Samurai";
			else if ($developer_score >=0.5*$max_developer && $developer_score < 0.75*$max_developer) 
				$badge = "Citizen Patrol";
			else if ($developer_score >= 0.40*$max_developer && $developer_score < 0.5*$max_developer){
				if ($bugzilla_score > $build_service_score )
					$badge = "Bug Buster";
				else
					$badge = "Build Superhero";
			}
			else if ($developer_score < 0.40*$max_developer && $developer_score > 0 )
				$badge = "Notable Endeavor";
		}
		else if ($marketing_score > $developer_score && $marketing_score > $wiki_score) {
			if ($marketing_score == $max_marketing)
				$badge = "SUSE Herald";
			else if ($marketing_score >= 0.75*$max_marketing && $marketing_score < $max_marketing)
				$badge = "Autobiographer";
			else if ($marketing_score >= 0.5*$max_marketing && $marketing_score < 0.75*$max_marketing)
				$badge = "Enthusiast";
			else {
				if ($planet_opensuse_score > $twitter_score)
					$badge = "Blog-o-Manic";
				else
					$badge = "Twitteratti";
			}
		}
		else 
			$badge = "Strunk & White";
		return $badge;
	}	
	
	//function to calculate user ranks based on total score.
	function karma_rank() {
		//get all karma entities.
		$entities = elgg_get_entities(array('types' => 'object',
					'subtypes' => 'karma',
					'limit' => FALSE ));
		//incase for certain users total score has not been calculated, do it now to avoid errors.
		foreach ($entities as $entity) {
			$entity->total_score = $entity->developer_score[0] + $entity->developer_score[1] + $entity->marketing_score[0] + $entity->marketing_score[1] + $entity->wiki_score + $entity->kudos;
			$entity->save();
		}	
				
		//fetch all karma entities in descending order of their karma total score.		
		$entities = elgg_get_entities_from_metadata(array('types' => 'object', 
					'subtypes' => 'karma' , 
					'limit' => FALSE,
					'metadata_names' => 'total_score',
					'order_by_metadata' => array(
					'name' => 'total_score',
					'direction' => 'DESC',
					'as' => integer) ));
		//starting from the karma entity at the top assign rank '1' and so on.
		$rank = 1;
		foreach ($entities as $entity) {
			$entity->rank =$rank;
			$entity->save();
			$rank += 1; 
		}
	}
	
	//Overrides default permissions for the karma context
	function karma_permissions_check($hook_name, $entity_type, $return_value, $parameters) {	
		if (get_context() == 'karma_cron') {
			return true;
		}
		return null;
	} 
	
	/* calcalute how much percentage of the maximum score is the current user score so that 
	 * user has idea of how much he needs to increase his score to be the maximum scorer. */
	function calculate_percent_of_score($developer_score,$marketing_score) {
		$context = get_context();
		set_context('percentage calculation');	
		$access = elgg_set_ignore_access(true);
		
		$developer_score = $developer_score[0]+$developer_score[1];
		$marketing_score = $marketing_score[0]+$marketing_score[1];
		$max_score = calculate_max_score($developer_score,$marketing_score);
				
		$max_developer = $max_score[0];
		$max_marketing = $max_score[1];
		
		//calculate percentage for whichever score is higher.
		if ($developer_score > $marketing_score) {
			$perc = $developer_score/$max_developer;
		}
		else {
			$perc = $marketing_score/$max_marketing;
		}
		
		return $perc;
		
		set_context($context);
		elgg_set_ignore_access($access);
	}
	
	//function that returns a message, which explains why a certain badge was awarded to the current user.
	function load_badge_suggestion($badge) {
			if($badge == "Twitteratti")
				$message = "Your badge suggests that Tweeting has been your only significant
				contribution to the openSUSE society. We strongly believe that you could do more and have fun!";
			else if ($badge == "Blog-o-Manic")
				$message = "Your badge suggests that blogging has been your only significant 
				contribution to the openSUSE society";
			else if ($badge == "Enthusiast")
				$message = "Your badge suggests that you a quite a marketier, you blog and 
				tweet quite often, but we feel you should do more of this. Have fun!";
			else if ($badge == "Autobiographer")
				$message = "Your badge suggests that you are an avid blogger or twitteratti, 
				we would really like you to keep up the great work and have fun!";	
			else if ($badge == "SUSE Herald")
				$message = "Your badge suggests that you are a top-scorer. You have the maximum
				marketing Karma and we are proud of you!";
			else if ($badge == "Notable Endeavor")
				$message = "Your badge suggests that you are involved more with developer works
				under openSUSE, but your contribution needs to be more significant. Keep up the good
				work and have fun!";
			else if ($badge == "Build Superhero")
				$message = "Your badge suggests that you have been working with various 
				distributions on build service, we think that is great! But we also feel you
				can contribute more to openSUSE to make your contribution much more significant";
			else if ($badge == "Bug Buster")
				$message = "Your badge suggests that you have been fixing bugs on Novell's Bugzilla
				,we think that is great! But we also feel you can contribute more to openSUSE to make your 
				contribution more signifcant";
			else if ($badge == "Citizen Patrol")
				$message = "Your badge suggests that you are quite a developer! You fix bugs or make quite many build service
				commits, and we encourage you to do more of this, have fun!";
			else if ($badge == "SUSE Samurai")
				$message = "Your badge suggests that you significantly contribute to openSUSE, in terms of
				fixing bugs and working with distributions on build service. We are proud of you and we feel,
				that you do more of this and have fun! ";
			else if ($badge == "Elite Stallion")
				$message = "Your badge suggests that you are a core developer, we salute you and encourage you to 
				keep up the good work and have fun!";
			else if ($badge == "Numero Uno")
				$message = "Your badge suggests that you are a top-scorer. You have the maximum developer karma
				and we are proud of you. Keep up the good work and have fun!";
			else if ($badge == "Novice")
				$message = "Your badge suggests that you have not had any contribution to openSUSE so far.
				We think you can do great work for the community. Work  hard and have fun!";
			else if ($badge == "Strunk & White")
				$message = "Your badge suggests that you have contributed to openSUSE wiki. We encourage you
				to carry on with the great work you're doing and have fun!";
			return $message;
	}
	
	
	//function that provides karma details on being called by the api.
	function karma_details($username) {
		$user = get_user_by_username($username);
		
		if ($user instanceof ElggUser) {
			$guid = $user->guid;
			$karma_update_time = date("d-m-Y H:i:s",$user->karma_update_time);
		
			$entities = get_entities('object','karma',$guid);	
			$entity = $entities[0];
			$badge = $entity->badge;
			$developer_score = $entity->developer_score;
			$marketing_score = $entity->marketing_score;
			$wiki_score = $entity->wiki_score;
		
			return array("badge"=>$badge,"developer_score"=>array("bugzilla_score"=>$developer_score[0],"build_service_score"=>$developer_score[1]),
			"marketing_score"=>array("twitter_score"=>$marketing_score[0],"planet_opensuse_score"=>$marketing_score[1]),
			"wiki_score" => $wiki_score,"karma_last_update"=>$karma_update_time);
		}
		else
			return "incorrect username";
	}
	
	//function to expose the karma_details method to the Connect API, requires api authentication.
	expose_function("karma.details", 
                "karma_details", 
                 array("username" => array('type' => 'string')), 'A method which fetches karma details of a user', 'GET',
                 false, false);
	
	 //register to action for allowing giving 'KUDOS to other connect users'.
	register_action("karma/kudos", false, $CONFIG->pluginspath . "karma/actions/kudos.php");
	//Initialize plugin.
	register_elgg_event_handler('init','system','karma_init'); 
?>
