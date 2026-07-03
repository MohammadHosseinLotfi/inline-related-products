<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IRP_Rest {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	public function register() {
		register_rest_route( 'irp/v1', '/search', array(
			'methods'             => WP_REST_Server::READABLE, // GET
			'callback'            => array( $this, 'search' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'q' => array(
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
	}

	public function search( WP_REST_Request $request ) {
		$q = trim( (string) $request->get_param( 'q' ) );
		if ( mb_strlen( $q ) < 2 ) {
			return rest_ensure_response( array() );
		}

		// جستجوی رسمی ووکامرس (همان روشی که پنل مدیریت ووکامرس استفاده می‌کند) — شامل نام و SKU.
		$data_store = WC_Data_Store::load( 'product' );
		$ids        = $data_store->search_products( $q, '', false, false, 20 );

		$results = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			$img_id = $product->get_image_id();
			$thumb  = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );

			$results[] = array(
				'id'    => $product->get_id(),
				'title' => $product->get_name(),
				'sku'   => $product->get_sku(),
				'thumb' => $thumb,
				'price' => $product->get_price_html(),
			);
		}

		return rest_ensure_response( $results );
	}
}
