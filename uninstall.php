<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'yy_dmm_auto_post_cron_event' );

$settings = get_option( 'yy_dmm_auto_post_settings', array() );
if ( is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] ) ) {
	delete_option( 'yy_dmm_auto_post_settings' );
	delete_option( 'yy_dmm_auto_post_logs' );
}
