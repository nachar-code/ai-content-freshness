<?php
/**
 * Plugin Name:       AI Content Freshness Monitor
 * Plugin URI:        https://github.com/nachar-code/ai-content-freshness
 * Description:       Audit published posts for content staleness and topical freshness against live search results using AI engines.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            nachar-code
 * Author URI:        https://github.com/nachar-code
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-content-freshness
 * Domain Path:       /languages
 *
 * @package AI_Content_Freshness
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'AICFM_VERSION', '1.0.0' );
define( 'AICFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AICFM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AICFM_SETTINGS_KEY', 'aicfm_settings' );

/**
 * Register default settings on plugin activation.
 *
 * @return void
 */
function aicfm_activate_plugin() {
	$default_settings = array(
		'engine'       => 'gemini',
		'gemini_key'   => '',
		'gemini_model' => 'gemini-3.8-flash',
		'openai_key'   => '',
		'openai_model' => 'gpt-4o',
		'staleness_mo' => 6,
	);

	if ( false === get_option( AICFM_SETTINGS_KEY ) ) {
		add_option( AICFM_SETTINGS_KEY, $default_settings );
	}
}
register_activation_hook( __FILE__, 'aicfm_activate_plugin' );

/**
 * Initialize plugin components on plugins_loaded.
 *
 * @return void
 */
function aicfm_init_plugin() {
	load_plugin_textdomain( 'ai-content-freshness', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Core components.
	require_once AICFM_PLUGIN_DIR . 'includes/class-admin-settings.php';
	require_once AICFM_PLUGIN_DIR . 'includes/class-admin-dashboard.php';
	require_once AICFM_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
	require_once AICFM_PLUGIN_DIR . 'includes/class-post-meta.php';
	require_once AICFM_PLUGIN_DIR . 'includes/class-api-client.php';

	new \AICFM\Admin_Settings();
	new \AICFM\Ajax_Handlers();
	new \AICFM\Post_Meta();
}
add_action( 'plugins_loaded', 'aicfm_init_plugin' );

/**
 * Add Settings quick link to plugin actions on plugins.php.
 *
 * @param array $links Array of plugin action links.
 * @return array
 */
function aicfm_add_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'tools.php?page=aicfm-settings' ) ) . '">' . esc_html__( 'Settings', 'ai-content-freshness' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'aicfm_add_action_links' );
