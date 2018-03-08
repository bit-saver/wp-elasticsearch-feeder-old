<?php
$index_to_cdp = get_post_meta($post->ID, '_iip_index_post_to_cdp_option', true);
$selected = get_post_meta($post->ID, '_iip_taxonomy_terms', true) ?: array();

function displayLevel($terms, $selected, $parent = null) {
  foreach ($terms as $term): ?>
    <option id="cdp-term-<?=$term->_id?>" value="<?=$term->_id?>" <?=(in_array($term->_id, $selected) ? 'selected="selected"' : '')?>><?=($parent ? $parent->language->{'en-US'} . ' > ' : '')?><?=$term->language->{'en-US'} ?></option>
    <?php if (count($term->children)) displayLevel($term->children, $selected, $term); ?>
  <?php endforeach;
}
?>
<select id="cdp-terms" data-placeholder="Select Terms..." name="cdp_terms[]" title="CDP Taxonomy Terms" multiple style="width: 100%">
  <?php displayLevel($taxonomy, $selected);?>
</select>

