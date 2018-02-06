<?php

require_once '../../../wp-load.php';
global $wpdb;

$log = ABSPATH . "callback.log";

file_put_contents($log, "RECEIVING ---------------------\r\n", FILE_APPEND);
file_put_contents($log, print_r($_GET,1) . "\r\n", FILE_APPEND);
file_put_contents($log, print_r($_POST,1) . "\r\n", FILE_APPEND);
file_put_contents($log, "EOL ---------------------\r\n", FILE_APPEND);


$post_id = $_POST['doc']['post_id'];
$uid = $_GET['uid'];
if ($post_id == $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_cdp_sync_uid' AND meta_value = '" . $wpdb->_real_escape($uid) . "'")) {
  if (!$_POST['error']) {
    update_post_meta($post_id,'_cdp_sync_status', 'Synced');
  } else {
    update_post_meta($post_id,'_cdp_sync_status', 'Error');
  }
  $wpdb->delete($wpdb->postmeta, array('meta_key' => '_cdp_sync_uid', 'meta_value' => $uid));
}

