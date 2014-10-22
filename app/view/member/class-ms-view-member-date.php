<?php

class MS_View_Member_Date extends MS_View {

	protected $fields;

	protected $data;

	public function to_html() {
		$this->prepare_fields();
		ob_start();
		/** Render tabbed interface. */
		?>
		<div class='ms-wrap'>
			<div class='ms-settings'>
				<h2 class='ms-settings-title'>
					<i class="ms-fa ms-fa-pencil-square"></i>
					<?php _e( 'Edit membership dates', MS_TEXT_DOMAIN ); ?>
				</h2>
				<form action="<?php echo remove_query_arg( array( 'action', 'member_id' ) ); ?>" method="post">
					<?php wp_nonce_field( $this->fields['action']['value'] ); ?>
					<?php MS_Helper_Html::html_element( $this->fields['member_id'] ); ?>
					<?php
						foreach ( $this->fields['membership_id'] as $field ){
							MS_Helper_Html::html_element( $field );
						}
					?>
					<?php MS_Helper_Html::html_element( $this->fields['action'] ); ?>
					<?php
						MS_Helper_Html::settings_box_header(
							__( 'Membership dates', MS_TEXT_DOMAIN ),
							'',
							array( 'label_element' => 'h3' )
						);
					?>
					<table class="form-table">
						<tbody>
							<?php foreach( $this->fields['memberships'] as $membership_id => $field ): ?>
								<tr>
									<td>
										<h4><?php echo $field['title']; ?></h4>
										<?php MS_Helper_Html::html_element( $field ); ?>
										<span><?php _e( 'Start date', MS_TEXT_DOMAIN ); ?></span>
										<?php MS_Helper_Html::html_element( $this->fields['dates'][$membership_id]['start_date'] ); ?>

										<?php if( $this->fields['dates'][$membership_id]['expire_date']['value']): ?>
											<span><?php _e( 'Expire date', MS_TEXT_DOMAIN ); ?></span>
											<?php MS_Helper_Html::html_element( $this->fields['dates'][$membership_id]['expire_date'] ); ?>
										<?php endif;?>
									</td>
								</tr>
								<?php endforeach; ?>
							<tr>
								<td>
									<?php MS_Helper_Html::html_separator(); ?>
									<?php MS_Helper_Html::html_link( $this->fields['cancel'] ); ?>
									<?php MS_Helper_Html::html_submit( $this->fields['submit'] ); ?>
								</td>
							</tr>
						</tbody>
					</table>
					<?php MS_Helper_Html::settings_box_footer(); ?>
				</form>
				<div class="clear"></div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		echo $html;
	}

	function prepare_fields() {
		$this->fields = array(
			'member_id' => array(
					'id' => 'member_id',
					'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
					'value' => $this->data['member_id'],
			),
			'cancel' => array(
				'id' => 'cancel',
				'title' => __('Cancel', MS_TEXT_DOMAIN ),
				'value' => __('Cancel', MS_TEXT_DOMAIN ),
				'url' => remove_query_arg( array( 'action', 'member_id' ) ),
				'class' => 'button',
			),
			'submit' => array(
				'id' => 'submit',
				'value' => __( 'Change Date', MS_TEXT_DOMAIN ),
				'type' => 'submit',
			),
			'action' => array(
				'id' => 'action',
				'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
				'value' => $this->data['action'],
			),
		);

		foreach( $this->data['ms_relationships'] as $ms_relationship ) {
			$membership_id = $ms_relationship->membership_id;
			$this->fields['membership_id'][] = array(
					'id' => "membership_id_$membership_id",
					'name' => "membership_id[]",
					'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
					'value' => $membership_id,
			);
			$this->fields['memberships'][ $membership_id ] = array(
				'id' => "membership_id_$membership_id",
				'title' => __( 'Membership', MS_TEXT_DOMAIN ) . ': '. $ms_relationship->get_membership()->name,
				'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
				'value' => '',
			);
			$this->fields['dates'][ $membership_id ]['start_date'] = array(
				'id' => "start_date_$membership_id",
				'type' => MS_Helper_Html::INPUT_TYPE_TEXT,
				'value' => $ms_relationship->start_date,
				'class' => 'ms-date',
			);
			$this->fields['dates'][ $membership_id ]['trial_expire_date'] = array(
					'id' => "trial_expire_date_$membership_id",
					'type' => MS_Helper_Html::INPUT_TYPE_TEXT,
					'value' => $ms_relationship->trial_expire_date,
					'class' => 'ms-date',
			);
			$this->fields['dates'][ $membership_id ]['expire_date'] = array(
				'id' => "expire_date_$membership_id",
				'type' => MS_Helper_Html::INPUT_TYPE_TEXT,
				'value' => $ms_relationship->expire_date,
				'class' => 'ms-date',
			);
		}
	}
}