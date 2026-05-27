<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Plugin {
	private static $instance = null;
	private $admin;
	private $cron;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		YY_DMM_Auto_Post_Settings::ensure_defaults();
		YY_DMM_Auto_Post_Cron::reschedule();
	}

	public static function deactivate() {
		YY_DMM_Auto_Post_Cron::clear();
	}

	public function run() {
		load_plugin_textdomain( 'yy-dmm-fanza-auto-post', false, dirname( YY_DMM_AUTO_POST_BASENAME ) . '/languages' );

		YY_DMM_Auto_Post_Sample_Movie::hooks();

		$this->cron = new YY_DMM_Auto_Post_Cron( $this );
		$this->cron->hooks();

		if ( is_admin() ) {
			$this->admin = new YY_DMM_Auto_Post_Admin( $this );
			$this->admin->hooks();
		}
	}

	public function run_import( $type = 'manual' ) {
		$result = array(
			'fetched'     => 0,
			'posted'      => 0,
			'created'     => 0,
			'updated'     => 0,
			'skipped'     => 0,
			'duplicate_skipped' => 0,
			'errors'      => array(),
			'started_at'  => current_time( 'mysql' ),
			'finished_at' => '',
		);

		$settings = YY_DMM_Auto_Post_Settings::get();
		$api      = new YY_DMM_Auto_Post_API_Client( $settings );
		$items    = $api->fetch_items();

		if ( is_wp_error( $items ) ) {
			$result['errors'][] = $items->get_error_message();
			$result['finished_at'] = current_time( 'mysql' );
			( new YY_DMM_Auto_Post_Logger() )->add( $type, $result );
			return $result;
		}

		$result['fetched'] = count( $items );
		$builder = new YY_DMM_Auto_Post_Post_Builder( $settings );
		$manager = new YY_DMM_Auto_Post_Post_Manager( $settings, $builder );
		$scraper = new YY_DMM_Auto_Post_Scraper();
		$media   = new YY_DMM_Auto_Post_Media( $settings );
		$max     = max( 1, absint( $settings['max_posts'] ) );

		foreach ( $items as $item ) {
			if ( $result['posted'] >= $max ) {
				break;
			}

			if ( ! is_array( $item ) ) {
				$result['skipped']++;
				continue;
			}

			$content_id = $builder->get_content_id( $item );
			if ( '' === $content_id ) {
				$result['skipped']++;
				$result['errors'][] = 'content_idがない商品をスキップしました。';
				continue;
			}

			$is_existing_post = $manager->is_posted( $content_id );
			if ( ! empty( $settings['prevent_duplicates'] ) && $is_existing_post ) {
				$result['skipped']++;
				$result['duplicate_skipped']++;
				continue;
			}

			$description = '';
			$scrape = $scraper->fetch_description( $content_id );
			if ( is_wp_error( $scrape ) ) {
				$result['errors'][] = sprintf( '%s: %s', $content_id, $scrape->get_error_message() );
			} else {
				$description = (string) $scrape;
			}

			$featured_media_id = 0;
			$attachment_id = $media->get_or_sideload_featured( $item );
			if ( is_wp_error( $attachment_id ) ) {
				$result['errors'][] = sprintf( '%s: %s', $content_id, $attachment_id->get_error_message() );
			} else {
				$featured_media_id = absint( $attachment_id );
			}

			$product_info_term_links = $manager->prepare_product_info_term_links( $item );
			$content = $builder->build_content( $item, $description, $product_info_term_links );
			$post_id = $manager->create_post( $item, $content, $featured_media_id );
			if ( is_wp_error( $post_id ) ) {
				$result['errors'][] = sprintf( '%s: %s', $content_id, $post_id->get_error_message() );
				continue;
			}

			$result['posted']++;
			if ( $is_existing_post ) {
				$result['updated']++;
			} else {
				$result['created']++;
			}
		}

		$result['finished_at'] = current_time( 'mysql' );
		( new YY_DMM_Auto_Post_Logger() )->add( $type, $result );

		return $result;
	}
}
