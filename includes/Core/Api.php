<?php
/**
 * REST API bootstrap.
 *
 * @package TeraxAI
 */

namespace TeraxAI\Core;

use TeraxAI\Libs\API\Config;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api
 */
class Api {

	use Base;

	/**
	 * Register routes file.
	 *
	 * @return void
	 */
	public function init() {
		Config::set_route_file( TERAX_AI_DIR . 'includes/Routes/Api.php' )
			->set_namespace( 'TeraxAI\Controllers' )
			->init();
	}
}
