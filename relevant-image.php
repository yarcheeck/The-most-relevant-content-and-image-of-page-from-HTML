<?php
/*
 * PREPARE PAGE CONTENT TO MANIPULATE WITH
 * =======================================
*/

	// URL of page we're going to use to retrieve images from
	$page_url = 'http://www.webdesignerdepot.com/2014/10/the-ultimate-guide-to-bootstrap/';
	
	// Setup timeout for case page is not responding
	ini_set('default_socket_timeout', 4); 
	
	// Include library for converting relative url to absolute
	require_once('url_to_absolute.php');
	
	// Parse page content and create DOM object
	$page_dom = new DOMDocument();
	@$page_dom->loadHTMLFile($page_url);


	
/*
 * FIND THE MAIN TEXT CONTENT  
 * ==========================
 * 
 * We use the biggest paragraph as an identifier of main content.
 * This is not the best solution because some pages use only BR tags.
*/


	foreach ( $page_dom->getElementsByTagName('p') as $p ) 
		if ( !isset($main_content) || strlen($p->textContent) > strlen($main_content->textContent )  )
			$main_content = $p;


/*
 * GET ALL IMAGES ON PAGE &
 * CALCULATE NODE DISTANCE FROM MAIN CONTENT
 * =========================================
 *
 * We mind only JPGs. Clean URL from parameters and absolutise.
 * All images are stored in array.
 * We use node distance as a measurement of relevance of image.
*/

	$all_img = array();
	foreach ( $page_dom->getElementsByTagName('img') as $i ) {
	
		// Prepare image URL. If not JPG skip to another one.
		$img_url = $i->getAttribute('src');

		if ( strtolower(substr($img_url, -3) ) != 'jpg') continue;
		if ( strpos($img_url, '?') ) $img_url = substr($page_url, 0, strpos($img_url, '?'));
		$img_url = url_to_absolute($page_url, $img_url);
		

		// Compare xpaths of main content and image to calculate distance between two nodes.	
		$content_path = $main_content->getNodePath();
		$image_path = $i->getNodePath();
	
		$offset = 0;
		while ( $offset < strlen($image_path) ) {
		    if ( $image_path[$offset] !== $content_path[$offset] ) break;
		    $offset++;
		}
		
		$distance = substr_count($image_path, '/', $offset) + substr_count($content_path, '/', $offset);
		
		
		// Add an image to array
		$all_img[] = array('url' => $img_url, 'distance' => $distance);
	}


/*
 * GET DIMENSION OF IMAGES
 * =======================
 * 
 * We don't download whole images, only headers.
*/

	foreach ( $all_img as $k => $i ) {
			
		$data = file_get_contents($i['url'], NULL, NULL, 0, 32768);
		$img = imagecreatefromstring($data);
	
		$all_img[$k]['width'] = imagesx($img);; 	
		$all_img[$k]['height'] = imagesy($img);; 	
	}




/*
 * GET THE MOST RELEVANT IMAGES WITH MINIMUM SPECIFIED WIDTH
 * =========================================================
*/

	// Sort array of images by distance
	usort($all_img, function($a, $b) {
	    return $a['distance'] - $b['distance'];
	});

	// Find first image with width at least 300px 
	$image = '';
	foreach( $all_img as $i ) if ( $i['width'] > 300 ) { $image = $i['url']; break; }
	
	var_dump($all_img);
	var_dump($image);
		
?>
