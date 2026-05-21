<?php
/**
 * Plugin Name: DMM/FANZA Auto Post
 * Description: DMM/FANZA Affiliate APIの商品情報を使ってWordPressへ自動投稿するプラグインです。
 * Version: 1.0.0
 * Author: yaoya
 * License: GPLv2 or later
 * Text Domain: yy-dmm-fanza-auto-post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YY_DMM_AUTO_POST_VERSION', '1.0.0' );
define( 'YY_DMM_AUTO_POST_FILE', __FILE__ );
define( 'YY_DMM_AUTO_POST_DIR', plugin_dir_path( __FILE__ ) );
define( 'YY_DMM_AUTO_POST_URL', plugin_dir_url( __FILE__ ) );
define( 'YY_DMM_AUTO_POST_BASENAME', plugin_basename( __FILE__ ) );

$yy_dmm_auto_post_includes = array(
	'includes/class-settings.php',
	'includes/class-logger.php',
	'includes/class-api-client.php',
	'includes/class-scraper.php',
	'includes/class-media.php',
	'includes/class-post-builder.php',
	'includes/class-post-manager.php',
	'includes/class-cron.php',
	'includes/class-admin.php',
	'includes/class-plugin.php',
);

foreach ( $yy_dmm_auto_post_includes as $yy_dmm_auto_post_file ) {
	require_once YY_DMM_AUTO_POST_DIR . $yy_dmm_auto_post_file;
}

register_activation_hook( __FILE__, array( 'YY_DMM_Auto_Post_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'YY_DMM_Auto_Post_Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		YY_DMM_Auto_Post_Plugin::instance()->run();
	}
);
