<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://github.com/MaxOrelus
 * @since      1.0.0
 *
 * @package    Wp_Es_Feeder
 * @subpackage Wp_Es_Feeder/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap wp_es_settings">
    <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
    <form method="post" name="elasticsearch_options" action="options.php">
    <?php
			// Import all the options from the databse
			$options = get_option($this->plugin_name);

			if ($options) {
				$es_url = $options['es_url'];
				$es_index = $options['es_index'];
				// $es_auth_required = $options['es_auth_required'];
				$es_access_key = $options['es_access_key'];
				$es_secret_key = $options['es_secret_key'];
				$es_post_types = $options['es_post_types'];
			}
    ?>

    <?php
        settings_fields($this->plugin_name);
        do_settings_sections($this->plugin_name);
    ?>

    <div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<div class="inside" style="display: none;">
							<input type="text" value="<?php echo site_url(); ?>" class="regular-text" id="es_wp_domain" name="<?php echo $this->plugin_name; ?>[es_wp_domain]" value="<?php echo $es_wp_domain; ?>" disabled/>
						</div>

						<h2><span><?php esc_attr_e( 'Elasticsearch Server URL', 'wp_admin_style' ); ?></span></h2>
						<div class="inside">
							<input type="text" placeholder="http://localhost:9200/" class="regular-text" id="es_url" name="<?php echo $this->plugin_name; ?>[es_url]" value="<?php if(!empty($es_url)) echo $es_url; ?>"/>
							<!--<span class="description"><?php esc_attr_e( 'It must include the trailing slash "/"', 'wp_admin_style' ); ?></span><br>-->
						</div>

						<h2><span><?php esc_attr_e( 'Index Name', 'wp_admin_style' ); ?></span></h2>
						<div class="inside">
							<input type="text" placeholder="sitename.com" class="regular-text" id="es_index" name="<?php echo $this->plugin_name; ?>[es_index]" value="<?php if(!empty($es_index)) echo $es_index; ?>"/><br/>
						</div>

						<hr/>

						<h2><span><?php esc_attr_e( 'AWS Authentication (optional)', 'wp_admin_style' ); ?></span></h2>

						<div class="inside">
							<input type="text" placeholder="Access Key ID" class="regular-text" id="es_access_key" name="<?php echo $this->plugin_name; ?>[es_access_key]" value="<?php if(!empty($es_access_key)) echo $es_access_key; ?>"/>
						</div>

						<div class="inside">
							<input type="text" placeholder="Secret Access Key" class="regular-text" id="es_secret_key" name="<?php echo $this->plugin_name; ?>[es_secret_key]" value="<?php if(!empty($es_secret_key)) echo $es_secret_key; ?>"/>
						</div>

						<hr/>

						<h2><span><?php esc_attr_e( 'Post Types', 'wp_admin_style' ); ?></span></h2>
						<div class="inside">
							<p>Select the post-types to index into Elasticsearch.</p>
							<?php $post_types = get_post_types(array( 'public' => true ));
							foreach($post_types as $key => $value) {
								// whether the post type is active or not
								$value_state = $es_post_types[$value];
								if ($value_state == 1) { $checked = 'checked="checked"'; }
								else { $checked = '';}

								// change attachment to media
								if ($value == 'attachment') { $value = 'media'; }

								// html structure
								echo '<fieldset>
												<legend class="screen-reader-text"><span>es_post_type_'.$value.'</span></legend>
												<label for="es_post_type_'.$value.'">
													<input type="checkbox" id="es_post_type_'.$value.'" name="'.$this->plugin_name.'[es_post_type_'.$value.']" '.$checked.'/>
													<span>'.$value.'</span>
												</label>
											</fieldset>';
							}?>
						</div>

						<hr/>

						<h2><span><?php esc_attr_e( 'Manage', 'wp_admin_style' ); ?></span></h2>
						<div class="inside">
							<input class="button-secondary" type="button" id="es_test_connection" name="es_test_connection" value="<?php esc_attr_e( 'Test Connection' ); ?>" />
							<input class="button-secondary" type="button" id="es_create_index" name="es_create_index" value="<?php esc_attr_e( 'Create Index' ); ?>" />
							<input class="button-secondary" type="button" id="es_reindex" name="es_reindex" value="<?php esc_attr_e( 'Re-index Data' ); ?>" />
							<input class="button-secondary" type="button" id="es_delete_index" name="es_delete_index" value="<?php esc_attr_e( 'Delete Index' ); ?>" />
						</div>

						<div class="inside index-spinner"></div>
						<hr/>

						<div class="inside">
							 <?php submit_button('Save all changes', 'primary', 'submit', true); ?>
						</div>

						<h2><span><?php esc_attr_e( 'Results', 'wp_admin_style' ); ?></span></h2>

						<div class="inside" style="margin-right: 10px;">
							<pre id="es_output" style="min-width: 100%; display: block;background-color:#eaeaea;padding:5px;"></pre>
						</div>
					</div>
				</div>
			</div>
		</div>
		<br class="clear">
	</div>
	</form>
</div>
