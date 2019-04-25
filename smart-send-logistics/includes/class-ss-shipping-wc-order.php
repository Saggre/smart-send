<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WooCommerce Smart Send Shipping Order.
 *
 * @package  SS_Shipping_WC_Order
 * @category Shipping
 * @author   Smart Send
 */

if (!class_exists('SS_Shipping_WC_Order')) :

    class SS_Shipping_WC_Order
    {

        protected $label_prefix = 'smart-send-label-';

        protected $order_bulk = null;
        /**
         * Init and hook in the integration.
         */
        public function __construct()
        {
            $order_bulk = new SS_Shipping_WC_Order_Bulk( $this );

            $this->define_constants();
            $this->init_hooks();
        }

        /**
         * Define constants
         */
        protected function define_constants()
        {
            SS_SHIPPING_WC()->define('SS_SHIPPING_BUTTON_LABEL_GEN',
                (SS_SHIPPING_WC()->get_demo_mode_setting() ? __('DEMO MODE',
                        'smart-send-logistics') . ': ' : '') . __('Generate label', 'smart-send-logistics'));
            SS_SHIPPING_WC()->define('SS_SHIPPING_BUTTON_RETURN_LABEL_GEN',
                (SS_SHIPPING_WC()->get_demo_mode_setting() ? __('DEMO MODE',
                        'smart-send-logistics') . ': ' : '') . __('Generate return label', 'smart-send-logistics'));
        }

        /**
         * Init hooks
         */
        public function init_hooks()
        {

            // Order page metabox actions
            add_action('add_meta_boxes', array($this, 'add_meta_box'), 20);
            add_action('wp_ajax_ss_shipping_generate_label', array($this, 'generate_label'));

            // Meta field for storing the selected agent_no
            add_filter('update_post_metadata_by_mid', array($this, 'filter_update_agent_meta'), 10, 4);//For Wordpress 5.0.0+
            add_action('deleted_post_meta', array($this, 'action_deleted_agent_meta'), 10, 4);

            $subs_version = class_exists('WC_Subscriptions') && !empty(WC_Subscriptions::$version) ? WC_Subscriptions::$version : null;
            // Prevent data being copied to subscriptions
            if (null !== $subs_version && version_compare($subs_version, '2.0.0', '>=')) {
                add_filter('wcs_renewal_order_meta_query',
                    array($this, 'woocommerce_subscriptions_renewal_order_meta_query'), 10);
            } else {
                add_filter('woocommerce_subscriptions_renewal_order_meta_query',
                    array($this, 'woocommerce_subscriptions_renewal_order_meta_query'), 10);
            }
        }

        /**
         * Add the meta box for shipment info on the order page
         */
        public function add_meta_box()
        {
            global $woocommerce, $post;
            $order_id = $post->ID;

            $ss_shipping_method_id = $this->get_smart_send_method_id($order_id);
            // Only display Smart Shipping (SS) meta box is SS selected as shipping method OR free shipping is set to SS method
            if (!empty($ss_shipping_method_id)) {

                add_meta_box('woocommerce-ss-shipping-label', __('Smart Send Shipping', 'smart-send-logistics'),
                    array($this, 'meta_box'), 'shop_order', 'side', 'default');
            } else {
                add_meta_box('woocommerce-ss-shipping-label', __('Smart Send Shipping', 'smart-send-logistics'),
                    array($this, 'meta_box_non_smart_send'), 'shop_order', 'side', 'default');

            }
        }

        /**
         * Show the meta box for shipment info on the order page
         *
         * This meta box is shown when the order does not have a Smart Send shipping method
         */
        public function meta_box_non_smart_send()
        {
            global $woocommerce, $post;
            $order_id = $post->ID;
            echo '<p>' . __('Order placed with a shipping method that is not from the Smart Send plugin',
                    'smart-send-logistics') . '</p>';
        }

        /**
         * Show the meta box for shipment info on the order page
         *
         * This meta box is shown when the order have a Smart Send shipping method
         */
        public function meta_box()
        {
            global $woocommerce, $post;
            $order_id = $post->ID;
            $order = wc_get_order($order_id);

            $shipping_ss_settings = SS_SHIPPING_WC()->get_ss_shipping_settings();

            $ss_shipping_method_id = $this->get_smart_send_method_id($order_id);
            $ss_shipping_method_name = SS_SHIPPING_WC()->get_shipping_method_name_from_all_shipping_method_instances($ss_shipping_method_id);

            // Get order agent object
            $ss_shipping_order_agent = $this->get_ss_shipping_order_agent($order_id);
            $ss_shipping_order_agent_no = $this->get_ss_shipping_order_agent_no($order_id);

            echo '<div id="ss-shipping-label-form">';

            woocommerce_wp_hidden_input(array(
                'id'    => 'ss_shipping_label_nonce',
                'value' => wp_create_nonce('create-ss-shipping-label'),
            ));

            $shipping_method_carrier = ucfirst(SS_SHIPPING_WC()->get_shipping_method_carrier($ss_shipping_method_id));
            $shipping_method_type = ucfirst(SS_SHIPPING_WC()->get_shipping_method_type($ss_shipping_method_id));

            echo '<h3>' . __('Shipping Method', 'smart-send-logistics') . '</h3>';
            echo '<p>' . $ss_shipping_method_name . '</p>';

            // If debug is enabled then show the shipping method id and instance id
            if (isset($shipping_ss_settings['ss_debug']) && $shipping_ss_settings['ss_debug'] == 'yes') {
                foreach ($order->get_shipping_methods() as $method) {
                	echo '<pre>';
                    echo __('Debug id', 'smart-send-logistics') . ': ' .
                        $method->get_method_id() . ':' . $method->get_instance_id() . '<br>';

	                echo __('SS Shipment id', 'smart-send-logistics') . ': '
	                     . $order->get_meta('_ss_shipping_label_id', true) . '<br>';

	                echo __('SS Shipment return id', 'smart-send-logistics') . ': '
	                     .  $order->get_meta( '_ss_shipping_return_label_id', true) . '<br>';
	                echo '</pre>';
                }
            }

            echo '<p>' . sprintf(__('Weight: %0.2f kg', 'smart-send-logistics'),
                    $this->getOrderWeight($order)) . '</p>';

            // Display Agent No. field if pickup-point shipping method selected
            if (stripos($shipping_method_type, 'agent') !== false) {
                echo '<h3>' . __('Pick-up Point', 'smart-send-logistics') . '</h3>';
                echo '<strong>' . __('Agent No.:', 'smart-send-logistics') . $ss_shipping_order_agent_no . '</strong>';
                echo $this->get_formatted_address($ss_shipping_order_agent);
            }

            echo '<hr>';

            $parcels = $this->get_ss_shipping_order_parcels($order_id);
            $checked_attrib = '';
            $items_class = 'hidden';
            $items = '';
            if (!empty($parcels)) {
                $checked_attrib = 'checked';
                $items_class = '';

                foreach ($parcels as $parcel) {
                    $dropdown = '<select data-id="' . $parcel['id'] . '" data-name="' . $parcel['name'] . '" name="ss_shipping_box_no[]"  autocomplete="off">';

                    for ($i = 1; $i <= 9; $i++) {
                        $selected = ($i == intval($parcel['value'])) ? 'selected' : '';
                        $dropdown .= '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
                    }
                    $dropdown .= '</select>';

                    $items .= '<tr><td width="80%">' . $parcel['name'] . '</td><td width="20%">' . $dropdown . '</td></tr>';
                }
            }

            echo '<input type="checkbox" id="ss-shipping-split-parcels" name="ss_shipping_split_parcels" autocomplete="off" value="1" ' . $checked_attrib . '> <strong>' . __('Split into parcels',
                    'smart-send-logistics') . '</strong><br/>';

            echo '<div id="ss-shipping-order-items" class="' . $items_class . '"><table width="100%">';

            if (!empty($parcels)) {
                echo $items;
            } else {
                foreach ($order->get_items() as $item_id => $item) {

                    $product_id = $item['product_id'];
                    $product_name = $item['name'];
                    // If variable product, add attribute to name
                    if (!empty($item['variation_id'])) {
                        $product_id = $item['variation_id'];

                        $product_attribute = wc_get_product_variation_attributes($item['variation_id']);
                        $product_name .= ': ' . current($product_attribute);

                    }

                    for ($ii = 1; $ii <= intval($item['qty']); $ii++) {

                        $dropdown = '<select data-id="' . $product_id . '" data-name="' . $product_name . '" name="ss_shipping_box_no[]"  autocomplete="off">';

                        for ($i = 1; $i <= 9; $i++) {
                            $dropdown .= '<option value="' . $i . '">' . $i . '</option>';
                        }

                        $dropdown .= '</select>';

                        echo '<tr><td width="80%">' . $product_name . '</td><td width="20%">' . $dropdown . '</td></tr>';
                    }
                }
            }

            echo '</table></div>';

            echo '<hr>';
            echo '</p>';


            echo '<button id="ss-shipping-label-button" class="button button-primary button-save-form">' . SS_SHIPPING_BUTTON_LABEL_GEN . '</button><br><br>';
            echo '<button id="ss-shipping-return-label-button" class="button button-save-form">' . SS_SHIPPING_BUTTON_RETURN_LABEL_GEN . '</button>';

            // Load JS for AJAX calls
            $ss_label_data = array(
                'read_more'             => __('Read more', 'smart-send-logistics'),
                'unique_error_id'       => __('Unique error id: ', 'smart-send-logistics'),
                'download_label'        => __('Download shipping label', 'smart-send-logistics'),
                'download_return_label' => __('Download return label', 'smart-send-logistics'),
                'unexpected_error'      => __('Unexpected error', 'smart-send-logistics'),
            );
            wp_enqueue_script('ss-shipping-label-js', SS_SHIPPING_PLUGIN_DIR_URL . '/assets/js/ss-shipping-label.js',
                array(), SS_SHIPPING_VERSION);
            wp_localize_script('ss-shipping-label-js', 'ss_label_data', $ss_label_data);

            echo '</div>';

        }

        /**
         * Return HTML formatted agent address
         *
         * @param object $ss_shipping_order_agent
         * @return string
         */
        protected function get_formatted_address($ss_shipping_order_agent)
        {

            if (empty($ss_shipping_order_agent)) {
                return '';
            }

            return '<p class="ss_agent_address">' . $ss_shipping_order_agent->company . '</br>' . $ss_shipping_order_agent->address_line1 . '</br>' . $ss_shipping_order_agent->postal_code . ' ' . $ss_shipping_order_agent->city . '</p>';
        }

        /**
         * Return ordered Smart Send shipping method, OR Free Shipping linked to Smart Send shipping method, otherwise empty string
         *
         * @param integer $order_id     Post object or post ID of the order.
         * @param boolean $return       Whether or not the label is return (true) or normal (false)
         * @return string               Unique Smart Send name of shipping method. Example 'postnord_agent'
         */
        public function get_smart_send_method_id($order_id, $return = false)
        {
            $order = wc_get_order($order_id);//Accepts Post object or post ID of the order.

            if (!$order) {
                return '';
            }

            // Get shipping id to make sure its either Smart Send, Free Shipping or vConnect
            $order_shipping_methods = $order->get_shipping_methods();
            if (!empty($order_shipping_methods)) {

                foreach ($order_shipping_methods as $item_id => $item) {
                    // Array access on 'WC_Order_Item_Shipping' works because it implements backwards compatibility
                    $shipping_method_id = !empty($item['method_id']) ? esc_html($item['method_id']) : null;

                    // If Smart Send found, return id
                    if (stripos($shipping_method_id, 'smart_send_shipping') !== false) {
                        if ($return) {
                            return array(
                                'smart_send_return_method'              => $item['smart_send_return_method'],
                                'smart_send_auto_generate_return_label' => $item['smart_send_auto_generate_return_label'],
                            );
                        } else {
                            return $item['smart_send_shipping_method'];
                        }
                    } else {
                        if (stripos($shipping_method_id, 'free_shipping') !== false) {
                            // If free shipping, then filter the shipping method to the correct Smart Send method

                            $ss_settings = SS_SHIPPING_WC()->get_ss_shipping_settings();

                            if (!empty($ss_settings['shipping_method_for_free_shipping'])) {
                                return $ss_settings['shipping_method_for_free_shipping'];
                            }
                        } elseif (stripos($shipping_method_id, 'vconnect_postnord') !== false) {
                            // If vConnect, then filter the shipping method to the correct Smart Send method
                            if ($return) {
                                return 'postnord_returndropoff';
                            } else {
                                if (stripos($shipping_method_id, '_pickup') !== false) {
                                    return 'postnord_agent';
                                } elseif (stripos($shipping_method_id, '_dpd') !== false) {
                                    return 'postnord_homedelivery';
                                } elseif (stripos($shipping_method_id, '_commercial') !== false) {
                                    return 'postnord_commercial';
                                } elseif (stripos($shipping_method_id, '_privatehome') !== false) {
                                    $vc_aio_options = get_post_meta($order_id, '_vc_aio_options');
                                    $flexDelivery = false;
                                    $flexDeliveryOption = false;
                                    $dayDelivery = false;
                                    if (is_array($vc_aio_options)) {
                                        foreach ($vc_aio_options as $option) {
                                            // Check if shipping method has flexDelivery enabled (the parcel can be left somewhere)
                                            if (array_search('flexDelivery',
                                                    array_column($option, 'value')) !== false) {
                                                $flexDelivery = true;
                                            }
                                            // Check if shipping method has dayDelivery enabled (customer will receive an SMS with possibility to choose)
                                            if (array_search('dayDelivery', array_column($option, 'value')) !== false) {
                                                $dayDelivery = true;
                                            }
                                            // A flexDelivery option is chosen
                                            if (!empty($option['typeId']['value']) && $option['typeId']['value'] == 'flexDelivery'
                                                && !empty($option['addressText']['value'])) {
                                                $flexDeliveryOption = true;
                                            }
                                        }
                                    }
                                    if (!$flexDelivery && !$dayDelivery && !$flexDeliveryOption) {
                                        return 'postnord_homedelivery';
                                    } elseif (!$flexDelivery && !$flexDeliveryOption && $dayDelivery) {
                                        return 'postnord_flexhome';
                                    } elseif ($flexDelivery && !$flexDeliveryOption && !$dayDelivery) {
                                        return 'postnord_doorstep';
                                        // The chosen flexdelivy option must be used to tell PostNord where the parcel should be left
                                    } elseif ($flexDelivery && $flexDeliveryOption && !$dayDelivery) {
                                        // The chosen flexdelivy option must be used to tell PostNord where the parcel should be left
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return '';
        }

	    /**
         * Agent meta data updated
         *
         *
	     * @since 5.0.0
	     *
	     * @param null|bool   $check      Whether to allow updating metadata for the given type.
	     * @param int         $meta_id    Meta ID.
	     * @param mixed       $meta_value Meta value. Must be serializable if non-scalar.
	     * @param string|bool $meta_key   Meta key, if provided.
         * @return bool                   Returning a non-null value will effectively short-circuit the function.
	     */
        public function filter_update_agent_meta($check, $meta_id, $meta_value, $meta_key) {
            if ($meta_key == 'ss_shipping_order_agent_no') {
                $meta = get_metadata_by_mid( 'post', $meta_id );
                $object_id    = $meta->post_id;
                if ($this->save_shipping_agent( $object_id, true, $meta_value ) !== true) {
	                // the agent was not found so do NOT save the new agent_no
                    $check = false;
                }
            }

            return $check;
        }

	    /**
         * Agent meta deleted
         * Fires immediately after deleting metadata of a specific type.
	     *
	     * @since WP 2.9.0
	     *
	     * @param array  $meta_ids    An array of deleted metadata entry IDs.
	     * @param int    $object_id   Object ID.
	     * @param string $meta_key    Meta key.
	     * @param mixed  $_meta_value Meta value.
	     */
        public function action_deleted_agent_meta($meta_ids, $object_id, $meta_key, $_meta_value) {

            if ($meta_key == 'ss_shipping_order_agent_no') {
                $this->delete_ss_shipping_order_agent( $object_id );
            }

        }

	    /**
         * Call the API if needed and save the shipping agent address
         *
	     * @param $post_id
	     * @param $doing_ajax
	     * @param $ss_shipping_agent_no
	     *
	     * @return bool|string         Returns true for success and false or a string when failing
	     */
        protected function save_shipping_agent( $post_id, $doing_ajax, $ss_shipping_agent_no ) {

            $ss_shipping_method_id = $this->get_smart_send_method_id($post_id);

            if (!empty($ss_shipping_method_id)) {
                $shipping_method_carrier = SS_SHIPPING_WC()->get_shipping_method_carrier($ss_shipping_method_id);

                $order = wc_get_order($post_id);
                $shipping_address = $order->get_address('shipping');

                if (!empty($shipping_method_carrier) && !empty($shipping_address['country'])) {

                    SS_SHIPPING_WC()->log_msg('Called "getAgentByAgentNo" with carrier = ' . $shipping_method_carrier . ', country = ' . $shipping_address['country'] . ', ss_shipping_agent_no = ' . $ss_shipping_agent_no);
                    // API call to get agent info by agent no.
                    if (SS_SHIPPING_WC()->get_api_handle()->getAgentByAgentNo($shipping_method_carrier,
                        $shipping_address['country'], $ss_shipping_agent_no)) {

                        SS_SHIPPING_WC()->log_msg('Agent found and saved.');

                        $this->save_ss_shipping_order_agent($post_id,
                            SS_SHIPPING_WC()->get_api_handle()->getData());
                        return true;
                    } else {

                        SS_SHIPPING_WC()->log_msg('Agent NOT found.');

                        $error_msg = sprintf(__('The agent number entered, %s, was not found.',
                            'smart-send-logistics'), $ss_shipping_agent_no);

                        if ($doing_ajax) {
                            return $error_msg;
                        } else {
                            WC_Admin_Meta_Boxes::add_error($error_msg);
                            return false;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Save Agent No. and Generate Label
         */
        public function generate_label()
        {
            check_ajax_referer('create-ss-shipping-label',
                'ss_shipping_label_nonce'); //This function dies if the referer is not correct
            $order_id = wc_clean($_POST['order_id']);
            $return = boolval($_POST['return_label']);
            $split_parcel = boolval($_POST['ss_shipping_split_parcel']);

            // Save parcels input if set:
            $parcels = ($split_parcel) ? $_POST['ss_shipping_parcels'] : array();
            $this->save_ss_shipping_order_parcels($order_id, $parcels);


            $response = $this->create_label_for_single_order_maybe_return($order_id, $return, false);

            wp_send_json($response);
            wp_die();
        }

        /**
         * Create shipment objects for a single WooCommerce order and maybe also for a return label
         *
         * The shipments object are used when creating a shipping label via the Smart Send API.
         * Will also generate a return shipment if return is false (normal) and the setting for auto-generating returns labels is on.
         *
         * @param int $order_id     WC Order ID
         * @param boolean $return   Whether or not the label is return (true) or normal (false)
         * @return array
         */
        public function get_shipment_object_array_for_single_order_maybe_return(
            $order_id,
            $return = false
        ) {

            $reponse_arr = array();

            $ss_shipping_method_id = $this->get_smart_send_method_id($order_id, true);

            // Load WC Order
            $order = wc_get_order($order_id);

            // If creating normal label and auto generate return flag is enabled, create both
            if (!$return &&
                isset($ss_shipping_method_id['smart_send_auto_generate_return_label']) &&
                $ss_shipping_method_id['smart_send_auto_generate_return_label'] == 'yes') {

                $ss_order_api = new SS_Shipping_Shipment($order, $this);
                $ss_order_api->make_single_shipment_api_payload( false );
                $shipment = $ss_order_api->get_shipment();

                array_push($reponse_arr, $shipment);

                $ss_order_api = new SS_Shipping_Shipment($order, $this);
                $ss_order_api->make_single_shipment_api_payload( true );
                $shipment = $ss_order_api->get_shipment();

                array_push($reponse_arr, $shipment);

            } else {
                $ss_order_api = new SS_Shipping_Shipment($order, $this);
                $ss_order_api->make_single_shipment_api_payload( $return );
                $shipment = $ss_order_api->get_shipment();

                array_push($reponse_arr, $shipment);
            }

            return $reponse_arr;
        }

        /**
         * Create label for a single WooCommerce order and maybe auto generate return label
         *
         * @param int $order_id Order ID
         * @param boolean $return Whether or not the label is return (true) or normal (false)
         * @param boolean $setting_save_order_note Whether or not to save an order note with information about label
         *
         * @return array
         */
        public function create_label_for_single_order_maybe_return(
            $order_id,
            $return = false,
            $setting_save_order_note = true
        ) {

            $reponse_arr = array();

            $ss_shipping_method_id = $this->get_smart_send_method_id($order_id, true);

            // If creating normal label and auto generate return flag is enabled, create both
            if (!$return &&
                isset($ss_shipping_method_id['smart_send_auto_generate_return_label']) &&
                $ss_shipping_method_id['smart_send_auto_generate_return_label'] == 'yes') {

                // Create the normal label
                $response = $this->create_label_for_single_order($order_id, false, $setting_save_order_note);
                array_push($reponse_arr, $response);

                // We're only creating the return label if the normal label creation is successful.
                if (isset($response['success']->woocommerce)) {
                    // Create the return label
                    $response = $this->create_label_for_single_order($order_id, true, $setting_save_order_note);
                    array_push($reponse_arr, $response);
                }
            } else {
                $response = $this->create_label_for_single_order($order_id, $return, $setting_save_order_note);
                array_push($reponse_arr, $response);
            }

            return $reponse_arr;
        }

        /**
         * Create label for a single WooCommerce order
         *
         * @param int $order_id Order ID
         * @param boolean $return Whether or not the label is return (true) or normal (false)
         * @param boolean $setting_save_order_note Whether or not to save an order note with information about label
         *
         * @return array
         */
        protected function create_label_for_single_order($order_id, $return = false, $setting_save_order_note = true)
        {
            // Load WC Order
            $order = wc_get_order($order_id);

	        if( 'ss-queue' == $order->get_status() ) {
		        return array('error' => __('Cannot create a label, the order is in the Smart Send queue.', 'smart-send-logistics'));
	        }

            $ss_order_api = new SS_Shipping_Shipment($order, $this);

            if ($ss_order_api->make_single_shipment_api_call( $return )) {

                //The request was successful, lets update WooCommerce
                $response = $ss_order_api->get_shipping_data();

                $this->create_pdf_set_wc( $response, $order_id, $return, $setting_save_order_note );

                // return the success data
                return array('success' => $response, 'shipment' => $ss_order_api->get_shipment());
            } else {

                if (!$return) {
                    $this->set_order_status_after_label_failed($order);
                }
                // Something failed. Let's return them, so the error can be shown to the user
                return array('error' => $ss_order_api->get_error_msg());
            }
        }

        public function create_pdf_set_wc( $response, $order_id, $return = false, $setting_save_order_note = true ) {

            // Load WC Order
            $order = wc_get_order($order_id);

            if (SS_SHIPPING_WC()->get_setting_save_shipping_labels_in_uploads()) {
                try {
                    // Save the PDF file
                    $labelUrl = $this->save_label_file($response->shipment_id, $response->pdf->base_64_encoded,
                        $return);
                } catch (Exception $e) {
                    return array('error' => $e->getMessage());
                }
            }

            // Get the label link
            $labelUrl = $response->pdf->link;

            // save order meta data
            $this->save_ss_shipment_id_in_order_meta($order_id, $response->shipment_id, $return);

            // Get formatted order comment
            $response->woocommerce['label_url'] = $labelUrl;
            $response->woocommerce['order_note'] = $this->get_formatted_order_note_with_label_and_tracking($order_id,
                $response, $return);
            $response->woocommerce['return'] = $return;

            // Save order note
            if ($setting_save_order_note) {
                /*
                 * Filter the order comment that is saved. The order comment can be seen in the WooCommerce backend
                 *
                 * @param string order note containing tracking link and link to pdf label
                 * @param WC_Order object
                 * @param boolean $return Whether or not the label is return (true) or normal (false)
                 */
                $order_note = apply_filters('smart_send_shipping_label_comment',
                    $response->woocommerce['order_note'], $order, $return);
                $order->add_order_note($order_note, 0, true);
            }

            // Add tracking info to "WooCommerce Shipment Tracking" plugin
            foreach ($response->parcels as $parcel) {
                // Only add tracking info to "WooCommerce Shipment Tracking" plugin for non-return parcels
                if (!$return) {
                    $this->save_tracking_in_shipment_tracking($order_id, $parcel->tracking_code,
                        $parcel->tracking_link,
                        $response->carrier_name);
                }
            }

            // Set order status after label generation
            // Important to update AFTER saving meta fields and tracking information (otherwise not included in email via Shipment Tracking)
            if (!$return) {
                $this->set_order_status_after_label_generated($order);
            }

            // Action when a shipping label has been created
            do_action('smart_send_shipping_label_created', $order_id, $response);

        }

        public function label_creation_failed( $response, $order_id, $return = false, $setting_save_order_note = true ) {

            // Load WC Order
            $order = wc_get_order($order_id);

            // Save order note
            if ($setting_save_order_note) {
		        $tracking_note = '<b><label>' . ($return ? __('Smart Send return label failed',
				        'smart-send-logistics') : __('Smart Send shipping label failed', 'smart-send-logistics')) . ':</label></b><br>'
		                         . SS_SHIPPING_WC()->get_api_handle()->getErrorString();
                /*
                 * Filter the order comment that is saved. The order comment can be seen in the WooCommerce backend
                 *
                 * @param string order note containing tracking link and link to pdf label
                 * @param WC_Order object
                 * @param boolean $return Whether or not the label is return (true) or normal (false)
                 */
                $order_note = apply_filters('smart_send_shipping_label_comment',
	                $tracking_note, $order, $return);
                $order->add_order_note($order_note, 0, true);
            }

            // Set order status after label generation
            // Important to update AFTER saving meta fields and tracking information (otherwise not included in email via Shipment Tracking)
            if (!$return) {
                $this->set_order_status_after_label_failed($order);
            }

            // Action when a shipping label has been created
            do_action('smart_send_shipping_label_failed', $order_id, $response);

        }

        /**
         * If set to change order after order generated, update order status
         */
        protected function set_order_status_after_label_generated($order)
        {

            $ss_settings = SS_SHIPPING_WC()->get_ss_shipping_settings();

            if (!empty($ss_settings['order_status'])) {
                $order->update_status($ss_settings['order_status']);
            }
        }

        /**
         * If set to change order after order generated, update order status
         */
        public function set_order_status_after_label_failed($order)
        {

            $ss_settings = SS_SHIPPING_WC()->get_ss_shipping_settings();

            if (!empty($ss_settings['order_status_failed'])) {
                $order->update_status($ss_settings['order_status_failed']);
            } else {
                $order->update_status('wc-failed');
            }
        }

        /**
         * Get tracking details from returned shipment details
         */
        protected function get_tracking_details($shipment)
        {
            $tracking_array = array();
            foreach ($shipment->parcels as $parcel) {
                $tracking_array[$parcel->parcel_internal_id] = array(
                    'carrier_code'  => $shipment->carrier_code,
                    'carrier_name'  => $shipment->carrier_name,
                    'tracking_code' => $parcel->tracking_code,
                    /*
                     * Filter the tracking link
                     *
                     * @param string | tracking link
                     * @param string | carrier code
                     */
                    'tracking_link' => apply_filters('smart_send_tracking_url', $parcel->tracking_link,
                        $shipment->carrier_code),
                );
            }
            return $tracking_array;
        }

        /**
         * Get a formatted string containing link to PDF label, tracking code and tracking link.
         * This note is inserted in the order comment.
         *
         * @param int $order_id Order ID
         * @param mixed $api_shipment_response response for API call
         * @param boolean $return true for return labels and false for normal labels (default)
         *
         * @return string HTML formatted note
         */
        protected function get_formatted_order_note_with_label_and_tracking($order_id, $api_shipment_response, $return)
        {

            $tracking_note = '<b><label>' . ($return ? __('Smart Send return label',
                    'smart-send-logistics') : __('Smart Send shipping label', 'smart-send-logistics')) . ':</label></b><br>'
                . $this->get_ss_shipping_label_link($api_shipment_response->woocommerce['label_url'], $return);

            foreach ($api_shipment_response->parcels as $parcel) {
                $tracking_note .= '<br><label>' . __('Tracking number', 'smart-send-logistics') . ':</label><br>'
                    . '<a href="' . $parcel->tracking_link . '" target="_blank">' . $parcel->tracking_code . '</a>';
            }

            return $tracking_note;
        }

        /**
         * Save label file in "uploads" folder
         */
        protected function save_label_file($shipment_id, $label_data, $return)
        {

            if (empty($shipment_id)) {
                throw new Exception(__('Shipment id is empty', 'smart-send-logistics'));
            }

            if (empty($label_data)) {
                throw new Exception(__('Label data empty', 'smart-send-logistics'));
            }

            $label_data_decoded = base64_decode($label_data);
            $file_ret = wp_upload_bits($this->get_label_name_from_shipment_id($shipment_id), null, $label_data_decoded,
                null);

            if (empty($file_ret['url'])) {
                throw new Exception(__('Label file cannot be saved',
                    'smart-send-logistics')); //This exception is not caught
            }

            return $file_ret['url'];
        }

        protected function get_label_url_from_shipment_id($shipment_id)
        {
            $upload_path = wp_upload_dir();
            return $upload_path['url'] . '/' . $this->get_label_name_from_shipment_id($shipment_id);
        }

        public function get_label_path_from_shipment_id($shipment_id)
        {
            $upload_path = wp_upload_dir();
            return $upload_path['path'] . '/' . $this->get_label_name_from_shipment_id($shipment_id);
        }

        protected function get_label_name_from_shipment_id($shipment_id)
        {
            if ($this->label_prefix) {
                $shipment_id = $this->label_prefix . $shipment_id;
            }
            return $shipment_id . '.pdf';
        }


        /**
         * Saves the parcels input to post_meta
         *
         * @param int $order_id
         * @param array $parcels
         *
         * @return void
         */
        public function save_ss_shipping_order_parcels($order_id, $parcels)
        {
            update_post_meta($order_id, 'ss_shipping_order_parcels', $parcels);
        }

        /**
         * Gets parcels input from post_meta
         *
         * @param int $order_id
         * @param array $parcels
         *
         * @return mixed Parcels if present, false otherwise
         */
        public function get_ss_shipping_order_parcels($order_id)
        {
            return get_post_meta($order_id, 'ss_shipping_order_parcels', true);
        }


        /**
         * Saves the label agent no to post_meta.
         *
         * @param int $order_id Order ID
         * @param array $agent_no Agent No.
         *
         * @return void
         */
        public function save_ss_shipping_order_agent_no($order_id, $agent_no)
        {
            update_post_meta($order_id, 'ss_shipping_order_agent_no', $agent_no);
        }

        /*
         * Gets agent no from the post meta array for an order
         *
         * @param int  $order_id  Order ID
         *
         * @return Agent No
         */
        public function get_ss_shipping_order_agent_no($order_id)
        {
            // Fecth agent_no from meta field saved by Smart Send
            $ss_agent_number = get_post_meta($order_id, 'ss_shipping_order_agent_no', true);
            if ($ss_agent_number) {
                // Return the agent_no found
                return $ss_agent_number;
            } else {
                // No Smart Send agent_no was found, check if the order has a vConnect agent_no
                $vc_aio_meta = get_post_meta($order_id, '_vc_aio_options', true);
                if (!empty($vc_aio_meta['addressId']['value'])) {
                    return $vc_aio_meta['addressId']['value'];
                } else {
                    return null;
                }
            }
        }

        /**
         * Saves the agent object to post_meta.
         *
         * @param int $order_id Order ID
         * @param array $agent Agent Object
         *
         * @return void
         */
        public function save_ss_shipping_order_agent($order_id, $agent)
        {
            update_post_meta($order_id, '_ss_shipping_order_agent', $agent);
        }

	    /**
         * Delete shippng agent object
         *
	     * @param $order_id
	     */
        public function delete_ss_shipping_order_agent($order_id) {
            delete_post_meta($order_id, '_ss_shipping_order_agent');
        }

        /*
         * Gets agent object from the post meta array for an order
         *
         * @param int  $order_id  Order ID
         *
         * @return Agent Object
         */
        public function get_ss_shipping_order_agent($order_id)
        {
            // Fetch agent info from meta field saved by Smart Send
            $ss_agent_info = get_post_meta($order_id, '_ss_shipping_order_agent', true);
            if ($ss_agent_info) {
                // Return the agent_no found
                return $ss_agent_info;
            } else {
                // No Smart Send agent_no was found, check if the order has a vConnect agent_no
                $vc_aio_meta = get_post_meta($order_id, '_vc_aio_options', true);
                if (!empty($vc_aio_meta['addressId']['value'])) {
                    return (object)array(
                        'agent_no'      => isset($vc_aio_meta['addressId']['value']) ? $vc_aio_meta['addressId']['value'] : null,
                        'company'       => isset($vc_aio_meta['name']['value']) ? $vc_aio_meta['name']['value'] : null,
                        'address_line1' => isset($vc_aio_meta['addressText']['value']) ? $vc_aio_meta['addressText']['value'] : null,
                        'address_line2' => null,
                        'city'          => isset($vc_aio_meta['city']['value']) ? $vc_aio_meta['city']['value'] : null,
                        'postal_code'   => isset($vc_aio_meta['postcode']['value']) ? $vc_aio_meta['postcode']['value'] : null,
                        'country'       => isset($vc_aio_meta['country']['value']) ? $vc_aio_meta['country']['value'] : null,
                    );
                } else {
                    return null;
                }
            }
        }

        /**
         * Saves the Shipment ID to post_meta.
         *
         * @param int $order_id Order ID
         * @param string $shipment_id Shipment ID
         * @param boolean $return Whether or not the label is return (true) or normal (false)
         *
         * @return void
         */
        public function save_ss_shipment_id_in_order_meta($order_id, $shipment_id, $return)
        {
            if ($return) {
                update_post_meta($order_id, '_ss_shipping_return_label_id', $shipment_id);
            } else {
                update_post_meta($order_id, '_ss_shipping_label_id', $shipment_id);
            }
        }

        /**
         * Get the Shipment ID from post_meta.
         *
         * @param int $order_id Order ID
         * @param boolean $return Whether or not the label is return (true) or normal (false)
         *
         * @return string $shipment_id Shipment ID
         */
        public function get_ss_shipment_id_from_order_meta($order_id, $return)
        {
            if ($return) {
                $shipment_id = get_post_meta($order_id, '_ss_shipping_return_label_id', true);
            } else {
                $shipment_id = get_post_meta($order_id, '_ss_shipping_label_id', true);
            }

            return $shipment_id;
        }

        /**
         * Gets label URL post meta array for an order
         *
         * @param int  $order_id  Order ID
         * @param boolean $return Whether or not the label is return (true) or normal (false)
         *
         * @return string URL label link
         */
        public function get_label_url_from_order_id($order_id, $return)
        {
            $shipment_id = $this->get_ss_shipment_id_from_order_meta($order_id, $return);

            return $this->get_label_url_from_shipment_id($shipment_id);
        }

        /**
         * Get formatted label link
         *
         * @param string $url label url
         * @param boolean $return Whether or not the label is return (true) or normal (false)
         *
         * @return string html label link
         */
        public function get_ss_shipping_label_link($url, $return)
        {
            if ($return) {
                $message = __('Download return label', 'smart-send-logistics');
            } else {
                $message = __('Download shipping label', 'smart-send-logistics');
            }
            return '<a href="' . $url . '" target="_blank">' . $message . '</a>';
        }


        /**
         * Save tracking number in Shipment Tracking
         *
         * @param int $order_id Order ID
         * @param string $tracking_number Unique tracking code for parcel
         * @param string $tracking_url Url for tracking parcel delivery
         * @param string $provider Carrier provider
         * @param string $date_shipped Shipping data in format YYYY-mm-dd
         *
         * @return void
         */
        public function save_tracking_in_shipment_tracking(
            $order_id,
            $tracking_number,
            $tracking_url,
            $provider = 'Smart Send',
            $date_shipped = null
        ) {

            if (function_exists('wc_st_add_tracking_number')) {
                wc_st_add_tracking_number($order_id, $tracking_number, $provider, $date_shipped, $tracking_url);
            }
        }

        /**
         * Prevents data being copied to subscription renewals
         */
        public function woocommerce_subscriptions_renewal_order_meta_query($order_meta_query)
        {
            $order_meta_query .= " AND `meta_key` NOT IN ( '_ss_shipping_label' )";

            return $order_meta_query;
        }

        /*
         * Get an orders total weight
         *
         * @param WC_Order | $order
         * @return float weight in kg
         */
        protected function getOrderWeight($order)
        {
            $weight_total = 0;

            // Get order item specific data
            $ordered_items = $order->get_items();
            if (!empty($ordered_items)) {
                foreach ($ordered_items as $key => $item) {
                    $product = wc_get_product($item['product_id']);
                    if (!empty($item['variation_id'])) {
                        $product_variation = wc_get_product($item['variation_id']);
                    } else {
                        $product_variation = $product;
                    }
                    $product_weight = round(wc_get_weight($product_variation->get_weight(), 'kg'), 2);
                    if ($product_weight) {
                        $weight_total += ($item['qty'] * $product_weight);
                    }
                }
            }
            return $weight_total;
        }
    }

endif;
