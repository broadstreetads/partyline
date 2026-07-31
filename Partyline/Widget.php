<?php
/**
 * "Partyline Posts" widget + [partyline_posts] shortcode.
 *
 * A deliberately simple way to surface recent Partyline submissions on a site.
 * It works like the core Recent Posts widget, but is pre-scoped to the Partyline
 * category chosen in settings (so publishers don't have to know or pick it) and
 * can show the featured image (which, for video Partylines, is the play-badged
 * poster frame).
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Partyline_Widget' ) ):

class Partyline_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'partyline_posts',
			__( 'Partyline Posts', 'partyline' ),
			array(
				'description'                 => __( 'Show your most recent Partyline submissions.', 'partyline' ),
				'classname'                   => 'partyline-widget',
				'customize_selective_refresh' => true,
			)
		);
	}

	/** Register the widget and the shortcode. */
	public static function init() {
		add_action( 'widgets_init', array( __CLASS__, 'register' ) );
		add_shortcode( 'partyline_posts', array( __CLASS__, 'shortcode' ) );
	}

	public static function register() {
		register_widget( 'Partyline_Widget' );
	}

	/** Field defaults shared by the widget and shortcode. */
	private static function defaults() {
		return array(
			'title'      => __( 'Partyline', 'partyline' ),
			'number'     => 5,
			'show_thumb' => 1,
			'show_date'  => 1,
		);
	}

	/** The Partyline category ID from settings (0 if none set). */
	private static function category_id() {
		$s = Partyline_Utility::getSettings();
		return isset( $s->partyline_category ) ? (int) $s->partyline_category : 0;
	}

	/**
	 * Render the list of recent Partyline posts. Returns HTML (already escaped),
	 * or '' when there's nothing to show.
	 */
	public static function render( $atts ) {
		$atts   = wp_parse_args( $atts, self::defaults() );
		$number = max( 1, min( 20, (int) $atts['number'] ) );
		$cat    = self::category_id();

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $number,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( $cat ) {
			$args['cat'] = $cat;
		}
		$q = new WP_Query( $args );

		if ( ! $q->have_posts() ) {
			wp_reset_postdata();
			// Nudge admins if the category simply isn't configured yet.
			if ( ! $cat && current_user_can( 'manage_options' ) ) {
				return '<p class="partyline-widget__empty">'
					. esc_html__( 'Choose a Partyline category in Partyline → Settings to show submissions here.', 'partyline' )
					. '</p>';
			}
			return '';
		}

		$show_thumb = ! empty( $atts['show_thumb'] );
		$show_date  = ! empty( $atts['show_date'] );

		$out  = self::styles();
		$out .= '<ul class="partyline-widget__list">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$out .= '<li class="partyline-widget__item">';
			$out .= '<a class="partyline-widget__link" href="' . esc_url( get_permalink() ) . '">';
			if ( $show_thumb && has_post_thumbnail() ) {
				$out .= '<span class="partyline-widget__thumb">'
					. get_the_post_thumbnail( get_the_ID(), 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) )
					. '</span>';
			}
			$out .= '<span class="partyline-widget__text">';
			$out .= '<span class="partyline-widget__title">' . esc_html( get_the_title() ) . '</span>';
			if ( $show_date ) {
				$out .= '<time class="partyline-widget__date" datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>';
			}
			$out .= '</span></a></li>';
		}
		$out .= '</ul>';
		wp_reset_postdata();

		return $out;
	}

	/** Minimal, self-contained styles, printed once per page. */
	private static function styles() {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;
		return '<style>'
			. '.partyline-widget__list{list-style:none;margin:0;padding:0}'
			. '.partyline-widget__item{margin:0 0 12px}'
			. '.partyline-widget__item:last-child{margin-bottom:0}'
			. '.partyline-widget__link{display:flex;gap:10px;align-items:center;text-decoration:none}'
			. '.partyline-widget__thumb{flex:0 0 auto;width:56px;height:56px;border-radius:8px;overflow:hidden;background:#eee}'
			. '.partyline-widget__thumb img{width:56px;height:56px;object-fit:cover;display:block}'
			. '.partyline-widget__text{display:flex;flex-direction:column;min-width:0}'
			. '.partyline-widget__title{font-weight:600;line-height:1.3}'
			. '.partyline-widget__date{font-size:12px;opacity:.7;margin-top:2px}'
			. '</style>';
	}

	/** Shortcode: [partyline_posts number="6" show_thumb="1" show_date="1" title="Around town"] */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'      => '',
				'number'     => 5,
				'show_thumb' => 1,
				'show_date'  => 1,
			),
			$atts,
			'partyline_posts'
		);

		$list = self::render( $atts );
		if ( '' === $list ) {
			return '';
		}

		$html = '<div class="partyline-widget">';
		if ( '' !== trim( (string) $atts['title'] ) ) {
			$html .= '<h3 class="partyline-widget__heading">' . esc_html( $atts['title'] ) . '</h3>';
		}
		$html .= $list . '</div>';
		return $html;
	}

	/** Front-end widget output. */
	public function widget( $args, $instance ) {
		$inst = wp_parse_args( (array) $instance, self::defaults() );
		$body = self::render( $inst );
		if ( '' === $body ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$title = apply_filters( 'widget_title', $inst['title'], $inst, $this->id_base );
		if ( '' !== trim( (string) $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Admin form. */
	public function form( $instance ) {
		$inst = wp_parse_args( (array) $instance, self::defaults() );
		$cat  = self::category_id();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'partyline' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $inst['title'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of Partylines to show:', 'partyline' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" max="20" value="<?php echo esc_attr( $inst['number'] ); ?>" size="3">
		</p>
		<p>
			<input class="checkbox" type="checkbox"<?php checked( $inst['show_thumb'] ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_thumb' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_thumb' ) ); ?>">
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_thumb' ) ); ?>"><?php esc_html_e( 'Show featured image', 'partyline' ); ?></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox"<?php checked( $inst['show_date'] ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_date' ) ); ?>">
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>"><?php esc_html_e( 'Display post date', 'partyline' ); ?></label>
		</p>
		<p style="color:#646970;">
			<?php if ( $cat ): ?>
				<?php
				printf(
					/* translators: %s: category name */
					esc_html__( 'Showing posts from your Partyline category (%s).', 'partyline' ),
					'<strong>' . esc_html( get_cat_name( $cat ) ) . '</strong>'
				);
				?>
			<?php else: ?>
				<?php esc_html_e( 'No Partyline category is set yet. Choose one in Partyline → Settings so this widget knows what to show.', 'partyline' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	/** Save handler. */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'      => sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' ),
			'number'     => max( 1, min( 20, (int) ( isset( $new_instance['number'] ) ? $new_instance['number'] : 5 ) ) ),
			'show_thumb' => empty( $new_instance['show_thumb'] ) ? 0 : 1,
			'show_date'  => empty( $new_instance['show_date'] ) ? 0 : 1,
		);
	}
}

endif;
