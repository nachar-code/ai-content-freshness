<?php
/**
 * AJAX Handlers Class.
 *
 * @package AI_Content_Freshness
 */

namespace AICFM;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ajax_Handlers
 *
 * Handles AJAX actions for connection testing and audits.
 */
class Ajax_Handlers {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_aicfm_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'wp_ajax_aicfm_execute_post_audit', array( $this, 'handle_execute_post_audit' ) );
		add_action( 'wp_ajax_aicfm_save_row_keyword', array( $this, 'handle_save_row_keyword' ) );
	}

	/**
	 * Handle AJAX request to save a post's target keyword inline from the inventory dashboard.
	 *
	 * @return void
	 */
	public function handle_save_row_keyword() {
		// Verify nonce.
		check_ajax_referer( 'aicfm_save_keyword_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid post ID provided.', 'ai-content-freshness-monitor' ) )
			);
		}

		// Check edit permission for this post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Unauthorized to edit this post.', 'ai-content-freshness-monitor' ) ),
				403
			);
		}

		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		update_post_meta( $post_id, '_aicfm_target_keyword', $keyword );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Saved', 'ai-content-freshness-monitor' ),
				'keyword' => $keyword,
			)
		);
	}

	/**
	 * Handle AJAX connection test for Gemini or OpenAI.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		// Verify nonce.
		check_ajax_referer( 'aicfm_admin_nonce', 'nonce' );

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Unauthorized user.', 'ai-content-freshness-monitor' ) ),
				403
			);
		}

		// Retrieve and sanitize inputs.
		$engine  = isset( $_POST['engine'] ) ? sanitize_text_field( wp_unslash( $_POST['engine'] ) ) : '';
		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$model   = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';

		if ( empty( $api_key ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'API key cannot be empty.', 'ai-content-freshness-monitor' ) )
			);
		}

		if ( 'gemini' === $engine ) {
			$this->test_gemini_connection( $api_key, $model );
		} elseif ( 'openai' === $engine ) {
			$this->test_openai_connection( $api_key, $model );
		} else {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid AI engine specified.', 'ai-content-freshness-monitor' ) )
			);
		}
	}

	/**
	 * Handle AJAX request to execute a post audit.
	 *
	 * @return void
	 */
	public function handle_execute_post_audit() {
		// Verify nonce.
		check_ajax_referer( 'aicfm_post_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid post ID provided.', 'ai-content-freshness-monitor' ) )
			);
		}

		// Check edit permission.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'You do not have permission to audit this post.', 'ai-content-freshness-monitor' ) ),
				403
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Post not found.', 'ai-content-freshness-monitor' ) )
			);
		}

		// Retrieve or update target keyword.
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		if ( ! empty( $keyword ) ) {
			update_post_meta( $post_id, '_aicfm_target_keyword', $keyword );
		} else {
			$keyword = (string) get_post_meta( $post_id, '_aicfm_target_keyword', true );
		}

		if ( empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Please provide a target keyword.', 'ai-content-freshness-monitor' ) )
			);
		}

		// Retrieve post title & content, preferring live editor inputs if provided.
		$raw_title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$title     = '' !== trim( $raw_title ) ? $raw_title : $post->post_title;

		$raw_content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$content     = '' !== trim( (string) $raw_content ) ? wp_kses_post( $raw_content ) : $post->post_content;

		// Execute audit using API Client.
		$client = new API_Client();
		$result = $client->audit_content( $title, $content, $keyword );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => esc_html( $result->get_error_message() ) )
			);
		}

		// Persist audit results and timestamp in post meta.
		$timestamp_mysql = current_time( 'mysql' );
		update_post_meta( $post_id, '_aicfm_audit_report', $result );
		update_post_meta( $post_id, '_aicfm_last_audited', $timestamp_mysql );

		$formatted_date = date_i18n(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			strtotime( $timestamp_mysql )
		);

		wp_send_json_success(
			array(
				'report'       => $result,
				'last_audited' => $formatted_date,
			)
		);
	}

	/**
	 * Force cURL to resolve using IPv4 to avoid IPv6 timeout delays on local/Windows hosts.
	 *
	 * @param resource $handle cURL handle.
	 * @return void
	 */
	public function force_ipv4_curl( $handle ) {
		if ( defined( 'CURLOPT_IPRESOLVE' ) && defined( 'CURL_IPRESOLVE_V4' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		}
	}

	/**
	 * Test Google Gemini connection.
	 *
	 * @param string $api_key Gemini API key.
	 * @param string $model   Optional model name.
	 * @return void
	 */
	private function test_gemini_connection( $api_key, $model = '' ) {
		if ( empty( $model ) ) {
			$options = get_option( AICFM_SETTINGS_KEY, array() );
			$model   = ! empty( $options['gemini_model'] ) ? $options['gemini_model'] : 'gemini-3.8-flash';
		}
		$model = preg_replace( '#^models/#', '', trim( $model ) );

		add_action( 'http_api_curl', array( $this, 'force_ipv4_curl' ) );

		// Fast verification via Model metadata endpoint.
		$endpoint = add_query_arg(
			'key',
			rawurlencode( $api_key ),
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model )
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 45,
			)
		);

		// Fallback check: if model name returned 404, check models list to verify key.
		if ( ! is_wp_error( $response ) && 404 === wp_remote_retrieve_response_code( $response ) ) {
			$list_endpoint = add_query_arg(
				'key',
				rawurlencode( $api_key ),
				'https://generativelanguage.googleapis.com/v1beta/models'
			);
			$response      = wp_remote_get(
				$list_endpoint,
				array(
					'timeout' => 45,
				)
			);
		}

		remove_action( 'http_api_curl', array( $this, 'force_ipv4_curl' ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Error message from WP_Error */
						esc_html__( 'Connection request failed: %s', 'ai-content-freshness-monitor' ),
						esc_html( $response->get_error_message() )
					),
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 === $status_code ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: Model name */
						esc_html__( 'Google Gemini API key and model (%s) verified successfully!', 'ai-content-freshness-monitor' ),
						esc_html( $model )
					),
				)
			);
		} else {
			$err_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf(
				/* translators: %d: HTTP status code */
				__( 'API responded with HTTP status %d.', 'ai-content-freshness-monitor' ),
				$status_code
			);

			wp_send_json_error(
				array(
					'message' => esc_html( $err_msg ),
				)
			);
		}
	}

	/**
	 * Test OpenAI connection.
	 *
	 * @param string $api_key OpenAI API key.
	 * @param string $model   Optional model name.
	 * @return void
	 */
	private function test_openai_connection( $api_key, $model = '' ) {
		$endpoint = 'https://api.openai.com/v1/models';

		add_action( 'http_api_curl', array( $this, 'force_ipv4_curl' ) );

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 45,
			)
		);

		remove_action( 'http_api_curl', array( $this, 'force_ipv4_curl' ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Error message from WP_Error */
						esc_html__( 'Connection request failed: %s', 'ai-content-freshness-monitor' ),
						esc_html( $response->get_error_message() )
					),
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 === $status_code ) {
			wp_send_json_success(
				array(
					'message' => esc_html__( 'OpenAI API key verified successfully!', 'ai-content-freshness-monitor' ),
				)
			);
		} else {
			$err_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf(
				/* translators: %d: HTTP status code */
				__( 'API responded with HTTP status %d.', 'ai-content-freshness-monitor' ),
				$status_code
			);

			wp_send_json_error(
				array(
					'message' => esc_html( $err_msg ),
				)
			);
		}
	}
}
