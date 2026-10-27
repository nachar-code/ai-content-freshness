<?php
/**
 * Admin Settings Class.
 *
 * @package AI_Content_Freshness
 */

namespace AICFM;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Settings
 *
 * Manages plugin settings page and registration under Tools.
 */
class Admin_Settings {

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register settings submenu under Tools.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_submenu_page(
			'tools.php',
			__( 'Content Freshness', 'ai-content-freshness' ),
			__( 'Content Freshness', 'ai-content-freshness' ),
			'manage_options',
			'aicfm-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings with the WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'aicfm_settings_group',
			AICFM_SETTINGS_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'engine'       => 'gemini',
					'gemini_key'   => '',
					'gemini_model' => 'gemini-3.8-flash',
					'openai_key'   => '',
					'openai_model' => 'gpt-4o',
					'staleness_mo' => 6,
				),
			)
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Validate AI Engine.
		$allowed_engines    = array( 'gemini', 'openai' );
		$sanitized['engine'] = ( isset( $input['engine'] ) && in_array( $input['engine'], $allowed_engines, true ) )
			? $input['engine']
			: 'gemini';

		// Sanitize API keys.
		$sanitized['gemini_key'] = isset( $input['gemini_key'] ) ? sanitize_text_field( $input['gemini_key'] ) : '';
		$sanitized['openai_key'] = isset( $input['openai_key'] ) ? sanitize_text_field( $input['openai_key'] ) : '';

		// Sanitize models with default fallbacks.
		$gemini_model             = isset( $input['gemini_model'] ) ? sanitize_text_field( trim( $input['gemini_model'] ) ) : '';
		$gemini_model             = preg_replace( '#^models/#', '', $gemini_model );
		$sanitized['gemini_model'] = ! empty( $gemini_model ) ? $gemini_model : 'gemini-3.8-flash';

		$openai_model             = isset( $input['openai_model'] ) ? sanitize_text_field( trim( $input['openai_model'] ) ) : '';
		$sanitized['openai_model'] = ! empty( $openai_model ) ? $openai_model : 'gpt-4o';

		// Sanitize staleness threshold (months).
		$staleness_mo             = isset( $input['staleness_mo'] ) ? absint( $input['staleness_mo'] ) : 6;
		$sanitized['staleness_mo'] = ( $staleness_mo < 1 ) ? 6 : $staleness_mo;

		return $sanitized;
	}

	/**
	 * Enqueue admin scripts and stylesheets on the settings page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tools_page_aicfm-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'aicfm-admin-style',
			AICFM_PLUGIN_URL . 'assets/css/admin-style.css',
			array(),
			AICFM_VERSION
		);

		wp_enqueue_script(
			'aicfm-admin-script',
			AICFM_PLUGIN_URL . 'assets/js/admin-script.js',
			array( 'jquery' ),
			AICFM_VERSION,
			true
		);

		wp_localize_script(
			'aicfm-admin-script',
			'aicfm_vars',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'aicfm_admin_nonce' ),
				'save_kw_nonce' => wp_create_nonce( 'aicfm_save_keyword_nonce' ),
				'post_nonce'    => wp_create_nonce( 'aicfm_post_nonce' ),
				'i18n'          => array(
					'empty_key'           => __( 'Please enter an API key to test.', 'ai-content-freshness' ),
					'testing'             => __( 'Testing connection...', 'ai-content-freshness' ),
					'success'             => __( 'Connected successfully!', 'ai-content-freshness' ),
					'error'               => __( 'Connection failed.', 'ai-content-freshness' ),
					'saving'              => __( 'Saving...', 'ai-content-freshness' ),
					'saved'               => __( 'Saved', 'ai-content-freshness' ),
					'save_failed'         => __( 'Failed to save', 'ai-content-freshness' ),
					'auditing_serp'       => __( 'Auditing SERP...', 'ai-content-freshness' ),
					'enter_keyword_first' => __( 'Please enter a target keyword first.', 'ai-content-freshness' ),
					'rescan'              => __( 'Re-Scan', 'ai-content-freshness' ),
				),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-content-freshness' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'inventory'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $current_tab, array( 'inventory', 'settings' ), true ) ) {
			$current_tab = 'inventory';
		}

		$options      = get_option( AICFM_SETTINGS_KEY, array() );
		$engine       = isset( $options['engine'] ) ? $options['engine'] : 'gemini';
		$gemini       = isset( $options['gemini_key'] ) ? $options['gemini_key'] : '';
		$gemini_model = ! empty( $options['gemini_model'] ) ? $options['gemini_model'] : 'gemini-3.8-flash';
		$openai       = isset( $options['openai_key'] ) ? $options['openai_key'] : '';
		$openai_model = ! empty( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-4o';
		$months       = isset( $options['staleness_mo'] ) ? absint( $options['staleness_mo'] ) : 6;
		?>
		<div class="wrap aicfm-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper aicfm-nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=aicfm-settings&tab=inventory' ) ); ?>" class="nav-tab <?php echo ( 'inventory' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Stale Posts Inventory', 'ai-content-freshness' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=aicfm-settings&tab=settings' ) ); ?>" class="nav-tab <?php echo ( 'settings' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'API & Engine Settings', 'ai-content-freshness' ); ?>
				</a>
			</nav>

			<?php if ( 'inventory' === $current_tab ) : ?>
				<?php
				$dashboard = new Admin_Dashboard();
				$dashboard->render_inventory_table();
				?>
			<?php else : ?>
				<div class="aicfm-settings-wrap">
					<p class="description">
						<?php esc_html_e( 'Configure your AI provider credentials and audit thresholds for content freshness monitoring.', 'ai-content-freshness' ); ?>
					</p>

					<form method="post" action="options.php" class="aicfm-settings-form">
						<?php
						settings_fields( 'aicfm_settings_group' );
						do_settings_sections( 'aicfm_settings_group' );
						?>

						<table class="form-table" role="presentation">
							<tbody>
								<!-- AI Engine Selection -->
								<tr>
									<th scope="row">
										<label for="aicfm-engine"><?php esc_html_e( 'AI Engine Provider', 'ai-content-freshness' ); ?></label>
									</th>
									<td>
										<select id="aicfm-engine" name="<?php echo esc_attr( AICFM_SETTINGS_KEY ); ?>[engine]">
											<option value="gemini" <?php selected( $engine, 'gemini' ); ?>>
												<?php esc_html_e( 'Google Gemini (with Google Search)', 'ai-content-freshness' ); ?>
											</option>
											<option value="openai" <?php selected( $engine, 'openai' ); ?>>
												<?php esc_html_e( 'OpenAI GPT-4o (with Web Search)', 'ai-content-freshness' ); ?>
											</option>
										</select>
										<p class="description">
											<?php esc_html_e( 'Select the primary AI engine to perform real-time search grounding audits.', 'ai-content-freshness' ); ?>
										</p>
									</td>
								</tr>

								<!-- Gemini API Key -->
								<tr class="aicfm-engine-row aicfm-engine-gemini <?php echo ( 'gemini' !== $engine ) ? 'aicfm-hidden' : ''; ?>">
									<th scope="row">
										<label for="aicfm-gemini-key"><?php esc_html_e( 'Gemini API Key', 'ai-content-freshness' ); ?></label>
									</th>
									<td>
										<div class="aicfm-key-input-group">
											<input
												type="password"
												id="aicfm-gemini-key"
												name="<?php echo esc_attr( AICFM_SETTINGS_KEY ); ?>[gemini_key]"
												value="<?php echo esc_attr( $gemini ); ?>"
												class="regular-text aicfm-key-input"
												autocomplete="off"
											/>
											<button
												type="button"
												class="button aicfm-test-key-btn"
												data-engine="gemini"
											>
												<?php esc_html_e( 'Test Connection', 'ai-content-freshness' ); ?>
											</button>
											<span class="aicfm-test-result" id="aicfm-gemini-test-result"></span>
										</div>
										<p class="description">
											<?php esc_html_e( 'Obtain your API key from Google AI Studio.', 'ai-content-freshness' ); ?>
										</p>
									</td>
								</tr>

								<!-- Gemini Model Selection (Manual or Default) -->
								<tr class="aicfm-engine-row aicfm-engine-gemini <?php echo ( 'gemini' !== $engine ) ? 'aicfm-hidden' : ''; ?>">
									<th scope="row">
										<label for="aicfm-gemini-model"><?php esc_html_e( 'Gemini Model', 'ai-content-freshness' ); ?></label>
									</th>
									<td>
										<input
											type="text"
											id="aicfm-gemini-model"
											name="<?php echo esc_attr( AICFM_SETTINGS_KEY ); ?>[gemini_model]"
											value="<?php echo esc_attr( $gemini_model ); ?>"
											class="regular-text"
											placeholder="gemini-3.8-flash"
										/>
										<p class="description">
											<?php esc_html_e( 'Model name (default: gemini-3.8-flash). You can enter any Gemini model manually, or leave blank to fallback to gemini-3.8-flash.', 'ai-content-freshness' ); ?>
										</p>
									</td>
								</tr>

								<!-- OpenAI API Key -->
								<tr class="aicfm-engine-row aicfm-engine-openai <?php echo ( 'openai' !== $engine ) ? 'aicfm-hidden' : ''; ?>">
									<th scope="row">
										<label for="aicfm-openai-key"><?php esc_html_e( 'OpenAI API Key', 'ai-content-freshness' ); ?></label>
									</th>
									<td>
										<div class="aicfm-key-input-group">
											<input
												type="password"
												id="aicfm-openai-key"
												name="<?php echo esc_attr( AICFM_SETTINGS_KEY ); ?>[openai_key]"
												value="<?php echo esc_attr( $openai ); ?>"
												class="regular-text aicfm-key-input"
												autocomplete="off"
											/>
											<button
												type="button"
												class="button aicfm-test-key-btn"
												data-engine="openai"
											>
												<?php esc_html_e( 'Test Connection', 'ai-content-freshness' ); ?>
											</button>
											<span class="aicfm-test-result" id="aicfm-openai-test-result"></span>
										</div>
										<p class="description">
											<?php esc_html_e( 'Obtain your API key from the OpenAI platform dashboard.', 'ai-content-freshness' ); ?>
										</p>
									</td>
								</tr>

								<!-- OpenAI Model Selection (Manual or Default) -->
								<tr class="aicfm-engine-row aicfm-engine-openai <?php echo ( 'openai' !== $engine ) ? 'aicfm-hidden' : ''; ?>">
									<th scope="row">
										<label for="aicfm-openai-model"><?php esc_html_e( 'OpenAI Model', 'ai-content-freshness' ); ?></label>
									</th>
									<td>
										<input
											type="text"
											id="aicfm-openai-model"
											name="<?php echo esc_attr( AICFM_SETTINGS_KEY ); ?>[openai_model]"
											value="<?php echo esc_attr( $openai_model ); ?>"
											class="regular-text"
											placeholder="gpt-4o"
										/>
										<p class="description">
											<?php esc_html_e( 'Model name (default: gpt-4o). You can enter any OpenAI model manually, or leave blank to fallback to gpt-4o.', 'ai-content-freshness' ); ?>
										</p>
									</td>
								</tr>

								<!-- Staleness Threshold -->
								<tr>
									<th scope="row">
										<label for="aicfm-staleness-mo"><?php esc_html_e( 'Staleness Threshold (Months)', 'ai-content-freshness' ); ?></label>
									</th>
									<td>
										<input
											type="number"
											id="aicfm-staleness-mo"
											name="<?php echo esc_attr( AICFM_SETTINGS_KEY ); ?>[staleness_mo]"
											value="<?php echo esc_attr( (string) $months ); ?>"
											class="small-text"
											min="1"
											step="1"
										/>
										<p class="description">
											<?php esc_html_e( 'Number of months after which content is deemed potentially stale (default: 6).', 'ai-content-freshness' ); ?>
										</p>
									</td>
								</tr>
							</tbody>
						</table>

						<?php submit_button( __( 'Save Settings', 'ai-content-freshness' ) ); ?>
					</form>
				</div>
			<?php endif; ?>

			<!-- Patreon Supporter Banner -->
			<div class="aicfm-support-card">
				<div class="aicfm-support-content">
					<span class="dashicons dashicons-heart aicfm-heart-icon"></span>
					<div class="aicfm-support-text">
						<h3><?php esc_html_e( 'Support Independent Open-Source Development', 'ai-content-freshness' ); ?></h3>
						<p><?php esc_html_e( 'AI Content Freshness Monitor is 100% free and open-source under GPLv2. If this plugin saves you time or helps your SEO rankings, consider supporting future development and maintenance on Patreon.', 'ai-content-freshness' ); ?></p>
					</div>
				</div>
				<div class="aicfm-support-action">
					<a href="https://www.patreon.com/nacharcode/membership" target="_blank" rel="noopener noreferrer" class="button button-primary aicfm-patreon-btn">
						<?php esc_html_e( '☕ Become a Patron', 'ai-content-freshness' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
