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
	 * The single instance of RapidAddon.
	 *
	 * @var     object
	 * @access  protected
	 * @since   1.0.0
	 */
	protected $add_on;

	/**
	 * Constructor function
	 */
	public function __construct() {

		add_action( 'wp_ajax_axe_get_posts', array( $this, 'ajax_get_posts' ), 10, 1 );
		add_action('pmxi_saved_post', array( $this, 'convert_repeater_data' ), 10, 3);
		add_action( 'pmxi_before_xml_import', array( $this, 'pmxi_before_xml_import' ), 10, 1 );
		add_filter( 'update_post_metadata', array( $this, 'delete_empty_meta'), 10, 5 );
		add_filter( 'wp_all_import_is_post_to_create', array( $this, 'skip_unknown_posts' ), 10, 3 );
		add_filter( 'wpallimport_xml_row', array( $this, 'convert_data_before_import' ), 10, 1 );
		add_filter('pmxi_custom_field', array( $this, 'keep_existing_if_empty'), 10, 6);

		// Define the add-on
		$this->add_on = new RapidAddon( 'Axe Import Add-On', 'axe-import_addon' );
		
		// Define add-on fields
		$this->add_on->add_field('_id', __('ID', 'axe-import'), 'text'); 
		
		$this->add_on->add_options(
			null,
			__('General Settings','axe-import'), 
			array(
				$this->add_on->add_field('_description', __('Description', 'axe-import'), 'text'),
				$this->add_on->add_field('_imageID', __('Image ID', 'axe-import'), 'text'),
			)
		);


		$this->add_on->add_options(
				null,
				__('Image Settings','axe-import'), 
				array(
					$this->add_on->add_field('_width', __('Width', 'axe-import'), 'text'),
					$this->add_on->add_field('_height', __('Height', 'axe-import'), 'text'),
					$this->add_on->add_field('_error', __('Error', 'axe-import'), 'text'),
				)
		);

		$this->add_on->add_options(
			null,
			__('Artist Settings','axe-import'), 
			array(
				$this->add_on->add_field('_firstname', __('First Name', 'axe-import'), 'text'),
				$this->add_on->add_field('_name', __('Name', 'axe-import'), 'text'),
				$this->add_on->add_field('_birth', __('Birth', 'axe-import'), 'text'),
				$this->add_on->add_field('_death', __('Death', 'axe-import'), 'text'),
			)
		);


		$this->add_on->add_options(
			null,
			__('Artwork Settings', 'axe-import'), 
			array(
				$this->add_on->add_field('_year', __('Year of establishment', 'axe-import'), 'text'),
				$this->add_on->add_field('_artistID', __('Artist ID', 'axe-import'), 'text'),
				$this->add_on->add_field('_owner', __('Owner', 'axe-import'), 'text'),
			)
		);

		$this->add_on->add_options(
			null,
			__('Exhibition Settings','axe-import'), 
			array(
				$this->add_on->add_field('_start', __('Exhibition Starts', 'axe-import'), 'text'),
				$this->add_on->add_field('_end', __('Exhibition Ends', 'axe-import'), 'text'),
			)
		);

		$this->add_on->set_import_function([ $this, 'import' ]);
		add_action( 'admin_init', [ $this, 'init' ] );
	}

	protected function logger($m) {
		echo "<div class='progress-msg'>[". date("H:i:s") ."] $m</div>\n";flush();
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
						if( array_key_exists( 'imageID', $meta)  && is_array( $meta['imageID'] )  && isset( $meta['imageID'][0] ) ) {
							$img_id        = $this->get_post_id_by_meta_key_and_value('_id', $meta['imageID'][0]);
							$item['thumb'] = get_the_post_thumbnail_url( $img_id, 'thumbnail' );
						}	
						if( array_key_exists( '_id', $meta)  )
							$item['id'] = $meta['_id'][0];
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
		$img 	  = $node->xpath( 'data[1]' );
		$filename = $node->xpath( '_id[1]' );
		if( $img ){
			$base64 = $img[0]->__toString();
			if ( !empty( $base64 ) ) {
				if ( $filename  ) {
					$file = $filename[0]->__toString();
					if ( !empty( $file ) ){
						$image_file = $this->base64_to_image( $base64, $file );
						if ( 'OK' == $image_file['status'] )
							$node->addChild('img_file', $image_file['filename']);
						else {
							$node->addChild('img_file_error', $image_file['message']);
						}	
					}
				}                  
			}
		}
	return $node;
	}

	public function base64_to_image( $b64, $filename ){
		// Obtain the original content (usually binary data)
		$bin = base64_decode($b64);

		// Gather information about the image using the GD library
		$size = getImageSizeFromString($bin);

		// Check the MIME type to be sure that the binary data is an image
		if (empty($size['mime']) || strpos($size['mime'], 'image/') !== 0) {

            $return ['status']  = 'Error';
			$return ['message'] = __("Binary data isn't an image", 'axe-import');
            return $return;
		}

		// Mime types are represented as image/gif, image/png, image/jpeg, and so on
		// Therefore, to extract the image extension, we subtract everything after the “image/” prefix
		$ext = substr($size['mime'], 6);

		// Make sure that you save only the desired file extensions
		if (!in_array($ext, ['png', 'gif', 'jpeg'])) {

            $return ['status']  = 'Error';
			$return ['message'] = __('Wrong image format', 'axe-import');
            return $return;
		}

		// Specify the location where you want to save the image
		$img_file = $filename.'.'.$ext;
		$upload_dir = wp_get_upload_dir();
        $file_path = $upload_dir['basedir'].'/wpallimport/files/'.$img_file;
		$bytes = file_put_contents($file_path , $bin);
        if ( false !== $bytes ){
			$return ['status']  = 'OK';
			$return ['filename'] = $img_file;
			return $return;
		}
		$return ['status']  = 'Error';
		$return ['message'] = __('Image file creation failed.', 'axe-import');
		return $return;
	}

	public function convert_repeater_data( $id, $xml_node, $is_update  ){
		$post_type_a  = $xml_node->xpath('./post_type');
		if( empty ( $post_type_a ) ) {
            return;
		}
        $post_type = $post_type_a[0]->__toString();;
        set_post_type($id, $post_type);
        switch ($post_type) {
			case 'axe_artist':
                $elems        = $xml_node->xpath('artworksID/item_3');
                $custom_field = 'artist_re_';
                $custom_value = '_artworkID';
				break;
			case 'axe_group':
				$elems        = $xml_node->xpath('artworksID/item_3');
				$custom_field = 'group_re_';
				$custom_value = '_artworkID';
				break;
			case 'axe_artwork':
				$elems        = $xml_node->xpath('infosID/item_3');
				$custom_field = 'artwork_re_';
				$custom_value = '_infosID';
				break;
			case 'axe_exhibition':
				$elems        = $xml_node->xpath('groupsID/item_2');
				$custom_field = 'exhibition_re_';
				$custom_value = '_groupID';
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

    /**
     * WP AllImport Data modification before the import
     * 
     * @param int $importID ID of the import
     * 
     * @return none
     */
    public function pmxi_before_xml_import( $importID ) {

		// Retrieve import object.
		$import = new PMXI_Import_Record();
		$import->getById( $importID );
	
		// Ensure import object is valid.
		if ( ! $import->isEmpty() ) {
	
				// Retrieve import file path.
				$file_to_import = wp_all_import_get_absolute_path( PMXI_Plugin::$session->filePath );
	
				// Load import file as SimpleXml.
                $file = simplexml_load_file($file_to_import, 'Axe_Import_SimpleXMLElement' );
	
				// Check if post_type is added to items_1
				$query = $file->xpath( "//item_1[1]/post_type[1]" );
				if ( ! empty( $query ) ) {
					// If it is, do nothing.
					return;
	
				}
	
	                // Adding item_1 to exhibition
					$exhibition = $file->xpath( "//exhibition" );
					if ( empty( $exhibition ) ) {

                        return;
					}
					// Check if exibition already prepared
					$exhibition_item = $file->xpath( "//exhibition/item_1" );
					if ( empty( $exhibition_item  ) ) {
						// Do the magic
                        $exhibition_item = new Axe_Import_SimpleXMLElement('<item_1></item_1>');
                        $exhibition_item->appendXML( $exhibition[0] );	
                        $exhibition[0]->appendXML( $exhibition_item->exhibition, 'item_1' );					
					}
	
					// Target path.
					$new_query = $file->xpath( "//item_1" );
	
					// Ensure path is valid.
					if ( ! empty( $new_query ) ) {						
						// Process each Procurement element.
						foreach ( $new_query as $record ) {						
							$parent = $file->xpath( '//item_1[_id[1] = "'.$record->_id.'"]/..' );	
							if ( ! isset( $record->post_type )  && ! empty( $parent ) ) {
	                            $parent_type = $parent[0]->getName();
								switch ($parent_type) {
									case 'exhibition':
                                        $post_type = 'axe_exhibition';
										break;
									case 'groups':
                                        $post_type = 'axe_group';
										break;
									case 'artworks':
										$post_type = 'axe_artwork';
										break;
									case 'artists':
										$post_type = 'axe_artist';
										break;
									case 'infos':
										$post_type = 'axe_infos';
										break;	
									case 'images':
										$post_type = 'axe_image';
										break;																																
									default:
                                        $post_type = 'unknown';
										break;
								}
								
								$record->addChild( 'post_type', $post_type );
								if ( !$record->title ){
									if ( 'axe_image' == $post_type  ) {
										$record->addChild( 'title', __('Image id: ', 'axe-import').$record->_id );
									} elseif( 'axe_artist' == $post_type ) {
										$record->addChild( 'title', $record->firstname.' '.$record->name );
									} else{
                                        $record->addChild('title', $record->_id);
									}
								}
							}
						}
	
						// Save updated file.
						$updated_file = $file->asXML( $file_to_import );
						$import->count = count( $file->xpath( "//item_1" ) );
						PMXI_Plugin::$session->set('count', $import->count );
                        $import->update();
					}
		}
	}

    /**
     * Skip unknown post type
     * 
     * @param bool $continue_import 
	 * @param int $post_id 
	 * @param object $xml_node 
     * @param int $import_id_id 
	 * 
     * @return bool
     */
	public function skip_unknown_posts( $continue_import, $data, $import_id ) {
	   
	   $this->logger( __('Unsupported post type. Row skipped', 'axe-import') );
	   
	   if ( array_key_exists( 'post_type', $data ) && 'unknown' == $data['post_type'] ) {
           return false;
	   } else{

           return true;
	   }
	}	

	public function delete_empty_meta($check, $object_id, $meta_key, $meta_value, $prev_value) {
        $custom_fields = [ '_id', '_description', '_width', '_height', '_imageID', '_start', '_end', '_year',
		'_artistID', '_owner', '_firstname', '_name', '_birth', '_death', '_error' ];
		if( in_array( $meta_key, $custom_fields )  && empty(  $meta_value )) {
			delete_post_meta($object_id, $meta_key, $prev_value);
			return true; //stop update
		}
		
		return null; //do update 
	}
	
	public function keep_existing_if_empty($value, $post_id, $key, $original_value, $existing_meta, $import_id){
        // Check if it has a value.
        if (empty($value)) {
            // If empty, use the existing value.
            $value = isset($existing_meta[$key][0]) ? $existing_meta[$key][0] : $value;

        }
    return $value;

	}

	public function import( $post_id, $data, $import_options, $article ) { 

        $properties = [ '_id', '_description', '_imageID', 
						'_firstname', '_name', '_birth',
						'_death',  '_year', '_artistID',
						'_owner', '_start', '_end',
						'_width', '_height', '_error'
					];
        
		foreach ($properties as $property ) {
			if ( $this->add_on->can_update_meta( $property, $import_options ) && !empty( $data[$property] ) ) {
				update_post_meta( $post_id, $property, $data[$property] );
				$this->logger( __('Assigned value '.$data[$property].' to '.$property.' property', 'axe-import')  );
			}
		}			
	}

	public function init() {

        if ( function_exists('is_plugin_active') ) {
            
            // Display this notice if neither the free or pro version of the Yoast plugin is active.
            if ( ! is_plugin_active( 'wp-all-import/plugin.php' ) && ! is_plugin_active( 'wp-all-import-pro/wp-all-import-pro.php' ) ) {
                // Specify a custom admin notice.
                $this->add_on->admin_notice(
                    __('The Axe Import Add-On requires WP All Import <a href="http://wordpress.org/plugins/wp-all-import" target="_blank">Free</a> and the <a href="https://yoast.com/wordpress/plugins/seo/">Yoast WordPress SEO</a> plugin.', 'axe-import' )
                );
            }
            
            // Only run this add-on if the free or pro version of the Yoast plugin is active.
            if ( is_plugin_active( 'wp-all-import/plugin.php' ) || is_plugin_active( 'wp-all-import-pro/wp-all-import-pro.php' ) ) {
                $this->add_on->run();
            }
        }
	
	}
}
