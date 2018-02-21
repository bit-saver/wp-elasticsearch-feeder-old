'use strict';
(function ( $ ) {
  $( document ).on( 'heartbeat-send', function ( event, data ) {
    data.es_sync_status = es_feeder_sync_status_post_id;
  } );
  $( document ).on( 'heartbeat-tick', function ( event, data ) {
    if ( !data.es_sync_status ) return;
    $( '#cdp_sync_status' ).html( data.es_sync_status );
  } );
})( jQuery );