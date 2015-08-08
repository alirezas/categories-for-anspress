<?php
/**
 * Categories extension for AnsPress
 *
 * AnsPress - Question and answer plugin for WordPress
 *
 * @package   AnsPress/Categories_for_AnsPress
 * @author    Rahul Aryan <support@anspress.io>
 * @license   GPL-2.0+
 * @link      http://anspress.io
 * @copyright 2014 AnsPress & Rahul Aryan
 *
 * @wordpress-plugin
 * Plugin Name:       Categories for AnsPress
 * Plugin URI:        http://anspress.io/downloads/categories-for-anspress
 * Description:       Extension for AnsPress. Add categories in AnsPress.
 * Donate link: https://www.paypal.com/cgi-bin/webscr?business=rah12@live.com&cmd=_xclick&item_name=Donation%20to%20AnsPress%20development
 * Version:           1.3.9
 * Author:            Rahul Aryan
 * Author URI:        http://anspress.io
 * Text Domain:       categories_for_anspress
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Category extension for AnsPress
 */
class Categories_For_AnsPress
{

	/**
	 * Class instance
	 * @var object
	 * @since 1.0
	 */
	private static $instance;


	/**
	 * Get active object instance
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @static
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Categories_For_AnsPress(); }

		return self::$instance;
	}
	/**
	 * Initialize the class
	 * @since 2.0
	 */
	public function __construct() {

		if ( ! class_exists( 'AnsPress' ) ) {
			return; // AnsPress not installed.
		}
		if ( ! defined( 'CATEGORIES_FOR_ANSPRESS_DIR' ) ) {
			define( 'CATEGORIES_FOR_ANSPRESS_DIR', plugin_dir_path( __FILE__ ) ); }

		if ( ! defined( 'CATEGORIES_FOR_ANSPRESS_URL' ) ) {
				define( 'CATEGORIES_FOR_ANSPRESS_URL', plugin_dir_url( __FILE__ ) ); }

		$this->includes();

		ap_register_page( 'category', __( 'Category', 'categories-for-anspress' ), array( $this, 'category_page' ), false );
		ap_register_page( 'categories', __( 'Categories', 'categories-for-anspress' ), array( $this, 'categories_page' ) );
		add_action( 'init', array( $this, 'textdomain' ) );
		add_action( 'init', array( $this, 'register_question_categories' ), 1 );
		add_action( 'admin_init', array( $this, 'load_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'ap_admin_menu', array( $this, 'admin_category_menu' ) );
		add_filter( 'ap_default_options', array( $this, 'ap_default_options' ) );
		add_action( 'ap_display_question_metas', array( $this, 'ap_display_question_metas' ), 10, 2 );
		add_action( 'ap_enqueue', array( $this, 'ap_enqueue' ) );
		add_filter( 'term_link', array( $this, 'term_link_filter' ), 10, 3 );
		add_action( 'ap_ask_form_fields', array( $this, 'ask_from_category_field' ), 10, 2 );
		add_action( 'ap_ask_fields_validation', array( $this, 'ap_ask_fields_validation' ) );
		add_action( 'ap_processed_new_question', array( $this, 'after_new_question' ), 0, 2 );
		add_action( 'ap_processed_update_question', array( $this, 'after_new_question' ), 0, 2 );
		add_filter( 'ap_page_title', array( $this, 'page_title' ) );
		add_filter( 'ap_breadcrumbs', array( $this, 'ap_breadcrumbs' ) );
		add_filter( 'ap_option_group_layout', array( $this, 'option' ) );
		add_action( 'ap_user_subscription_tab', array( $this, 'subscription_tab' ) );
		add_action( 'ap_user_subscription_page', array( $this, 'subscription_page' ) );
		add_action( 'terms_clauses', array( $this, 'terms_clauses' ), 10, 3 );
		add_action( 'ap_list_head', array( $this, 'ap_list_head' ) );
		add_action( 'question_category_add_form_fields', array( $this, 'image_field_new' ) );
		add_action( 'question_category_edit_form_fields', array( $this, 'image_field_edit' ) );
		add_action( 'create_question_category', array( $this, 'save_image_field' ) );
		add_action( 'edited_question_category', array( $this, 'save_image_field' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
	}

	/**
	 * Include required files
	 */
	public function includes() {
		require_once( CATEGORIES_FOR_ANSPRESS_DIR . 'categories-widget.php' );
	}

	/**
	 * Category page layout
	 */
	public function category_page() {

		global $questions, $question_category;

		$category_id = sanitize_text_field( get_query_var( 'q_cat' ) );

		$question_args = array(
		'tax_query' => array(
			array(
				'taxonomy' => 'question_category',
				'field' => is_numeric( $category_id ) ? 'id' : 'slug',
				'terms' => array( $category_id ),
			),
		),
		);

		$question_category = get_term_by( is_numeric( $category_id ) ? 'id' : 'slug', $category_id, 'question_category' );
		$questions = ap_get_questions( $question_args );
		include( ap_get_theme_location( 'category.php', CATEGORIES_FOR_ANSPRESS_DIR ) );
	}

	/**
	 * Categories page layout
	 */
	public function categories_page() {
		global $question_categories, $ap_max_num_pages, $ap_per_page;

		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
		$per_page           = ap_opt( 'categories_per_page' );
		$total_terms        = wp_count_terms( 'question_category' );
		$offset             = $per_page * ( $paged - 1) ;
		$ap_max_num_pages   = $total_terms / $per_page ;

		$cat_args = array(
			'parent'        => 0,
			'number'        => $per_page,
			'offset'        => $offset,
			'hide_empty'    => false,
			'orderby'       => 'count',
			'order'         => 'DESC',
		);

		/**
		 * FILTER: ap_categories_shortcode_args
		 * Filter applied before getting categories.
		 * @var array
		 * @since 1.0
		 */
		$cat_args = apply_filters( 'ap_categories_shortcode_args', $cat_args );

		$question_categories = get_terms( 'question_category' , $cat_args );

		include ap_get_theme_location( 'categories.php', CATEGORIES_FOR_ANSPRESS_DIR );
	}

	/**
	 * Load plugin text domain
	 * @since 1.0
	 * @access public
	 */
	public static function textdomain() {

		// Set filter for plugin's languages directory.
		$lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';

		// Load the translations.
		load_plugin_textdomain( 'categories-for-anspress', false, $lang_dir );
	}

	/**
	 * Register category taxonomy for question cpt
	 * @return void
	 * @since 2.0
	 */
	public function register_question_categories() {
		ap_register_menu( 'ANSPRESS_CATEGORIES_PAGE_URL', __( 'Categories', 'categories-for-anspress' ), ap_get_link_to( 'categories' ) );

		/**
		 * Labesl for category taxonomy
		 * @var array
		 */
		$categories_labels = array(
			'name' 				=> __( 'Question Categories', 'categories-for-anspress' ),
			'singular_name' 	=> _x( 'Category', 'categories-for-anspress' ),
			'all_items' 		=> __( 'All Categories', 'categories-for-anspress' ),
			'add_new_item' 		=> _x( 'Add New Category', 'categories-for-anspress' ),
			'edit_item' 		=> __( 'Edit Category', 'categories-for-anspress' ),
			'new_item' 			=> __( 'New Category', 'categories-for-anspress' ),
			'view_item' 		=> __( 'View Category', 'categories-for-anspress' ),
			'search_items' 		=> __( 'Search Category', 'categories-for-anspress' ),
			'not_found' 		=> __( 'Nothing Found', 'categories-for-anspress' ),
			'not_found_in_trash' => __( 'Nothing found in Trash', 'categories-for-anspress' ),
			'parent_item_colon' => '',
		);

		/**
		 * FILTER: ap_question_category_labels
		 * Filter ic called before registering question_category taxonomy
		 */
		$categories_labels = apply_filters( 'ap_question_category_labels',  $categories_labels );

		/**
		 * Arguments for category taxonomy
		 * @var array
		 * @since 2.0
		 */
		$category_args = array(
			'hierarchical' => true,
			'labels' => $categories_labels,
			'rewrite' => true,
		);

		/**
		 * FILTER: ap_question_category_args
		 * Filter ic called before registering question_category taxonomy
		 */
		$category_args = apply_filters( 'ap_question_category_args',  $category_args );

		/**
		 * Now let WordPress know about our taxonomy
		 */
		register_taxonomy( 'question_category', array( 'question' ), $category_args );

	}

	/**
	 * Register Categories options
	 */
	public function load_options() {
		$settings = ap_opt();
		ap_register_option_group( 'categories', __( 'Categories', 'categories-for-anspress' ), array(
			array(
				'name'              => 'anspress_opt[form_category_orderby]',
				'label'             => __( 'Category order by', 'categories-for-anspress' ),
				'description'       => __( 'Set how you want to order categories in form.', 'categories-for-anspress' ),
				'type'              => 'select',
				'options'			=> array(
					'ID' 			=> __( 'ID', 'categories-for-anspress' ),
					'name' 			=> __( 'Name', 'categories-for-anspress' ),
					'slug' 			=> __( 'Slug', 'categories-for-anspress' ),
					'count' 		=> __( 'Count', 'categories-for-anspress' ),
					'term_group' 	=> __( 'Group', 'categories-for-anspress' ),
					),
				'value'             => $settings['form_category_orderby'],
			),
		));
	}

	/**
	 * Enqueue required script
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Apppend default options
	 * @param   array $defaults Default AnsPress option
	 * @return  array
	 * @since   1.0
	 */
	public function ap_default_options($defaults) {
		$defaults['form_category_orderby']  = 'count';

		return $defaults;
	}

	/**
	 * Add category menu in wp-admin
	 * @return void
	 * @since 2.0
	 */
	public function admin_category_menu() {
			add_submenu_page( 'anspress', 'Questions Category', 'Category', 'manage_options', 'edit-tags.php?taxonomy=question_category' );
	}

	/**
	 * Append meta display
	 * @param  	array   $metas Display meta items.
	 * @param 	integer $question_id  Question id.
	 * @return 	array
	 * @since 	1.0
	 */
	public function ap_display_question_metas($metas, $question_id) {
		if ( ap_question_have_category( $question_id ) && ! is_singular( 'question' ) ) {
			$metas['categories'] = ap_question_categories_html( array( 'label' => ap_icon( 'category', true ) ) ); }

		return $metas;
	}

	/**
	 * Enqueue scripts
	 * @since 1.0
	 */
	public function ap_enqueue() {
		wp_enqueue_style( 'categories_for_anspress_css', ap_get_theme_url( 'css/categories.css', CATEGORIES_FOR_ANSPRESS_URL ) );

	}

	/**
	 * Filter category permalink.
	 * @param  string $url      Default taxonomy url.
	 * @param  object $term     WordPress term object.
	 * @param  string $taxonomy Current taxonomy slug.
	 * @return string
	 */
	public function term_link_filter( $url, $term, $taxonomy ) {
		if ( 'question_category' == $taxonomy ) {
			if ( get_option( 'permalink_structure' ) != '' ) {
				 return ap_get_link_to( array( 'ap_page' => 'category', 'q_cat' => $term->slug ) ); } else {
				return ap_get_link_to( array( 'ap_page' => 'category', 'q_cat' => $term->term_id ) ); }
		}
		return $url;
	}

	/**
	 * Add category field in ask form
	 * @param  	array 	$args 		Ask form arguments.
	 * @param  	boolean $editing 	true if is edit form.
	 * @return 	array
	 * @since 	2.0
	 */
	public function ask_from_category_field($args, $editing) {
		if ( wp_count_terms( 'question_category' ) == 0 ) {
			return $args; }

		global $editing_post;

		if ( $editing ) {
			$category = get_the_terms( $editing_post->ID, 'question_category' );
			$catgeory = $category[0]->term_id;
		}

		$args['fields'][] = array(
			'name' 		=> 'category',
			'label' 	=> __( 'Category', 'categories-for-anspress' ),
			'type'  	=> 'taxonomy_select',
			'value' 	=> ( $editing ? $catgeory :  sanitize_text_field( wp_unslash( @$_POST['category'] ) ) ),
			'taxonomy' 	=> 'question_category',
			'orderby' 	=> ap_opt( 'form_category_orderby' ),
			'desc' 		=> __( 'Select a topic that best fits your question', 'categories-for-anspress' ),
			'order' 	=> 6,
		);

		return $args;
	}

	/**
	 * Add category in validation field
	 * @param  array $args validation args.
	 * @return array
	 * @since  1.0
	 */
	public function ap_ask_fields_validation($args) {

		if ( wp_count_terms( 'question_category' ) == 0 ) {
			return $args; }

		$args['category'] = array(
			'sanitize' => array( 'only_int' ),
			'validate' => array( 'required' ),
		);

		return $args;
	}

	/**
	 * Things to do after creating a question
	 * @param  	integer $post_id    Questions ID.
	 * @param  	object  $post       Question post object.
	 * @return 	void
	 * @since 	1.0
	 */
	public function after_new_question($post_id, $post) {
		global $validate;

		if ( empty( $validate ) ) {
			return; }

		$fields = $validate->get_sanitized_fields();

		if ( isset( $fields['category'] ) ) {
			$category = wp_set_post_terms( $post_id, $fields['category'], 'question_category' ); }

	}

	/**
	 * Add category page title
	 * @param  string $title AnsPress page title.
	 * @return string
	 */
	public function page_title($title) {
		if ( is_question_categories() ) {
			$title = ap_opt( 'categories_page_title' );
		} elseif ( is_question_category() ) {
			$category_id = sanitize_text_field( get_query_var( 'q_cat' ) );
			$category = get_term_by( is_numeric( $category_id ) ? 'id' : 'slug', $category_id, 'question_category' );
			$title = $category->name;
		}

		return $title;
	}

	/**
	 * Add category nav in AnsPress breadcrumbs.
	 * @param  array $navs Breadcrumbs nav array.
	 * @return array
	 */
	public function ap_breadcrumbs($navs) {
		if ( is_question_category() ) {
			$category_id = sanitize_text_field( get_query_var( 'q_cat' ) );
			$category = get_term_by( is_numeric( $category_id ) ? 'id' : 'slug', $category_id, 'question_category' );
			$navs['page'] = array( 'title' => __( 'Categories', 'categories-for-anspress' ), 'link' => ap_get_link_to( 'categories' ), 'order' => 8 );
			$navs['category'] = array( 'title' => $category->name, 'link' => get_term_link( $category, 'question_category' ), 'order' => 8 );
		} elseif ( is_question_categories() ) {
			$navs['page'] = array( 'title' => __( 'Categories', 'categories-for-anspress' ), 'link' => ap_get_link_to( 'categories' ), 'order' => 8 );

		}

		return $navs;
	}

	public function option($fields) {
		$settings = ap_opt();

		$fields[] = array(
			'name' => 'anspress_opt[categories_page_title]',
			'label' => __( 'Categories title', 'categories-for-anspress' ),
			'desc' => __( 'Title of the categories page', 'categories-for-anspress' ),
			'type' => 'text',
			'value' => $settings['categories_page_title'],
			'show_desc_tip' => false,
		);

		return $fields;
	}

	public function subscription_tab($active) {

		echo '<li class="'.($active == 'category' ? 'active' : '').'"><a href="?tab=category">'.__( 'Category', 'categories-for-anspress' ).'</a></li>';
	}

	public function subscription_page($active) {

		$active = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'question';

		if ( $active != 'category' ) {
			return; }

		global $question_categories, $ap_max_num_pages, $ap_per_page;

		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
		$per_page           = ap_opt( 'categories_per_page' );
		$total_terms        = wp_count_terms( 'question_category' );
		$offset             = $per_page * ( $paged - 1) ;
		$ap_max_num_pages   = $total_terms / $per_page ;

		$cat_args = array(
			'user_id'       => get_current_user_id(),
			'ap_query'      => 'subscription',
			'parent'        => 0,
			'number'        => $per_page,
			'offset'        => $offset,
			'hide_empty'    => false,
			'orderby'       => 'count',
			'order'         => 'DESC',
		);

		$question_categories = get_terms( 'question_category' , $cat_args );

		include ap_get_theme_location( 'categories.php', CATEGORIES_FOR_ANSPRESS_DIR );
	}

	public function terms_clauses($pieces, $taxonomies, $args) {

		if ( ! in_array( 'question_category', $taxonomies ) || ! isset( $args['ap_query'] ) || $args['ap_query'] != 'subscription' ) {
			return $pieces; }

		global $wpdb;

		$pieces['join']     = $pieces['join'].' INNER JOIN '.$wpdb->prefix.'ap_meta apmeta ON t.term_id = apmeta.apmeta_actionid';
		$pieces['where']    = $pieces['where']." AND apmeta.apmeta_type='subscriber' AND apmeta.apmeta_param='category' AND apmeta.apmeta_userid='".$args['user_id']."'";

		return $pieces;
	}

	/**
	 * Add category sorting in list head
	 * @return void
	 */
	public function ap_list_head() {

		global $wp;

		if ( ! isset( $wp->query_vars['ap_sc_atts_categories'] ) ) {
			ap_category_sorting(); }
	}

	/**
	 * Custom question category fields
	 * @param  array $term
	 * @return void
	 */
	public function image_field_new( $term ) {
		echo "<div class='form-field term-image-wrap'>";
		echo "<label for='ap_image'>".__( 'Image', 'categories-for-anspress' ).'</label>';
		echo '<a href="#" id="ap-category-upload">'.__( 'Upload image', 'categories-for-anspress' ).'</a>';

		echo '<input id="ap_category_media_url" type="hidden" name="ap_category_image_url" value="'.$ap_image['url'].'">';
		echo '<input id="ap_category_media_id" type="hidden" name="ap_category_image_id" value="'.$ap_image['id'].'">';
		echo '<p class="description">'.__( 'Category image', 'categories-for-anspress' ).'</p>';
		echo '<div>';
		echo "<div class='form-field term-image-wrap'>";
		echo "<label for='ap_icon'>".__( 'Category icon class', 'categories-for-anspress' ).'</label>';
		echo '<input id="ap_icon" type="text" name="ap_icon" value="">';
		echo '<p class="description">'.__( 'Font icon class, if image not set', 'categories-for-anspress' ).'</p>';
		echo '<div>';
		echo "<div class='form-field term-image-wrap'>";
		echo "<label for='ap-category-color'>".__( 'Category icon color', 'categories-for-anspress' ).'</label>';
		echo '<input id="ap-category-color" type="text" name="ap_color" value="">';
		echo '<p class="description">'.__( 'Icon color', 'categories-for-anspress' ).'</p>';
		echo '<div>';
	}

	public function image_field_edit( $term ) {
		$termID         = $term->term_id;
		$option_name    = 'ap_cat_'.$term->term_id;
		$termMeta       = get_option( $option_name );
		$ap_image       = $termMeta['ap_image'];
		$ap_icon        = $termMeta['ap_icon'];
		$ap_color        = $termMeta['ap_color'];

		echo "<tr class='form-field form-required term-name-wrap'>";
		echo "<th scope='row'><label for='custom-field'>".__( 'Image', 'categories-for-anspress' ).'</label></th>';
		echo '<td>';
		echo '<a href="#" id="ap-category-upload">'.__( 'Upload image', 'categories-for-anspress' ).'</a>';

		if ( isset( $ap_image['url'] ) && $ap_image['url'] != '' ) {
			echo '<img id="ap_category_media_preview" src="'.$ap_image['url'].'" />'; }

		echo '<input id="ap_category_media_url" type="hidden" name="ap_category_image_url" value="'.$ap_image['url'].'">';
		echo '<input id="ap_category_media_id" type="hidden" name="ap_category_image_id" value="'.$ap_image['id'].'">';
		echo "<p class='description'>".__( 'Featured image for category', 'categories-for-anspress' ).'</p>';
		echo '<a href="#" id="ap-category-upload-remove">'.__( 'Remove image', 'categories-for-anspress' ).'</a>';
		echo '</td></tr>';

		echo "<tr class='form-field form-required term-name-wrap'>";
		echo "<th scope='row'><label for='custom-field'>".__( 'Category icon class', 'categories-for-anspress' ).'</label></th>';
		echo '<td>';
		echo '<input id="ap_icon" type="text" name="ap_icon" value="'.$ap_icon.'">';
		echo '<p class="description">'.__( 'Font icon class, if image not set', 'categories-for-anspress' ).'</p>';
		echo '</td></tr>';

		echo "<tr class='form-field form-required term-name-wrap'>";
		echo "<th scope='row'><label for='ap-category-color'>".__( 'Category icon color', 'categories-for-anspress' ).'</label></th>';
		echo '<td>';
		echo '<input id="ap-category-color" type="text" name="ap_color" value="'.$ap_color.'">';
		echo '<p class="description">'.__( 'Font icon class, if image not set', 'categories-for-anspress' ).'</p>';
		echo '</td></tr>';
	}

	public function save_image_field($termID) {

		if ( (isset( $_POST['ap_category_image_url'] ) && isset( $_POST['ap_category_image_id'] )) || isset( $_POST['ap_icon'] ) ) {

			// get options from database - if not a array create a new one
			$termMeta = get_option( 'ap_cat_'.$termID );

			if ( ! is_array( $termMeta ) ) {
				$termMeta = array(); }

			if ( isset( $_POST['ap_category_image_url'] ) && isset( $_POST['ap_category_image_id'] ) ) {

				if ( ! is_array( $termMeta['ap_image'] ) ) {
					$termMeta['ap_image'] = array(); }

				// get value and save it into the database
				$termMeta['ap_image']['url'] = isset( $_POST['ap_category_image_url'] ) ? sanitize_text_field( $_POST['ap_category_image_url'] ) : '';

				$termMeta['ap_image']['id'] = isset( $_POST['ap_category_image_id'] ) ? (int) $_POST['ap_category_image_id'] : '';
			}

			if ( isset( $_POST['ap_icon'] ) ) {
				$termMeta['ap_icon'] = sanitize_text_field( $_POST['ap_icon'] ); }

			if ( isset( $_POST['ap_color'] ) ) {
				$termMeta['ap_color'] = sanitize_text_field( $_POST['ap_color'] ); }

			update_option( 'ap_cat_'.$termID, $termMeta );
		}
	}

	/**
	 * Register category widget
	 * @return void
	 */
	public function register_widget() {
		register_widget( 'AnsPress_Category_Widget' );
	}
}

/**
 * Get everything running
 *
 * @since 1.0
 *
 * @access private
 * @return void
 */

function categories_for_anspress() {
	$discounts = new Categories_For_AnsPress();
}
add_action( 'plugins_loaded', 'categories_for_anspress' );

/**
 * Output question categories
 * @param  array $args
 * @return string
 */
function ap_question_categories_html($args = array()) {

	$defaults  = array(
		'question_id'   => get_the_ID(),
		'list'           => false,
		'tag'           => 'span',
		'class'         => 'question-categories',
		'label'         => __( 'Categories', 'categories-for-anspress' ),
		'echo'          => false,
	);

	if ( ! is_array( $args ) ) {
		$defaults['question_id'] = $args;
		$args = $defaults;
	} else {
		$args = wp_parse_args( $args, $defaults );
	}

	$cats = get_the_terms( $args['question_id'], 'question_category' );

	if ( $cats ) {
		$o = '';
		if ( $args['list'] ) {
			$o = '<ul class="'.$args['class'].'">';
			foreach ( $cats as $c ) {
				$o .= '<li><a href="'.esc_url( get_term_link( $c ) ).'" title="'.$c->description.'">'. $c->name .'</a></li>';
			}
			$o .= '</ul>';

		} else {
			$o = $args['label'];
			$o .= '<'.$args['tag'].' class="'.$args['class'].'">';
			foreach ( $cats as $c ) {
				$o .= '<a href="'.esc_url( get_term_link( $c ) ).'" title="'.$c->description.'">'. $c->name .'</a>';
			}
			$o .= '</'.$args['tag'].'>';
		}
		if ( $args['echo'] ) {
			echo $o; }

		return $o;
	}

}


function ap_category_details() {

	$var = get_query_var( 'question_category' );

	$category = get_term_by( 'slug', $var, 'question_category' );

	echo '<div class="clearfix">';
	echo '<h3><a href="'.get_category_link( $category ).'">'. $category->name .'</a></h3>';
	echo '<div class="ap-taxo-meta">';
	echo '<span class="count">'. $category->count .' '.__( 'Questions', 'categories-for-anspress' ).'</span>';
	echo '<a class="aicon-rss feed-link" href="' . get_term_feed_link( $category->term_id, 'question_category' ) . '" title="Subscribe to '. $category->name .'" rel="nofollow"></a>';
	echo '</div>';
	echo '</div>';

	echo '<p class="desc clearfix">'. $category->description .'</p>';

	$child = get_terms( array( 'taxonomy' => 'question_category' ), array( 'parent' => $category->term_id, 'hierarchical' => false, 'hide_empty' => false ) );

	if ( $child ) :
		echo '<ul class="ap-child-list clearfix">';
		foreach ( $child as $key => $c ) :
			echo '<li><a class="taxo-title" href="'.get_category_link( $c ).'">'.$c->name.'<span>'.$c->count.'</span></a>';
			echo '</li>';
			endforeach;
		echo'</ul>';
	endif;
}
function ap_sub_category_list($parent) {
	$categories = get_terms( array( 'taxonomy' => 'question_category' ), array( 'parent' => $parent, 'hide_empty' => false ) );

	if ( $categories ) {
		echo '<ul class="ap-sub-taxo ap-ul-inline clearfix">';
		foreach ( $categories as $cat ) {
			echo '<li><a href="'.get_category_link( $cat ).'">' .$cat->name.'<span>'.$cat->count.'</span></a></li>';
		}
		echo '</ul>';
	}
}

function ap_question_have_category($post_id = false) {
	if ( ! $post_id ) {
		$post_id = get_the_ID(); }

	$categories = wp_get_post_terms( $post_id, 'question_category' );
	if ( ! empty( $categories ) ) {
		return true; }

	return false;
}


/**
 * Check if anspress categories page
 * @return boolean
 * @since  1.0
 */
if ( ! function_exists( 'is_question_categories' ) ) {
	function is_question_categories() {
		if ( 'categories' == get_query_var( 'ap_page' ) ) {
			return true; }

		return false;
	}
}

if ( ! function_exists( 'is_question_category' ) ) {
	function is_question_category() {
		if ( 'category' == get_query_var( 'ap_page' ) ) {
			return true; }

		return false;
	}
}

function ap_category_sorting() {
	$args = array(
		'hierarchical'      => true,
		'hide_if_empty'     => true,
		'number'            => 10,
	);

	$terms = get_terms( 'question_category', $args );
	$selected = isset( $_GET['ap_cat_sort'] ) ? (int) $_GET['ap_cat_sort'] : '';
	if ( $terms ) {
		echo '<div class="ap-dropdown">';
			echo '<a id="ap-sort-anchor" class="ap-dropdown-toggle'.($selected != '' ? ' active' : '').'" href="#">'.__( 'Category', 'categories-for-anspress' ).'</a>';
			echo '<div class="ap-dropdown-menu">';
		foreach ( $terms as $t ) {
			echo '<li '.($selected == $t->term_id ? 'class="active" ' : '').'><a href="#" data-value="'.$t->term_id.'">'. $t->name .'</a></li>';
		}
			echo '<input name="ap_cat_sort" type="hidden" value="'.$selected.'" />';
			echo '</div>';
		echo '</div>';
	}
}

function ap_get_category_image($term_id) {
	$option = get_option( 'ap_cat_'.$term_id );
	$color = isset( $option['ap_color'] ) && $option['ap_color'] != '' ? ' style="background:'.$option['ap_color'].'"' : ' style="background:#333"';
	if ( isset( $option['ap_image']['url'] ) && $option['ap_image']['url'] != '' ) {
		echo '<img class="ap-category-image" src="'.$option['ap_image']['url'].'" />';
	} elseif ( isset( $option['ap_icon'] ) && $option['ap_icon'] != '' ) {
		echo '<span class="ap-category-icon '.$option['ap_icon'].'"'.$color.'></span>';
	} else {
		echo '<span class="ap-category-icon apicon-category"'.$color.'></span>';
	}

}
