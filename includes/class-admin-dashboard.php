<?php
/**
 * Admin Stale Posts Dashboard Class.
 *
 * @package AI_Content_Freshness
 */

namespace AICFM;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Dashboard
 *
 * Manages the Stale Posts Inventory query and table view.
 */
class Admin_Dashboard {

	/**
	 * Query stale published posts based on the configured staleness threshold.
	 *
	 * @param int $paged    Current page number.
	 * @param int $per_page Posts per page.
	 * @return \WP_Query
	 */
	public function get_stale_posts( $paged = 1, $per_page = 20 ) {
		$options      = get_option( AICFM_SETTINGS_KEY, array() );
		$staleness_mo = isset( $options['staleness_mo'] ) ? absint( $options['staleness_mo'] ) : 6;
		if ( $staleness_mo < 1 ) {
			$staleness_mo = 6;
		}

		$threshold_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$staleness_mo} months" ) );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => max( 1, absint( $paged ) ),
			'date_query'     => array(
				array(
					'column' => 'post_modified',
					'before' => $threshold_date,
				),
			),
			'orderby'        => 'post_modified',
			'order'          => 'ASC', // Oldest modified first.
		);

		return new \WP_Query( $args );
	}

	/**
	 * Render the Stale Posts Inventory table view.
	 *
	 * @return void
	 */
	public function render_inventory_table() {
		$options      = get_option( AICFM_SETTINGS_KEY, array() );
		$staleness_mo = isset( $options['staleness_mo'] ) ? absint( $options['staleness_mo'] ) : 6;
		if ( $staleness_mo < 1 ) {
			$staleness_mo = 6;
		}

		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page     = 20;
		$query        = $this->get_stale_posts( $current_page, $per_page );
		$total_posts  = $query->found_posts;
		?>
		<div class="aicfm-dashboard-wrap">
			<!-- Summary Notice Banner -->
			<div class="notice notice-info inline aicfm-summary-banner">
				<p>
					<?php
					printf(
						/* translators: 1: Number of posts, 2: Number of months */
						esc_html( _n( 'Found %1$s published post not updated in the last %2$d months.', 'Found %1$s published posts not updated in the last %2$d months.', $total_posts, 'ai-content-freshness' ) ),
						'<strong>' . esc_html( number_format_i18n( $total_posts ) ) . '</strong>',
						esc_html( (string) $staleness_mo )
					);
					?>
				</p>
			</div>

			<!-- Inventory Table -->
			<table class="wp-list-table widefat fixed striped posts aicfm-inventory-table">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-title column-primary"><?php esc_html_e( 'Title', 'ai-content-freshness' ); ?></th>
						<th scope="col" class="manage-column column-modified"><?php esc_html_e( 'Last Modified', 'ai-content-freshness' ); ?></th>
						<th scope="col" class="manage-column column-keyword"><?php esc_html_e( 'Target Keyword', 'ai-content-freshness' ); ?></th>
						<th scope="col" class="manage-column column-freshness"><?php esc_html_e( 'Freshness Status', 'ai-content-freshness' ); ?></th>
						<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'ai-content-freshness' ); ?></th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php if ( $query->have_posts() ) : ?>
						<?php
						while ( $query->have_posts() ) :
							$query->the_post();
							$post_id = get_the_ID();

							// Retrieve Target Keyword with SEO plugin fallbacks.
							$keyword = get_post_meta( $post_id, '_aicfm_target_keyword', true );
							if ( empty( $keyword ) ) {
								$keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
							}
							if ( empty( $keyword ) ) {
								$keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
							}

							// Retrieve audit report & last audited timestamp.
							$report       = get_post_meta( $post_id, '_aicfm_audit_report', true );
							$last_audited = get_post_meta( $post_id, '_aicfm_last_audited', true );

							$modified_ts = get_post_modified_time( 'U', true, $post_id );
							$human_time  = human_time_diff( $modified_ts, current_time( 'timestamp' ) );
							$exact_date  = get_the_modified_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post_id );
							$post_title  = get_the_title();
							if ( empty( $post_title ) ) {
								$post_title = __( '(no title)', 'ai-content-freshness' );
							}
							?>
							<tr id="post-<?php echo esc_attr( (string) $post_id ); ?>" class="aicfm-post-row" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
								<!-- Title Column -->
								<td class="title column-title has-row-actions column-primary">
									<strong>
										<a class="row-title" href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>">
											<?php echo esc_html( $post_title ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
												<?php esc_html_e( 'Edit', 'ai-content-freshness' ); ?>
											</a> | 
										</span>
										<span class="view">
											<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener noreferrer">
												<?php esc_html_e( 'View', 'ai-content-freshness' ); ?>
											</a>
										</span>
									</div>
								</td>

								<!-- Last Modified Column -->
								<td class="column-modified" title="<?php echo esc_attr( $exact_date ); ?>">
									<span>
										<?php
										printf(
											/* translators: %s: Relative time e.g. 7 months */
											esc_html__( '%s ago', 'ai-content-freshness' ),
											esc_html( $human_time )
										);
										?>
									</span>
									<span class="aicfm-status-date"><?php echo esc_html( get_the_modified_date( get_option( 'date_format' ), $post_id ) ); ?></span>
								</td>

								<!-- Target Keyword Column -->
								<td class="column-keyword">
									<div class="aicfm-keyword-field-wrap">
										<input
											type="text"
											class="aicfm-keyword-input aicfm-row-keyword"
											data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
											value="<?php echo esc_attr( $keyword ); ?>"
											placeholder="<?php esc_attr_e( 'e.g., seo audit guide', 'ai-content-freshness' ); ?>"
											autocomplete="off"
										/>
										<span class="aicfm-keyword-status" id="aicfm-kw-status-<?php echo esc_attr( (string) $post_id ); ?>"></span>
									</div>
								</td>

								<!-- Freshness Status Column -->
								<td class="column-freshness aicfm-freshness-cell" id="aicfm-freshness-cell-<?php echo esc_attr( (string) $post_id ); ?>">
									<?php if ( is_array( $report ) && isset( $report['score'] ) ) : ?>
										<?php
										$score       = absint( $report['score'] );
										$score_class = 'score-green';
										if ( $score < 50 ) {
											$score_class = 'score-red';
										} elseif ( $score < 70 ) {
											$score_class = 'score-orange';
										}

										$audited_formatted = '';
										if ( ! empty( $last_audited ) ) {
											$audited_ts = strtotime( $last_audited );
											if ( $audited_ts ) {
												$audited_formatted = date_i18n( get_option( 'date_format' ), $audited_ts );
											}
										}
										?>
										<span class="aicfm-score-pill <?php echo esc_attr( $score_class ); ?>">
											<?php echo esc_html( (string) $score ); ?>
										</span>
										<?php if ( ! empty( $audited_formatted ) ) : ?>
											<span class="aicfm-status-date"><?php echo esc_html( $audited_formatted ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span class="aicfm-badge badge-stale"><?php esc_html_e( 'Needs Audit', 'ai-content-freshness' ); ?></span>
									<?php endif; ?>
								</td>

								<!-- Actions Column -->
								<td class="column-actions">
									<div class="aicfm-row-action-wrap">
										<button
											type="button"
											class="button button-secondary aicfm-row-scan-btn"
											data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
										>
											<?php echo ( ! empty( $report ) ) ? esc_html__( 'Re-Scan', 'ai-content-freshness' ) : esc_html__( 'Scan Now', 'ai-content-freshness' ); ?>
										</button>
										<span class="aicfm-row-status" id="aicfm-row-status-<?php echo esc_attr( (string) $post_id ); ?>"></span>
									</div>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else : ?>
						<tr>
							<td colspan="5" class="aicfm-empty-cell">
								<p><?php esc_html_e( 'No stale posts found. All your published posts have been modified recently!', 'ai-content-freshness' ); ?></p>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<th scope="col" class="manage-column column-title column-primary"><?php esc_html_e( 'Title', 'ai-content-freshness' ); ?></th>
						<th scope="col" class="manage-column column-modified"><?php esc_html_e( 'Last Modified', 'ai-content-freshness' ); ?></th>
						<th scope="col" class="manage-column column-keyword"><?php esc_html_e( 'Target Keyword', 'ai-content-freshness' ); ?></th>
						<th scope="col" class="manage-column column-freshness"><?php esc_html_e( 'Freshness Status', 'ai-content-freshness' ); ?></th>
						<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'ai-content-freshness' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<!-- Pagination Navigation -->
			<?php if ( $query->max_num_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$pagination_args = array(
							'base'      => add_query_arg(
								array(
									'page'  => 'aicfm-settings',
									'tab'   => 'inventory',
									'paged' => '%#%',
								),
								admin_url( 'tools.php' )
							),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $query->max_num_pages,
							'current'   => $current_page,
						);
						echo paginate_links( $pagination_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php wp_reset_postdata(); ?>
		</div>
		<?php
	}
}
