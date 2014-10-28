<?php
/**
 * @copyright Incsub (http://incsub.com/)
 *
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,
 * MA 02110-1301 USA
 *
*/

/**
 * Paypal Single Gateway.
 *
 * Process single paypal purchases/payments.
 *
 * Persisted by parent class MS_Model_Option. Singleton.
 *
 * @since 1.0.0
 * @package Membership
 * @subpackage Model
 */
class MS_Model_Gateway_Paypal_Single extends MS_Model_Gateway {

	/**
	 * Gateway transaction status constants.
	 *
	 * @since 1.0.0
	 * @var string $status
	 */
	const STATUS_FAILED = 'failed';
	const STATUS_REVERSED = 'reversed';
	const STATUS_REFUNDED = 'refunded';
	const STATUS_PENDING = 'pending';
	const STATUS_DISPUTE = 'dispute';
	const STATUS_DENIED = 'denied';

	/**
	 * Gateway singleton instance.
	 *
	 * @since 1.0.0
	 * @var string $instance
	 */
	public static $instance;

	/**
	 * Gateway ID.
	 *
	 * @since 1.0.0
	 * @var int $id
	 */
	protected $id = self::GATEWAY_PAYPAL_SINGLE;

	/**
	 * Gateway name.
	 *
	 * @since 1.0.0
	 * @var string $name
	 */
	protected $name = '';

	/**
	 * Gateway description.
	 *
	 * @since 1.0.0
	 * @var string $description
	 */
	protected $description = '';

	/**
	 * Gateway allow Pro rating.
	 *
	 * @todo To be released in further versions.
	 * @since 1.0.0
	 * @var bool $pro_rate
	 */
	protected $pro_rate = true;

	/**
	 * Gateway operation mode.
	 *
	 * Live or sandbox (test) mode.
	 *
	 * @since 1.0.0
	 * @var string $mode
	 */
	protected $mode;

	/**
	 * Manual payment indicator.
	 *
	 * If the gateway does not allow automatic reccuring billing.
	 *
	 * @since 1.0.0
	 * @var bool $manual_payment
	 */
	protected $manual_payment = true;

	/**
	 * Paypal merchant/seller's email.
	 *
	 * @since 1.0.0
	 * @var bool $paypal_email
	 */
	protected $paypal_email;

	/**
	 * Paypal country site.
	 *
	 * @since 1.0.0
	 * @var bool $paypal_site
	 */
	protected $paypal_site;


	/**
	 * Hook to add custom transaction status.
	 * This is called by the MS_Factory
	 *
	 * @since 1.0.0
	 */
	public function after_load() {
		parent::after_load();

		$this->name = __( 'PayPal Single Gateway', MS_TEXT_DOMAIN );

		if ( $this->active ) {
			$this->add_filter( 'ms_model_invoice_get_status', 'gateway_custom_status' );
		}
	}

	/**
	 * Add Gateway custom status.
	 *
	 * * Hooks Actions: *
	 * * ms_model_invoice_get_status
	 *
	 * @since 1.0.0
	 * @return array {
	 *     Array of ( $status_id => $status_name ).
	 *
	 *     @type string $status_id The status id.
	 *     @type string $status_name The status name.
	 * }
	 */
	public function gateway_custom_status( $status ) {
		$paypal_status = array(
			self::STATUS_FAILED => __( 'Failed', MS_TEXT_DOMAIN ),
			self::STATUS_REVERSED => __( 'Reversed', MS_TEXT_DOMAIN ),
			self::STATUS_REFUNDED => __( 'Refunded', MS_TEXT_DOMAIN ),
			self::STATUS_PENDING => __( 'Pending', MS_TEXT_DOMAIN ),
			self::STATUS_DISPUTE => __( 'Dispute', MS_TEXT_DOMAIN ),
			self::STATUS_DENIED => __( 'Denied', MS_TEXT_DOMAIN ),
		);

		return apply_filters(
			'ms_model_gateway_paypal_single_gateway_custom_status',
			array_merge( $status, $paypal_status )
		);
	}

	/**
	 * Processes gateway IPN return.
	 *
	 * @since 1.0.0
	 */
	public function handle_return() {
		do_action(
			'ms_model_gateway_paypal_single_handle_return_before',
			$this
		);

		if ( ( isset($_POST['payment_status'] ) || isset( $_POST['txn_type'] ) )
			&& ! empty( $_POST['custom'] )
		) {
			if ( $this->is_live_mode() ) {
				$domain = 'https://www.paypal.com';
			}
			else {
				$domain = 'https://www.sandbox.paypal.com';
			}

			// Paypal post authenticity verification
			$ipn_data = (array) stripslashes_deep( $_POST );
			$ipn_data['cmd'] = '_notify-validate';
			$response = wp_remote_post(
				$domain . '/cgi-bin/webscr',
				array(
					'timeout' => 60,
					'sslverify' => false,
					'httpversion' => '1.1',
					'body' => $ipn_data,
				)
			);

			if ( ! is_wp_error( $response )
				&& 200 == $response['response']['code']
				&& ! empty( $response['body'] )
				&& 'VERIFIED' == $response['body']
			) {
				MS_Helper_Debug::log( 'PayPal Transaction Verified' );
			}
			else {
				$error = 'Response Error: Unexpected transaction response';
				MS_Helper_Debug::log( $error );
				MS_Helper_Debug::log( $response );
				echo $error;
				exit;
			}

			$new_status = false;
			$invoice = MS_Factory::load( 'MS_Model_Invoice', $_POST['custom'] );
			$ms_relationship = MS_Factory::load(
				'MS_Model_Membership_Relationship',
				$invoice->ms_relationship_id
			);
			$membership = $ms_relationship->get_membership();
			$member = MS_Factory::load( 'MS_Model_Member', $ms_relationship->user_id );

			$external_id = $_POST['txn_id'];
			$amount = $_POST['mc_gross'];
			$currency = $_POST['mc_currency'];
			$status = null;
			$notes = null;

			// Process PayPal response
			switch ( $_POST['payment_status'] ) {
				// Successful payment
				case 'Completed':
				case 'Processed':
					if ( $amount == $invoice->total ) {
						$status = MS_Model_Invoice::STATUS_PAID;
					}
					else {
						$notes = __( 'Payment amount differs from invoice total.', MS_TEXT_DOMAIN );
						$status = self::STATUS_DENIED;
					}
					break;

				case 'Reversed':
					$notes = __( 'Last transaction has been reversed. Reason: Payment has been reversed (charge back). ', MS_TEXT_DOMAIN );
					$status = self::STATUS_REVERSED;
					break;

				case 'Refunded':
					$notes = __( 'Last transaction has been reversed. Reason: Payment has been refunded', MS_TEXT_DOMAIN );
					$status = self::STATUS_REFUNDED;
					break;

				case 'Denied':
					$notes = __( 'Last transaction has been reversed. Reason: Payment Denied', MS_TEXT_DOMAIN );
					$status = self::STATUS_DENIED;
					break;

				case 'Pending':
					$pending_str = array(
						'address' => __( 'Customer did not include a confirmed shipping address', MS_TEXT_DOMAIN ),
						'authorization' => __( 'Funds not captured yet', MS_TEXT_DOMAIN ),
						'echeck' => __( 'eCheck that has not cleared yet', MS_TEXT_DOMAIN ),
						'intl' => __( 'Payment waiting for aproval by service provider', MS_TEXT_DOMAIN ),
						'multi-currency' => __( 'Payment waiting for service provider to handle multi-currency process', MS_TEXT_DOMAIN ),
						'unilateral' => __( 'Customer did not register or confirm his/her email yet', MS_TEXT_DOMAIN ),
						'upgrade' => __( 'Waiting for service provider to upgrade the PayPal account', MS_TEXT_DOMAIN ),
						'verify' => __( 'Waiting for service provider to verify his/her PayPal account', MS_TEXT_DOMAIN ),
						'*' => '',
					);

					$reason = $_POST['pending_reason'];
					$notes = __( 'Last transaction is pending. Reason: ', MS_TEXT_DOMAIN ) .
						( isset($pending_str[$reason] ) ? $pending_str[$reason] : $pending_str['*'] );
					$status = self::STATUS_PENDING;
					break;

				default:
				case 'Partially-Refunded':
				case 'In-Progress':
					break;
			}

			if ( 'new_case' == $_POST['txn_type']
				&& 'dispute' == $_POST['case_type']
			) {
				$status = self::STATUS_DISPUTE;
			}

			if ( empty( $invoice ) ) {
				$invoice = MS_Model_Invoice::get_current_invoice( $ms_relationship );
			}
			$invoice->external_id = $external_id;

			if ( ! empty( $notes ) ) {
				$invoice->add_notes( $notes );
			}
			$invoice->gateway_id = $this->id;
			$invoice->save();

			if ( ! empty( $status ) ) {
				$invoice->status = $status;
				$invoice->save();
				$this->process_transaction( $invoice );
			}

			do_action(
				'ms_model_gateway_paypal_single_payment_processed_' . $status,
				$invoice,
				$ms_relationship
			);
		}
		else {
			// Did not find expected POST variables. Possible access attempt from a non PayPal site.
			header( 'Status: 404 Not Found' );
			$notes = __( 'Error: Missing POST variables. Identification is not possible.', MS_TEXT_DOMAIN );
			MS_Helper_Debug::log( $notes );
			exit;
		}

		do_action(
			'ms_model_gateway_paypal_single_handle_return_after',
			$this
		);
	}

	/**
	 * Get paypal country sites list.
	 *
	 * @see MS_Model_Gateway::get_country_codes()
	 * @since 1.0.0
	 * @return array
	 */
	public function get_paypal_sites() {
		return apply_filters(
			'ms_model_gateway_paylpay_single_get_paypal_sites',
			self::get_country_codes()
		);
	}

	/**
	 * Process transaction.
	 *
	 * Process transaction status change related to this membership relationship.
	 * Change status accordinly to transaction status.
	 *
	 * @since 1.0.0
	 *
	 * @param MS_Model_Invoice $invoice The Transaction.
	 * @return MS_Model_Invoice The processed invoice.
	 */
	public function process_transaction( $invoice ) {
		do_action(
			'ms_model_gateway_paypal_single_process_transaction_before',
			$this
		);

		$ms_relationship = MS_Factory::load(
			'MS_Model_Membership_Relationship',
			$invoice->ms_relationship_id
		);
		$member = MS_Factory::load( 'MS_Model_Member', $invoice->user_id );

		switch ( $invoice->status ) {
			case self::STATUS_REVERSED:
			case self::STATUS_REFUNDED:
			case self::STATUS_DENIED:
			case self::STATUS_DISPUTE:
				MS_Model_Event::save_event(
					MS_Model_Event::TYPE_PAYMENT_DENIED,
					$ms_relationship
				);
				//Disable user @todo 
// 				$member->active = false;
				break;

			default:
				$ms_relationship = parent::process_transaction( $invoice );
				break;
		}

		$member->save();
		$ms_relationship->gateway_id = $this->gateway_id;
		$ms_relationship->save();

		return apply_filters(
			'ms_model_gateway_paypal_single_processed_transaction',
			$invoice,
			$this
		);
	}

	/**
	 * Verify required fields.
	 *
	 * @since 1.0.0
	 *
	 * @return boolean
	 */
	public function is_configured() {
		$is_configured = true;
		$required = array( 'paypal_email', 'paypal_site' );

		foreach ( $required as $field ) {
			if ( empty( $this->$field ) ) {
				$is_configured = false;
				break;
			}
		}

		return apply_filters(
			'ms_model_gateway_paypal_single_is_configured',
			$is_configured
		);
	}

	/**
	 * Validate specific property before set.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param string $name The name of a property to associate.
	 * @param mixed $value The value of a property.
	 */
	public function __set( $property, $value ) {
		if ( property_exists( $this, $property ) ) {
			switch ( $property ) {
				case 'paypal_site':
					if ( array_key_exists( $value, self::get_paypal_sites() ) ) {
						$this->$property = $value;
					}
					break;

				case 'paypal_email':
					if ( is_email( $value ) ) {
						$this->$property = $value;
					}
					break;

				default:
					parent::__set( $property, $value );
					break;
			}
		}

		do_action(
			'ms_model_gateway_paypal_single__set_after',
			$property,
			$value,
			$this
		);
	}

}