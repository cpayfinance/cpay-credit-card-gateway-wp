<?php

/*
 * Plugin Name: CPay Credit Card Payment Gateway
 * Plugin URI: https://cpay.finance
 * Description: CPay Credit Card Payment Gateway.
 * Author: CPay
 * Author URI: https://cpay.finance
 * Version: 1.0.0
 *
 */

defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (is_plugin_active('woocommerce/woocommerce.php') === true) {
    require_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
    // Add the Gateway to WooCommerce.
    add_filter('woocommerce_payment_gateways', 'wc_ljkjcpay_gateway');
    add_action('plugins_loaded', 'woocommerce_ljkjcpay_init', 0);


    /**
     * Add the gateways to WooCommerce
     *
     * @param array $methods $args {
     *                       Optional. An array of arguments.
     *
     * @type   type $key Description. Default 'value'. Accepts 'value', 'value'.
     *    (aligned with Description, if wraps to a new line)
     * @type   type $key Description.
     * }
     * @return array $methods
     * @since  1.0.0
     */
    function wc_ljkjcpay_gateway( $methods) {
        $methods[] = 'ljkjcpay';
        return $methods;

    }

    /**
     * Add the Gateway to WooCommerce init
     *
     * @return bool
     */
    function woocommerce_ljkjcpay_init() {
        if (class_exists('WC_Payment_Gateway') === false) {
            return;
        }
    }

    /**
     * Define ljkjcpay Class
     *
     * @package  WooCommerce
     * @author   ljkjcpay <dev@cpay.finance>
     * @link     cpay.finance
     */
    class Ljkjcpay extends WC_Payment_Gateway {
        /**
         * Define Ljkjcpay Class constructor
         **/
        public function __construct() {
            $this->id   = 'ljkjcpay';
            $this->icon = ''; // plugins_url('images/crypto.png', __FILE__);

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->merchantid  = $this->get_option('merchantid');

            $this->apikey         = '1';
            $this->secret         = $this->get_option('secret');
            $this->msg['message'] = '';
            $this->msg['class']   = '';

            add_action('init', array(&$this, 'check_cpaycreditcard_response'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            add_action('woocommerce_api_' . strtolower(get_class($this)).'_creditcard_callback', array( &$this, 'check_cpaycreditcard_response' ));

            // Valid for use.
            if (empty($this->settings['enabled']) === false && empty($this->apikey) === false && empty($this->secret) === false) {
                $this->enabled = 'yes';
            } else {
                $this->enabled = 'no';
            }

            // Checking if apikey is not empty.
            if (empty($this->apikey) === true) {
                add_action('admin_notices', array( &$this, 'apikey_missingmessage' ));
            }

            // Checking if app_secret is not empty.
            if (empty($this->secret) === true) {
                add_action('admin_notices', array( &$this, 'secret_missingmessage' ));
            }

        }

        /**
         * Define initFormfields function
         *
         * @return mixed
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'     => array(
                    'title'   => __('Enable/Disable', 'Cpay Credit Card'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Ljkjcpay', 'Cpay Credit Card'),
                    'default' => 'yes',
                ),
                'title'       => array(
                    'title'       => __('Title', 'Cpay Credit Card'),
                    'type'        => 'text',
                    'description' => __('This controls the title the user can see during checkout.', 'Cpay Credit Card'),
                    'default'     => __('Cpay Credit Card', 'Cpay Credit Card'),
                ),
                'description' => array(
                    'title'       => __('Description', 'Cpay Credit Card'),
                    'type'        => 'textarea',
                    'description' => __('This controls the title the user can see during checkout.', 'Cpay Credit Card'),
                    'default'     => __('You will be redirected to cpay.finance to complete your purchase.', 'Cpay Credit Card'),
                ),
                'merchantid'  => array(
                    'title'       => __('Your MerchantID', 'N/A'),
                    'type'        => 'text',
                    'description' => __('Please enter your Merchant ID, You can get this information from cpay.finance', 'Cpay Credit Card'),
                    'default'     => '',
                ),
                'secret'      => array(
                    'title'       => __('SecurityKey', 'Ljkjcpay'),
                    'type'        => 'password',
                    'description' => __('Please enter your cpay SecurityKey, You can get this information from cpay.finance', 'Cpay Credit Card'),
                    'default'     => '',
                ),
            );

        }//end init_form_fields()


        /**
         * Define adminOptions function
         *
         * @return mixed
         */
        public function admin_options() {
            ?>
            <h3><?php esc_html_e('Cpay Checkout', 'Cpay'); ?></h3>

            <div id="wc_get_started">
                <span class="main"><?php esc_html_e('Provides a secure way to accept crypto currencies.', 'Cpay'); ?></span>
                <p><a href="https://cpay.finance" target="_blank" class="button button-primary"><?php esc_html_e('Join free', 'Cpay'); ?></a> <a href="https://cpay.finance" target="_blank" class="button"><?php esc_html_e('Learn more about WooCommerce and Cpay', 'Cpay'); ?></a></p>
            </div>

            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php

        }//end admin_options()


        /**
         *  There are no payment fields for Cpay, but we want to show the description if set.
         *
         * @return string
         **/
        public function payment_fields() {
            if (strlen($this->description) > 0) {
                echo esc_html($this->description);
            }
        }//end payment_fields()


        /**
         * Process the payment and return the result
         *
         * @param int $orderid comment
         *
         * @return $array
         **/
        public function process_payment( $orderid )
        {
            global $woocommerce;
            $order = wc_get_order($orderid);

            if ($order->get_total() < 0.1) {
                wc_add_notice(__('Payment error:', 'cpay'), 'error', 'order total amount less than 0.10' );
                exit();
            }

            $itemnames = array();
            if (count($order->get_items()) > 0) {
                foreach ($order->get_items() as $item) {
                    if ($item->get_quantity() > 0) {
                        // [[{"name":"USDT","price":"0.00","num":"200","currency":"USDT"}]]
                        $itemnames[] = ['name' => $item->get_name(),
                            'price' => $item->get_total(),
                            'num' => $item->get_quantity(),
                            'currency' => get_woocommerce_currency()];
                    }
                }
            }

            list($usec, $sec) = explode(" ", microtime());
            $order_data = $order->get_data();
            $ss = 'amount=' . number_format($order->get_total(), 2, '.', '').
                '&callBackURL=' . site_url('/?wc-api=ljkjcpay_creditcard_callback').
                '&country=' . (isset($order_data['billing']['country']) ? $order_data['billing']['country'] : '').
                '&createTime=' . round($sec*1000).
                '&currency='.get_woocommerce_currency().
                '&email='.(isset($order_data['billing']['email']) ? $order_data['billing']['email'] : '').
                '&ip='.(isset($order_data['customer_ip_address']) ? $order_data['customer_ip_address'] : '').
                '&merchantId=' . $this->merchantid .
                '&merchantTradeNo=' . $this->merchantid . '_' . $orderid .
                '&products=' . json_encode($itemnames).
                '&userId=' . $sec.
                '&key=' . $this->secret;

            $ps = array(
                'merchantId=' . $this->merchantid,
                'merchantTradeNo=' . $this->merchantid . '_' . $orderid,
                'createTime=' . round($sec*1000),
                'userId=' . $sec,
                'ip=' . (isset($order_data['customer_ip_address']) ? $order_data['customer_ip_address'] : ''),
                'email='.(isset($order_data['billing']['email']) ? $order_data['billing']['email'] : ''),
                'country=' . (isset($order_data['billing']['country']) ? $order_data['billing']['country'] : ''),
                'products=' . json_encode($itemnames),
                'currency='.get_woocommerce_currency(),
                'amount=' . number_format($order->get_total(), 2, '.', ''),
                'callBackURL=' . site_url('/?wc-api=ljkjcpay_creditcard_callback'),
                'returnURL=' . '', // 可为空 没用
                'successURL=' . '',
                'failURL=' . '',
                'sign=' . hash_hmac("sha256", $ss, $this->secret),
                'extInfo=' . '', // 可为空
            );

            $params    = array(
                'body' => implode($ps, '&'),
            );

            $url       = 'https://api.cpay.ltd/openapi/v1/createOrderByCreditCard';
            $response  = wp_safe_remote_post($url, $params);
            if (( false === is_wp_error($response) ) && ( 200 === $response['response']['code'] ) && ( 'OK' === $response['response']['message'] )) {
                $body = json_decode($response['body'], true);
                if (isset($body['code']) && $body['code'] == 0) {
                    // 更新订单状态为等待中 (等待第三方支付网关返回)
                    $order->update_status('pending', __( 'Awaiting payment', 'woocommerce' ));
                    $order->reduce_order_stock(); // 减少库存
                    $woocommerce->cart->empty_cart(); // 清空购物车
                    $rr = array(
                        'result'   => 'success',
                        'redirect' => isset($body['data']['cashierURL']) ? $body['data']['cashierURL'] : '',
                    );
                    return $rr;
                }
            }
            wc_add_notice(__('Payment error:', 'cpay'), 'error', 'call cpay payment gateway fail' );

        }//end process_payment()

        /**
         * Check for valid Cpay server callback
         *
         *
         * @return string
         **/
        public function check_cpaycreditcard_response() {
            global $woocommerce;
            $body = file_get_contents('php://input');
            $body_data = json_decode($body, true);
            $orderid = '';
            if (isset($body_data['merchantTradeNo']) && !empty($body_data['merchantTradeNo'])) {
                $oid = explode('_', $body_data['merchantTradeNo']);
                if (count($oid)>1) {
                    $orderid = $oid[1];
                }
            }
            if (empty($orderid) || !isset($body_data['orderStatus']) || !in_array($body_data['orderStatus'], [14, 15])) {
                echo 'invalid callback param';
                exit();
            }

            /**
             $order_statuses = array(
            'wc-pending'    => _x( 'Pending payment', 'Order status', 'woocommerce' ),
            'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
            'wc-on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
            'wc-completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
            'wc-cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
            'wc-refunded'   => _x( 'Refunded', 'Order status', 'woocommerce' ),
            'wc-failed'     => _x( 'Failed', 'Order status', 'woocommerce' ),
            );
             */
            $order_status = $body_data['orderStatus'];
            $order = new WC_Order($orderid);
            if ($order_status == 14) {
                // Do your magic here, and return 200 OK to ljkjcpay.
                if ('pending' === $order->status) {
                    $order->update_status('processing', sprintf(__('IPN: Payment completed notification from Cpay', 'woocommerce')));
                    $order->save();
                    $order->add_order_note( __( 'IPN: Update status event for Cpay', 'woocommerce' ) . ' ' . $orderid);
                }
                echo 'ok';
                exit;
            } else {
                if ('failed' !== $order->status) {
                    $order->update_status('failed', sprintf(__('IPN: Payment failed notification from Cpay', 'woocommerce')));
                }
                echo 'ok';
                exit;
            }

            /*
            $woocommerce->cart->empty_cart();
            $orderid          = ( !empty(intval($_REQUEST['CustomerReferenceNr'])) ) ? intval($_REQUEST['CustomerReferenceNr']) : 0;
            $ordstatus        = ( !empty(sanitize_text_field($_REQUEST['status'])) ) ? sanitize_text_field($_REQUEST['status']) : '';
            $ordtransactionid = ( !empty(sanitize_text_field($_REQUEST['TransactionID'])) ) ? sanitize_text_field($_REQUEST['TransactionID']) : '';
            $ordconfirmcode   = ( !empty(sanitize_text_field($_REQUEST['ConfirmCode'])) ) ? sanitize_text_field($_REQUEST['ConfirmCode']) : '';
            $notenough        = ( isset($_REQUEST['notenough']) ) ? intval($_REQUEST['notenough']) : '';

            $order    = new WC_Order($orderid);
            $data     = array(
                'mid'           => $this->merchantid,
                'TransactionID' => $ordtransactionid,
                'ConfirmCode'   => $ordconfirmcode,
            );
            $transactionData = $this->validate_order($data);
            if (200 !== $transactionData['status_code']){
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url(plugins_url('images/fail.png', __FILE__)) . '"><p style="font-size:20px;color:#5C5C5C;">'.$transactionData['message'] .'</p><a href="' . esc_url(site_url()) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                get_footer();
                exit;
            }
            if($transactionData['data']['Security'] != $ordconfirmcode){
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url(plugins_url('images/fail.png', __FILE__)) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! ConfirmCode doesn\'t match</p><a href="' . esc_url(site_url()) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                get_footer();
                exit;
            } elseif($transactionData['data']['CustomerReferenceNr'] != $orderid){
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url(plugins_url('images/fail.png', __FILE__)) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! CustomerReferenceNr doesn\'t match</p><a href="' . esc_url(site_url()) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                get_footer();
                exit;
            } elseif($transactionData['data']['TransactionID'] != $ordtransactionid){
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url(plugins_url('images/fail.png', __FILE__)) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! TransactionID doesn\'t match</p><a href="' . esc_url(site_url()) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                get_footer();
                exit;
            } elseif($transactionData['data']['Status'] != $ordstatus){
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url(plugins_url('images/fail.png', __FILE__)) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! status doesn\'t match. Your order status is '. $transactionData['data']['Status'].'</p><a href="' . esc_url(site_url()) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                get_footer();
                exit;
            }
            if (( 'paid' === $ordstatus ) && ( 0 === $notenough )) {
                // Do your magic here, and return 200 OK to ljkjcpay.
                if ('processing' === $order->status) {
                    $order->update_status('processing', sprintf(__('IPN: Payment completed notification from Cpay', 'woocommerce')));
                } else {
                    $order->payment_complete();
                    $order->update_status('processing', sprintf(__('IPN: Payment completed notification from Cpay', 'woocommerce')));
                }

                $order->save();

                $order->add_order_note( __( 'IPN: Update status event for Cpay to status COMPLETED:', 'woocommerce' ) . ' ' . $orderid);

                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#0fad00">Success!</h2><img style="margin:auto;"  src="' . esc_url(plugins_url('images/check.png', __FILE__)) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been received and confirmed successfully.</p><a href="' . esc_url(site_url()) . '" style="background-color: #0fad00;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br><br><br></div></div></div>';
                get_footer();
                exit;
            } elseif ('failed' === $ordstatus && 1 === $notenough) {
                $order->update_status('on-hold', sprintf(__('IPN: Payment failed notification from Cpay because notenough', 'woocommerce')));
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url(plugins_url('images/fail.png', __FILE__)) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p><a href="' . esc_url(site_url()) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br><br><br></div></div></div>';
                get_footer();
                exit;
            } else {
                $order->update_status('failed', sprintf(__('IPN: Payment failed notification from Cpay', 'woocommerce')));
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url(plugins_url('images/fail.png', __FILE__)) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p><a href="' . esc_url(site_url()) . '" style="background-color:#ff0000;border:none;color: white;padding:15px 32px;text-align: center;text-decoration:none;display:inline-block;font-size:16px;">Back</a><br><br><br><br></div></div></div>';
                get_footer();
                exit;
            }//end if
*/
        }//end check_ljkjcpay_response()


        /**
         * Adds error message when not configured the api key.
         *
         * @return string Error Mensage.
         */
        public function apikey_missingmessage() {
            $message  = '<div class="notice notice-info is-dismissible">';
            $message .= '<p><strong>Gateway Disabled</strong> You should enter your API key in Cpay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=ljkjcpay">Click here to configure</a></p>';
            $message .= '</div>';

            echo $message;

        }//end apikey_missingmessage()


        /**
         * Adds error message when not configured the secret.
         *
         * @return String Error Mensage.
         */
        public function secret_missingmessage() {
            $message  = '<div class="notice notice-info is-dismissible">';
            $message .= '<p><strong>Gateway Disabled</strong> You should check your MerchantID and SecurityKey in Cpay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=ljkjcpay">Click here to configure!</a></p>';
            $message .= '</div>';

            echo $message;

        }//end secret_missingmessage()

    }//end class
}//end if
