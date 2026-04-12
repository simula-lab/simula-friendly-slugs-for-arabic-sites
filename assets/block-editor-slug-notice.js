( function( wp, config ) {
	if ( ! wp || ! config || ! wp.domReady || ! wp.data ) {
		return;
	}

	var domReady = wp.domReady;
	var select = wp.data.select;
	var dispatch = wp.data.dispatch;
	var subscribe = wp.data.subscribe;
	var noticesStore = 'core/notices';
	var editorStore = 'core/editor';
	var notices = dispatch( noticesStore );
	var lastPostId = 0;
	var lastIsSavingPost = false;
	var lastKnownStateKey = '';
	var inflightRequest = null;
	var refreshTimers = [];

	function removeDivergenceNotice() {
		notices.removeNotice( config.noticeId );
		lastKnownStateKey = '';
	}

	function clearScheduledRefreshes() {
		refreshTimers.forEach( function( timerId ) {
			window.clearTimeout( timerId );
		} );
		refreshTimers = [];
	}

	function buildAction( label, url ) {
		return {
			label: label,
			onClick: function() {
				runSlugAction( url );
			},
		};
	}

	function renderStatusNotice() {
		if ( ! config.statusMessage || ! config.statusMessage.text ) {
			return;
		}

		notices.createNotice(
			config.statusMessage.type || 'info',
			config.statusMessage.text,
			{
				id: config.statusNoticeId,
				isDismissible: true,
			}
		);
	}

	function renderDivergenceNotice( state ) {
		var actionUrls = state.action_urls || {};
		var labels = config.labels || {};
		var message = [
			labels.title || '',
			labels.body || '',
			labels.current || 'Current slug:',
			state.current_slug || '',
			labels.suggested || 'Suggested slug:',
			state.suggested_slug || '',
		].join( ' ' );

		notices.createNotice(
			'warning',
			message,
			{
				id: config.noticeId,
				isDismissible: true,
				actions: [
					buildAction( labels.keep || 'Keep current slug', actionUrls.keep_current_slug ),
					buildAction( labels.useFriendly || 'Use friendly slug', actionUrls.use_friendly_slug ),
				].filter( function( action ) {
					return typeof action.onClick === 'function';
				} ),
			}
		);
	}

	function buildStateKey( state ) {
		return [
			state.post_id || 0,
			state.current_slug || '',
			state.suggested_slug || '',
			state.manual_lock ? '1' : '0',
			state.should_show_notice ? '1' : '0',
		].join( '|' );
	}

	function fetchDivergenceState( postId ) {
		if ( ! postId || inflightRequest ) {
			return;
		}

		inflightRequest = window.fetch(
			config.ajaxUrl +
				'?action=' + encodeURIComponent( config.ajaxAction ) +
				'&post_id=' + encodeURIComponent( postId ) +
				'&nonce=' + encodeURIComponent( config.ajaxNonce ),
			{
				credentials: 'same-origin',
			}
		)
			.then( function( response ) {
				return response.json();
			} )
			.then( function( payload ) {
				inflightRequest = null;

				if ( ! payload || ! payload.success || ! payload.data ) {
					return;
				}

				var state = payload.data;
				var stateKey = buildStateKey( state );

				if ( ! state.should_show_notice ) {
					removeDivergenceNotice();
					return;
				}

				if ( stateKey === lastKnownStateKey ) {
					return;
				}

				lastKnownStateKey = stateKey;
				notices.removeNotice( config.noticeId );
				renderDivergenceNotice( state );
			} )
			.catch( function() {
				inflightRequest = null;
			} );
	}

	function runSlugAction( actionUrl ) {
		var url = new window.URL( actionUrl, window.location.origin );
		var postId = url.searchParams.get( 'post_id' ) || url.searchParams.get( 'post' ) || '';
		var actionName = url.searchParams.get( 'simula_slug_action' ) || '';
		if ( ! postId || ! actionName ) {
			return;
		}

		var body = new window.URLSearchParams();
		body.append( 'action', config.runActionAjaxAction );
		body.append( 'nonce', config.ajaxNonce );
		body.append( 'post_id', postId );
		body.append( 'simula_slug_action', actionName );

		window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		} )
			.then( function( response ) {
				return response.json();
			} )
			.then( function( payload ) {
				if ( ! payload || ! payload.success || ! payload.data ) {
					return;
				}

				if ( payload.data.message && payload.data.message.text ) {
					notices.createNotice(
						payload.data.message.type || 'success',
						payload.data.message.text,
						{
							id: config.statusNoticeId,
							isDismissible: true,
						}
					);
				}

				if ( payload.data.divergence && ! payload.data.divergence.should_show_notice ) {
					removeDivergenceNotice();
					return;
				}

				scheduleRefreshBurst( postId );
			} );
	}

	function scheduleRefreshBurst( postId ) {
		if ( ! postId ) {
			return;
		}

		clearScheduledRefreshes();
		[ 0, 250, 800, 1500 ].forEach( function( delay ) {
			refreshTimers.push(
				window.setTimeout( function() {
					fetchDivergenceState( postId );
				}, delay )
			);
		} );
	}

	function refreshFromEditorState() {
		var editor = select( editorStore );
		if ( ! editor ) {
			return;
		}

		var postId = editor.getCurrentPostId ? editor.getCurrentPostId() : 0;
		var isSavingPost = editor.isSavingPost ? editor.isSavingPost() : false;
		var isAutosavingPost = editor.isAutosavingPost ? editor.isAutosavingPost() : false;

		if ( postId && postId !== lastPostId ) {
			lastPostId = postId;
			scheduleRefreshBurst( postId );
		}

		if ( lastIsSavingPost && ! isSavingPost && ! isAutosavingPost && postId ) {
			scheduleRefreshBurst( postId );
		}

		lastIsSavingPost = isSavingPost;
	}

	domReady( function() {
		renderStatusNotice();
		if ( config.initialPostId ) {
			lastPostId = config.initialPostId;
			scheduleRefreshBurst( config.initialPostId );
		}

		refreshFromEditorState();
		subscribe( refreshFromEditorState );
	} );
} )( window.wp, window.simulaFriendlySlugsBlockEditor );
