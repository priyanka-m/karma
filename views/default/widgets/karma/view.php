<?php 
	$score  = 0;
	elgg_extend_view('metatags', 'karma/metatags_ext');
	
	//Script that extracts twitter username, blog url, obs repository from Connect.
	$vars['entity'] = get_entity(get_loggedin_userid());
	$details = array();
	foreach($vars['config']->profile as $shortname => $valtype) {
		$value = $vars['entity']->$shortname;
		if(strcasecmp($shortname,"twitter") == 0 || strcasecmp($shortname,"obs") == 0 || strcasecmp($shortname,"blog") == 0 )
		{
			$details[$shortname] = $value;
		}
	}
	
	//information about resolved bugs from Bugzilla. For each bug fixed 10 points assigned.
	$email = $vars['entity']->email;
	$csv_url = "https://bugzilla.novell.com/buglist.cgi?bug_status=RESOLVED&bug_status=VERIFIED&email1=$email&emailassigned_to1=1&emailinfoprovider1=1&emailtype1=exact&query_format=advanced&title=Bug%20List&ctype=csv";
	$xmls = file_get_contents($csv_url);
	$arr = explode("\n",$xmls);
	array_shift($arr);
	$max_score = 0;
	$num_of_bugs = 0;
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
	 *  score, Master Ninja for securing greater than 70% but less than 85% and Noob for the rest.*/ 
	$max_score = $num_of_bugs*10;
	if ($max_score == 0)
		$badge = "Noob";
	else if ($score >= 8.5*$max_score && $score < 7*$max_score && $max_score > 0)
		$badge = "Bug Squasher";
	else if ($score >= 7*$max_score && $score < 5*$max_score && $max_score > 0)
		$badge = "Master Ninja";
	$img_class = '';		
	?>
<br/>
<div class="usericon">
	&nbsp;&nbsp;&nbsp;<img <?php echo $img_class; ?> src="<?php echo elgg_format_url($vars['url']."mod/karma/default_icons/".$badge.".jpg");?>" border="0"/>
</div>
<br/>
<div class = "badge">
	<?php
	$title = $badge. " (".$score." Points)";
	echo elgg_view_title($title);?>
</div>


