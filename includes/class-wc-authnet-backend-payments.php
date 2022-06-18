<?php
/**
 * Main class file
 *
 * Orders must have a status of Pending Payment or On Hold before the payment form will appear in the sidebar.
 *
 * @package Authnet Backend Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Cardpay_Authnet' ) ) {
	return;
}

/**
 * Main plugin class file
 */
class WC_Authnet_Backend_Payments {

	/**
	 * The CardPay Solutions payment gateway slug
	 *
	 * @var string Payment gateway slug, used to get payment token for existing customers.
	 */
	private $id = 'authnet';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( &$this, 'add_order_meta_box' ), -1 );
		add_action( 'init', array( &$this, 'maybe_process_authnet_payment' ), 10 );
	}

	/**
	 * Add metabox to the WC orders that appears when editing individual orders
	 *
	 * The metabox only appears if the order status is "Pending payment" or "On hold".
	 *
	 * @return void
	 */
	public function add_order_meta_box() {
		global $post, $typenow;

		if ( 'shop_order' !== $typenow ) {
			return;
		}

		$order = wc_get_order( $post->ID );

		if ( empty( $order ) ) {
			return;
		}

		if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			return;
		}

		add_meta_box( 'woocommerce-authnet-payments', __( 'Authorize.net Payment', 'woocommerce' ), array( &$this, 'payment_meta_box' ), 'shop_order', 'side', 'default' );
	}

	/**
	 * Draws the meta box HTML and enqueues required payment scripts
	 *
	 * @return void
	 */
	public function payment_meta_box() {
		global $post;

		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( empty( $gateways['authnet'] ) ) {
			return;
		}

		$order = wc_get_order( $post->ID );

		wp_enqueue_script( 'jquery-payment', plugins_url( '/assets/js/jquery-payment/jquery.payment.min.js', WC_PLUGIN_FILE ), array(), WC_VERSION, true );
		wp_enqueue_script( 'credit-card-form', plugins_url( '/assets/js/frontend/credit-card-form.min.js', WC_PLUGIN_FILE ), array(), WC_VERSION, true );
		wp_enqueue_script( 'wc-authnet-backend-script', plugin_dir_url( WC_AUTHNET_PLUGIN_FILE ) . 'assets/js/wc-authnet-backend-script.js', array( 'jquery' ), '1.0', true );

		?>
		<?php echo wp_nonce_field( 'authnet_payment_nonce', '_authnet_nonce' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="authnet_admin_payment" value="1">
		<input type="hidden" name="authnet_customer_id" value="<?php echo esc_html( $order->get_customer_id() ); ?>">
		<input type="hidden" name="authnet_order_total" value="<?php echo esc_html( $order->get_total() ); ?>">
		<?php $this->payment_fields( $gateways['authnet'], $order ); ?>
		<input type="submit" name="authnet_submit_btn" value="Process payment" class="button-primary">
		<?php
	}

	/**
	 * Injects the payment form fields into the meta box HTML
	 *
	 * @param object $gateway WC Authnet Payment gateway object.
	 * @param object $order WC_Order.
	 * @return void
	 */
	public function payment_fields( $gateway, $order ) {
		if ( $gateway->description ) {
			echo apply_filters( 'wc_cardpay_authnet_description', wpautop( wp_kses_post( $gateway->description ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( $gateway->supports( 'tokenization' ) && 'yes' === $gateway->cim_enabled ) {

			$gateway->tokenization_script();

			// Get saved payment methods.
			$tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_customer_id(), $this->id );

			$html = '<ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods" data-count="' . esc_attr( count( $tokens ) ) . '">';

			foreach ( $tokens as $token ) {
				$html .= $this->get_saved_payment_method_option_html( $token );
			}

			if ( ! empty( $tokens ) ) {
				$set_as_checked = false;
			} else {
				$set_as_checked = true;
			}

			$html .= $this->get_new_payment_method_option_html( $set_as_checked );
			$html .= '</ul>';

			echo $html; // @codingStandardsIgnoreLine 

			?>
			<div id="wc-authnet-payment-form-wrapper">
			<?php
				$gateway->form();
				$gateway->save_payment_method_checkbox();
			?>
			</div>
			<?php
		} else {
			$gateway->form();
		}
	}

	/**
	 * Draws the saved payment token HTML for a given token
	 *
	 * This method is based on the WC core code.
	 *
	 * @param object $token WC payment token object.
	 * @return string $html HTML output
	 */
	public function get_saved_payment_method_option_html( $token ) {
		$html = sprintf(
			'<li class="woocommerce-SavedPaymentMethods-token">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token"value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput wc-authnet-token" %4$s />
				<label for="wc-%1$s-payment-token-%2$s">%3$s</label>
			</li>',
			esc_attr( $this->id ),
			esc_attr( $token->get_id() ),
			esc_html( $token->get_display_name() ),
			checked( $token->is_default(), true, false )
		);

		return $html;
	}

	/**
	 * Draws the new payment method option radio button HTML
	 *
	 * This method is based on the WC core code.
	 *
	 * @param bool $set_as_checked true|false Determines whether to set the checkbox HTML as checked by default.
	 *
	 * @return string $html HTML output
	 */
	public function get_new_payment_method_option_html( $set_as_checked ) {

		if ( $set_as_checked ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}

		$label = apply_filters( 'woocommerce_payment_gateway_get_new_payment_method_option_html_label', $this->new_method_label ? $this->new_method_label : __( 'Use a new payment method', 'woocommerce' ), $this );

		$html = sprintf(
			'<li class="woocommerce-SavedPaymentMethods-new">
				<input id="wc-%1$s-payment-token-new" type="radio" name="wc-%1$s-payment-token" value="new" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput wc-authnet-token" %3$s />
				<label for="wc-%1$s-payment-token-new">%2$s</label>
			</li>',
			esc_attr( $this->id ),
			esc_html( $label ),
			$checked
		);

		return $html;
	}


	/**
	 * Checks to determine if a payment needs to be processed due to the payment form being submitted
	 *
	 * @return void
	 */
	public function maybe_process_authnet_payment() {

		if ( empty( $_POST['authnet_submit_btn'] ) ) {
			return;
		}

		$nonce = isset( $_POST['_authnet_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_authnet_nonce'] ) ) : false;

		if ( empty( $nonce ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'authnet_payment_nonce' ) ) {
			return;
		}

		$this->authnet_process_payment();
	}

	/**
	 * Processing the payment via the CardPay Solutions Authorize.net payment gateway
	 *
	 * This method is based on the code in the CardPay Solutions plugin.
	 *
	 * @return void
	 */
	public function authnet_process_payment() {
		try {
			global $woocommerce;

			// Nonce check has already been completed at this point.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order_id = isset( $_POST['post_ID'] ) ? absint( wp_unslash( $_POST['post_ID'] ) ) : false;

			if ( empty( $order_id ) ) {
				return;
			}

			$order  = wc_get_order( $order_id );
			$amount = $order->get_total();
			$card   = '';

			$gateways = WC()->payment_gateways->get_available_payment_gateways();

			// Get any previous Auth.net transaction ID so it can be voided before processing a new payment.
			$tran_meta      = $order->get_meta( '_authnet_transaction', true );
			$transaction_id = isset( $tran_meta['transaction_id'] ) ? $tran_meta['transaction_id'] : false;

			if ( ! empty( $transaction_id ) ) {
				$authnet = new WC_Cardpay_Authnet_API();

				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$previous_amount = isset( $_POST['authnet_order_total'] ) ? sanitize_text_field( wp_unslash( $_POST['authnet_order_total'] ) ) : false;

				$response = $authnet->void( $gateways['authnet'], $order, floatval( $previous_amount ) );

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( isset( $response->transactionResponse->responseCode ) && '1' === $response->transactionResponse->responseCode ) {
					// translators: %s is the transaction ID.
					$order->add_order_note( sprintf( __( 'Previous transaction ID %s voided.', 'woocommerce-cardpay-authnet' ), $transaction_id ) );
				} else {
					// translators: the first %1$s is the transaction ID, the second %2$s is the error code.
					$order->add_order_note( sprintf( __( 'Error voiding previous transaction ID %1$s. Response code: %2$s', 'woocommerce-cardpay-authnet' ), $transaction_id, $response->transactionResponse->responseCode ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				}
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$token_id = isset( $_POST['wc-authnet-payment-token'] ) ? sanitize_text_field( wp_unslash( $_POST['wc-authnet-payment-token'] ) ) : false;

			if ( isset( $token_id ) && 'new' !== $token_id ) {
				$card = WC_Payment_Tokens::get( $token_id );
				// Return if card does not belong to order customer user.
				if ( $card->get_user_id() !== $order->get_customer_id() ) {
					$order->add_order_note( __( 'ERROR: The selected card does not belong to this user!', 'woocommerce-cardpay-authnet' ) );
					return;
				}
			}

			$authnet = new WC_Cardpay_Authnet_API();

			if ( 'authorize' === $gateways['authnet']->transaction_type ) {
				$response = $authnet->authorize( $gateways['authnet'], $order, $amount, $card );
			} else {
				$response = $authnet->purchase( $gateways['authnet'], $order, $amount, $card );
			}

			if ( is_wp_error( $response ) ) {
				$order->add_order_note( $response->get_error_message() );
				return;
			}

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( isset( $response->transactionResponse->responseCode ) && '1' === $response->transactionResponse->responseCode ) {
				$trans_id = $response->transactionResponse->transId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$order->payment_complete( $trans_id );

				// Reset the $_POST['order_status'] variable so that WooCommerce saves it correctly,
				// note that we cannot always use $order->update_status( 'processing' ) because WC processes
				// the $_POST data AFTER we run this payment process. Thus we must override the $_POST variable.
				$_POST['order_status'] = 'wc-processing';

				if ( ! empty( $card ) ) {
					$exp_date = $card->get_expiry_month() . substr( $card->get_expiry_year(), -2 );
				} else {
					$exp_date_array = explode( '/', $_POST['authnet-card-expiry'] ); // phpcs:ignore
					$exp_month      = trim( $exp_date_array[0] );
					$exp_year       = trim( $exp_date_array[1] );
					$exp_date       = $exp_month . substr( $exp_year, -2 );
				}

				$amount_approved = number_format( $amount, '2', '.', '' );
				$message         = 'authorize' === $gateways['authnet']->transaction_type ? 'authorized' : 'completed';

				$order->add_order_note(
					sprintf(
						// translators: %1\$s is transaction type, %2\$s is amount, %3\$s is transaction ID, %4\$s is address verification response, %5\$s is card security code verification response.
						__( "Authorize.Net payment %1\$s for %2\$s. Transaction ID: %3\$s.\n\n <strong>AVS Response:</strong> %4\$s.\n\n <strong>CVV2 Response:</strong> %5\$s.", 'woocommerce-cardpay-authnet' ),
						$message,
						$amount_approved,
						$response->transactionResponse->transId, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$gateways['authnet']->get_avs_message( $response->transactionResponse->avsResultCode ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$gateways['authnet']->get_cvv_message( $response->transactionResponse->cvvResultCode ) // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					)
				);

				$tran_meta = array(
					'transaction_id'   => $response->transactionResponse->transId, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'cc_last4'         => substr( $response->transactionResponse->accountNumber, -4 ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'cc_expiry'        => $exp_date,
					'transaction_type' => $gateways['authnet']->transaction_type,
				);

				$order->update_meta_data( '_authnet_transaction', $tran_meta );
				$order->update_status( 'processing' );
				$order->save();

				// Save the card if possible.
				if ( isset( $_POST['wc-authnet-new-payment-method'] ) && 'yes' === $gateways['authnet']->cim_enabled ) { // phpcs:ignore
					$this->authnet_save_card( $response, $exp_date );
				}

				return true;
			} else {
				$order->add_order_note( __( 'Payment error: Please check your credit card details and try again.', 'woocommerce-cardpay-authnet' ) );
				return false;
			}
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Saves a payment card to the customer's profile if CIM is enabled in the Authorize.net account
	 *
	 * This method is based on the code in the CardPay Solutions plugin.
	 *
	 * @param object $response Authorize.net payment response.
	 * @param string $exp_date Card expiration date.
	 * @return void
	 */
	public function authnet_save_card( $response, $exp_date ) {

		// At this point the nonce has already been checked.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$customer_id = isset( $_POST['authnet_customer_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['authnet_customer_id'] ) ) ) : false;

		if ( empty( $customer_id ) ) {
			return;
		}

		if ( isset( $response->profileResponse->customerProfileId ) && ! empty( $response->profileResponse->customerProfileId ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$token = new WC_Payment_Token_CC();
			$token->set_token( $response->profileResponse->customerProfileId . '|' . $response->profileResponse->customerPaymentProfileIdList[0] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$token->set_gateway_id( $this->id );
			$token->set_card_type( $response->transactionResponse->accountType ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$token->set_last4( substr( $response->transactionResponse->accountNumber, -4 ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$token->set_expiry_month( substr( $exp_date, 0, 2 ) );
			$token->set_expiry_year( '20' . substr( $exp_date, -2 ) );
			$token->set_user_id( $customer_id );
			$token->save();
		}
	}
}

new WC_Authnet_Backend_Payments();
