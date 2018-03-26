'use strict';
(function($) {
  // sync object contains data related to the current (if any) resync
  var sync = {
    total: 0,
    complete: 0,
    post: null,
    paused: false
  };

  var last_heartbeat = null;
  var last_heartbeat_timer = null;

  /**
   * Register click listener functions, load sync data from the injected variable, and
   * update sync state if a sync was in progress.
   */
  $(window).load(function() {
    $('#truncate_logs').on('click', truncateLogs);
    $('#es_test_connection').on('click', testConnection);
    $('#es_query_index').on('click', queryIndex);
    $('#es_resync').on('click', resyncStart(0));
    $('#es_resync_errors').on('click', resyncStart(1));
    $('#es_resync_control').on('click', resyncControl);

    console.log(es_feeder_sync);
    sync.total = parseInt(es_feeder_sync.total);
    sync.complete = parseInt(es_feeder_sync.complete);
    sync.paused = es_feeder_sync.paused === "1";
    if (sync.paused) {
      createProgress();
      updateProgress();
    }

    $(document).on('heartbeat-send', function (event, data) {
      data.es_sync_status_counts = 1;
    });
    $(document).on('heartbeat-tick', function (event, data) {
      resetHeartbeatTimer();
      if (!data.es_sync_status_counts) return;
      $('.status-count').each(function (i, status) {
        var $status = $(status);
        var id = $status.attr('data-status-id');
        var newCount = data.es_sync_status_counts[id] || 0;
        if ($status.html() !== '' + newCount) {
          $status.fadeOut( 'slow', function () {
            $status.html( newCount );
            $status.fadeIn('slow');
          } );
        }
      });
    } );
    resetHeartbeatTimer();
  });

  function resetHeartbeatTimer() {
    if (last_heartbeat_timer)
      clearInterval(last_heartbeat_timer);
    last_heartbeat = 0;
    $('#last-heartbeat').html(last_heartbeat + 's ago (usually every 15s)');
    last_heartbeat_timer = setInterval(function() {
      last_heartbeat++;
      $('#last-heartbeat').html(last_heartbeat + 's ago (usually every 15s)');
    }, 1000);
  }

  function truncateLogs() {
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      dataType: 'JSON',
      data: {
        _wpnonce: $('#_wpnonce').val(),
        action: 'es_truncate_logs'
      },
      success: function (result) {
        alert('Logs truncated.');
      },
      error: function (result) {
        console.error(result);
        alert('Communication error while truncating logs.');
      }
    })
  }

  /**
   * Send a basic request to the provided URL and print the response in the output container.
   */
  function testConnection() {
    $('#es_output').text('');
    disableManage();
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      dataType: 'JSON',
      data: {
        _wpnonce: $('#_wpnonce').val(),
        action: 'es_request',
        data: {
          method: 'GET',
          url: $('#es_url').val()
        }
      },
      success: function (result) {
        $('#es_output').text(JSON.stringify(result, null, 2));
      },
      error: function (result) {
        $('#es_output').text(JSON.stringify(result, null, 2));
      }
    }).always(enableManage);
  }

  /**
   * Execute an arbitrary query against the API.
   */
  function queryIndex() {
    // TODO: Add query handler to house a query test of some kind.
  }

  /**
   * TODO: Initiate a new sync by deleting ALL of this site's posts from ES
   * Clear out old sync post meta (if any) and initiate a new sync process.
   */
  function resyncStart(errorsOnly) {
    return function() {
      var $notice = $('.feeder-notice.notice-error');
      if ($notice.length > 0) {
        $notice.fadeTo( 100, 0, function () {
          $notice.slideUp( 100, function () {
            $notice.remove();
          } );
        } );
      }
      sync = {
        total: 0,
        complete: 0,
        post: null,
        paused: false
      };
      createProgress();
      updateProgress();
      $.ajax( {
        url: ajaxurl,
        type: 'POST',
        dataType: 'JSON',
        data: {
          _wpnonce: $( '#_wpnonce' ).val(),
          action: 'es_initiate_sync',
          sync_errors: errorsOnly
        },
        success: function ( result ) {
          handleQueueResult( result );
        },
        error: function ( result ) {
          console.error( result );
          clearProgress();
        }
      } );
    }
  }

  /**
   * Pause or resume the current sync process and update the UI accordingly.
   */
  function resyncControl() {
    if (sync.paused) {
      $('#es_resync_control').html('Pause Sync');
      sync.paused = false;
      $('#progress-bar').removeClass('paused');
      $('.spinner-text').html('Processing... Leaving this page will pause the resync.');
      processQueue();
    } else {
      $('#es_resync_control').html('Resume Sync');
      $('#progress-bar').addClass('paused');
      $('.spinner-text').html('Paused.');
      sync.paused = true;
    }
  }

  /**
   * Trigger backend processing of the next available Post in the sync queue
   * and relay the results to the result handler function.
   */
  function processQueue() {
    if (sync.paused) return;
    $.ajax({
      type: 'POST',
      dataType: 'JSON',
      url: ajaxurl,
      data: {
        _wpnonce: $('#_wpnonce').val(),
        action: 'es_process_next'
      },
      success: function (result) {
        handleQueueResult(result);
      },
      error: function (result) {
        console.error(result);
      }
    });
  }

  /**
   * Store result data in the local variable and update the state and progress bar,
   * and spew the raw result into the output container.
   *
   * @param result
   */
  function handleQueueResult(result) {
    if (result.error || result.done) {
      clearProgress();
    } else {
      sync.complete = result.complete;
      sync.total = result.total;
      sync.post = result.response.req;
      updateProgress();
      processQueue();
    }
    $('#es_output').prepend(JSON.stringify(result, null, 2) + "\r\n\r\n");
  }

  /**
   * Add relevant markup for the progress bar and state UI/UX.
   */
  function createProgress() {
    var html = '<div class="spinner is-active spinner-animation">';
    html += '<span class="spinner-text">' + (sync.paused ? 'Paused.' : 'Processing... Leaving this page will pause the resync.') + '</span> <span class="count"></span> <span class="current-post"></span>';
    html += '</div>';
    $('.index-spinner').html(html);
    $('.progress-wrapper').html('<div id="progress-bar" ' + (sync.paused ? 'class="paused"' : '') + '><span></span></div>');
    $('#es_resync_control').html(sync.paused ? 'Resume Sync' : 'Pause Sync').show();
    $('#es_output').empty();
  }

  /**
   * Update the pgoress bar and state UI using the local sync variable.
   */
  function updateProgress() {
    $('.index-spinner .count').html(sync.complete + ' / ' + sync.total);
    $('#progress-bar span').animate({'width': (sync.complete / sync.total * 100) + '%'});
    $('.current-post').html((sync.post ? 'Indexing post: ' + (sync.post.title ? sync.post.title : sync.post.type + ' post #' + sync.post.post_id) : ''));
  }

  /**
   * Remove progress bar and state UI.
   */
  function clearProgress() {
    $('.index-spinner').empty();
    $('.progress-wrapper').empty();
    $('#es_resync_control').hide();
  }

  /**
   * Disable the manage buttons.
   */
  function disableManage() {
    $('.inside.manage-btns button').attr('disabled', true);
  }

  /**
   * Enable the manage buttons.
   */
  function enableManage() {
    $('.inside.manage-btns button').attr('disabled', null);
  }
})(jQuery);