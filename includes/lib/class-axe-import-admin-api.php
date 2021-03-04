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
	 * The posttype filter.
	 *
	 * @var     array
	 * @access  protected
	 * @since   1.0.1
	 */
	protected $post_type_filter;

	/**
	 * Constructor function
	 */
	public function __construct() {

		add_action( 'wp_ajax_axe_get_posts', array( $this, 'ajax_get_posts' ), 10, 1 );
		add_action( 'wp_ajax_axe_get_post_types', array( $this, 'ajax_get_post_types' ), 10, 1 );
		add_action( 'pmxi_saved_post', array( $this, 'convert_repeater_data' ), 10, 3 );
		add_action( 'pmxi_before_xml_import', array( $this, 'pmxi_before_xml_import' ), 10, 1 );
		add_filter( 'update_post_metadata', array( $this, 'delete_empty_meta' ), 10, 5 );
		add_filter( 'wp_all_import_is_post_to_create', array( $this, 'skip_unknown_posts' ), 10, 3 );
		add_filter( 'wpallimport_xml_row', array( $this, 'convert_data_before_import' ), 10, 1 );
		add_filter( 'pmxi_custom_field', array( $this, 'keep_existing_if_empty' ), 10, 6 );
		add_action( 'before_delete_post', array( $this, 'delete_cascade' ), 10, 2 );
		add_action( 'wp_trash_post', array( $this, 'trash_cascade' ), 10 );
		add_action( 'untrash_post', array( $this, 'untrash_cascade' ), 10 );
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );
		add_action( 'manage_posts_extra_tablenav', array( $this, 'add_import_button' ), 10, 1 );
		add_action( 'admin_menu', array( $this, 'register_custom_submenu_page' ), 10 );
		add_action( 'admin_footer-edit.php', array( $this, 'delete_alert' ), 10 );
		add_action( 'admin_footer-post.php', array( $this, 'delete_alert' ), 10 );
		add_action( 'transition_post_status', array( $this, 'change_status_cascade' ), 10, 3 );

		// Define the add-on.
		$this->add_on = new RapidAddon( 'Axe Import Add-On', 'axe-import_addon' );

		// Define add-on fields.
		$this->add_on->add_field( '_id', __( 'ID', 'axe-import' ), 'text' );
		$this->add_on->add_field( '_uid', __( 'Import uid', 'axe-import' ), 'text' );

		$this->add_on->add_options(
			null,
			__( 'General Settings', 'axe-import' ),
			array(
				$this->add_on->add_field( '_description', __( 'Description', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_imageID', __( 'Image ID', 'axe-import' ), 'text' ),
			)
		);

		$this->add_on->add_options(
			null,
			__( 'Image Settings', 'axe-import' ),
			array(
				$this->add_on->add_field( '_width', __( 'Width', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_height', __( 'Height', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_error', __( 'Error', 'axe-import' ), 'text' ),
			)
		);

		$this->add_on->add_options(
			null,
			__( 'Artist Settings', 'axe-import' ),
			array(
				$this->add_on->add_field( '_firstname', __( 'First Name', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_name', __( 'Name', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_birth', __( 'Birth', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_death', __( 'Death', 'axe-import' ), 'text' ),
			)
		);

		$this->add_on->add_options(
			null,
			__( 'Artwork Settings', 'axe-import' ),
			array(
				$this->add_on->add_field( '_year', __( 'Year of establishment', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_artistID', __( 'Artist ID', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_owner', __( 'Owner', 'axe-import' ), 'text' ),
			)
		);

		$this->add_on->add_options(
			null,
			__( 'Exhibition Settings', 'axe-import' ),
			array(
				$this->add_on->add_field( '_start', __( 'Exhibition Starts', 'axe-import' ), 'text' ),
				$this->add_on->add_field( '_end', __( 'Exhibition Ends', 'axe-import' ), 'text' ),
			)
		);

		$this->add_on->set_import_function( array( $this, 'import' ) );
		add_action( 'admin_init', array( $this, 'init' ) );

		$this->post_type_filter = array(
			'axe_exhibition' => true,
			'axe_group'      => true,
			'axe_artwork'    => true,
			'axe_artist'     => true,
			'axe_infos'      => true,
			'axe_image'      => true,
		);
	}

	/**
	 * Generate HTML for displaying fields.
	 *
	 * @param  array   $data Data array.
	 * @param  object  $post Post object.
	 * @param  boolean $echo  Whether to echo the field HTML or return it.
	 * @return string
	 */
	public function display_field( $data = array(), $post = null, $echo = true ) {

		// Get field info.
		if ( isset( $data['field'] ) ) {
			$field = $data['field'];
		} else {
			$field = $data;
		}

		// Check for prefix on option name.
		$option_name = '';
		if ( isset( $data['prefix'] ) ) {
			$option_name = $data['prefix'];
		}

		// Get saved data.
		$data = '';
		if ( $post ) {

			// Get saved field data.
			$option_name .= $field['id'];
			$option       = get_post_meta( $post->ID, $field['id'], true );

			// Get data to display in field.
			if ( isset( $option ) ) {
				$data = $option;
			}
		} else {

			// Get saved option.
			$option_name .= $field['id'];
			$option       = get_option( $option_name );

			// Get data to display in field.
			if ( isset( $option ) ) {
				$data = $option;
			}
		}

		// Show default data if no option saved and default is supplied.
		if ( false === $data && isset( $field['default'] ) ) {
			$data = $field['default'];
		} elseif ( false === $data ) {
			$data = '';
		}

		$html = '';

		switch ( $field['type'] ) {

			case 'text':
			case 'url':
			case 'email':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . esc_attr( $data ) . '" />' . "\n";
				break;

			case 'password':
			case 'number':
			case 'hidden':
				$min = '';
				if ( isset( $field['min'] ) ) {
					$min = ' min="' . esc_attr( $field['min'] ) . '"';
				}

				$max = '';
				if ( isset( $field['max'] ) ) {
					$max = ' max="' . esc_attr( $field['max'] ) . '"';
				}
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . esc_attr( $data ) . '"' . $min . '' . $max . '/>' . "\n";
				break;

			case 'text_secret':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="" />' . "\n";
				break;

			case 'textarea':
				$html .= '<textarea id="' . esc_attr( $field['id'] ) . '" rows="5" cols="50" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '">' . $data . '</textarea><br/>' . "\n";
				break;

			case 'checkbox':
				$checked = '';
				if ( $data && 'on' === $data ) {
					$checked = 'checked="checked"';
				}
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $option_name ) . '" ' . $checked . '/>' . "\n";
				break;

			case 'checkbox_multi':
				foreach ( $field['options'] as $k => $v ) {
					$checked = false;
					if ( in_array( $k, (array) $data, true ) ) {
						$checked = true;
					}
					$html .= '<p><label for="' . esc_attr( $field['id'] . '_' . $k ) . '" class="checkbox_multi"><input type="checkbox" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '[]" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label></p> ';
				}
				break;

			case 'radio':
				foreach ( $field['options'] as $k => $v ) {
					$checked = false;
					if ( $k === $data ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="radio" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
				}
				break;

			case 'select':
				$html .= '<select name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $field['id'] ) . '">';
				foreach ( $field['options'] as $k => $v ) {
					$selected = false;
					if ( $k === $data ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . $v . '</option>';
				}
				$html .= '</select> ';
				break;

			case 'select_multi':
				$html .= '<select name="' . esc_attr( $option_name ) . '[]" id="' . esc_attr( $field['id'] ) . '" multiple="multiple">';
				foreach ( $field['options'] as $k => $v ) {
					$selected = false;
					if ( in_array( $k, (array) $data, true ) ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . $v . '</option>';
				}
				$html .= '</select> ';
				break;

			case 'image':
				$image_thumb = '';
				if ( $data ) {
					$image_thumb = wp_get_attachment_thumb_url( $data );
				}
				$html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
				$html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __( 'Upload an image', 'wordpress-plugin-template' ) . '" data-uploader_button_text="' . __( 'Use image', 'wordpress-plugin-template' ) . '" class="image_upload_button button" value="' . __( 'Upload new image', 'wordpress-plugin-template' ) . '" />' . "\n";
				$html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="' . __( 'Remove image', 'wordpress-plugin-template' ) . '" />' . "\n";
				$html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/><br/>' . "\n";
				break;

			case 'color':
				//phpcs:disable
				?><div class="color-picker" style="position:relative;">
					<input type="text" name="<?php esc_attr_e( $option_name ); ?>" class="color" value="<?php esc_attr_e( $data ); ?>" />
					<div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;" class="colorpicker"></div>
				</div>
				<?php
				//phpcs:enable
				break;

			case 'editor':
				wp_editor(
					$data,
					$option_name,
					array(
						'textarea_name' => $option_name,
					)
				);
				break;

		}

		switch ( $field['type'] ) {

			case 'checkbox_multi':
			case 'radio':
			case 'select_multi':
				$html .= '<br/><span class="description">' . $field['description'] . '</span>';
				break;

			default:
				if ( ! $post ) {
					$html .= '<label for="' . esc_attr( $field['id'] ) . '">' . "\n";
				}

				$html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

				if ( ! $post ) {
					$html .= '</label>' . "\n";
				}
				break;
		}

		if ( ! $echo ) {
			return $html;
		}

		echo $html; //phpcs:ignore

	}

	/**
	 * Validate form field
	 *
	 * @param  string $data Submitted value.
	 * @param  string $type Type of field to validate.
	 * @return string       Validated value
	 */
	public function validate_field( $data = '', $type = 'text' ) {

		switch ( $type ) {
			case 'text':
				$data = esc_attr( $data );
				break;
			case 'url':
				$data = esc_url( $data );
				break;
			case 'email':
				$data = is_email( $data );
				break;
		}

		return $data;
	}

	/**
	 * Logger function
	 *
	 * @param string $m String to put to log.
	 * @return void
	 */
	protected function logger( $m ) {
		$allowed_html = array(
			'div' => array(
				'class' => true,
			),
		);

		$msg = sprintf(
			"<div class='progress-msg'>[%s] %s</div>\n",
			esc_html( gmdate( 'H:i:s' ) ),
			esc_html( $m )
		);
		echo wp_kses( $msg, $allowed_html );
		flush();
	}

	/**
	 * Get posts for ajax query.
	 *
	 * @return void
	 */
	public function ajax_get_posts() {
		global $wpdb;
		$return          = array();
		$return['items'] = array();

		if ( ! isset( $_REQUEST['axe_import_metabox_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['axe_import_metabox_nonce'] ) ), basename( __FILE__ ) )
		) {
			$return['items'][] = array( 'text' => __( 'Sorry, your nonce did not verify', 'axe-import' ) );
		} else {
			$search          = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['q'] ) ) : '';
			$type            = isset( $_REQUEST['post_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['post_type'] ) ) : 'post';
			$args            = array(
				'post_type'      => $type,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			);
			$is_search_posts = true;

			if ( $search ) {
				$search_query = "SELECT ID FROM {$wpdb->prefix}posts
								WHERE post_type = % 
								AND post_title LIKE %s";
				$like         = '%' . $search . '%';
				//phpcs:disable
				$results      = $wpdb->get_results( $wpdb->prepare( $search_query, $type, $like ), ARRAY_N );
				//phpcs:enable
				foreach ( $results as $key => $array ) {
					$quote_ids[] = $array[0];
				}
				if ( isset( $quote_ids ) ) {
					$args['post__in'] = $quote_ids;
				} else {
					$is_search_posts = false;
				}
			}
			if ( $is_search_posts ) {
				$posts = get_posts( $args );
				if ( $posts ) {
					foreach ( $posts as $post ) {
						$item          = array();
						$item['id']    = $post->ID;
						$item['title'] = get_the_title( $post );
						$item['name']  = $post->post_name;
						if ( 'axe_image' === $type ) {
							$item['thumb'] = get_the_post_thumbnail_url( $post, 'thumbnail' );
						}
						$meta        = get_post_meta( $post->ID );
						$item['url'] = get_edit_post_link( $post->ID );
						if ( $meta ) {
							if ( array_key_exists( 'imageID', $meta ) && is_array( $meta['imageID'] ) && isset( $meta['imageID'][0] ) ) {
								$img_id        = $this->get_post_id_by_meta_key_and_value( '_id', $meta['imageID'][0] );
								$item['thumb'] = get_the_post_thumbnail_url( $img_id, 'thumbnail' );
							}
							if ( array_key_exists( '_id', $meta ) ) {
								$item['id'] = $meta['_id'][0];
							}
						}
						$return['items'][] = $item;
					}
				}
				wp_reset_postdata();
			}
		}

		echo wp_json_encode( $return );
		wp_die(); // this is required to terminate immediately and return a proper response.
	}

	/**
	 * Get posts types for ajax query.
	 *
	 * @return void
	 */
	public function ajax_get_post_types() {
		$return            = array();
		$return['content'] = '';

		if ( ! isset( $_REQUEST['axe_alert_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['axe_alert_nonce'] ) ), 'axe-alerts' )
		) {
			$return['content'] = __( 'Sorry, your nonce did not verify', 'axe-import' );
			$retutn['status']  = 'error';
		} else {
			$type               = isset( $_REQUEST['form'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['form'] ) ) : 'default';
			$return['content'] .= sprintf(
				'<form action="" class="form-%s">' .
				'<div class="form-group">' .
				'<label class="form-head">%s</label>',
				$type,
				__( 'Please select which associated content to delete:', 'axe-import' )
			);

			$return['content'] .= wp_nonce_field( 'axe-actions', 'axe_action_nonce', true, false );
			foreach ( $this->post_type_filter as $type => $state ) {
				$post_type = get_post_type_object( $type );
				if ( 'axe_exhibition' === $post_type->name ) {
					continue;
				}
				$return['content'] .= sprintf(
					'<div class="form-check">' .
					'<input class="form-check-input" type="checkbox" id="%s" name="%s" checked>' .
					'<label class="form-check-label" for="groups">%s</label>' .
					'</div>',
					$post_type->name,
					$post_type->name,
					$post_type->labels->name
				);
			}
			$return['content'] .= '</div>' .
			'</form>';
			$return['status']   = 'ok';
		}
		echo wp_json_encode( $return );
		wp_die(); // this is required to terminate immediately and return a proper response.
	}

	/**
	 * Get post id from meta key and value
	 *
	 * @param string $key meta key.
	 * @param mixed  $value meta value.
	 * @return int|bool
	 */
	public static function get_post_id_by_meta_key_and_value( $key, $value ) {
		global $wpdb;
		$cache_key = sprintf( 'metakey[%s]-metavalue[%s]', $key, $value );
		$meta      = wp_cache_get( $cache_key );
		if ( false === $meta ) {
			//phpcs:disable
			$meta = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `' . $wpdb->postmeta . '` WHERE meta_key = %s AND meta_value = %s', $key, $value ) );
			//phpcs:enable
			wp_cache_set( $cache_key, $meta );
		}
		if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta[0] ) ) {
			$meta = $meta[0];
		}
		if ( is_object( $meta ) ) {
			return $meta->post_id;
		} else {
			return false;
		}
	}


	/**
	 * Get post ids from meta key and value
	 *
	 * @param string $key meta key.
	 * @param mixed  $value meta value.
	 * @return array array of related post_id.
	 */
	public static function get_posts_by_meta_key_and_value( $key, $value ) {
		global $wpdb;
		$posts     = array();
		$cache_key = sprintf( 'posts-metakey[%s]-metavalue[%s]', $key, $value );
		$meta      = wp_cache_get( $cache_key );
		if ( false === $meta ) {
			//phpcs:disable
			$meta = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `' . $wpdb->postmeta . '` WHERE meta_key = %s AND meta_value = %s', $key, $value ), OBJECT );
			//phpcs:enable
			wp_cache_set( $cache_key, $meta );
		}
		foreach ( $meta as $row ) {
			if ( $row->post_id ) {
				$posts[] = $row->post_id;
			}
		}
		return $posts;
	}

	/**
	 * WP AllImport Data modification before the import
	 *
	 * @param object $node XML node to convert.
	 * @return object
	 */
	public function convert_data_before_import( $node ) {
		$img      = $node->xpath( 'data[1]' );
		$filename = $node->xpath( '_id[1]' );
		if ( $img ) {
			$base64 = $img[0]->__toString();
			if ( ! empty( $base64 ) ) {
				if ( $filename ) {
					$file = $filename[0]->__toString();
					if ( ! empty( $file ) ) {
						$image_file = $this->base64_to_image( $base64, $file );
						if ( 'OK' === $image_file['status'] ) {
							$node->addChild( 'img_file', $image_file['filename'] );
						} else {
							$node->addChild( 'img_file_error', $image_file['message'] );
						}
					}
				}
			}
		}
		return $node;
	}
	/**
	 * Convert base64 decoded string to image
	 *
	 * @param binary $b64 binary image string.
	 * @param string $filename image file name.
	 * @return array result array with code, message and filename.
	 */
	public function base64_to_image( $b64, $filename ) {
		WP_Filesystem();

		global $wp_filesystem;

		// Obtain the original content (usually binary data).
		//phpcs:disable
		$bin = base64_decode( $b64 );
		//phpcs:enable
		// Gather information about the image using the GD library.
		$size = getImageSizeFromString( $bin );

		// Check the MIME type to be sure that the binary data is an image.
		if ( empty( $size['mime'] ) || strpos( $size['mime'], 'image/' ) !== 0 ) {

			$return ['status']  = 'Error';
			$return ['message'] = __( "Binary data isn't an image", 'axe-import' );
			return $return;
		}

		// Mime types are represented as image/gif, image/png, image/jpeg, and so on
		// Therefore, to extract the image extension, we subtract everything after the “image/” prefix.
		$ext = substr( $size['mime'], 6 );

		// Make sure that you save only the desired file extensions.
		if ( ! in_array( $ext, array( 'png', 'gif', 'jpeg' ), true ) ) {

			$return ['status']  = 'Error';
			$return ['message'] = __( 'Wrong image format', 'axe-import' );
			return $return;
		}

		// Specify the location where you want to save the image.
		$img_file   = $filename . ' . ' . $ext;
		$upload_dir = wp_get_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/wpallimport/files/' . $img_file;
		$bytes      = $wp_filesystem->put_contents( $file_path, $bin );
		if ( false !== $bytes ) {
			$return ['status']   = 'OK';
			$return ['filename'] = $img_file;
			return $return;
		}
		$return ['status']  = 'Error';
		$return ['message'] = __( 'Image file creation failed . ', 'axe-import' );
		return $return;
	}

	/**
	 * Convert XML array to repeater field
	 *
	 * @param int    $id post ID.
	 * @param object $xml_node current node.
	 * @param bool   $is_update update mode.
	 * @return void
	 */
	public function convert_repeater_data( $id, $xml_node, $is_update ) {

		$post_type_a = $xml_node->xpath( './post_type' );
		if ( empty( $post_type_a ) ) {
			return;
		}
		$post_type = $post_type_a[0]->__toString();

		set_post_type( $id, $post_type );
		switch ( $post_type ) {
			case 'axe_artist':
				$elems        = $xml_node->xpath( 'artworksID/item_3' );
				$custom_field = 'artist_re_';
				$custom_value = '_artworkID';
				break;
			case 'axe_group':
				$elems        = $xml_node->xpath( 'artworksID/item_3' );
				$custom_field = 'group_re_';
				$custom_value = '_artworkID';
				break;
			case 'axe_artwork':
				$elems        = $xml_node->xpath( 'infosID/item_3' );
				$custom_field = 'artwork_re_';
				$custom_value = '_infosID';
				break;
			case 'axe_exhibition':
				$elems        = $xml_node->xpath( 'groupsID/item_2' );
				$custom_field = 'exhibition_re_';
				$custom_value = '_groupID';
				break;
			default:
				return;
		}

		foreach ( $elems as $key => $elem ) {
			if ( trim( $elem ) ) {
				$meta[] = array( $custom_value => trim( $elem ) );
			}
		}

		if ( isset( $meta ) && is_array( $meta ) ) {
			update_post_meta( $id, $custom_field, $meta );
		}

	}

	/**
	 * WP AllImport Data modification before the import
	 *
	 * @param int $import_id ID of the import.
	 * @return none
	 */
	public function pmxi_before_xml_import( $import_id ) {

		// Retrieve import object.
		$import = new PMXI_Import_Record();
		$import->getById( $import_id );
		$uid = uniqid();

		// Ensure import object is valid.
		if ( ! $import->isEmpty() ) {

			// Retrieve import file path.
			$file_to_import = wp_all_import_get_absolute_path( PMXI_Plugin::$session->filePath );

			// Load import file as SimpleXml.
			$file = simplexml_load_file( $file_to_import, 'Axe_Import_SimpleXMLElement' );

			// Check if post_type is added to items_1.
			$query = $file->xpath( '//item_1[1]/post_type[1]' );
			if ( ! empty( $query ) ) {
				// If it is, do nothing.
				return;

			}

			// Adding item_1 to exhibition.
			$exhibition = $file->xpath( '//exhibition' );
			if ( empty( $exhibition ) ) {
				return;
			}
			// Check if exibition already prepared.
			$exhibition_item = $file->xpath( '//exhibition/item_1' );
			if ( empty( $exhibition_item ) ) {
				// Do the magic.
				$exhibition_item = new Axe_Import_SimpleXMLElement( '<item_1></item_1>' );
				$exhibition_item->appendXML( $exhibition[0] );
				/**
				 * Array of exhibitions
				 *
				 * @var array[Axe_Import_SimpleXMLElement] $exhibition
				 */
				$exhibition[0]->appendXML( $exhibition_item->exhibition, 'item_1' );
			}

			// Target path.
			$new_query = $file->xpath( '//item_1' );

			// Ensure path is valid.
			if ( ! empty( $new_query ) ) {
				// Process each Procurement element.
				foreach ( $new_query as $record ) {
					$parent = $file->xpath( '//item_1[_id[1] = "' . $record->_id . '"]/..' );
					if ( ! isset( $record->post_type ) && ! empty( $parent ) ) {
						$parent_type = $parent[0]->getName();
						switch ( $parent_type ) {
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
						if ( ! $record->title ) {
							if ( 'axe_image' === $post_type ) {
								$record->addChild( 'title', __( 'Image id: ', 'axe-import' ) . $record->_id );
							} elseif ( 'axe_artist' === $post_type ) {
								$record->addChild( 'title', $record->firstname . ' ' . $record->name );
							} else {
								$record->addChild( 'title', $record->_id );
							}
						}

						if ( ! $record->uid ) {
							$record->uid = $uid;
						} else {
							$record->addChild( 'uid', $uid );
						}
					}
				}

				// Save updated file.
				$updated_file  = $file->asXML( $file_to_import );
				$import->count = count( $file->xpath( '//item_1' ) );
				PMXI_Plugin::$session->set( 'count', $import->count );
				$import->update();
			}
		}
	}

	/**
	 * Skip unknown post type
	 *
	 * @param bool  $continue_import if import should continue.
	 * @param array $data current data to check.
	 * @param int   $import_id import ID.
	 * @return bool
	 */
	public function skip_unknown_posts( $continue_import, $data, $import_id ) {

		$this->logger( __( 'Unsupported post type. Row skipped', 'axe-import' ) );

		if ( array_key_exists( 'post_type', $data ) && 'unknown' === $data['post_type'] ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Delete meta key if its value is empty
	 *
	 * @param bool   $check previous check result.
	 * @param int    $object_id ID of checked object.
	 * @param string $meta_key meta key.
	 * @param string $meta_value meta value.
	 * @param string $prev_value previous meta value.
	 * @return bool stop or continue update.
	 */
	public function delete_empty_meta( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		$custom_fields = array(
			'_id',
			'_description',
			'_width',
			'_height',
			'_imageID',
			'_start',
			'_end',
			'_year',
			'_artistID',
			'_owner',
			'_firstname',
			'_name',
			'_birth',
			'_death',
			'_error',
		);
		if ( in_array( $meta_key, $custom_fields, true ) && empty( $meta_value ) ) {
			delete_post_meta( $object_id, $meta_key, $prev_value );
			return true; // stop update.
		}

		return null; // do update.
	}

	/**
	 * Keep existing value of custom field if new value is empty
	 *
	 * @param mixed  $value value to update.
	 * @param in     $post_id post ID.
	 * @param string $key meta key.
	 * @param mixed  $original_value original value before update.
	 * @param array  $existing_meta existing meta.
	 * @param int    $import_id import ID.
	 * @return mixed final value to update.
	 */
	public function keep_existing_if_empty( $value, $post_id, $key, $original_value, $existing_meta, $import_id ) {
		// Check if it has a value.
		if ( empty( $value ) ) {
			// If empty, use the existing value.
			$value = isset( $existing_meta[ $key ][0] ) ? $existing_meta[ $key ][0] : $value;

		}
		return $value;

	}

	/**
	 * Import data from WP AllImport Add-on
	 *
	 * @param int   $post_id post ID.
	 * @param array $data data to insert.
	 * @param array $import_options import options.
	 * @param array $article post values.
	 * @return void
	 */
	public function import( $post_id, $data, $import_options, $article ) {

		$properties = array(
			'_id',
			'_description',
			'_imageID',
			'_firstname',
			'_name',
			'_birth',
			'_death',
			'_year',
			'_artistID',
			'_owner',
			'_start',
			'_end',
			'_width',
			'_height',
			'_error',
			'_uid',
		);

		foreach ( $properties as $property ) {
			if ( $this->add_on->can_update_meta( $property, $import_options ) && ! empty( $data[ $property ] ) ) {
				update_post_meta( $post_id, $property, $data[ $property ] );
				// translators: key and value to log.
				$this->logger( sprintf( __( 'Assigned value %s to property %', 'axe-import' ), $data[ $property ], $property ) );
			}
		}
	}

	/**
	 * Init function for Add On
	 *
	 * @return void
	 */
	public function init() {

		if ( function_exists( 'is_plugin_active' ) ) {

			// Display this notice if neither the free or pro version of the Yoast plugin is active.
			if ( ! is_plugin_active( 'wp-all-import/plugin.php' ) && ! is_plugin_active( 'wp-all-import-pro/wp-all-import-pro.php' ) ) {
				// Specify a custom admin notice.
				$this->add_on->admin_notice(
					__( 'The Axe Import Add-On requires WP All Import <a href="http://wordpress.org/plugins/wp-all-import" target="_blank">Free</a>.', 'axe-import' )
				);
			}

			// Only run this add-on if the free or pro version of the Yoast plugin is active.
			if ( is_plugin_active( 'wp-all-import/plugin.php' ) || is_plugin_active( 'wp-all-import-pro/wp-all-import-pro.php' ) ) {
				$this->add_on->run();
			}
		}

	}

	/**
	 * Cascade restore from trash of all elements from Exhibition post
	 *
	 * @param int $post_id  post ID to restore from trash.
	 * @return void
	 */
	public function untrash_cascade( $post_id ) {

		if ( isset( $_REQUEST['axe-action-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['axe-action-nonce'] ) ), 'axe-actions' ) ) {
			$this->post_type_filter['axe_group']   = isset( $_REQUEST['groups'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['groups'] ) ) ? false : true;
			$this->post_type_filter['axe_artwork'] = isset( $_REQUEST['artworks'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['artworks'] ) ) ? false : true;
			$this->post_type_filter['axe_artist']  = isset( $_REQUEST['artists'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['artists'] ) ) ? false : true;
			$this->post_type_filter['axe_infos']   = isset( $_REQUEST['infos'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['infos'] ) ) ? false : true;
			$this->post_type_filter['axe_image']   = isset( $_REQUEST['images'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['images'] ) ) ? false : true;
		}
		$post_type = get_post_type( $post_id );
		if ( 'axe_exhibition' === $post_type && $this->is_action_needed( $post_id, 'restore', false ) ) {
			$this->move_cascade( $post_id, 'restore', false );
		}
	}

	/**
	 * Cascade change status from Exhibition post
	 *
	 * @param string $new_status new post status.
	 * @param string $old_status old post status.
	 * @param object $post post to update status.
	 * @return void
	 */
	public function change_status_cascade( $new_status, $old_status, $post ) {

		if ( isset( $_REQUEST['axe-action-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['axe-action-nonce'] ) ), 'axe-actions' ) ) {
			$this->post_type_filter['axe_group']   = isset( $_REQUEST['groups'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['groups'] ) ) ? false : true;
			$this->post_type_filter['axe_artwork'] = isset( $_REQUEST['artworks'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['artworks'] ) ) ? false : true;
			$this->post_type_filter['axe_artist']  = isset( $_REQUEST['artists'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['artists'] ) ) ? false : true;
			$this->post_type_filter['axe_infos']   = isset( $_REQUEST['infos'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['infos'] ) ) ? false : true;
			$this->post_type_filter['axe_image']   = isset( $_REQUEST['images'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['images'] ) ) ? false : true;
		}

		if ( $old_status === $new_status && 'draft' === $old_status ) {
			$new_status        = 'publish';
			$post->post_status = $new_status;
			wp_update_post( $post );
		}
		$post_type = $post->post_type;

		if ( 'axe_exhibition' === $post_type ) {
			$meta = get_post_meta( $post->ID, '_uid' );
			if ( $meta && is_array( $meta ) && isset( $meta[0] ) ) {
				$meta = $meta[0];
			}
			$posts = $this->get_posts_by_meta_key_and_value( '_uid', $meta );
			foreach ( $posts as $post_id ) {
				if ( strval( $post->ID ) !== $post_id ) {
					$post_type = get_post_type( $post_id );
					if ( ! isset( $this->post_type_filter[ $post_type ] ) || $this->post_type_filter[ $post_type ] ) {
						$_post = get_post( $post_id );
						if ( $_post->post_status !== $new_status ) {
							$_post->post_status = $new_status;
							wp_update_post( wp_slash( $_post ) );
						}
					}
				}
			}
		}
	}


	/**
	 * Cascade move to trash of all elements from Exhibition post
	 *
	 * @param int $post_id  post ID to move to trash.
	 * @return void
	 */
	public function trash_cascade( $post_id ) {
		if ( isset( $_REQUEST['axe-action-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['axe-action-nonce'] ) ), 'axe-actions' ) ) {
			$this->post_type_filter['axe_group']   = isset( $_REQUEST['groups'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['groups'] ) ) ? false : true;
			$this->post_type_filter['axe_artwork'] = isset( $_REQUEST['artworks'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['artworks'] ) ) ? false : true;
			$this->post_type_filter['axe_artist']  = isset( $_REQUEST['artists'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['artists'] ) ) ? false : true;
			$this->post_type_filter['axe_infos']   = isset( $_REQUEST['infos'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['infos'] ) ) ? false : true;
			$this->post_type_filter['axe_image']   = isset( $_REQUEST['images'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['images'] ) ) ? false : true;
		}
		$post_type = get_post_type( $post_id );
		if ( 'axe_exhibition' === $post_type && $this->is_action_needed( $post_id, 'delete', true ) ) {
			$this->move_cascade( $post_id, 'delete', true );
		}
	}

	/**
	 * Cascade deletion of all elements from Exhibition post
	 *
	 * @param int    $post_id deleted post ID.
	 * @param object $post deleted post.
	 * @return void
	 */
	public function delete_cascade( $post_id, $post ) {

		if ( isset( $_REQUEST['axe-action-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['axe-action-nonce'] ) ), 'axe-actions' ) ) {
			$this->post_type_filter['axe_group']   = isset( $_REQUEST['groups'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['groups'] ) ) ? false : true;
			$this->post_type_filter['axe_artwork'] = isset( $_REQUEST['artworks'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['artworks'] ) ) ? false : true;
			$this->post_type_filter['axe_artist']  = isset( $_REQUEST['artists'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['artists'] ) ) ? false : true;
			$this->post_type_filter['axe_infos']   = isset( $_REQUEST['infos'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['infos'] ) ) ? false : true;
			$this->post_type_filter['axe_image']   = isset( $_REQUEST['images'] ) && 'false' === sanitize_text_field( wp_unslash( $_REQUEST['images'] ) ) ? false : true;
		}

		$post_type = get_post_type( $post_id );
		if ( 'axe_exhibition' === $post_type && $this->is_action_needed( $post_id, 'delete', false ) ) {
			$this->move_cascade( $post_id, 'delete', false );
		}
	}

	/**
	 * Cascade movement of posts
	 *
	 * @param int     $id deleted post ID.
	 * @param string  $direction delete or restore.
	 * @param boolean $trash if true post moves to trash bin.
	 * @return void
	 */
	public function move_cascade( $id, $direction, $trash ) {

		$meta = get_post_meta( $id, '_uid' );
		if ( $meta && is_array( $meta ) && isset( $meta[0] ) ) {
			$meta = $meta[0];
		}
		$posts = $this->get_posts_by_meta_key_and_value( '_uid', $meta );
		foreach ( $posts as $post_id ) {
			if ( strval( $id ) !== $post_id ) {
				$post_type = get_post_type( $post_id );
				if ( ! isset( $this->post_type_filter[ $post_type ] ) || $this->post_type_filter[ $post_type ] ) {
					$this->move_post( $post_id, $direction, $trash );
				}
			}
		}
	}

	/**
	 * Move post
	 *
	 * @param int     $post_id ID of the post.
	 * @param string  $direction delete or restore.
	 * @param boolean $trash if the post should move to trash bin.
	 * @return void
	 */
	private function move_post( $post_id, $direction = 'delete', $trash = false ) {
		if ( 'delete' === $direction ) {
			if ( $trash ) {
				wp_trash_post( $post_id );
			} else {
				$attachments = get_attached_media( '', $post_id );
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment( $attachment->ID, 'true' );
				}
				wp_delete_post( $post_id );
			}
		} else {
			wp_untrash_post( $post_id );
		}
	}

	/**
	 * Check if action needed
	 *
	 * @param int     $post_id ID of the post.
	 * @param string  $direction delete or restore.
	 * @param boolean $trash if the post should move to trash bin.
	 * @return boolean
	 */
	private function is_action_needed( $post_id, $direction, $trash ) {
		$status = get_post_status( $post_id );
		if ( 'publish' === $status && 'restore' === $direction ) {
			return false;
		}
		if ( 'trash' === $status && 'delete' === $direction && $trash ) {
			return false;
		}
		return true;
	}

	/**
	 * Add import button to exhibition list
	 *
	 * @param string $which part of the page.
	 * @return void
	 */
	public function add_import_button( $which ) {
		global $typenow;
		$allowed_html = array(
			'a'      => array(
				'href'  => true,
				'title' => true,
			),
			'div'    => array(
				'class' => true,
			),
			'button' => array(
				'class' => true,
				'name'  => true,
				'type'  => true,
				'value' => true,
			),
		);

		if ( 'axe_exhibition' === $typenow && 'top' === $which ) {
			$import_name = get_option( 'axe_import_name' );
			if ( ! $import_name ) {
				$import_name = 'Axe Import';
			}
			$import = new PMXI_Import_Record();
			$import->getByFriendly_Name( $import_name );
			if ( ! $import->isEmpty() ) {
				$import_id = $import->id;
				$url       = get_admin_url(
					null,
					sprintf( 'admin.php?page=pmxi-admin-manage&id=%s&action=options', $import_id )
				);
				$button    = sprintf(
					'<div class="alignleft actions axe-import-button">
            			<button type="submit" name="import_exhibition" class="button" value="yes">
						<a href="%s">%s</a></button>
           			</div>',
					$url,
					__( 'Import Exhibition', 'axe-import' )
				);
				echo wp_kses( $button, $allowed_html );
			} else {
				$this->add_on->admin_notice(
					// translators: the name of import.
					sprintf( __( 'Could not find any import with the name %s. Please create an import using Wp All Import menu.', 'axe-import' ), $import_name )
				);
			}
		}
	}

	/**
	 * Admin menu rearangement
	 *
	 * @return void
	 */
	public function register_custom_submenu_page() {
		$import_name = get_option( 'axe_import_name' );
		if ( ! $import_name ) {
			$import_name = 'Axe Import';
		}
		$import = new PMXI_Import_Record();
		$import->getByFriendly_Name( $import_name );
		if ( ! $import->isEmpty() ) {
			$import_id = $import->id;
			$url       = get_admin_url(
				null,
				sprintf( 'admin.php?page=pmxi-admin-manage&id=%s&action=options', $import_id )
			);
			add_submenu_page(
				'edit.php?post_type=axe_exhibition',
				__( 'Import Exhibition', 'axe-import' ),
				__( 'Import Exhibition', 'axe-import' ),
				'manage_options',
				$url,
				''
			);
		}

		global $menu;
		//phpcs:disable
		$menu['3.1'] = $menu[100001];
		$menu['3.2'] = $menu[100002];
		$menu['3.3'] = $menu[100003];
		$menu['3.4'] = $menu[100004];
		$menu['3.6'] = $menu[100005];
		$menu['3.7'] = $menu[100006];

		unset( $menu[100001] );
		unset( $menu[100002] );
		unset( $menu[100003] );
		unset( $menu[100004] );
		unset( $menu[100005] );
		unset( $menu[100006] );

		// move Media menu (position 10) item to front, in the same group.
		$menu['3.8'] = $menu[10];
		unset( $menu[10] );
		// phpcs:enable

	}

	/**
	 * Alert before deleting
	 *
	 * @return bool
	 */
	public function delete_alert() {
		$screen = get_current_screen();
		$nonce  = wp_create_nonce( 'axe-alerts' );
		if ( in_array( $screen->id, array( 'edit-axe_exhibition', 'axe_exhibition' ), true ) ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('a.submitdelete').click(function(e){
						e.preventDefault();
						var href = $(this).attr('href');
						$.confirm({
							title: '<?php echo esc_html_e( 'Do you really want to delete the exhibition?', 'axe-import' ); ?>',
							columnClass: 'medium',
							icon: 'fa fa-warning',
							type: 'red',
							content: function () {
								var self = this;
								return $.ajax({
									url: ajaxurl,
									dataType: 'json',
									method: 'get',
									data: { 
											action: 'axe_get_post_types',
											form: 'red',
											axe_alert_nonce:  '<?php echo esc_html( $nonce ); ?>' 
									}
								}).done(function (response) {
									self.setContent(response.content);
								}).fail(function(){
									self.setContent('Something went wrong.');
								});
							},
							buttons: {
								ok: {
									text: '<?php echo esc_html_e( 'Delete', 'axe-import' ); ?>',
									btnClass: 'btn-red',
									action: function () {
										var groups = this.$content.find('#axe_group').is(':checked');
										var artworks = this.$content.find('#axe_artwork').is(':checked');
										var artists = this.$content.find('#axe_artist').is(':checked');
										var infos = this.$content.find('#axe_infos').is(':checked');
										var images = this.$content.find('#axe_image').is(':checked');	
										var nonce = this.$content.find('#axe_action_nonce').val();

										location.href = href + '&groups=' + groups
														+ '&artworks=' + artworks
														+ '&artists=' + artists
														+ '&infos=' + infos
														+ '&images=' + images
														+ '&axe_action_nonce=' + nonce;
									}
								},
								cancel: {
									text: '<?php echo esc_html_e( 'Cancel', 'axe-import' ); ?>',
									action: function () {
									//close
									}
								},
							}
						});
					});

					$('#doaction').click(function(e){
						if($('#bulk-action-selector-top').val() == 'trash'){
							if($('input[name="post[]"]:checked').length > 0){
								e.preventDefault();
								$.confirm({
									title: '<?php echo esc_html_e( 'Do you really want to delete these exhibitions?', 'axe-import' ); ?>',
									columnClass: 'medium',
									icon: 'fa fa-warning',
									type: 'red',
									content: function () {
												var self = this;
												return $.ajax({
													url: ajaxurl,
													dataType: 'json',
													method: 'get',
													data: { 
															action: 'axe_get_post_types',
															form: 'red',
															axe_alert_nonce:  '<?php echo esc_html( $nonce ); ?>'
													}
												}).done(function (response) {
													self.setContent(response.content);
												}).fail(function(){
													self.setContent('Something went wrong.');
												});
											},
									buttons: {
										ok: {
											text: '<?php echo esc_html_e( 'Delete', 'axe-import' ); ?>',
											btnClass: 'btn-red',
											action: function () {

													$('<input />').attr('type', 'hidden')
													.attr('name', 'groups')
													.attr('value', this.$content.find('#axe_group').is(':checked'))
													.appendTo('#posts-filter');

													$('<input />').attr('type', 'hidden')
													.attr('name', 'artworks')
													.attr('value', this.$content.find('#axe_artwork').is(':checked'))
													.appendTo('#posts-filter');

													$('<input />').attr('type', 'hidden')
													.attr('name', 'atrists')
													.attr('value', this.$content.find('#axe_atrist').is(':checked'))
													.appendTo('#posts-filter');

													$('<input />').attr('type', 'hidden')
													.attr('name', 'infos')
													.attr('value', this.$content.find('#axe_infos').is(':checked'))
													.appendTo('#posts-filter');

													$('<input />').attr('type', 'hidden')
													.attr('name', 'images')
													.attr('value', this.$content.find('#axe_image').is(':checked'))
													.appendTo('#posts-filter');

													$('#posts-filter').submit();
											}
										},
										cancel: {
											text: '<?php echo esc_html_e( 'Cancel', 'axe-import' ); ?>',
											action: function () {
												//close
											}
										},
									}
								});
							}
						}
					});

					$('.untrash a').click(function(e){
						e.preventDefault();
						var href = $(this).attr('href');
						$.confirm({
							title: '<?php echo esc_html_e( 'Do you really want to restore the exhibition?', 'axe-import' ); ?>',
							columnClass: 'medium',
							type: 'green',
							content: function () {
								var self = this;
								return $.ajax({
									url: ajaxurl,
									dataType: 'json',
									method: 'get',
									data: { 
											action: 'axe_get_post_types',
											form: 'green',
											axe_alert_nonce:  '<?php echo esc_html( $nonce ); ?>' 
									}
								}).done(function (response) {
									self.setContent(response.content);
								}).fail(function(){
									self.setContent('Something went wrong.');
								});
							},
							buttons: {
								ok: {
									text: '<?php echo esc_html_e( 'Restore', 'axe-import' ); ?>',
									btnClass: 'btn-green',
									action: function () {
										var groups = this.$content.find('#axe_group').is(':checked');
										var artworks = this.$content.find('#axe_artwork').is(':checked');
										var artists = this.$content.find('#axe_artist').is(':checked');
										var infos = this.$content.find('#axe_infos').is(':checked');
										var images = this.$content.find('#axe_image').is(':checked');	
										var nonce = this.$content.find('#axe_action_nonce').val();

										location.href = href + '&groups=' + groups
														+ '&artworks=' + artworks
														+ '&artists=' + artists
														+ '&infos=' + infos
														+ '&images=' + images
														+ '&axe_action_nonce=' + nonce;
									}
								},
								cancel: {
									text: '<?php echo esc_html_e( 'Cancel', 'axe-import' ); ?>',
									action: function () {
									//close
									}
								},
							}
						});
					});		

					$('#doaction').click(function(e){
						if($('#bulk-action-selector-top').val() == 'untrash'){
							if($('input[name="post[]"]:checked').length > 0){
								e.preventDefault();
								$.confirm({
							title: '<?php echo esc_html_e( 'Do you really want to restore the exhibition?', 'axe-import' ); ?>',
							columnClass: 'medium',
							type: 'green',
							content: function () {
								var self = this;
								return $.ajax({
									url: ajaxurl,
									dataType: 'json',
									method: 'get',
									data: { 
											action: 'axe_get_post_types',
											form: 'green',
											axe_alert_nonce:  '<?php echo esc_html( $nonce ); ?>' 
									}
								}).done(function (response) {
									self.setContent(response.content);
								}).fail(function(){
									self.setContent('Something went wrong.');
								});
							},
							buttons: {
								ok: {
									text: '<?php echo esc_html_e( 'Restore', 'axe-import' ); ?>',
									btnClass: 'btn-green',
									action: function () {
										$('<input />').attr('type', 'hidden')
													.attr('name', 'groups')
													.attr('value', this.$content.find('#axe_group').is(':checked'))
													.appendTo('#posts-filter');

													$('<input />').attr('type', 'hidden')
													.attr('name', 'artworks')
													.attr('value', this.$content.find('#axe_artwork').is(':checked'))
													.appendTo('#posts-filter');

													$('<input />').attr('type', 'hidden')
													.attr('name', 'atrists')
													.attr('value', this.$content.find('#axe_atrist').is(':checked'))
													.appendTo('#posts-filter');

													$('<input />').attr('type', 'hidden')
													.attr('name', 'infos')
													.attr('value', this.$content.find('#axe_infos').is(':checked'))
													.appendTo('#posts-filter');

													$('<input />').attr('type', 'hidden')
													.attr('name', 'images')
													.attr('value', this.$content.find('#axe_image').is(':checked'))
													.appendTo('#posts-filter');

													$('#posts-filter').submit();
									}
								},
								cancel: {
									text: '<?php echo esc_html_e( 'Cancel', 'axe-import' ); ?>',
									action: function () {
									//close
									}
								},
							}
						});
							}
						}
					});	

					$('#publish').click(function(e){
						e.preventDefault();
						$.confirm({
							title: '<?php echo esc_html_e( 'Do you really want to change status of the exhibitions?', 'axe-import' ); ?>',
							columnClass: 'medium',
							type: 'purple',
							content: function () {
								var self = this;
								return $.ajax({
									url: ajaxurl,
									dataType: 'json',
									method: 'get',
									data: { 
											action: 'axe_get_post_types',
											form: 'purple',
											axe_alert_nonce:  '<?php echo esc_html( $nonce ); ?>' 
									}
								}).done(function (response) {
									self.setContent(response.content);
								}).fail(function(){
									self.setContent('Something went wrong.');
								});
							},
							buttons: {
								ok: {
										text: '<?php echo esc_html_e( 'Update', 'axe-import' ); ?>',
										btnClass: 'btn-purple',
										action: function () {

											$('<input />').attr('type', 'hidden')
												.attr('name', 'groups')
												.attr('value', this.$content.find('#axe_group').is(':checked'))
												.appendTo('#post');

											$('<input />').attr('type', 'hidden')
												.attr('name', 'artworks')
												.attr('value', this.$content.find('#axe_artwork').is(':checked'))
												.appendTo('#post');

											$('<input />').attr('type', 'hidden')
												.attr('name', 'atrists')
												.attr('value', this.$content.find('#axe_atrist').is(':checked'))
												.appendTo('#post');

											$('<input />').attr('type', 'hidden')
												.attr('name', 'infos')
												.attr('value', this.$content.find('#axe_infos').is(':checked'))
												.appendTo('#post');

											$('<input />').attr('type', 'hidden')
												.attr('name', 'images')
												.attr('value', this.$content.find('#axe_image').is(':checked'))
												.appendTo('#post#publish');

											$('#post').submit();
										}
									},
									cancel: {
											text: '<?php echo esc_html_e( 'Cancel', 'axe-import' ); ?>',
											action: function () {
												//close
											}
									},
							}
						});
					});
				});
			</script>
			<?php
		}
		return true;
	}
}
