<?php
/**
 * "Partyline Call to Action" widget + [partyline_cta] shortcode.
 *
 * A small promo box that invites readers to become contributors — an evocative
 * quote ("That should be on <your site>!"), a line of social proof showing how
 * many Partyliners have already signed up, and a button to join. Drop it in a
 * sidebar, a column, or anywhere via the shortcode.
 *
 * Every piece of copy is customizable. The reader count is pulled live from the
 * number of Partyliners (via Partyline_Utility::countPartyliners()) unless a
 * number is set explicitly. Tokens {count} and {site} are replaced in the
 * headline and body.
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Partyline_Cta_Widget' ) ):

class Partyline_Cta_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'partyline_cta',
			__( 'Partyline Call to Action', 'partyline' ),
			array(
				'description'                 => __( 'Invite readers to become Partyliners, with a live count of how many have signed up.', 'partyline' ),
				'classname'                   => 'partyline-cta-widget',
				'customize_selective_refresh' => true,
			)
		);
	}

	/** Register the widget + shortcode, and keep the cached count fresh. */
	public static function init() {
		add_action( 'widgets_init', array( __CLASS__, 'register' ) );
		add_shortcode( 'partyline_cta', array( __CLASS__, 'shortcode' ) );

		// Bust the cached Partyliner count when the roster changes.
		add_action( 'user_register', array( 'Partyline_Utility', 'clearPartylinerCount' ) );
		add_action( 'deleted_user', array( 'Partyline_Utility', 'clearPartylinerCount' ) );
		add_action( 'set_user_role', array( 'Partyline_Utility', 'clearPartylinerCount' ) );
	}

	public static function register() {
		register_widget( 'Partyline_Cta_Widget' );
	}

	/** Field defaults shared by the widget and shortcode. */
	private static function defaults() {
		return array(
			/* translators: {site} becomes the site name. Keep the quotation marks. */
			'headline' => __( '&ldquo;That should be on {site}!&rdquo;', 'partyline' ),
			/* translators: {count} becomes the number of Partyliners, {site} the site name. */
			'body'     => __( "If you've ever thought that, join the {count} readers who've signed up to post their own stories on {site}.", 'partyline' ),
			'button'   => __( 'Become a Partyliner', 'partyline' ),
			'count'    => '', // blank = use the live Partyliner count
			'min'      => '', // optional floor so a small site still reads well
			'url'      => '', // blank = apply page (or the app when applications are closed)
		);
	}

	/** Where "join" points: the apply page if open, otherwise the app. */
	private static function joinUrl() {
		if ( class_exists( 'Partyline_Pwa' ) ) {
			if ( method_exists( 'Partyline_Pwa', 'applyEnabled' ) && Partyline_Pwa::applyEnabled() ) {
				return Partyline_Pwa::applyUrl();
			}
			if ( method_exists( 'Partyline_Pwa', 'appUrl' ) ) {
				return Partyline_Pwa::appUrl();
			}
		}
		return home_url( '/partyline' );
	}

	/**
	 * Build the CTA markup (already escaped). Returns '' only in the rare case
	 * there's genuinely nothing to show (empty headline, body and button).
	 */
	public static function render( $atts ) {
		$atts = wp_parse_args( $atts, self::defaults() );

		$site = Partyline_Utility::siteName();

		// Reader count: explicit override wins, else the live Partyliner count.
		if ( '' !== (string) $atts['count'] && is_numeric( $atts['count'] ) ) {
			$count = (int) $atts['count'];
		} else {
			$count = Partyline_Utility::countPartyliners();
		}
		if ( '' !== (string) $atts['min'] && is_numeric( $atts['min'] ) && $count < (int) $atts['min'] ) {
			$count = (int) $atts['min'];
		}
		$count_str = number_format_i18n( $count );

		// Headline: plain text with {site}/{count} tokens. wp_kses with no allowed
		// tags strips any markup (XSS-safe for shortcode-supplied values) while
		// preserving entities like the curly quotes in the default headline.
		$headline = str_replace(
			array( '{site}', '{count}' ),
			array( $site, $count_str ),
			(string) $atts['headline']
		);
		$headline = wp_kses( $headline, array() );

		// Body: escape the template around the tokens so {count} can be bolded.
		$body_tpl = str_replace( '{site}', $site, (string) $atts['body'] );
		$segments = explode( '{count}', $body_tpl );
		$body     = '';
		$last     = count( $segments ) - 1;
		foreach ( $segments as $i => $seg ) {
			$body .= esc_html( $seg );
			if ( $i < $last ) {
				$body .= '<strong class="pl-cta-count">' . esc_html( $count_str ) . '</strong>';
			}
		}

		$button = trim( (string) $atts['button'] );
		$url    = '' !== trim( (string) $atts['url'] ) ? $atts['url'] : self::joinUrl();

		if ( '' === trim( wp_strip_all_tags( $headline ) ) && '' === trim( wp_strip_all_tags( $body ) ) && '' === $button ) {
			return '';
		}

		$out  = self::styles();
		$out .= '<div class="pl-cta">';
		if ( '' !== trim( wp_strip_all_tags( $headline ) ) ) {
			$out .= '<div class="pl-cta-quote">' . $headline . '</div>';
		}
		if ( '' !== trim( wp_strip_all_tags( $body ) ) ) {
			$out .= '<p class="pl-cta-body">' . $body . '</p>';
		}
		if ( '' !== $button ) {
			$out .= '<a class="pl-cta-btn" href="' . esc_url( $url ) . '">'
				. esc_html( $button ) . ' <span aria-hidden="true">&rarr;</span></a>';
		}
		$out .= '</div>';

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
			. '.pl-cta{background:#f3f6f3;border:1px solid #e1e8e1;border-radius:12px;padding:18px 18px 20px;margin:0 0 20px}'
			. '.pl-cta-quote{font-size:21px;line-height:1.2;font-weight:800;letter-spacing:-.01em;color:#16321b;margin:0 0 9px}'
			. '.pl-cta-body{font-size:14px;line-height:1.5;color:#3a3f3a;margin:0 0 15px}'
			. '.pl-cta-count{font-weight:800;color:#1e6228}'
			. '.pl-cta-btn{display:inline-flex;align-items:center;gap:6px;background:#1e6228;color:#fff;'
			. 'text-decoration:none;font-weight:700;font-size:14px;line-height:1;padding:11px 18px;border-radius:999px;transition:background .15s ease}'
			. '.pl-cta-btn:hover,.pl-cta-btn:focus{background:#184f20;color:#fff;text-decoration:none}'
			. '</style>';
	}

	/**
	 * Shortcode:
	 * [partyline_cta headline="…" body="…join {count} readers on {site}…" button="Join" count="" min="" url=""]
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( self::defaults(), $atts, 'partyline_cta' );
		return self::render( $atts );
	}

	/** Front-end widget output. */
	public function widget( $args, $instance ) {
		$inst = wp_parse_args( (array) $instance, self::defaults() );
		$body = self::render( $inst );
		if ( '' === $body ) {
			return;
		}
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Admin form. */
	public function form( $instance ) {
		$inst = wp_parse_args( (array) $instance, self::defaults() );
		$live = Partyline_Utility::countPartyliners();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'headline' ) ); ?>"><?php esc_html_e( 'Headline:', 'partyline' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'headline' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'headline' ) ); ?>" type="text" value="<?php echo esc_attr( $inst['headline'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'body' ) ); ?>"><?php esc_html_e( 'Body:', 'partyline' ); ?></label>
			<textarea class="widefat" rows="3" id="<?php echo esc_attr( $this->get_field_id( 'body' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'body' ) ); ?>"><?php echo esc_textarea( $inst['body'] ); ?></textarea>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'button' ) ); ?>"><?php esc_html_e( 'Button label:', 'partyline' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'button' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'button' ) ); ?>" type="text" value="<?php echo esc_attr( $inst['button'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Reader count (leave blank to use the live count):', 'partyline' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" step="1" min="0" value="<?php echo esc_attr( $inst['count'] ); ?>" size="5">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'min' ) ); ?>"><?php esc_html_e( 'Minimum to show (optional floor):', 'partyline' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'min' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'min' ) ); ?>" type="number" step="1" min="0" value="<?php echo esc_attr( $inst['min'] ); ?>" size="5">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'url' ) ); ?>"><?php esc_html_e( 'Button link (leave blank for the Partyline app/apply page):', 'partyline' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'url' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'url' ) ); ?>" type="text" value="<?php echo esc_attr( $inst['url'] ); ?>">
		</p>
		<p style="color:#646970;">
			<?php
			printf(
				/* translators: %d: live Partyliner count */
				esc_html__( 'Live count right now: %d Partyliner(s). Use {count} and {site} in the headline or body.', 'partyline' ),
				(int) $live
			);
			?>
		</p>
		<?php
	}

	/** Save handler. */
	public function update( $new_instance, $old_instance ) {
		$count = isset( $new_instance['count'] ) ? trim( (string) $new_instance['count'] ) : '';
		$min   = isset( $new_instance['min'] ) ? trim( (string) $new_instance['min'] ) : '';
		$url   = isset( $new_instance['url'] ) ? trim( (string) $new_instance['url'] ) : '';
		return array(
			'headline' => sanitize_text_field( isset( $new_instance['headline'] ) ? $new_instance['headline'] : '' ),
			'body'     => sanitize_textarea_field( isset( $new_instance['body'] ) ? $new_instance['body'] : '' ),
			'button'   => sanitize_text_field( isset( $new_instance['button'] ) ? $new_instance['button'] : '' ),
			'count'    => ( '' !== $count && is_numeric( $count ) ) ? (string) max( 0, (int) $count ) : '',
			'min'      => ( '' !== $min && is_numeric( $min ) ) ? (string) max( 0, (int) $min ) : '',
			'url'      => '' !== $url ? esc_url_raw( $url ) : '',
		);
	}
}

endif;
