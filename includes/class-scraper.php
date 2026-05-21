<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Scraper {
	const DETAIL_URL = 'https://www.dmm.co.jp/litevideo/-/detail/=/cid=%s/';

	public function fetch_description( $content_id ) {
		$content_id = sanitize_key( $content_id );
		if ( '' === $content_id ) {
			return '';
		}

		$response = wp_remote_get(
			sprintf( self::DETAIL_URL, rawurlencode( $content_id ) ),
			array(
				'timeout'     => 20,
				'redirection' => 5,
				'headers'     => array(
					'Cookie'     => 'age_check_done=1; cklg=ja',
					'User-Agent' => 'Mozilla/5.0 (WordPress; DMM/FANZA Auto Post)',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error(
				'yy_dmm_scrape_http_error',
				sprintf( '商品ページ取得のHTTPステータスが%sでした。', $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! preg_match( '/<script\b[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/is', $body, $matches ) ) {
			return '';
		}

		$json = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$data = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return '';
		}

		$queries = $data['props']['pageProps']['dehydratedState']['queries'] ?? array();
		if ( ! is_array( $queries ) ) {
			return '';
		}

		foreach ( $queries as $query ) {
			$text = $query['state']['data']['videoContent']['text'] ?? '';
			if ( is_string( $text ) && '' !== trim( $text ) ) {
				return $this->clean_description( $text );
			}
		}

		return '';
	}

	private function clean_description( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\\\\u003cbr\\\\u003e\\\\n|\\\\u003cbr\\\\u003e/i', "\n", $text );
		$text = preg_replace( '/<br\s*\/?>/i', "\n", $text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( "/\r\n|\r/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}
}
