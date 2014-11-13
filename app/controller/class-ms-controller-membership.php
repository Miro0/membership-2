<?php
/**
 * This file defines the MS_Controller_Membership class.
 *
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
 * Controller for managing Memberships and Membership Rules.
 *
 * @since 1.0.0
 *
 * @package Membership
 * @subpackage Controller
 */
class MS_Controller_Membership extends MS_Controller {

	/**
	 * AJAX action constants.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const AJAX_ACTION_TOGGLE_MEMBERSHIP = 'toggle_membership';
	const AJAX_ACTION_UPDATE_MEMBERSHIP = 'update_membership';

	/**
	 * Membership page step constants.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const STEP_MS_LIST = 'ms_list';
	const STEP_OVERVIEW = 'ms_overview';
	const STEP_NEWS = 'ms_news';
	const STEP_SETUP_PROTECTED_CONTENT = 'setup_protected_content';
	const STEP_CHOOSE_MS_TYPE = 'choose_ms_type';
	const STEP_ACCESSIBLE_CONTENT = 'accessible_content';
	const STEP_SETUP_PAYMENT = 'setup_payment';
	const STEP_SETUP_CONTENT_TYPES = 'setup_content_types';
	const STEP_SETUP_MS_TIERS = 'setup_ms_tiers';
	const STEP_SETUP_DRIPPED = 'setup_dripped';

	/**
	 * The model to use for loading/saving Membership data.
	 *
	 * @since 4.0.0
	 * @var MS_Model_Membership
	 */
	private $model;

	/**
	 * The active page tab.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $active_tab;

	/**
	 * Prepare the Membership manager.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();

		$protected_content_menu_hook = 'toplevel_page_protected-content';
		$protected_content_setup_hook = 'protect-content_page_protected-content-setup';

		$this->add_action( 'load-' . $protected_content_menu_hook, 'membership_admin_page_process' );
		$this->add_action( 'load-' . $protected_content_setup_hook, 'membership_admin_page_process' );

		$this->add_action( 'wp_ajax_' . self::AJAX_ACTION_TOGGLE_MEMBERSHIP, 'ajax_action_toggle_membership' );
		$this->add_action( 'wp_ajax_' . self::AJAX_ACTION_UPDATE_MEMBERSHIP, 'ajax_action_update_membership' );

		$this->add_action( 'admin_print_scripts-' . $protected_content_setup_hook, 'enqueue_scripts' );
		$this->add_action( 'admin_print_styles-' . $protected_content_setup_hook, 'enqueue_styles' );

		$this->add_action( 'admin_print_scripts-' . $protected_content_menu_hook, 'enqueue_scripts' );
		$this->add_action( 'admin_print_styles-' . $protected_content_menu_hook, 'enqueue_styles' );
	}

	/**
	 * Handle Ajax toggle action.
	 *
	 * **Hooks Actions: **
	 *
	 * * wp_ajax_toggle_membership
	 *
	 * @since 1.0.0
	 */
	public function ajax_action_toggle_membership() {
		$msg = 0;

		if ( $this->verify_nonce() && ! empty( $_POST['membership_id'] ) && ! empty( $_POST['field'] ) && $this->is_admin_user() ) {
			$msg = $this->membership_list_do_action( 'toggle_'. $_POST['field'], array( $_POST['membership_id'] ) );
		}

		do_action( 'ms_controller_membership_ajax_action_toggle_membership', $msg, $this );

		echo $msg;
		exit;
	}

	/**
	 * Handle Ajax toggle action.
	 *
	 * **Hooks Actions: **
	 *
	 * * wp_ajax_update_membership
	 *
	 * @since 1.0.0
	 */
	public function ajax_action_update_membership() {
		$msg = 0;

		$required = array( 'membership_id', 'field', 'value' );
		if ( $this->verify_nonce() && $this->validate_required( $required, 'POST', false ) && $this->is_admin_user() ) {
			$msg = $this->save_membership( array( $_POST['field'] => $_POST['value'] ) );
		}

		do_action( 'ms_controller_membership_ajax_action_update_membership', $msg, $this );

		echo $msg;
		exit;

	}

	/**
	 * Load membership from request.
	 *
	 * @since 1.0.0
	 *
	 * @return MS_Model_Membership The membership model object.
	 */
	public function load_membership() {
		$membership_id = 0;

		if ( empty( $this->model ) || ! $this->model->is_valid() ) {
			if ( ! empty( $_GET['membership_id'] ) ) {
				$membership_id = absint( $_GET['membership_id'] );
			}
			elseif ( ! empty( $_POST['membership_id'] ) ) {
				$membership_id = absint( $_POST['membership_id'] );
			}

			$this->model = MS_Factory::load( 'MS_Model_Membership', $membership_id );
		}

		return apply_filters( 'ms_controller_membership_load_membership', $this->model, $this );
	}

	/**
	 * Process membership pages requests
	 *
	 * Verifies GET and POST requests to manage memberships.
	 * Redirect to next step after processing.
	 *
	 * @since 1.0.0
	 */
	public function membership_admin_page_process() {
		$msg = 0;
		$next_step = null;
		$step = $this->get_step();
		$goto_url = null;
		$membership = $this->load_membership();
		$membership_id = $membership->id;

		// MS_Controller_Rule is executed using this action
		do_action( 'ms_controller_membership_admin_page_process_'. $step, $this->get_active_tab() );

		// Verify intent in request, only accessible to admin users
		if ( $this->is_admin_user() && ( $this->verify_nonce() || $this->verify_nonce( null, 'GET' ) ) ) {
			// Take next actions based in current step.
			switch ( $step ) {
				case self::STEP_MS_LIST:
					$fields = array( 'action', 'membership_id' );
					if ( $this->validate_required( $fields, 'GET' ) ) {
						$msg = $this->membership_list_do_action(
							$_GET['action'],
							array( absint( $_GET['membership_id'] ) )
						);
						$next_step = self::STEP_MS_LIST;
					}
					break;

				case self::STEP_SETUP_PROTECTED_CONTENT:
					$next_step = self::STEP_CHOOSE_MS_TYPE;
					$this->wizard_tracker( $next_step );
					break;

				case self::STEP_CHOOSE_MS_TYPE:
					$this->wizard_tracker( $step, true );
					$fields = $_POST;

					if ( ! $this->validate_required( array( 'private' ) ) ) {
						$fields['private'] = false;
					}
					$msg = $this->save_membership( $fields );

					// Reload membership after creating
					$membership = $this->load_membership();
					$membership_id = $membership->id;

					$next_step = self::STEP_ACCESSIBLE_CONTENT;
					switch ( $membership->type ) {
						case MS_Model_Membership::TYPE_CONTENT_TYPE:
							$next_step = self::STEP_SETUP_CONTENT_TYPES;
							break;
						case MS_Model_Membership::TYPE_TIER:
							$next_step = self::STEP_SETUP_MS_TIERS;
							break;
						case MS_Model_Membership::TYPE_DRIPPED:
							$next_step = self::STEP_SETUP_DRIPPED;
							break;
						default:
							$next_step = self::STEP_ACCESSIBLE_CONTENT;
							break;
					}
					break;

				case self::STEP_ACCESSIBLE_CONTENT:
					$fields = $_POST;
					$msg = $this->save_membership( $fields );
					$next_step = self::STEP_ACCESSIBLE_CONTENT;
					switch ( $membership->type ) {
						case MS_Model_Membership::TYPE_CONTENT_TYPE:
							$next_step = self::STEP_SETUP_CONTENT_TYPES;
							if ( $membership->parent_id ) {
								$membership_id = $membership->parent_id;
							}
							break;
						case MS_Model_Membership::TYPE_TIER:
							$next_step = self::STEP_SETUP_MS_TIERS;
							if ( $membership->parent_id ) {
								$membership_id = $membership->parent_id;
							}
							break;
						case MS_Model_Membership::TYPE_SIMPLE:
							if ( $membership->private ) {
								$next_step = self::STEP_MS_LIST;
								$msg = $this->mark_setup_completed();
							}
							else {
								$next_step = self::STEP_SETUP_PAYMENT;
							}
							break;
						default:
							$next_step = self::STEP_MS_LIST;
							$msg = $this->mark_setup_completed();
							break;
					}
					break;

				case self::STEP_SETUP_PAYMENT:
					$next_step = self::STEP_MS_LIST;
					$msg = $this->mark_setup_completed();
					break;

				case self::STEP_SETUP_CONTENT_TYPES:
					if ( $this->validate_required( array( 'name' ) ) && 'create_content_type' == $_POST['action'] ) {
						$child = $this->create_child_membership(  $_POST['name'] );
						$membership_id = $child->id;
						$next_step = self::STEP_ACCESSIBLE_CONTENT;
					}
					else {
						if ( $membership->private ) {
							$next_step = self::STEP_MS_LIST;
							$msg = $this->mark_setup_completed();
						}
						else {
							$next_step = self::STEP_SETUP_PAYMENT;
						}
					}
					break;

				case self::STEP_SETUP_MS_TIERS:
					if ( $this->validate_required( array( 'name' ) ) && 'create_tier' == $_POST['action'] ) {
						$child = $this->create_child_membership(  $_POST['name'] );
						$membership_id = $child->id;
						$next_step = self::STEP_ACCESSIBLE_CONTENT;
					}
					else {
						$next_step = self::STEP_SETUP_PAYMENT;
					}
					break;

				case self::STEP_SETUP_DRIPPED:
					$next_step = self::STEP_SETUP_PAYMENT;
					break;

				default:
					break;
			}

			if ( ! empty( $next_step ) ) {
				$args = array(
					'step' => $next_step,
					'membership_id' => $membership_id,
				);
				if ( ! empty( $msg ) ) {
					$args['msg'] = $msg;
				}
				$goto_url = add_query_arg( $args, MS_Controller_Plugin::get_admin_url() );
				$goto_url = apply_filters( 'ms_controller_membership_membership_admin_page_process_goto_url', $goto_url, $next_step );
				wp_safe_redirect( $goto_url );
				exit;
			}
		}
		// No action request found. Validate direct access.
		else {
			switch ( $step ) {
				// Child overview page is shown in parent's overview, redirect.
				case self::STEP_OVERVIEW:
					if ( $membership->has_parent() && empty( $_GET['tab'] ) ) {
						$new_url = add_query_arg(
							array(
								'membership_id' => $membership->parent_id,
								'tab' => $membership->id,
							)
						);
						wp_safe_redirect( $new_url );
						exit;
					}
					break;

				case self::STEP_ACCESSIBLE_CONTENT:
					// Parent membership can not edit rules-
					if ( $membership->can_have_children() ) {

						$args = array();
						$child = $membership->get_last_descendant();

						if ( $child->id != $membership->id ) {
							$args['membership_id'] = $child->id;
						}
						else {
							if ( MS_Model_Membership::TYPE_CONTENT_TYPE == $membership->type ) {
								$args['step'] = self::STEP_SETUP_CONTENT_TYPES;
							}
							elseif ( MS_Model_Membership::TYPE_TIER == $membership->type ) {
								$args['step'] = self::STEP_SETUP_MS_TIERS;
							}
						}
						wp_safe_redirect( add_query_arg( $args ) );
						exit;
					}
					break;
			}
		}

	}

	/**
	 * Route page request to handling method.
	 *
	 * @since 1.0.0
	 */
	public function membership_admin_page_router() {
		$this->wizard_tracker();
		$step = $this->get_step();

		if ( self::is_valid_step( $step ) ) {

			$method = "page_{$step}";
			if ( method_exists( $this, $method ) ) {
				$callback = apply_filters( 'ms_controller_membership_admin_page_router_callback', array( $this, $method ), $this );
				call_user_func( $callback );
			}
			else {
				do_action( 'ms_controller_membership_admin_page_router_' . $step, $this );
				MS_Helper_Debug::log( "Method $method not found for step $step" );
			}
		}
		else {
			MS_Helper_Debug::log( "Invalid step: $step" );
		}

		do_action( 'ms_controller_membership_admin_page_router', $step, $this );
	}

	/**
	 * Mark membership setup as complete.
	 *
	 * @since 1.0.0
	 *
	 * @return int $msg The action status message code.
	 */
	private function mark_setup_completed() {
		$msg = 0;
		$membership = $this->load_membership();

		if ( $membership->mark_setup_completed() ) {
			$msg = MS_Helper_Membership::MEMBERSHIP_MSG_ADDED;
			do_action( 'ms_controller_membership_setup_completed', $membership );
		}

		return apply_filters(
			'ms_controller_membership_mark_setup_completed',
			$msg,
			$this
		);
	}

	/**
	 * Display Setup Protected Content page.
	 *
	 * @since 1.0.0
	 */
	public function page_setup_protected_content() {
		$data = array();
		$data['tabs'] = $this->get_protected_content_tabs();
		$data['active_tab'] = $this->get_active_tab();
		$data['step'] = $this->get_step();
		$data['action'] = MS_Controller_Rule::AJAX_ACTION_UPDATE_RULE;
		$data['show_next_button'] = MS_Plugin::is_wizard();
		$data['settings'] = MS_Plugin::instance()->settings;

		$data['membership'] = MS_Model_Membership::get_protected_content();
		$data['menus'] = $data['membership']->get_rule( MS_Model_Rule::RULE_TYPE_MENU )->get_menu_array();
		$first_value = array_keys( $data['menus'] );
		$first_value = reset( $first_value );
		$data['menu_id'] = $this->get_request_field( 'menu_id', $first_value, 'REQUEST' );
		$data['initial_setup'] = MS_Plugin::is_wizard();

		$view = MS_Factory::create( 'MS_View_Membership_Setup_Protected_Content' );
		$view->data = apply_filters( 'ms_view_membership_setup_protected_content_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Choose Membership Type page.
	 *
	 * @since 1.0.0
	 */
	public function page_choose_ms_type() {
		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = 'save_membership';
		$data['membership'] = $this->load_membership();

		$view = MS_Factory::create( 'MS_View_Membership_Choose_Type' );
		$view->data = apply_filters( 'ms_view_membership_choose_type_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Accessible Content page.
	 *
	 * @since 1.0.0
	 */
	public function page_accessible_content() {
		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = MS_Controller_Rule::AJAX_ACTION_UPDATE_RULE;
		$data['bread_crumbs'] = $this->get_bread_crumbs();
		$data['tabs'] = $this->get_accessible_content_tabs();
		$data['active_tab'] = $this->get_active_tab();
		$data['membership'] = $this->load_membership();
		$data['show_next_button'] = true;
		$data['settings'] = MS_Plugin::instance()->settings;

		$data['menus'] = $data['membership']->get_rule( MS_Model_Rule::RULE_TYPE_MENU )->get_menu_array();
		$first_value = array_keys( $data['menus'] );
		$first_value = reset( $first_value );
		$data['menu_id'] = $this->get_request_field( 'menu_id', $first_value, 'REQUEST' );

		$view = MS_Factory::create( 'MS_View_Membership_Accessible_Content' );
		$view->data = apply_filters( 'ms_view_membership_setup_accessible_content_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Membership List page.
	 *
	 * @since 1.0.0
	 */
	public function page_ms_list() {
		$membership = $this->load_membership();

		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = 'save_membership';
		$data['tabs'] = $this->get_accessible_content_tabs();
		$data['membership'] = $membership;
		$data['create_new_url'] = add_query_arg(
			array( 'step' => self::STEP_CHOOSE_MS_TYPE ),
			MS_Controller_Plugin::get_admin_url()
		);

		$view = MS_Factory::create( 'MS_View_Membership_List' );
		$view->data = apply_filters( 'ms_view_membership_list_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Setup Payment page.
	 *
	 * @since 1.0.0
	 */
	public function page_setup_payment() {
		$membership = $this->load_membership();

		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = 'save_payment_settings';
		$data['membership'] = $membership;
		$data['children'] = $membership->get_children();
		$data['is_global_payments_set'] = MS_Plugin::instance()->settings->is_global_payments_set;
		$data['bread_crumbs'] = $this->get_bread_crumbs();
		$data['show_next_button'] = true;

		$view = MS_Factory::create( 'MS_View_Membership_Setup_Payment' );
		$view->data = apply_filters( 'ms_view_membership_setup_payment_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Membership Overview page.
	 *
	 * @since 1.0.0
	 */
	public function page_ms_overview() {
		$membership = $this->load_membership();
		$membership_id = $membership->id;

		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = 'save_membership';
		$data['membership'] = $membership;
		$data['bread_crumbs'] = $this->get_bread_crumbs();

		$data['members'] = array();
		$ms_relationships = MS_Model_Membership_Relationship::get_membership_relationships(
			array( 'membership_id' => $membership->id )
		);

		foreach ( $ms_relationships as $ms_relationship ) {
			$data['members'][] = $ms_relationship->get_member();
		}

		switch ( $membership->type ) {
			case MS_Model_Membership::TYPE_DRIPPED:
				$view = MS_Factory::create( 'MS_View_Membership_Overview_Dripped' );
				break;

			case MS_Model_Membership::TYPE_TIER:
				$view = MS_Factory::create( 'MS_View_Membership_Overview_Tier' );
				$data['tabs'] = $this->get_children_tabs( $membership );
				$child = MS_Factory::load( 'MS_Model_Membership', $this->get_active_tab() );
				$data['child_membership'] = $child;
				$membership_id = $child->id;
				$ms_relationships = MS_Model_Membership_Relationship::get_membership_relationships(
					array( 'membership_id' => $membership_id )
				);

				foreach ( $ms_relationships as $ms_relationship ) {
					$data['members'][] = $ms_relationship->get_member();
				}
				break;

			case MS_Model_Membership::TYPE_CONTENT_TYPE:
				$view = MS_Factory::create( 'MS_View_Membership_Overview_Content_Type' );
				$data['tabs'] = $this->get_children_tabs( $membership );
				$child = MS_Factory::load( 'MS_Model_Membership', $this->get_active_tab() );
				$data['child_membership'] = $child;
				$membership_id = $child->id;
				$ms_relationships = MS_Model_Membership_Relationship::get_membership_relationships(
					array( 'membership_id' => $membership_id )
				);

				foreach ( $ms_relationships as $ms_relationship ) {
					$data['members'][] = $ms_relationship->get_member();
				}
				break;

			default:
			case MS_Model_Membership::TYPE_SIMPLE:
				$view = MS_Factory::create( 'MS_View_Membership_Overview' );
				break;
		}

		// Select Events args
		$args = array();
		$args['meta_query']['membership_id'] = array(
			'key'     => 'membership_id',
			'value'   => array( $membership_id, 0 ),
			'compare' => 'IN',
		);
		$data['events'] = MS_Model_Event::get_events( $args );

		$view = apply_filters( 'ms_view_membership_ms_overview', $view );
		$view->data = apply_filters( 'ms_view_membership_ms_overview_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Membership News page.
	 *
	 * @since 1.0.0
	 */
	public function page_ms_news() {
		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = '';
		$data['membership'] = $this->load_membership();

		$args = apply_filters(
			'ms_controller_membership_page_ms_news_event_args',
			array( 'posts_per_page' => -1 )
		);
		$data['events'] = MS_Model_Event::get_events( $args );

		$view = MS_Factory::create( 'MS_View_Membership_News' );
		$view->data = apply_filters( 'ms_view_membership_news_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Setup Content Types page.
	 *
	 * @since 1.0.0
	 */
	public function page_setup_content_types() {
		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = 'create_content_type';
		$data['membership'] = $this->load_membership();
		$data['initial_setup'] = MS_Plugin::is_wizard();
		$data['bread_crumbs'] = $this->get_bread_crumbs();
		$data['show_next_button'] = true;

		$view = MS_Factory::create( 'MS_View_Membership_Setup_Content_Type' );
		$view->data = apply_filters( 'ms_view_membership_setup_content_types_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Setup Membership Tiers page.
	 *
	 * @since 1.0.0
	 */
	public function page_setup_ms_tiers() {
		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = 'create_tier';
		$data['membership'] = $this->load_membership();
		$data['initial_setup'] = MS_Plugin::is_wizard();
		$data['bread_crumbs'] = $this->get_bread_crumbs();
		$data['show_next_button'] = true;

		$view = MS_Factory::create( 'MS_View_Membership_Setup_Tier' );
		$view->data = apply_filters( 'ms_view_membership_ms_tiers_data', $data, $this );
		$view->render();
	}

	/**
	 * Display Setup Dripped Content page.
	 *
	 * @since 1.0.0
	 */
	public function page_setup_dripped() {
		$data = array();
		$data['step'] = $this->get_step();
		$data['action'] = 'save_membership';
		$data['membership'] = $this->load_membership();
		$data['tabs'] = $this->get_setup_dripped_tabs();
		$data['bread_crumbs'] = $this->get_bread_crumbs();
		$data['show_next_button'] = true;

		$view = MS_Factory::create( 'MS_View_Membership_Setup_Dripped' );
		$view->data = apply_filters( 'ms_view_membership_setup_dripped_data', $data, $this );
		$view->render();
	}

	/**
	 * Get Membership setup steps.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] The existing steps.
	 */
	public static function get_steps() {
		static $steps;

		if ( empty( $steps ) ) {
			$steps = array(
				self::STEP_MS_LIST,
				self::STEP_OVERVIEW,
				self::STEP_NEWS,
				self::STEP_SETUP_PROTECTED_CONTENT,
				self::STEP_CHOOSE_MS_TYPE,
				self::STEP_SETUP_CONTENT_TYPES,
				self::STEP_SETUP_MS_TIERS,
				self::STEP_SETUP_DRIPPED,
				self::STEP_ACCESSIBLE_CONTENT,
				self::STEP_SETUP_PAYMENT,
			);
		}

		return apply_filters( 'ms_controller_membership_get_steps', $steps );
	}

	/**
	 * Validate Membership setup step.
	 *
	 * @since 1.0.0
	 *
	 * @param string $step The step name to validate.
	 * @return boolean True if valid step.
	 */
	public static function is_valid_step( $step ) {
		$valid = false;

		$steps = self::get_steps();
		if ( in_array( $step, $steps ) ) {
			$valid = true;
		}

		return apply_filters(
			'ms_controller_membership_is_valid_step',
			$valid,
			$step
		);
	}

	/**
	 * Get current step.
	 *
	 * Try to retrieve step from request.
	 * Validate the step, returning a default if not valid.
	 *
	 * since 1.0.0
	 *
	 * @return string The current step.
	 */
	public function get_step() {
		// Initial step
		$step = self::STEP_MS_LIST;
		$settings = MS_Factory::load( 'MS_Model_Settings' );
		$membership = $this->load_membership();

		// Get current step from request
		if ( ! empty( $_REQUEST['step'] ) && self::is_valid_step( $_REQUEST['step'] ) ) {
			$step = $_REQUEST['step'];
		}

		// If user has left before completing the wizard, try to recover last wizard step.
		elseif ( MS_Plugin::is_wizard() ) {
			$wizard_steps = apply_filters(
				'ms_controller_membership_wizard_steps',
				array( self::STEP_SETUP_PROTECTED_CONTENT, self::STEP_CHOOSE_MS_TYPE )
			);

			if ( $settings->wizard_step
				&& in_array( $settings->wizard_step, $wizard_steps )
			) {
				$step = $settings->wizard_step;
			}
			else {
				$step = self::STEP_SETUP_PROTECTED_CONTENT;
			}
		}

		// Hack to use same page in two different menus
		$the_page = sanitize_html_class( @$_GET['page'] );
		if ( MS_Controller_Plugin::MENU_SLUG . '-setup' === $the_page ) {
			$step = self::STEP_SETUP_PROTECTED_CONTENT;
		}

		// If trying to setup children of not supported type, or already is a child (grand child not allowed)
		if ( in_array( $step, array( self::STEP_SETUP_CONTENT_TYPES, self::STEP_SETUP_MS_TIERS ) )
			&& ! $membership->can_have_children()
		) {
			$step = self::STEP_OVERVIEW;
		}

		// Accessible content page is not available to dripped type
		if ( self::STEP_ACCESSIBLE_CONTENT === $step
			&& MS_Model_Membership::TYPE_DRIPPED === $membership->type
		) {
			$step = self::STEP_SETUP_DRIPPED;
		}

		// Can't modify membership type
		if ( self::STEP_CHOOSE_MS_TYPE == $step && $membership->is_valid() ) {
			$step = self::STEP_OVERVIEW;
		}

		return apply_filters(
			'ms_controller_membership_get_next_step',
			$step,
			$this
		);
	}

	/**
	 * Track wizard step.
	 *
	 * Save current step.
	 *
	 * since 1.0.0
	 *
	 * @param string $step Optional. The step to save. Default to current step.
	 * @param boolean $end_wizard Optional. Whether end the wizard mode.
	 * @return string The current step.
	 */
	public function wizard_tracker( $step = null, $end_wizard = false ) {
		$settings = MS_Factory::load( 'MS_Model_Settings' );

		if ( empty( $step ) ) {
			$step = $this->get_step();
		}
		if ( MS_Plugin::is_wizard() ) {
			$settings->wizard_step = $step;

			if ( $end_wizard ) {
				$settings->initial_setup = false;
			}
			$settings->save();
		}

		do_action(
			'ms_controller_membership_wizard_tracker',
			$step,
			$end_wizard,
			$settings,
			$this
		);
	}

	/**
	 * Get available tabs for Protected Content page.
	 *
	 * @since 1.0.0
	 *
	 * @return array The tabs configuration.
	 */
	public function get_protected_content_tabs() {
		$membership_id = $this->load_membership()->id;

		$tabs = array(
			'page' => array(
				'title' => __( 'Pages', MS_TEXT_DOMAIN ),
			),
			'category' => array(
				'title' => __( 'Categories, Custom Post Types', MS_TEXT_DOMAIN ),
			),
			'post' => array(
				'title' => __( 'Posts', MS_TEXT_DOMAIN ),
			),
			'cpt' => array(
				'title' => __( 'Custom Post Types', MS_TEXT_DOMAIN ),
			),
			'comment' => array(
				'title' => __( 'Comments, More Tag, Menus', MS_TEXT_DOMAIN ),
			),
			'shortcode' => array(
				'title' => __( 'Shortcodes', MS_TEXT_DOMAIN ),
			),
			'url_group' => array(
				'title' => __( 'URL Groups', MS_TEXT_DOMAIN ),
			),
			'special' => array(
				'title' => __( 'Special Pages', MS_TEXT_DOMAIN ),
			),
		);

		$title = array();
		// Enable / Disable post by post tab.
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_POST_BY_POST ) ) {
			unset( $tabs['post'] );
			$title['category'] = __( 'Categories', MS_TEXT_DOMAIN );
		}

		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_CPT_POST_BY_POST ) ) {
			$title['cpt_group'] = __( 'Custom Post Types', MS_TEXT_DOMAIN );
		}


		$tabs['category']['title'] = implode( ', ', $title );
		if ( MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_CPT_POST_BY_POST ) &&
			MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_POST_BY_POST	) ) {
			unset( $tabs['category'] );
		}

		// Add the special-pages protection.
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_SPECIAL_PAGES ) ) {
			unset( $tabs['special'] );
		}

		// Enable / Disable custom post by post tab.
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_CPT_POST_BY_POST ) ) {
			unset( $tabs['cpt'] );
		}

		// Disable urlgroup tab.
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_URL_GROUPS ) ) {
			unset( $tabs['url_group'] );
		}

		// Disable shortcode tab.
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_SHORTCODE ) ) {
			unset( $tabs['shortcode'] );
		}

		$tabs = apply_filters( 'ms_controller_membership_tabs', $tabs, $membership_id );
		$url = admin_url( 'admin.php' );
		$page = sanitize_html_class( @$_GET['page'], 'protected-content-memberships' );

		foreach ( $tabs as $tab => $info ) {
			$tabs[ $tab ]['url'] = admin_url(
				sprintf(
					'admin.php?page=%s&tab=%s',
					$page,
					$tab
				)
			);
		}

		return apply_filters( 'ms_controller_membership_get_tabs', $tabs, $membership_id, $this );
	}

	/**
	 * Get available tabs for Accessible Content page.
	 *
	 * @since 1.0.0
	 *
	 * @return array The tabs configuration.
	 */
	public function get_accessible_content_tabs() {
		$membership_id = $this->load_membership()->id;
		$tabs = $this->get_protected_content_tabs();
		$protected_content = MS_Model_Membership::get_protected_content();

		$step = $this->get_step();
		$page = sanitize_html_class( @$_GET['page'], 'protected-content-memberships' );

		foreach ( $tabs as $tab => $info ) {
			$rule = $protected_content->get_rule( $tab );

			switch ( $tab ) {
				case 'category':
					$cnt_category = $protected_content->get_rule( MS_Model_Rule::RULE_TYPE_CATEGORY )->count_rules();
					$cnt_cpt = $protected_content->get_rule( MS_Model_Rule::RULE_TYPE_CUSTOM_POST_TYPE_GROUP )->count_rules();
					if ( ! $cnt_category && ! $cnt_cpt ) {
						unset( $tabs[ $tab ] );
					}
					break;

				case 'comment':
					$cnt_comment = $protected_content->get_rule( MS_Model_Rule::RULE_TYPE_COMMENT )->count_rules();
					$cnt_more_tag = $protected_content->get_rule( MS_Model_Rule::RULE_TYPE_MORE_TAG )->count_rules();
					$cnt_menu = $protected_content->get_rule( MS_Model_Rule::RULE_TYPE_MENU )->count_rules();
					if ( ! $cnt_comment && ! $cnt_more_tag && ! $cnt_menu ) {
						unset( $tabs[ $tab ] );
					}
					break;

				case 'url_group':
					$cnt_url_group = $protected_content->get_rule( MS_Model_Rule::RULE_TYPE_URL_GROUP )->count_rules();
					if ( ! $cnt_url_group || ! $rule->access ) {
						unset( $tabs[ $tab ] );
					}
					break;

				default:
					if ( ! $rule->count_rules() ) {
						unset( $tabs[ $tab ] );
					}
					break;
			}
		}
		foreach ( $tabs as $tab => $info ) {
			$tabs[ $tab ]['url'] = admin_url(
				sprintf(
					'admin.php?page=%1$s&step=%2$s&tab=%3$s&membership_id=%4$s',
					$page,
					$step,
					$tab,
					$membership_id
				)
			);
		}

		return apply_filters( 'ms_controller_membership_get_tabs', $tabs, $membership_id, $this );
	}

	/**
	 * Get available tabs for Setup Dripped Content page.
	 *
	 * @since 1.0.0
	 *
	 * @return array The tabs configuration.
	 */
	public function get_setup_dripped_tabs() {
		$membership_id = $this->load_membership()->id;

		$tabs = array(
			'post' => array(
				'title' => __( 'Posts', MS_TEXT_DOMAIN ),
			),
			'page' => array(
				'title' => __( 'Pages', MS_TEXT_DOMAIN ),
			),
		);

		$step = $this->get_step();
		$page = sanitize_html_class( @$_GET['page'], 'protected-content' );

		foreach ( $tabs as $tab => $info ) {
			$tabs[ $tab ]['url'] = admin_url(
				sprintf(
					'admin.php?page=%s&step=%s&tab=%s&membership_id=%s',
					$page,
					$step,
					$tab,
					$membership_id
				)
			);
		}

		return apply_filters( 'ms_controller_membership_get_tabs', $tabs, $membership_id );
	}

	/**
	 * Get available tabs for Tiers/Content-type parent memberships.
	 *
	 * @since 1.0.0
	 *
	 * @return array The tabs configuration.
	 */
	public function get_children_tabs() {

		$tabs = array();

		$membership = $this->load_membership();
		$children = $membership->get_children();
		foreach ( $children as $child ) {
			$tabs[ $child->id ] = array(
				'title' => $child->name,
				'url' => add_query_arg( array( 'tab' => $child->id ) ),
			);
		}

		return apply_filters( 'ms_controller_membership_get_children_tabs', $tabs, $this );
	}

	/**
	 * Get the current membership page's active tab.
	 *
	 * @since 1.0.0
	 *
	 * @return string The active tab.
	 */
	public function get_active_tab() {

		$step = $this->get_step();
		$tabs = array();

		if ( self::STEP_SETUP_PROTECTED_CONTENT == $step ) {
			$tabs = $this->get_protected_content_tabs();
		}
		elseif ( self::STEP_ACCESSIBLE_CONTENT == $step ) {
			$tabs = $this->get_accessible_content_tabs();
		}
		elseif ( self::STEP_SETUP_DRIPPED == $step ) {
			$tabs = $this->get_setup_dripped_tabs();
		}
		elseif ( self::STEP_OVERVIEW == $step ) {
			$tabs = $this->get_children_tabs();
		}
		reset( $tabs );
		$first_key = key( $tabs );

		// Setup navigation tabs.
		$active_tab = sanitize_html_class( @$_GET['tab'], $first_key );

		if ( ! array_key_exists( $active_tab, $tabs ) ) {
			switch ( $active_tab ) {
				case 'cpt_group':
					$active_tab = 'category';
					break;

				case 'menu':
				case 'replace_menu':
				case 'replace_menulocation':
				case 'more_tag':
					$active_tab = 'comment';
					break;

				default:
					$active_tab = $first_key;
					break;
			}
		}

		return $this->active_tab = apply_filters( 'ms_controller_membership_get_active_tab', $active_tab );
	}

	/**
	 * Execute action in Membership model.
	 *
	 * @since 1.0.0
	 *
	 * @todo There is no more bulk actions. Deprecate this method and create a specific one.
	 *
	 * @param string $action The action to execute.
	 * @param int[] $membership_ids The membership ids which action will be taken.
	 * @return number Resulting message id.
	 */
	private function membership_list_do_action( $action, $membership_ids ) {

		$msg = MS_Helper_Membership::MEMBERSHIP_MSG_NOT_UPDATED;
		if ( ! $this->is_admin_user() ) {
			return $msg;
		}

		$msg = 0;
		foreach ( $membership_ids as $membership_id ) {
			$membership = MS_Factory::load( 'MS_Model_Membership', $membership_id );
			switch ( $action ) {
				case 'toggle_active':
				case 'toggle_activation':
					$membership->active = ! $membership->active;
					$membership->save();
					$msg = MS_Helper_Membership::MEMBERSHIP_MSG_ACTIVATION_TOGGLED;
					break;
				case 'toggle_public':
					$membership->private = ! $membership->private;
					$membership->save();
					$msg = MS_Helper_Membership::MEMBERSHIP_MSG_STATUS_TOGGLED;
					break;
				case 'delete':
					try{
						$membership->delete();
						$msg = MS_Helper_Membership::MEMBERSHIP_MSG_DELETED;
					}
					catch( Exception $e ) {
						$msg = MS_Helper_Membership::MEMBERSHIP_MSG_NOT_DELETED;
					}
					break;
			}
		}

		return $msg;
	}

	/**
	 * Get Membership page bread crumbs.
	 *
	 * @since 1.0.0
	 *
	 * @return array The bread crumbs array.
	 */
	public function get_bread_crumbs() {
		$step = $this->get_step();
		$membership = $this->load_membership();

		$bread_crumbs = array();

		switch ( $step ) {
			case self::STEP_OVERVIEW:
				$bread_crumbs['prev'] = array(
					'title' => __( 'Memberships', MS_TEXT_DOMAIN ),
					'url' => admin_url(
						sprintf(
							'admin.php?page=%s&step=%s',
							MS_Controller_Plugin::MENU_SLUG,
							self::STEP_MS_LIST
						)
					),
				);
				$bread_crumbs['current'] = array(
					'title' => $membership->name,
				);
				break;

			case self::STEP_ACCESSIBLE_CONTENT:
				if ( $parent = $membership->get_parent() ) {
					$bread_crumbs['prev'] = array(
						'title' => $parent->name,
						'url' => admin_url(
							sprintf(
								'admin.php?page=%s&step=%s&membership_id=%s',
								MS_Controller_Plugin::MENU_SLUG,
								self::STEP_OVERVIEW,
								$parent->id
							)
						),
					);
					if ( MS_Model_Membership::TYPE_TIER == $parent->type ) {
						$bread_crumbs['prev1'] = array(
							'title' => __( 'Tier Levels', MS_TEXT_DOMAIN ),
							'url' => admin_url(
								sprintf(
									'admin.php?page=%s&step=%s&membership_id=%s',
									MS_Controller_Plugin::MENU_SLUG,
									self::STEP_SETUP_MS_TIERS,
									$parent->id
								)
							),
						);
					}
					elseif ( MS_Model_Membership::TYPE_CONTENT_TYPE == $parent->type ) {
						$bread_crumbs['prev1'] = array(
							'title' => __( 'Content Types', MS_TEXT_DOMAIN ),
							'url' => admin_url(
								sprintf(
									'admin.php?page=%s&step=%s&membership_id=%s',
									MS_Controller_Plugin::MENU_SLUG,
									self::STEP_SETUP_CONTENT_TYPES,
									$parent->id
								)
							),
						);
					}
					$bread_crumbs['current'] = array(
						'title' => sprintf(
							__( '%s Accessible Content', MS_TEXT_DOMAIN ),
							$membership->name
						),
					);
				}
				else {
					$bread_crumbs['prev'] = array(
						'title' => $membership->name,
						'url' => admin_url(
							sprintf(
								'admin.php?page=%s&step=%s&membership_id=%s',
								MS_Controller_Plugin::MENU_SLUG,
								self::STEP_OVERVIEW,
								$membership->id
							)
						),
					);
					$bread_crumbs['current'] = array(
						'title' => __( 'Accessible Content', MS_TEXT_DOMAIN ),
					);

				}
				break;

			case self::STEP_SETUP_CONTENT_TYPES:
				$bread_crumbs['prev'] = array(
					'title' => $membership->name,
					'url' => admin_url(
						sprintf(
							'admin.php?page=%s&step=%s&membership_id=%s',
							MS_Controller_Plugin::MENU_SLUG,
							self::STEP_OVERVIEW,
							$membership->id
						)
					),
				);
				$bread_crumbs['current'] = array(
					'title' => __( 'Content Types', MS_TEXT_DOMAIN ),
				);
				if ( ! $membership->private ) {
					$bread_crumbs['next'] = array(
						'title' => __( 'Payment', MS_TEXT_DOMAIN ),
					);
				}
				break;

			case self::STEP_SETUP_MS_TIERS:
				$bread_crumbs['prev'] = array(
					'title' => $membership->name,
					'url' => admin_url(
						sprintf(
							'admin.php?page=%s&step=%s&membership_id=%s',
							MS_Controller_Plugin::MENU_SLUG,
							self::STEP_OVERVIEW,
							$membership->id
						)
					),
				);
				$bread_crumbs['current'] = array(
					'title' => __( 'Membership Tiers', MS_TEXT_DOMAIN ),
				);
				$bread_crumbs['next'] = array(
					'title' => __( 'Payment', MS_TEXT_DOMAIN ),
				);
				break;

			case self::STEP_SETUP_DRIPPED:
				$bread_crumbs['prev'] = array(
					'title' => $membership->name,
					'url' => admin_url(
						sprintf(
							'admin.php?page=%s&step=%s&membership_id=%s',
							MS_Controller_Plugin::MENU_SLUG,
							self::STEP_OVERVIEW,
							$membership->id
						)
					),
				);
				$bread_crumbs['current'] = array(
					'title' => __( 'Dripped Content', MS_TEXT_DOMAIN ),
				);
				$bread_crumbs['next'] = array(
					'title' => __( 'Payment', MS_TEXT_DOMAIN ),
				);
				break;

			case self::STEP_SETUP_PAYMENT:
				$bread_crumbs['prev'] = array(
					'title' => $membership->name,
					'url' => admin_url(
						sprintf(
							'admin.php?page=%s&step=%s&membership_id=%s',
							MS_Controller_Plugin::MENU_SLUG,
							self::STEP_OVERVIEW,
							$membership->id
						)
					),
				);
				if ( MS_Model_Membership::TYPE_TIER == $membership->type ) {
					$bread_crumbs['prev1'] = array(
						'title' => __( 'Tier Levels', MS_TEXT_DOMAIN ),
						'url' => admin_url(
							sprintf(
								'admin.php?page=%s&step=%s&membership_id=%s',
								MS_Controller_Plugin::MENU_SLUG,
								self::STEP_SETUP_MS_TIERS,
								$membership->id
							)
						),
					);
				}
				elseif ( MS_Model_Membership::TYPE_CONTENT_TYPE == $membership->type ) {
					$bread_crumbs['prev1'] = array(
						'title' => __( 'Content Types', MS_TEXT_DOMAIN ),
						'url' => admin_url(
							sprintf(
								'admin.php?page=%s&step=%s&membership_id=%s',
								MS_Controller_Plugin::MENU_SLUG,
								self::STEP_SETUP_CONTENT_TYPES,
								$membership->id
							)
						),
					);
				}
				$bread_crumbs['current'] = array(
					'title' => __( 'Payment', MS_TEXT_DOMAIN ),
				);
				break;
		}

		// Add the "edit" param if it is set.
		if ( 1 == @$_GET['edit'] ) {
			foreach ( $bread_crumbs as $key => $data ) {
				if ( isset( $bread_crumbs[$key]['url'] ) ) {
					$bread_crumbs[$key]['url'] .= '&edit=1';
				}
			}
		}

		return apply_filters( 'ms_controller_membership_get_bread_crumbs', $bread_crumbs, $this );
	}

	/**
	 * Save membership general tab fields
	 *
	 * @since 1.0.0
	 *
	 * @param mixed[] $fields
	 */
	private function save_membership( $fields ) {

		$msg = MS_Helper_Membership::MEMBERSHIP_MSG_NOT_UPDATED;

		if ( $this->is_admin_user() ) {

			$membership = $this->load_membership();

			if ( is_array( $fields ) ) {
				$msg = 0;
				foreach ( $fields as $field => $value ) {
					try {
						$membership->$field = $value;
					}
					catch (Exception $e) {
						$msg = MS_Helper_Membership::MEMBERSHIP_MSG_PARTIALLY_UPDATED;
					}
				}
				$membership->save();
				if ( empty( $msg ) ) {
					$msg = MS_Helper_Membership::MEMBERSHIP_MSG_UPDATED;
				}
			}
		}

		return apply_filters( 'ms_controller_membership_save_membership_msg', $msg, $fields, $this );
	}

	/**
	 * Create child membership.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The membership name.
	 * @return MS_Model_Membership The child membership or null.
	 */
	private function create_child_membership( $name ) {

		$membership = null;

		if ( $this->is_admin_user() ) {
			$parent = $this->load_membership();
			if ( $parent->is_valid() && $parent->can_have_children() ) {
				$membership = $parent->create_child( $name );
			}
		}

		return apply_filters( 'ms_controller_membership_create_child_membership', $membership, $this );
	}

	/**
	 * Load Membership manager specific styles.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {

		switch ( $this->get_active_tab() ) {
			default:
				wp_enqueue_style( 'jquery-ui' );
				break;
		}

		wp_enqueue_style( 'ms_view_membership' );

		do_action( 'ms_controller_membership_enqueue_styles', $this );
	}

	/**
	 * Load Membership manager specific scripts.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {

		$data = array(
			'ms_init' => array(),
		);

		switch ( $this->get_step() ) {
			case self::STEP_CHOOSE_MS_TYPE:
				wp_enqueue_style( 'wp-pointer' );
				wp_enqueue_script( 'wp-pointer' );

				$ms_pointer = array(
					'hide_wizard_pointer' => MS_Model_Settings::get_setting( 'hide_wizard_pointer' ),
					'message' => sprintf(
						'<div class="ms-pointer-text">%s</div>',
						__( 'You can add / remove and modify your Protected Content at anytime here', MS_TEXT_DOMAIN )
					),
					'pointer_class' => 'ms-pointer-wrapper',
					'field' => 'hide_wizard_pointer',
					'value' => true,
					'action' => MS_Controller_Settings::AJAX_ACTION_UPDATE_SETTING,
					'nonce' => wp_create_nonce( MS_Controller_Settings::AJAX_ACTION_UPDATE_SETTING ),
				);

				$data['ms_private_types'] = MS_Model_Membership::get_private_eligible_types();
				$data['ms_pointer'] = $ms_pointer;
				$data['ms_init'][] = 'view_membership_choose_type';
				$data['initial_url'] = admin_url( 'admin.php?page=' . MS_Controller_Plugin::MENU_SLUG );
				break;

			case self::STEP_OVERVIEW:
				$data['ms_init'][] = 'view_membership_overview';
				break;

			case self::STEP_SETUP_PROTECTED_CONTENT:
			case self::STEP_ACCESSIBLE_CONTENT:
				switch ( $this->get_active_tab() ) {
					case 'category':
					case 'comment':
						wp_enqueue_script( 'ms-view-membership-setup-protected-content' );
						break;

					case 'url_group':
						wp_localize_script(
							'ms-view-membership-render-url-group',
							'ms',
							array(
								'valid_rule_msg' => __( 'Valid', MS_TEXT_DOMAIN ),
								'invalid_rule_msg' => __( 'Invalid', MS_TEXT_DOMAIN ),
								'empty_msg'	=> __( 'Add Page URLs to the group in case you want to test it against', MS_TEXT_DOMAIN ), //wording confusing
								'nothing_msg' => __( 'Enter an URL above to test against rules in the group', MS_TEXT_DOMAIN ),
							)
						);
						wp_enqueue_script( 'ms-view-membership-render-url-group' );
						break;

					default:
						wp_enqueue_script( 'jquery-ui-datepicker' );
						wp_enqueue_script( 'jquery-validate' );
						break;
				}
				break;

			case self::STEP_SETUP_CONTENT_TYPES:
			case self::STEP_SETUP_MS_TIERS:
				wp_enqueue_script( 'jquery-validate' );
				wp_enqueue_script( 'ms-view-membership-create-child' );
				break;

			case self::STEP_SETUP_PAYMENT:
				$data['ms_init'][] = 'view_membership_setup_payment';
				$data['ms_init'][] = 'view_settings_payment';

				add_thickbox();
				wp_enqueue_script( 'jquery-validate' );
				break;

			case self::STEP_SETUP_DRIPPED:
				wp_enqueue_script( 'ms-view-membership-setup-dripped' );
				break;
		}

		wp_localize_script( 'ms-admin', 'ms_data', $data );
		wp_enqueue_script( 'ms-admin' );

		do_action( 'ms_controller_membership_enqueue_scripts', $this );
	}

}