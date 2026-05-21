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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_yy_dmm_auto_post_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_yy_dmm_auto_post_run_manual', array( $this, 'handle_manual_run' ) );
	}

	public function add_menu() {
		add_menu_page(
			'DMM/FANZA自動投稿',
			'DMM/FANZA自動投稿',
			'manage_options',
			'yy-dmm-fanza-auto-post',
			array( $this, 'render_settings_page' ),
			'dashicons-update-alt',
			58
		);

		add_submenu_page( 'yy-dmm-fanza-auto-post', '設定', '設定', 'manage_options', 'yy-dmm-fanza-auto-post', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'yy-dmm-fanza-auto-post', '手動実行', '手動実行', 'manage_options', 'yy-dmm-fanza-auto-post-run', array( $this, 'render_manual_page' ) );
		add_submenu_page( 'yy-dmm-fanza-auto-post', '投稿ログ', '投稿ログ', 'manage_options', 'yy-dmm-fanza-auto-post-logs', array( $this, 'render_logs_page' ) );
		add_submenu_page( 'yy-dmm-fanza-auto-post', '使い方', '使い方', 'manage_options', 'yy-dmm-fanza-auto-post-guide', array( $this, 'render_guide_page' ) );
	}

	public function enqueue_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'yy-dmm-fanza-auto-post' ) ) {
			return;
		}

		wp_enqueue_style( 'yy-dmm-auto-post-admin', YY_DMM_AUTO_POST_URL . 'assets/admin.css', array(), YY_DMM_AUTO_POST_VERSION );
		wp_enqueue_script( 'yy-dmm-auto-post-admin', YY_DMM_AUTO_POST_URL . 'assets/admin.js', array(), YY_DMM_AUTO_POST_VERSION, true );
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'yy-dmm-fanza-auto-post' ) );
		}

		check_admin_referer( 'yy_dmm_auto_post_save_settings' );
		$settings = isset( $_POST['yy_dmm_auto_post_settings'] ) ? wp_unslash( $_POST['yy_dmm_auto_post_settings'] ) : array();
		YY_DMM_Auto_Post_Settings::update( $settings );
		YY_DMM_Auto_Post_Cron::reschedule();

		wp_safe_redirect( admin_url( 'admin.php?page=yy-dmm-fanza-auto-post&settings-updated=1' ) );
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

	public function render_settings_page() {
		$this->guard();
		$settings = YY_DMM_Auto_Post_Settings::get();
		?>
		<div class="wrap yy-dmm-admin">
			<h1>DMM/FANZA自動投稿 設定</h1>
			<?php if ( ! empty( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="yy_dmm_auto_post_save_settings">
				<?php wp_nonce_field( 'yy_dmm_auto_post_save_settings' ); ?>

				<h2>API設定</h2>
				<table class="form-table" role="presentation">
					<?php $this->text_row( 'API ID', 'api_id', $settings['api_id'] ); ?>
					<?php $this->text_row( 'アフィリエイトID', 'affiliate_id', $settings['affiliate_id'] ); ?>
					<?php $this->text_row( 'site', 'site', $settings['site'] ); ?>
					<?php $this->text_row( 'service', 'service', $settings['service'] ); ?>
					<?php $this->text_row( 'floor', 'floor', $settings['floor'] ); ?>
					<?php $this->text_row( 'keyword', 'keyword', $settings['keyword'] ); ?>
					<?php $this->text_row( 'sort', 'sort', $settings['sort'] ); ?>
					<?php $this->number_row( 'hits', 'hits', $settings['hits'], 1, 100 ); ?>
				</table>

				<h2>投稿設定</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="yy-dmm-post-status">投稿ステータス</label></th>
						<td>
							<select id="yy-dmm-post-status" name="yy_dmm_auto_post_settings[post_status]">
								<option value="draft" <?php selected( $settings['post_status'], 'draft' ); ?>>下書き</option>
								<option value="publish" <?php selected( $settings['post_status'], 'publish' ); ?>>公開</option>
							</select>
						</td>
					</tr>
					<?php $this->text_row( '投稿タイプ', 'post_type', $settings['post_type'] ); ?>
					<?php $this->number_row( '親カテゴリID', 'parent_category_id', $settings['parent_category_id'], 0, 999999 ); ?>
					<?php $this->checkbox_row( 'カテゴリ作成', 'create_categories', $settings['create_categories'] ); ?>
					<?php $this->checkbox_row( 'タグ作成', 'create_tags', $settings['create_tags'] ); ?>
					<?php $this->number_row( '1回の最大投稿数', 'max_posts', $settings['max_posts'], 1, 50 ); ?>
					<?php $this->checkbox_row( '重複投稿防止', 'prevent_duplicates', $settings['prevent_duplicates'] ); ?>
				</table>

				<h2>実行設定</h2>
				<table class="form-table" role="presentation">
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

				<?php submit_button( '設定を保存' ); ?>
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
				<?php submit_button( '今すぐ取得して投稿する', 'primary', 'submit', false ); ?>
			</form>
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
						<th>スキップ件数</th>
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
								<td><?php echo esc_html( absint( $log['skipped'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( implode( "\n", $log['errors'] ?? array() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="6">ログはまだありません。</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_guide_page() {
		$this->guard();
		?>
		<div class="wrap yy-dmm-admin">
			<h1>DMM/FANZA自動投稿 使い方</h1>
			<ol class="yy-dmm-guide">
				<li>DMM/FANZA Affiliate API IDとアフィリエイトIDを設定します。</li>
				<li>検索キーワード、floor、取得件数、投稿ステータスを設定します。</li>
				<li>必要に応じてカテゴリ作成、タグ作成、自動実行を有効にします。</li>
				<li>手動実行画面で「今すぐ取得して投稿する」を押すと、未投稿の商品だけを投稿します。</li>
				<li>サンプル画像とサンプル動画はAPI URLを本文内で直接表示し、保存する画像はアイキャッチのみです。</li>
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
				スキップ件数: <strong><?php echo esc_html( absint( $result['skipped'] ?? 0 ) ); ?></strong>
				エラー件数: <strong><?php echo esc_html( count( $result['errors'] ?? array() ) ); ?></strong>
			</p>
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

	private function text_row( $label, $key, $value, $placeholder = '' ) {
		$id = 'yy-dmm-' . sanitize_key( $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input id="<?php echo esc_attr( $id ); ?>" class="regular-text" type="text" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
			</td>
		</tr>
		<?php
	}

	private function number_row( $label, $key, $value, $min, $max ) {
		$id = 'yy-dmm-' . sanitize_key( $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input id="<?php echo esc_attr( $id ); ?>" type="number" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
			</td>
		</tr>
		<?php
	}

	private function checkbox_row( $label, $key, $value ) {
		$id = 'yy-dmm-' . sanitize_key( $key );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input id="<?php echo esc_attr( $id ); ?>" type="checkbox" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $value, 1 ); ?>>
					ON
				</label>
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
