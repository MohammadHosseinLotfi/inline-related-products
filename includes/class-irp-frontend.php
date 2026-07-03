<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IRP_Frontend {

	public function __construct() {
		add_shortcode( 'irp', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'auto_insert' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/** بارگذاری CSS/JS فقط در صفحاتی که واقعاً بلوک دارند. */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$blocks = $this->get_blocks( $post->ID );
		if ( empty( $blocks ) ) {
			return;
		}

		wp_enqueue_style( 'irp-frontend', IRP_URL . 'assets/frontend.css', array(), IRP_VERSION );

		foreach ( $blocks as $b ) {
			if ( 'group' === $b['type'] && 'slider' === $b['layout'] ) {
				wp_enqueue_script( 'irp-slider', IRP_URL . 'assets/slider.js', array(), IRP_VERSION, true );
				break;
			}
		}
	}

	private function get_blocks( $post_id ) {
		$blocks = get_post_meta( $post_id, IRP_META_KEY, true );
		return is_array( $blocks ) ? $blocks : array();
	}

	private function find_block( $key ) {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}
		foreach ( $this->get_blocks( $post->ID ) as $b ) {
			if ( $b['key'] === $key ) {
				return $b;
			}
		}
		return null;
	}

	/** شورتکد [irp key="..."] — فقط برای بلوک‌های دستی (تا با درج خودکار دوباره نشود). */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'key' => '' ), $atts, 'irp' );
		$key  = sanitize_key( $atts['key'] );
		if ( ! $key ) {
			return '';
		}
		$block = $this->find_block( $key );
		if ( ! $block || 'auto' === $block['placement'] ) {
			return '';
		}
		return $this->render_block( $block );
	}

	/** درج خودکار بلوک‌های auto بعد از هدینگ موردنظر. */
	public function auto_insert( $content ) {
		if ( ! is_singular() || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}
		$blocks = $this->get_blocks( get_the_ID() );
		if ( empty( $blocks ) ) {
			return $content;
		}

		$auto = array();
		foreach ( $blocks as $b ) {
			if ( 'auto' === $b['placement'] && $b['heading'] > 0 ) {
				$auto[ $b['heading'] ][] = $b;
			}
		}
		if ( empty( $auto ) ) {
			return $content;
		}

		$counter = 0;
		$self    = $this;
		return preg_replace_callback(
			'/<h[2-4][^>]*>.*?<\/h[2-4]>/is',
			function ( $m ) use ( &$counter, $auto, $self ) {
				$counter++;
				$html = $m[0];
				if ( isset( $auto[ $counter ] ) ) {
					foreach ( $auto[ $counter ] as $b ) {
						$html .= $self->render_block( $b );
					}
				}
				return $html;
			},
			$content
		);
	}

	public function render_block( $block ) {
		if ( 'group' === $block['type'] ) {
			return 'slider' === $block['layout']
				? $this->render_slider( $block['products'] )
				: $this->render_grid( $block['products'] );
		}
		return $this->render_single( $block['products'][0] );
	}

	private function render_single( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return '';
		}
		return '<div class="irp-wrap irp-single">' . $this->card_html( $product ) . '</div>';
	}

	private function render_grid( $ids ) {
		$cards = '';
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p || 'publish' !== $p->get_status() ) {
				continue;
			}
			$cards .= '<li class="irp-grid__item">' . $this->card_html( $p ) . '</li>';
		}
		if ( ! $cards ) {
			return '';
		}
		return '<div class="irp-wrap irp-group"><ul class="irp-grid">' . $cards . '</ul></div>';
	}

	private function render_slider( $ids ) {
		$slides = '';
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p || 'publish' !== $p->get_status() ) {
				continue;
			}
			$slides .= '<li class="irp-slider__slide">' . $this->card_html( $p ) . '</li>';
		}
		if ( ! $slides ) {
			return '';
		}
		$prev = esc_attr__( 'قبلی', 'irp' );
		$next = esc_attr__( 'بعدی', 'irp' );
		return '<div class="irp-wrap irp-group irp-slider" data-irp-slider>'
			. '<button type="button" class="irp-slider__nav irp-slider__prev" aria-label="' . $prev . '">›</button>'
			. '<ul class="irp-slider__track">' . $slides . '</ul>'
			. '<button type="button" class="irp-slider__nav irp-slider__next" aria-label="' . $next . '">‹</button>'
			. '</div>';
	}

	/** مارکآپ کارت محصول: عکس راست، تایتل + توضیح، قیمت پایین‌چپ. */
	private function card_html( $product ) {
		$id    = $product->get_id();
		$url   = get_permalink( $id );
		$title = $product->get_name();
		$img   = $product->get_image( 'woocommerce_thumbnail', array(
			'class'   => 'irp-img',
			'loading' => 'lazy',
			'alt'     => $title,
		) );
		$raw_desc = $product->get_short_description();
		if ( ! $raw_desc ) {
			$raw_desc = $product->get_description();
		}
		$desc  = $raw_desc ? wp_trim_words( wp_strip_all_tags( $raw_desc ), 18, '…' ) : '';
		$price = $product->get_price_html();

		ob_start();
		?>
		<article class="irp-card">
			<a class="irp-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo $img; // توسط خود ووکامرس escape می‌شود ?>
			</a>
			<div class="irp-card__body">
				<div class="irp-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></div>
				<?php if ( $desc ) : ?>
					<p class="irp-card__desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
				<div class="irp-card__footer">
					<?php if ( $price ) : ?>
						<div class="irp-card__price"><?php echo wp_kses_post( $price ); ?></div>
					<?php endif; ?>
					<a class="irp-card__btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'مشاهده و خرید', 'irp' ); ?></a>
				</div>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
