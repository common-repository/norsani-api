<?php
/**
 * Norsani Location API
 *
 * @package norsani-api
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Norsani_Location_API_Route extends Norsani_General_API_Route {

	/**
	* Register the routes for the objects of the controller.
	*/
	public function register_routes() {
		
		$namespace = NORSANI_API_NAMESPACE;
		
		register_rest_route( $namespace, '/localityoptions', array(
		array(
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> array( $this, 'localityoptions' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			)
		));
	}
	
	public function localityoptions($request) {

		$creds = $request->get_params();
		$base_country = frozr_get_base_country();
		$data = array();
		
		$data[] = array('label' => sprintf(__('All of %s','norsani-api'),$base_country), 'value' => $base_country);
	
		$localities = frozr_get_localities(true);
		foreach ($localities as $locality => $locality_array) {
			$data[] = array('label' => $locality, 'value' => $locality);

			foreach ($locality_array as $locality_data) {
				if (empty($locality_data)) {
					continue;
				}
				$option_value = implode(',',$locality_data).','.$locality;
				$option_text = implode(', ',$locality_data).', '.$locality;
				$data[] = array('label' => $option_text, 'value' => $option_value);
			}
		}
		
		return new WP_REST_Response( json_encode($data), 200 );
	}
}
add_action('rest_api_init', function () {
	$norsani_api = new Norsani_Location_API_Route();
	$norsani_api->register_routes();
});