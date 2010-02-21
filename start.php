<?php
	/**
	 * Elgg pingback support.
	 * 
	 * @package ElggPingBack
	 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
	 * @author Curverider Ltd
	 * @copyright Curverider Ltd 2008-2010
	 * @link http://elgg.com/
	 */

	function pingback_init()
	{
		global $CONFIG;
		
		// Outgoing pings - listen to object create and look at supported subtypes (configurable, but initially 'blog').. will parse urls and transmit
		register_elgg_event_handler('create', 'object', 'pingback_send_pings');
		
		// Incoming pingback
		register_xmlrpc_handler('pingback.ping', 'pingback_incoming_ping');
		
		// Add meta tags
		elgg_extend_view('metatags','pingback/metatags');
		
	}
	
	/**
	 * Attempt to send a pingback for $object to all the urls in the array.
	 * 
	 * The urls will be probed for pingback addreses, and if found the ping is sent.
	 *
	 * @param ElggObject $object Object.
	 * @param array $url_array The array.
	 * @return array An array containing all pinged urls or false.
	 */
	function pingback_ping(ElggObject $object, array $url_array)
	{
		$return = array();
		
		$item_url = $object->getURL(); // use geturl to get object location
		if ($item_url)
		{
			foreach ($url_array as $url)
			{
				$matches = array();
				$rpc_url = "";
				$status = false;
				
				// attempt to fetch url
				$page = file_get_contents($url);
				
				if (preg_match("<link rel=\"pingback\" href=\"([^\"]+)\" ?/?>", $page, $matches) > 0)
					$rpc_url = $matches[1];
				else
				{
					// Not found in content, look for header
					$string = $http_response_header;
					$a = (strpos($string, "X-Pingback:") + 11);
    				$b = @strpos($string, "\n", $a);

    				if ($b===false) {
        				$rpc_url = trim(substr($string, $a));
					} else {
	        			$rpc_url = trim(substr($string, $a, $b - $a));
					}
				}
				
				// PIIIIIIIIIIING
				if ($rpc_url)
				{
					$query = "<?xml version=\"1.0\"?>
<methodCall>
<methodName>pingback.ping</methodName>
<params>
<param>
	<value><string>$item_url</string></value>
</param>
<param>
	<value><string>$url</string></value>
</param>
</params>
</methodCall>
";
					
					$headers = array(); 
					$headers['Content-Length'] = strlen($query);
					$headers['Content-Type'] = 'text/xml';
					
					$http_opts = array(
						'method' => 'POST',
						'header' => serialise_api_headers($headers),
						'content' => $query
					);
			
					$opts = array('http' => $http_opts);
			
					// Send context
					$context = stream_context_create($opts);
			
					$result = file_get_contents($rpc_url, false, $context);
					
					if (($result) && (stripos($result, 'fault')===false))
						$return[] = $url; // Success so add to list
				}
				
			}
		}
		
		if (count($return))
			return $return;
			
		return false;
	}
	
	/**
	 * Event handler which sends pingbacks on certain events.
	 *
	 * @param unknown_type $event
	 * @param unknown_type $object_type
	 * @param unknown_type $object
	 */
	function pingback_send_pings($event, $object_type, $object)
	{
		global $CONFIG;
		
		$pingback_subtypes = array();
		$pingback_subtypes = trigger_plugin_hook('pingback:object:subtypes', 'object', null, $pingback_subtypes);
		
		if (($object) && (in_array(get_subtype_from_id($object->subtype), $pingback_subtypes)))
		{
			
			// Find urls
			$urls = array();
			$rtn = preg_match_all("*href=\"([^\"]+)\"*", $object->description, $urls); 
			if ($rtn)
			{	
				// Append return This will get appended as metadata to the object.
				$object->pinged_urls = pingback_ping($object, $urls[1]);
			
			}
		}
		
		// Always return true so that the post is always made.
		return true;
	}
	
	function pingback_harvestextract($page, $refuri)
	{
		// Find title
		preg_match("/<title>(.*)<\/title>/imsU", $page, $matches);
		$title = $matches[1];

		// Get extract
		$strpos = strpos($page, $refuri);
		if ($strpos!==false)
		{
			$a = 0;
			if ($strpos>300) $a=$strpos-300;

			$extract = strip_tags(substr($page, $a, 600));

			if ($extract) {
				$hwp = strlen($extract) / 2;
				$extract = substr($extract, $hwp - 75, 150);

				return array('title' => $title, 'extract' => "..." . $extract . "...");
			}
		}

		return false;
	}
	
	
	/**
	 * XML-RPC endpoint for incoming pings.
	 *
	 * @param XMLRPCCall $data
	 */
	function pingback_incoming_ping(XMLRPCCall $data) 
	{
		global $CONFIG;
		
		$strings = xmlrpc_parse_params( $data->getParameters());
			
		if (count($strings)<2)
			return new XMLRPCErrorResponse(elgg_echo('pingback:error:missingparams'));	
		
		$sourceURI = trim($strings[0]);
		$targetURI = trim($strings[1]);
		
		if ((!$sourceURI) || (!$targetURI))
			return new XMLRPCErrorResponse(elgg_echo('pingback:error:missingparams'));	
		
		// Validate target - is this pointing at me?
		if (strpos($targetURI, $CONFIG->wwwroot)===false)
			return new XMLRPCErrorResponse(elgg_echo('pingback:error:targetnotme'));
			
		// Validate source - does it contain a link to the target?
		$source = @file_get_contents($sourceURI);
		if ((!$source) || (strpos($source, $targetURI)===false))
			return new XMLRPCErrorResponse(elgg_echo('pingback:error:targetnotfoundinsource') . $source . $sourceURI);
			
		// Got that far, harvest an extract.
		$extract = pingback_harvestextract($source, $targetURI);
		if (($extract) && (is_array($extract)))	
		{
			// We have an extract and we are valid as far as we can tell... so now create an object.
			$ping = new ElggObject();
			$ping->access_id = ACCESS_PUBLIC;
			$ping->subtype = 'pingback';
			$ping->title = $extract['title'];
			$ping->description = $extract['extract'];
			$ping->source_url = $sourceURI;
			$ping->target_url = $targetURI;
			
			$ping->save(); // This triggers an event which other plugins can listen to, 
		}
		
		$response = new XMLRPCSuccessResponse();
		$response->addString(elgg_echo('pingback:success'));
		
		return $response;
		
	}
	
	// Initialise 
	register_elgg_event_handler('init','system','pingback_init');
?>