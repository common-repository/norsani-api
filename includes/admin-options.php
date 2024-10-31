<?php

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Norsani API options tab
 * 
 */
add_filter('frozr_norsani_settings_page_tabs','norsani_api_tab',10,1);
function norsani_api_tab( $array ) {
	$array['api'] = __('API','norsani-delivery-api');
	
	return $array;
}
/**
 * Add Norsani API options
 * 
 */
add_action('frozr_before_norsani_options_settings', 'norsani_api_options');
function norsani_api_options() {
	
	register_setting( 'norsani_page_api', 'norsani_api' );
	
	// Sections
	add_settings_section(
		'norsani_api_section',
		sprintf(__( 'Braintree Settings (%s)', 'norsani-api' ), '<a href="https://articles.braintreepayments.com/control-panel/important-gateway-credentials#api-credentials" title="Braintree" >'.__('API credentials','norsani-api').'</a>'),
		'',
		'norsani_page_api');
	
	
	// Settings
	add_settings_field( 
		'norsani_braintree_environment',
		'Environment', 
		'norsani_braintree_environment_render',
		'norsani_page_api',
		'norsani_api_section' );
		
	add_settings_field( 
		'norsani_braintree_merchantId',
		'Merchant ID', 
		'norsani_braintree_merchantId_render',
		'norsani_page_api',
		'norsani_api_section' );
		
	add_settings_field( 
		'norsani_braintree_publicKey',
		'Public Key', 
		'norsani_braintree_publicKey_render',
		'norsani_page_api',
		'norsani_api_section' );
		
	add_settings_field( 
		'norsani_braintree_privateKey',
		'Private Key', 
		'norsani_braintree_privateKey_render',
		'norsani_page_api',
		'norsani_api_section' );

}

/**
 * Braintree Environment
 *
 */
function norsani_braintree_environment_render() {
	$option = get_option( 'norsani_api' );
	$selected_env = isset($option['norsani_braintree_environment']) ? $option['norsani_braintree_environment'] : 'sandbox'; ?>
	
	<select id="norsani_braintree_environment" class="frozr_admin_select" name="norsani_api[norsani_braintree_environment]">
		<option value="sandbox" <?php selected( 'sandbox', $selected_env ); ?> >Sandbox</option>
		<option value="production" <?php selected( 'production', $selected_env ); ?> >Production</option>
	</select>
	<?php
}

/**
 * Braintree merchant ID
 *
 */
function norsani_braintree_merchantId_render() {
	$option = get_option( 'norsani_api' );
	$merchantId = isset($option['norsani_braintree_merchantId']) ? $option['norsani_braintree_merchantId'] : null; ?>
	
	<fieldset class="form-group">
		<label for="norsani_braintree_merchantId">
			<input id="norsani_braintree_merchantId" type="text" name="norsani_api[norsani_braintree_merchantId]" value="<?php echo $merchantId; ?>" />
		</label>
	</fieldset>

	<?php
}

/**
 * Braintree Public key
 *
 */
function norsani_braintree_publicKey_render() {
	$option = get_option( 'norsani_api' );
	$key = isset($option['norsani_braintree_publicKey']) ? $option['norsani_braintree_publicKey'] : null; ?>
	
	<fieldset class="form-group">
		<label for="norsani_braintree_publicKey">
			<input id="norsani_braintree_publicKey" type="text" name="norsani_api[norsani_braintree_publicKey]" value="<?php echo $key; ?>" />
		</label>
	</fieldset>

	<?php
}

/**
 * Braintree Private key
 *
 */
function norsani_braintree_privateKey_render() {
	$option = get_option( 'norsani_api' );
	$key = isset($option['norsani_braintree_privateKey']) ? $option['norsani_braintree_privateKey'] : null; ?>
	
	<fieldset class="form-group">
		<label for="norsani_braintree_privateKey">
			<input id="norsani_braintree_privateKey" type="text" name="norsani_api[norsani_braintree_privateKey]" value="<?php echo $key; ?>" />
		</label>
	</fieldset>

	<?php
}