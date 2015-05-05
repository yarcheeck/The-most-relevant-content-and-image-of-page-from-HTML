<?php
	
if ( !class_exists('relevant_images_external_article') ) {
	

	/** 
	 * Class for detecting relevant images on external page (article).
	 *
	 * Article on this topic: http://yarcheek.com/how-to-get-the-most-relevant-image-from-page-php/
	 * Class requires external library url_to_absolute.php by Nitin Kr. Gupta: https://github.com/converspace/converspace/blob/master/url_to_absolute.php
	 *
	 * Usage example:
	 * $foo = new relevant_images_external_article;
	 * $image =  $foo->get_images('http://www.webdesignerdepot.com/2014/10/the-ultimate-guide-to-bootstrap/');
	 * $all_images = $foo->get_images('http://www.webdesignerdepot.com/2014/10/the-ultimate-guide-to-bootstrap/', false);
	 * $big_images = $foo->get_images('http://www.webdesignerdepot.com/2014/10/the-ultimate-guide-to-bootstrap/', false, 1024);	 
	 */		

	class relevant_images_external_article {
		
		public $extensions_path;
		public $min_width = 300;
		
		function __construct() {
			
			// Define path for extensions now, so later we can call function from different locations
			$this->$extensions_path = dirname(dirname(__FILE__));
						
		}
		
		
		
		/** 
		 * Return relevant image(s).
		 *
		 * If any error occurs, returns a string with the error description.
		 *
		 * @param string $page_url Specify the requested URL
		 * @param boolean $return_single If false, returns array of images with additional informations, otherwise return string URL to the most relevant image.
		 * @param int $min_width If specified, return images wider than $min_width
		 */		
		 
		public function get_images($page_url, $return_single = true, $min_width = $this->$min_width) {

			/*
			 * PREPARE PAGE CONTENT TO MANIPULATE WITH
			 * =======================================
			*/
							
				// Test if URL exists
				if ( !$page_url ) return('ERROR: Missing web page URL.');
				if ( strpos(@get_headers($page_url)[0],'200') === false ) return('ERROR: Web page do not exist.');
	
				// Test if all extensions exist
				if ( !file_exists($this->$extensions_path.'/url_to_absolute.php') ) return('ERROR: Missing library url_to_absolute.php in expected location '.$this->$extensions_path);
	
				// Setup timeout for the case when page is not responding
				// 4 seconds should be enough to parse page content. With this value we can do approximately 10 pages a minute 
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
			 * We expect main content to be surrounded by relevant images.
			 *
			 * This is not the best solution because some pages use only BR tags, but it's the fastest one.
			 * Other detection possibilities are:
			 * - compare some text snippet or meta tag with page content (fce similar_text)
			 * - search based on html5 structure (main tag)
			*/
			
				// Store xpath of the biggest paragraph to $main_conten
				// We use the path later for finding relevant images
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
			 * We use node distance to determine image relevance.
			 *
			 * Another signal could be image dimensions.
			 * Downside of this technique is, that the closest image could ilustrate the paragraph itself, not the article.
			*/


				$all_img = array();
				
				foreach ( $page_dom->getElementsByTagName('img') as $i ) {
				
					// Prepare image URL. If not JPG skip to another one.
					$img_url = $i->getAttribute('src');
					if ( strtolower(substr($img_url, -3) ) != 'jpg') continue;
					
					// Make URL ready
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
				
				if ( empty($all_img) ) return('ERROR: There are no JPG images on the requested page');
				
			
			/*
			 * GET DIMENSION OF IMAGES
			 * =======================
			 * 
			 * We don't download whole images, only headers.
			 * This approach dramatically save time and resources.  
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
			
				// First image with minimal with is the most relevant
				$image = '';
				foreach( $all_img as $i ) if ( $i['width'] > $min_width ) { $image = $i['url']; break; }
				
				if ( $return_single ) return $image;
				else return $all_img;
												

		} #get_images

	}	
}
		
?>
