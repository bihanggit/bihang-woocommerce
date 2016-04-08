<?php
/**
 * Plugin Name: bihang-woocommerce
 * Plugin URI: https://github.com/bihang/bihang-woocommerce
 * Description: Accept Bitcoin on your WooCommerce-powered website with bihang.
 * Version: 1.0.0
 * Author: bihang Inc.
 * Author URI: https://bihang.com
 * License: MIT
 * Text Domain: bihang-woocommerce
 */

/*  Copyright 2014 bihang Inc.

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

	function bihang_woocommerce_init() {

		if (!class_exists('WC_Payment_Gateway'))
			return;

		/**
		 * bihang Payment Gateway
		 *
		 * Provides a bihang Payment Gateway.
		 *
		 * @class       WC_Gateway_bihang
		 * @extends     WC_Payment_Gateway
		 * @version     1.0.0
		 * @author      bihang Inc.
		 */
		class WC_Gateway_Bihang extends WC_Payment_Gateway {
			var $notify_url;

			public function __construct() {
				$this->id   = 'bihang';
				$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/bihang.png';

				$this->has_fields        = false;
				$this->order_button_text = __('Proceed to Bihang', 'bihang-woocommerce');
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
				add_action('woocommerce_receipt_bihang', array(
					$this,
					'receipt_page'
				));

				// Payment listener/API hook
				add_action('woocommerce_api_wc_gateway_bihang', array(
					$this,
					'check_bihang_callback'
				));
			}

			public function admin_options() {
				echo '<h3>' . __('bihang Payment Gateway', 'bihang-woocommerce') . '</h3>';
				$bihang_account_email = get_option("bihang_account_email");
			
				$bihang_error_message = get_option("bihang_error_message");
				if ($bihang_account_email != false) {
					echo '<p>' . __('Successfully connected bihang account', 'bihang-woocommerce') . " '$bihang_account_email'" . '</p>';
				} elseif ($bihang_error_message != false) {
					echo '<p>' . __('Could not validate API Key:', 'bihang-woocommerce') . " $bihang_error_message" . '</p>';
				}
				echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			}

			function process_admin_options() {
				if (!parent::process_admin_options())
					return false;

				require_once(plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . 'Bihang.php');

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				// Validate merchant API key
				try {
					$client = Bihang::withApiKey($api_key, $api_secret);
					$result = $client->userInfoUser();
					update_option("bihang_account_email", $result->user->email);
					update_option("bihang_error_message", false);
				}
				catch (Exception $e) {
					$error_message = $e->getMessage();
					update_option("bihang_account_email", false);
					update_option("bihang_error_message", $error_message);
					return;
				}
			}

			function construct_notify_url() {
				$callback_secret = get_option("bihang_callback_secret");
				if ($callback_secret == false) {
					$callback_secret = sha1(openssl_random_pseudo_bytes(20));
					update_option("bihang_callback_secret", $callback_secret);
				}
				$notify_url = WC()->api_request_url('WC_Gateway_bihang');
				$notify_url = add_query_arg('callback_secret', $callback_secret, $notify_url);
				return $notify_url;
			}

			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable bihang plugin', 'bihang-woocommerce'),
						'type' => 'checkbox',
						'label' => __('Show bitcoin as an option to customers during checkout?', 'bihang-woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'woocommerce'),
						'type' => 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
						'default' => __('Bitcoin', 'bihang-woocommerce')
					),
					'description' => array(
						'title'       => __( 'Description', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
						'default'     => __('Pay with bitcoin, a virtual currency.', 'bihang-woocommerce')
											. " <a href='http://bitcoin.org/' target='_blank'>"
											. __('What is bitcoin?', 'bihang-woocommerce')
											. "</a>"
	             	),
					'apiKey' => array(
						'title' => __('API Key', 'bihang-woocommerce'),
						'type' => 'text',
						'description' => __('')
					),
					'apiSecret' => array(
						'title' => __('API Secret', 'bihang-woocommerce'),
						'type' => 'password',
						'description' => __('')
					)
				);
			}

			function process_payment($order_id) {

				require_once(plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . 'Bihang.php');
				global $woocommerce;

				$order = new WC_Order($order_id);

				$success_url = add_query_arg('return_from_bihang', true, $this->get_return_url($order));

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
						$client   = Bihang::withApiKey($api_key, $api_secret);
						$result   = $client->buttonsButton($params);
					}
					catch (Exception $e) {
						$order->add_order_note(__('Error while processing bihang payment:', 'bihang-woocommerce') . ' ' . var_export($e, TRUE));
						$woocommerce->add_error(__($e->getMessage(), 'bihang-woocommerce'));				
						// $woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'coinbase-woocommerce'));
						return;
					}

					return array(
						'result'   => 'success',
						'redirect' =>  BihangBase::WEB_BASE."merchant/mPayOrderStemp1.do?buttonid=".$result->button->id,
					);			
				}else{
					$woocommerce->add_error(__('only support USD and CNY', 'bihang-woocommerce'));
					return;
				}


			}

			function check_bihang_callback() {
				require_once(plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . 'Bihang.php');
				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');
				$client   = Bihang::withApiKey($api_key, $api_secret);

				if ($client->checkCallback()) {
					$post_body = json_decode(file_get_contents("php://input"));
					if (isset($post_body)) {
						$bihang_order = $post_body;
						$order_id     = $bihang_order->custom;
						$order        = new WC_Order($order_id);
					} else {
						header("HTTP/1.1 400 Bad Request");
						exit("Unrecognized bihang Callback");
					}
				} else {
					header("HTTP/1.1 401 Not Authorized");
					exit("Spoofed callback");
				}

				header('HTTP/1.1 200 OK');
				update_post_meta($order->id, __('Bihang Order ID', 'bihang-woocommerce'), wc_clean($bihang_order->id));
				// if (isset($bihang_order->customer) && isset($bihang_order->customer->email)) {
				// 	update_post_meta($order->id, __('bihang Account of Payer', 'bihang-woocommerce'), wc_clean($bihang_order->customer->email));
				// }

				switch (strtolower($bihang_order->status)) {

					case 'completed':
						// Check order not already completed
						if ($order->status == 'completed') {
							exit;
						}

						$order->add_order_note(__('Bihang payment completed', 'bihang-woocommerce'));
						$order->payment_complete();

						break;
					case 'canceled':

						$order->update_status('failed', __('Bihang reports payment cancelled.', 'bihang-woocommerce'));
						break;

				}

				exit;
			}
		}

		/**
		 * Add this Gateway to WooCommerce
		 **/
		function woocommerce_add_bihang_gateway($methods) {
			$methods[] = 'WC_Gateway_bihang';
			return $methods;
		}

		function woocommerce_handle_bihang_return() {
			if (!isset($_GET['return_from_bihang']))
				return;

			if (isset($_GET['cancelled'])) {
				$order = new WC_Order($_GET['order']['custom']);
				if ($order->status != 'completed') {
					$order->update_status('failed', __('Customer cancelled bihang payment', 'bihang-woocommerce'));
				}
			}

			// bihang order param interferes with woocommerce
			unset($_GET['order']);
			unset($_REQUEST['order']);
			if (isset($_GET['order_key'])) {
				$_GET['order'] = $_GET['order_key'];
			}
		}

		add_action('init', 'woocommerce_handle_bihang_return');
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_bihang_gateway');
	}

	add_action('plugins_loaded', 'bihang_woocommerce_init', 0);
}
