<?php
/**
 * Edit plugin editor administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

if ( is_multisite() && ! is_network_admin() ) {
	wp_redirect( network_admin_url( 'plugin-editor.php' ) );
	exit();
}

if ( !current_user_can('edit_plugins') )
	wp_die( __('Sorry, you are not allowed to edit plugins for this site.') );

$title = __("Edit Plugins");
$parent_file = 'plugins.php';

$plugins = get_plugins();

if ( empty( $plugins ) ) {
	include( ABSPATH . 'wp-admin/admin-header.php' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( $title ); ?></h1>
		<div id="message" class="error"><p><?php _e( 'You do not appear to have any plugins available at this time.' ); ?></p></div>
	</div>
	<?php
	include( ABSPATH . 'wp-admin/admin-footer.php' );
	exit;
}

$file = '';
$plugin = '';
if ( isset( $_REQUEST['file'] ) ) {
	$file = wp_unslash( $_REQUEST['file'] );
}

if ( isset( $_REQUEST['plugin'] ) ) {
	$plugin = wp_unslash( $_REQUEST['plugin'] );
}

if ( empty( $plugin ) ) {
	if ( $file ) {

		// Locate the plugin for a given plugin file being edited.
		$file_dirname = dirname( $file );
		foreach ( array_keys( $plugins ) as $plugin_candidate ) {
			if ( $plugin_candidate === $file || ( '.' !== $file_dirname && dirname( $plugin_candidate ) === $file_dirname ) ) {
				$plugin = $plugin_candidate;
				break;
			}
		}

		// Fallback to the file as the plugin.
		if ( empty( $plugin ) ) {
			$plugin = $file;
		}
	} else {
		$plugin = array_keys( $plugins );
		$plugin = $plugin[0];
	}
}

$plugin_files = get_plugin_files($plugin);

if ( empty($file) )
	$file = $plugin_files[0];

$file = validate_file_to_edit($file, $plugin_files);
$real_file = WP_PLUGIN_DIR . '/' . $file;
$scrollto = isset($_REQUEST['scrollto']) ? (int) $_REQUEST['scrollto'] : 0;

if ( isset( $_REQUEST['action'] ) && 'update' === $_REQUEST['action'] ) {

	check_admin_referer('edit-plugin_' . $file);

	$newcontent = wp_unslash( $_POST['newcontent'] );
	if ( is_writeable($real_file) ) {
		$f = fopen($real_file, 'w+');
		fwrite($f, $newcontent);
		fclose($f);

		if ( preg_match( '/\.php$/', $real_file ) && function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $real_file, true );
		}

		$network_wide = is_plugin_active_for_network( $file );

		// Deactivate so we can test it.
		if ( is_plugin_active( $plugin ) || isset( $_POST['phperror'] ) ) {
			if ( is_plugin_active( $plugin ) ) {
				deactivate_plugins( $plugin, true );
			}

			if ( ! is_network_admin() ) {
				update_option( 'recently_activated', array( $file => time() ) + (array) get_option( 'recently_activated' ) );
			} else {
				update_site_option( 'recently_activated', array( $file => time() ) + (array) get_site_option( 'recently_activated' ) );
			}

			wp_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'edit-plugin-test_' . $file ), "plugin-editor.php?file=$file&plugin=$plugin&liveupdate=1&scrollto=$scrollto&networkwide=" . $network_wide ) );
			exit;
		}
		wp_redirect( self_admin_url( "plugin-editor.php?file=$file&plugin=$plugin&a=te&scrollto=$scrollto" ) );
	} else {
		wp_redirect( self_admin_url( "plugin-editor.php?file=$file&plugin=$plugin&scrollto=$scrollto" ) );
	}
	exit;

} else {

	if ( isset($_GET['liveupdate']) ) {
		check_admin_referer('edit-plugin-test_' . $file);

		$error = validate_plugin( $plugin );

		if ( is_wp_error( $error ) ) {
			wp_die( $error );
		}

		if ( ( ! empty( $_GET['networkwide'] ) && ! is_plugin_active_for_network( $file ) ) || ! is_plugin_active( $file ) ) {
			activate_plugin( $plugin, "plugin-editor.php?file=" . urlencode( $file ) . "&phperror=1", ! empty( $_GET['networkwide'] ) );
		} // we'll override this later if the plugin can be included without fatal error

		wp_redirect( self_admin_url( 'plugin-editor.php?file=' . urlencode( $file ) . '&plugin=' . urlencode( $plugin ) . "&a=te&scrollto=$scrollto" ) );
		exit;
	}

	// List of allowable extensions
	$editable_extensions = array(
		'bash',
		'conf',
		'css',
		'diff',
		'htm',
		'html',
		'http',
		'inc',
		'include',
		'js',
		'json',
		'jsx',
		'less',
		'md',
		'patch',
		'php',
		'php3',
		'php4',
		'php5',
		'php7',
		'phps',
		'phtml',
		'sass',
		'scss',
		'sh',
		'sql',
		'svg',
		'text',
		'txt',
		'xml',
		'yaml',
		'yml',
	);

	/**
	 * Filters file type extensions editable in the plugin editor.
	 *
	 * @since 2.8.0
	 *
	 * @param array $editable_extensions An array of editable plugin file extensions.
	 */
	$editable_extensions = (array) apply_filters( 'editable_extensions', $editable_extensions );

	if ( ! is_file($real_file) ) {
		wp_die(sprintf('<p>%s</p>', __('No such file exists! Double check the name and try again.')));
	} else {
		// Get the extension of the file
		if ( preg_match('/\.([^.]+)$/', $real_file, $matches) ) {
			$ext = strtolower($matches[1]);
			// If extension is not in the acceptable list, skip it
			if ( !in_array( $ext, $editable_extensions) )
				wp_die(sprintf('<p>%s</p>', __('Files of this type are not editable.')));
		}
	}

	get_current_screen()->add_help_tab( array(
	'id'		=> 'overview',
	'title'		=> __('Overview'),
	'content'	=>
		'<p>' . __('You can use the editor to make changes to any of your plugins&#8217; individual PHP files. Be aware that if you make changes, plugins updates will overwrite your customizations.') . '</p>' .
		'<p>' . __('Choose a plugin to edit from the dropdown menu and click the Select button. Click once on any file name to load it in the editor, and make your changes. Don&#8217;t forget to save your changes (Update File) when you&#8217;re finished.') . '</p>' .
		'<p>' . __('The Documentation menu below the editor lists the PHP functions recognized in the plugin file. Clicking Look Up takes you to a web page about that particular function.') . '</p>' .
		'<p id="editor-keyboard-trap-help-1">' . __( 'When using a keyboard to navigate:' ) . '</p>' .
		'<ul>' .
		'<li id="editor-keyboard-trap-help-2">' . __( 'In the editing area, the Tab key enters a tab character.' ) . '</li>' .
		'<li id="editor-keyboard-trap-help-3">' . __( 'To move away from this area, press the Esc key followed by the Tab key.' ) . '</li>' .
		'<li id="editor-keyboard-trap-help-4">' . __( 'Screen reader users: when in forms mode, you may need to press the Esc key twice.' ) . '</li>' .
		'</ul>' .
		'<p>' . __('If you want to make changes but don&#8217;t want them to be overwritten when the plugin is updated, you may be ready to think about writing your own plugin. For information on how to edit plugins, write your own from scratch, or just better understand their anatomy, check out the links below.') . '</p>' .
		( is_network_admin() ? '<p>' . __('Any edits to files from this screen will be reflected on all sites in the network.') . '</p>' : '' )
	) );

	get_current_screen()->set_help_sidebar(
		'<p><strong>' . __('For more information:') . '</strong></p>' .
		'<p>' . __('<a href="https://codex.wordpress.org/Plugins_Editor_Screen">Documentation on Editing Plugins</a>') . '</p>' .
		'<p>' . __('<a href="https://codex.wordpress.org/Writing_a_Plugin">Documentation on Writing Plugins</a>') . '</p>' .
		'<p>' . __('<a href="https://wordpress.org/support/">Support Forums</a>') . '</p>'
	);

	$settings = wp_enqueue_code_editor( array( 'file' => $real_file ) );
	if ( ! empty( $settings ) ) {
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_add_inline_script( 'wp-theme-plugin-editor', sprintf( 'jQuery( function() { wp.themePluginEditor.init( %s ); } )', wp_json_encode( $settings ) ) );
	}

	require_once(ABSPATH . 'wp-admin/admin-header.php');

	update_recently_edited(WP_PLUGIN_DIR . '/' . $file);

	$content = file_get_contents( $real_file );

	if ( '.php' == substr( $real_file, strrpos( $real_file, '.' ) ) ) {
		$functions = wp_doc_link_parse( $content );

		if ( !empty($functions) ) {
			$docs_select = '<select name="docs-list" id="docs-list">';
			$docs_select .= '<option value="">' . __( 'Function Name&hellip;' ) . '</option>';
			foreach ( $functions as $function) {
				$docs_select .= '<option value="' . esc_attr( $function ) . '">' . esc_html( $function ) . '()</option>';
			}
			$docs_select .= '</select>';
		}
	}

	$content = esc_textarea( $content );
	?>
<?php if (isset($_GET['a'])) : ?>
 <div id="message" class="updated notice is-dismissible"><p><?php _e('File edited successfully.') ?></p></div>
<?php elseif (isset($_GET['phperror'])) : ?>
 <div id="message" class="notice notice-error"><p><?php _e( 'This plugin has been deactivated because your changes resulted in a <strong>fatal error</strong>.' ); ?></p>
	<?php
		if ( wp_verify_nonce( $_GET['_error_nonce'], 'plugin-activation-error_' . $plugin ) ) {
			$iframe_url = add_query_arg( array(
				'action'   => 'error_scrape',
				'plugin'   => urlencode( $plugin ),
				'_wpnonce' => urlencode( $_GET['_error_nonce'] ),
			), admin_url( 'plugins.php' ) );
			?>
	<iframe style="border:0" width="100%" height="70px" src="<?php echo esc_url( $iframe_url ); ?>"></iframe>
	<?php } ?>
</div>
<?php endif; ?>
<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<div class="fileedit-sub">
<div class="alignleft">
<h2>
	<?php
	if ( is_plugin_active( $plugin ) ) {
		if ( is_writeable( $real_file ) ) {
			/* translators: %s: plugin file name */
			echo sprintf( __( 'Editing %s (active)' ), '<strong>' . esc_html( $file ) . '</strong>' );
		} else {
			/* translators: %s: plugin file name */
			echo sprintf( __( 'Browsing %s (active)' ), '<strong>' . esc_html( $file ) . '</strong>' );
		}
	} else {
		if ( is_writeable( $real_file ) ) {
			/* translators: %s: plugin file name */
			echo sprintf( __( 'Editing %s (inactive)' ), '<strong>' . esc_html( $file ) . '</strong>' );
		} else {
			/* translators: %s: plugin file name */
			echo sprintf( __( 'Browsing %s (inactive)' ), '<strong>' . esc_html( $file ) . '</strong>' );
		}
	}
	?>
</h2>
</div>
<div class="alignright">
	<form action="plugin-editor.php" method="post">
		<strong><label for="plugin"><?php _e('Select plugin to edit:'); ?> </label></strong>
		<select name="plugin" id="plugin">
<?php
	foreach ( $plugins as $plugin_key => $a_plugin ) {
		$plugin_name = $a_plugin['Name'];
		if ( $plugin_key == $plugin )
			$selected = " selected='selected'";
		else
			$selected = '';
		$plugin_name = esc_attr($plugin_name);
		$plugin_key = esc_attr($plugin_key);
		echo "\n\t<option value=\"$plugin_key\" $selected>$plugin_name</option>";
	}
?>
		</select>
		<?php submit_button( __( 'Select' ), '', 'Submit', false ); ?>
	</form>
</div>
<br class="clear" />
</div>

<div id="templateside">
	<h2><?php _e( 'Plugin Files' ); ?></h2>

	<ul>
<?php
foreach ( $plugin_files as $plugin_file ) :
	// Get the extension of the file
	if ( preg_match('/\.([^.]+)$/', $plugin_file, $matches) ) {
		$ext = strtolower($matches[1]);
		// If extension is not in the acceptable list, skip it
		if ( !in_array( $ext, $editable_extensions ) )
			continue;
	} else {
		// No extension found
		continue;
	}
	?>
	<li class="<?php echo esc_attr( $file === $plugin_file ? 'notice notice-info' : '' ); ?>"><a href="plugin-editor.php?file=<?php echo urlencode( $plugin_file ); ?>&amp;plugin=<?php echo urlencode( $plugin ); ?>"><?php echo esc_html( $plugin_file ); ?></a></li>
<?php endforeach; ?>
	</ul>
</div>
<form name="template" id="template" action="plugin-editor.php" method="post">
	<?php wp_nonce_field('edit-plugin_' . $file) ?>
		<div>
			<label for="newcontent" id="theme-plugin-editor-label"><?php _e( 'Selected file content:' ); ?></label>
			<textarea cols="70" rows="25" name="newcontent" id="newcontent" aria-describedby="editor-keyboard-trap-help-1 editor-keyboard-trap-help-2 editor-keyboard-trap-help-3 editor-keyboard-trap-help-4"><?php echo $content; ?></textarea>
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="file" value="<?php echo esc_attr( $file ); ?>" />
			<input type="hidden" name="plugin" value="<?php echo esc_attr( $plugin ); ?>" />
			<input type="hidden" name="scrollto" id="scrollto" value="<?php echo esc_attr( $scrollto ); ?>" />
		</div>
		<?php if ( !empty( $docs_select ) ) : ?>
		<div id="documentation" class="hide-if-no-js"><label for="docs-list"><?php _e('Documentation:') ?></label> <?php echo $docs_select ?> <input type="button" class="button" value="<?php esc_attr_e( 'Look Up' ) ?> " onclick="if ( '' != jQuery('#docs-list').val() ) { window.open( 'https://api.wordpress.org/core/handbook/1.0/?function=' + escape( jQuery( '#docs-list' ).val() ) + '&amp;locale=<?php echo urlencode( get_user_locale() ) ?>&amp;version=<?php echo urlencode( get_bloginfo( 'version' ) ) ?>&amp;redirect=true'); }" /></div>
		<?php endif; ?>
<?php if ( is_writeable($real_file) ) : ?>
	<?php if ( in_array( $plugin, (array) get_option( 'active_plugins', array() ) ) ) { ?>
		<div class="notice notice-warning inline active-plugin-edit-warning">
			<p><?php _e('<strong>Warning:</strong> Making changes to active plugins is not recommended. If your changes cause a fatal error, the plugin will be automatically deactivated.'); ?></p>
		</div>
	<?php } ?>
	<p class="submit">
	<?php
		if ( isset($_GET['phperror']) ) {
			echo "<input type='hidden' name='phperror' value='1' />";
			submit_button( __( 'Update File and Attempt to Reactivate' ), 'primary', 'submit', false );
		} else {
			submit_button( __( 'Update File' ), 'primary', 'submit', false );
		}
	?>
	</p>
<?php else : ?>
	<p><em><?php _e('You need to make this file writable before you can save your changes. See <a href="https://codex.wordpress.org/Changing_File_Permissions">the Codex</a> for more information.'); ?></em></p>
<?php endif; ?>
</form>
<br class="clear" />
</div>
<script type="text/javascript">
jQuery(document).ready(function($){
	$('#template').submit(function(){ $('#scrollto').val( $('#newcontent').scrollTop() ); });
	$('#newcontent').scrollTop( $('#scrollto').val() );
});
</script>
<?php
}

include(ABSPATH . "wp-admin/admin-footer.php");
