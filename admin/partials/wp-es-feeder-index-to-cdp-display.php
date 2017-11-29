<?php $value = get_post_meta($post->ID, '_index_post_to_cdp_option', true); ?>
<input 
  type="radio" id="index_cdp_yes" 
  name="index_post_to_cdp_option" 
  value="yes" 
  style="margin-top:-1px; vertical-align:middle;"
  <?php checked($value, ''); ?>
  <?php checked($value, 'yes'); ?>
/>
<label for="index_cdp_yes">Yes</label>
<input 
  type="radio" 
  id="index_cdp_no" 
  name="index_post_to_cdp_option" 
  value="no" 
  style="margin-top:-1px; margin-left: 10px; vertical-align:middle;"
  <?php checked($value, 'no'); ?>
/>
<label for="index_cdp_no">No</label>
