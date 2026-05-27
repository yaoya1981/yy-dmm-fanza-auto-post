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
		$tab      = isset( $_POST['yy_dmm_auto_post_tab'] ) ? sanitize_key( wp_unslash( $_POST['yy_dmm_auto_post_tab'] ) ) : 'api';

		YY_DMM_Auto_Post_Settings::update( $settings );
		YY_DMM_Auto_Post_Cron::reschedule();

		wp_safe_redirect( admin_url( 'admin.php?page=yy-dmm-fanza-auto-post&settings-updated=1&tab=' . $this->normalize_settings_tab( $tab ) ) );
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
					<a class="nav-tab <?php echo $active === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=yy-dmm-fanza-auto-post&tab=' . $key ) ); ?>">
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
			<?php $this->number_row( '1回の最大投稿数', 'max_posts', $settings['max_posts'], 1, 50 ); ?>
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
				<?php $this->text_row( '投稿タイトル形式', 'title_template', $settings['title_template'], '{title}｜{label}', '使用可能: {title}, {content_id}, {label}, {maker}, {date}' ); ?>
			</table>

			<h2>本文設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->ordered_checkbox_group_row( '表示する項目', 'body_sections', 'body_section_order', $settings['body_sections'], $settings['body_section_order'], $this->body_section_labels() ); ?>
			</table>

			<h2>作品情報テーブル</h2>
			<table class="form-table" role="presentation">
				<?php $this->ordered_checkbox_group_row( '表示する項目', 'product_info_fields', 'product_info_field_order', $settings['product_info_fields'], $settings['product_info_field_order'], $this->product_info_field_labels() ); ?>
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
			<h2>共通設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->select_row( 'スラッグ', 'term_slug_source', $settings['term_slug_source'], array( 'id' => 'IDを使用', 'name' => '名前を使用' ), 'カテゴリーとタグの両方に適用します。' ); ?>
			</table>

			<h2>カテゴリー設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->checkbox_row( 'カテゴリ作成', 'create_categories', $settings['create_categories'] ); ?>
				<?php $this->number_row( '親カテゴリID', 'parent_category_id', $settings['parent_category_id'], 0, 999999 ); ?>
				<?php $this->checkbox_row( '種類別の親カテゴリ作成', 'create_parent_categories', $settings['create_parent_categories'], 'ONの場合は「ジャンル」「メーカー」などの親カテゴリを作り、その下に各カテゴリを作成します。親カテゴリIDを指定した場合は、その下に作成します。' ); ?>
				<?php $this->iteminfo_checkbox_group_row( '使用する項目', 'category_iteminfo_keys', $settings['category_iteminfo_keys'] ); ?>
			</table>

			<h2>タグ設定</h2>
			<table class="form-table" role="presentation">
				<?php $this->checkbox_row( 'タグ作成', 'create_tags', $settings['create_tags'] ); ?>
				<?php $this->iteminfo_checkbox_group_row( '使用する項目', 'tag_iteminfo_keys', $settings['tag_iteminfo_keys'] ); ?>
			</table>
		</div>
		<?php
	}

	private function body_section_labels() {
		return array(
			'sample_movie'        => 'サンプル動画',
			'top_affiliate_button'=> '上部公式ボタン',
			'product_info'        => '作品情報テーブル',
			'description'         => '商品説明文',
			'sample_images'       => 'サンプル画像',
			'bottom_affiliate_button' => '下部公式ボタン',
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
			'director'   => '監督',
			'genres'     => 'ジャンル',
			'date'       => '発売日・配信日',
			'volume'     => '収録時間',
			'price'      => '価格',
			'list_price' => '定価',
			'delivery_prices' => '配信価格',
			'product_url' => '商品ページ',
			'affiliate_url' => 'アフィリエイトURL',
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

	private function iteminfo_checkbox_group_row( $label, $key, $values ) {
		$this->checkbox_group_row( $label, $key, $values, YY_DMM_Auto_Post_Settings::taxonomy_iteminfo_options() );
	}

	private function checkbox_group_row( $label, $key, $values, $options ) {
		$values = is_array( $values ) ? $values : array();
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<fieldset class="yy-dmm-checklist">
					<?php foreach ( $options as $option_key => $option_label ) : ?>
						<label>
							<input type="hidden" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $option_key ); ?>]" value="0">
							<input type="checkbox" name="yy_dmm_auto_post_settings[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $option_key ); ?>]" value="1" <?php checked( ! empty( $values[ $option_key ] ), true ); ?>>
							<?php echo esc_html( $option_label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
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
