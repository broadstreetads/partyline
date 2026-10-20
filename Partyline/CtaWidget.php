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
			'kicker'   => __( 'Partyline', 'partyline' ), // small label in the black header
			/* translators: {site} becomes the site name. Keep the quotation marks. */
			'headline' => __( '&ldquo;That should be on {site}!&rdquo;', 'partyline' ),
			/* translators: {count} becomes the number of Partyliners, {site} the site name. */
			'body'     => __( "If you've ever thought that, join the {count} readers who've signed up to post their own stories on {site}.", 'partyline' ),
			'button'   => __( 'Become a Partyliner', 'partyline' ),
			'count'    => '', // blank = use the live Partyliner count
			'min'      => '', // optional floor so a small site still reads well
			'url'      => '', // blank = apply page (or the app when applications are closed)
			'icon'     => '', // blank = the site icon; "none" = no logo; else a URL
			'compact'  => '', // force the stacked/compact layout (narrow columns auto-detect)
			'arrow'    => '', // show a down-arrow below the card (points at the Partylines beneath)
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
		$kicker = trim( (string) $atts['kicker'] );

		// Optional circular logo in the black header. Blank = the site icon (if
		// one is set); "none" = no logo; anything else = an explicit image URL.
		$icon = (string) $atts['icon'];
		if ( '' === $icon ) {
			$icon_url = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 112 ) : '';
		} elseif ( 'none' === strtolower( $icon ) ) {
			$icon_url = '';
		} else {
			$icon_url = $icon;
		}

		$compact = in_array( strtolower( (string) $atts['compact'] ), array( '1', 'yes', 'true', 'on' ), true );
		// "arrow" now draws a speech-bubble tail pointing down at the stories below.
		$tail = in_array( strtolower( (string) $atts['arrow'] ), array( '1', 'yes', 'true', 'on' ), true );

		$has_headline = '' !== trim( wp_strip_all_tags( $headline ) );
		$has_body     = '' !== trim( wp_strip_all_tags( $body ) );
		if ( ! $has_headline && ! $has_body && '' === $button ) {
			return '';
		}

		$out  = self::styles();
		$out .= '<div class="pl-cta' . ( $compact ? ' is-compact' : '' ) . ( $tail ? ' has-tail' : '' ) . '">';

		// Black header band: optional logo + kicker + the quote headline.
		if ( $has_headline || '' !== $kicker || '' !== $icon_url ) {
			$out .= '<div class="pl-cta-header">';
			if ( '' !== $icon_url ) {
				$out .= '<img class="pl-cta-logo" src="' . esc_url( $icon_url ) . '" alt="" width="56" height="56" loading="lazy" />';
			}
			$out .= '<div class="pl-cta-htext">';
			if ( '' !== $kicker ) {
				$out .= '<span class="pl-cta-kicker">' . esc_html( $kicker ) . '</span>';
			}
			if ( $has_headline ) {
				$out .= '<div class="pl-cta-head">' . $headline . '</div>';
			}
			$out .= '</div></div>';
		}

		// Clean white body: the social-proof line + the join button.
		$out .= '<div class="pl-cta-body">';
		if ( $has_body ) {
			$out .= '<p class="pl-cta-text">' . $body . '</p>';
		}
		if ( '' !== $button ) {
			$out .= '<a class="pl-cta-btn" href="' . esc_url( $url ) . '">' . esc_html( $button ) . '</a>';
		}
		$out .= '</div>';

		$out .= '</div>';

		return $out;
	}

	/**
	 * Self-contained styles, printed once per page. Mirrors the RBG Support
	 * campaign card (.rbg-cta): a black header band with a lime kicker + big
	 * white headline, a clean white body, soft shadow, rounded corners. Every
	 * rule is scoped under .pl-cta, and container queries make it stack in a
	 * narrow column (sidebar) automatically.
	 */
	private static function styles() {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;
		return '<style>'
			. '.pl-cta{--pl-accent:#e8f651;--pl-ink:#111;container-type:inline-size;position:relative;width:100%;margin:0 0 20px;'
			. 'background:#fff;border:1px solid #e7e7ea;border-radius:16px;'
			. 'box-shadow:0 10px 30px rgba(17,17,17,.10),0 2px 6px rgba(17,17,17,.05);overflow:visible;color:var(--pl-ink)}'
			. '.pl-cta *{box-sizing:border-box}'
			. '.pl-cta-header{background:#0a0a0a;display:flex;align-items:center;gap:14px;padding:15px 20px 16px;margin:0;border-radius:15px 15px 0 0}'
			. '.pl-cta-logo{flex:0 0 auto;width:52px;height:52px;border-radius:50%;display:block;margin:0;max-width:none}'
			. '.pl-cta-htext{min-width:0}'
			. '.pl-cta-kicker{display:block;font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--pl-accent);margin:0 0 2px}'
			. '.pl-cta-head{margin:0;padding:0;font-size:clamp(1rem,6cqi,1.35rem);font-weight:800;letter-spacing:-.02em;line-height:1.12;color:#fff}'
			. '.pl-cta-body{padding:18px 20px 20px;border-radius:0 0 15px 15px}'
			. '.pl-cta-text{margin:0 0 16px;font-size:1rem;line-height:1.55;color:#3f4650}'
			. '.pl-cta-count{font-weight:800;color:var(--pl-ink)}'
			. '.pl-cta-btn{display:inline-block;background:var(--pl-accent);color:var(--pl-ink)!important;font-weight:800;'
			. 'font-size:1.02rem;letter-spacing:-.01em;padding:13px 24px;border-radius:12px;text-decoration:none!important;'
			. 'box-shadow:none;white-space:nowrap;transition:filter .15s,transform .05s}'
			. '.pl-cta-btn:hover,.pl-cta-btn:focus{filter:brightness(.95);color:var(--pl-ink)!important;text-decoration:none!important}'
			. '.pl-cta-btn:active{transform:translateY(1px)}'
			. '@container (max-width:440px){'
			. '.pl-cta .pl-cta-header{padding:14px 16px 15px;gap:11px}'
			. '.pl-cta .pl-cta-logo{width:42px;height:42px}'
			. '.pl-cta .pl-cta-head{font-size:clamp(.95rem,7.5cqi,1.3rem)}'
			. '.pl-cta .pl-cta-body{padding:16px}'
			. '.pl-cta .pl-cta-text{font-size:.95rem;margin-bottom:14px}'
			. '.pl-cta .pl-cta-btn{display:block;text-align:center}'
			. '}'
			. '.pl-cta.is-compact .pl-cta-header{padding:14px 16px 15px;gap:11px}'
			. '.pl-cta.is-compact .pl-cta-logo{width:42px;height:42px}'
			. '.pl-cta.is-compact .pl-cta-head{font-size:clamp(.95rem,7.5cqi,1.3rem)}'
			. '.pl-cta.is-compact .pl-cta-body{padding:16px}'
			. '.pl-cta.is-compact .pl-cta-text{font-size:.95rem;margin-bottom:14px}'
			. '.pl-cta.is-compact .pl-cta-btn{display:block;text-align:center}'
			// Speech-bubble tail: a white rotated square peeking out the bottom,
			// bordered on its lower-right edges, pointing down at the stories below.
			. '.pl-cta.has-tail{margin-bottom:30px}'
			. '.pl-cta.has-tail::after{content:"";position:absolute;left:34px;bottom:-11px;width:22px;height:22px;'
			. 'background:#fff;border-right:1px solid #e7e7ea;border-bottom:1px solid #e7e7ea;border-bottom-right-radius:4px;'
			. 'transform:rotate(45deg);box-shadow:3px 3px 6px rgba(17,17,17,.05)}'
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
			<label for="<?php echo esc_attr( $this->get_field_id( 'kicker' ) ); ?>"><?php esc_html_e( 'Kicker (small label in the black header):', 'partyline' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'kicker' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'kicker' ) ); ?>" type="text" value="<?php echo esc_attr( $inst['kicker'] ); ?>">
		</p>
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
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'icon' ) ); ?>"><?php esc_html_e( 'Header logo URL (blank = your Site Icon; "none" to hide):', 'partyline' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'icon' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'icon' ) ); ?>" type="text" value="<?php echo esc_attr( $inst['icon'] ); ?>">
		</p>
		<p>
			<input class="checkbox" type="checkbox"<?php checked( in_array( strtolower( (string) $inst['compact'] ), array( '1', 'yes', 'true', 'on' ), true ) ); ?> id="<?php echo esc_attr( $this->get_field_id( 'compact' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'compact' ) ); ?>" value="1">
			<label for="<?php echo esc_attr( $this->get_field_id( 'compact' ) ); ?>"><?php esc_html_e( 'Force compact layout (for narrow spots)', 'partyline' ); ?></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox"<?php checked( in_array( strtolower( (string) $inst['arrow'] ), array( '1', 'yes', 'true', 'on' ), true ) ); ?> id="<?php echo esc_attr( $this->get_field_id( 'arrow' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'arrow' ) ); ?>" value="1">
			<label for="<?php echo esc_attr( $this->get_field_id( 'arrow' ) ); ?>"><?php esc_html_e( 'Speech-bubble tail (points down at the Partylines beneath it)', 'partyline' ); ?></label>
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
		$icon = isset( $new_instance['icon'] ) ? trim( (string) $new_instance['icon'] ) : '';
		return array(
			'kicker'   => sanitize_text_field( isset( $new_instance['kicker'] ) ? $new_instance['kicker'] : '' ),
			'headline' => sanitize_text_field( isset( $new_instance['headline'] ) ? $new_instance['headline'] : '' ),
			'body'     => sanitize_textarea_field( isset( $new_instance['body'] ) ? $new_instance['body'] : '' ),
			'button'   => sanitize_text_field( isset( $new_instance['button'] ) ? $new_instance['button'] : '' ),
			'count'    => ( '' !== $count && is_numeric( $count ) ) ? (string) max( 0, (int) $count ) : '',
			'min'      => ( '' !== $min && is_numeric( $min ) ) ? (string) max( 0, (int) $min ) : '',
			'url'      => '' !== $url ? esc_url_raw( $url ) : '',
			'icon'     => ( '' === $icon || 'none' === strtolower( $icon ) ) ? $icon : esc_url_raw( $icon ),
			'compact'  => empty( $new_instance['compact'] ) ? '' : '1',
			'arrow'    => empty( $new_instance['arrow'] ) ? '' : '1',
		);
	}
}

endif;
