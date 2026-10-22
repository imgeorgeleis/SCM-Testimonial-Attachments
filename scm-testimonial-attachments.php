<?php
/**
 * Plugin Name: SCM Testimonial Attachments
 * Description: Keeps testimonial attachment terms synchronized with content and provides a grouped testimonial selector.
 * Version: 1.2.0
 * Author: Second Click Media
 * License: GPL-2.0-or-later
 * Text Domain: scm-testimonial-attachments
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SCM_Testimonial_Attachments {
	const VERSION               = '1.2.0';
	const TESTIMONIAL_POST_TYPE = 'testimonial';
	const PEOPLE_POST_TYPE      = 'people';
	const LOCATION_POST_TYPE    = 'location';
	const TAXONOMY              = 'testimonial_attachment';
	const SOURCE_ID_META        = '_scm_attachment_source_id';
	const SOURCE_TYPE_META      = '_scm_attachment_source_type';
	const SOURCE_TAXONOMY_META  = '_scm_attachment_source_taxonomy';
	const SYNC_VERSION_OPTION   = 'scm_testimonial_attachments_sync_version';
	const CRON_HOOK             = 'scm_testimonial_attachments_daily_sync';

	private $creating_managed_term = false;

	private $service_categories = array(
		'General', 'State Planning', 'Divorce', 'High-Asset Divorce',
		'Child Custody', 'Property Division', 'Spousal Support',
		'Prenuptial Agreements', 'Domestic Violence', 'Wills and Trusts',
		'Power of Attorney',
	);

	public function __construct() {
		add_action( 'init', array( $this, 'maybe_initial_sync' ), 100 );
		add_action( self::CRON_HOOK, array( $this, 'sync_all_attachment_terms' ) );
		add_action( 'save_post', array( $this, 'sync_terms_for_saved_post' ), 999, 3 );
		add_action( 'before_delete_post', array( $this, 'delete_terms_for_source_post' ), 20 );
		add_action( 'add_meta_boxes_' . self::TESTIMONIAL_POST_TYPE, array( $this, 'register_testimonial_metabox' ), 20 );
		add_action( 'save_post_' . self::TESTIMONIAL_POST_TYPE, array( $this, 'save_testimonial_attachments' ), 9999, 3 );
		add_filter( 'pre_insert_term', array( $this, 'prevent_manual_term_creation' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'hide_taxonomy_admin_menu' ), 999 );
	}

	public static function activate() {
		delete_option( self::SYNC_VERSION_OPTION );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	private function taxonomy_available() {
		return taxonomy_exists( self::TAXONOMY );
	}

	private function source_types() {
		return array(
			self::PEOPLE_POST_TYPE   => 'people',
			'page'                   => 'page',
			'post'                   => 'post',
			self::LOCATION_POST_TYPE => 'location',
		);
	}

	private function managed_slug( $type, $id ) {
		return sanitize_title( 'scm-' . $type . '-' . absint( $id ) );
	}

	/** Run the initial bulk sync once per version, after ACF registrations are ready. */
	public function maybe_initial_sync() {
		if ( ! $this->taxonomy_available() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
		if ( get_option( self::SYNC_VERSION_OPTION ) === self::VERSION ) {
			return;
		}
		$this->sync_all_attachment_terms();
		update_option( self::SYNC_VERSION_OPTION, self::VERSION, false );
	}

	public function sync_all_attachment_terms() {
		if ( ! $this->taxonomy_available() ) {
			return;
		}
		foreach ( $this->source_types() as $post_type => $source_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			$ids = get_posts( array(
				'post_type' => $post_type, 'post_status' => 'publish',
				'posts_per_page' => -1, 'fields' => 'ids',
				'orderby' => 'ID', 'order' => 'ASC',
				'no_found_rows' => true, 'suppress_filters' => false,
			) );
			foreach ( $ids as $id ) {
				$this->sync_source_post( $id, $source_type );
			}
		}
		$this->sync_default_service_terms();
		$this->remove_orphaned_attachment_terms();
	}

	public function sync_terms_for_saved_post( $post_id, $post, $update ) {
		if ( ! $this->taxonomy_available() || ! $post instanceof WP_Post ||
			wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$types = $this->source_types();
		if ( ! isset( $types[ $post->post_type ] ) ) {
			return;
		}
		if ( 'publish' === $post->post_status ) {
			$this->sync_source_post( $post_id, $types[ $post->post_type ] );
		} else {
			$this->delete_source_term( $post_id, $types[ $post->post_type ] );
		}
	}

	private function sync_source_post( $id, $type ) {
		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		$name = get_the_title( $id );
		if ( '' !== trim( $name ) ) {
			$this->upsert_source_term( $id, $type, $name );
		}
	}

	/** Lookup by source identity, not by display name (names need not be unique). */
	private function find_source_terms( $id, $type ) {
		if ( ! $this->taxonomy_available() ) {
			return array();
		}
		$terms = get_terms( array(
			'taxonomy' => self::TAXONOMY, 'hide_empty' => false,
			'meta_query' => array(
				array( 'key' => self::SOURCE_ID_META, 'value' => (string) absint( $id ) ),
				array( 'key' => self::SOURCE_TYPE_META, 'value' => $type ),
			),
		) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	private function upsert_source_term( $source_id, $type, $name, $source_taxonomy = '' ) {
		if ( ! $this->taxonomy_available() ) {
			return;
		}
		$slug = $this->managed_slug( $type, $source_id );
		$terms = $this->find_source_terms( $source_id, $type );
		if ( $terms ) {
			$term = reset( $terms );
			if ( $term->name !== $name || $term->slug !== $slug ) {
				wp_update_term( $term->term_id, self::TAXONOMY, array( 'name' => $name, 'slug' => $slug ) );
			}
			return;
		}
		// Check for existing managed slug rather than claim an unrelated term.
		$collision = get_term_by( 'slug', $slug, self::TAXONOMY );
		if ( $collision ) {
			return;
		}
		$this->creating_managed_term = true;
		try {
			$inserted = wp_insert_term( $name, self::TAXONOMY, array( 'slug' => $slug ) );
		} finally {
			$this->creating_managed_term = false;
		}
		if ( is_wp_error( $inserted ) ) {
			return;
		}
		$term_id = absint( $inserted['term_id'] );
		update_term_meta( $term_id, self::SOURCE_ID_META, absint( $source_id ) );
		update_term_meta( $term_id, self::SOURCE_TYPE_META, $type );
		update_term_meta( $term_id, self::SOURCE_TAXONOMY_META, $source_taxonomy );
	}

	/**
	 * Default services exist independently of WordPress categories and source posts.
	 * Upgrade previously category-backed service terms in place to keep testimonial
	 * relationships and existing term IDs intact.
	 */
	private function sync_default_service_terms() {
		foreach ( $this->service_categories as $name ) {
			$slug = 'scm-default-service-' . sanitize_title( $name );
			$term = get_term_by( 'slug', $slug, self::TAXONOMY );
			if ( ! $term ) {
				$legacy = get_terms( array(
					'taxonomy' => self::TAXONOMY,
					'hide_empty' => false,
					'name' => $name,
					'number' => 0,
				) );
				if ( ! is_wp_error( $legacy ) ) {
					foreach ( $legacy as $candidate ) {
						$type = get_term_meta( $candidate->term_id, self::SOURCE_TYPE_META, true );
						if ( 'service' === $type || 'service_default' === $type ) {
							$term = $candidate;
							break;
						}
					}
				}
			}
			if ( ! $term ) {
				$this->creating_managed_term = true;
				try {
					$created = wp_insert_term( $name, self::TAXONOMY, array( 'slug' => $slug ) );
				} finally {
					$this->creating_managed_term = false;
				}
				if ( is_wp_error( $created ) ) {
					// WordPress disallows a second term with the same name on some
					// taxonomies; keep a distinct label for the permanent default.
					$this->creating_managed_term = true;
					try {
						$created = wp_insert_term( $name . ' (Service)', self::TAXONOMY, array( 'slug' => $slug ) );
					} finally {
							$this->creating_managed_term = false;
					}
				}
				if ( is_wp_error( $created ) ) {
						continue;
					}
				$term = get_term( $created['term_id'], self::TAXONOMY );
			}
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			if ( $term->slug !== $slug ) {
				wp_update_term( $term->term_id, self::TAXONOMY, array( 'slug' => $slug ) );
			}
			delete_term_meta( $term->term_id, self::SOURCE_ID_META );
			update_term_meta( $term->term_id, self::SOURCE_TYPE_META, 'service_default' );
			update_term_meta( $term->term_id, self::SOURCE_TAXONOMY_META, '' );
		}
	}

	public function delete_terms_for_source_post( $post_id ) {
		$post = get_post( $post_id );
		$types = $this->source_types();
		if ( $post && isset( $types[ $post->post_type ] ) ) {
			$this->delete_source_term( $post_id, $types[ $post->post_type ] );
		}
	}

	private function delete_source_term( $id, $type ) {
		foreach ( $this->find_source_terms( $id, $type ) as $term ) {
			wp_delete_term( $term->term_id, self::TAXONOMY );
		}
	}

	private function remove_orphaned_attachment_terms() {
		$terms = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return;
		}
		$types = array_flip( $this->source_types() );
		foreach ( $terms as $term ) {
			$id   = absint( get_term_meta( $term->term_id, self::SOURCE_ID_META, true ) );
			$type = get_term_meta( $term->term_id, self::SOURCE_TYPE_META, true );
			// Preserve unrelated / legacy terms that have not been marked as managed.
			if ( 'service_default' === $type || ! $id || ! $type ) {
				continue;
			}
			if ( 'service' === $type ) {
				$category = get_term( $id, 'category' );
				$valid = $category instanceof WP_Term && in_array( $category->name, $this->service_categories, true );
			} else {
				$post = get_post( $id );
				$valid = isset( $types[ $type ] ) && $post && $post->post_type === $types[ $type ] && 'publish' === $post->post_status;
			}
			if ( ! $valid ) {
				wp_delete_term( $term->term_id, self::TAXONOMY );
			}
		}
	}

	public function register_testimonial_metabox( $post ) {
		if ( ! $this->taxonomy_available() || ! is_object_in_taxonomy( self::TESTIMONIAL_POST_TYPE, self::TAXONOMY ) ) {
			return;
		}
		// Replace default WordPress taxonomy UI to avoid two conflicting selectors.
		remove_meta_box( 'tagsdiv-' . self::TAXONOMY, self::TESTIMONIAL_POST_TYPE, 'side' );
		remove_meta_box( self::TAXONOMY . 'div', self::TESTIMONIAL_POST_TYPE, 'side' );
		add_meta_box( 'scm_testimonial_attachments', __( 'Testimonial Attachments', 'scm-testimonial-attachments' ),
			array( $this, 'render_testimonial_metabox' ), self::TESTIMONIAL_POST_TYPE, 'normal', 'default' );
	}

	public function render_testimonial_metabox( $post ) {
		wp_nonce_field( 'scm_save_testimonial_attachments', 'scm_testimonial_attachments_nonce' );
		$current = wp_get_post_terms( $post->ID, self::TAXONOMY, array( 'fields' => 'ids' ) );
		$current = is_wp_error( $current ) ? array() : array_map( 'intval', $current );
		$groups = array( 'People' => 'people', 'Pages' => 'page', 'Posts' => 'post', 'Locations' => 'location', 'Services' => 'service_default' );
		echo '<p>' . esc_html__( 'Select the website content this testimonial should appear with.', 'scm-testimonial-attachments' ) . '</p>';
		foreach ( $groups as $label => $type ) {
			$terms = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false,
				'meta_query' => array( array( 'key' => self::SOURCE_TYPE_META, 'value' => $type ) ),
				'orderby' => 'name', 'order' => 'ASC' ) );
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			echo '<fieldset style="margin:14px 0"><legend><strong>' . esc_html( $label ) . '</strong></legend>';
			foreach ( $terms as $term ) {
				echo '<label style="display:block;margin:5px 0"><input type="checkbox" name="scm_testimonial_attachments[]" value="' . esc_attr( $term->term_id ) . '" ' . checked( in_array( (int) $term->term_id, $current, true ), true, false ) . '> ' . esc_html( $term->name ) . '</label>';
			}
			echo '</fieldset>';
		}
	}

	public function save_testimonial_attachments( $post_id, $post, $update ) {
		if ( ! $post instanceof WP_Post || $post->post_type !== self::TESTIMONIAL_POST_TYPE ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ||
			! $this->taxonomy_available() || ! is_object_in_taxonomy( self::TESTIMONIAL_POST_TYPE, self::TAXONOMY ) ||
			! isset( $_POST['scm_testimonial_attachments_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scm_testimonial_attachments_nonce'] ) ), 'scm_save_testimonial_attachments' ) ||
			! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$submitted = isset( $_POST['scm_testimonial_attachments'] ) ? (array) wp_unslash( $_POST['scm_testimonial_attachments'] ) : array();
		$valid = array();
		foreach ( array_unique( array_filter( array_map( 'absint', $submitted ) ) ) as $term_id ) {
			$term = get_term( $term_id, self::TAXONOMY );
			if ( $term instanceof WP_Term && in_array( get_term_meta( $term_id, self::SOURCE_TYPE_META, true ),
				array( 'people', 'page', 'post', 'location', 'service_default' ), true ) ) {
				$valid[] = $term_id;
			}
		}
		$result = wp_set_object_terms( $post_id, $valid, self::TAXONOMY, false );
		if ( is_wp_error( $result ) ) {
			error_log( 'SCM Testimonial Attachments save error: ' . $result->get_error_message() );
		}
	}

	public function prevent_manual_term_creation( $term, $taxonomy ) {
		if ( self::TAXONOMY === $taxonomy && ! $this->creating_managed_term ) {
			return new WP_Error( 'scm_manual_terms_disabled', __( 'Attachment terms are managed automatically.', 'scm-testimonial-attachments' ) );
		}
		return $term;
	}

	public function hide_taxonomy_admin_menu() {
		if ( ! $this->taxonomy_available() ) {
			return;
		}
		$taxonomy = get_taxonomy( self::TAXONOMY );
		if ( ! $taxonomy ) {
			return;
		}
		foreach ( $taxonomy->object_type as $post_type ) {
			$parent = 'post' === $post_type ? 'edit.php' : 'edit.php?post_type=' . $post_type;
			remove_submenu_page( $parent, 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=' . $post_type );
		}
	}
}

register_activation_hook( __FILE__, array( 'SCM_Testimonial_Attachments', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SCM_Testimonial_Attachments', 'deactivate' ) );
new SCM_Testimonial_Attachments();
