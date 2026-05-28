<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Sample_Movie {
	const SHORTCODE = 'yy_dmm_sample_movie';

	public static function hooks() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'replace_legacy_video_tags' ), 12 );
	}

	public static function build_shortcode( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		return sprintf( '[%s url="%s"]', self::SHORTCODE, esc_attr( $url ) );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'url' => '',
			),
			$atts,
			self::SHORTCODE
		);

		return self::render( $atts['url'] );
	}

	public static function replace_legacy_video_tags( $content ) {
		if ( false === strpos( $content, 'yy-dmm-sample-movie' ) || false === strpos( $content, '<video' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'~<div\b(?=[^>]*\bclass=(["\'])[^"\']*\byy-dmm-sample-movie\b[^"\']*\1)[^>]*>\s*<video\b[^>]*\bsrc=(["\'])([^"\']+)\2[^>]*>\s*</video>\s*</div>~i',
			static function ( $matches ) {
				$rendered = self::render( html_entity_decode( $matches[3], ENT_QUOTES, get_bloginfo( 'charset' ) ) );
				return '' !== $rendered ? $rendered : $matches[0];
			},
			$content
		);
	}

	private static function render( $url ) {
		$url = self::sanitize_litevideo_url( $url );
		if ( '' === $url ) {
			return '';
		}

		$size      = self::extract_size( $url );
		$max_width = max( 1, absint( $size[0] ?? 1280 ) );

		return sprintf(
			'<div class="yy-dmm-sample-movie" style="margin:0 0 24px;"><div style="width:100%%;max-width:%1$dpx;margin:0 auto;padding-top:75%%;position:relative;"><iframe width="100%%" height="100%%" src="%2$s" loading="lazy" scrolling="no" frameborder="0" allow="fullscreen; encrypted-media; picture-in-picture" allowfullscreen style="position:absolute;top:0;left:0;display:block;width:100%%;height:100%%;border:0;"></iframe></div></div>',
			$max_width,
			esc_url( $url )
		);
	}

	private static function sanitize_litevideo_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$url = esc_url_raw( html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return '';
		}

		$host          = strtolower( $parts['host'] );
		$allowed_hosts = array( 'www.dmm.co.jp', 'www.dmm.com' );
		if ( ! in_array( $host, $allowed_hosts, true ) ) {
			return '';
		}

		if ( false === strpos( $parts['path'], '/litevideo/-/part/=' ) ) {
			return '';
		}

		return esc_url_raw( set_url_scheme( $url, 'https' ) );
	}

	private static function extract_size( $url ) {
		if ( preg_match( '~/size=(\d{2,4})_(\d{2,4})(?:/|$)~', $url, $matches ) ) {
			$width  = absint( $matches[1] );
			$height = absint( $matches[2] );
			if ( $width > 0 && $height > 0 ) {
				return array( $width, $height );
			}
		}

		return array( 720, 480 );
	}
}
