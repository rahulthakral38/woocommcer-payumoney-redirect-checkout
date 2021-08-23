<?php
/*
 * Plugin Name: WooCommerce Payumoney Redirect Payment Gateway.
 * Plugin URI: #
 * Description: Take Debit/credit card, Netbanking & UPI payments on payu hosted payment form.
 * Author: Rahul Thakral
 * Author URI: http://samaarambh.com
 * Version: 1.0.0
 */
 
 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */



/**
 * Check if WooCommerce is active
 **/
if (!in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return "Woocommcer is compulsary for this plugin, please install/activate woocommcer first.";
}


add_filter( 'woocommerce_payment_gateways', 'payumoney_redirect_class' );
function payumoney_redirect_class( $gateways ) {
	$gateways[] = 'WC_Payumoney_Redirect'; // your class name is here
	return $gateways;
}

add_action( 'plugins_loaded', 'wc_init_payumoney_redirect' );


function wc_init_payumoney_redirect()
{
	class WC_Payumoney_Redirect extends WC_Payment_Gateway {		
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
		 
 		public function __construct() {

		$this->id = 'payumoney_redirect'; // payment gateway plugin ID
		/* $my_plugin = WP_PLUGIN_DIR . '/woocommerce-payumoney-redirect/'; */
		
		
		$my_plugin = plugin_dir_url( __FILE__ );
		$this->icon = $my_plugin.'/assets/images/payu-icon.png'; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields = false; // in case you need a custom credit card form
		$this->method_title = 'Paymoney Hosted - Redirect Checkout';
		$this->method_description = 'Paymoney Hosted Checkout will redirect user to payu hosted payment page for credit/debit card and netbanking payments'; // will be displayed on the options page

		// gateways can support subscriptions, refunds, saved payment methods,
		// but in this tutorial we begin with simple payments
		$this->supports = array(
			'products'
		);

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled = $this->get_option( 'enabled' );
		$this->testmode = 'yes' === $this->get_option( 'testmode' );
		
		if($this->get_option( 'testmode' ) == 'yes'){
			$this->PAYU_BASE_URL = $this->get_option( 'sandbox_url' );		// For Sandbox Mode
			$this->private_key = $this->get_option( 'test_private_key' );
			$this->publishable_key = $this->get_option( 'test_publishable_key' );
		}else{
			$this->PAYU_BASE_URL = $this->get_option( 'live_url' );			// For Production Mode
			$this->private_key = $this->get_option( 'private_key' );
			$this->publishable_key = $this->get_option( 'publishable_key' );
		}
		
			$this->txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
		
			// This action hook saves the settings
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) ); 		
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'pay_for_order') );	
		}		
		
		
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){

			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Paymoney Redirect Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Paymoney',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Credit Card',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via our super-cool payment gateway.',
				),
				'testmode' => array(
					'title'       => 'Sandbox',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'publishable_key' => array(
					'title'       => 'Marchant Key',
					'type'        => 'text'
				),
				
				'private_key' => array(
					'title'       => 'Marchant Salt',
					'type'        => 'text'
				),
				'sandbox_url' => array(
					'title'       => 'Sandbox URL',
					'type'        => 'text',
					'value'		  => "https://sandboxsecure.payu.in"
				),
				'live_url' => array(
					'title'       => 'Live URL',
					'type'        => 'text',
					'value'		  => "https://secure.payu.in"
				)
				,'error_message' => array(
					'title'       => 'Failed/Cancelled Payment Error Message',
					'type'        => 'text',
					'value'		  => "Payment Failed, Please try again to receive your order."
				)
				
				
			);
	
	 	}
		
		
		
		public function process_payment( $order_id ) {
			global $woocommerce;
			
			$order = wc_get_order( $order_id );
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);

	 	}
		
		public function pay_for_order( $order_id ) {
			$order = new WC_Order( $order_id );
			if(isset($_GET['resp'])){
				$display ="display:block";
				if($_GET['resp'] == 'payment_failed'){
					$error_msg = $this->get_option( 'error_message' );
					if($error_msg !=""){
						echo "<p class='payment_error'>".$error_msg."</p>";
					}else{
						echo "<p class='payment_error'>Payment Failed, Please try again.</p>";
					}
					
				}
			}else{
				$display ="display:none";
				echo '<p>' . __( 'Redirecting to payment provider.', 'txtdomain' ) . '</p>';
				// add a note to show order has been placed and the user redirected
				$order->add_order_note( __( 'Order placed and user redirected.', 'txtdomain' ) );
			}
			
			
			
			if(!isset($_GET['resp'])){
				// perform a click action on the submit button of the form you are going to return
				wc_enqueue_js( 'jQuery( "body #submit-form" ).click();' );
			}
			
						
			$hash = '';
			$hashSequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
			
			$hashVarsSeq = explode('|', $hashSequence);
			$hash_string = '';	

			$order_data = $order->get_data(); 
			
			$prod = array();
			foreach($order->get_items() as $item) {
				array_push($prod, $item['name']);
			}
			
			$product_names = implode("|", $prod);
			

			$args = array(
				"key"=>$this->publishable_key,
				"txnid"=>substr(hash('sha256', mt_rand() . microtime()), 0, 20),
				"amount"=>$order_data['total'],
				"productinfo"=>$product_names ,
				"firstname"=>$order_data['billing']['first_name'],
				"email"=>$order_data['billing']['email']
			);
			
			foreach($hashVarsSeq as $hash_var) {
			  $hash_string .= isset($args[$hash_var]) ? $args[$hash_var] : '';
			  $hash_string .= '|';
			}
			$hash_string .= $this->private_key;
			$hash = strtolower(hash('sha512', $hash_string));
			$action = $this->PAYU_BASE_URL . '/_payment';
			$order_id = $order->get_id();
			$order_key = $order->get_order_key();
			$success = get_home_url().'/checkout/order-received/'.$order_id.'/?key='.$order_key.'&resp=success'; 
			$failure = get_home_url().'/checkout/order-pay/'.$order_id.'/?key='.$order_key.'&resp=payment_failed';
			$args['phone'] =$order_data['billing']['phone'];
			
			
			
			echo '<form action="' . $action . '" method="post" target="_top">
				<input type="hidden" name="key" value="'.$args['key'].'">
				<input type="hidden" name="service_provider" value="payu_paisa" />
				<input type="hidden" name="txnid" value="'.$args['txnid'].'">
				<input type="hidden" name="amount" value="'.$args['amount'].'">
				<input type="hidden" name="productinfo" value="'.$args['productinfo'].'">
				<input type="hidden" name="firstname" value="'.$args['firstname'].'">
				<input type="hidden" name="email" value="'.$args['email'].'">
				<input type="hidden" name="phone" value="'.$args['phone'].'">
				<input type="hidden" name="hash" value="'.$hash.'">
				<input type="hidden" name="surl" value="'.$success.'">
				<input type="hidden" name="furl" value="'.$failure.'">
				<div class="btn-submit-payment c-product-list-widget__buttons" style="'.$display.'">
					<button type="submit" class="button" id="submit-form">Pay Now</button>
				</div>
			</form>
			<style>button#submit-form {background: white;width: 180px;}p.payment_error {background: #db2e2eeb;color: white;padding: 10px;border-radius: 5px;}</style>';
		}		
	}
}
add_action("init", "checkforPayment");
function checkforPayment(){
	if(isset($_GET)){
		if(isset($_GET['resp']) && isset($_GET['key'])){
			if(isset($_POST)){
				$order_id = wc_get_order_id_by_order_key($_GET['key']);
				if(!$order_id){
					return;
				}
				$order = new WC_Order($order_id);
				$failure = get_home_url().'/checkout/order-pay/'.$order_id.'/?key='.$_GET['key'].'&resp=payment_failed';
				
				if($_SERVER['HTTP_ORIGIN'] == "https://www.payumoney.com"){	
					if($_POST['status'] == "failure"){
						wp_redirect($failure);
						exit;
					}else if($_POST['status'] == "success"){					
						$res = $_GET['resp'];					
						if( $res == 'success'){
							$order->update_status('processing');
							$transaction_id = $_POST['txnid'];
							update_post_meta( $order_id, '_transaction_id', $transaction_id, true );
							update_post_meta( $order_id, '_transaction_data', $_POST, true );
						}
					}
				}else{
					wp_redirect($failure);
					exit;
				}
			}
		}
	}
}
