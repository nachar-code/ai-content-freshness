<?php
/**
 * AI Engine API Client.
 *
 * @package AI_Content_Freshness
 */

namespace AICFM;

defined( 'ABSPATH' ) || exit;

/**
 * Class API_Client
 *
 * Handles requests to Gemini and OpenAI with native search grounding.
 */
class API_Client {

	/**
	 * Configured plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( AICFM_SETTINGS_KEY, array() );
	}

	/**
	 * Execute freshness audit for post content and target keyword.
	 *
	 * @param string $title   Post title.
	 * @param string $content Post HTML/raw content.
	 * @param string $keyword Target SEO keyword.
	 * @return array|\WP_Error Validated audit data or WP_Error.
	 */
	public function audit_content( $title, $content, $keyword ) {
		$engine  = isset( $this->settings['engine'] ) ? $this->settings['engine'] : 'gemini';
		$api_key = ( 'openai' === $engine )
			? ( $this->settings['openai_key'] ?? '' )
			: ( $this->settings['gemini_key'] ?? '' );

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'missing_api_key',
				__( 'API key is missing in Content Freshness settings.', 'ai-content-freshness-monitor' )
			);
		}

		// Clean content and truncate to 2,500 words.
		$clean_content = wp_strip_all_tags( $content );
		if ( empty( trim( $clean_content ) ) ) {
			return new \WP_Error(
				'empty_content',
				__( 'Post content is empty or contains no readable text to audit.', 'ai-content-freshness-monitor' )
			);
		}

		$words = preg_split( '/\s+/', trim( $clean_content ) );
		if ( is_array( $words ) && count( $words ) > 2500 ) {
			$clean_content = implode( ' ', array_slice( $words, 0, 2500 ) );
		}

		$prompt = sprintf(
			"You are a senior SEO content auditor.\n" .
			"Target Keyword: %s\n" .
			"Post Title: %s\n" .
			"Post Content:\n%s\n\n" .
			"Perform a live web search for the target keyword to analyze top current ranking results.\n" .
			"Compare the post content against current ranking results.\n" .
			"Return ONLY a valid JSON object matching this schema:\n" .
			"{\n" .
			"  \"score\": 75,\n" .
			"  \"summary\": \"Short 2-sentence overview\",\n" .
			"  \"outdated_points\": [\n" .
			"    {\"claim\": \"string\", \"fix\": \"string\"}\n" .
			"  ],\n" .
			"  \"missing_topics\": [\n" .
			"    {\"topic\": \"string\", \"reason\": \"string\"}\n" .
			"  ],\n" .
			"  \"action_steps\": [\"string\", \"string\"]\n" .
			"}\n" .
			'Do NOT return any markdown wrapping or text outside the JSON.',
			$keyword,
			$title,
			$clean_content
		);

		if ( 'openai' === $engine ) {
			return $this->request_openai( $prompt, $api_key );
		}

		return $this->request_gemini( $prompt, $api_key );
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
	 * Send audit request to Google Gemini with Google Search tool.
	 *
	 * @param string $prompt  Constructed audit prompt.
	 * @param string $api_key Gemini API key.
	 * @return array|\WP_Error
	 */
	private function request_gemini( $prompt, $api_key ) {
		$model = ! empty( $this->settings['gemini_model'] ) ? $this->settings['gemini_model'] : 'gemini-3.8-flash';
		$model = preg_replace( '#^models/#', '', trim( $model ) );

		$endpoint = add_query_arg(
			'key',
			rawurlencode( $api_key ),
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent'
		);

		$payload = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $prompt ),
					),
				),
			),
			'tools'    => array(
				array(
					'google_search' => new \stdClass(),
				),
			),
		);

		add_action( 'http_api_curl', array( $this, 'force_ipv4_curl' ) );

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 60,
			)
		);

		remove_action( 'http_api_curl', array( $this, 'force_ipv4_curl' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$err_msg = isset( $data['error']['message'] )
				? $data['error']['message']
				: sprintf(
					/* translators: %d: HTTP status code */
					__( 'Gemini API responded with HTTP %d.', 'ai-content-freshness-monitor' ),
					$status_code
				);
			return new \WP_Error( 'gemini_api_error', $err_msg );
		}

		// Extract content text from parts.
		$raw_text = '';
		if ( isset( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$raw_text .= $part['text'];
				}
			}
		}

		if ( empty( $raw_text ) && isset( $data['candidates'][0]['finishReason'] ) && 'STOP' !== $data['candidates'][0]['finishReason'] ) {
			return new \WP_Error(
				'gemini_finish_reason',
				sprintf(
					/* translators: %s: Finish reason name */
					__( 'Gemini stopped generation due to: %s', 'ai-content-freshness-monitor' ),
					esc_html( $data['candidates'][0]['finishReason'] )
				)
			);
		}

		return $this->parse_and_validate_json( $raw_text );
	}

	/**
	 * Send audit request to OpenAI with web search.
	 *
	 * @param string $prompt  Constructed audit prompt.
	 * @param string $api_key OpenAI API key.
	 * @return array|\WP_Error
	 */
	private function request_openai( $prompt, $api_key ) {
		$endpoint = 'https://api.openai.com/v1/chat/completions';
		$model    = ! empty( $this->settings['openai_model'] ) ? $this->settings['openai_model'] : 'gpt-4o';

		$payload = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'tools'    => array(
				array(
					'type' => 'web_search',
				),
			),
		);

		add_action( 'http_api_curl', array( $this, 'force_ipv4_curl' ) );

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 60,
			)
		);

		remove_action( 'http_api_curl', array( $this, 'force_ipv4_curl' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$err_msg = isset( $data['error']['message'] )
				? $data['error']['message']
				: sprintf(
					/* translators: %d: HTTP status code */
					__( 'OpenAI API responded with HTTP %d.', 'ai-content-freshness-monitor' ),
					$status_code
				);
			return new \WP_Error( 'openai_api_error', $err_msg );
		}

		$raw_text = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';

		return $this->parse_and_validate_json( $raw_text );
	}

	/**
	 * Parse, sanitize, and validate JSON response from AI engine.
	 *
	 * @param string $raw_text Raw model text output.
	 * @return array|\WP_Error
	 */
	public function parse_and_validate_json( $raw_text ) {
		if ( empty( $raw_text ) ) {
			return new \WP_Error( 'empty_response', __( 'AI engine returned an empty response.', 'ai-content-freshness-monitor' ) );
		}

		// Strip markdown code block wrapping.
		$cleaned = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw_text ) );
		$cleaned = preg_replace( '/\s*```$/', '', $cleaned );

		// Extract JSON substring if surrounded by extra text.
		if ( preg_match( '/\{[\s\S]*\}/', $cleaned, $matches ) ) {
			$cleaned = $matches[0];
		}

		$decoded = json_decode( $cleaned, true );

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'invalid_json',
				__( 'Failed to parse audit results into valid JSON format.', 'ai-content-freshness-monitor' )
			);
		}

		// Validate required schema properties.
		$required_keys = array( 'score', 'summary', 'outdated_points', 'missing_topics', 'action_steps' );
		foreach ( $required_keys as $key ) {
			if ( ! array_key_exists( $key, $decoded ) ) {
				return new \WP_Error(
					'missing_schema_key',
					sprintf(
						/* translators: %s: Missing JSON key */
						__( 'Audit payload is missing required key: "%s".', 'ai-content-freshness-monitor' ),
						$key
					)
				);
			}
		}

		// Normalize data types.
		$normalized = array(
			'score'           => min( 100, max( 0, absint( $decoded['score'] ) ) ),
			'summary'         => sanitize_text_field( $decoded['summary'] ),
			'outdated_points' => array(),
			'missing_topics'  => array(),
			'action_steps'    => array(),
		);

		if ( is_array( $decoded['outdated_points'] ) ) {
			foreach ( $decoded['outdated_points'] as $point ) {
				if ( is_array( $point ) ) {
					$normalized['outdated_points'][] = array(
						'claim' => sanitize_text_field( $point['claim'] ?? '' ),
						'fix'   => sanitize_text_field( $point['fix'] ?? '' ),
					);
				}
			}
		}

		if ( is_array( $decoded['missing_topics'] ) ) {
			foreach ( $decoded['missing_topics'] as $topic ) {
				if ( is_array( $topic ) ) {
					$normalized['missing_topics'][] = array(
						'topic'  => sanitize_text_field( $topic['topic'] ?? '' ),
						'reason' => sanitize_text_field( $topic['reason'] ?? '' ),
					);
				}
			}
		}

		if ( is_array( $decoded['action_steps'] ) ) {
			foreach ( $decoded['action_steps'] as $step ) {
				if ( is_string( $step ) ) {
					$normalized['action_steps'][] = sanitize_text_field( $step );
				}
			}
		}

		return $normalized;
	}
}
