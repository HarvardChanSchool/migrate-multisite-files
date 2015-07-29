<?php
/*
Plugin Name: Migrate Multisite Files
Plugin URI: http://www.hsph.harvard.edu/
Description: A plugin to migrate Multisite files from blogs.dir to the uploads directory. 
Version: 0.1
Network: true
Author: dmarshall/HSPH WebTeam
Author URI: http://www.hsph.harvard.edu/
*/

// if we are not a multisite then kill the plugin
if ( ! is_multisite() ) {
	wp_die( __( 'Multisite support is not enabled.' ) );
}

// this is not for ordpress MU networks
if ( ! defined( 'MULTISITE' ) ) {
	wp_die( __( 'This plugin is not for WordPress MU networks.' ) );
}

// well er have gotten here and we are in an admin so allow for activation
if ( is_admin() ) {
	register_activation_hook( __FILE__, 'migrate_multisite_files_activation' );
}

// turn off the MS files rewriting filter
// this will be redundant post activation but it is here
//remove_filter( 'default_site_option_ms_files_rewriting', '__return_true' );

/**
 * Run on activation. Check if we are or are not able to even use this plugin
 *
 * @since Migrate Multisite Files 1.0
 */
function migrate_multisite_files_activation() {
	// we need to do a version check
	global $wp_version;
	
	// version compare
	if (version_compare($wp_version,"3.5","<")) {
		exit ( 'This requires WordPress 3.5 or newer. <a href="http://codex.wordpress.org/Upgrading_WordPress">Please update</a>' );
	}	
}

/**
 * add a page to each site's admin menu	
 *
 * @since Migrate Multisite Files 1.0
 */
		
function migrate_multisite_files_admin_setup() {
	add_submenu_page( 'settings.php', 'Migrate Multisite', 'Migrate Multisite', 'manage_network', 'migrate_multisite_files_page', 'migrate_multisite_files_page');
}

add_action( 'network_admin_menu', 'migrate_multisite_files_admin_setup' );

/**
 * Callback for the menu page from above
 * If we are to process an action do it
 * Otherwise display the appropriate activate otr deactivate page. 
 *
 * @since Migrate Multisite Files 1.0
 */
function migrate_multisite_files_page() {  		
	echo '<div class="wrap">';
	echo '<h2>' . __( 'Migrate Multisite Files' ) . '</h2>';
	
	if ( !empty( $_POST[ 'action' ] ) ) {
		// check and then go
		check_admin_referer( 'migrate_multisite_files' );
		
		$update_content = ( isset( $_POST['migrate_multisite_files_update_content'] ) ) ? true : false;
		$migrate_files = ( isset( $_POST['migrate_multisite_files_migrate_files'] ) ) ? true : false;
		
		if ( $_POST[ 'action' ] == 'update' ) {
			migrate_multisite_files_update( 'update', $update_content, $migrate_files );
		}
		
		if ( $_POST[ 'action' ] == 'undo' ) {
			migrate_multisite_files_update( 'undo', $update_content, false );
		}
	} else {
		// check our ms files is enabled. If so, then show the ability to undo it
		// otherwise allow for us to change it
		if ( get_site_option( 'ms_files_rewriting' ) == 0) {
			show_migrate_multisite_files_undo_info();
		} else {
			show_migrate_multisite_files_update_info();
		}
	}
	
	echo '</div>';
}

/**
 * Display the text for the reactivation of ms files (undo)
 *
 * @since Migrate Multisite Files 1.0
 */
function show_migrate_multisite_files_undo_info() {
	?>
	<p>Your site has the ms-files.php filter turned off.</p>
	<p>Please verify all the links on the site are intact and working. You can use the <strong>Broken Link Checker</strong> to help identify links that need to be changed</p>
	<h2>Need to undo?</h2>
	<p>If you would like to re-enable the ms-files.php filter, click the undo button below. This will set the <code>ms_files_rewriting</code> flag to 1 and reset the upload paths for each site. No files will be copied.</p>
	<?php
	echo "<form method='POST'><input type='hidden' name='action' value='undo' />";
	wp_nonce_field( 'migrate_multisite_files' );
	echo "<br/><input name='migrate_multisite_files_update_content' type='checkbox' id='migrate_multisite_files_update_content' value='true' checked='checked' /> <label for='migrate_multisite_files_update_content'>Change URLs to use the ms-files.php filter.<br/><em>Uncheck if you intend to create symlinks or use redirects</em></label><br/>";
	echo "<p><input type='submit' class='button-secondary' value='" .__( 'Undo' ). "' /></p>";
	echo "</form>";
}

/**
 * Display text for the deactivation of ms files 
 *
 * @since Migrate Multisite Files 1.0
 */
function show_migrate_multisite_files_update_info() {
	?>
	<p>WordPress 3.5 now <a href='http://core.trac.wordpress.org/ticket/19235' target="_blank">turns ms-files.php off by default</a> for new multisite installations, but it is also possible to turn it off manually.</p>
	<h3>Why would you want to do so?</h3>
	<p>If you have an existing multisite installation why would you want to change how uploaded files are processed?</p>
	<p>For pretty much the same reasons it was turned off for new installations! Single site WordPress uses the <em>uploads</em> directory to store uploaded images. Prior to version 3.5, sub-sites in a WordPress multisite installation required a special directory called <em>blogs.dir</em> to store uploaded files, and accessed these images through a file handler called <code>ms-files.php</code>. However a web server can serve up static files such as images more efficiently by itself than by loading them through PHP. Avoiding the ms-files.php filter is therefore a good thing.</p>
	<h3>What can this plugin do?</h3>
	<p>You can use this plugin to migrate an existing multisite installation to use the uploads directory instead of blogs.dir. It works by flipping the new switch that controls how multisite files are processed and updates your network. In more detail, it sets the <code>ms_files_rewriting</code> flag to 0 in the <code>wp_sitemeta</code> table, resets the upload paths for each sub-site, and finally copies all files from <code>blogs.dir/[id]/files/</code> to <cpde>uploads/sites/[id]/</code> (where [id] is the site identifier).
	</p>
	<h3>Options</h3>
	<?php
	echo "<form method='POST'><input type='hidden' name='action' value='update' />";
	wp_nonce_field( 'migrate_multisite_files' );
	echo "<br/><input name='migrate_multisite_files_update_content' type='checkbox' id='migrate_multisite_files_update_content' value='true' checked='checked' /> <label for='migrate_multisite_files_update_content'>Update existing URLs that use the ms-files.php filter.<br/><em>Uncheck if you intend to create symlinks or use redirects</em></label><br/>";
	echo "<br/><input name='migrate_multisite_files_migrate_files' type='checkbox' id='migrate_multisite_files_migrate_files' value='true' /> <label for='migrate_multisite_files_migrate_files'>Migrate the files over from the directory as well. This will copy all of your site uploads from <code>" . trailingslashit( path_join( ABSPATH, 'wp-content/blogs.dir/' ) ) . "</code> to <code>" . trailingslashit( path_join( ABSPATH, 'wp-content/uploads/sites/' ) ) . "</code>. This is purely a copy. No files will be removed.<br/><em>Do not use on large networks</em></label><br/>";
	echo "<p><input type='submit' class='button button-primary button-large' value='" .__( ' Migrate Network ' ). "' /></p>";
	echo "</form>";
}

/**
 * deactivate MS files
 *
 * @since Migrate Multisite Files 1.0
 */
function migrate_multisite_files_update( $direction = 'update', $update_content = true, $migrate_files = false ) {
	// set these as globals - we need them later
	global $files_copied, $urls_changed, $wpdb;
	
	$urls_changed = 0;
	$files_copied = 0;
	$migrate_sites_current = 0;
		
	// copy files for each blog
	echo "<h4>Updating</h4>";
	echo "<p><em>Note that this step can take several minutes depending on the number of sites to be updated.</em></p>";

	// get all our blogs
	$blogs = $wpdb->get_results( 'SELECT * FROM `' . $wpdb->prefix . 'blogs`', ARRAY_A );
		
	// for each blog in our sstem look for things to upgrade		
	foreach ( $blogs as $blog ) {		
		// copy files from blog directory to uploads
		// only do this if the option is checked
		if ( $direction == 'undo' ) {
			// update URL links in posts and pages
			$content_source = '/wp-content/uploads/sites/' . $blog['blog_id'] . '/';
			$content_dest = $blog['path'] . 'files/';
		} else {
			// source and destination
			$source = trailingslashit( path_join( ABSPATH, 'wp-content/blogs.dir/' . $blog['blog_id'] . '/files/' ) );
			$site_upload_dir = trailingslashit( path_join( ABSPATH, 'wp-content/uploads/sites/' . $blog['blog_id'] ) );
			// update URL links in posts and pages
			$content_source = $blog['path'] . 'files/';
			$content_dest = '/wp-content/uploads/sites/' . $blog['blog_id'] . '/';
		}

		if ( true == $migrate_files ) {
			// DO NOT run this option on large sites
			// migrate the files
			migrate_multisite_files_copy( $source , $site_upload_dir );
			
		}

		// update the site contents
		if ( true == $update_content ) {
			// run the converter				
			migrate_multisite_files_update_url( $content_source, $content_dest, $blog['blog_id'] );
			
		}
		
		// get all the subcarousel templates for a blog
		$uploadurl = $wpdb->query( 'UPDATE `' . $wpdb->prefix . $blog['blog_id'] . '_options` SET `option_value`="" WHERE `option_name`="upload_url_path"', ARRAY_A );
		$upload = $wpdb->query( 'UPDATE `' . $wpdb->prefix . $blog['blog_id'] . '_options` SET `option_value`="" WHERE `option_name`="upload_path"', ARRAY_A );

		// update progress
		$migrate_sites_current++;
		show_migrate_multisite_files_progress($migrate_sites_current, count($blogs));
	}
	
	//remove the ms files filter so we can get this all working. 
	remove_filter( 'default_site_option_ms_files_rewriting', '__return_true' );
	
	if ( $direction == 'undo' ) {
		// finally upgade the network site option
		if ( false === get_site_option( 'ms_files_rewriting', false, false ) ) {
			add_site_option( 'ms_files_rewriting', 1 );
		} else {
			update_site_option( 'ms_files_rewriting', 1 );
		}
	} else {
		// finally upgade the network site option
		if ( false === get_site_option( 'ms_files_rewriting', false, false ) ) {
			add_site_option( 'ms_files_rewriting', 0 );
		} else {
			update_site_option( 'ms_files_rewriting', 0 );
		}
	}

	echo "<br/>";
	
	if ( true == $migrate_files ) {
		echo "<br/>Copied $files_copied files";
	}

	if (true == $update_content) {
		echo "<br/>Changed $urls_changed URLs";
	}
	
	echo "<br/>";
	echo "<br/>";
	echo "<p>All done.";
}

/**
 * Display a Span Progress bar
 *
 * @since Migrate Multisite Files 1.0
 */
function show_migrate_multisite_files_progress( $current, $total ) {
    echo "<span style='position: absolute;z-index:$current;background:#F1F1F1;'>Parsing Blog " . $current . ' - ' . round($current / $total * 100) . "% Complete</span>";
    echo(str_repeat(' ', 256));
    if (@ob_get_contents()) {
        @ob_end_flush();
    }
    flush();
}

/**
 * Replace the post content, excerpt annd meta with the old and new URL
 *
 * @since Migrate Multisite Files 1.0
 */
function migrate_multisite_files_update_url( $oldurl, $newurl, $blog_id ){	
	global $wpdb, $urls_changed;

	$oldurl = esc_attr($oldurl);
	$newurl = esc_attr($newurl);
	
	$queries = array(
		'content' => 		'UPDATE `' . $wpdb->prefix . $blog_id . '_posts` SET post_content = replace(post_content, %s, %s)',
		'excerpts' =>		'UPDATE `' . $wpdb->prefix . $blog_id . '_posts` SET post_excerpt = replace(post_excerpt, %s, %s)',
		'attachments' =>	'UPDATE `' . $wpdb->prefix . $blog_id . '_posts` SET guid = replace(guid, %s, %s) WHERE post_type = "attachment"',
		'postmeta' =>		'UPDATE `' . $wpdb->prefix . $blog_id . '_postmeta` SET meta_value = replace(meta_value, %s, %s)',
		'options' =>		'UPDATE `' . $wpdb->prefix . $blog_id . '_postmeta` SET option_value = replace(option_value, %s, %s)'
	);
	
	foreach( $queries as $option => $query ){
		switch( $option ){
			case 'postmeta':
				$postmeta = $wpdb->get_results( 'SELECT * FROM `' . $wpdb->prefix . $blog_id . '_postmeta` WHERE meta_value != ""', ARRAY_A );
			
				foreach( $postmeta as $key => $item ) {
					// if the string is empty then dont bother and continue
					if( trim( $item['meta_value'] ) == '' ) {
						continue;
					}
					
					// we have a possibel suspect lets check if it is serialized
					if ( is_serialized( $item['meta_value'] ) ) { 
						$edited = migrate_multisite_files_unserialize_replace( $oldurl, $newurl, $item['meta_value'] );
					} else {
						// we are not serialized so we can replace directly
						$edited = str_ireplace( $oldurl, $newurl, $item['meta_value'], $count );
						$urls_changed += $count;
					}
			
					if( $edited != $item['meta_value'] ){
						$fix = $wpdb->query( $wpdb->prepare( 'UPDATE `' . $wpdb->prefix . $blog_id . '_postmeta` SET meta_value = "%s" WHERE meta_id = %s', $edited, $item['meta_id'] ) );
					}
				}
			break;
			case 'options':
				$postmeta = $wpdb->get_results( 'SELECT * FROM `' . $wpdb->prefix . $blog_id . '_options` WHERE option_value != ""', ARRAY_A );
			
				foreach( $postmeta as $key => $item ) {
					// if the string is empty then dont bother and continue
					if( trim( $item['option_value'] ) == '' ) {
						continue;
					}
					
					// we have a possibel suspect lets check if it is serialized
					if ( is_serialized( $item['option_value'] ) ) { 
						$edited = migrate_multisite_files_unserialize_replace( $oldurl, $newurl, $item['option_value'] );
					} else {
						// we are not serialized so we can replace directly
						$edited = str_ireplace( $oldurl, $newurl, $item['option_value'], $count );
						$urls_changed += $count;
					}
			
					if( $edited != $item['option_value'] ){
						$fix = $wpdb->query( $wpdb->prepare( 'UPDATE `' . $wpdb->prefix . $blog_id . '_options` SET option_value = "%s" WHERE option_id = %s', $edited, $item['option_id'] ) );
					}
				}
			break;
			default:
				$result = $wpdb->query( $wpdb->prepare( $query, $oldurl, $newurl) );
			
				if ( FALSE !== $result && 0 < $result ) {
					$urls_changed += $result;
				}
			break;
		}
	}
	//return $results;			
}

/**
 * Serialized data cannot have a direct swap. We need to handle the URLs ourside of serialization
 *
 * @since Migrate Multisite Files 1.0
 */

function migrate_multisite_files_unserialize_replace( $from = '', $to = '', $data = '', $serialised = false ) {
	global $urls_changed;
	
	try {
		if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
			$data = migrate_multisite_files_unserialize_replace( $from, $to, $unserialized, true );
		}
		elseif ( is_array( $data ) ) {
			$_tmp = array( );
			foreach ( $data as $key => $value ) {
				$_tmp[ $key ] = migrate_multisite_files_unserialize_replace( $from, $to, $value, false );
			}
			$data = $_tmp;
			unset( $_tmp );
		}
		else {
			if ( is_string( $data ) ) {
				$data = str_replace( $from, $to, $data, $count );
				$urls_changed += $count;
			}
		}
		if ( $serialised )
			return serialize( $data );
	} catch( Exception $error ) {
	}
	return $data;
}

/**
 * copy all of the fles from the old to the new directory
 *
 * @since Migrate Multisite Files 1.0
 */
function migrate_multisite_files_copy($source, $dest) 
{ 
	global $files_copied;
	
    if (is_file($source)) { 
    	
    	$dest_dir;
    	if (is_dir($dest)) {
	    	$dest_dir = $dest;
    	}
    	else {
	    	$dest_dir = dirname($dest);
    	}

    	// ensure destination exists
        if (!file_exists($dest_dir)) { 
            wp_mkdir_p($dest_dir); 
        } 
        
        //echo "copy $source -> $dest<br />";
        copy($source, $dest);
        $files_copied++;
                 
    } elseif(is_dir($source)) { 
    
    	// if source is a directory, create corresponding 
    	// directory structure at destination location
        if ($dest[strlen($dest)-1]=='/') { 
            if ('/' != $source[strlen($source)-1]) { 
                $dest=path_join($dest , basename($source)); 
            } 
        }
            
        $dirHandle=opendir($source); 
        while($file=readdir($dirHandle)) 
        { 
            if($file!="." && $file!="..") 
            { 
            	// recursively copy each file
                $new_dest = path_join($dest , $file);
                $new_source = path_join($source , $file);
                
                //echo "$new_source -> $new_dest<br />"; 
                migrate_multisite_files_copy($new_source, $new_dest); 
            } 
        } 
        closedir($dirHandle); 
    } 
}