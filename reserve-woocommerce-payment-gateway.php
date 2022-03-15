<?php
/*
 * Plugin Name: Reserve WooCommerce Payment Gateway
 * Description: Reserve app allows people to save, spend, and receive money in stable currency, without the need for a foreign bank account
 * Author: Blockshift.us
 * Author URI: https://blockshift.us/
 * Version: 1.0.0
 */

add_filter('woocommerce_payment_gateways', 'add_reserve_gateway_class');
function add_reserve_gateway_class($gateways)
{
    $gateways[] = 'WC_Reserve_Gateway';
    return $gateways;
}


add_action('plugins_loaded', 'initialize_gateway_class');
function initialize_gateway_class()
{

    class WC_Reserve_Gateway extends WC_Payment_Gateway
    {

        /*
         * Class constructor
         */
        public function __construct()
        {

            $this->id = 'reserve'; // payment gateway ID
            $this->icon = ''; // payment gateway icon
            $this->has_fields = true; // for reference input form
            $this->title = __('Reserve (Offline)', 'reserve-payment-gateway'); // vertical tab title
            $this->method_title = __('Reserve (Offline)', 'reserve-payment-gateway'); // payment method name
            $this->method_description = __('Reserve Offline payment gateway', 'reserve-payment-gateway'); // payment method description
        
            // load backend options fields
            $this->init_form_fields();
        
            // load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->username = $this->get_option('username');
            $this->base_url = 'https://rpay.app/';
        
            // Action hook to saves the settings
            if (is_admin()) {
                  add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
            }

            add_action('wp_enqueue_scripts', array( $this, 'register_plugin_styles' ));
        }

        /*
         * Plugin options and setting fields
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable/Disable', 'reserve-payment-gateway'),
                    'label'       => __('Enable Reserve Gateway', 'reserve-payment-gateway'),
                    'type'        => 'checkbox',
                    'description' => __('This enable the gateway which allow to accept payment through Reserve Offline.', 'reserve-payment-gateway'),
                    'default'     => 'no',
                    'desc_tip'    => true
                ),
                'title' => array(
                    'title'       => __('Title', 'reserve-payment-gateway'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'reserve-payment-gateway'),
                    'default'     => __('Reserve', 'reserve-payment-gateway'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'reserve-payment-gateway'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'reserve-payment-gateway'),
                    'default'     => __('Pay with your Reserve App.', 'reserve-payment-gateway'),
                ),
                'username' => array(
                    'title'       => __('Reserve Username', 'reserve-payment-gateway'),
                    'type'        => 'text',
                    'description' => __('Reserve username without @ symbol', 'reserve-payment-gateway'),
                    'desc_tip'    => true,
                ),
            );
        }


        /*
         * Custom CSS and JS
         */
        public function payment_scripts()
        {

            wp_register_style('reserve-woocommerce-payment-gateway', plugins_url('reserve-woocommerce-payment-gateway/css/custom.css'));
            wp_enqueue_style('reserve-woocommerce-payment-gateway');
        }

        public function generate_qr()
        {
            $url = urlencode($this->base_url.$this->username.'?web=1');
            return "<img alt='Reserve QR' style='float: inherit;	margin-left: auto;	margin-right: auto;	max-height: inherit;' src='https://chart.googleapis.com/chart?chl={$url}&cht=qr&choe=UTF-8&chs=250x250'>";
        }

        public function payment_fields()
        {

            if ($this->description) {
                if ($this->test_mode) {
                    $this->description .= ' Test mode is enabled. You can use the dummy credit card numbers to test it.';
                }
                echo wpautop(wp_kses_post($this->description));
            }
            
            ?>
        
            <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
        
                <div class="form-row form-row-wide" style="text-align: center;">
                <?php echo $this->generate_qr(); ?>
                <p>Escanea para pagar en dólares a</p>
                <span class="user" id="username" style="font-weight: 600; font-size: 18px;"><?php echo $this->username; ?></span>
                </div>

                <div class="form-row" id="blockshifht">
                    <label><?php __('Enter the Reserve transaction number:', 'reserve-payment-gateway')?><span class="required">*</span></label>
                    <input id="ybc_cvc" type="text" autocomplete="off" placeholder="<?php __('Transaction Number: G######', 'reserve-payment-gateway')?>">
                </div>
                <div class="clear"></div>
        
                <div class="clear"></div>
        
            </fieldset>
        
            <?php
        }


        /*
         * Process the payments here
         */
        public function process_payment($order_id)
        {

            global $woocommerce;
            $order = new WC_Order($order_id);

            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('on-hold', __('Awaiting payment', 'reserve-payment-gateway'));

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url($order)
            );
        }


        public function thankyou()
        {
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize(wp_kses_post($description)));
            }

            echo '<h2>' . __('Our Details', 'reserve-payment-gateway') . '</h2>';

            echo '<ul class="order_details ReserveWoocommercePaymentGateway_details">';

            $fields = apply_filters('woocommerce_ReserveWoocommercePaymentGateway_fields', array(
                'example_field'  => __('Example field', 'reserve-payment-gateway')
            ));

            foreach ($fields as $key => $value) {
                if (! empty($this->$key)) {
                    echo '<li class="' . esc_attr($key) . '">' . esc_attr($value) . ': <strong>' . wptexturize($this->$key) . '</strong></li>';
                }
            }

            echo '</ul>';
        }
    }
}

function reserve_gateway_icon($icon, $id)
{
    if ($id === 'reserve') {
        return '<img src="https://reserve.org/assets/img/reserve_black.svg" alt="Reserve logo" />';
    } else {
        return $icon;
    }
}
add_filter('woocommerce_gateway_icon', 'reserve_gateway_icon', 10, 2);
