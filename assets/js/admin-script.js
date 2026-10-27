/**
 * AI Content Freshness Monitor - Admin Scripts
 *
 * @package AI_Content_Freshness
 */

( function( $ ) {
	'use strict';

	$( function() {
		/* =====================================================================
		 * 1. Settings Page Handlers
		 * =================================================================== */
		var $engineSelect = $( '#aicfm-engine' );
		var $geminiRow    = $( '.aicfm-engine-gemini' );
		var $openaiRow    = $( '.aicfm-engine-openai' );

		/**
		 * Toggle API key rows based on engine dropdown selection.
		 */
		function toggleEngineRows() {
			var selected = $engineSelect.val();
			if ( 'gemini' === selected ) {
				$geminiRow.removeClass( 'aicfm-hidden' );
				$openaiRow.addClass( 'aicfm-hidden' );
			} else if ( 'openai' === selected ) {
				$openaiRow.removeClass( 'aicfm-hidden' );
				$geminiRow.addClass( 'aicfm-hidden' );
			}
		}

		if ( $engineSelect.length ) {
			toggleEngineRows();
			$engineSelect.on( 'change', toggleEngineRows );
		}

		/**
		 * Handle "Test Connection" button clicks on settings page.
		 */
		$( document ).on( 'click', '.aicfm-test-key-btn', function( e ) {
			e.preventDefault();

			var $btn       = $( this );
			var engine     = $btn.data( 'engine' );
			var $row       = $btn.closest( 'tr' );
			var $keyInput  = $row.find( '.aicfm-key-input' );
			var $resultBox = $row.find( '.aicfm-test-result' );
			var apiKey     = $.trim( $keyInput.val() );
			var model      = ( 'gemini' === engine )
				? $.trim( $( '#aicfm-gemini-model' ).val() )
				: $.trim( $( '#aicfm-openai-model' ).val() );

			// Clear prior status.
			$resultBox.empty();

			// Validate empty key before dispatching request.
			if ( ! apiKey ) {
				var emptyMsg = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.empty_key )
					? window.aicfm_vars.i18n.empty_key
					: 'Please enter an API key to test.';

				var $errorBadge = $( '<span/>' )
					.addClass( 'aicfm-badge aicfm-badge-error' )
					.text( emptyMsg );

				$resultBox.append( $errorBadge );
				$keyInput.trigger( 'focus' );
				return;
			}

			// Show loading status.
			$btn.prop( 'disabled', true );
			var testingMsg = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.testing )
				? window.aicfm_vars.i18n.testing
				: 'Testing connection...';

			var $loadingBadge = $( '<span/>' )
				.addClass( 'aicfm-badge aicfm-badge-loading' )
				.html( '<span class="spinner is-active"></span> ' )
				.append( $( '<span/>' ).text( testingMsg ) );

			$resultBox.append( $loadingBadge );

			// Execute AJAX call.
			$.ajax( {
				url: ( window.aicfm_vars && window.aicfm_vars.ajax_url ) ? window.aicfm_vars.ajax_url : ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action:  'aicfm_test_connection',
					nonce:   ( window.aicfm_vars && window.aicfm_vars.nonce ) ? window.aicfm_vars.nonce : '',
					engine:  engine,
					api_key: apiKey,
					model:   model
				}
			} ).done( function( response ) {
				$resultBox.empty();

				if ( response && response.success ) {
					var successMsg = ( response.data && response.data.message )
						? response.data.message
						: 'Connected successfully!';

					var $successBadge = $( '<span/>' )
						.addClass( 'aicfm-badge aicfm-badge-success' )
						.text( '✓ ' + successMsg );

					$resultBox.append( $successBadge );
				} else {
					var errorMsg = ( response && response.data && response.data.message )
						? response.data.message
						: 'Connection failed.';

					var $failBadge = $( '<span/>' )
						.addClass( 'aicfm-badge aicfm-badge-error' )
						.text( '✗ ' + errorMsg );

					$resultBox.append( $failBadge );
				}
			} ).fail( function( jqXHR, textStatus, errorThrown ) {
				$resultBox.empty();
				var statusError = errorThrown || textStatus || 'Server error occurred.';
				var $failBadge  = $( '<span/>' )
					.addClass( 'aicfm-badge aicfm-badge-error' )
					.text( '✗ ' + statusError );

				$resultBox.append( $failBadge );
			} ).always( function() {
				$btn.prop( 'disabled', false );
			} );
		} );

		/* =====================================================================
		 * 2. Post Edit Screen Handlers (Meta Box & Audit Runner)
		 * =================================================================== */
		var $keywordInput = $( '#aicfm-target-keyword' );
		var $auditBtn     = $( '#aicfm-run-audit-btn' );
		var $resultsBox   = $( '#aicfm-audit-results' );

		/**
		 * Build client-side HTML DOM for an audit report card.
		 *
		 * @param {Object} report The audit report payload.
		 * @return {jQuery} The compiled DOM container.
		 */
		function buildReportDom( report ) {
			var score = parseInt( report.score, 10 ) || 0;
			score     = Math.max( 0, Math.min( 100, score ) );

			var scoreClass = 'aicfm-score-high';
			if ( score < 50 ) {
				scoreClass = 'aicfm-score-low';
			} else if ( score < 70 ) {
				scoreClass = 'aicfm-score-medium';
			}

			var $card = $( '<div/>' ).addClass( 'aicfm-report-card' );

			// 1. Score Pill Header.
			var $header = $( '<div/>' ).addClass( 'aicfm-score-header' );
			var $pill   = $( '<div/>' ).addClass( 'aicfm-score-pill ' + scoreClass );
			$pill.append( $( '<span/>' ).addClass( 'aicfm-score-number' ).text( score ) );
			$pill.append( $( '<span/>' ).addClass( 'aicfm-score-total' ).text( '/100' ) );

			var $meta = $( '<div/>' ).addClass( 'aicfm-score-meta' );
			$meta.append( $( '<span/>' ).addClass( 'aicfm-score-label' ).text( 'Freshness Score' ) );

			$header.append( $pill ).append( $meta );
			$card.append( $header );

			// 2. Summary Paragraph.
			if ( report.summary ) {
				var $summaryBox = $( '<div/>' ).addClass( 'aicfm-summary-box' );
				$summaryBox.append( $( '<p/>' ).text( report.summary ) );
				$card.append( $summaryBox );
			}

			// 3. Outdated Points Section.
			if ( report.outdated_points && report.outdated_points.length ) {
				var $outdatedSec = $( '<div/>' ).addClass( 'aicfm-section aicfm-section-outdated' );
				var $outDetails  = $( '<details/>' ).addClass( 'aicfm-details' ).prop( 'open', true );
				var $outSummary  = $( '<summary/>' ).addClass( 'aicfm-section-title' ).text(
					'Outdated Points (' + report.outdated_points.length + ')'
				);
				var $pointsList  = $( '<ul/>' ).addClass( 'aicfm-points-list' );

				$.each( report.outdated_points, function( idx, pt ) {
					var $item = $( '<li/>' ).addClass( 'aicfm-point-item' );

					var $claim = $( '<div/>' ).addClass( 'aicfm-claim' );
					$claim.append( $( '<span/>' ).addClass( 'aicfm-claim-tag' ).text( 'Claim: ' ) );
					$claim.append( $( '<span/>' ).text( pt.claim || '' ) );

					var $fix = $( '<div/>' ).addClass( 'aicfm-fix' );
					$fix.append( $( '<span/>' ).addClass( 'aicfm-fix-tag' ).text( 'Fix: ' ) );
					$fix.append( $( '<span/>' ).text( pt.fix || '' ) );

					$item.append( $claim ).append( $fix );
					$pointsList.append( $item );
				} );

				$outDetails.append( $outSummary ).append( $pointsList );
				$outdatedSec.append( $outDetails );
				$card.append( $outdatedSec );
			}

			// 4. Missing Topics List.
			if ( report.missing_topics && report.missing_topics.length ) {
				var $topicsSec   = $( '<div/>' ).addClass( 'aicfm-section aicfm-section-topics' );
				var $topDetails  = $( '<details/>' ).addClass( 'aicfm-details' ).prop( 'open', true );
				var $topSummary  = $( '<summary/>' ).addClass( 'aicfm-section-title' ).text(
					'Missing Topics (' + report.missing_topics.length + ')'
				);
				var $tagsWrap    = $( '<div/>' ).addClass( 'aicfm-tags-wrap' );

				$.each( report.missing_topics, function( idx, topic ) {
					var $badge = $( '<div/>' )
						.addClass( 'aicfm-topic-badge' )
						.attr( 'title', topic.reason || '' );

					$badge.append( $( '<span/>' ).addClass( 'aicfm-topic-title' ).text( topic.topic || '' ) );
					if ( topic.reason ) {
						$badge.append( $( '<span/>' ).addClass( 'aicfm-topic-reason' ).text( topic.reason ) );
					}

					$tagsWrap.append( $badge );
				} );

				$topDetails.append( $topSummary ).append( $tagsWrap );
				$topicsSec.append( $topDetails );
				$card.append( $topicsSec );
			}

			// 5. Action Checklist.
			if ( report.action_steps && report.action_steps.length ) {
				var $actSec      = $( '<div/>' ).addClass( 'aicfm-section aicfm-section-actions' );
				var $actDetails  = $( '<details/>' ).addClass( 'aicfm-details' ).prop( 'open', true );
				var $actSummary  = $( '<summary/>' ).addClass( 'aicfm-section-title' ).text(
					'Action Steps (' + report.action_steps.length + ')'
				);
				var $checkList   = $( '<ul/>' ).addClass( 'aicfm-checklist' );

				$.each( report.action_steps, function( idx, step ) {
					var $li    = $( '<li/>' ).addClass( 'aicfm-checklist-item' );
					var $label = $( '<label/>' );
					var $input = $( '<input/>' ).attr( 'type', 'checkbox' ).addClass( 'aicfm-step-check' );

					$label.append( $input ).append( $( '<span/>' ).text( ' ' + step ) );
					$li.append( $label );
					$checkList.append( $li );
				} );

				$actDetails.append( $actSummary ).append( $checkList );
				$actSec.append( $actDetails );
				$card.append( $actSec );
			}

			return $card;
		}

		/**
		 * Checklist item toggle state.
		 */
		$( document ).on( 'change', '.aicfm-step-check', function() {
			var $item = $( this ).closest( '.aicfm-checklist-item' );
			if ( $( this ).is( ':checked' ) ) {
				$item.addClass( 'is-completed' );
			} else {
				$item.removeClass( 'is-completed' );
			}
		} );

		/**
		 * Execute Post Audit AJAX runner.
		 */
		if ( $auditBtn.length ) {
			$auditBtn.on( 'click', function( e ) {
				e.preventDefault();

				if ( $auditBtn.prop( 'disabled' ) ) {
					return;
				}

				var keyword = $.trim( $keywordInput.val() );
				if ( ! keyword ) {
					var emptyKwMsg = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.empty_keyword )
						? window.aicfm_vars.i18n.empty_keyword
						: 'Please enter a target keyword before running an audit.';

					$resultsBox.empty().append(
						$( '<div/>' ).addClass( 'aicfm-badge aicfm-badge-error' ).text( emptyKwMsg )
					);
					$keywordInput.trigger( 'focus' );
					return;
				}

				var postId = $( '#post_ID' ).val() || ( window.aicfm_vars && window.aicfm_vars.post_id ) || $auditBtn.data( 'post-id' );

				// Retrieve current editor title and content (supports Gutenberg and Classic editor).
				var liveTitle   = '';
				var liveContent = '';

				if ( window.wp && wp.data && wp.data.select && wp.data.select( 'core/editor' ) ) {
					liveTitle   = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
					liveContent = wp.data.select( 'core/editor' ).getEditedPostContent() || '';
				} else {
					liveTitle = $( '#title' ).val() || '';
					if ( typeof tinyMCE !== 'undefined' && tinyMCE.get( 'content' ) && ! tinyMCE.get( 'content' ).isHidden() ) {
						liveContent = tinyMCE.get( 'content' ).getContent();
					} else {
						liveContent = $( '#content' ).val() || '';
					}
				}

				// Show loading state and disable button.
				$auditBtn.prop( 'disabled', true );
				var runningMsg = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.running_audit )
					? window.aicfm_vars.i18n.running_audit
					: 'Searching live web and auditing content...';

				var $loading = $( '<div/>' )
					.addClass( 'aicfm-badge aicfm-badge-loading' )
					.html( '<span class="spinner is-active"></span> ' )
					.append( $( '<span/>' ).text( runningMsg ) );

				$resultsBox.empty().append( $loading );

				// Dispatch AJAX request.
				$.ajax( {
					url: ( window.aicfm_vars && window.aicfm_vars.ajax_url ) ? window.aicfm_vars.ajax_url : ajaxurl,
					type: 'POST',
					dataType: 'json',
					data: {
						action:  'aicfm_execute_post_audit',
						nonce:   ( window.aicfm_vars && window.aicfm_vars.post_nonce ) ? window.aicfm_vars.post_nonce : '',
						post_id: postId,
						keyword: keyword,
						title:   liveTitle,
						content: liveContent
					}
				} ).done( function( response ) {
					$resultsBox.empty();

					if ( response && response.success && response.data ) {
						var reportData = response.data.report || response.data;
						var $card      = buildReportDom( reportData );
						$resultsBox.append( $card );

						if ( response.data.last_audited ) {
							$( '#aicfm-last-audited-val' ).text( response.data.last_audited );
						}
					} else {
						var err = ( response && response.data && response.data.message )
							? response.data.message
							: 'Audit failed. Please try again.';

						$resultsBox.append(
							$( '<div/>' ).addClass( 'aicfm-badge aicfm-badge-error' ).text( '✗ ' + err )
						);
					}
				} ).fail( function( jqXHR, textStatus, errorThrown ) {
					$resultsBox.empty();
					var statusErr = errorThrown || textStatus || 'Server error occurred during audit.';
					$resultsBox.append(
						$( '<div/>' ).addClass( 'aicfm-badge aicfm-badge-error' ).text( '✗ ' + statusErr )
					);
				} ).always( function() {
					$auditBtn.prop( 'disabled', false );
				} );
			} );
		}

		/* =====================================================================
		 * 3. Stale Posts Inventory Dashboard Handlers
		 * =================================================================== */
		// Cache initial keyword values on page load.
		$( '.aicfm-row-keyword' ).each( function() {
			$( this ).data( 'last-value', $.trim( $( this ).val() ) );
		} );

		/**
		 * Inline Keyword Auto-Save on blur or change.
		 */
		$( document ).on( 'change blur', '.aicfm-row-keyword', function() {
			var $input   = $( this );
			var postId   = $input.data( 'post-id' );
			var keyword  = $.trim( $input.val() );
			var lastVal  = $input.data( 'last-value' );
			var $status  = $( '#aicfm-kw-status-' + postId );

			if ( keyword === lastVal || $input.data( 'is-saving' ) ) {
				return;
			}

			// Immediately set saving state and cached value to prevent double triggers.
			$input.data( 'is-saving', true );
			$input.data( 'last-value', keyword );

			var savingText = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.saving )
				? window.aicfm_vars.i18n.saving
				: 'Saving...';

			$status
				.removeClass( 'is-saved is-error' )
				.addClass( 'is-saving' )
				.text( savingText )
				.show();

			$.ajax( {
				url: ( window.aicfm_vars && window.aicfm_vars.ajax_url ) ? window.aicfm_vars.ajax_url : ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action:  'aicfm_save_row_keyword',
					nonce:   ( window.aicfm_vars && window.aicfm_vars.save_kw_nonce ) ? window.aicfm_vars.save_kw_nonce : '',
					post_id: postId,
					keyword: keyword
				}
			} ).done( function( response ) {
				if ( response && response.success ) {
					var savedText = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.saved )
						? window.aicfm_vars.i18n.saved
						: 'Saved';

					$status
						.removeClass( 'is-saving is-error' )
						.addClass( 'is-saved' )
						.text( savedText );

					setTimeout( function() {
						$status.fadeOut( 400 );
					}, 2000 );
				} else {
					$input.data( 'last-value', lastVal ); // Revert on failure.
					var errText = ( response && response.data && response.data.message )
						? response.data.message
						: 'Failed';

					$status
						.removeClass( 'is-saving is-saved' )
						.addClass( 'is-error' )
						.text( errText );
				}
			} ).fail( function() {
				$input.data( 'last-value', lastVal ); // Revert on failure.
				$status
					.removeClass( 'is-saving is-saved' )
					.addClass( 'is-error' )
					.text( 'Error' );
			} ).always( function() {
				$input.data( 'is-saving', false );
			} );
		} );

		/**
		 * Row-based "Scan Now" / "Re-Scan" trigger.
		 */
		$( document ).on( 'click', '.aicfm-row-scan-btn', function( e ) {
			e.preventDefault();

			var $btn = $( this );
			if ( $btn.prop( 'disabled' ) ) {
				return;
			}
			var postId     = $btn.data( 'post-id' );
			var $row       = $btn.closest( 'tr' );
			var $kwInput   = $row.find( '.aicfm-row-keyword' );
			var keyword    = $.trim( $kwInput.val() );
			var $rowStatus = $( '#aicfm-row-status-' + postId );
			var $cell      = $( '#aicfm-freshness-cell-' + postId );
			var origText   = $btn.text();

			// Validate target keyword presence.
			if ( ! keyword ) {
				var emptyMsg = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.enter_keyword_first )
					? window.aicfm_vars.i18n.enter_keyword_first
					: 'Please enter a target keyword first.';

				alert( emptyMsg );
				$kwInput.trigger( 'focus' );
				return;
			}

			// Set auditing state.
			$btn.prop( 'disabled', true );
			var auditingText = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.auditing_serp )
				? window.aicfm_vars.i18n.auditing_serp
				: 'Auditing SERP...';

			$btn.text( auditingText );
			$rowStatus.html( '<span class="spinner is-active"></span>' );

			$.ajax( {
				url: ( window.aicfm_vars && window.aicfm_vars.ajax_url ) ? window.aicfm_vars.ajax_url : ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action:  'aicfm_execute_post_audit',
					nonce:   ( window.aicfm_vars && window.aicfm_vars.post_nonce ) ? window.aicfm_vars.post_nonce : '',
					post_id: postId,
					keyword: keyword
				}
			} ).done( function( response ) {
				$rowStatus.empty();

				if ( response && response.success && response.data ) {
					var reportData = response.data.report || response.data;
					var score      = parseInt( reportData.score, 10 ) || 0;
					score          = Math.max( 0, Math.min( 100, score ) );

					var scoreClass = 'score-green';
					if ( score < 50 ) {
						scoreClass = 'score-red';
					} else if ( score < 70 ) {
						scoreClass = 'score-orange';
					}

					var $pill = $( '<span/>' )
						.addClass( 'aicfm-score-pill ' + scoreClass )
						.text( score );

					$cell.empty().append( $pill );

					if ( response.data.last_audited ) {
						var $date = $( '<span/>' )
							.addClass( 'aicfm-status-date' )
							.text( response.data.last_audited );
						$cell.append( $date );
					}

					var rescanText = ( window.aicfm_vars && window.aicfm_vars.i18n && window.aicfm_vars.i18n.rescan )
						? window.aicfm_vars.i18n.rescan
						: 'Re-Scan';

					$btn.text( rescanText );
				} else {
					var errMsg = ( response && response.data && response.data.message )
						? response.data.message
						: 'Audit failed.';

					$rowStatus.html( '<span class="aicfm-badge aicfm-badge-error" title="' + errMsg + '">✗ Error</span>' );
					$btn.text( origText );
				}
			} ).fail( function( jqXHR, textStatus, errorThrown ) {
				$rowStatus.empty();
				var statusErr = errorThrown || textStatus || 'Request error.';
				$rowStatus.html( '<span class="aicfm-badge aicfm-badge-error" title="' + statusErr + '">✗ Error</span>' );
				$btn.text( origText );
			} ).always( function() {
				$btn.prop( 'disabled', false );
			} );
		} );
	} );
} )( jQuery );
