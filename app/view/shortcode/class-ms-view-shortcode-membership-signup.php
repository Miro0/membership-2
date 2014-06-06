<?php

class MS_View_Shortcode_Membership_Signup extends MS_View {
	
	protected $data;
	
	public function to_html() {
		MS_Helper_Debug::log( __( 'About to display the signup page...', MS_TEXT_DOMAIN ) );
		ob_start();
		?>
			<div class="ms-membership-form-wrapper">
				<legend><?php _e( 'Your Membership', MS_TEXT_DOMAIN ) ?></legend>
				<p class="ms-alert-box <?php echo $this->data['member']->is_member() ? 'ms-alert-success' : ''; ?>">
					<?php
						if( $this->data['member']->is_member() ) {
	 						_e( 'Your current subscriptions are listed here. You can renew, cancel or upgrade your subscriptions by using the forms below.', MS_TEXT_DOMAIN );
	 						foreach( $this->data['member']->membership_ids as $membership_id ){
	 							$this->membership_box_html( MS_Model_Membership::load( $membership_id ), MS_Model_Membership_Relationship::MEMBERSHIP_ACTION_CANCEL );
	 						}
	 					}
	 					else {
	 						_e( 'We have the following subscriptions available for our site. To join, simply click on the <strong>Sign Up</strong> button and then complete the registration details.', MS_TEXT_DOMAIN );
	 					}
					?>
				</p>
				<?php
					if( $this->data['member']->is_member() && ! empty( $this->data['memberships'] ) ) {
						?>
						    <?php  //***********  WE NEED TO CHANGE THIS PROCESS HERE ***************// ?>
		 					<legend class="ms-upgrade-from"> 
		 						<?php 
									if ( MS_Plugin::instance()->addon->multiple_membership ) {
										echo __( 'Add membership', MS_TEXT_DOMAIN ); 										
									} else {
										echo __( 'Change membership', MS_TEXT_DOMAIN ); 										
									}
								?>
		 					</legend>
		 				<?php 
	 				}
				?>	
				<form class="ms-membership-form" method="post">
					<div class="ms-form-price-boxes">
						<?php do_action( 'ms_membership_form_before_memberships' ); ?>
						<?php
							$membership_ids = $this->data['member']->membership_ids;
							$move_from_id = reset( $membership_ids );
							$action = MS_Model_Membership_Relationship::MEMBERSHIP_ACTION_SIGNUP;
							if( ! MS_Plugin::instance()->addon->multiple_membership && $move_from_id ) {
								$action = MS_Model_Membership_Relationship::MEMBERSHIP_ACTION_MOVE;
							}

							foreach( $this->data['memberships'] as $membership ) {
								$this->membership_box_html( $membership, $action, $move_from_id );
							}
						?>
						<?php do_action( 'ms_membership_form_after_memberships' ) ?>
					</div>
				</form>
			</div>
			<div style='clear:both;'></div>
		<?php
		$html = ob_get_clean();
		return $html;
	}
	
	private function membership_box_html( $membership, $action, $move_from_id = 0 ) {
		?>
		<div id="ms-membership-wrapper-<?php echo $membership->id ?>" class="ms-membership-details-wrapper">
			<div class="ms-top-bar">
				<span class="ms-title"><?php echo $membership->name; ?></span>
			</div>
			<div class="ms-price-details"><?php echo $membership->description; ?></div>
			<div class="ms-bottom-bar">
				<span class="ms-link">
				<?php
					$query_args = array( 'action' => $action, 'membership' => $membership->id ) ;
					if( ! empty( $move_from_id ) ) {
						$query_args[ 'move_from' ] = $move_from_id; 
					}
					$link = wp_nonce_url( add_query_arg( $query_args ), $action );
					$class = apply_filters( 'ms_membership_form_button_class', 'ms-signup-button' );
				?>
				<a href="<?php echo esc_url( $link ) ?>" class="<?php echo $class; ?>">
					<?php echo esc_html( $this->data[ "{$action}_text" ] ); ?>
				</a>
				</span>
			</div>
		</div>
		<?php 
	}
}