(function ($) {
  'use strict';
  var settings = {};
  var ELASTIC_PROXY = 'http://localhost:3000/api/elasticsearch';

  $(window).load(function () {
    init();
    getSettings();
  });

  function init() {
    testConnectionClick();
    createIndexClick();
    deleteIndexClick();
    reindexClick();
  }

  function testConnectionClick() {
    $('#es_test_connection').on('click', function (e) {
      if (!checkpoint()) return;
      connectionRequest();
    });
  }

  function createIndexClick() {
    $('#es_create_index').on('click', function (e) {
      if (!checkpoint()) return;
      createIndexRequest();
    });
  }

  function deleteIndexClick() {
    $('#es_delete_index').on('click', function (e) {
      if (!checkpoint()) return;

      var isConfirmed = confirm('Deleting an index removes all of the data stored in that index. Do you want to continue?');
      if (!isConfirmed) {
        return;
      }

      deleteIndexRequest();
    });
  }

  function reindexClick() {
    $('#es_reindex').on('click', function (e) {
      if (!checkpoint()) return;

      deleteIndexRequest()
        .then(function () {
          createIndexRequest();
        }).then(function () {
          processRecords();
        });
    });
  }

  function generatePostBody(method, url, elasticBody) {
    return {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        url: url,
        auth: $.extend({}, settings.auth.config),
        options: {
          method: method,
          'content-type': 'application/json',
          body: elasticBody
        }
      })
    };
  }

  function connectionRequest() {
    var opts = generatePostBody('GET', settings.server);

    return request(ELASTIC_PROXY, opts)
      .then(function (data) {
        if (data.error || !data) {
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

  function createIndexRequest() {
    var opts = generatePostBody('PUT', settings.server + '/' + settings.index);

    return request(ELASTIC_PROXY, opts)
      .then(function (data) {
        if (data.error) {
          var errorMessage = 'Index creation failed.';
          jsonDisplay(
            JSON.stringify($.extend(data, { message: errorMessage }), null, 2)
          );
        }
        else {
          jsonDisplay(JSON.stringify(data, null, 2));
        }
      });
  }

  function deleteIndexRequest() {
    var opts = generatePostBody('DELETE', settings.server + '/' + settings.index);

    return request(ELASTIC_PROXY, opts)
      .then(function (data) {
        if (data.error) {
          var errorMessage = 'Index deletion failed';
          jsonDisplay(
            JSON.stringify($.extend(data, { message: errorMessage }), null, 2)
          );
        }
        else {
          jsonDisplay(JSON.stringify(data, null, 2));
        }
      });
  }

  function processRecords() {
    $('.index-spinner').html(renderCounter());

    var postTypePromises = [];
    settings.postTypes.forEach(function (type) {
      postTypePromises.push(getPostTypeList(type));
    })

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
    var origin = document.location.origin;

    return request(origin + '/wp-json/elasticsearch/v1/' + typeUtility(type) + '?page=' + page)
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

  function typeUtility(type) {
    if (type === 'media') {
      return type;
    }
    return type + 's';
  }

  function indexRecord(data, type) {
    var opts = generatePostBody('POST', settings.server + '/' + settings.index + '/' + type, data);

    return request(ELASTIC_PROXY, opts);
  }

  function getCount() {
    var opts = generatePostBody('GET', settings.server + '/' + settings.index + '/_count')

    request(ELASTIC_PROXY, opts).then(function (data) {
      $('.index-spinner')
        .html('<span style="top: 10px; position: absolute;">Indexed ' + data.count + ' records.</span>');
      jsonDisplay(
        JSON.stringify(data, null, 2)
      );
    });
  }

  function checkpoint() {
    getSettings();
    if (!isValidIndex()) {
      return false;
    }
    return true;
  }

  function renderCounter() {
    var html = '<div class="spinner is-active spinner-animation">';
    html += 'Processing... Do not leave this page.';
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

  function notice(str) {
    if (typeof str !== 'string') {
      throw new Error('notice(): argument must be a string');
    }
    $('.wp_es_settings').prepend(str);
  }

  function noticeTimer() {
    var timer = setTimeout(function () {
      $('.notice').remove();
      clearTimeout(timer);
    }, 5000);
  }

  function isValidIndex() {
    var validName = new RegExp('(^[a-z0-9_\.-]+$)', 'gi');

    if (!settings.index || !validName.test(settings.index)) {
      var errorMessage = 'Please supply a valid index name.';
      notice('<div class="notice notice-error"><p>' + errorMessage + '</p></div>');
      noticeTimer();
      return false;
    }

    return true;
  }

  function getSelectedPostTypes() {
    var types = [];
    $('[id^="es_post_type_"]').each(function (index, element) {
      if (element.checked) {
        var tmpArray = element.id.split('_');
        types.push(tmpArray[3]);
      }
    });
    return types;
  }

  function getRegion() {
    var server = $('#es_url').val();

    if (server.indexOf('amazonaws.com') < 0) {
      return '';
    }

    server = server.split('.');

    if (server.length === 5) {
      return server[1];
    }

    throw new Error('getRegion(): Not a properly formatted AWS URL');
  }

  function getSettings() {
    settings = {
      server: $('#es_url').val(),
      index: $('#es_index').val(),
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
