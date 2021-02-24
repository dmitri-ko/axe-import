<?php
/**
 * Post type Admin API file.
 *
 * @package Axe Import/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin API class.
 */
class Axe_Import_Admin_API {

	/**
	 * Constructor function
	 */
	public function __construct() {

		add_action( 'wp_ajax_axe_get_posts', array( $this, 'ajax_get_posts' ), 10, 1 );
		add_filter( 'wpallimport_xml_row', array( $this, 'convert_data_before_import' ), 10, 1 );
		add_action('pmxi_saved_post', array( $this, 'convert_repeater_data' ), 10, 3);
		add_action( 'pmxi_before_xml_import', array( $this, 'pmxi_before_xml_import' ), 10, 1 );
	}

	/**
	 * Get posts for ajax query.
	 *
	 * @return void
	 */
	public function ajax_get_posts( ) {
        global $wpdb;
        $return = [];
		$return[ 'items' ] = [];

		$search =  isset( $_REQUEST['q'] ) ? $_REQUEST['q'] : '';
		$type   =  isset( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] : 'post';
		$keywords = preg_split("/_/", $type);
		if ( is_array($keywords) && 2 == count($keywords)  )
        	$id_key = $keywords[1].'_id';
		$args = array( 'post_type' => $type, 'posts_per_page' => -1, 'post_status' => 'publish' );
        $is_search_posts = true;

		if ( $search ){
			$search_query = "SELECT ID FROM {$wpdb->prefix}posts
							WHERE post_type = '{$type}' 
							AND post_title LIKE %s";
			$like = '%'.$search.'%';
			$results = $wpdb->get_results($wpdb->prepare($search_query, $like), ARRAY_N);
			foreach($results as $key => $array){
				$quote_ids[] = $array[0];
			}	
			if ( isset($quote_ids) ){
                $args['post__in'] = $quote_ids;
			} else{
				$is_search_posts = false;
			}	
		}
        if ( $is_search_posts ){
			$posts = get_posts( $args );
			if( $posts ) {
				foreach ( $posts as $post ) { 
					$item          = [];
					$item['id']    = $post->ID;
					$item['title'] = get_the_title( $post );
					$item['name']  = $post->post_name;
					if( 'axe_image' == $type ){
						$item['thumb'] = get_the_post_thumbnail_url( $post, 'thumbnail' );
					}
					$meta = get_post_meta( $post->ID );
					$item['url'] = get_edit_post_link(  $post->ID );
					if ( $meta ){
						foreach (array('artist_image','artwork_image','infos_image' ) as $img) {
							 if( array_key_exists( $img, $meta)  && is_array( $meta[$img] )  && isset( $meta[$img][0] ) ) {
                                 $img_id        = $this->get_post_id_by_meta_key_and_value('image_id', $meta[$img][0]);
								 $item['thumb'] = get_the_post_thumbnail_url( $img_id, 'thumbnail' );
							 }							 	
						}
						if( array_key_exists( $id_key, $meta)  )
							$item['id'] = $meta[$id_key][0];
					}
					$return[ 'items' ][] = $item;
				}
			}
			wp_reset_postdata();	
		}

		echo json_encode($return);
		wp_die(); // this is required to terminate immediately and return a proper response
	}
	
	/**
	 * Get post id from meta key and value
	 * @param string $key
	 * @param mixed $value
	 * @return int|bool
	 * @author David M&aring;rtensson <david.martensson@gmail.com>
	 */
	public function get_post_id_by_meta_key_and_value($key, $value) {
		global $wpdb;
		$meta = $wpdb->get_results("SELECT * FROM `".$wpdb->postmeta."` WHERE meta_key='".$wpdb->escape($key)."' AND meta_value='".$wpdb->escape($value)."'");
		if (is_array($meta) && !empty($meta) && isset($meta[0])) {
			$meta = $meta[0];
		}		
		if (is_object($meta)) {
			return $meta->post_id;
		}
		else {
			return false;
		}
	}	

	/**
	 * WP AllImport Data modification before the import
	 * 
	 * @param object $node
	 * @return object
	 */	
	public function convert_data_before_import( $node ){
		//foreach ($node as $elem){
			$img 	  = $node->xpath( 'data[1]' );
			$filename = $node->xpath( '_id[1]' );
			if( $img ){
				$base64 = $img[0]->__toString();
				if ( !empty( $base64 ) ) {
					if ( $filename  ) {
						$file = $filename[0]->__toString();
						if ( !empty( $file ) ){
							$image_file = $this->base64_to_image( $base64, $file );
							if ( $image_file )
								$node->addChild('img_file', $image_file);
						}
					}                  
				}
			}
		//}
        return $node;
	}

	public function base64_to_image( $b64, $filename ){
		// Obtain the original content (usually binary data)
		$bin = base64_decode($b64);

		// Gather information about the image using the GD library
		$size = getImageSizeFromString($bin);

		// Check the MIME type to be sure that the binary data is an image
		if (empty($size['mime']) || strpos($size['mime'], 'image/') !== 0) {

            return;
		}

		// Mime types are represented as image/gif, image/png, image/jpeg, and so on
		// Therefore, to extract the image extension, we subtract everything after the “image/” prefix
		$ext = substr($size['mime'], 6);

		// Make sure that you save only the desired file extensions
		if (!in_array($ext, ['png', 'gif', 'jpeg'])) {

            return;
		}

		// Specify the location where you want to save the image
		$img_file = $filename.'.'.$ext;
		$upload_dir = wp_get_upload_dir();
        $file_path = $upload_dir['basedir'].'/wpallimport/files/'.$img_file;
		$bytes = file_put_contents($file_path , $bin);
        if ( false !== $bytes )
			return $img_file;


        return;
	}

	public function convert_repeater_data( $id, $xml_node, $is_update  ){
        $post_type = get_post_type($id);
        switch ($post_type) {
			case 'axe_artist':
                $elems        = $xml_node->xpath('artworksID/item_3');
                $custom_field = 'artist_re_';
                $custom_value = 'artist_artwork';
				break;
			case 'axe_group':
				$elems        = $xml_node->xpath('artworksID/item_3');
				$custom_field = 'group_re_';
				$custom_value = 'group_artwork';
				break;
			case 'axe_artwork':
				$elems        = $xml_node->xpath('infosID/item_3');
				$custom_field = 'artwork_re_';
				$custom_value = 'artwork_infos';
				break;
			case 'axe_exhibition':
				$elems        = $xml_node->xpath('groupsID/item_2');
				$custom_field = 'exhibition_re_';
				$custom_value = 'exhibition_group';
				break;																
			default:
				return;
		}
		foreach ($elems as $key => $elem) {
			if ( trim($elem) )
				$meta[] = [ $custom_value => trim($elem) ];	
		}
		if (isset($meta) && is_array($meta)){
			update_post_meta($id, $custom_field,  $meta  );
		}	
	}

	public function pmxi_before_xml_import( $importID ) {

		// Retrieve import object.
		$import = new PMXI_Import_Record();
		$import->getById( $importID );
	
		// Ensure import object is valid.
		if ( ! $import->isEmpty() ) {
	
			// Retrieve history file object.
			$history_file = new PMXI_File_Record();
			$history_file->getBy( 'import_id', $importID );
	
			// Ensure history file object is valid.
			if ( ! $history_file->isEmpty() ) {
	
				// Retrieve import file path.
				$file_to_import = wp_all_import_get_absolute_path( $history_file->path );
	
				// Load import file as SimpleXml.
                $file = simplexml_load_file($file_to_import, 'Axe_Import_SimpleXMLElement' );
	
				// Check if Group is a child of Exhibition.
				$query = $file->xpath( "//exhibition/groupsID[1]/item_2[1]/group[1]" );
				if ( ! empty( $query ) ) {
	
					// If it is, do nothing.
					return;
	
				}
	
				// Get Group value.
			//	$iquery = $file->xpath( "//Apartment/Status[1]" );
	
				// Ensure value isn't empty.
			//	if ( ! empty( $iquery ) ) {
	
					// Value of status as string.
			//		$status = $iquery[0]->__toString();
	
					// Target path.
					$new_query = $file->xpath( "./exhibition/groupsID/item_2" );
	
					// Ensure path is valid.
					if ( ! empty( $new_query ) ) {
	
						// Process each Procurement element.
						foreach ( $new_query as $record ) {						
							// Ensure this element doesn't have Status.
							if ( ! isset( $record->group ) ) {
								$iquery = $file->xpath( '//groups/item_1[_id[1] = "'.$record[0].'"]' );
								if ( ! empty( $iquery ) ) {
								
									$record->appendXML(  $iquery[0], 'group' );
								}
							}
						}
	
						// Save updated file.
						$updated_file = $file->asXML( $file_to_import );
	
					}
				}
			//}
		}
	}
	
}
