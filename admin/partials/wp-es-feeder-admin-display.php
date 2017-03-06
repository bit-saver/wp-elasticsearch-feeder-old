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
        //Grab all options
        $options = get_option($this->plugin_name);

				if ($options) {
					$es_url = $options['es_url'];
					$es_index = $options['es_index'];
					$es_auth_required = $options['es_auth_required'];
					$es_username = $options['es_username'];
					$es_password = $options['es_password'];
				}
    ?>

    <?php
        settings_fields($this->plugin_name);
        do_settings_sections($this->plugin_name);
    ?>

    <div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
			<!-- main content -->
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<h2><span><?php esc_attr_e( 'Elasticsearch Server URL', 'wp_admin_style' ); ?></span></h2>

						<div class="inside">
							<input type="text" placeholder="http://localhost:9200/" class="regular-text" id="es_url" name="<?php echo $this->plugin_name; ?>[es_url]" value="<?php if(!empty($es_url)) echo $es_url; ?>"/>
							<span class="description"><?php esc_attr_e( 'It must include the trailing slash "/"', 'wp_admin_style' ); ?></span><br>
						</div>

						<h2><span><?php esc_attr_e( 'Index Name', 'wp_admin_style' ); ?></span></h2>

						<div class="inside">
							<input type="text" placeholder="sitename.com" class="regular-text" id="es_index" name="<?php echo $this->plugin_name; ?>[es_index]" value="<?php if(!empty($es_index)) echo $es_index; ?>"/><br/>
						</div>

						<hr/>

						<h2><span><?php esc_attr_e( 'Authentication (optional)', 'wp_admin_style' ); ?></span></h2>

						<div class="inside">
							<fieldset>
							<legend class="screen-reader-text"><span>Fieldset Example</span></legend>
							<label for="users_can_register">
								<input type="checkbox" id="es_auth_required" name="<?php echo $this->plugin_name; ?>[es_auth_required]" <?php checked($es_auth_required, 1); ?>/>
								<span><?php esc_attr_e( 'Authentication required', 'wp_admin_style' ); ?></span>
							</label>
						</fieldset>
						</div>

						<div class="inside">
							<input type="text" placeholder="username" class="regular-text" id="es_username" name="<?php echo $this->plugin_name; ?>[es_username]" value="<?php if(!empty($es_username)) echo $es_username; ?>"/>
						</div>

						<div class="inside">
							<input type="password" placeholder="password" class="regular-text" id="es_passowrd" name="<?php echo $this->plugin_name; ?>[es_password]" value="<?php if(!empty($es_password)) echo $es_password; ?>"/>
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

						<!-- .inside -->

					</div>
					<!-- .postbox -->

				</div>
				<!-- .meta-box-sortables .ui-sortable -->

			</div>
			<!-- post-body-content -->

		</div>
		<!-- #post-body .metabox-holder .columns-2 -->

		<br class="clear">
	</div>
    </form>

</div>