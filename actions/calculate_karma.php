<?php
  // only logged in users can find their karma score
  gatekeeper();
 
  // get the form input
  $twitter_username = get_input('twitter_username');
  $bugzilla_username = get_input('bugzilla_username');
  $planet_username = get_input('planet_username');
  
 //TODO:use the respective api's to find out activities done and assign score to each
 //on the basis of score, assign title like hero of the month, bug squasher etc 
 
  // create a new score object
  //TODO:Handle all this dynamically such that badges are assigned 
  //with regard to the score attained.
  $badge = "Master Ninja";
  $score = "100 points";
  $yourkarma = new ElggObject();
  $yourkarma->title = $badge;
  $yourkarma->description = $score." - You are the master of all! Thumbs Up!";
  $yourkarma->subtype = "karma";
 
  $yourkarma->access_id = ACCESS_PUBLIC;
 
  $yourkarma->owner_guid = get_loggedin_userid();
 
  $yourkarma->save();
  
  forward($yourkarma->getURL());
?>
