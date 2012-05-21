<?php

    include_once(dirname(dirname(dirname(__FILE__))) . "/engine/start.php");
    
    gatekeeper();
    
    $title = "Enter your details to find your karma score";
    $area2 = elgg_view_title($title);
    $area2 .= elgg_view("karma/form");
    $body = elgg_view_layout('two_column_left_sidebar', '', $area2);
    page_draw($title, $body);
?>
