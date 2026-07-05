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
			if ( 'group' === ( isset( $b['type'] ) ? $b['type'] : 'single' ) && $this->has_slider( $b ) ) {
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
			if ( isset( $b['key'] ) && $b['key'] === $key ) {
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
		if ( ! $block || ( isset( $block['placement'] ) && 'auto' === $block['placement'] ) ) {
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
			if ( isset( $b['placement'] ) && 'auto' === $b['placement'] && ! empty( $b['heading'] ) && $b['heading'] > 0 ) {
				$auto[ (int) $b['heading'] ][] = $b;
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
		$type = ( isset( $block['type'] ) && 'group' === $block['type'] ) ? 'group' : 'single';
		if ( empty( $block['products'] ) || ! is_array( $block['products'] ) ) {
			return '';
		}
		$r    = $this->resolve( $block );
		$key  = isset( $block['key'] ) ? sanitize_html_class( $block['key'] ) : 'x';
		$bcls = 'irp-b-' . $key;

		// عناصری که در هیچ بریک‌پوینتی نمایش داده نمی‌شوند اصلاً وارد DOM نمی‌شوند.
		$vis = array(
			'image'  => $r['d']['showImage']  || $r['t']['showImage']  || $r['m']['showImage'],
			'desc'   => $r['d']['showDesc']   || $r['t']['showDesc']   || $r['m']['showDesc'],
			'price'  => $r['d']['showPrice']  || $r['t']['showPrice']  || $r['m']['showPrice'],
			'button' => $r['d']['showButton'] || $r['t']['showButton'] || $r['m']['showButton'],
		);

		$css = $this->block_css( $bcls, $type, $r, $vis );

		if ( 'group' === $type ) {
			$slider = $this->has_slider( $block );
			$items  = '';
			foreach ( $block['products'] as $id ) {
				$card = $this->card_html( $id, $vis );
				if ( $card ) {
					$items .= '<li class="irp-list__item">' . $card . '</li>';
				}
			}
			if ( '' === $items ) {
				return '';
			}
			$out  = $css;
			$out .= '<div class="irp-wrap irp-group ' . esc_attr( $bcls ) . '"' . ( $slider ? ' data-irp-slider' : '' ) . '>';
			if ( $slider ) {
				$prev = esc_attr__( 'قبلی', 'irp' );
				$next = esc_attr__( 'بعدی', 'irp' );
				// در RTL: «قبلی» به راست و «بعدی» به چپ اشاره می‌کند.
				$out .= '<button type="button" class="irp-slider__nav irp-slider__prev" aria-label="' . $prev . '"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
			}
			$out .= '<ul class="irp-list">' . $items . '</ul>';
			if ( $slider ) {
				$out .= '<button type="button" class="irp-slider__nav irp-slider__next" aria-label="' . $next . '"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
			}
			$out .= '</div>';
			return $out;
		}

		// کارت تکی
		$card = $this->card_html( $block['products'][0], $vis );
		if ( '' === $card ) {
			return '';
		}
		return $css . '<div class="irp-wrap irp-single ' . esc_attr( $bcls ) . '">' . $card . '</div>';
	}

	private function bp_defaults() {
		return array( 'mode' => 'slider', 'slides' => 3, 'columns' => 2, 'listDir' => 'h', 'cardDir' => 'h', 'showImage' => true, 'showDesc' => true, 'showPrice' => true, 'showButton' => true, 'fsTitle' => 0, 'fsPrice' => 0, 'fsDel' => 0, 'fsBtn' => 0 );
	}

	/** تبدیل ساختار تختِ قدیمی به مدل دستگاهی. */
	private function upgrade( $block ) {
		if ( isset( $block['d'] ) && is_array( $block['d'] ) ) {
			return $block;
		}
		$d = array(
			'mode'       => ( isset( $block['layout'] ) && 'grid' === $block['layout'] ) ? 'grid' : 'slider',
			'slides'     => isset( $block['slidesDesktop'] ) ? (int) $block['slidesDesktop'] : 3,
			'columns'    => isset( $block['columns'] ) ? (int) $block['columns'] : 2,
			'listDir'    => ( isset( $block['listDir'] ) && 'v' === $block['listDir'] ) ? 'v' : 'h',
			'cardDir'    => ( isset( $block['cardDir'] ) && 'v' === $block['cardDir'] ) ? 'v' : 'h',
			'showImage'  => array_key_exists( 'showImage', $block )  ? (bool) $block['showImage']  : true,
			'showDesc'   => array_key_exists( 'showDesc', $block )   ? (bool) $block['showDesc']   : true,
			'showPrice'  => array_key_exists( 'showPrice', $block )  ? (bool) $block['showPrice']  : true,
			'showButton' => array_key_exists( 'showButton', $block ) ? (bool) $block['showButton'] : true,
		);
		$m = array();
		if ( isset( $block['slidesMobile'] ) && (int) $block['slidesMobile'] !== $d['slides'] ) {
			$m['slides'] = (int) $block['slidesMobile'];
		}
		return array( 'd' => $d, 't' => array(), 'm' => $m ) + $block;
	}

	/** ترکیب override‌های یک بریک‌پوینت روی مقادیر والد. */
	private function clean_bp( $bp, $parent ) {
		$bp  = is_array( $bp ) ? $bp : array();
		$out = array();
		foreach ( $parent as $k => $pv ) {
			if ( ! array_key_exists( $k, $bp ) ) {
				$out[ $k ] = $pv;
				continue;
			}
			$v = $bp[ $k ];
			switch ( $k ) {
				case 'mode':
					$out[ $k ] = ( 'grid' === $v ) ? 'grid' : 'slider';
					break;
				case 'listDir':
				case 'cardDir':
					$out[ $k ] = ( 'v' === $v ) ? 'v' : 'h';
					break;
				case 'slides':
				case 'columns':
					$out[ $k ] = min( 6, max( 1, (int) $v ) );
					break;
				case 'fsTitle':
				case 'fsPrice':
				case 'fsDel':
				case 'fsBtn':
					$n = (int) $v;
					$out[ $k ] = $n ? min( 60, max( 10, $n ) ) : 0;
					break;
				default:
					$out[ $k ] = (bool) $v;
			}
		}
		return $out;
	}

	/** حل نهایی سه بریک‌پوینت با ارث‌بری m ← t ← d. */
	private function resolve( $block ) {
		$block = $this->upgrade( $block );
		$d = $this->clean_bp( isset( $block['d'] ) ? $block['d'] : array(), $this->bp_defaults() );
		$t = $this->clean_bp( isset( $block['t'] ) ? $block['t'] : array(), $d );
		$m = $this->clean_bp( isset( $block['m'] ) ? $block['m'] : array(), $t );
		return array( 'd' => $d, 't' => $t, 'm' => $m );
	}

	/** آیا در هر یک از بریک‌پوینت‌ها حالت اسلایدر فعال است؟ */
	private function has_slider( $block ) {
		$r = $this->resolve( $block );
		return 'slider' === $r['d']['mode'] || 'slider' === $r['t']['mode'] || 'slider' === $r['m']['mode'];
	}

	/** CSS چیدمان لیست/آیتم برای یک بریک‌پوینت. */
	private function css_list( $sel, $bp ) {
		if ( ! is_array( $bp ) ) {
			return '';
		}
		if ( 'slider' === $bp['mode'] ) {
			$s = max( 1, (int) $bp['slides'] );
			return $sel . ' .irp-list{display:flex;grid-template-columns:none;overflow-x:auto}'
				. $sel . ' .irp-list__item{flex:0 0 calc((100% - (' . $s . ' - 1) * 14px) / ' . $s . ');max-width:100%}';
		}
		$cols = ( 'v' === $bp['listDir'] ) ? 1 : max( 1, (int) $bp['columns'] );
		return $sel . ' .irp-list{display:grid;grid-template-columns:repeat(' . $cols . ',minmax(0,1fr));overflow-x:visible}'
			. $sel . ' .irp-list__item{flex:0 0 auto;max-width:none}';
	}

	/** CSS جهت کارت (افقی/عمودی) و مدیا. */
	private function css_card( $sel, $bp ) {
		if ( ! is_array( $bp ) ) {
			return '';
		}
		if ( 'v' === $bp['cardDir'] ) {
			return $sel . ' .irp-card{flex-direction:column}'
				. $sel . ' .irp-card__media{flex:0 0 auto;width:100%;height:auto;aspect-ratio:4/3}';
		}
		return $sel . ' .irp-card{flex-direction:row}'
			. $sel . ' .irp-card__media{flex:0 0 clamp(88px,26vw,118px);width:clamp(88px,26vw,118px);height:auto;aspect-ratio:1}';
	}

	/** CSS یک لایه (tier): فقط قواعدی که با والد فرق دارند. */
	private function tier_css( $sel, $type, $bp, $parent, $vis ) {
		$out   = '';
		$first = ( null === $parent );

		if ( 'group' === $type ) {
			$list = $this->css_list( $sel, $bp );
			if ( $first || $list !== $this->css_list( $sel, $parent ) ) {
				$out .= $list;
			}
			$nav  = $sel . ' .irp-slider__nav{display:' . ( 'slider' === $bp['mode'] ? 'flex' : 'none' ) . '}';
			$pnav = $first ? '' : $sel . ' .irp-slider__nav{display:' . ( 'slider' === $parent['mode'] ? 'flex' : 'none' ) . '}';
			if ( $first || $nav !== $pnav ) {
				$out .= $nav;
			}
		}

		$card = $this->css_card( $sel, $bp );
		if ( $first || $card !== $this->css_card( $sel, $parent ) ) {
			$out .= $card;
		}

		$map = array(
			array( 'opt' => 'showImage', 'vis' => 'image', 'sel' => ' .irp-card__media', 'show' => 'display:block' ),
			array( 'opt' => 'showDesc', 'vis' => 'desc', 'sel' => ' .irp-card__desc', 'show' => 'display:-webkit-box' ),
			array( 'opt' => 'showPrice', 'vis' => 'price', 'sel' => ' .irp-card__price', 'show' => 'display:flex' ),
			array( 'opt' => 'showButton', 'vis' => 'button', 'sel' => ' .irp-card__btn', 'show' => 'display:inline-flex' ),
		);
		foreach ( $map as $c ) {
			if ( empty( $vis[ $c['vis'] ] ) ) {
				continue;
			}
			$cur = $sel . $c['sel'] . '{' . ( $bp[ $c['opt'] ] ? $c['show'] : 'display:none' ) . '}';
			$par = $first ? '' : $sel . $c['sel'] . '{' . ( $parent[ $c['opt'] ] ? $c['show'] : 'display:none' ) . '}';
			if ( $first || $cur !== $par ) {
				$out .= $cur;
			}
		}

		$fonts = array(
			array( 'opt' => 'fsTitle', 'sel' => ' .irp-card__title' ),
			array( 'opt' => 'fsPrice', 'sel' => ' .irp-card__price' ),
			array( 'opt' => 'fsDel', 'sel' => ' .irp-card__price del' ),
			array( 'opt' => 'fsBtn', 'sel' => ' .irp-card__btn' ),
		);
		foreach ( $fonts as $f ) {
			$cv  = (int) $bp[ $f['opt'] ];
			$cur = $cv > 0 ? $sel . $f['sel'] . '{font-size:' . $cv . 'px}' : '';
			$pv  = $first ? 0 : (int) $parent[ $f['opt'] ];
			$par = $pv > 0 ? $sel . $f['sel'] . '{font-size:' . $pv . 'px}' : '';
			if ( '' !== $cur && $cur !== $par ) {
				$out .= $cur;
			}
		}

		return $out;
	}

	/** ساخت بلوک <style> اسکوپ‌شده با سه لایه‌ی دسکتاپ/تبلت/موبایل. */
	private function block_css( $bcls, $type, $r, $vis ) {
		$sel = '.' . $bcls;
		$css = $this->tier_css( $sel, $type, $r['d'], null, $vis );
		$t   = $this->tier_css( $sel, $type, $r['t'], $r['d'], $vis );
		if ( '' !== $t ) {
			$css .= '@media (max-width:1024px){' . $t . '}';
		}
		$m = $this->tier_css( $sel, $type, $r['m'], $r['t'], $vis );
		if ( '' !== $m ) {
			$css .= '@media (max-width:600px){' . $m . '}';
		}
		if ( '' === $css ) {
			return '';
		}
		return '<style>' . $css . '</style>';
	}

	/** مارکآپ کارت محصول. عناصری که در هیچ دستگاهی دیده نمی‌شوند ($vis) اصلاً رندر نمی‌شوند؛ نمایش/مخفی در هر بریک‌پوینت را CSS درون‌خطی بلوک کنترل می‌کند. */
	private function card_html( $product_id, $vis ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return '';
		}
		$url   = get_permalink( $product->get_id() );
		$title = $product->get_name();

		$media = '';
		if ( ! empty( $vis['image'] ) ) {
			$img   = $product->get_image( 'woocommerce_thumbnail', array(
				'class'   => 'irp-img',
				'loading' => 'lazy',
				'alt'     => $title,
			) );
			$media = '<a class="irp-card__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
		}

		$desc = '';
		if ( ! empty( $vis['desc'] ) ) {
			$raw_desc = $product->get_short_description();
			if ( ! $raw_desc ) {
				$raw_desc = $product->get_description();
			}
			$desc = $raw_desc ? wp_trim_words( wp_strip_all_tags( $raw_desc ), 18, '…' ) : '';
		}

		$price       = ! empty( $vis['price'] ) ? $product->get_price_html() : '';
		$show_button = ! empty( $vis['button'] );
		$show_footer = ( '' !== $price ) || $show_button;

		ob_start();
		?>
		<article class="irp-card">
			<?php echo $media; // خروجی get_image توسط ووکامرس escape می‌شود ?>
			<div class="irp-card__body">
				<div class="irp-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></div>
				<?php if ( $desc ) : ?>
					<p class="irp-card__desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
				<?php if ( $show_footer ) : ?>
					<div class="irp-card__footer">
						<?php if ( $price ) : ?>
							<div class="irp-card__price"><?php echo wp_kses_post( $price ); ?></div>
						<?php endif; ?>
						<?php if ( $show_button ) : ?>
							<a class="irp-card__btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'مشاهده و خرید', 'irp' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
