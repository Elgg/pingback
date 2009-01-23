<?php
	/**
	 * Elgg log browser plugin language pack
	 * 
	 * @package ElggPingBack
	 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
	 * @author Curverider Ltd
	 * @copyright Curverider Ltd 2008-2009
	 * @link http://elgg.com/
	 */

	$english = array(
	
		/**
		 * Error messages
		 */
	
			'pingback:error:missingparams' => 'Missing parameters in pingback request',
			'pingback:error:targetnotme' => 'Target URL is not pointing to my domain',
			'pingback:error:targetnotfoundinsource' => 'Target not found in source, or source not accessable',
	
			'pingback:success' => 'Pingback received'
	);
					
	add_translation("en",$english);
?>