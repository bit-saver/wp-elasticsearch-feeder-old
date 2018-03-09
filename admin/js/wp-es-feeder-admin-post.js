'use strict';
(function ( $ ) {
  $( document ).on( 'heartbeat-send', function ( event, data ) {
    data.es_sync_status = es_feeder_sync_status_post_id;
  } );
  $( document ).on( 'heartbeat-tick', function ( event, data ) {
    if ( !data.es_sync_status ) return;
    $( '#cdp_sync_status' ).html( data.es_sync_status );
  } );

  $(document).ready(function() {
    $('#cdp-terms').chosen({width: '100%'});
    toggleTaxBox();
    $('input[name=index_post_to_cdp_option]').change(toggleTaxBox);
  });

  function toggleTaxBox() {
    if ($('#index_cdp_yes').is(':checked')) {
      $('#cdp-taxonomy').show();
    } else {
      $('#cdp-taxonomy').hide();
    }
  }
})( jQuery );