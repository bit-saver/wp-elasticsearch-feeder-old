<?php
$index_to_cdp = get_post_meta($post->ID, '_iip_index_post_to_cdp_option', true);
$selected = get_post_meta($post->ID, '_iip_taxonomy_terms', true) ?: array();
function displayLevel($terms, $selected, $parent = null) {
  foreach ($terms as $term): $id = $term->_id . ($parent ? "<$parent->_id" : ''); ?>
    <option id="cdp-term-<?=$id?>" value="<?=$id?>" <?=(in_array($id, $selected) ? 'selected="selected"' : '')?>><?=($parent ? $parent->language->en . ' > ' : '')?><?=$term->language->en ?></option>
    <?php if (count($term->children)) displayLevel($term->children, $selected, $term); ?>
  <?php endforeach;
}
?>
<select id="cdp-terms" data-placeholder="Select Terms..." name="cdp_terms[]" title="CDP Taxonomy Terms" multiple style="width: 100%">
  <?php displayLevel($taxonomy, $selected);?>
</select>

