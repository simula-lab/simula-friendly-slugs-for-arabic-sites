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
	var generateButtonClass = 'simula-generate-slug-button';
	var isActionRunning = false;

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
		if ( ! url ) {
			return null;
		}

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

	function setGenerateButtonBusy( busy ) {
		var button = document.querySelector( '.' + generateButtonClass );
		if ( ! button ) {
			return;
		}

		button.disabled = !! busy;
		button.textContent = busy
			? ( ( config.labels && config.labels.generating ) || 'Generating friendly slug...' )
			: ( ( config.labels && config.labels.generate ) || 'Generate friendly slug' );
	}

	function syncEditorSlug( slug ) {
		if ( ! slug ) {
			return;
		}

		var editorDispatch = dispatch( editorStore );
		if ( editorDispatch && typeof editorDispatch.editPost === 'function' ) {
			editorDispatch.editPost( { slug: slug } );
		}

		var slugInputs = document.querySelectorAll( 'input[aria-label="URL Slug"], input.editor-post-slug__input, input.components-text-control__input' );
		slugInputs.forEach( function( input ) {
			if ( input && input.value !== slug ) {
				input.value = slug;
				input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
			}
		} );
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
		if ( isActionRunning ) {
			return;
		}

		var url = new window.URL( actionUrl, window.location.origin );
		var postId = url.searchParams.get( 'post_id' ) || url.searchParams.get( 'post' ) || '';
		var actionName = url.searchParams.get( 'simula_slug_action' ) || '';
		if ( ! postId || ! actionName ) {
			return;
		}

		isActionRunning = true;
		if ( actionName === config.generateAction ) {
			setGenerateButtonBusy( true );
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

				if ( payload.data.divergence && payload.data.divergence.current_slug ) {
					syncEditorSlug( payload.data.divergence.current_slug );
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
			} )
			.finally( function() {
				isActionRunning = false;
				setGenerateButtonBusy( false );
			} );
	}

	function ensureGenerateButton() {
		if ( document.querySelector( '.' + generateButtonClass ) ) {
			return;
		}

		var editor = select( editorStore );
		var postId = editor && editor.getCurrentPostId ? editor.getCurrentPostId() : 0;
		if ( ! postId ) {
			return;
		}

		var permalinkPanel = document.querySelector( '.editor-post-url, .components-panel__body .editor-post-url__panel-toggle, .editor-post-url__input' );
		if ( ! permalinkPanel ) {
			return;
		}

		var container = permalinkPanel.closest( '.components-base-control, .components-panel__body, .editor-post-url' ) || permalinkPanel.parentNode;
		if ( ! container ) {
			return;
		}

		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'components-button is-secondary ' + generateButtonClass;
		button.style.marginTop = '8px';
		button.textContent = ( config.labels && config.labels.generate ) || 'Generate friendly slug';
		button.addEventListener( 'click', function() {
			runSlugAction(
				config.ajaxUrl +
				'?action=' + encodeURIComponent( config.runActionAjaxAction ) +
				'&post_id=' + encodeURIComponent( postId ) +
				'&simula_slug_action=' + encodeURIComponent( config.generateAction || '' )
			);
		} );

		var wrap = document.createElement( 'div' );
		wrap.className = 'simula-generate-slug-wrap';
		wrap.appendChild( button );
		container.appendChild( wrap );
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
		ensureGenerateButton();
		new window.MutationObserver( ensureGenerateButton ).observe( document.body, {
			childList: true,
			subtree: true,
		} );
		subscribe( refreshFromEditorState );
	} );
} )( window.wp, window.simulaFriendlySlugsBlockEditor );
