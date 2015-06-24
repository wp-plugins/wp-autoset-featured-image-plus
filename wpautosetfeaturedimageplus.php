<?php
/*
Plugin Name: WP Autoset Featured Image Plus
Plugin URI: http://www.luciaintelisano.it/wp-autoset-featured-image-plus
Description: A plugin to set external/remote images from text editor as post thumbnail/featured image.
Version: 2.0
Author: Lucia Intelisano
Author URI: http://www.luciaintelisano.it
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
?>
<?php

 
 
		 
	 
 
 wpasfip_init();
   

	 

    
    	function wpasfip_init() {
	  
			// if (is_admin()) {
				add_filter( 'post_thumbnail_html', 'wpasfip_post_thumbnail_html', 20, 5 );
				add_action('admin_menu', 'wpasfip_plugin_setup_menu');
	 			add_action( 'admin_init',  'wpasfip_register_mysettings' ); 
				add_action('pre_post_update',  'wpasfip_saveurl');
				 
				add_action('publish_post',  'wpasfip_saveurl');
				add_action('edit_page_form',   'wpasfip_saveurl');  	
				add_action('draft_to_publish',   'wpasfip_saveurl'  );
				add_action('new_to_publish',   'wpasfip_saveurl' );
				add_action('pending_to_publish',    'wpasfip_saveurl');
				add_action('future_to_publish',    'wpasfip_saveurl');
				add_action('save_post',   'wpasfip_saveurl');
				add_filter( 'admin_post_thumbnail_html',  'wpasfip_thumbnail_url_field');
			 
		 		//}
			 
		}
 
 

function wpasfip_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr  ) {
  global $post;
 
	$wpasfip_thumb=get_option('wpasfip_default_first_url');
	$cats = get_option('wpasfip_view_on_cat');
	 
	if ($wpasfip_thumb==true && $cats!="") {
 
		$attachok = 0;
		 
    	$catPost = get_the_category($post->ID);
		$arrCat = split(",",$cats);
		 
		 if($catPost){
			foreach($catPost as $category) {
		 
					foreach($arrCat as $cat) {
						if (strtolower($category->name)==$cat) {
							$attachok=1;
					 	}
					}
			}
		}	
		if ($attachok==1) {
		 
		 
			preg_match_all( '/<img .*?(?=src)src=\"([^\"]+)\"/si', strtolower($post->post_content), $allpics );
			 
			if (is_array($allpics[1]) && count($allpics[1])>0) {
				 
				$pic = $allpics[1][0];
				 
				$exturl =  $pic;
				$html = '<img src="' . $exturl . '" alt="" />';
		 
			}
			
		}
		 
	}
	return $html;
}	 
	 
	 	function wpasfip_thumbnail_url_field( $html ) {
		  global $post;
 		
 			
		   if (!has_post_thumbnail($post->ID) ) {
			$post_details=get_post($post->ID);
		
			preg_match_all( '/<img .*?(?=src)src=\"([^\"]+)\"/si', $post_details->post_content, $allpics );
			if (is_array($allpics[1]) && count($allpics[1])>0) {
				$pic = $allpics[1][0];
				echo "Found in editor: ". $pic;
				$exturl =  $pic;
			}
		  
		  $nonce = wp_create_nonce( 'thumbnail_ext_url_' . $post->ID . get_current_blog_id() );
		  $html .= '<input type="hidden" name="thumbnail_ext_url_nonce" value="' 
			. esc_attr( $nonce ) . '">';
		  $html .= '<div><p>' . __('Or', 'txtdomain') . '</p>';
		  $html .= '<p>' . __( 'Enter the url for external image', 'txtdomain' ) . '</p>';
		  $html .= '<p><input type="url" name="thumbnail_ext_url" id="thumbnail_ext_url" value="' . $exturl . '">&nbsp;<input type="button" value="delete url" name="delurlBtn" id="delurlBtn" onclick="document.getElementById(\'thumbnail_ext_url\').value=\'\';document.getElementById(\'imgUrl\').src=\'\'"/></p>';
		  if ( $exturl!=""  ) {
			$html .= '<p><img id="imgUrl" style="max-width:150px;height:auto;" src="' 
			  . esc_url($exturl) . '"></p>';
			$html .= '<p>' . __( 'Leave url blank to remove.', 'txtdomain' ) . '</p>';
		  }
		  $html .= '</div>';
		 }  
	 return $html;
		  
	}

  


 
		 function wpasfip_saveurl( $post_id ) {
			
			if (is_object($post_id)) {
				$post_id = $post_id->ID;
			 
			}
			 
			if ( ! isset( $_POST['thumbnail_ext_url_nonce'] ) ) {
				return $post_id;
			}
			$nonce = $_POST['thumbnail_ext_url_nonce'];
		 
	  	 
	  	
			if ( ! wp_verify_nonce( $nonce, 'thumbnail_ext_url_' . $post_id . get_current_blog_id()  ) ) {
				return $post_id;
			}
		 
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return $post_id;
			}
	   

		   if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
		   }
 
			$extImgUrl = sanitize_text_field( $_POST['thumbnail_ext_url'] );
		 
	 		if ($extImgUrl!="") {
				$thumbId = wpasfiCreateThumb($extImgUrl, $post_id); 
	  
				if ($thumbId) {
					update_post_meta( $post_id, '_thumbnail_id', $thumbId );
				}	
			}
			return $post_id;	 
		}

	 
	
		function wpasfiCreateThumb ($imageUrl, $post_id) {
	 
 
			$filename = substr($imageUrl, (strrpos($imageUrl, '/'))+1);
		 
			if (!(($uploads = wp_upload_dir(current_time('mysql')) ) && false === $uploads['error'])) {
				return null;
			}

 
			$filename = wp_unique_filename( $uploads['path'], $filename );

	 
			$new_file = $uploads['path'] . "/$filename";

			if (!ini_get('allow_url_fopen')) {
				$file_data = curl_get_file_contents($imageUrl);
			} else {
				$file_data = @file_get_contents($imageUrl);
			}

			if (!$file_data) {
				return null;
			}

			file_put_contents($new_file, $file_data);

 
			$stat = stat( dirname( $new_file ));
			$perms = $stat['mode'] & 0000666;
			@chmod( $new_file, $perms );
			if (strpos($new_file,".php")>0) {
				$new_file2 = str_replace(".php",".jpg",$new_file);
				rename($new_file,$new_file2);
				$new_file = $new_file2;
				$filename = str_replace(".php",".jpg",$filename);
			}
			if (strpos($new_file,".cgi")>0) {
				$new_file2 = str_replace(".cgi",".jpg",$new_file);
			 
				rename($new_file,$new_file2);
				$new_file = $new_file2;
				$filename = str_replace(".cgi",".jpg",$filename);
			}
 
			$wp_filetype = wp_check_filetype( $filename, $mimes );

			extract( $wp_filetype );
			 
 
			if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) ) {
				return null;
			}

	 
			$url = $uploads['url'] . "/$filename";

 
			$attachment = array(
				'post_mime_type' => $type,
				'guid' => $url,
				'post_parent' => null,
				'post_title' => $imageTitle,
				'post_content' => '',
			);

			$thumb_id = wp_insert_attachment($attachment, $file, $post_id);
			if ( !is_wp_error($thumb_id) ) {
				require_once(ABSPATH . '/wp-admin/includes/image.php');

		 
				wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $new_file ) );
				update_attached_file( $thumb_id, $new_file );

				return $thumb_id;
			}
			return null;
		}
	 

			/**
			 * Function to fetch the contents of URL using curl in absense of allow_url_fopen.
			 *
			 * Copied from user comment on php.net (http://in.php.net/manual/en/function.file-get-contents.php#82255)
			 */
			function curl_get_file_contents($URL) {
				$c = curl_init();
				curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($c, CURLOPT_URL, $URL);
				$contents = curl_exec($c);
				curl_close($c);

				if ($contents) {
					return $contents;
				}

				return FALSE;
			}
 
 
 
 function wpasfip_register_mysettings() {
	register_setting( 'wpasfip-settings-group', 'wpasfip_default_first_url' );
 	register_setting( 'wpasfip-settings-group', 'wpasfip_view_on_cat' );
}


function wpasfip_plugin_setup_menu(){
       add_options_page('WP Auto Set Featured Image Plus', 'WP Auto Set Featured Image Plus', 'administrator', __FILE__, 'wpasfip_settings_page',plugins_url('/images/icon.png', __FILE__));
	}
	/**
 * Function for view settings page 
 */
function wpasfip_settings_page() {
?>
<div class="wrap">
<h2>WP Auto Set Featured Image Plus</h2>

<form method="post" action="options.php">
    <?php 
    	settings_fields( 'wpasfip-settings-group' );  
    	do_settings_sections( 'wpasfip-settings-group' ); 
    ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Set first url image on post (if exists) as featured image</th>
        <td><input type="checkbox" name="wpasfip_default_first_url" <?php checked( '1', get_option('wpasfip_default_first_url')) ; ?> value="1" /></td>
        </tr>
         <tr valign="top">
        <th scope="row">View on post of categories</th>
        <td><input type="text" name="wpasfip_view_on_cat" value="<?php echo esc_attr( get_option('wpasfip_view_on_cat') ); ?>" /> (es. cat1,cat2,...)</td>
        </tr>
       
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php 
	
}

?>