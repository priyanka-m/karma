<?php
$rss = new SimpleXMLElement('http://planet.opensuse.org/en/rss20.xml', null, true);
$entities = get_entities('user');
foreach ($entities as $entity) {
	$blog = $entity->blog;
	foreach($rss->xpath('channel/item') as $item)
	{
		if(stripos($item->link,$blog) !== False) {
			$score += 5;
		}
	}
}
?>
