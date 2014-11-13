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
 * Membership Menu Rule class.
 *
 * Persisted by Membership class.
 *
 * @since 1.0.0
 *
 * @package Membership
 * @subpackage Model
 */
class MS_Model_Rule_Menu extends MS_Model_Rule {

	/**
	 * An array that holds all menu-IDs that are available for the current user.
	 * This is static, so it has correct values even when multiple memberships
	 * are evaluated.
	 *
	 * @var array
	 */
	static protected $allowed_items = array();

	/**
	 * Rule type.
	 *
	 * @since 1.0.0
	 *
	 * @var string $rule_type
	 */
	protected $rule_type = self::RULE_TYPE_MENU;

	/**
	 * Verify access to the current content.
	 *
	 * This rule will return NULL (not relevant), because the menus are
	 * protected via a wordpress hook instead of protecting the current page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id The content id to verify access.
	 * @return bool|null True if has access, false otherwise.
	 *     Null means: Rule not relevant for current page.
	 */
	public function has_access( $id = null ) {
		return apply_filters(
			'ms_model_rule_menu_has_access',
			null,
			$id,
			$this
		);
	}

	/**
	 * Set initial protection.
	 *
	 * @since 1.0.0
	 *
	 * @param MS_Model_Membership_Relationship $ms_relationship Optional. The membership relationship.
	 */
	public function protect_content( $ms_relationship = false ) {
		parent::protect_content( $ms_relationship );

		$this->add_filter( 'wp_setup_nav_menu_item', 'prepare_menuitem', 10, 3 );
		$this->add_filter( 'wp_get_nav_menu_items', 'protect_menuitems', 10, 3 );
	}

	/**
	 * Checks if the specified menu-ID is allowed by this rule.
	 *
	 * @since  1.0.4.3
	 *
	 * @param  object $item The menu item object.
	 * @return bool
	 */
	protected function can_access_menu( $item ) {
		$result = false;

		if ( parent::has_access( $item->ID ) ) {
			$result = true;
		} else if ( ! empty( $item->post_parent ) ) {
			$parent = get_post( $item->post_parent );
			$result = $this->can_access_menu( $parent );
		}

		return $result;
	}

	/**
	 * Set the protection flag for each menu item.
	 *
	 * This function is called before function protect_menuitems() below.
	 * Here we evaluate each menu item by itself to see if the user has access
	 * to the menu item and collect all accessible menu items in a static/shared
	 * array so we have correct information when evaluating multiple memberships.
	 *
	 * Relevant Action Hooks:
	 * - wp_setup_nav_menu_item
	 *
	 * @since 1.0.4.3
	 *
	 * @param array $item A single menu item.
	 * @param mixed $args The menu select args.
	 */
	public function prepare_menuitem( $item ) {
		if ( ! empty( $item ) ) {
			if ( $this->can_access_menu( $item ) ) {
				self::$allowed_items[$item->ID] = $item->ID;
			}
		}

		return apply_filters(
			'ms_model_rule_menu_prepare_menuitems',
			$item,
			$this
		);
	}

	/**
	 * Remove menu items that are protected.
	 *
	 * Menu-Item protection is split into two steps to ensure correct
	 * menu-visibility when users are members of multiple memberships.
	 * http://premium.wpmudev.org/forums/topic/multiple-membership-types-defaults-to-less-access-protected-content
	 *
	 * Relevant Action Hooks:
	 * - wp_get_nav_menu_items
	 *
	 * @since 1.0.0
	 *
	 * @param array $items The menu items.
	 * @param object $menu The menu object.
	 * @param mixed $args The menu select args.
	 */
	public function protect_menuitems( $items, $menu, $args ) {
		if ( ! empty( $items ) ) {
			foreach ( $items as $key => $item ) {
				if ( ! isset( self::$allowed_items[ $item->ID ] ) ) {
					unset( $items[ $key ] );
				}
			}
		}

		return apply_filters(
			'ms_model_rule_menu_protect_menuitems',
			$items,
			$menu,
			$args,
			$this
		);
	}

	/**
	 * Reset the rule value data.
	 *
	 * @since 1.0.0
	 * @param $menu_id The menu_id to reset children menu item rules.
	 * @return array The reset rule value.
	 */
	public function reset_menu_rule_values( $menu_id ) {
		$items = wp_get_nav_menu_items( $menu_id );

		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				unset( $this->rule_value[ $item->ID ] );
			}
		}

		$this->rule_value = apply_filters(
			'ms_model_rule_menu_reset_menu_rule_values',
			$this->rule_value,
			$this
		);
	}

	/**
	 * Get content to protect.
	 *
	 * @since 1.0.0
	 * @param $args The query post args
	 *     @see @link http://codex.wordpress.org/Class_Reference/WP_Query
	 * @return array The contents array.
	 */
	public function get_contents( $args = null ) {
		$contents = array();

		if ( ! empty( $args['protected_content'] ) ) {
			$menus = $this->get_menu_array();
			foreach ( $menus as $menu_id => $menu ) {
				//recursive call.
				$contents = array_merge(
					$contents,
					$this->get_contents( array( 'menu_id' => $menu_id ) )
				);
			}
			return $contents;
		}
		elseif ( ! empty( $args['menu_id'] ) ) {
			$menu_id = $args['menu_id'];
			$items = wp_get_nav_menu_items( $menu_id );

			if ( ! empty( $items ) ) {
				foreach ( $items as $item ) {
					$item_id = $item->ID;
					$contents[ $item_id ] = $item;
					$contents[ $item_id ]->id = $item_id;
					$contents[ $item_id ]->title = esc_html( $item->title );
					$contents[ $item_id ]->name = esc_html( $item->title );
					$contents[ $item_id ]->parent_id = $menu_id;
					$contents[ $item_id ]->type = $this->rule_type;
					$contents[ $item_id ]->access = $this->get_rule_value( $contents[ $item_id ]->id );
				}
			}
		}

		// If not visitor membership, just show protected content
		if ( ! $this->rule_value_invert ) {
			$contents = array_intersect_key( $contents,  $this->rule_value );
		}

		if ( ! empty( $args['rule_status'] ) ) {
			$contents = $this->filter_content( $args['rule_status'], $contents );
		}

		return apply_filters(
			'ms_model_rule_menu_get_contents',
			$contents,
			$args,
			$this
		);
	}

	/**
	 * Get post content array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $array The query args. @see self::get_query_args()
	 * @return array {
	 *     @type int $key The content ID.
	 *     @type string $value The content title.
	 * }
	 */
	public function get_options_array( $args = array() ) {
		$cont = array();
		$contents = $this->get_contents( $args );

		foreach ( $contents as $content ) {
			$cont[ $content->id ] = $content->name;
		}

		return apply_filters(
			'ms_model_rule_menu_get_content_array',
			$cont,
			$this
		);
	}

	/**
	 * Get menu array.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *      @type string $menu_id The menu id.
	 *      @type string $name The menu name.
	 * }
	 */
	public function get_menu_array() {
		$contents = array( __( 'No menus found.', MS_TEXT_DOMAIN ) );
		$navs = wp_get_nav_menus( array( 'orderby' => 'name' ) );

		if ( ! empty( $navs ) ) {
			$contents = array();
			foreach ( $navs as $nav ) {
				$contents[ $nav->term_id ] = esc_html( $nav->name );
			}
		}

		return apply_filters(
			'ms_model_rule_menu_get_menu_array',
			$contents,
			$this
		);
	}

}