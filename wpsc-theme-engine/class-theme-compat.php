<?php

class WPSC_Theme_Engine_Compat
{
	private $type;

	public function __construct() {
		add_filter( 'wpsc_theme_compat_reset_globals_archive' , array( $this, '_filter_reset_globals_archive'   ) );
		add_filter( 'wpsc_theme_compat_reset_globals_cart'    , array( $this, '_filter_reset_globals_cart'      ) );
		add_filter( 'wpsc_theme_compat_reset_globals_single'  , array( $this, '_filter_reset_globals_single'    ) );
		add_filter( 'wpsc_theme_compat_reset_globals_taxonomy', array( $this, '_filter_reset_globals_taxonomy' ) );
	}

	public function _filter_reset_globals_taxonomy() {
		return array(
			'post' => array(
				'post_title' => wpsc_get_category_archive_title(),
			),
			'wp_query' => array(
				'is_tax' => true,
			),
		);
	}

	public function _filter_reset_globals_single() {
		$id = wpsc_get_product_id();
		return array(
			'wp_query' => array(
				'is_single' => true,
			),
		);
	}

	public function _filter_reset_globals_archive() {
		return array(
			'post' => array(
				'post_title'   => wpsc_get_product_catalog_title(),
				'post_type'    => 'wpsc-product',
				'post_status'  => 'publish',
			),
			'wp_query' => array(
				'is_archive'   => true,
			),
		);
	}

	public function _filter_reset_globals_cart() {
		return array(
			'post' => array(
				'post_title' => wpsc_get_cart_title(),
			),
			'wp_query' => array(
				'wpsc_is_cart' => true,
			),
		);
	}

	public function activate( $type ) {
		global $wpsc_query;

		$this->type = $type;
		$this->reset_globals();

		// replace the content, making sure this is the last filter that runs in 'the_content',
		// thereby escape all the sanitization that WordPress did
		add_filter( 'the_content', array( $this, '_filter_replace_the_content' ), 9999 );
		add_filter( 'wpsc_replace_the_content_archive' , array( $this, '_filter_replace_the_content_archive'  ) );
		add_filter( 'wpsc_replace_the_content_cart'    , array( $this, '_filter_replace_the_content_cart'     ) );
		add_filter( 'wpsc_replace_the_content_single'  , array( $this, '_filter_replace_the_content_single'   ) );
		add_filter( 'wpsc_replace_the_content_taxonomy', array( $this, '_filter_replace_the_content_taxonomy' ) );
	}

	public function _filter_replace_the_content_archive( $content ) {
		$post_type_object = get_queried_object();
		$post_type = str_replace( array( 'wpsc_', 'wpsc-' ), '', $post_type_object->name );

		ob_start();
		wpsc_get_template_part( "archive-{$post_type}", 'list' );
		return ob_get_clean();
	}

	public function _filter_replace_the_content_cart( $content ) {
		ob_start();
		wpsc_get_template_part( 'cart' );
		return ob_get_clean();
	}

	public function _filter_replace_the_content_single( $content ) {
		ob_start();
		wpsc_get_template_part( 'product', 'single' );
		return ob_get_clean();
	}

	public function _filter_replace_the_content_taxonomy( $content ) {
		$current_term = get_queried_object();

		ob_start();
		wpsc_get_template_part( 'taxonomy', $current_term->taxonomy );
		return ob_get_clean();
	}

	public function _filter_replace_the_content( $content ) {
		remove_filter( 'the_content', array( $this, '_filter_replace_the_content' ), 9999 );

		$before = apply_filters( 'wpsc_replace_the_content_before', '<div class="%s">', $this->type, $content );
		$after  = apply_filters( 'wpsc_replace_the_content_after' , '</div>'          , $this->type, $content );

		$before = sprintf( $before, 'wpsc-replaced-content' );

		$content = apply_filters( "wpsc_replace_the_content_{$this->type}", $content );

		return $content;
	}

	public function reset_globals() {
		global $wp_query;

		$args = apply_filters( "wpsc_theme_compat_reset_globals_{$this->type}", array( 'post' => array(), 'wp_query' => array() ) );
		if ( ! isset( $args['wp_query'] ) )
			$args['wp_query'] = array();

		if ( ! isset( $args['post'] ) )
			$args['post'] = array();

		$defaults = array(
			'post' => array(
				'ID'              => 0,
				'post_title'      => '',
				'post_author'     => 0,
				'post_date'       => 0,
				'post_content'    => '',
				'post_type'       => 'page',
				'post_status'     => 'publish',
				'post_parent'     => 0,
				'post_name'       => '',
				'ping_status'     => 'closed',
				'comment_status'  => 'closed',
				'comment_count'   => 0,
			),
			'wp_query' => array(
				'post_count'      => 1,
				'is_404'          => false,
				'is_page'         => false,
				'is_single'       => false,
				'is_archive'      => false,
				'is_tax'          => false,
			),
		);

		// Default for current post
		if ( isset( $wp_query->post ) ) {
			$post_id = $wp_query->post->ID;
			$defaults['post'] = array_merge( $defaults['post'], array(
				'ID'              => $post_id,
				'post_title'      => get_post_field( 'post_title'  , $post_id ),
				'post_author'     => get_post_field( 'post_author' , $post_id ) ,
				'post_date'       => get_post_field( 'post_date'   , $post_id ),
				'post_content'    => get_post_field( 'post_content', $post_id ),
				'post_type'       => get_post_field( 'post_type'   , $post_id ),
				'post_status'     => get_post_field( 'post_status' , $post_id ),
				'post_name'       => get_post_field( 'post_name'   , $post_id ),
				'comment_status'  => comments_open(),
				)
			);
		}

		$args['post'] = array_merge( $defaults['post'], $args['post'] );
		$args['wp_query'] = array_merge( $defaults['wp_query'], $args['wp_query'] );

		// Clear out the post related globals
		$GLOBALS['post'] = $wp_query->post = (object) $args['post'];
		$wp_query->posts = array( $wp_query->post );

		// Prevent comments form from appearing
		foreach ( $args['wp_query'] as $flag => $value ) {
			$wp_query->$flag = $value;
		}
	}

	public function locate_template() {
		$templates = apply_filters( "wpsc_locate_compat_template_{$this->type}", array( 'page.php' ), $this->type );
		return wpsc_locate_template( $templates );
	}
}