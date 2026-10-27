<?php
/**
 * Post Meta Class.
 *
 * @package AI_Content_Freshness
 */

namespace AICFM;

defined( 'ABSPATH' ) || exit;

/**
 * Class Post_Meta
 *
 * Manages the Content Freshness Audit meta box on the post edit screen.
 */
class Post_Meta {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save_post_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register meta box on post edit screen.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'aicfm_freshness_box',
			__( 'Content Freshness Audit', 'ai-content-freshness' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'high'
		);
	}

	/**
	 * Render the Content Freshness Audit meta box content.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		// Output nonce for security.
		wp_nonce_field( 'aicfm_save_post_meta_action', 'aicfm_post_meta_nonce' );

		$target_keyword = get_post_meta( $post->ID, '_aicfm_target_keyword', true );
		$last_audited   = get_post_meta( $post->ID, '_aicfm_last_audited', true );
		$existing_report = get_post_meta( $post->ID, '_aicfm_audit_report', true );

		$formatted_date = __( 'Never', 'ai-content-freshness' );
		if ( ! empty( $last_audited ) ) {
			$timestamp = strtotime( $last_audited );
			if ( $timestamp ) {
				$formatted_date = date_i18n(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					$timestamp
				);
			}
		}
		?>
		<div class="aicfm-metabox-content">
			<!-- Target Keyword Field -->
			<div class="aicfm-field-group">
				<label for="aicfm-target-keyword" class="aicfm-label">
					<strong><?php esc_html_e( 'Target Keyword:', 'ai-content-freshness' ); ?></strong>
				</label>
				<input
					type="text"
					id="aicfm-target-keyword"
					name="_aicfm_target_keyword"
					value="<?php echo esc_attr( $target_keyword ); ?>"
					class="widefat aicfm-keyword-input"
					placeholder="<?php esc_attr_e( 'e.g., best espresso machine 2026', 'ai-content-freshness' ); ?>"
				/>
				<p class="description">
					<?php esc_html_e( 'Primary search phrase used to evaluate competitor content freshness.', 'ai-content-freshness' ); ?>
				</p>
			</div>

			<!-- Last Audited Indicator -->
			<div class="aicfm-meta-info">
				<span class="aicfm-info-label"><?php esc_html_e( 'Last Audited:', 'ai-content-freshness' ); ?></span>
				<span class="aicfm-info-value" id="aicfm-last-audited-val"><?php echo esc_html( $formatted_date ); ?></span>
			</div>

			<!-- Scan Action Button -->
			<div class="aicfm-action-wrap">
				<button
					type="button"
					id="aicfm-run-audit-btn"
					class="button button-primary button-large widefat"
					data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
				>
					<?php esc_html_e( 'Scan & Refresh', 'ai-content-freshness' ); ?>
				</button>
			</div>

			<!-- Audit Status & Results Container -->
			<div id="aicfm-audit-results" class="aicfm-results-container">
				<?php
				if ( is_array( $existing_report ) && ! empty( $existing_report ) ) {
					echo $this->render_report_html( $existing_report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Build markup for an audit report card.
	 *
	 * @param array $report Audit report data.
	 * @return string HTML report markup.
	 */
	public function render_report_html( $report ) {
		$score   = isset( $report['score'] ) ? min( 100, max( 0, absint( $report['score'] ) ) ) : 0;
		$summary = isset( $report['summary'] ) ? $report['summary'] : '';

		$score_class = 'aicfm-score-high';
		if ( $score < 50 ) {
			$score_class = 'aicfm-score-low';
		} elseif ( $score < 70 ) {
			$score_class = 'aicfm-score-medium';
		}

		ob_start();
		?>
		<div class="aicfm-report-card">
			<!-- Freshness Score Header -->
			<div class="aicfm-score-header">
				<div class="aicfm-score-pill <?php echo esc_attr( $score_class ); ?>">
					<span class="aicfm-score-number"><?php echo esc_html( (string) $score ); ?></span>
					<span class="aicfm-score-total">/100</span>
				</div>
				<div class="aicfm-score-meta">
					<span class="aicfm-score-label"><?php esc_html_e( 'Freshness Score', 'ai-content-freshness' ); ?></span>
				</div>
			</div>

			<!-- Summary Paragraph -->
			<?php if ( ! empty( $summary ) ) : ?>
				<div class="aicfm-summary-box">
					<p><?php echo esc_html( $summary ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Outdated Points Card -->
			<?php if ( ! empty( $report['outdated_points'] ) && is_array( $report['outdated_points'] ) ) : ?>
				<div class="aicfm-section aicfm-section-outdated">
					<details class="aicfm-details" open>
						<summary class="aicfm-section-title">
							<span><?php esc_html_e( 'Outdated Points', 'ai-content-freshness' ); ?> (<?php echo esc_html( (string) count( $report['outdated_points'] ) ); ?>)</span>
						</summary>
						<ul class="aicfm-points-list">
							<?php foreach ( $report['outdated_points'] as $point ) : ?>
								<li class="aicfm-point-item">
									<div class="aicfm-claim">
										<span class="aicfm-claim-tag"><?php esc_html_e( 'Claim:', 'ai-content-freshness' ); ?></span>
										<span><?php echo esc_html( $point['claim'] ?? '' ); ?></span>
									</div>
									<div class="aicfm-fix">
										<span class="aicfm-fix-tag"><?php esc_html_e( 'Fix:', 'ai-content-freshness' ); ?></span>
										<span><?php echo esc_html( $point['fix'] ?? '' ); ?></span>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</details>
				</div>
			<?php endif; ?>

			<!-- Missing Topics List -->
			<?php if ( ! empty( $report['missing_topics'] ) && is_array( $report['missing_topics'] ) ) : ?>
				<div class="aicfm-section aicfm-section-topics">
					<details class="aicfm-details" open>
						<summary class="aicfm-section-title">
							<span><?php esc_html_e( 'Missing Topics', 'ai-content-freshness' ); ?> (<?php echo esc_html( (string) count( $report['missing_topics'] ) ); ?>)</span>
						</summary>
						<div class="aicfm-tags-wrap">
							<?php foreach ( $report['missing_topics'] as $topic ) : ?>
								<div class="aicfm-topic-badge" title="<?php echo esc_attr( $topic['reason'] ?? '' ); ?>">
									<span class="aicfm-topic-title"><?php echo esc_html( $topic['topic'] ?? '' ); ?></span>
									<?php if ( ! empty( $topic['reason'] ) ) : ?>
										<span class="aicfm-topic-reason"><?php echo esc_html( $topic['reason'] ); ?></span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</details>
				</div>
			<?php endif; ?>

			<!-- Action Checklist -->
			<?php if ( ! empty( $report['action_steps'] ) && is_array( $report['action_steps'] ) ) : ?>
				<div class="aicfm-section aicfm-section-actions">
					<details class="aicfm-details" open>
						<summary class="aicfm-section-title">
							<span><?php esc_html_e( 'Action Steps', 'ai-content-freshness' ); ?> (<?php echo esc_html( (string) count( $report['action_steps'] ) ); ?>)</span>
						</summary>
						<ul class="aicfm-checklist">
							<?php foreach ( $report['action_steps'] as $index => $step ) : ?>
								<li class="aicfm-checklist-item">
									<label>
										<input type="checkbox" class="aicfm-step-check" />
										<span><?php echo esc_html( $step ); ?></span>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					</details>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save post meta field on post save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_post_meta( $post_id, $post ) {
		// Verify nonce.
		if (
			! isset( $_POST['aicfm_post_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aicfm_post_meta_nonce'] ) ), 'aicfm_save_post_meta_action' )
		) {
			return;
		}

		// Prevent saving during autosave or revision creation.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check user capability.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sanitize and update target keyword.
		if ( isset( $_POST['_aicfm_target_keyword'] ) ) {
			$keyword = sanitize_text_field( wp_unslash( $_POST['_aicfm_target_keyword'] ) );
			update_post_meta( $post_id, '_aicfm_target_keyword', $keyword );
		}
	}

	/**
	 * Enqueue assets on post edit screens.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		global $post;
		$post_id = $post ? $post->ID : 0;

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
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'post_nonce' => wp_create_nonce( 'aicfm_post_nonce' ),
				'post_id'    => $post_id,
				'i18n'       => array(
					'empty_keyword' => __( 'Please enter a target keyword before running an audit.', 'ai-content-freshness' ),
					'running_audit' => __( 'Searching live web and auditing content...', 'ai-content-freshness' ),
					'audit_failed'  => __( 'Audit failed. Please try again.', 'ai-content-freshness' ),
				),
			)
		);
	}
}
