<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Post_Manager {
	const POST_TYPE = 'post';

	private $settings;
	private $builder;

	public function __construct( $settings, YY_DMM_Auto_Post_Post_Builder $builder ) {
		$this->settings = $settings;
		$this->builder  = $builder;
	}

	public function is_posted( $content_id ) {
		return (bool) $this->find_existing_post_id( $content_id );
	}

	public function create_post( $item, $content, $featured_media_id ) {
		$content_id = $this->builder->get_content_id( $item );
		if ( '' === $content_id ) {
			return new WP_Error( 'yy_dmm_post_missing_content_id', 'content_idがないため投稿できません。' );
		}

		$post_data = array(
			'post_title'   => $this->builder->get_wp_title( $item ),
			'post_content' => $content,
			'post_status'  => $this->settings['post_status'],
		);
		$post_dates = $this->get_release_post_dates( $item );
		if ( $post_dates ) {
			$post_data = array_merge( $post_data, $post_dates );
		}

		$existing_post_id = $this->find_existing_post_id( $content_id );
		if ( $existing_post_id ) {
			$post_data['ID'] = $existing_post_id;
			$post_id = wp_update_post( $post_data, true );
		} else {
			$post_data['post_type'] = self::POST_TYPE;
			$post_data['post_name'] = sanitize_title( $content_id );
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->save_post_data( $post_id, $item, $content_id, $featured_media_id );

		return absint( $post_id );
	}

	public function prepare_product_info_term_links( $item ) {
		$links = array();
		foreach ( array( 'genre', 'maker', 'label', 'director' ) as $key ) {
			$links[ $key ] = $this->prepare_iteminfo_term_links( $item, $key );
		}

		return $links;
	}

	private function get_release_post_dates( $item ) {
		if ( empty( $this->settings['sync_post_date_with_release_date'] ) ) {
			return array();
		}

		$value = trim( (string) ( $item['date'] ?? '' ) );
		if ( ! preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $matches ) ) {
			return array();
		}

		try {
			$date = new DateTimeImmutable(
				sprintf( '%04d-%02d-%02d 00:00:00', $matches[1], $matches[2], $matches[3] ),
				wp_timezone()
			);
		} catch ( Exception $e ) {
			return array();
		}

		$post_date = $date->format( 'Y-m-d H:i:s' );

		return array(
			'post_date'     => $post_date,
			'post_date_gmt' => get_gmt_from_date( $post_date ),
		);
	}

	private function save_post_data( $post_id, $item, $content_id, $featured_media_id ) {
		update_post_meta( $post_id, '_yy_dmm_content_id', $content_id );
		update_post_meta( $post_id, '_yy_dmm_affiliate_url', esc_url_raw( $item['affiliateURL'] ?? '' ) );
		update_post_meta( $post_id, '_yy_dmm_product_url', esc_url_raw( $item['URL'] ?? '' ) );

		if ( $featured_media_id ) {
			$featured_media_id = absint( $featured_media_id );
			set_post_thumbnail( $post_id, $featured_media_id );
			update_post_meta( $post_id, '_thumbnail_id', $featured_media_id );
		}

		$this->assign_categories( $post_id, $item );
		$this->assign_tags( $post_id, $item );
	}

	private function find_existing_post_id( $content_id ) {
		$content_id = sanitize_key( (string) $content_id );
		if ( '' === $content_id ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_yy_dmm_content_id',
				'meta_value'     => $content_id,
			)
		);

		if ( ! empty( $posts ) ) {
			return absint( $posts[0] );
		}

		$posts = get_posts(
			array(
				'name'           => sanitize_title( $content_id ),
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? absint( $posts[0] ) : 0;
	}

	private function assign_categories( $post_id, $item ) {
		if ( ! taxonomy_exists( 'category' ) ) {
			return;
		}

		$ids = array();
		foreach ( $this->enabled_iteminfo_keys( 'category_iteminfo_keys' ) as $key ) {
			$parent = $this->get_iteminfo_parent_category_id( $key );
			$entries = YY_DMM_Auto_Post_Post_Builder::extract_iteminfo_entries( $item, $key );
			foreach ( $entries as $entry ) {
				$term = $this->build_term_from_iteminfo_entry( $entry );
				if ( empty( $term['name'] ) ) {
					continue;
				}

				$term_id = $this->get_or_create_category( $term['name'], $term['slug'], $parent );
				if ( $term_id ) {
					$ids[] = $term_id;
				}
			}
		}

		wp_set_post_terms( $post_id, array_values( array_unique( $ids ) ), 'category' );
	}

	private function prepare_iteminfo_term_links( $item, $key ) {
		$links = array();
		$entries = YY_DMM_Auto_Post_Post_Builder::extract_iteminfo_entries( $item, $key );
		foreach ( $entries as $entry ) {
			$term = $this->build_term_from_iteminfo_entry( $entry );
			if ( empty( $term['name'] ) ) {
				continue;
			}

			$url = '';
			if ( in_array( $key, $this->enabled_iteminfo_keys( 'category_iteminfo_keys' ), true ) && taxonomy_exists( 'category' ) ) {
				$parent = $this->get_iteminfo_parent_category_id( $key );
				$term_id = $this->get_or_create_category( $term['name'], $term['slug'], $parent );
				$url = $this->get_term_url( $term_id, 'category' );
			}

			if ( '' === $url && in_array( $key, $this->enabled_iteminfo_keys( 'tag_iteminfo_keys' ), true ) && taxonomy_exists( 'post_tag' ) ) {
				$term_id = $this->get_or_create_tag( $term['name'], $term['slug'] );
				$url = $this->get_term_url( $term_id, 'post_tag' );
			}

			$links[] = array(
				'name' => $term['name'],
				'url'  => $url,
			);
		}

		return $links;
	}

	private function get_term_url( $term_id, $taxonomy ) {
		$term_id = absint( $term_id );
		if ( ! $term_id ) {
			return '';
		}

		$url = get_term_link( $term_id, $taxonomy );
		return is_wp_error( $url ) ? '' : esc_url_raw( $url );
	}

	private function get_iteminfo_parent_category_id( $key ) {
		$base_parent = absint( $this->settings['parent_category_id'] ?? 0 );
		if ( empty( $this->settings['create_parent_categories'] ) ) {
			return $base_parent;
		}

		$options = YY_DMM_Auto_Post_Settings::taxonomy_iteminfo_options();
		$name = isset( $options[ $key ] ) ? $options[ $key ] : $key;
		$slug = sanitize_title( $key );
		$term_id = $this->get_or_create_category( $name, $slug, $base_parent );

		return $term_id ? $term_id : $base_parent;
	}

	private function get_or_create_category( $name, $slug, $parent = 0 ) {
		$parent = absint( $parent );
		$term_id = $this->find_category_id( $name, $slug, $parent );
		if ( $term_id ) {
			return $term_id;
		}

		if ( empty( $this->settings['create_categories'] ) ) {
			return 0;
		}

		$result = wp_insert_term(
			$name,
			'category',
			array(
				'slug'   => $slug,
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['term_id'] ) ) {
				$existing_term_id = absint( $data['term_id'] );
				$existing_term = get_term( $existing_term_id, 'category' );
				if ( $existing_term && ! is_wp_error( $existing_term ) && absint( $existing_term->parent ) === $parent ) {
					return $existing_term_id;
				}

				return $this->insert_category_with_fallback_slug( $name, $slug, $parent );
			}

			if ( is_numeric( $data ) ) {
				$existing_term_id = absint( $data );
				$existing_term = get_term( $existing_term_id, 'category' );
				if ( $existing_term && ! is_wp_error( $existing_term ) && absint( $existing_term->parent ) === $parent ) {
					return $existing_term_id;
				}

				return $this->insert_category_with_fallback_slug( $name, $slug, $parent );
			}

			return 0;
		}

		return absint( $result['term_id'] ?? 0 );
	}

	private function insert_category_with_fallback_slug( $name, $slug, $parent ) {
		$fallback_slug = sanitize_title( $slug . '-' . absint( $parent ) );
		if ( '' === $fallback_slug || $fallback_slug === $slug ) {
			return 0;
		}

		$result = wp_insert_term(
			$name,
			'category',
			array(
				'slug'   => $fallback_slug,
				'parent' => absint( $parent ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['term_id'] ) ) {
				return absint( $data['term_id'] );
			}

			return is_numeric( $data ) ? absint( $data ) : 0;
		}

		return absint( $result['term_id'] ?? 0 );
	}

	private function find_category_id( $name, $slug, $parent ) {
		foreach ( array( 'slug' => $slug, 'name' => $name ) as $field => $value ) {
			$args = array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'parent'     => absint( $parent ),
				'number'     => 1,
				'fields'     => 'ids',
				$field       => $value,
			);

			$terms = get_terms( $args );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				return absint( $terms[0] );
			}
		}

		return 0;
	}

	private function assign_tags( $post_id, $item ) {
		if ( ! taxonomy_exists( 'post_tag' ) ) {
			return;
		}

		$ids = array();
		foreach ( $this->enabled_iteminfo_keys( 'tag_iteminfo_keys' ) as $key ) {
			$entries = YY_DMM_Auto_Post_Post_Builder::extract_iteminfo_entries( $item, $key );
			foreach ( $entries as $entry ) {
				$term = $this->build_term_from_iteminfo_entry( $entry );
				if ( empty( $term['name'] ) ) {
					continue;
				}

				$term_id = $this->get_or_create_tag( $term['name'], $term['slug'] );
				if ( $term_id ) {
					$ids[] = $term_id;
				}
			}
		}

		wp_set_post_terms( $post_id, array_values( array_unique( $ids ) ), 'post_tag' );
	}

	private function get_or_create_tag( $name, $slug ) {
		$term = get_term_by( 'slug', $slug, 'post_tag' );
		if ( $term ) {
			return absint( $term->term_id );
		}

		$term = get_term_by( 'name', $name, 'post_tag' );
		if ( $term ) {
			return absint( $term->term_id );
		}

		if ( empty( $this->settings['create_tags'] ) ) {
			return 0;
		}

		$result = wp_insert_term(
			$name,
			'post_tag',
			array(
				'slug' => $slug,
			)
		);
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['term_id'] ) ) {
				return absint( $data['term_id'] );
			}

			return is_numeric( $data ) ? absint( $data ) : 0;
		}

		return absint( $result['term_id'] ?? 0 );
	}

	private function enabled_iteminfo_keys( $setting_key ) {
		$values = isset( $this->settings[ $setting_key ] ) && is_array( $this->settings[ $setting_key ] ) ? $this->settings[ $setting_key ] : array();
		$keys = array();
		foreach ( YY_DMM_Auto_Post_Settings::taxonomy_iteminfo_options() as $key => $label ) {
			if ( ! empty( $values[ $key ] ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	private function build_term_from_iteminfo_entry( $entry ) {
		$name = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
		if ( '' === $name ) {
			return array(
				'name' => '',
				'slug' => '',
			);
		}

		$slug_source = 'name' === ( $this->settings['term_slug_source'] ?? 'id' ) ? 'name' : 'id';
		$slug_value = 'id' === $slug_source && ! empty( $entry['id'] ) ? (string) $entry['id'] : $name;

		return array(
			'name' => $name,
			'slug' => sanitize_title( $slug_value ),
		);
	}
}
