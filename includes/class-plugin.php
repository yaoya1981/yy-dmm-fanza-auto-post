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
		$this->maybe_disable_wp_cron_for_manual_ajax();

		YY_DMM_Auto_Post_Sample_Movie::hooks();

		$this->cron = new YY_DMM_Auto_Post_Cron( $this );
		$this->cron->hooks();

		if ( is_admin() ) {
			$this->admin = new YY_DMM_Auto_Post_Admin( $this );
			$this->admin->hooks();
		}
	}

	public function run_import( $type = 'manual', $progress_callback = null ) {
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
		$progress_callback = is_callable( $progress_callback ) ? $progress_callback : null;
		$emit_progress = static function ( $event ) use ( &$result, $progress_callback ) {
			if ( ! $progress_callback ) {
				return;
			}

			$event = is_array( $event ) ? $event : array();
			$event['result'] = $result;
			call_user_func( $progress_callback, $event );
		};

		$settings = YY_DMM_Auto_Post_Settings::get();
		$api      = new YY_DMM_Auto_Post_API_Client( $settings );
		$emit_progress(
			array(
				'status'  => 'running',
				'level'   => 'info',
				'message' => 'APIから商品情報を取得します。',
			)
		);
		$items    = $api->fetch_items();

		if ( is_wp_error( $items ) ) {
			$result['errors'][] = $items->get_error_message();
			$result['finished_at'] = current_time( 'mysql' );
			$emit_progress(
				array(
					'status'  => 'error',
					'level'   => 'error',
					'message' => 'API取得に失敗しました: ' . $items->get_error_message(),
				)
			);
			( new YY_DMM_Auto_Post_Logger() )->add( $type, $result );
			return $result;
		}

		$result['fetched'] = count( $items );
		$emit_progress(
			array(
				'status'  => 'running',
				'level'   => 'success',
				'message' => sprintf( 'APIから%d件取得しました。', $result['fetched'] ),
			)
		);
		$builder = new YY_DMM_Auto_Post_Post_Builder( $settings );
		$manager = new YY_DMM_Auto_Post_Post_Manager( $settings, $builder );
		$scraper = new YY_DMM_Auto_Post_Scraper();
		$media   = new YY_DMM_Auto_Post_Media( $settings );
		$max     = max( 1, absint( $settings['max_posts'] ) );
		$all     = ! empty( $settings['max_posts_all'] );

		foreach ( $items as $index => $item ) {
			if ( ! $all && $result['posted'] >= $max ) {
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'info',
						'message' => sprintf( '最大投稿数%d件に達したため終了します。', $max ),
					)
				);
				break;
			}

			if ( ! is_array( $item ) ) {
				$result['skipped']++;
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'warning',
						'message' => sprintf( '%d件目: 商品データが不正なためスキップしました。', $index + 1 ),
					)
				);
				continue;
			}

			$content_id = $builder->get_content_id( $item );
			if ( '' === $content_id ) {
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'error',
						'message' => sprintf( '%d件目: content_idが空のためスキップしました。', $index + 1 ),
					)
				);
				$result['skipped']++;
				$result['errors'][] = 'content_idがない商品をスキップしました。';
				continue;
			}

			$post_title = $builder->get_wp_title( $item );
			$api_title = isset( $item['title'] ) ? trim( sanitize_text_field( (string) $item['title'] ) ) : '';
			$emit_progress(
				array(
					'status'  => 'running',
					'level'   => '' === $api_title ? 'warning' : 'info',
					'message' => sprintf( '%d/%d 処理開始: %s / %s', $index + 1, $result['fetched'], $content_id, '' !== $post_title ? $post_title : 'タイトル空' ),
				)
			);
			if ( '' === $api_title ) {
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'warning',
						'message' => sprintf( '%s: APIのtitleが空です。生成タイトル「%s」で処理します。', $content_id, '' !== $post_title ? $post_title : 'タイトル空' ),
					)
				);
			}

			$is_existing_post = $manager->is_posted( $content_id );
			if ( ! empty( $settings['prevent_duplicates'] ) && $is_existing_post ) {
				$result['skipped']++;
				$result['duplicate_skipped']++;
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'info',
						'message' => sprintf( '%s: 重複投稿防止のためスキップしました。', $content_id ),
					)
				);
				continue;
			}

			$description = '';
			if ( $this->should_fetch_description( $settings ) ) {
				$scrape = $scraper->fetch_description( $content_id );
				if ( is_wp_error( $scrape ) ) {
					$result['errors'][] = sprintf( '%s: %s', $content_id, $scrape->get_error_message() );
					$emit_progress(
						array(
							'status'  => 'running',
							'level'   => 'warning',
							'message' => sprintf( '%s: 説明文の取得に失敗しました: %s', $content_id, $scrape->get_error_message() ),
						)
					);
				} else {
					$description = (string) $scrape;
				}
			} else {
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'info',
						'message' => sprintf( '%s: 説明文取得をスキップしました。', $content_id ),
					)
				);
			}

			$featured_media_id = 0;
			if ( $this->should_save_featured_image( $settings ) ) {
				$attachment_id = $media->get_or_sideload_featured( $item );
				if ( is_wp_error( $attachment_id ) ) {
					$result['errors'][] = sprintf( '%s: %s', $content_id, $attachment_id->get_error_message() );
					$emit_progress(
						array(
							'status'  => 'running',
							'level'   => 'warning',
							'message' => sprintf( '%s: アイキャッチ取得に失敗しました: %s', $content_id, $attachment_id->get_error_message() ),
						)
					);
				} else {
					$featured_media_id = absint( $attachment_id );
				}
			} else {
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'info',
						'message' => sprintf( '%s: アイキャッチ保存をスキップしました。', $content_id ),
					)
				);
			}

			$this->begin_import_performance_mode();
			try {
				$product_info_term_links = $manager->prepare_product_info_term_links( $item );
				$content = $builder->build_content( $item, $description, $product_info_term_links );
				$post_id = $manager->create_post( $item, $content, $featured_media_id );
			} finally {
				$this->end_import_performance_mode();
			}
			if ( is_wp_error( $post_id ) ) {
				$result['errors'][] = sprintf( '%s: %s', $content_id, $post_id->get_error_message() );
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'error',
						'message' => sprintf( '%s: 投稿に失敗しました: %s', $content_id, $post_id->get_error_message() ),
					)
				);
				continue;
			}

			$result['posted']++;
			if ( $is_existing_post ) {
				$result['updated']++;
				$action_label = '更新';
			} else {
				$result['created']++;
				$action_label = '新規投稿';
			}
			$emit_progress(
				array(
					'status'  => 'running',
					'level'   => 'success',
					'message' => sprintf( '%s: %sしました。投稿ID %d / %s', $content_id, $action_label, absint( $post_id ), $post_title ),
				)
			);
		}

		$result['finished_at'] = current_time( 'mysql' );
		$emit_progress(
			array(
				'status'  => 'running',
				'level'   => empty( $result['errors'] ) ? 'success' : 'warning',
				'message' => '投稿処理を終了しました。',
			)
		);
		( new YY_DMM_Auto_Post_Logger() )->add( $type, $result );

		return $result;
	}

	public function begin_import( $type = 'manual', $progress_callback = null ) {
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
		$progress_callback = is_callable( $progress_callback ) ? $progress_callback : null;
		$emit_progress = static function ( $event ) use ( &$result, $progress_callback ) {
			if ( ! $progress_callback ) {
				return;
			}

			$event = is_array( $event ) ? $event : array();
			$event['result'] = $result;
			call_user_func( $progress_callback, $event );
		};

		$settings = YY_DMM_Auto_Post_Settings::get();
		$emit_progress(
			array(
				'status'  => 'running',
				'level'   => 'info',
				'message' => 'APIから商品情報を取得します。',
			)
		);

		$items = ( new YY_DMM_Auto_Post_API_Client( $settings ) )->fetch_items();
		if ( is_wp_error( $items ) ) {
			$result['errors'][] = $items->get_error_message();
			$result['finished_at'] = current_time( 'mysql' );
			$emit_progress(
				array(
					'status'  => 'error',
					'level'   => 'error',
					'message' => 'API取得に失敗しました: ' . $items->get_error_message(),
				)
			);
			( new YY_DMM_Auto_Post_Logger() )->add( $type, $result );

			return array(
				'type'     => $type,
				'settings' => $settings,
				'items'    => array(),
				'index'    => 0,
				'done'     => true,
				'result'   => $result,
			);
		}

		$result['fetched'] = count( $items );
		$emit_progress(
			array(
				'status'  => 'running',
				'level'   => 'success',
				'message' => sprintf( 'APIから%d件取得しました。1件ずつ投稿します。', $result['fetched'] ),
			)
		);

		return array(
			'type'     => $type,
			'settings' => $settings,
			'items'    => $items,
			'index'    => 0,
			'done'     => false,
			'result'   => $result,
		);
	}

	public function run_import_step( $state, $progress_callback = null ) {
		$state = is_array( $state ) ? $state : array();
		$settings = isset( $state['settings'] ) && is_array( $state['settings'] ) ? $state['settings'] : YY_DMM_Auto_Post_Settings::get();
		$items = isset( $state['items'] ) && is_array( $state['items'] ) ? $state['items'] : array();
		$index = absint( $state['index'] ?? 0 );
		$type = sanitize_key( $state['type'] ?? 'manual' );
		$result = isset( $state['result'] ) && is_array( $state['result'] ) ? $state['result'] : array();
		$result = wp_parse_args(
			$result,
			array(
				'fetched'     => count( $items ),
				'posted'      => 0,
				'created'     => 0,
				'updated'     => 0,
				'skipped'     => 0,
				'duplicate_skipped' => 0,
				'errors'      => array(),
				'started_at'  => current_time( 'mysql' ),
				'finished_at' => '',
			)
		);
		$progress_callback = is_callable( $progress_callback ) ? $progress_callback : null;
		$emit_progress = static function ( $event ) use ( &$result, $progress_callback ) {
			if ( ! $progress_callback ) {
				return;
			}

			$event = is_array( $event ) ? $event : array();
			$event['result'] = $result;
			call_user_func( $progress_callback, $event );
		};

		if ( ! empty( $state['done'] ) ) {
			return $state;
		}

		$max = max( 1, absint( $settings['max_posts'] ?? 1 ) );
		$all = ! empty( $settings['max_posts_all'] );
		if ( ! $all && $result['posted'] >= $max ) {
			return $this->finish_import_state( $state, $result, $emit_progress );
		}

		if ( $index >= count( $items ) ) {
			return $this->finish_import_state( $state, $result, $emit_progress );
		}

		$item = $items[ $index ];
		$builder = new YY_DMM_Auto_Post_Post_Builder( $settings );
		$manager = new YY_DMM_Auto_Post_Post_Manager( $settings, $builder );
		$scraper = new YY_DMM_Auto_Post_Scraper();
		$media   = new YY_DMM_Auto_Post_Media( $settings );
		$timing_enabled = $this->should_emit_timing_logs( $type );
		$step_started_at = microtime( true );
		$queries_before_step = $this->get_query_count();
		$memory_before_step = $this->get_memory_usage_bytes();
		$content_id = '';

		if ( ! is_array( $item ) ) {
			$result['skipped']++;
			$emit_progress(
				array(
					'status'  => 'running',
					'level'   => 'warning',
					'message' => sprintf( '%d件目: 商品データが不正なためスキップしました。', $index + 1 ),
				)
			);
		} else {
			$content_id = $builder->get_content_id( $item );
			if ( '' === $content_id ) {
				$result['skipped']++;
				$result['errors'][] = 'content_idがない商品をスキップしました。';
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'error',
						'message' => sprintf( '%d件目: content_idが空のためスキップしました。', $index + 1 ),
					)
				);
			} else {
				$post_title = $builder->get_wp_title( $item );
				$emit_progress(
					array(
						'status'  => 'running',
						'level'   => 'info',
						'message' => sprintf( '%d/%d 処理開始: %s / %s', $index + 1, count( $items ), $content_id, $post_title ),
					)
				);

				$existing_check_started_at = microtime( true );
				$is_existing_post = $manager->is_posted( $content_id );
				$this->emit_timing_log( $timing_enabled, $emit_progress, $content_id, 'existing_check', $existing_check_started_at );
				if ( ! empty( $settings['prevent_duplicates'] ) && $is_existing_post ) {
					$result['skipped']++;
					$result['duplicate_skipped']++;
					$emit_progress(
						array(
							'status'  => 'running',
							'level'   => 'info',
							'message' => sprintf( '%s: 重複投稿防止のためスキップしました。', $content_id ),
						)
					);
				} else {
					$description = '';
					if ( $this->should_fetch_description( $settings ) ) {
						$description_started_at = microtime( true );
						$scrape = $scraper->fetch_description( $content_id );
						if ( is_wp_error( $scrape ) ) {
							$result['errors'][] = sprintf( '%s: %s', $content_id, $scrape->get_error_message() );
							$emit_progress(
								array(
									'status'  => 'running',
									'level'   => 'warning',
									'message' => sprintf( '%s: 説明文の取得に失敗しました: %s', $content_id, $scrape->get_error_message() ),
								)
							);
						} else {
							$description = (string) $scrape;
						}
						$this->emit_timing_log( $timing_enabled, $emit_progress, $content_id, 'fetch_description', $description_started_at );
					} else {
						$emit_progress(
							array(
								'status'  => 'running',
								'level'   => 'info',
								'message' => sprintf( '%s: 説明文取得をスキップしました。', $content_id ),
							)
						);
					}

					$featured_media_id = 0;
					if ( $this->should_save_featured_image( $settings ) ) {
						$featured_started_at = microtime( true );
						$attachment_id = $media->get_or_sideload_featured( $item );
						if ( is_wp_error( $attachment_id ) ) {
							$result['errors'][] = sprintf( '%s: %s', $content_id, $attachment_id->get_error_message() );
							$emit_progress(
								array(
									'status'  => 'running',
									'level'   => 'warning',
									'message' => sprintf( '%s: アイキャッチ取得に失敗しました: %s', $content_id, $attachment_id->get_error_message() ),
								)
							);
						} else {
							$featured_media_id = absint( $attachment_id );
						}
						$this->emit_timing_log( $timing_enabled, $emit_progress, $content_id, 'save_featured_image', $featured_started_at );
					} else {
						$emit_progress(
							array(
								'status'  => 'running',
								'level'   => 'info',
								'message' => sprintf( '%s: アイキャッチ保存をスキップしました。', $content_id ),
							)
						);
					}

					$this->begin_import_performance_mode();
					try {
						$build_started_at = microtime( true );
						$product_info_term_links = $manager->prepare_product_info_term_links( $item );
						$content = $builder->build_content( $item, $description, $product_info_term_links );
						$this->emit_timing_log( $timing_enabled, $emit_progress, $content_id, 'build_content', $build_started_at );
						$save_started_at = microtime( true );
						$post_id = $manager->create_post( $item, $content, $featured_media_id );
						$this->emit_timing_log( $timing_enabled, $emit_progress, $content_id, 'save_post', $save_started_at );
					} finally {
						$this->end_import_performance_mode();
					}
					if ( is_wp_error( $post_id ) ) {
						$result['errors'][] = sprintf( '%s: %s', $content_id, $post_id->get_error_message() );
						$emit_progress(
							array(
								'status'  => 'running',
								'level'   => 'error',
								'message' => sprintf( '%s: 投稿に失敗しました: %s', $content_id, $post_id->get_error_message() ),
							)
						);
					} else {
						$result['posted']++;
						if ( $is_existing_post ) {
							$result['updated']++;
							$action_label = '更新';
						} else {
							$result['created']++;
							$action_label = '新規投稿';
						}
						$emit_progress(
							array(
								'status'  => 'running',
								'level'   => 'success',
								'message' => sprintf( '%s: %sしました。投稿ID %d / %s', $content_id, $action_label, absint( $post_id ), $post_title ),
							)
						);
					}
				}
			}
		}

		$this->emit_step_timing_summary(
			$timing_enabled,
			$emit_progress,
			'' !== $content_id ? $content_id : sprintf( 'item_%d', $index + 1 ),
			$step_started_at,
			$queries_before_step,
			$memory_before_step
		);

		$state['index'] = $index + 1;
		$state['result'] = $result;
		if ( $state['index'] >= count( $items ) || ( ! $all && $result['posted'] >= $max ) ) {
			return $this->finish_import_state( $state, $result, $emit_progress );
		}

		return $state;
	}

	private function should_fetch_description( $settings ) {
		if ( empty( $settings['fetch_description'] ) ) {
			return false;
		}

		$sections = isset( $settings['body_sections'] ) && is_array( $settings['body_sections'] ) ? $settings['body_sections'] : array();
		if ( array_key_exists( 'description', $sections ) && empty( $sections['description'] ) ) {
			return false;
		}

		return true;
	}

	private function should_save_featured_image( $settings ) {
		return ! empty( $settings['save_featured_image'] );
	}

	private function should_emit_timing_logs( $type ) {
		return 'manual' === sanitize_key( (string) $type );
	}

	private function emit_timing_log( $enabled, $emit_progress, $content_id, $label, $started_at ) {
		if ( ! $enabled ) {
			return;
		}

		call_user_func(
			$emit_progress,
			array(
				'status'  => 'running',
				'level'   => 'info',
				'message' => sprintf(
					'[PERF] %s: %s=%dms',
					$content_id,
					sanitize_key( $label ),
					$this->elapsed_ms( $started_at )
				),
			)
		);
	}

	private function emit_step_timing_summary( $enabled, $emit_progress, $content_id, $step_started_at, $queries_before_step, $memory_before_step ) {
		if ( ! $enabled ) {
			return;
		}

		$query_delta = max( 0, $this->get_query_count() - absint( $queries_before_step ) );
		$memory_delta = max( 0, $this->get_memory_usage_bytes() - absint( $memory_before_step ) );
		call_user_func(
			$emit_progress,
			array(
				'status'  => 'running',
				'level'   => 'info',
				'message' => sprintf(
					'[PERF] %s: total=%dms queries=+%d mem=+%s',
					$content_id,
					$this->elapsed_ms( $step_started_at ),
					$query_delta,
					size_format( $memory_delta )
				),
			)
		);
	}

	private function elapsed_ms( $started_at ) {
		$started_at = is_numeric( $started_at ) ? (float) $started_at : microtime( true );
		return (int) round( max( 0, microtime( true ) - $started_at ) * 1000 );
	}

	private function get_query_count() {
		if ( function_exists( 'get_num_queries' ) ) {
			return absint( get_num_queries() );
		}

		return 0;
	}

	private function get_memory_usage_bytes() {
		if ( function_exists( 'memory_get_usage' ) ) {
			return absint( memory_get_usage( true ) );
		}

		return 0;
	}

	private function maybe_disable_wp_cron_for_manual_ajax() {
		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( ! in_array( $action, array( 'yy_dmm_auto_post_run_manual', 'yy_dmm_auto_post_manual_step' ), true ) ) {
			return;
		}

		if ( ! defined( 'DISABLE_WP_CRON' ) ) {
			define( 'DISABLE_WP_CRON', true );
		}
	}

	private function begin_import_performance_mode() {
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( true );
		}
		if ( function_exists( 'wp_defer_comment_counting' ) ) {
			wp_defer_comment_counting( true );
		}
		if ( function_exists( 'wp_suspend_cache_invalidation' ) ) {
			wp_suspend_cache_invalidation( true );
		}
	}

	private function end_import_performance_mode() {
		if ( function_exists( 'wp_suspend_cache_invalidation' ) ) {
			wp_suspend_cache_invalidation( false );
		}
		if ( function_exists( 'wp_defer_comment_counting' ) ) {
			wp_defer_comment_counting( false );
		}
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( false );
		}
	}

	private function finish_import_state( $state, $result, $emit_progress ) {
		if ( ! empty( $state['done'] ) ) {
			return $state;
		}

		$result['finished_at'] = current_time( 'mysql' );
		$state['result'] = $result;
		$state['done'] = true;
		call_user_func(
			$emit_progress,
			array(
				'status'  => 'running',
				'level'   => empty( $result['errors'] ) ? 'success' : 'warning',
				'message' => '投稿処理を終了しました。',
			)
		);
		( new YY_DMM_Auto_Post_Logger() )->add( $state['type'] ?? 'manual', $result );

		return $state;
	}
}
