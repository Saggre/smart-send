jQuery(function($) {
	function showDisplayAdvancedSettings(e) {
	    if( $('#woocommerce_smart_send_shipping_advanced_settings_enable').is(':checked')) {
	        $('#woocommerce_smart_send_shipping_advanced_settings_enable').closest('tr').nextAll().show();
	        // $('.display-smart-send').show();

	    } else {
	        $('#woocommerce_smart_send_shipping_advanced_settings_enable').closest('tr').nextAll().hide();
	    }
	}

	function showDisplayMinAmount(e) {
		var shipping_requires = $('#woocommerce_smart_send_shipping_requires').val();
	    if( ( shipping_requires == 'min_amount' ) || ( shipping_requires == 'either' ) || ( shipping_requires == 'both') ) {
	        $('#woocommerce_smart_send_shipping_requires').closest('tr').next().show();

	    } else {
	        $('#woocommerce_smart_send_shipping_requires').closest('tr').next().hide();
	    }
	}

	$( document ).ready(function() {
		// Display or hide advanced settings
		showDisplayAdvancedSettings();
		$('#woocommerce_smart_send_shipping_advanced_settings_enable').on("click", showDisplayAdvancedSettings);

		// Display or hide minimum amount
		showDisplayMinAmount();
		$('#woocommerce_smart_send_shipping_requires').on("change", showDisplayMinAmount);
	});
});
