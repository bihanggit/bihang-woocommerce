<?php
/**
 * Plugin Name: oklink-woocommerce
 * Plugin URI: https://github.com/oklink/oklink-woocommerce
 * Description: Accept Bitcoin on your WooCommerce-powered website with Oklink.
 * Version: 1.0.0
 * Author: Oklink Inc.
 * Author URI: https://oklink.com
 * License: MIT
 * Text Domain: oklink-woocommerce
 */

/*  Copyright 2014 Oklink Inc.

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	function oklink_woocommerce_init() {

		if (!class_exists('WC_Payment_Gateway'))
			return;

		/**
		 * oklink Payment Gateway
		 *
		 * Provides a oklink Payment Gateway.
		 *
		 * @class       WC_Gateway_Oklink
		 * @extends     WC_Payment_Gateway
		 * @version     1.0.0
		 * @author      Oklink Inc.
		 */
		class WC_Gateway_Oklink extends WC_Payment_Gateway {
			var $notify_url;

			public function __construct() {
				$this->id   = 'oklink';
				$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/oklink.png';

				$this->has_fields        = false;
				$this->order_button_text = __('Proceed to Oklink', 'oklink-woocommerce');
				$this->notify_url        = $this->construct_notify_url();

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->get_option('title');
				$this->description = $this->get_option('description');

				// Actions
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				));
				add_action('woocommerce_receipt_oklink', array(
					$this,
					'receipt_page'
				));

				// Payment listener/API hook
				add_action('woocommerce_api_wc_gateway_oklink', array(
					$this,
					'check_oklink_callback'
				));
			}

			public function admin_options() {
				echo '<h3>' . __('Oklink Payment Gateway', 'oklink-woocommerce') . '</h3>';
				$oklink_account_email = get_option("oklink_account_email");
			
				$oklink_error_message = get_option("oklink_error_message");
				if ($oklink_account_email != false) {
					echo '<p>' . __('Successfully connected Oklink account', 'oklink-woocommerce') . " '$oklink_account_email'" . '</p>';
				} elseif ($oklink_error_message != false) {
					echo '<p>' . __('Could not validate API Key:', 'oklink-woocommerce') . " $oklink_error_message" . '</p>';
				}
				echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			}

			function process_admin_options() {
				if (!parent::process_admin_options())
					return false;

				require_once(plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . 'Oklink.php');

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				// Validate merchant API key
				try {
					$client = OKlink::withApiKey($api_key, $api_secret);
					$result = $client->userInfoUser();
					update_option("oklink_account_email", $result->user->email);
					update_option("oklink_error_message", false);
				}
				catch (Exception $e) {
					$error_message = $e->getMessage();
					update_option("oklink_account_email", false);
					update_option("oklink_error_message", $error_message);
					return;
				}
			}

			function construct_notify_url() {
				$callback_secret = get_option("oklink_callback_secret");
				if ($callback_secret == false) {
					$callback_secret = sha1(openssl_random_pseudo_bytes(20));
					update_option("oklink_callback_secret", $callback_secret);
				}
				$notify_url = WC()->api_request_url('WC_Gateway_oklink');
				$notify_url = add_query_arg('callback_secret', $callback_secret, $notify_url);
				return $notify_url;
			}

			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable Oklink plugin', 'oklink-woocommerce'),
						'type' => 'checkbox',
						'label' => __('Show bitcoin as an option to customers during checkout?', 'oklink-woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'woocommerce'),
						'type' => 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
						'default' => __('Bitcoin', 'oklink-woocommerce')
					),
					'description' => array(
						'title'       => __( 'Description', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
						'default'     => __('Pay with bitcoin, a virtual currency.', 'oklink-woocommerce')
											. " <a href='http://bitcoin.org/' target='_blank'>"
											. __('What is bitcoin?', 'oklink-woocommerce')
											. "</a>"
	             	),
					'apiKey' => array(
						'title' => __('API Key', 'oklink-woocommerce'),
						'type' => 'text',
						'description' => __('')
					),
					'apiSecret' => array(
						'title' => __('API Secret', 'oklink-woocommerce'),
						'type' => 'password',
						'description' => __('')
					)
				);
			}

			function process_payment($order_id) {

				require_once(plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . 'Oklink.php');
				global $woocommerce;

				$order = new WC_Order($order_id);

				$success_url = add_query_arg('return_from_oklink', true, $this->get_return_url($order));

				if( get_woocommerce_currency()=='USD' || get_woocommerce_currency()=='CNY'  ){
					$params = array(
						'name'               => 'Order #' . $order_id,
						'price'              => $order->get_total(),
						'price_currency'     => get_woocommerce_currency(),
						'custom'             => $order_id,
						'callback_url'       => $this->notify_url,
						'success_url'        => $success_url,
					);

					$api_key    = $this->get_option('apiKey');
					$api_secret = $this->get_option('apiSecret');

					if ($api_key == '' || $api_secret == '') {
						$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)', 'coinbase-woocommerce'));
						return;
					}

					try {
						$client   = Oklink::withApiKey($api_key, $api_secret);
						$result   = $client->buttonsButton($params);
					}
					catch (Exception $e) {
						$order->add_order_note(__('Error while processing oklink payment:', 'oklink-woocommerce') . ' ' . var_export($e, TRUE));
						$woocommerce->add_error(__($e->getMessage(), 'oklink-woocommerce'));				
						// $woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'coinbase-woocommerce'));
						return;
					}

					return array(
						'result'   => 'success',
						'redirect' =>  OklinkBase::WEB_BASE."merchant/mPayOrderStemp1.do?buttonid=".$result->button->id,
					);			
				}else{
					$woocommerce->add_error(__('only support USD and CNY', 'oklink-woocommerce'));
					return;
				}


			}

			function check_oklink_callback() {
				require_once(plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . 'Oklink.php');
				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');
				$client   = Oklink::withApiKey($api_key, $api_secret);

				if ($client->checkCallback()) {
					$post_body = json_decode(file_get_contents("php://input"));
					if (isset($post_body)) {
						$oklink_order = $post_body;
						$order_id     = $oklink_order->custom;
						$order        = new WC_Order($order_id);
					} else {
						header("HTTP/1.1 400 Bad Request");
						exit("Unrecognized Oklink Callback");
					}
				} else {
					header("HTTP/1.1 401 Not Authorized");
					exit("Spoofed callback");
				}

				header('HTTP/1.1 200 OK');
				update_post_meta($order->id, __('Oklink Order ID', 'oklink-woocommerce'), wc_clean($oklink_order->id));
				// if (isset($oklink_order->customer) && isset($oklink_order->customer->email)) {
				// 	update_post_meta($order->id, __('Oklink Account of Payer', 'oklink-woocommerce'), wc_clean($oklink_order->customer->email));
				// }

				switch (strtolower($oklink_order->status)) {

					case 'completed':
						// Check order not already completed
						if ($order->status == 'completed') {
							exit;
						}

						$order->add_order_note(__('Oklink payment completed', 'oklink-woocommerce'));
						$order->payment_complete();

						break;
					case 'canceled':

						$order->update_status('failed', __('Oklink reports payment cancelled.', 'oklink-woocommerce'));
						break;

				}

				exit;
			}
		}

		/**
		 * Add this Gateway to WooCommerce
		 **/
		function woocommerce_add_oklink_gateway($methods) {
			$methods[] = 'WC_Gateway_oklink';
			return $methods;
		}

		function woocommerce_handle_oklink_return() {
			if (!isset($_GET['return_from_oklink']))
				return;

			if (isset($_GET['cancelled'])) {
				$order = new WC_Order($_GET['order']['custom']);
				if ($order->status != 'completed') {
					$order->update_status('failed', __('Customer cancelled oklink payment', 'oklink-woocommerce'));
				}
			}

			// Oklink order param interferes with woocommerce
			unset($_GET['order']);
			unset($_REQUEST['order']);
			if (isset($_GET['order_key'])) {
				$_GET['order'] = $_GET['order_key'];
			}
		}

		add_action('init', 'woocommerce_handle_oklink_return');
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_oklink_gateway');
	}

	add_action('plugins_loaded', 'oklink_woocommerce_init', 0);
}
