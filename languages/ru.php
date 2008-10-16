<?php
	/**
	 * Elgg log browser plugin language pack
	 * 
	 * @package ElggPingBack
	 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
	 * @author Curverider Ltd
	 * @copyright Curverider Ltd 2008
	 * @link http://elgg.com/
	 */

	$russian = array(
	
		/**
		 * Error messages
		 */
	
			'pingback:error:missingparams' => 'Нехватает параметров для Pingback запроса',
			'pingback:error:targetnotme' => 'Целевой URL не указывает на мой cайт',
			'pingback:error:targetnotfoundinsource' => 'Цель не найдена в источнике, или источник недоступен',
	
			'pingback:success' => 'Получен Pingback'
	);
					
	add_translation("ru",$russian);
?>