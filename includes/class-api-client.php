<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_API_Client {
	const ENDPOINT = 'https://api.dmm.com/affiliate/v3/ItemList';

	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function fetch_items() {
		$data = $this->request( $this->build_params() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( isset( $data['result']['items'] ) && is_array( $data['result']['items'] ) ) {
			return $data['result']['items'];
		}

		if ( isset( $data['result']['message'] ) ) {
			return new WP_Error( 'yy_dmm_api_result_error', sanitize_text_field( $data['result']['message'] ) );
		}

		if ( isset( $data['message'] ) ) {
			return new WP_Error( 'yy_dmm_api_error', sanitize_text_field( $data['message'] ) );
		}

		return new WP_Error( 'yy_dmm_api_empty_items', 'DMM/FANZA APIから商品リストを取得できませんでした。' );
	}

	public function fetch_test_items( $keyword, $hits = 10 ) {
		$hits = max( 1, min( 10, absint( $hits ) ) );
		$data = $this->request(
			$this->build_params(
				array(
					'keyword' => sanitize_text_field( (string) $keyword ),
					'hits'    => $hits,
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( isset( $data['result']['message'] ) ) {
			return new WP_Error( 'yy_dmm_api_result_error', sanitize_text_field( $data['result']['message'] ) );
		}

		if ( isset( $data['message'] ) ) {
			return new WP_Error( 'yy_dmm_api_error', sanitize_text_field( $data['message'] ) );
		}

		$result = isset( $data['result'] ) && is_array( $data['result'] ) ? $data['result'] : array();
		$items  = isset( $result['items'] ) && is_array( $result['items'] ) ? array_slice( $result['items'], 0, $hits ) : array();

		return array(
			'total_count'   => absint( $result['total_count'] ?? count( $items ) ),
			'result_count'  => absint( $result['result_count'] ?? count( $items ) ),
			'display_count' => count( $items ),
			'items'         => $items,
			'response'      => $data,
		);
	}

	private function build_params( $overrides = array() ) {
		$params = array(
			'api_id'       => $this->settings['api_id'],
			'affiliate_id' => $this->settings['affiliate_id'],
			'site'         => $this->settings['site'],
			'service'      => $this->settings['service'],
			'floor'        => $this->settings['floor'],
			'hits'         => absint( $this->settings['hits'] ),
			'sort'         => $this->settings['sort'],
			'keyword'      => $this->settings['keyword'],
			'output'       => 'json',
		);

		return array_merge( $params, is_array( $overrides ) ? $overrides : array() );
	}

	private function request( $params ) {
		if ( empty( $this->settings['api_id'] ) || empty( $this->settings['affiliate_id'] ) ) {
			return new WP_Error( 'yy_dmm_missing_api_credentials', 'API IDとアフィリエイトIDを設定してください。' );
		}

		$response = wp_remote_get(
			add_query_arg( $params, self::ENDPOINT ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error(
				'yy_dmm_api_http_error',
				sprintf( 'DMM/FANZA APIのHTTPステータスが%sでした。', $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'yy_dmm_api_json_error', 'DMM/FANZA APIレスポンスのJSON解析に失敗しました。' );
		}

		return $data;
	}
}
