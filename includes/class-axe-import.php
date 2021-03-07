<?php
/**
 * Main plugin class file.
 *
 * @package WordPress Axe Import/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Axe_Import {

	/**
	 * The single instance of Axe_Import.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore


	/**
	 * Local instance of Axe_Import_Admin_API
	 *
	 * @var Axe_Import_Admin_API|null
	 */
	public $admin = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version; //phpcs:ignore

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token; //phpcs:ignore

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for JavaScripts.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor funtion.
	 *
	 * @param string $file File constructor.
	 * @param string $version Plugin version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token   = 'axe_import';

		// Load plugin environment variables.
		$this->file       = $file;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Load frontend JS & CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		// Handle localisation.
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// Load API for generic admin functions.
		if ( is_admin() ) {
			$this->admin = new Axe_Import_Admin_API();
		}

	} // End __construct ()

	/**
	 * Register post type function.
	 *
	 * @param string $post_type Post Type.
	 * @param string $plural Plural Label.
	 * @param string $single Single Label.
	 * @param string $description Description.
	 * @param array  $options Options array.
	 *
	 * @return bool|string|Axe_Import_Post_Type
	 */
	public function register_post_type( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) {
			return false;
		}

		$post_type = new Axe_Import_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	} // End register_post_type ()

	/**
	 * Wrapper function to register a new taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $plural Plural Label.
	 * @param string $single Single Label.
	 * @param array  $post_types Post types to register this taxonomy for.
	 * @param array  $taxonomy_args Taxonomy arguments.
	 *
	 * @return bool|string|Axe_Import_Taxonomy
	 */
	public function register_taxonomy( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) {
			return false;
		}

		$taxonomy = new Axe_Import_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	} // End register_taxonomy ()

	/**
	 * Load frontend CSS.
	 *
	 * @access  public
	 * @return void
	 * @since   1.0.0
	 */
	public function enqueue_styles() {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function enqueue_scripts() {
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-frontend' );

	} // End enqueue_scripts ()

	/**
	 * Admin enqueue style.
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return void
	 */
	public function admin_enqueue_styles( $hook = '' ) {
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin' . $this->script_suffix . '.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
		if ( in_array( $hook, array( 'edit.php', 'post.php' ), true ) ) {
			$screen = get_current_screen();
			if ( in_array( $screen->id, array( 'edit-axe_exhibition', 'axe_exhibition' ), true ) ) {
				wp_register_style( $this->_token . '-admin-bootstrap', esc_url( $this->assets_url ) . 'css/admin-alerts' . $this->script_suffix . '.css', array(), $this->_version );
				wp_enqueue_style( $this->_token . '-admin-bootstrap' );
			}
		}

	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 *
	 * @access  public
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function admin_enqueue_scripts( $hook = '' ) {
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-admin' );

		wp_register_script( $this->_token . '-admin-confirm', esc_url( $this->assets_url ) . 'js/confirm/jquery-confirm.min.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-admin-confirm' );

	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_localisation() {
		load_plugin_textdomain( 'axe-import', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'axe-import';

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main Axe_Import Instance
	 *
	 * Ensures only one instance of Axe_Import is loaded or can be loaded.
	 *
	 * @param string $file File instance.
	 * @param string $version Version parameter.
	 *
	 * @return Object Axe_Import instance
	 * @see Axe_Import()
	 * @since 1.0.0
	 * @static
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of Axe_Import is forbidden' ) ), esc_attr( $this->_version ) );

	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of Axe_Import is forbidden' ) ), esc_attr( $this->_version ) );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install() {
		$this->_log_version_number();

	} // End install ()

	/**
	 * Log the plugin version number.
	 *
	 * @access  private
	 * @return  void
	 * @since   1.0.0
	 */
	private function _log_version_number() { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

	/**
	 * Register all post types.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function register_all_post_types() {

		// Image CPT registration.
		$args = array(
			'supports'      => array( 'thumbnail' ),
			'menu_icon'     => 'dashicons-format-image',
			'menu_position' => 100006,
		);

		$this->register_post_type(
			'axe_image',
			__( 'Images', 'axe-import' ),
			__( 'image', 'axe-import' ),
			__( 'Images for exhibitions', 'axe-import' ),
			$args
		);
		$prefix  = 'image_';
		$config  = array(
			'id'           => $prefix . 'meta_box',
			'title'        => __( 'Dimensions', 'axe-import' ),
			'pages'        => array( 'axe_image' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'local_images' => false,
			'fields'       => array(),
		);
		$my_meta = new Axe_Import_Metabox( $config );
		$my_meta->addHidden( '_id', array( 'name' => __( 'Key', 'axe-import' ) ) );
		$my_meta->addText( '_width', array( 'name' => __( 'Width', 'axe-import' ) ) );
		$my_meta->addText( '_height', array( 'name' => __( 'Height', 'axe-import' ) ) );
		$my_meta->Finish();
		// End of Image CPT registration.

		// Infos CPT registration.
		$args = array(
			'supports'      => array( 'title' ),
			'menu_icon'     => 'dashicons-format-aside',
			'menu_position' => 100005,
		);

		$this->register_post_type(
			'axe_infos',
			__( 'Infos', 'axe-import' ),
			__( 'info', 'axe-import' ),
			__( 'Image infos', 'axe-import' ),
			$args
		);
		$prefix  = 'infos_';
		$config  = array(
			'id'           => $prefix . 'meta_box',
			'title'        => __( 'Details', 'axe-import' ),
			'pages'        => array( 'axe_infos' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'local_images' => false,
			'fields'       => array(),
		);
		$my_meta = new Axe_Import_Metabox( $config );
		$my_meta->addHidden( '_id', array( 'name' => __( 'Key', 'axe-import' ) ) );
		$my_meta->addTextArea( '_description', array( 'name' => __( 'Description', 'axe-import' ) ) );
		$my_meta->addPosts(
			'_imageID',
			array(
				'post_type' => 'axe_image',
				'type'      => 'ajax',
			),
			array( 'name' => __( 'Image', 'axe-import' ) )
		);
		$my_meta->Finish();
		// End of Infos CPT registration.

		// Artist CPT registration.
		$args = array(
			'supports'      => array( 'thumbnail' ),
			'menu_icon'     => 'dashicons-id-alt',
			'menu_position' => 100004,
		);

		$this->register_post_type(
			'axe_artist',
			__( 'Artists', 'axe-import' ),
			__( 'artist', 'axe-import' ),
			__( 'Artists', 'axe-import' ),
			$args
		);
		$prefix  = 'artist_';
		$config  = array(
			'id'           => $prefix . 'meta_box',
			'title'        => __( 'Artist information', 'axe-import' ),
			'pages'        => array( 'axe_artist' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'local_images' => false,
			'fields'       => array(),
		);
		$my_meta = new Axe_Import_Metabox( $config );
		$my_meta->addHidden( '_id', array( 'name' => __( 'Key', 'axe-import' ) ) );
		$my_meta->addText(
			'_firstname',
			array(
				'name'  => __( 'First Name', 'axe-import' ),
				'group' => 'start',
			)
		);
		$my_meta->addText(
			'_name',
			array(
				'name'  => __( 'Name', 'axe-import' ),
				'group' => 'end',
			)
		);
		$my_meta->addText(
			'_birth',
			array(
				'name'  => __( 'Birth', 'axe-import' ),
				'group' => 'start',
			)
		);
		$my_meta->addText(
			'_death',
			array(
				'name'  => __( 'Death', 'axe-import' ),
				'group' => 'end',
			)
		);
		$my_meta->addTextArea( '_description', array( 'name' => __( 'Description', 'axe-import' ) ) );
		$my_meta->addPosts(
			'_imageID',
			array(
				'post_type' => 'axe_image',
				'type'      => 'ajax',
			),
			array( 'name' => __( 'Image', 'axe-import' ) )
		);
		$repeater_fields   = array();
		$repeater_fields[] = $my_meta->addPosts(
			'_artworkID',
			array(
				'post_type' => 'axe_artwork',
				'type'      => 'ajax',
			),
			array( 'name' => __( 'Artwork', 'axe-import' ) ),
			true
		);
		$my_meta->addRepeaterBlock(
			$prefix . 're_',
			array(
				'inline'   => true,
				'name'     => __( 'Artworks', 'axe-import' ),
				'fields'   => $repeater_fields,
				'sortable' => true,
			)
		);
		$my_meta->Finish();
		// End of Artist CPT registration.

		// Artwork CPT registration.
		$args = array(
			'supports'      => array( 'title', 'thumbnail' ),
			'menu_icon'     => 'dashicons-smiley',
			'menu_position' => 100003,
		);
		$this->register_post_type(
			'axe_artwork',
			__( 'Artworks', 'axe-import' ),
			__( 'artwork', 'axe-import' ),
			__( 'Artworks', 'axe-import' ),
			$args
		);

		$prefix  = 'artwork_';
		$config  = array(
			'id'           => $prefix . 'meta_box',
			'title'        => __( 'Artwork information', 'axe-import' ),
			'pages'        => array( 'axe_artwork' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'local_images' => false,
			'fields'       => array(),
		);
		$my_meta = new Axe_Import_Metabox( $config );
		$my_meta->addHidden( '_id', array( 'name' => __( 'Key', 'axe-import' ) ) );
		$my_meta->addText( '_year', array( 'name' => __( 'Year of establishment', 'axe-import' ) ) );
		$my_meta->addPosts(
			'_artistID',
			array(
				'post_type' => 'axe_artist',
				'type'      => 'ajax',
			),
			array( 'name' => __( 'Artist', 'axe-import' ) )
		);
		$my_meta->addTextArea( '_description', array( 'name' => __( 'Description', 'axe-import' ) ) );
		$my_meta->addText( '_owner', array( 'name' => __( 'Owner', 'axe-import' ) ) );
		$my_meta->addPosts(
			'_imageID',
			array(
				'post_type' => 'axe_image',
				'type'      => 'ajax',
			),
			array( 'name' => __( 'Image', 'axe-import' ) )
		);
		$repeater_fields   = array();
		$repeater_fields[] = $my_meta->addPosts(
			'_infosID',
			array(
				'post_type' => 'axe_infos',
				'type'      => 'ajax',
			),
			array( 'name' => __( 'Infos', 'axe-import' ) ),
			true
		);
		$my_meta->addRepeaterBlock(
			$prefix . 're_',
			array(
				'inline'   => true,
				'name'     => __( 'Infos', 'axe-import' ),
				'fields'   => $repeater_fields,
				'sortable' => true,
			)
		);
		$my_meta->Finish();
		// End of Artwork CPT registration.

		// Group CPT registration.
		$args = array(
			'supports'      => array( 'title' ),
			'menu_icon'     => 'dashicons-format-gallery',
			'menu_position' => 100002,
		);
		$this->register_post_type(
			'axe_group',
			__( 'Groups', 'axe-import' ),
			__( 'group', 'axe-import' ),
			__( 'Groups of artwork', 'axe-import' ),
			$args
		);

		$prefix  = 'group_';
		$config  = array(
			'id'           => $prefix . 'meta_box',
			'title'        => __( 'Group information', 'axe-import' ),
			'pages'        => array( 'axe_group' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'local_images' => false,
			'fields'       => array(),
		);
		$my_meta = new Axe_Import_Metabox( $config );
		$my_meta->addHidden( '_id', array( 'name' => __( 'Key', 'axe-import' ) ) );
		$my_meta->addTextArea( '_description', array( 'name' => __( 'Description', 'axe-import' ) ) );
		$repeater_fields   = array();
		$repeater_fields[] = $my_meta->addPosts(
			'_artworkID',
			array(
				'post_type' => 'axe_artwork',
				'type'      => 'ajax',
			),
			array( 'name' => __( 'Artwork', 'axe-import' ) ),
			true
		);
		$my_meta->addRepeaterBlock(
			$prefix . 're_',
			array(
				'inline'   => true,
				'name'     => __( 'Artworks', 'axe-import' ),
				'fields'   => $repeater_fields,
				'sortable' => true,
			)
		);
		$my_meta->Finish();
		// End of Group CPT registration.

		// Exhibition CPT registration.
		$args = array(
			'supports'      => array( 'title' ),
			'menu_icon'     => 'dashicons-images-alt',
			'menu_position' => 100001,
		);
		$this->register_post_type(
			'axe_exhibition',
			__( 'Exhibitions', 'axe-import' ),
			__( 'exhibition', 'axe-import' ),
			__( 'Exhibitions of artwork', 'axe-import' ),
			$args
		);

		$prefix  = 'exhibition_';
		$config  = array(
			'id'           => $prefix . 'meta_box',
			'title'        => __( 'Exhibition information', 'axe-import' ),
			'pages'        => array( 'axe_exhibition' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'local_images' => false,
			'fields'       => array(),
		);
		$my_meta = new Axe_Import_Metabox( $config );
		$my_meta->addHidden( '_id', array( 'name' => __( 'Key', 'axe-import' ) ) );
		$my_meta->addDate(
			'_start',
			array(
				'name'  => __( 'Exhibition starts', 'axe-import' ),
				'group' => 'start',
			)
		);
		$my_meta->addDate(
			'_end',
			array(
				'name'  => __( 'Exhibition ends', 'axe-import' ),
				'group' => 'end',
			)
		);
		$my_meta->addTextArea( '_description', array( 'name' => __( 'Description', 'axe-import' ) ) );
		$repeater_fields   = array();
		$repeater_fields[] = $my_meta->addPosts(
			'_groupID',
			array(
				'post_type' => 'axe_group',
				'type'      => 'ajax',
			),
			array( 'name' => __( 'Group', 'axe-import' ) ),
			true
		);
		$my_meta->addRepeaterBlock(
			$prefix . 're_',
			array(
				'inline'   => true,
				'name'     => __( 'Groups', 'axe-import' ),
				'fields'   => $repeater_fields,
				'sortable' => true,
			)
		);
		$my_meta->Finish();
		// End of Exhibition CPT registration.
	} // End register_all_post_types ()


}
