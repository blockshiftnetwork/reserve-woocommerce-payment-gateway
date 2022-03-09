<?php
/*
Plugin Name: Reserve Woocommerce Payment Gateway
Plugin URI: http://woothemes.com/reserve-woocommerce-payment-gateway/
Description: Receive Payments from Reserve offline
Version: 0.0.0
Author: Alexander Rodriguez <alexander@marketingfino.com.ve>
Author URI: http://woothemes.com/

	Copyright: © 2022 Alexander Rodriguez <alexander@marketingfino.com.ve>.
	License: GNU General Public License v2
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

add_action( 'plugins_loaded', 'ReserveWoocommercePaymentGateway_init', 0 );
function ReserveWoocommercePaymentGateway_init() {

	if ( ! class_exists( 'ReserveWoocommercePaymentGateway' ) ) return;

	/**
 	 * Localisation
	 */
	load_plugin_textdomain( 'reserve-woocommerce-payment-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	/**
 	 * Gateway class
 	 */
	class ReserveWoocommercePaymentGateway extends WC_Payment_Gateway {
		/**
	     * Constructor for the gateway.
	     */
	    public function __construct() {
			$this->id                 = 'ReserveWoocommercePaymentGateway';
			$this->icon               = apply_filters('woocommerce_ReserveWoocommercePaymentGateway_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Reserve Woocommerce Payment Gateway', 'reserve-woocommerce-payment-gateway' );
			$this->method_description = __( 'This is the payment gateway description', 'reserve-woocommerce-payment-gateway' );

			// Load the settings.
			$this->init_form_fields();

	        // Define user set variables
			$this->title         = $this->get_option( 'title' );
			$this->description   = $this->get_option( 'description' );
			$this->example_field = $this->get_option( 'example_field' );

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	    	add_action( 'woocommerce_thankyou_ReserveWoocommercePaymentGateway', array( $this, 'thankyou_page' ) );
	    }

		/**
	     * Create form fields for the payment gateway
	     *
	     * @return void
	     */
	    public function init_form_fields() {
	        $this->form_fields = array(
	            'enabled' => array(
	                'title' => __( 'Enable/Disable', 'reserve-woocommerce-payment-gateway' ),
	                'type' => 'checkbox',
	                'label' => __( 'Enable Reserve Woocommerce Payment Gateway', 'reserve-woocommerce-payment-gateway' ),
	                'default' => 'no'
	            ),
	            'title' => array(
	                'title' => __( 'Title', 'reserve-woocommerce-payment-gateway' ),
	                'type' => 'text',
	                'description' => __( 'This controls the title which the user sees during checkout', 'reserve-woocommerce-payment-gateway' ),
	                'default' => __( 'Reserve Woocommerce Payment Gateway', 'reserve-woocommerce-payment-gateway' ),
	                'desc_tip'      => true,
	            ),
	            'description' => array(
	                'title' => __( 'Customer Message', 'reserve-woocommerce-payment-gateway' ),
	                'type' => 'textarea',
	                'default' => __( 'Description of the payment gateway', 'reserve-woocommerce-payment-gateway' )
	            ),
				'example_field' => array(
					'title' => __( 'Example field', 'reserve-woocommerce-payment-gateway' ),
					'type' => 'text',
					'default' => __( 'Example field description', 'reserve-woocommerce-payment-gateway' )
				),
	        );
	    }

	    /**
	     * Process the order payment status
	     *
	     * @param int $order_id
	     * @return array
	     */
	    public function process_payment( $order_id ) {
	        $order = new WC_Order( $order_id );

	        // Mark as on-hold (we're awaiting the cheque)
	        $order->update_status( 'on-hold', __( 'Awaiting payment', 'reserve-woocommerce-payment-gateway' ) );

	        // Reduce stock levels
	        $order->reduce_order_stock();

	        // Remove cart
	        WC()->cart->empty_cart();

	        // Return thankyou redirect
	        return array(
	            'result'    => 'success',
	            'redirect'  => $this->get_return_url( $order )
	        );
	    }

	    /**
	     * Output for the order received page.
	     *
	     * @return void
	     */
	    public function thankyou() {
	        if ( $description = $this->get_description() )
	            echo wpautop( wptexturize( wp_kses_post( $description ) ) );

	        echo '<h2>' . __( 'Our Details', 'reserve-woocommerce-payment-gateway' ) . '</h2>';

	        echo '<ul class="order_details ReserveWoocommercePaymentGateway_details">';

	        $fields = apply_filters( 'woocommerce_ReserveWoocommercePaymentGateway_fields', array(
	            'example_field'  => __( 'Example field', 'reserve-woocommerce-payment-gateway' )
	        ) );

	        foreach ( $fields as $key => $value ) {
	            if ( ! empty( $this->$key ) ) {
	                echo '<li class="' . esc_attr( $key ) . '">' . esc_attr( $value ) . ': <strong>' . wptexturize( $this->$key ) . '</strong></li>';
	            }
	        }

	        echo '</ul>';
	    }
	}

	/**
 	* Add the Gateway to WooCommerce
 	**/
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_gateway_ReserveWoocommercePaymentGateway' );
	function woocommerce_add_gateway_ReserveWoocommercePaymentGateway($methods) {
		$methods[] = 'ReserveWoocommercePaymentGateway';
		return $methods;
	}
}
