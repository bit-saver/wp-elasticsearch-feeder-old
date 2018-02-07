'use strict';
(function($) {
  var sync = {
    total: 0,
    complete: 0,
    post: null,
    paused: false
  };

  $(window).load(function() {
    $('#es_test_connection').on('click', testConnection);
    $('#es_query_index').on('click', queryIndex);
    $('#es_resync').on('click', resyncStart);
    $('#es_resync_control').on('click', resyncControl);
    console.log(es_feeder_sync);
    sync.total = parseInt(es_feeder_sync.total);
    sync.complete = parseInt(es_feeder_sync.complete);
    sync.paused = es_feeder_sync.paused === "1";
    if (sync.paused) {
      createProgress();
      updateProgress();
    }
  });

  function testConnection() {
    $('#es_output').text('');
    disableManage();
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      dataType: 'JSON',
      data: {
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

  function queryIndex() {
    // TODO: Add query handler to house a query test of some kind.
  }

  function resyncStart() {
    sync = {
      total: 0,
      complete: 0,
      post: null,
      paused: false
    };
    createProgress();
    updateProgress();
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      dataType: 'JSON',
      data: {
        action: 'es_initiate_sync'
      },
      success: function (result) {
        handleQueueResult(result);
      },
      error: function (result) {
        console.error(result);
        clearProgress();
      }
    });
  }

  function resyncControl() {
    if (sync.paused) {
      $('#es_resync_control').html('Pause Sync');
      sync.paused = false;
      $('#progress-bar').removeClass('paused');
      $('.spinner-text').html('Processing... Do not leave this page.');
      processQueue();
    } else {
      $('#es_resync_control').html('Resume Sync');
      $('#progress-bar').addClass('paused');
      $('.spinner-text').html('Paused.');
      sync.paused = true;
    }
  }

  function processQueue() {
    if (sync.paused) return;
    $.ajax({
      type: 'POST',
      dataType: 'JSON',
      url: ajaxurl,
      data: {
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
    $('#es_output').text(JSON.stringify(result, null, 2));
  }

  function createProgress() {
    var html = '<div class="spinner is-active spinner-animation">';
    html += '<span class="spinner-text">' + (sync.paused ? 'Paused.' : 'Processing... Do not leave this page.') + '</span> <span class="count"></span> <span class="current-post"></span>';
    html += '</div>';
    $('.index-spinner').html(html);
    $('.progress-wrapper').html('<div id="progress-bar" ' + (sync.paused ? 'class="paused"' : '') + '><span></span></div>');
    $('#es_resync_control').html(sync.paused ? 'Resume Sync' : 'Pause Sync').show();
  }

  function updateProgress() {
    $('.index-spinner .count').html(sync.complete + ' / ' + sync.total);
    $('#progress-bar span').animate({'width': (sync.complete / sync.total * 100) + '%'});
    $('.current-post').html((sync.post ? 'Indexing post: ' + (sync.post.title ? sync.post.title : sync.post.type + ' post #' + sync.post.post_id) : ''));
  }

  function clearProgress() {
    $('.index-spinner').empty();
    $('.progress-wrapper').empty();
    $('#es_resync_control').hide();
  }

  function disableManage() {
    $('.inside.manage-btns button').attr('disabled', true);
  }

  function enableManage() {
    $('.inside.manage-btns button').attr('disabled', null);
  }
})(jQuery);