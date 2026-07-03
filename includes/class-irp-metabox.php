<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IRP_Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** پست‌تایپ‌هایی که متاباکس در آن‌ها نمایش داده می‌شود. */
	public function post_types() {
		return (array) apply_filters( 'irp_post_types', array( 'post' ) );
	}

	public function register() {
		foreach ( $this->post_types() as $pt ) {
			add_meta_box(
				'irp_metabox',
				__( 'محصولات مرتبط داخل متن', 'irp' ),
				array( $this, 'render' ),
				$pt,
				'normal',
				'high'
			);
		}
	}

	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->post_types(), true ) ) {
			return;
		}

		wp_enqueue_style( 'irp-admin', IRP_URL . 'assets/admin.css', array(), IRP_VERSION );
		wp_enqueue_script( 'irp-admin', IRP_URL . 'assets/admin.js', array(), IRP_VERSION, true );

		wp_localize_script( 'irp-admin', 'IRP_ADMIN', array(
			'restUrl' => esc_url_raw( rest_url( 'irp/v1/search' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'searching'     => __( 'در حال جستجو…', 'irp' ),
				'noResults'     => __( 'محصولی یافت نشد.', 'irp' ),
				'error'         => __( 'خطا در ارتباط با سرور.', 'irp' ),
				'added'         => __( 'اضافه شده', 'irp' ),
				'selectTwo'     => __( 'برای گروه‌بندی حداقل دو محصولِ تکی را انتخاب کنید.', 'irp' ),
				'chooseHeading' => __( 'انتخاب هدینگ…', 'irp' ),
				'noHeadings'    => __( 'ابتدا مقاله را ذخیره کنید تا هدینگ‌ها شناسایی شوند.', 'irp' ),
				'copy'          => __( 'کپی', 'irp' ),
				'copied'        => __( 'کپی شد!', 'irp' ),
				'placement'     => __( 'جایگاه نمایش:', 'irp' ),
				'manual'        => __( 'شورتکد (دستی)', 'irp' ),
				'auto'          => __( 'درج خودکار بعد از هدینگ', 'irp' ),
				'remove'        => __( 'حذف', 'irp' ),
				'selectGroup'   => __( 'انتخاب برای گروه', 'irp' ),
				'single'        => __( 'کارت تکی', 'irp' ),
				'group'         => __( 'گروه', 'irp' ),
				'slider'        => __( 'اسلایدر', 'irp' ),
				'grid'          => __( 'گرید/لیست', 'irp' ),
				'ungroup'       => __( 'تفکیک گروه', 'irp' ),
				'empty'         => __( 'هنوز محصولی اضافه نشده است. از کادر بالا محصول جستجو و اضافه کنید.', 'irp' ),
			),
		) );
	}

	public function render( $post ) {
		wp_nonce_field( 'irp_save', 'irp_nonce' );

		$blocks = get_post_meta( $post->ID, IRP_META_KEY, true );
		if ( ! is_array( $blocks ) ) {
			$blocks = array();
		}
		$headings = $this->parse_headings( $post->post_content );
		$initial  = $this->initial_products( $blocks );
		$flags    = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
		?>
		<div id="irp-app" class="irp-app" dir="rtl">
			<div class="irp-search">
				<input type="text" class="irp-search__input" autocomplete="off"
					placeholder="<?php esc_attr_e( 'جستجوی محصول بر اساس نام یا SKU…', 'irp' ); ?>">
				<div class="irp-search__results" hidden></div>
			</div>

			<div class="irp-toolbar">
				<span class="irp-toolbar__hint"><?php esc_html_e( 'برای ساخت گروه، چند محصول تکی را تیک بزنید و حالت نمایش را انتخاب کنید:', 'irp' ); ?></span>
				<select class="irp-toolbar__layout irp-field">
					<option value="slider"><?php esc_html_e( 'اسلایدر', 'irp' ); ?></option>
					<option value="grid"><?php esc_html_e( 'گرید/لیست', 'irp' ); ?></option>
				</select>
				<button type="button" class="button irp-toolbar__group"><?php esc_html_e( 'گروه‌بندی انتخاب‌شده‌ها', 'irp' ); ?></button>
			</div>

			<div class="irp-blocks"></div>

			<input type="hidden" name="irp_blocks" id="irp-blocks-input" value="<?php echo esc_attr( wp_json_encode( $blocks ) ); ?>">
			<script type="application/json" id="irp-headings"><?php echo wp_json_encode( $headings, $flags ); ?></script>
			<script type="application/json" id="irp-initial-products"><?php echo wp_json_encode( $initial, $flags ); ?></script>
		</div>
		<?php
	}

	/** استخراج هدینگ‌های H2 تا H4 از محتوای ذخیره‌شده. */
	private function parse_headings( $content ) {
		$headings = array();
		if ( ! $content ) {
			return $headings;
		}
		if ( preg_match_all( '/<h([2-4])[^>]*>(.*?)<\/h\1>/is', $content, $m, PREG_SET_ORDER ) ) {
			$i = 0;
			foreach ( $m as $match ) {
				$i++;
				$headings[] = array(
					'index' => $i,
					'level' => (int) $match[1],
					'text'  => wp_strip_all_tags( $match[2] ),
				);
			}
		}
		return $headings;
	}

	/** اطلاعات نمایشی محصولات موجود در بلوک‌ها برای رندر اولیه‌ی رابط کاربری. */
	private function initial_products( $blocks ) {
		$ids = array();
		foreach ( $blocks as $b ) {
			if ( ! empty( $b['products'] ) && is_array( $b['products'] ) ) {
				foreach ( $b['products'] as $id ) {
					$ids[] = (int) $id;
				}
			}
		}
		$ids = array_values( array_unique( $ids ) );

		$map = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$img_id = $product->get_image_id();
			$thumb  = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
			$map[ $id ] = array(
				'title' => $product->get_name(),
				'thumb' => $thumb,
				'price' => $product->get_price_html(),
			);
		}
		return $map;
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['irp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['irp_nonce'] ), 'irp_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return;
		}

		if ( empty( $_POST['irp_blocks'] ) ) {
			delete_post_meta( $post_id, IRP_META_KEY );
			return;
		}

		$decoded = json_decode( wp_unslash( $_POST['irp_blocks'] ), true );
		$clean   = $this->sanitize_blocks( $decoded );

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, IRP_META_KEY );
		} else {
			update_post_meta( $post_id, IRP_META_KEY, $clean );
		}
	}

	/** پاکسازی و اعتبارسنجی ساختار بلوک‌ها پیش از ذخیره. */
	private function sanitize_blocks( $blocks ) {
		$clean = array();
		if ( ! is_array( $blocks ) ) {
			return $clean;
		}
		foreach ( $blocks as $b ) {
			if ( empty( $b['products'] ) || ! is_array( $b['products'] ) ) {
				continue;
			}
			$products = array_values( array_unique( array_filter( array_map( 'absint', $b['products'] ) ) ) );
			if ( empty( $products ) ) {
				continue;
			}

			$type = ( isset( $b['type'] ) && 'group' === $b['type'] ) ? 'group' : 'single';
			if ( 'single' === $type ) {
				$products = array( $products[0] );
			}

			$layout = 'card';
			if ( 'group' === $type ) {
				$layout = ( isset( $b['layout'] ) && 'grid' === $b['layout'] ) ? 'grid' : 'slider';
			}

			$placement = ( isset( $b['placement'] ) && 'auto' === $b['placement'] ) ? 'auto' : 'manual';
			$heading   = isset( $b['heading'] ) ? absint( $b['heading'] ) : 0;
			$key       = isset( $b['key'] ) ? sanitize_key( $b['key'] ) : '';
			if ( ! $key ) {
				$key = 'irp' . wp_generate_password( 6, false, false );
			}

			$clean[] = array(
				'key'       => $key,
				'type'      => $type,
				'products'  => $products,
				'layout'    => $layout,
				'placement' => $placement,
				'heading'   => $heading,
			);
		}
		return $clean;
	}
}
