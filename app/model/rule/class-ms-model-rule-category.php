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
 * Membership Category Rule class.
 *
 * Persisted by Membership class.
 *
 * @since 1.0.0
 * @package Membership
 * @subpackage Model
 */
class MS_Model_Rule_Category extends MS_Model_Rule {

	/**
	 * Rule type.
	 *
	 * @since 1.0.0
	 *
	 * @var string $rule_type
	 */
	protected $rule_type = self::RULE_TYPE_CATEGORY;

	/**
	 * Set initial protection.
	 *
	 * @since 1.0.0
	 *
	 * @param MS_Model_Membership_Relationship $ms_relationship Optional. Not used.
	 */
	public function protect_content( $ms_relationship = false ) {
		parent::protect_content( $ms_relationship );

		$this->add_action( 'pre_get_posts', 'protect_posts', 98 );
		$this->add_filter( 'get_terms', 'protect_categories', 99, 2 );
	}

	/**
	 * Adds category__in filter for posts query to remove all posts which not
	 * belong to allowed categories.
	 *
	 * Related Filters:
	 * - pre_get_posts
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WP_Query object to filter.
	 */
	public function protect_posts( $wp_query ) {
		// Only verify permission if ruled by categories.
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_POST_BY_POST ) ) {

			if ( in_array( $wp_query->get( 'post_type' ), array( 'post', '' ) ) ) {
				$categories = array();
				$contents = $this->get_contents();
				$contents = get_categories( 'get=all' );
				$limited = false;

				foreach ( $contents as $content ) {
					if ( parent::has_access( $content->term_id ) ) {
						$categories[] = $content->term_id;
					} else {
						$limited = true;
					}
				}

				if ( $limited ) {
					$wp_query->query_vars['category__in'] = $categories;
				}
			}
		}

		do_action( 'ms_model_rule_category_protect_posts', $wp_query, $this );
	}

	/**
	 * Filters categories and removes all not accessible categories.
	 *
	 * @since 1.0.0
	 *
	 * @param array $terms The terms array.
	 * @param array $taxonomies The taxonomies array.
	 * @return array Filtered terms array.
	 */
	public function protect_categories( $terms, $taxonomies ) {
		$new_terms = array();

		// Bail - not fetching category taxonomy.
		if ( ! in_array( 'category', $taxonomies ) ) {
			return $terms;
		}

		if ( ! is_array( $terms ) ) {
			$terms = (array) $terms;
		}

		foreach ( $terms as $key => $term ) {
			if ( ! empty( $term->taxonomy ) && 'category' === $term->taxonomy ) {
				if ( parent::has_access( $term->term_id ) ) {
					$new_terms[ $key ] = $term;
				}
			} else {
				// Taxonomy is no category: Add it so custom taxonomies don't break.
				$new_terms[ $key ] = $term;
			}
		}

		return apply_filters(
			'ms_model_rule_category_protect_categories',
			$new_terms
		);
	}

	/**
	 * Verify access to the current category or post belonging to a catogory.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Optional. The current post_id.
	 * @return bool|null True if has access, false otherwise.
	 *     Null means: Rule not relevant for current page.
	 */
	public function has_access( $post_id = null ) {
		$has_access = null;

		// Only verify permissions if ruled by categories.
		if ( ! MS_Model_Addon::is_enabled( MS_Model_Addon::ADDON_POST_BY_POST ) ) {
			$taxonomies = get_object_taxonomies( get_post_type() );

			// Verify post access accordingly to category rules.
			if ( ! empty( $post_id )
				|| ( is_single() && in_array( 'category', $taxonomies ) )
			) {
				if ( empty( $post_id ) ) {
					$post_id = get_the_ID();
				}

				$categories = wp_get_post_categories( $post_id );
				foreach ( $categories as $category_id ) {
					$has_access = parent::has_access( $category_id );

					if ( $has_access ) {
						break;
					}
				}
			}
			// Category page.
			elseif ( is_category() ) {
				$has_access = parent::has_access( get_queried_object_id() );
			}
		}

		return apply_filters(
			'ms_model_rule_category_has_access',
			$has_access,
			$post_id,
			$this
		);
	}

	/**
	 * Get content to protect.
	 *
	 * @since 1.0.0
	 *
	 * @param string $args The default query args.
	 * @return array The content.
	 */
	public function get_contents( $args = null ) {
		$contents = get_categories( 'get=all' );

		foreach ( $contents as $key => $content ) {
			$content->id = $content->term_id;
			if ( ! $this->has_rule( $content->id ) ) {
				unset( $contents[ $key ] );
				continue;
			}

			$content->type = MS_Model_RULE::RULE_TYPE_CATEGORY;
			$content->access = $this->get_rule_value( $content->id );

			if ( array_key_exists( $content->id, $this->dripped ) ) {
				$content->delayed_period =
					$this->dripped[ $content->id ]['period_unit'] . ' ' .
					$this->dripped[ $content->id ]['period_type'];
				$content->dripped = $this->dripped[ $content->id ];
			}
			else {
				$content->delayed_period = '';
			}
		}
		if ( ! empty( $args['rule_status'] ) ) {
			$contents = $this->filter_content( $args['rule_status'], $contents );
		}
		return $contents;
	}

	/**
	 * Get category content array.
	 * Used to show content in html select.
	 *
	 * @since 1.0.0
	 * @return array of id => category name
	 */
	public function get_content_array() {
		$cont = array();
		$contents = get_categories( 'get=all' );

		foreach ( $contents as $key => $content ) {
			$cont[ $content->term_id ] = $content->name;
		}
		return apply_filters(
			'ms_model_rule_category_get_content_array',
			$cont
		);
	}

}