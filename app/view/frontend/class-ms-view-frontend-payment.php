<?php

class MS_View_Frontend_Payment extends MS_View {

	/**
	 * Returns the HTML code for the Purchase-Membership form.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function to_html() {
		$membership = $this->data['membership'];
		$invoice = $this->data['invoice'];

		$class = 'ms-alert-success';
		$msg = __(
			'Please check the details of the membership below and click ' .
			'on the relevant button to complete the signup.', MS_TEXT_DOMAIN
		);

		if ( ! empty( $this->data['error'] ) ) {
			$class = 'ms-alert-error';
			$msg = $this->data['error'];
		}

		$cancel_warning = false;
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_MULTI_MEMBERSHIPS ) ) {
			// Member can only sign up to one membership.
			$valid_status = array(
				MS_Model_Membership_Relationship::STATUS_TRIAL,
				MS_Model_Membership_Relationship::STATUS_ACTIVE,
				MS_Model_Membership_Relationship::STATUS_PENDING,
			);

			foreach ( $this->data['member']->ms_relationships as $ms_relationship ) {
				if ( in_array( $ms_relationship->status, $valid_status ) ) {
					$cancel_warning = true;
					break;
				}
			}
		}

		ob_start();
		?>
		<div class="ms-membership-form-wrapper">
			<legend><?php _e( 'Join Membership', MS_TEXT_DOMAIN ) ?></legend>
			<p class="ms-alert-box <?php echo esc_attr( $class ); ?>">
				<?php echo $msg; ?>
			</p>
			<table class="ms-purchase-table">
				<tr>
					<td class="ms-title-column">
						<?php _e( 'Name', MS_TEXT_DOMAIN ); ?>
					</td>
					<td class="ms-details-column">
						<?php echo esc_html( $membership->name ); ?>
					</td>
				</tr>

				<?php if ( $membership->description ) : ?>
					<tr>
						<td class="ms-title-column">
							<?php _e( 'Description', MS_TEXT_DOMAIN ); ?>
						</td>
						<td class="ms-desc-column" colspan="2">
							<span class="ms-membership-description"><?php
								echo $membership->description;
							?></span>
						</td>
					</tr>
				<?php endif; ?>

				<tr>
					<td class="ms-title-column">
						<?php _e( 'Price', MS_TEXT_DOMAIN ); ?>
					</td>
					<td class="ms-details-column">
						<?php
						if ( $membership->price > 0 ) {
							echo $invoice->currency . ' ' . number_format( $membership->price, 2 );
						}
						else {
							_e( 'Free', MS_TEXT_DOMAIN );
						}
						?>
					</td>
				</tr>

				<?php if ( $invoice->discount ) : ?>
					<tr>
						<td class="ms-title-column">
							<?php _e( 'Coupon discount', MS_TEXT_DOMAIN ); ?>
						</td>
						<td class="ms-price-column">
							<?php printf( '%s -%s', $invoice->currency, number_format( $invoice->discount, 2 ) ); ?>
						</td>
					</tr>
				<?php endif; ?>

				<?php if ( $invoice->pro_rate ) : ?>
					<tr>
						<td class="ms-title-column">
							<?php _e( 'Pro rate discount', MS_TEXT_DOMAIN ); ?>
						</td>
						<td class="ms-price-column">
							<?php printf( '%s -%s', $invoice->currency, number_format( $invoice->pro_rate, 2 ) ); ?>
						</td>
					</tr>
				<?php endif; ?>

				<tr>
					<td class="ms-title-column">
						<?php _e( 'Total', MS_TEXT_DOMAIN ); ?>
					</td>
					<td class="ms-price-column ms-total">
						<?php echo $invoice->currency . ' '. number_format( $invoice->total, 2 ); ?>
					</td>
				</tr>

				<?php if ( $membership->trial_period_enabled && $invoice->trial_period ) : ?>
					<tr>
						<td class="ms-title-column">
							<?php _e( 'Trial until', MS_TEXT_DOMAIN ); ?>
						</td>
						<td class="ms-desc-column"><?php
							echo $this->data['ms_relationship']->calc_trial_expire_date(
								MS_Helper_Period::current_date()
							);
						?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td class="ms-desc-column" colspan="2">
						<span class="ms-membership-description"><?php
							echo $this->data['ms_relationship']->get_payment_description();
						?></span>
					</td>
				</tr>

				<?php if ( $cancel_warning ) : ?>
					<tr>
						<td class="ms-desc-warning" colspan="2">
							<span class="ms-cancel-other-memberships"><?php
								_e(
									'Note that your other Memberships will be ' .
									'cancelled when you complete this payment.',
									MS_TEXT_DOMAIN
								);
							?></span>
						</td>
					</tr>
				<?php endif; ?>

				<?php do_action( 'ms_view_frontend_payment_purchase_button', $this->data['ms_relationship'] ); ?>
			</table>
		</div>
		<?php $this->coupon_html(); ?>
		<div style="clear:both;"></div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Returns a form where the member can enter a coupon code
	 *
	 * @since  1.0.0
	 * @return string HTML code
	 */
	private function coupon_html() {
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_COUPON ) ) {
			return;
		}

		$coupon = $this->data['coupon'];
		$coupon_message = '';
		$fields = array();

		if ( ! empty ( $this->data['coupon_valid'] ) ) {
			$fields = array(
				'coupon_code' => array(
					'id' => 'coupon_code',
					'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
					'value' => $coupon->code,
				),
				'remove_coupon_code' => array(
					'id' => 'remove_coupon_code',
					'type' => MS_Helper_Html::INPUT_TYPE_SUBMIT,
					'value' => __( 'Remove Coupon', MS_TEXT_DOMAIN ),
				),
			);
		}
		else {
			$fields = array(
				'coupon_code' => array(
					'id' => 'coupon_code',
					'type' => MS_Helper_Html::INPUT_TYPE_TEXT,
					'value' => $coupon->code,
				),
				'apply_coupon_code' => array(
					'id' => 'apply_coupon_code',
					'type' => MS_Helper_Html::INPUT_TYPE_SUBMIT,
					'value' => __( 'Apply Coupon', MS_TEXT_DOMAIN ),
				),
			);
		}

		$coupon_message = $coupon->coupon_message;

		$fields['membership_id'] = array(
			'id' => 'membership_id',
			'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
			'value' => $this->data['membership']->id,
		);
		$fields['move_from_id'] = array(
			'id' => 'move_from_id',
			'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
			'value' => $this->data['ms_relationship']->move_from_id,
		);
		$fields['step'] = array(
			'id' => 'step',
			'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
			'value' => MS_Controller_Frontend::STEP_PAYMENT_TABLE,
		);

		if ( ! empty ( $this->data['coupon_valid'] ) ) {
			$class = 'ms-alert-success';
		} else {
			$class = 'ms-alert-error';
		}

		?>
		<div class="membership-coupon">
			<div class="membership_coupon_form couponbar">
				<form method="post">
					<?php if ( $coupon_message ) : ?>
						<p class="ms-alert-box <?php echo esc_attr( $class ); ?>"><?php
							echo $coupon_message;
						?></p>
					<?php endif; ?>
					<div class="couponEntry">
						<?php if ( ! isset( $this->data['coupon_valid'] ) ) : ?>
							<div class="coupon-question"><?php
							_e( 'Have a coupon code?', MS_TEXT_DOMAIN );
							?></div>
						<?php endif;

						foreach ( $fields as $field ){
							MS_Helper_Html::html_element( $field );
						}
						?>
					</div>
				</form>
			</div>
		</div>
	<?php
	}

}