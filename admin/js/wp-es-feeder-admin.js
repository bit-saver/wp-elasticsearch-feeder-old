(function ($) {
  'use strict';
  var settings = {};
  var completed = 0;
  var total = 0;
  var current = null;

  $(window).load(function () {
    init();
    getSettings();
  });

  function init() {
    testConnectionClick();
    // createIndexClick();
    queryIndexClick();
    // deleteIndexClick();
    reindexClick();
  }

  function wpRequest(data) {
    return new Promise(function (resolve, reject) {
      $.post(
        ajaxurl, {
          action: 'es_request',
          data: data
        }, function (response) {
          resolve(response);
        });
    });
  }

  function testConnectionClick() {
    $('#es_test_connection').on('click', function (e) {
      if (!checkpoint()) return;
      connectionRequest();
    });
  }

  // function createIndexClick() {
  //   $('#es_create_index').on('click', function (e) {
  //     if (!checkpoint()) return;
  //     createIndexRequest();
  //   });
  // }

  function queryIndexClick() {
    $('#es_query_index').on('click', function (e) {
      if (!checkpoint()) return;
      getCount();
    });
  }

  // function deleteIndexClick() {
  //   $('#es_delete_index').on('click', function (e) {
  //     if (!checkpoint()) return;
  //
  //     var isConfirmed = confirm('Deleting an index removes all of the data stored in that index. Do you want to continue?');
  //     if (!isConfirmed) {
  //       return;
  //     }
  //
  //     deleteIndexRequest();
  //   });
  // }

  function reindexClick() {
    $('#es_reindex').on('click', function (e) {
      // if (!checkpoint()) return;
      //
      // deleteIndexRequest()
      //   .then(function () {
      //     createIndexRequest();
      //   }).then(function () {
      //     processRecords();
      //   });

      createProgress();

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'JSON',
        data: {
          action: 'es_initiate_sync'
        },
        success: function (result) {
          console.log(result);
          if (result.error) {
            clearProgress();
          } else {
            completed = result.completed;
            total = result.total;
            current = result.response.req;
            processQueue();
          }
        },
        error: function (result) {
          console.error(result);
          clearProgress();
        }
      });
    });
  }

  function processQueue() {
    updateProgress();
    $.ajax({
      type: 'POST',
      dataType: 'JSON',
      url: ajaxurl,
      data: {
        action: 'es_process_next'
      },
      success: function (result) {
        console.log(result);
        if (result.error || result.done) {
          clearProgress();
        } else {
          completed = result.completed;
          total = result.total;
          current = result.response.req;
          processQueue();
        }
      },
      error: function (result) {
        console.error(result);
      }
    });
  }

  function createProgress() {
    $('.index-spinner').html(renderCounter());
    $('.progress-wrapper').html('<div id="progress-bar"><span></span></div>');
  }

  function updateProgress() {
    $('.index-spinner .count').html(completed + ' / ' + total);
    $('#progress-bar span').animate({'width': (completed / total * 100) + '%'});
    $('.current-post').html('Indexing post: ' + (current.title ? current.title : current.type + ' post #' + current.post_id));
  }

  function clearProgress() {
    $('.index-spinner').empty();
    $('.progress-wrapper').empty();
  }

  function generatePostBody(method, url, elasticBody) {
    var options = {
      method: method,
      url: url
    };

    if (elasticBody) {
      try {
        var body = JSON.stringify(elasticBody);
        options.body = window.btoa(encodeURIComponent(body));
      } catch (err) {
        console.info('document endured errors while encoding:');
        console.log(err);
        console.info('This is your culprit', body);
        console.log('\n\n');
      }
    }

    return options;
  }

  function connectionRequest() {
    var opts = generatePostBody('GET', settings.server);

    return wpRequest(opts)
      .then(function (data) {
        if (!data || data.error) {
          var errorMessage = 'Connection failed';
          jsonDisplay(
            JSON.stringify($.extend(data, { message: errorMessage }), null, 2)
          );
        }
        else {
          jsonDisplay(JSON.stringify(data, null, 2));
        }
      });
  }

  // function createIndexRequest() {
  //   var opts = generatePostBody('PUT', settings.server + '/' + settings.index);
  //
  //   return wpRequest(opts)
  //     .then(function (data) {
  //       if (!data | data.error) {
  //         var errorMessage = 'Index creation failed.';
  //         jsonDisplay(
  //           JSON.stringify($.extend(data, { message: errorMessage }), null, 2)
  //         );
  //       }
  //       else {
  //         jsonDisplay(JSON.stringify(data, null, 2));
  //       }
  //     });
  // }

  // function deleteIndexRequest() {
  //   var opts = generatePostBody('DELETE', settings.server + '/' + settings.index);
  //
  //   return wpRequest(opts)
  //     .then(function (data) {
  //       if (!data | data.error) {
  //         var errorMessage = 'Index deletion failed';
  //         jsonDisplay(
  //           JSON.stringify($.extend(data, { message: errorMessage }), null, 2)
  //         );
  //       }
  //       else {
  //         jsonDisplay(JSON.stringify(data, null, 2));
  //       }
  //     });
  // }

  function processRecords() {
    $('.index-spinner').html(renderCounter());

    var postTypePromises = [];
    settings.postTypes.forEach(function (type) {
      postTypePromises.push(getPostTypeList(type));
    });

    Promise.all(postTypePromises).then(function (isProcessing) {
      var isAllComplete = isProcessing.every(function (processing) {
        return processing === false;
      });
      if (isAllComplete) {
        var timer = setTimeout(function () {
          getCount();
          clearTimeout(timer);
        }, 5000);
      }
    })
      .catch(function (error) {
        console.error(error);
        $('.index-spinner').empty();
        var errorMessage = 'Error encountered while indexing.';
        jsonDisplay(
          JSON.stringify({ error: true, message: errorMessage }, null, 2)
        );
      });
  }

  function getPostTypeList(type, page) {
    if (!page) { page = 1; }
    if (!type) { throw new Error('getPostTypeList(): no post-type parameter supplied'); }

    // apiType is the plural version of the post-type
    var apiType = $('[data-type="' + type + '"]').text().toLowerCase();
    return request(settings.domain + '/wp-json/elasticsearch/v1/' + apiType + '?page=' + page, {
      credentials: 'include'
    })
      .then(function (data) {
        if (!(data instanceof Array)) {
          return true;
        }
        else if (!data.length) {
          return true;
        }

        data.forEach(function (record) {
          indexRecord(record, type);
        });

        page++;
      })
      .then(function (isEmpty) {
        if (isEmpty) {
          return false;
        }

        return getPostTypeList(type, page);
      });
  }

  function indexRecord(data, type) {
    var opts = generatePostBody('POST', settings.server + '/' + settings.index + '/' + type, data);
    return wpRequest(opts);
  }

  function getCount() {
    var opts = generatePostBody('POST', settings.server + '/search', {index: 'videos'});

    wpRequest(opts).then(function (data) {
      var count = (typeof data.count === "undefined") ? 0 : data.count;
      $('.index-spinner')
        .html('<span style="top: 10px; position: absolute;">' + count + ' records indexed.</span>');
      jsonDisplay(
        JSON.stringify(data, null, 2)
      );
    });
  }

  function checkpoint() {
    getSettings();
    // if (!isValidIndex()) {
    //   return false;
    // }
    return true;
  }

  function renderCounter() {
    var html = '<div class="spinner is-active spinner-animation">';
    html += 'Processing... Do not leave this page. <span class="count"></span> <span class="current-post"></span>';
    html += '</div>';
    return html;
  }

  function request(url, options) {
    return fetch(url, options)
      .then(function (response) {
        return response.json();
      })
      .catch(function (error) {
        return { error: true, message: '' };
      });
  }

  function jsonDisplay(str) {
    if (typeof str !== 'string') {
      throw new Error('jsonDisplay(): argument must be a string');
    }
    $('#es_output').text(str);
  }

  // function notice(str) {
  //   if (typeof str !== 'string') {
  //     throw new Error('notice(): argument must be a string');
  //   }
  //   $('.wp_es_settings').prepend(str);
  // }
  //
  // function noticeTimer() {
  //   var timer = setTimeout(function () {
  //     $('.notice').remove();
  //     clearTimeout(timer);
  //   }, 5000);
  // }
  //
  // function isValidIndex() {
  //   var validName = new RegExp('(^[a-z0-9_\.-]+$)', 'gi');
  //
  //   if (!settings.index || !validName.test(settings.index)) {
  //     var errorMessage = 'Please supply a valid index name.';
  //     notice('<div class="notice notice-error"><p>' + errorMessage + '</p></div>');
  //     noticeTimer();
  //     return false;
  //   }
  //
  //   return true;
  // }

  function getSelectedPostTypes() {
    var types = [];
    $('[id^="es_post_type_"]').each(function (index, element) {
      if (element.checked) {
        types.push($(element).next().attr('data-type').toLowerCase())
      }
    });

    return types;
  }

  // function getRegion() {
  //   var server = $('#es_url').val();
  //
  //   if (server.indexOf('amazonaws.com') < 0) {
  //     return '';
  //   }
  //
  //   server = server.split('.');
  //
  //   if (server.length === 5) {
  //     return server[1];
  //   }
  //
  //   throw new Error('getRegion(): Not a properly formatted AWS URL');
  // }

  function getSettings() {
    settings = {
      domain: $('#es_wpdomain').val(),
      server: $('#es_url').val(),
      postTypes: getSelectedPostTypes(),
      auth: {
        enabled: $('#es_auth_required').is(':checked'),
        config: {
          accessKeyId: $('#es_access_key').val(),
          secretAccessKey: $('#es_secret_key').val()
        }
      }
    };
    return settings;
  }
})(jQuery);
