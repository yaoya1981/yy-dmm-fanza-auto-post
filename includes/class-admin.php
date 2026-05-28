<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Admin {
	private $plugin;

	public function __construct( YY_DMM_Auto_Post_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_menu', array( $this, 'add_api_test_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_yy_dmm_auto_post_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_yy_dmm_auto_post_run_manual', array( $this, 'handle_manual_run' ) );
		add_action( 'admin_post_yy_dmm_auto_post_delete_content', array( $this, 'handle_delete_content' ) );
		add_action( 'wp_ajax_yy_dmm_auto_post_run_manual', array( $this, 'handle_manual_run_ajax' ) );
		add_action( 'wp_ajax_yy_dmm_auto_post_manual_step', array( $this, 'handle_manual_step_ajax' ) );
		add_action( 'wp_ajax_yy_dmm_auto_post_manual_progress', array( $this, 'handle_manual_progress_ajax' ) );
	}

	public function add_menu() {
		add_menu_page(
			'DMM/FANZA自動投稿',
			'DMM/FANZA自動投稿',
			'manage_options',
			'yy-dmm-fanza-auto-post',
			array( $this, 'render_guide_page' ),
			'dashicons-update-alt',
			58
		);

		add_submenu_page( 'yy-dmm-fanza-auto-post', '使い方', '使い方', 'manage_options', 'yy-dmm-fanza-auto-post', array( $this, 'render_guide_page' ) );
		add_submenu_page( 'yy-dmm-fanza-auto-post', '手動実行', '手動実行', 'manage_options', 'yy-dmm-fanza-auto-post-run', array( $this, 'render_manual_page' ) );
		add_submenu_page( 'yy-dmm-fanza-auto-post', '設定', '設定', 'manage_options', 'yy-dmm-fanza-auto-post-settings', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'yy-dmm-fanza-auto-post', '投稿ログ', '投稿ログ', 'manage_options', 'yy-dmm-fanza-auto-post-logs', array( $this, 'render_logs_page' ) );
		add_submenu_page( 'yy-dmm-fanza-auto-post', 'クリーンアップ', 'クリーンアップ', 'manage_options', 'yy-dmm-fanza-auto-post-cleanup', array( $this, 'render_cleanup_page' ) );
	}

	public function add_api_test_menu() {
		add_submenu_page( 'yy-dmm-fanza-auto-post', 'APIテスト', 'APIテスト', 'manage_options', 'yy-dmm-fanza-auto-post-api-test', array( $this, 'render_api_test_page' ) );
	}

	public function enqueue_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'yy-dmm-fanza-auto-post' ) ) {
			return;
		}

		wp_enqueue_style( 'yy-dmm-auto-post-admin', YY_DMM_AUTO_POST_URL . 'assets/admin.css', array(), YY_DMM_AUTO_POST_VERSION );
		wp_enqueue_script( 'yy-dmm-auto-post-admin', YY_DMM_AUTO_POST_URL . 'assets/admin.js', array(), YY_DMM_AUTO_POST_VERSION, true );
		wp_localize_script(
			'yy-dmm-auto-post-admin',
			'yyDmmAutoPostAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'yy-dmm-fanza-auto-post' ) );
		}

		check_admin_referer( 'yy_dmm_auto_post_save_settings' );
		$settings = isset( $_POST['yy_dmm_auto_post_settings'] ) ? wp_unslash( $_POST['yy_dmm_auto_post_settings'] ) : array();
		$tab      = isset( $_POST['yy_dmm_auto_post_tab'] ) ? sanitize_key( wp_unslash( $_POST['yy_dmm_auto_post_tab'] ) ) : 'api';

		YY_DMM_Auto_Post_Settings::update( $settings );
		YY_DMM_Auto_Post_Cron::reschedule();

		wp_safe_redirect( admin_url( 'admin.php?page=yy-dmm-fanza-auto-post-settings&settings-updated=1&tab=' . $this->normalize_settings_tab( $tab ) ) );
		exit;
	}

	public function handle_manual_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'yy-dmm-fanza-auto-post' ) );
		}

		check_admin_referer( 'yy_dmm_auto_post_run_manual' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		$result = $this->plugin->run_import( 'manual' );
		set_transient( 'yy_dmm_auto_post_manual_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=yy-dmm-fanza-auto-post-run&run-complete=1' ) );
		exit;
	}

	public function handle_manual_run_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		check_ajax_referer( 'yy_dmm_auto_post_run_manual', 'nonce' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 );
		}

		$this->reset_manual_progress();
		$self = $this;
		$state = $this->plugin->begin_import(
			'manual',
			static function ( $event ) use ( $self ) {
				$self->update_manual_progress( $event );
			}
		);
		set_transient( $this->manual_state_key(), $state, 30 * MINUTE_IN_SECONDS );
		if ( ! empty( $state['done'] ) ) {
			$result = isset( $state['result'] ) && is_array( $state['result'] ) ? $state['result'] : array();
			set_transient( 'yy_dmm_auto_post_manual_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
			$this->finish_manual_progress( $result );
		}

		wp_send_json_success(
			array(
				'done'     => ! empty( $state['done'] ),
				'result'   => isset( $state['result'] ) ? $state['result'] : array(),
				'progress' => $this->get_manual_progress(),
			)
		);
	}

	public function handle_manual_step_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		check_ajax_referer( 'yy_dmm_auto_post_run_manual', 'nonce' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 90 );
		}

		$state = get_transient( $this->manual_state_key() );
		if ( ! is_array( $state ) ) {
			wp_send_json_error( array( 'message' => '手動実行の状態が見つかりません。もう一度実行してください。' ), 404 );
		}

		$self = $this;
		$state = $this->plugin->run_import_step(
			$state,
			static function ( $event ) use ( $self ) {
				$self->update_manual_progress( $event );
			}
		);
		set_transient( $this->manual_state_key(), $state, 30 * MINUTE_IN_SECONDS );
		if ( ! empty( $state['done'] ) ) {
			$result = isset( $state['result'] ) && is_array( $state['result'] ) ? $state['result'] : array();
			set_transient( 'yy_dmm_auto_post_manual_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
			$this->finish_manual_progress( $result );
			delete_transient( $this->manual_state_key() );
		}

		wp_send_json_success(
			array(
				'done'     => ! empty( $state['done'] ),
				'result'   => isset( $state['result'] ) ? $state['result'] : array(),
				'progress' => $this->get_manual_progress(),
			)
		);
	}

	public function handle_manual_progress_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		check_ajax_referer( 'yy_dmm_auto_post_run_manual', 'nonce' );
		wp_send_json_success( $this->get_manual_progress() );
	}

	public function handle_delete_content() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'yy-dmm-fanza-auto-post' ) );
		}

		check_admin_referer( 'yy_dmm_auto_post_delete_content' );

		$delete_type = isset( $_POST['yy_dmm_delete_type'] ) ? sanitize_key( wp_unslash( $_POST['yy_dmm_delete_type'] ) ) : '';
		$confirm = isset( $_POST['yy_dmm_delete_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['yy_dmm_delete_confirm'] ) ) : '';
		$actions = $this->delete_actions();

		if ( ! isset( $actions[ $delete_type ] ) ) {
			$result = array(
				'label'  => 'クリーンアップ',
				'errors' => array( '削除対象が正しくありません。' ),
			);
		} elseif ( 'all' === $delete_type && '全削除' !== $confirm ) {
			$result = array(
				'label'  => $actions[ $delete_type ]['label'],
				'errors' => array( '全削除を実行するには、確認欄に「全削除」と入力してください。' ),
			);
		} else {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 600 );
			}

			$result = $this->run_delete_action( $delete_type );
		}

		set_transient( 'yy_dmm_auto_post_delete_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=yy-dmm-fanza-auto-post-cleanup&cleanup-complete=1' ) );
		exit;
	}

	public function render_api_test_page() {
		$this->guard();
		$settings = YY_DMM_Auto_Post_Settings::get();
		$keyword  = '';
		$result   = null;

		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			check_admin_referer( 'yy_dmm_auto_post_api_test' );
			$posted_settings = isset( $_POST['yy_dmm_auto_post_settings'] ) ? wp_unslash( $_POST['yy_dmm_auto_post_settings'] ) : array();
			$posted_settings = is_array( $posted_settings ) ? $posted_settings : array();
			$keyword = isset( $posted_settings['api_test_keyword'] ) ? sanitize_text_field( $posted_settings['api_test_keyword'] ) : '';
			$result = ( new YY_DMM_Auto_Post_API_Client( $settings ) )->fetch_test_items( $keyword, 10 );
		}
		?>
		<div class="wrap yy-dmm-admin">
			<h1>APIテスト</h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=yy-dmm-fanza-auto-post-api-test' ) ); ?>" class="yy-dmm-api-test-form">
				<?php wp_nonce_field( 'yy_dmm_auto_post_api_test' ); ?>
				<table class="form-table yy-dmm-settings-panel" role="presentation">
					<?php $this->text_row( 'キーワード', 'api_test_keyword', $keyword, '検索キーワード' ); ?>
				</table>
				<?php submit_button( 'APIテストを実行', 'primary', 'yy_dmm_api_test_submit', false ); ?>
			</form>
			<?php $this->render_api_test_result( $result, $keyword ); ?>
		</div>
		<?php
	}

	private function render_api_test_result( $result, $keyword ) {
		if ( null === $result ) {
			return;
		}

		if ( is_wp_error( $result ) ) {
			?>
			<div class="notice notice-error"><p><?php echo esc_html( $result->get_error_message() ); ?></p></div>
			<?php
			return;
		}

		$items = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
		?>
		<div class="yy-dmm-api-test-result">
			<h2>検索結果</h2>
			<table class="widefat striped yy-dmm-api-test-summary">
				<tbody>
					<tr>
						<th>キーワード</th>
						<td><?php echo '' !== $keyword ? esc_html( $keyword ) : '（空白）'; ?></td>
					</tr>
					<tr>
						<th>API結果件数</th>
						<td><?php echo esc_html( absint( $result['total_count'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th>表示件数</th>
						<td><?php echo esc_html( absint( $result['display_count'] ?? count( $items ) ) ); ?> / 10</td>
					</tr>
				</tbody>
			</table>

			<table class="widefat striped yy-dmm-api-test-table">
				<thead>
					<tr>
						<th>No.</th>
						<th>content_id</th>
						<th>タイトル</th>
						<th>日付</th>
						<th>アフィリエイトURL</th>
						<th>JSON</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $items ) : ?>
						<?php foreach ( $items as $index => $item ) : ?>
							<?php $item = is_array( $item ) ? $item : array(); ?>
							<tr>
								<td><?php echo esc_html( $index + 1 ); ?></td>
								<td><code><?php echo esc_html( $item['content_id'] ?? '' ); ?></code></td>
								<td><?php echo esc_html( $item['title'] ?? '' ); ?></td>
								<td><?php echo esc_html( $item['date'] ?? '' ); ?></td>
								<td>
									<?php if ( ! empty( $item['affiliateURL'] ) ) : ?>
										<a href="<?php echo esc_url( $item['affiliateURL'] ); ?>" target="_blank" rel="noopener noreferrer">URL</a>
									<?php endif; ?>
								</td>
								<td><pre><?php echo esc_html( $this->format_api_test_json( $item ) ); ?></pre></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="6">該当する商品はありません。</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function format_api_test_json( $value ) {
		$json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		return false !== $json ? $json : '';
	}

	public function render_settings_page() {
		$this->guard();
		$settings = YY_DMM_Auto_Post_Settings::get();
		$tabs     = $this->settings_tabs();
		$active   = $this->normalize_settings_tab( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'api' );
		?>
		<div class="wrap yy-dmm-admin">
			<h1>DMM/FANZA自動投稿 設定</h1>
			<?php if ( ! empty( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper yy-dmm-settings-tabs" aria-label="設定タブ">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $active === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=yy-dmm-fanza-auto-post-settings&tab=' . $key ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="yy_dmm_auto_post_save_settings">
				<input type="hidden" name="yy_dmm_auto_post_tab" value="<?php echo esc_attr( $active ); ?>">
				<?php wp_nonce_field( 'yy_dmm_auto_post_save_settings' ); ?>

				<?php $this->render_settings_tab_fields( $active, $settings ); ?>

				<?php submit_button( $tabs[ $active ] . 'を保存' ); ?>
			</form>
		</div>
		<?php
	}

	public function render_manual_page() {
		$this->guard();
		$result = get_transient( 'yy_dmm_auto_post_manual_result_' . get_current_user_id() );
		if ( false !== $result ) {
			delete_transient( 'yy_dmm_auto_post_manual_result_' . get_current_user_id() );
		}
		?>
		<div class="wrap yy-dmm-admin">
			<h1>DMM/FANZA自動投稿 手動実行</h1>
			<?php $this->render_result_notice( $result ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yy-dmm-run-form">
				<input type="hidden" name="action" value="yy_dmm_auto_post_run_manual">
				<?php wp_nonce_field( 'yy_dmm_auto_post_run_manual' ); ?>
				<?php $this->render_manual_progress_panel(); ?>
				<?php submit_button( '今すぐ取得して投稿する', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	private function manual_state_key() {
		return 'yy_dmm_auto_post_manual_state_' . get_current_user_id();
	}

	private function manual_progress_key() {
		return 'yy_dmm_auto_post_manual_progress_' . get_current_user_id();
	}

	private function reset_manual_progress() {
		$progress = array(
			'status'       => 'running',
			'status_label' => '実行中',
			'started_at'   => current_time( 'mysql' ),
			'finished_at'  => '',
			'result'       => array(
				'fetched'     => 0,
				'posted'      => 0,
				'created'     => 0,
				'updated'     => 0,
				'skipped'     => 0,
				'duplicate_skipped' => 0,
				'errors'      => array(),
			),
			'lines'        => array(
				$this->manual_progress_line( 'info', '手動実行を開始しました。' ),
			),
		);

		set_transient( $this->manual_progress_key(), $progress, 30 * MINUTE_IN_SECONDS );
	}

	private function get_manual_progress() {
		$progress = get_transient( $this->manual_progress_key() );
		if ( ! is_array( $progress ) ) {
			return array(
				'status'       => 'idle',
				'status_label' => '待機中',
				'result'       => array(
					'fetched'     => 0,
					'posted'      => 0,
					'created'     => 0,
					'updated'     => 0,
					'skipped'     => 0,
					'duplicate_skipped' => 0,
					'errors'      => array(),
				),
				'lines'        => array(),
			);
		}

		return $progress;
	}

	private function update_manual_progress( $event ) {
		$event = is_array( $event ) ? $event : array();
		$progress = $this->get_manual_progress();
		$progress['status'] = sanitize_key( $event['status'] ?? $progress['status'] ?? 'running' );
		$progress['status_label'] = $this->manual_progress_status_label( $progress['status'] );
		if ( isset( $event['result'] ) && is_array( $event['result'] ) ) {
			$progress['result'] = $this->sanitize_manual_progress_result( $event['result'] );
		}

		$message = isset( $event['message'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $event['message'] ) ) : '';
		if ( '' !== $message ) {
			$level = sanitize_key( $event['level'] ?? 'info' );
			$progress['lines'][] = $this->manual_progress_line( $level, $message );
			$progress['lines'] = array_slice( $progress['lines'], -200 );
		}

		set_transient( $this->manual_progress_key(), $progress, 30 * MINUTE_IN_SECONDS );
	}

	private function finish_manual_progress( $result ) {
		$progress = $this->get_manual_progress();
		$progress['status'] = empty( $result['errors'] ) ? 'done' : 'done_with_errors';
		$progress['status_label'] = $this->manual_progress_status_label( $progress['status'] );
		$progress['finished_at'] = current_time( 'mysql' );
		$progress['result'] = $this->sanitize_manual_progress_result( $result );
		$progress['lines'][] = $this->manual_progress_line( empty( $result['errors'] ) ? 'success' : 'warning', '手動実行が完了しました。' );
		$progress['lines'] = array_slice( $progress['lines'], -200 );

		set_transient( $this->manual_progress_key(), $progress, 30 * MINUTE_IN_SECONDS );
	}

	private function manual_progress_line( $level, $message ) {
		return array(
			'time'    => current_time( 'H:i:s' ),
			'level'   => sanitize_key( $level ),
			'message' => sanitize_text_field( wp_strip_all_tags( (string) $message ) ),
		);
	}

	private function manual_progress_status_label( $status ) {
		$labels = array(
			'idle'             => '待機中',
			'running'          => '実行中',
			'done'             => '完了',
			'done_with_errors' => '完了（要確認）',
			'error'            => 'エラー',
		);

		return $labels[ $status ] ?? $labels['running'];
	}

	private function sanitize_manual_progress_result( $result ) {
		$result = is_array( $result ) ? $result : array();

		return array(
			'fetched'     => absint( $result['fetched'] ?? 0 ),
			'posted'      => absint( $result['posted'] ?? 0 ),
			'created'     => absint( $result['created'] ?? 0 ),
			'updated'     => absint( $result['updated'] ?? 0 ),
			'skipped'     => absint( $result['skipped'] ?? 0 ),
			'duplicate_skipped' => absint( $result['duplicate_skipped'] ?? 0 ),
			'errors'      => array_map( 'sanitize_text_field', array_slice( (array) ( $result['errors'] ?? array() ), 0, 20 ) ),
		);
	}

	private function render_manual_progress_panel() {
		?>
		<div class="yy-dmm-realtime-log" data-yy-dmm-manual-progress hidden>
			<h2>リアルタイムログ</h2>
			<div class="yy-dmm-progress-summary">
				<span>状態: <strong data-yy-dmm-progress-status>待機中</strong></span>
				<span>取得: <strong data-yy-dmm-progress-fetched>0</strong></span>
				<span>投稿: <strong data-yy-dmm-progress-posted>0</strong></span>
				<span>新規: <strong data-yy-dmm-progress-created>0</strong></span>
				<span>更新: <strong data-yy-dmm-progress-updated>0</strong></span>
				<span>スキップ: <strong data-yy-dmm-progress-skipped>0</strong></span>
				<span>エラー: <strong data-yy-dmm-progress-errors>0</strong></span>
			</div>
			<ul class="yy-dmm-progress-lines" data-yy-dmm-progress-lines></ul>
		</div>
		<?php
	}

	public function render_logs_page() {
		$this->guard();
		$logs = array_reverse( YY_DMM_Auto_Post_Logger::get_logs() );
		?>
		<div class="wrap yy-dmm-admin">
			<h1>DMM/FANZA自動投稿 投稿ログ</h1>
			<table class="widefat striped yy-dmm-log-table">
				<thead>
					<tr>
						<th>実行日時</th>
						<th>種別</th>
						<th>取得件数</th>
						<th>投稿件数</th>
						<th>新規</th>
						<th>更新</th>
						<th>スキップ件数</th>
						<th>重複スキップ</th>
						<th>エラー</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $logs ) : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['datetime'] ?? '' ); ?></td>
								<td><?php echo esc_html( $log['type'] ?? '' ); ?></td>
								<td><?php echo esc_html( absint( $log['fetched'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( absint( $log['posted'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( absint( $log['created'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( absint( $log['updated'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( absint( $log['skipped'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( absint( $log['duplicate_skipped'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( implode( "\n", $log['errors'] ?? array() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="9">ログはまだありません。</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_cleanup_page() {
		$this->guard();
		?>
		<div class="wrap yy-dmm-admin">
			<h1>DMM/FANZA自動投稿 クリーンアップ</h1>
			<?php $this->render_delete_settings_fields(); ?>
		</div>
		<?php
	}

	public function render_guide_page() {
		$this->guard();
		?>
		<div class="wrap yy-dmm-admin">
			<h1>DMM/FANZA自動投稿 使い方</h1>
			<ol class="yy-dmm-guide">
				<li>左メニューは「使い方」「手動実行」「設定」「投稿ログ」「クリーンアップ」の順に並んでいます。</li>
				<li>設定画面の「API設定」で、DMM/FANZA Affiliate API ID、アフィリエイトID、site、service、floor、keyword、sort、hitsを設定します。</li>
				<li>「投稿設定」で投稿ステータス、発売日・配信日との日付同期、1回の最大投稿数、重複投稿防止、自動実行、ログ保存を設定します。</li>
				<li>「タイトル・本文設定」で投稿タイトル形式、使用可能フィールドのコピー、本文に表示する項目、作品情報テーブルの表示項目・表示順・リンク有無を調整します。</li>
				<li>「動画・画像設定」でサンプル動画サイズ、アイキャッチ画像、サンプル画像サイズ、掲載枚数、続きを見るボタンを設定します。</li>
				<li>「カテゴリー・タグ」でカテゴリ作成、親カテゴリID、ジャンル・メーカー・レーベルごとの使用有無、親カテゴリ有無、スラッグのID/名前を設定します。同名の項目がある場合はIDスラッグを推奨します。</li>
				<li>手動実行画面で「今すぐ取得して投稿する」を押すと、現在の設定で商品を取得して投稿します。重複投稿防止がONの場合、投稿済みの品番はスキップします。</li>
				<li>投稿ログ画面で取得件数、投稿件数、新規、更新、スキップ、エラーを確認します。</li>
				<li>クリーンアップ画面ではキャッシュクリア、カテゴリーなし投稿修復、投稿記事・固定記事・カテゴリー・タグ・メディアの全削除、全削除を実行できます。削除はゴミ箱に入らず完全削除されるため、実行前にバックアップを確認してください。</li>
			</ol>
		</div>
		<?php
	}

	private function render_result_notice( $result ) {
		if ( ! is_array( $result ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				取得件数: <strong><?php echo esc_html( absint( $result['fetched'] ?? 0 ) ); ?></strong>
				投稿件数: <strong><?php echo esc_html( absint( $result['posted'] ?? 0 ) ); ?></strong>
				新規: <strong><?php echo esc_html( absint( $result['created'] ?? 0 ) ); ?></strong>
				更新: <strong><?php echo esc_html( absint( $result['updated'] ?? 0 ) ); ?></strong>
				スキップ件数: <strong><?php echo esc_html( absint( $result['skipped'] ?? 0 ) ); ?></strong>
				重複スキップ: <strong><?php echo esc_html( absint( $result['duplicate_skipped'] ?? 0 ) ); ?></strong>
				エラー件数: <strong><?php echo esc_html( count( $result['errors'] ?? array() ) ); ?></strong>
			</p>
			<?php if ( empty( $result['created'] ) && ! empty( $result['updated'] ) ) : ?>
				<p>新規投稿はありません。既存投稿を更新したため、記事数は増えていません。</p>
			<?php elseif ( empty( $result['created'] ) && ! empty( $result['duplicate_skipped'] ) ) : ?>
				<p>新規投稿はありません。重複投稿防止がONのため、既存品番をスキップしています。</p>
			<?php endif; ?>
			<?php if ( ! empty( $result['errors'] ) ) : ?>
				<ul>
					<?php foreach ( $result['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	private function settings_tabs() {
		return array(
			'api'      => 'API設定',
			'post'     => '投稿設定',
			'content'  => 'タイトル・本文設定',
			'media'    => '動画・画像設定',
			'taxonomy' => 'カテゴリー・タグ',
		);
	}

	private function normalize_settings_tab( $tab ) {
		$tab = sanitize_key( $tab );
		return array_key_exists( $tab, $this->settings_tabs() ) ? $tab : 'api';
	}

	private function render_settings_tab_fields( $tab, $settings ) {
		if ( 'post' === $tab ) {
			$this->render_post_settings_fields( $settings );
			return;
		}

		if ( 'content' === $tab ) {
			$this->render_content_settings_fields( $settings );
			return;
		}

		if ( 'media' === $tab ) {
			$this->render_media_settings_fields( $settings );
			return;
		}

		if ( 'taxonomy' === $tab ) {
			$this->render_taxonomy_settings_fields( $settings );
			return;
		}

		$this->render_api_settings_fields( $settings );
	}

	private function render_api_settings_fields( $settings ) {
		?>
		<table class="form-table yy-dmm-settings-panel" role="presentation">
			<?php $this->text_row( 'API ID', 'api_id', $settings['api_id'] ); ?>
			<?php $this->text_row( 'アフィリエイトID', 'affiliate_id', $settings['affiliate_id'] ); ?>
			<?php $this->text_row( 'site', 'site', $settings['site'] ); ?>
			<?php $this->text_row( 'service', 'service', $settings['service'] ); ?>
			<?php $this->text_row( 'floor', 'floor', $settings['floor'] ); ?>
			<?php $this->text_row( 'keyword', 'keyword', $settings['keyword'] ); ?>
			<?php $this->text_row( 'sort', 'sort', $settings['sort'] ); ?>
			<?php $this->number_row( 'hits', 'hits', $settings['hits'], 1, 100 ); ?>
		</table>
		<?php
	}

	private function render_post_settings_fields( $settings ) {
		?>
		<table class="form-table yy-dmm-settings-panel" role="presentation">
			<tr>
				<th scope="row"><label for="yy-dmm-post-status">投稿ステータス</label></th>
				<td>
					<select id="yy-dmm-post-status" name="yy_dmm_auto_post_settings[post_status]">
						<option value="draft" <?php selected( $settings['post_status'], 'draft' ); ?>>下書き</option>
						<option value="publish" <?php selected( $settings['post_status'], 'publish' ); ?>>公開</option>
					</select>
				</td>
			</tr>
			<?php $this->checkbox_row( '投稿日を発売日・配信日にする', 'sync_post_date_with_release_date', $settings['sync_post_date_with_release_date'], 'ONの場合はAPIの発売日・配信日を投稿日時に使用します。日付が取得できない場合は通常の投稿日時になります。' ); ?>
			<?php $this->text_row( '投稿用アフィリエイトID', 'post_affiliate_id', $settings['post_affiliate_id'], '', '登録したサイトURL用のアフィリエイトIDを投稿リンクで使用する場合に入力してください。空白ならAPI設定のアフィリエイトIDのまま投稿します。' ); ?>
			<?php $this->max_posts_row( $settings ); ?>
			<?php $this->checkbox_row( '重複投稿防止', 'prevent_duplicates', $settings['prevent_duplicates'], 'ONの場合は投稿済みの品番をスキップします。OFFの場合は同じ品番の既存投稿を更新し、未投稿の商品だけ新規投稿します。' ); ?>
			<?php $this->checkbox_row( '自動実行', 'enable_cron', $settings['enable_cron'] ); ?>
			<tr>
				<th scope="row"><label for="yy-dmm-cron-interval">実行間隔</label></th>
				<td>
					<select id="yy-dmm-cron-interval" name="yy_dmm_auto_post_settings[cron_interval]">
						<option value="daily" <?php selected( $settings['cron_interval'], 'daily' ); ?>>毎日</option>
						<option value="twicedaily" <?php selected( $settings['cron_interval'], 'twicedaily' ); ?>>12時間ごと</option>
						<option value="sixhourly" <?php selected( $settings['cron_interval'], 'sixhourly' ); ?>>6時間ごと</option>
					</select>
				</td>
			</tr>
			<?php $this->text_row( '実行時刻', 'daily_time', $settings['daily_time'], 'HH:MM' ); ?>
			<?php $this->checkbox_row( 'ログ保存', 'save_logs', $settings['save_logs'] ); ?>
			<?php $this->checkbox_row( 'アンインストール時に設定とログを削除', 'delete_on_uninstall', $settings['delete_on_uninstall'] ); ?>
		</table>
		<?php
	}

	private function render_content_settings_fields( $settings ) {
		?>
		<div class="yy-dmm-settings-panel">
			<h2>タイトル設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->text_row( '投稿タイトル形式', 'title_template', $settings['title_template'], '{title}｜{label}', '下のフィールドを組み合わせて使用できます。' ); ?>
				<?php $this->title_template_tokens_row(); ?>
			</table>

			<h2>本文設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->ordered_checkbox_group_row( '表示する項目', 'body_sections', 'body_section_order', $settings['body_sections'], $settings['body_section_order'], $this->body_section_labels() ); ?>
				<?php $this->text_row( '公式ボタン1のテキスト', 'affiliate_button_text_top', $settings['affiliate_button_text_top'], '公式ページを見る' ); ?>
				<?php $this->text_row( '公式ボタン2のテキスト', 'affiliate_button_text_middle', $settings['affiliate_button_text_middle'], '公式ページを見る' ); ?>
				<?php $this->text_row( '公式ボタン3のテキスト', 'affiliate_button_text_bottom', $settings['affiliate_button_text_bottom'], '公式ページを見る' ); ?>
			</table>

			<h2>作品情報テーブル</h2>
			<table class="form-table" role="presentation">
				<?php $this->ordered_checkbox_group_row( '表示する項目', 'product_info_fields', 'product_info_field_order', $settings['product_info_fields'], $settings['product_info_field_order'], $this->product_info_field_labels() ); ?>
				<?php $this->checkbox_row( 'リンクを付ける', 'product_info_link_terms', $settings['product_info_link_terms'], 'レーベル、メーカー、ジャンルに対応するカテゴリーまたはタグがある場合はリンク形式で表示します。' ); ?>
				<?php $this->text_row( '商品ページの左側テキスト', 'product_info_product_url_label', $settings['product_info_product_url_label'], '商品ページ' ); ?>
				<?php $this->text_row( '商品ページのリンクテキスト', 'product_info_product_url_link_text', $settings['product_info_product_url_link_text'], '商品ページを見る' ); ?>
				<?php $this->checkbox_row( '商品ページをボタンにする', 'product_info_product_url_button', $settings['product_info_product_url_button'] ); ?>
			</table>
		</div>
		<?php
	}

	private function render_media_settings_fields( $settings ) {
		?>
		<div class="yy-dmm-settings-panel">
			<h2>動画設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->select_row( 'サンプル動画サイズ', 'sample_movie_size', $settings['sample_movie_size'], $this->sample_movie_size_labels(), '選択したサイズがない商品では、利用可能なサイズへ自動で切り替えます。' ); ?>
			</table>

			<h2>アイキャッチ設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->select_row( 'アイキャッチ画像', 'featured_image_source', $settings['featured_image_source'], $this->featured_image_source_labels() ); ?>
			</table>

			<h2>サンプル画像設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->select_row( 'サンプル画像サイズ', 'sample_image_size', $settings['sample_image_size'], $this->sample_image_size_labels(), '選択したサイズがない商品では、もう一方のサイズへ自動で切り替えます。' ); ?>
				<?php $this->number_row( '掲載枚数', 'sample_image_max', $settings['sample_image_max'], 0, 100, '0なら取得できた画像をすべて掲載します。' ); ?>
				<?php $this->checkbox_row( 'サンプル画像後に続きを見るボタンを表示', 'show_sample_image_continue_button', $settings['show_sample_image_continue_button'], '公式ページへのアフィリエイトリンクを表示します。' ); ?>
				<?php $this->text_row( 'ボタン文言', 'sample_image_continue_button_text', $settings['sample_image_continue_button_text'], '続きを見る' ); ?>
			</table>
		</div>
		<?php
	}

	private function render_taxonomy_settings_fields( $settings ) {
		?>
		<div class="yy-dmm-settings-panel">
			<p class="description yy-dmm-taxonomy-note">複数の項目を同時に使う場合、名前を使用すると同名のジャンル・メーカー・レーベルでスラッグが競合することがあります。競合を避けたい場合はIDを使用してください。</p>

			<h2>カテゴリー設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->checkbox_row( 'カテゴリ作成', 'create_categories', $settings['create_categories'] ); ?>
				<?php $this->number_row( '親カテゴリID', 'parent_category_id', $settings['parent_category_id'], 0, 999999, '0の場合はトップ階層に作成します。' ); ?>
				<?php $this->category_taxonomy_matrix_row( '項目別設定', $settings ); ?>
			</table>

			<h2>タグ設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->checkbox_row( 'タグ作成', 'create_tags', $settings['create_tags'] ); ?>
				<?php $this->tag_taxonomy_matrix_row( '項目別設定', $settings ); ?>
			</table>
		</div>
		<?php
	}

	private function render_delete_settings_fields() {
		$result = get_transient( 'yy_dmm_auto_post_delete_result_' . get_current_user_id() );
		if ( false !== $result ) {
			delete_transient( 'yy_dmm_auto_post_delete_result_' . get_current_user_id() );
		}
		$actions = $this->delete_actions();
		?>
		<div class="yy-dmm-settings-panel yy-dmm-delete-panel">
			<?php $this->render_delete_result_notice( $result ); ?>
			<p class="description yy-dmm-delete-note">この画面のクリーンアップはゴミ箱へ移動せず完全削除します。実行前にバックアップを確認してください。</p>
			<table class="widefat striped yy-dmm-delete-table">
				<thead>
					<tr>
						<th>操作</th>
						<th>対象</th>
						<th>実行</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $actions as $action_key => $action ) : ?>
						<tr class="<?php echo 'all' === $action_key ? 'yy-dmm-delete-all-row' : ''; ?>">
							<th scope="row"><?php echo esc_html( $action['label'] ); ?></th>
							<td><?php echo esc_html( $action['description'] ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yy-dmm-delete-form">
									<input type="hidden" name="action" value="yy_dmm_auto_post_delete_content">
									<input type="hidden" name="yy_dmm_delete_type" value="<?php echo esc_attr( $action_key ); ?>">
									<?php wp_nonce_field( 'yy_dmm_auto_post_delete_content' ); ?>
									<?php if ( 'all' === $action_key ) : ?>
										<input type="text" name="yy_dmm_delete_confirm" value="" placeholder="全削除" aria-label="全削除の確認入力">
									<?php endif; ?>
									<button type="submit" class="button yy-dmm-danger-button" onclick="return confirm('<?php echo esc_js( $action['confirm'] ); ?>');"><?php echo esc_html( $action['button'] ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">全削除のみ、確認欄に「全削除」と入力してから実行してください。カテゴリー全削除ではWordPressの既定カテゴリーを削除できない場合があります。</p>
		</div>
		<?php
	}

	private function render_delete_result_notice( $result ) {
		if ( ! is_array( $result ) ) {
			return;
		}

		$errors = isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : array();
		$counts = isset( $result['counts'] ) && is_array( $result['counts'] ) ? $result['counts'] : array();
		$notice_class = empty( $errors ) ? 'notice-success' : 'notice-error';
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<p><strong><?php echo esc_html( $result['label'] ?? 'クリーンアップ' ); ?></strong></p>
			<?php if ( $counts ) : ?>
				<ul>
					<?php foreach ( $counts as $label => $count ) : ?>
						<li>
							<?php echo esc_html( $label ); ?>:
							<?php if ( isset( $count['cleared'] ) ) : ?>
								クリア <?php echo esc_html( absint( $count['cleared'] ) ); ?>
							<?php elseif ( isset( $count['repaired'] ) && ! isset( $count['deleted'] ) ) : ?>
								修復 <?php echo esc_html( absint( $count['repaired'] ) ); ?>
							<?php else : ?>
								削除 <?php echo esc_html( absint( $count['deleted'] ?? 0 ) ); ?>
							<?php endif; ?>
							<?php if ( ! empty( $count['skipped'] ) ) : ?>
								/ スキップ <?php echo esc_html( absint( $count['skipped'] ) ); ?>
							<?php endif; ?>
							<?php if ( isset( $count['deleted'] ) && ! empty( $count['repaired'] ) ) : ?>
								/ 修復 <?php echo esc_html( absint( $count['repaired'] ) ); ?>
							<?php endif; ?>
							<?php if ( ! empty( $count['failed'] ) ) : ?>
								/ 失敗 <?php echo esc_html( absint( $count['failed'] ) ); ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $errors ) : ?>
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	private function delete_actions() {
		return array(
			'cache'      => array(
				'label'       => 'キャッシュクリア',
				'description' => 'WordPressキャッシュ、一時データ、リライトルールをクリアします。',
				'button'      => 'キャッシュをクリア',
				'confirm'     => 'キャッシュをクリアします。実行しますか？',
			),
			'repair_categories' => array(
				'label'       => 'カテゴリーなし投稿修復',
				'description' => 'カテゴリーが付いていない投稿へWordPressの既定カテゴリーを設定します。',
				'button'      => 'カテゴリーを修復',
				'confirm'     => 'カテゴリーが付いていない投稿へ既定カテゴリーを設定します。実行しますか？',
			),
			'posts'      => array(
				'label'       => '投稿記事全削除',
				'description' => '投稿タイプ「post」の記事をすべて完全削除します。',
				'button'      => '投稿記事を削除',
				'confirm'     => '投稿記事をすべて完全削除します。実行しますか？',
			),
			'pages'      => array(
				'label'       => '固定記事全削除',
				'description' => '固定ページをすべて完全削除します。',
				'button'      => '固定記事を削除',
				'confirm'     => '固定ページをすべて完全削除します。実行しますか？',
			),
			'categories' => array(
				'label'       => 'カテゴリー全削除',
				'description' => 'カテゴリーをすべて削除します。既定カテゴリーは削除できない場合があります。',
				'button'      => 'カテゴリーを削除',
				'confirm'     => 'カテゴリーをすべて削除します。実行しますか？',
			),
			'tags'       => array(
				'label'       => 'タグ全削除',
				'description' => '投稿タグをすべて削除します。',
				'button'      => 'タグを削除',
				'confirm'     => 'タグをすべて削除します。実行しますか？',
			),
			'media'      => array(
				'label'       => 'メディア全削除',
				'description' => 'メディアライブラリの添付ファイルをすべて完全削除します。',
				'button'      => 'メディアを削除',
				'confirm'     => 'メディアをすべて完全削除します。実行しますか？',
			),
			'all'        => array(
				'label'       => '全削除',
				'description' => '投稿記事、固定記事、カテゴリー、タグ、メディアをすべて削除します。',
				'button'      => '全削除',
				'confirm'     => '投稿記事、固定記事、カテゴリー、タグ、メディアをすべて削除します。本当に実行しますか？',
			),
		);
	}

	private function run_delete_action( $delete_type ) {
		$actions = $this->delete_actions();
		$result = array(
			'label'  => $actions[ $delete_type ]['label'] ?? 'クリーンアップ',
			'counts' => array(),
			'errors' => array(),
		);
		$targets = 'all' === $delete_type ? array( 'posts', 'pages', 'categories', 'tags', 'media' ) : array( $delete_type );

		foreach ( $targets as $target ) {
			$target_result = $this->delete_content_target( $target );
			$result['counts'][ $target_result['label'] ] = $target_result['count'];
			if ( ! empty( $target_result['errors'] ) ) {
				$result['errors'] = array_merge( $result['errors'], $target_result['errors'] );
			}
		}

		return $result;
	}

	private function delete_content_target( $target ) {
		if ( 'cache' === $target ) {
			return $this->clear_cache();
		}

		if ( 'posts' === $target ) {
			return $this->delete_posts_by_type( 'post', '投稿記事' );
		}

		if ( 'repair_categories' === $target ) {
			$repair_result = $this->repair_posts_without_categories();
			return array(
				'label'  => 'カテゴリーなし投稿',
				'count'  => array(
					'repaired' => $repair_result['repaired'],
					'failed'   => $repair_result['failed'],
					'skipped'  => 0,
				),
				'errors' => $repair_result['errors'],
			);
		}

		if ( 'pages' === $target ) {
			return $this->delete_posts_by_type( 'page', '固定記事' );
		}

		if ( 'media' === $target ) {
			return $this->delete_posts_by_type( 'attachment', 'メディア' );
		}

		if ( 'categories' === $target ) {
			return $this->delete_terms_by_taxonomy( 'category', 'カテゴリー' );
		}

		if ( 'tags' === $target ) {
			return $this->delete_terms_by_taxonomy( 'post_tag', 'タグ' );
		}

		return array(
			'label'  => '不明',
			'count'  => array( 'deleted' => 0, 'failed' => 0, 'skipped' => 0 ),
			'errors' => array( '削除対象が正しくありません。' ),
		);
	}

	private function clear_cache() {
		$count = array( 'cleared' => 0, 'failed' => 0, 'skipped' => 0 );
		$errors = array();

		if ( function_exists( 'wp_cache_flush' ) ) {
			$flushed = wp_cache_flush();
			if ( false === $flushed ) {
				$count['failed']++;
				$errors[] = 'WordPressオブジェクトキャッシュをクリアできませんでした。';
			} else {
				$count['cleared']++;
			}
		} else {
			$count['skipped']++;
		}

		if ( function_exists( 'delete_expired_transients' ) ) {
			delete_expired_transients();
			$count['cleared']++;
		}

		$transient_count = $this->delete_plugin_transients();
		if ( $transient_count > 0 ) {
			$count['cleared'] += $transient_count;
		}

		flush_rewrite_rules( false );
		$count['cleared']++;

		return array(
			'label'  => 'キャッシュ',
			'count'  => $count,
			'errors' => $errors,
		);
	}

	private function delete_plugin_transients() {
		global $wpdb;

		$deleted = 0;
		$prefixes = array(
			'_transient_yy_dmm_auto_post_',
			'_transient_timeout_yy_dmm_auto_post_',
			'_site_transient_yy_dmm_auto_post_',
			'_site_transient_timeout_yy_dmm_auto_post_',
		);

		foreach ( $prefixes as $prefix ) {
			$like = $wpdb->esc_like( $prefix ) . '%';
			$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
			if ( false !== $result ) {
				$deleted += absint( $result );
			}
		}

		return $deleted;
	}

	private function delete_posts_by_type( $post_type, $label ) {
		$count = array( 'deleted' => 0, 'failed' => 0, 'skipped' => 0 );
		$errors = array();
		$failed_ids = array();
		$post_statuses = array_values( get_post_stati( array(), 'names' ) );

		do {
			$args = array(
				'post_type'        => $post_type,
				'post_status'      => $post_statuses,
				'posts_per_page'   => 100,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			);
			if ( $failed_ids ) {
				$args['post__not_in'] = $failed_ids;
			}

			$ids = get_posts( $args );
			foreach ( $ids as $post_id ) {
				$post_id = absint( $post_id );
				$deleted = 'attachment' === $post_type ? wp_delete_attachment( $post_id, true ) : wp_delete_post( $post_id, true );
				if ( $deleted ) {
					$count['deleted']++;
				} else {
					$count['failed']++;
					$failed_ids[] = $post_id;
					$errors[] = sprintf( '%s ID %d を削除できませんでした。', $label, $post_id );
				}
			}
		} while ( ! empty( $ids ) );

		return array(
			'label'  => $label,
			'count'  => $count,
			'errors' => $errors,
		);
	}

	private function delete_terms_by_taxonomy( $taxonomy, $label ) {
		$count = array( 'deleted' => 0, 'failed' => 0, 'skipped' => 0, 'repaired' => 0 );
		$errors = array();
		$excluded_ids = array();
		$default_category_id = 'category' === $taxonomy ? absint( get_option( 'default_category' ) ) : 0;

		do {
			$args = array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 100,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			);
			if ( $excluded_ids ) {
				$args['exclude'] = $excluded_ids;
			}

			$term_ids = get_terms( $args );
			if ( is_wp_error( $term_ids ) ) {
				$errors[] = $term_ids->get_error_message();
				break;
			}

			foreach ( $term_ids as $term_id ) {
				$term_id = absint( $term_id );
				if ( $default_category_id && $default_category_id === $term_id ) {
					$count['skipped']++;
					$excluded_ids[] = $term_id;
					continue;
				}

				$deleted = wp_delete_term( $term_id, $taxonomy );
				if ( is_wp_error( $deleted ) ) {
					$count['failed']++;
					$excluded_ids[] = $term_id;
					$errors[] = $deleted->get_error_message();
				} elseif ( $deleted ) {
					$count['deleted']++;
				} else {
					$count['failed']++;
					$excluded_ids[] = $term_id;
					$errors[] = sprintf( '%s ID %d を削除できませんでした。', $label, $term_id );
				}
			}
		} while ( ! empty( $term_ids ) );

		if ( 'category' === $taxonomy ) {
			$repair_result = $this->repair_posts_without_categories();
			$count['repaired'] += $repair_result['repaired'];
			$count['failed'] += $repair_result['failed'];
			$errors = array_merge( $errors, $repair_result['errors'] );
		}

		return array(
			'label'  => $label,
			'count'  => $count,
			'errors' => $errors,
		);
	}

	private function repair_posts_without_categories() {
		$result = array(
			'repaired' => 0,
			'failed'   => 0,
			'errors'   => array(),
		);

		if ( ! taxonomy_exists( 'category' ) ) {
			return $result;
		}

		$default_category_id = absint( get_option( 'default_category' ) );
		$default_category = $default_category_id ? get_term( $default_category_id, 'category' ) : null;
		if ( ! $default_category || is_wp_error( $default_category ) ) {
			$result['errors'][] = '既定カテゴリーが見つからないため、カテゴリー未設定の投稿を修復できませんでした。';
			return $result;
		}

		$processed_ids = array();
		$post_statuses = array_values( get_post_stati( array(), 'names' ) );

		do {
			$args = array(
				'post_type'        => 'post',
				'post_status'      => $post_statuses,
				'posts_per_page'   => 100,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			);
			if ( $processed_ids ) {
				$args['post__not_in'] = $processed_ids;
			}

			$post_ids = get_posts( $args );
			foreach ( $post_ids as $post_id ) {
				$post_id = absint( $post_id );
				$processed_ids[] = $post_id;
				if ( has_category( '', $post_id ) ) {
					continue;
				}

				$set_result = wp_set_post_terms( $post_id, array( $default_category_id ), 'category' );
				if ( is_wp_error( $set_result ) ) {
					$result['failed']++;
					$result['errors'][] = sprintf( '投稿 ID %d に既定カテゴリーを設定できませんでした。', $post_id );
				} else {
					$result['repaired']++;
				}
			}
		} while ( ! empty( $post_ids ) );

		return $result;
	}

	private function body_section_labels() {
		return array(
			'sample_movie'        => 'サンプル動画',
			'top_affiliate_button'=> '公式ボタン1',
			'product_info'        => '作品情報テーブル',
			'description'         => '商品説明文',
			'middle_affiliate_button' => '公式ボタン2',
			'sample_images'       => 'サンプル画像',
			'bottom_affiliate_button' => '公式ボタン3',
		);
	}

	private function product_info_field_labels() {
		return array(
			'title'      => 'タイトル',
			'product_id' => '商品ID',
			'content_id' => '品番',
			'service'    => 'サービス',
			'floor'      => 'フロア',
			'category_name' => 'カテゴリ名',
			'maker'      => 'メーカー',
			'label'      => 'レーベル',
			'genres'     => 'ジャンル',
			'date'       => '発売日・配信日',
			'volume'     => '収録時間',
			'price'      => '価格',
			'list_price' => '定価',
			'delivery_prices' => '配信価格',
			'product_url' => '商品ページ',
		);
	}

	private function sample_movie_size_labels() {
		return array(
			'size_720_480' => '720 x 480',
			'size_644_414' => '644 x 414',
			'size_560_360' => '560 x 360',
			'size_476_306' => '476 x 306',
		);
	}

	private function featured_image_source_labels() {
		return array(
			'image_small' => '商品画像 small',
			'image_list'  => '商品画像 list',
			'sample_image_1' => 'サンプル画像 1枚目',
			'sample_image_2' => 'サンプル画像 2枚目',
			'sample_image_3' => 'サンプル画像 3枚目',
			'sample_image_4' => 'サンプル画像 4枚目',
			'sample_image_5' => 'サンプル画像 5枚目',
		);
	}

	private function sample_image_size_labels() {
		return array(
			'sample_l' => '大きい画像 sample_l',
			'sample_s' => '小さい画像 sample_s',
		);
	}

	private function text_row( $label, $key, $value, $placeholder = '', $description = '' ) {
		$id = 'yy-dmm-' . sanitize_key( $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input id="<?php echo esc_attr( $id ); ?>" class="regular-text" type="text" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function title_template_tokens_row() {
		$tokens = YY_DMM_Auto_Post_Post_Builder::title_template_token_options();
		?>
		<tr>
			<th scope="row">使用可能フィールド</th>
			<td>
				<div class="yy-dmm-token-grid" aria-live="polite">
					<?php foreach ( $tokens as $token => $label ) : ?>
						<div class="yy-dmm-token-item">
							<div>
								<code><?php echo esc_html( $token ); ?></code>
								<span><?php echo esc_html( $label ); ?></span>
							</div>
							<button type="button" class="button button-small yy-dmm-copy-token" data-copy-text="<?php echo esc_attr( $token ); ?>">コピー</button>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="description">コピーしたフィールドを投稿タイトル形式へ貼り付けてください。</p>
			</td>
		</tr>
		<?php
	}

	private function number_row( $label, $key, $value, $min, $max, $description = '' ) {
		$id = 'yy-dmm-' . sanitize_key( $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input id="<?php echo esc_attr( $id ); ?>" type="number" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function max_posts_row( $settings ) {
		$id = 'yy-dmm-max-posts';
		$all_id = 'yy-dmm-max-posts-all';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>">1回の最大投稿数</label></th>
			<td>
				<input id="<?php echo esc_attr( $id ); ?>" type="number" min="1" max="50" name="yy_dmm_auto_post_settings[max_posts]" value="<?php echo esc_attr( $settings['max_posts'] ); ?>" <?php disabled( ! empty( $settings['max_posts_all'] ), true ); ?>>
				<label class="yy-dmm-inline-checkbox" for="<?php echo esc_attr( $all_id ); ?>">
					<input type="hidden" name="yy_dmm_auto_post_settings[max_posts_all]" value="0">
					<input id="<?php echo esc_attr( $all_id ); ?>" type="checkbox" name="yy_dmm_auto_post_settings[max_posts_all]" value="1" <?php checked( ! empty( $settings['max_posts_all'] ), true ); ?>>
					すべて投稿する
				</label>
			</td>
		</tr>
		<?php
	}

	private function select_row( $label, $key, $value, $options, $description = '' ) {
		$id = 'yy-dmm-' . sanitize_key( $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $id ); ?>" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>]">
					<?php foreach ( $options as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function checkbox_row( $label, $key, $value, $description = '' ) {
		$id = 'yy-dmm-' . sanitize_key( $key );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="hidden" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>]" value="0">
					<input id="<?php echo esc_attr( $id ); ?>" type="checkbox" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $value, 1 ); ?>>
					ON
				</label>
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function category_taxonomy_matrix_row( $label, $settings ) {
		$options = YY_DMM_Auto_Post_Settings::taxonomy_iteminfo_options();
		$category_values = is_array( $settings['category_iteminfo_keys'] ?? null ) ? $settings['category_iteminfo_keys'] : array();
		$parent_values = is_array( $settings['category_parent_iteminfo_keys'] ?? null ) ? $settings['category_parent_iteminfo_keys'] : array();
		$slug_sources = is_array( $settings['category_iteminfo_slug_sources'] ?? null ) ? $settings['category_iteminfo_slug_sources'] : array();
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<table class="widefat striped yy-dmm-taxonomy-table">
					<thead>
						<tr>
							<th>項目</th>
							<th>使用</th>
							<th>親カテゴリ</th>
							<th>スラッグ</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $options as $option_key => $option_label ) : ?>
							<?php $source = 'name' === ( $slug_sources[ $option_key ] ?? 'id' ) ? 'name' : 'id'; ?>
							<tr>
								<th scope="row"><?php echo esc_html( $option_label ); ?></th>
								<td>
									<input type="hidden" name="yy_dmm_auto_post_settings[category_iteminfo_keys][<?php echo esc_attr( $option_key ); ?>]" value="0">
									<label>
										<input type="checkbox" name="yy_dmm_auto_post_settings[category_iteminfo_keys][<?php echo esc_attr( $option_key ); ?>]" value="1" <?php checked( ! empty( $category_values[ $option_key ] ), true ); ?>>
										使う
									</label>
								</td>
								<td>
									<input type="hidden" name="yy_dmm_auto_post_settings[category_parent_iteminfo_keys][<?php echo esc_attr( $option_key ); ?>]" value="0">
									<label>
										<input type="checkbox" name="yy_dmm_auto_post_settings[category_parent_iteminfo_keys][<?php echo esc_attr( $option_key ); ?>]" value="1" <?php checked( ! empty( $parent_values[ $option_key ] ), true ); ?>>
										付ける
									</label>
								</td>
								<td>
									<select name="yy_dmm_auto_post_settings[category_iteminfo_slug_sources][<?php echo esc_attr( $option_key ); ?>]">
										<option value="id" <?php selected( $source, 'id' ); ?>>ID</option>
										<option value="name" <?php selected( $source, 'name' ); ?>>名前</option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">親カテゴリを付けると「ジャンル」「メーカー」「レーベル」の下に子カテゴリーを作成します。</p>
			</td>
		</tr>
		<?php
	}

	private function tag_taxonomy_matrix_row( $label, $settings ) {
		$options = YY_DMM_Auto_Post_Settings::taxonomy_iteminfo_options();
		$tag_values = is_array( $settings['tag_iteminfo_keys'] ?? null ) ? $settings['tag_iteminfo_keys'] : array();
		$slug_sources = is_array( $settings['tag_iteminfo_slug_sources'] ?? null ) ? $settings['tag_iteminfo_slug_sources'] : array();
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<table class="widefat striped yy-dmm-taxonomy-table yy-dmm-taxonomy-table-tags">
					<thead>
						<tr>
							<th>項目</th>
							<th>使用</th>
							<th>スラッグ</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $options as $option_key => $option_label ) : ?>
							<?php $source = 'name' === ( $slug_sources[ $option_key ] ?? 'id' ) ? 'name' : 'id'; ?>
							<tr>
								<th scope="row"><?php echo esc_html( $option_label ); ?></th>
								<td>
									<input type="hidden" name="yy_dmm_auto_post_settings[tag_iteminfo_keys][<?php echo esc_attr( $option_key ); ?>]" value="0">
									<label>
										<input type="checkbox" name="yy_dmm_auto_post_settings[tag_iteminfo_keys][<?php echo esc_attr( $option_key ); ?>]" value="1" <?php checked( ! empty( $tag_values[ $option_key ] ), true ); ?>>
										使う
									</label>
								</td>
								<td>
									<select name="yy_dmm_auto_post_settings[tag_iteminfo_slug_sources][<?php echo esc_attr( $option_key ); ?>]">
										<option value="id" <?php selected( $source, 'id' ); ?>>ID</option>
										<option value="name" <?php selected( $source, 'name' ); ?>>名前</option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
	}

	private function ordered_checkbox_group_row( $label, $value_key, $order_key, $values, $orders, $options ) {
		$values = is_array( $values ) ? $values : array();
		$orders = is_array( $orders ) ? $orders : array();
		$ordered_options = $options;
		uksort(
			$ordered_options,
			static function ( $a, $b ) use ( $orders ) {
				$a_order = absint( $orders[ $a ] ?? 999 );
				$b_order = absint( $orders[ $b ] ?? 999 );
				if ( $a_order === $b_order ) {
					return strcmp( $a, $b );
				}

				return $a_order <=> $b_order;
			}
		);
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<table class="widefat striped yy-dmm-order-table">
					<thead>
						<tr>
							<th>表示</th>
							<th>項目</th>
							<th>表示順</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $ordered_options as $option_key => $option_label ) : ?>
							<tr>
								<td>
									<input type="hidden" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $value_key ); ?>][<?php echo esc_attr( $option_key ); ?>]" value="0">
									<input type="checkbox" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $value_key ); ?>][<?php echo esc_attr( $option_key ); ?>]" value="1" <?php checked( ! empty( $values[ $option_key ] ), true ); ?>>
								</td>
								<td><?php echo esc_html( $option_label ); ?></td>
								<td>
									<input class="small-text" type="number" min="1" max="999" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $order_key ); ?>][<?php echo esc_attr( $option_key ); ?>]" value="<?php echo esc_attr( absint( $orders[ $option_key ] ?? 10 ) ); ?>">
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">表示順は小さい番号ほど上に表示されます。</p>
			</td>
		</tr>
		<?php
	}

	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'yy-dmm-fanza-auto-post' ) );
		}
	}
}
