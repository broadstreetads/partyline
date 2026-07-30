<?php
/**
 * Video support for the Partyline contributor app.
 *
 * Videos are only offered when the server can actually process them: FFmpeg and
 * ffprobe are on PATH and PHP is allowed to shell out. On hosts without those
 * (most shared hosting), everything here reports "unsupported" and the app never
 * shows a video option, so the feature is completely inert.
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Partyline_Video' ) ):

class Partyline_Video {

	/** Fraction of the upload limit we recommend a single video stay under,
	 *  leaving headroom for the photos and form fields in the same request. */
	const BUDGET_FRACTION = 0.75;

	/** Rough bytes-per-second for a typical 1080p phone clip (~12 Mbps H.264),
	 *  used only to translate the byte budget into a friendly "seconds" estimate. */
	const ASSUMED_BYTES_PER_SEC = 1572864;

	/** Cron hook that transcodes one post's pending video. */
	const CRON_HOOK = 'partyline_process_video';

	/** Post meta keys for the async pipeline. */
	const STATUS_META  = '_partyline_video_status';   // pending | processing | done | failed
	const SRC_META     = '_partyline_video_src';      // staged original file path
	const NAME_META    = '_partyline_video_name';     // original file name (for a nice title)
	const ATTEMPTS_META = '_partyline_video_attempts'; // transcode attempts so far
	const ATTACH_META  = '_partyline_video_attachment'; // final MP4 attachment ID

	/** How many times to retry a failed transcode before giving up. */
	const MAX_ATTEMPTS = 3;

	/** HTML-comment marker wrapping the "processing" placeholder in post content. */
	const MARKER = 'partyline-video';

	private static $bins = array();

	/** Wire up the cron worker. Cheap and inert unless a post has a pending video. */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'processPost' ) );
	}

	/** Server can process video (FFmpeg present + shell access). */
	public static function isSupported() {
		if ( ! self::canExec() ) {
			return false;
		}
		return '' !== self::binPath( 'ffmpeg' ) && '' !== self::binPath( 'ffprobe' );
	}

	/** Video is actually available to contributors: supported AND switched on. */
	public static function isActive() {
		if ( ! self::isSupported() ) {
			return false;
		}
		$s = Partyline_Utility::getSettings();
		return ! empty( $s->video_enabled );
	}

	/**
	 * Whether the CURRENT user may attach a video. Feature must be active, and
	 * when the "editors only" setting is on, the user needs edit_others_posts
	 * (editors/admins) — which anonymous submitters never have.
	 */
	public static function userCanUpload() {
		if ( ! self::isActive() ) {
			return false;
		}
		$s = Partyline_Utility::getSettings();
		if ( ! empty( $s->video_restrict ) ) {
			return current_user_can( 'edit_others_posts' );
		}
		return true;
	}

	/** Is PHP allowed to run external processes? */
	public static function canExec() {
		if ( ! function_exists( 'proc_open' ) || ! function_exists( 'exec' ) ) {
			return false;
		}
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		return ! in_array( 'proc_open', $disabled, true ) && ! in_array( 'exec', $disabled, true );
	}

	/** Absolute path to a binary on PATH, or '' if not found (cached per request). */
	public static function binPath( $bin ) {
		$bin = preg_replace( '/[^a-z0-9_-]/i', '', (string) $bin );
		if ( '' === $bin ) {
			return '';
		}
		if ( array_key_exists( $bin, self::$bins ) ) {
			return self::$bins[ $bin ];
		}
		$path = '';
		if ( self::canExec() ) {
			$out = array();
			$rc  = 1;
			@exec( 'command -v ' . escapeshellarg( $bin ) . ' 2>/dev/null', $out, $rc );
			if ( 0 === $rc && ! empty( $out[0] ) ) {
				$path = trim( $out[0] );
			}
		}
		self::$bins[ $bin ] = $path;
		return $path;
	}

	/** The effective per-request upload ceiling in bytes (min of the PHP limits). */
	public static function uploadLimitBytes() {
		$candidates = array_filter( array(
			self::parseSize( (string) ini_get( 'upload_max_filesize' ) ),
			self::parseSize( (string) ini_get( 'post_max_size' ) ),
		) );
		return $candidates ? min( $candidates ) : 0;
	}

	/** Conservative recommended max size for one video (bytes). Over this we warn;
	 *  over uploadLimitBytes() the upload would just fail, so we block. */
	public static function recommendedMaxBytes() {
		$limit = self::uploadLimitBytes();
		return $limit > 0 ? (int) floor( $limit * self::BUDGET_FRACTION ) : 0;
	}

	/** A friendly "keep it under N seconds" estimate derived from the byte budget
	 *  and a typical bitrate, rounded down to a clean number. Guidance only —
	 *  the actual enforcement is on file size. */
	public static function recommendedMaxSeconds() {
		$bytes = self::recommendedMaxBytes();
		if ( $bytes <= 0 ) {
			return 0;
		}
		$secs = (int) floor( $bytes / self::ASSUMED_BYTES_PER_SEC );
		if ( $secs >= 60 ) {
			$secs = (int) ( floor( $secs / 15 ) * 15 );  // nearest 15s
		} elseif ( $secs >= 20 ) {
			$secs = (int) ( floor( $secs / 10 ) * 10 );  // nearest 10s
		} elseif ( $secs >= 5 ) {
			$secs = (int) ( floor( $secs / 5 ) * 5 );    // nearest 5s
		}
		return max( 5, $secs );
	}

	// ---------------------------------------------------------------------
	//  Async pipeline: stage on submit, transcode on cron.
	// ---------------------------------------------------------------------

	/** The uploads subdirectory where in-flight videos are staged. */
	public static function stagingDir() {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'partyline-video-tmp';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// Discourage casual access / listing of in-flight uploads.
			@file_put_contents( trailingslashit( $dir ) . 'index.html', '' );
		}
		return $dir;
	}

	/**
	 * Validate an uploaded video ($_FILES field) and move it into staging.
	 * Returns array( 'path' => ..., 'name' => ... ) or WP_Error. Kept fast:
	 * heavy validation (ffprobe) happens later in the cron worker.
	 */
	public static function stageUpload( $field ) {
		if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['tmp_name'] ) ) {
			return new WP_Error( 'partyline_video_missing', 'No video file was received.' );
		}
		$f = $_FILES[ $field ];
		if ( ! empty( $f['error'] ) ) {
			return new WP_Error( 'partyline_video_upload', 'The video failed to upload.' );
		}
		if ( ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'partyline_video_upload', 'The video upload could not be verified.' );
		}
		$limit = self::uploadLimitBytes();
		if ( $limit > 0 && (int) $f['size'] > $limit ) {
			return new WP_Error( 'partyline_video_toobig', 'That video is too large to upload.' );
		}
		$type = isset( $f['type'] ) ? (string) $f['type'] : '';
		if ( 0 !== strpos( $type, 'video/' ) ) {
			return new WP_Error( 'partyline_video_type', 'That file does not look like a video.' );
		}

		$ext = strtolower( (string) pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
		$ext = preg_replace( '/[^a-z0-9]/', '', $ext );
		if ( '' === $ext || strlen( $ext ) > 5 ) {
			$ext = 'mp4';
		}
		$dest = trailingslashit( self::stagingDir() ) . 'src-' . wp_generate_password( 16, false, false ) . '.' . $ext;
		if ( ! @move_uploaded_file( $f['tmp_name'], $dest ) ) {
			return new WP_Error( 'partyline_video_move', 'The video could not be saved for processing.' );
		}
		@chmod( $dest, 0640 );
		return array( 'path' => $dest, 'name' => sanitize_file_name( (string) $f['name'] ) );
	}

	/** The placeholder shown in post content while a video is being optimized. */
	public static function placeholderHtml() {
		return "\n<!-- " . self::MARKER . " -->\n"
			. '<p class="partyline-video-processing"><em>&#127916; A video is being optimized and will appear here shortly.</em></p>'
			. "\n<!-- /" . self::MARKER . " -->\n";
	}

	/** Record a staged video against a post and queue it for transcoding. */
	public static function attachPending( $post_id, array $video ) {
		if ( empty( $video['path'] ) || ! file_exists( $video['path'] ) ) {
			return;
		}
		update_post_meta( $post_id, self::STATUS_META, 'pending' );
		update_post_meta( $post_id, self::SRC_META, $video['path'] );
		if ( ! empty( $video['name'] ) ) {
			update_post_meta( $post_id, self::NAME_META, $video['name'] );
		}
		update_post_meta( $post_id, self::ATTEMPTS_META, 0 );
		self::schedule( $post_id );
	}

	/** Queue (or re-queue) the transcode job for a post. */
	public static function schedule( $post_id, $delay = 5 ) {
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( (int) $post_id ) ) ) {
			wp_schedule_single_event( time() + (int) $delay, self::CRON_HOOK, array( (int) $post_id ) );
		}
	}

	/**
	 * Cron worker: transcode one post's staged video to a web-friendly MP4,
	 * grab a poster frame, attach both, and swap the placeholder for the embed.
	 * Safe to call repeatedly — it only acts on posts still marked "pending".
	 */
	public static function processPost( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return;
		}
		if ( 'pending' !== get_post_meta( $post_id, self::STATUS_META, true ) ) {
			return; // already processing, done, or failed
		}
		if ( ! self::isSupported() ) {
			self::fail( $post_id, 'FFmpeg is not available on the server.' );
			return;
		}
		$src = get_post_meta( $post_id, self::SRC_META, true );
		if ( ! $src || ! file_exists( $src ) ) {
			self::fail( $post_id, 'The staged video could not be found.' );
			return;
		}

		// Claim the job so an overlapping cron run won't double-process it.
		update_post_meta( $post_id, self::STATUS_META, 'processing' );
		$attempts = (int) get_post_meta( $post_id, self::ATTEMPTS_META, true ) + 1;
		update_post_meta( $post_id, self::ATTEMPTS_META, $attempts );

		$dir  = self::stagingDir();
		$base = 'out-' . wp_generate_password( 16, false, false );
		$mp4  = trailingslashit( $dir ) . $base . '.mp4';
		$jpg  = trailingslashit( $dir ) . $base . '.jpg';

		if ( ! self::transcode( $src, $mp4 ) || ! file_exists( $mp4 ) || filesize( $mp4 ) < 1024 ) {
			@unlink( $mp4 );
			self::retryOrFail( $post_id, $attempts, 'Transcode failed or produced no output.' );
			return;
		}

		// Poster frame (the video's first frame) — best-effort; not fatal if absent.
		$have_poster = self::poster( $mp4, $jpg );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$name   = (string) get_post_meta( $post_id, self::NAME_META, true );
		$mp4_id = self::sideloadFile( $mp4, $post_id, self::niceName( $name, 'mp4' ), 'video/mp4' );
		if ( is_wp_error( $mp4_id ) ) {
			@unlink( $mp4 );
			@unlink( $jpg );
			self::retryOrFail( $post_id, $attempts, 'Could not add the video to the media library: ' . $mp4_id->get_error_message() );
			return;
		}

		$poster_url = '';
		$poster_id  = 0;
		if ( $have_poster ) {
			// The clean first frame becomes the <video> poster — the browser draws
			//  its own play button over it, so we leave this one un-badged.
			$pid = self::sideloadFile( $jpg, $post_id, self::niceName( $name, 'jpg' ), 'image/jpeg' );
			if ( ! is_wp_error( $pid ) ) {
				$poster_id  = (int) $pid;
				$poster_url = (string) wp_get_attachment_url( $poster_id );
			}

			// Featured image: a copy of that frame with a play badge baked in, so
			//  the post reads as video everywhere the thumbnail appears (archives,
			//  social cards, feeds). Only when the post has no thumbnail yet, so a
			//  contributor's chosen cover photo still wins.
			if ( ! get_post_thumbnail_id( $post_id ) ) {
				$featured_id = 0;
				$badge = trailingslashit( $dir ) . $base . '-preview.jpg';
				if ( self::overlayPlayIcon( $jpg, $badge ) ) {
					$fname = preg_replace( '/\.jpg$/', '-preview.jpg', self::niceName( $name, 'jpg' ) );
					$bid   = self::sideloadFile( $badge, $post_id, $fname, 'image/jpeg' );
					if ( ! is_wp_error( $bid ) ) {
						$featured_id = (int) $bid;
					}
					@unlink( $badge );
				}
				// Fall back to the clean frame if GD is unavailable or the badge failed.
				if ( ! $featured_id && $poster_id ) {
					$featured_id = $poster_id;
				}
				if ( $featured_id ) {
					set_post_thumbnail( $post_id, $featured_id );
				}
			}
		}

		$video_url = (string) wp_get_attachment_url( (int) $mp4_id );
		self::replacePlaceholder( $post_id, self::embedHtml( $video_url, $poster_url ) );

		update_post_meta( $post_id, self::ATTACH_META, (int) $mp4_id );
		update_post_meta( $post_id, self::STATUS_META, 'done' );
		delete_post_meta( $post_id, self::SRC_META );

		@unlink( $src );
		@unlink( $jpg );
		@unlink( $mp4 );
	}

	/** Transcode to a web-friendly, size-capped H.264/AAC MP4 (faststart). */
	private static function transcode( $src, $out ) {
		$ffmpeg = self::binPath( 'ffmpeg' );
		if ( '' === $ffmpeg ) {
			return false;
		}
		$cmd = escapeshellarg( $ffmpeg )
			. ' -y -loglevel error -i ' . escapeshellarg( $src )
			. ' -vf ' . escapeshellarg( "scale='min(1280,iw)':-2" )
			. ' -c:v libx264 -preset veryfast -crf 26 -maxrate 2500k -bufsize 5000k'
			. ' -movflags +faststart -pix_fmt yuv420p'
			. ' -c:a aac -b:a 128k -ac 2'
			. ' ' . escapeshellarg( $out ) . ' 2>&1';
		$o  = array();
		$rc = 1;
		@exec( $cmd, $o, $rc );
		if ( 0 !== $rc ) {
			self::log( 'ffmpeg transcode rc=' . $rc . ' :: ' . implode( ' | ', array_slice( $o, -3 ) ) );
			return false;
		}
		return true;
	}

	/** Grab the very first frame of the video as the poster / preview image. */
	private static function poster( $mp4, $jpg ) {
		$ffmpeg = self::binPath( 'ffmpeg' );
		if ( '' === $ffmpeg ) {
			return false;
		}
		// No -ss: decode from the start and take frame 0, so the preview image
		// is exactly what the contributor pointed at when they hit record.
		$cmd = escapeshellarg( $ffmpeg )
			. ' -y -loglevel error -i ' . escapeshellarg( $mp4 )
			. ' -frames:v 1 -q:v 3 ' . escapeshellarg( $jpg ) . ' 2>&1';
		$o  = array();
		$rc = 1;
		@exec( $cmd, $o, $rc );
		return ( 0 === $rc && file_exists( $jpg ) && filesize( $jpg ) > 0 );
	}

	/**
	 * Composite a centered play badge onto a JPEG, writing the result to $dest.
	 * Best-effort: returns false (and writes nothing) if GD is unavailable, so
	 * callers can fall back to the plain frame. The badge is drawn on a
	 * supersampled canvas and resampled down for smooth, anti-aliased edges.
	 */
	private static function overlayPlayIcon( $src, $dest ) {
		if ( ! function_exists( 'imagecreatefromjpeg' ) || ! function_exists( 'imagefilledpolygon' ) ) {
			return false;
		}
		$base = @imagecreatefromjpeg( $src );
		if ( ! $base ) {
			return false;
		}
		$w = imagesx( $base );
		$h = imagesy( $base );

		// Badge box ~28% of the shorter side, with a sensible minimum.
		$r = (int) round( min( $w, $h ) * 0.14 );
		if ( $r < 18 ) {
			$r = 18;
		}
		$d     = $r * 2;      // final badge size on the base image
		$scale = 3;           // supersample factor for anti-aliasing
		$s     = $d * $scale; // supersampled canvas size

		$icon = imagecreatetruecolor( $s, $s );
		imagealphablending( $icon, false );
		imagesavealpha( $icon, true );
		imagefill( $icon, 0, 0, imagecolorallocatealpha( $icon, 0, 0, 0, 127 ) );
		imagealphablending( $icon, true );

		$c = intdiv( $s, 2 );
		// Soft dark disc.
		imagefilledellipse( $icon, $c, $c, (int) round( $s * 0.98 ), (int) round( $s * 0.98 ), imagecolorallocatealpha( $icon, 0, 0, 0, 62 ) );
		// White play triangle, nudged right a touch for optical centering.
		$tw    = (int) round( $s * 0.42 );
		$th    = (int) round( $s * 0.46 );
		$ox    = (int) round( $s * 0.05 );
		$white = imagecolorallocatealpha( $icon, 255, 255, 255, 0 );
		$pts   = array(
			(int) ( $c - $tw / 2 + $ox ), (int) ( $c - $th / 2 ),
			(int) ( $c - $tw / 2 + $ox ), (int) ( $c + $th / 2 ),
			(int) ( $c + $tw / 2 + $ox ), $c,
		);
		imagefilledpolygon( $icon, $pts, $white );

		// Downsample the badge onto the base, centered.
		imagealphablending( $base, true );
		imagecopyresampled( $base, $icon, intdiv( $w, 2 ) - $r, intdiv( $h, 2 ) - $r, 0, 0, $d, $d, $s, $s );

		$ok = imagejpeg( $base, $dest, 90 );
		imagedestroy( $icon );
		imagedestroy( $base );
		return $ok && file_exists( $dest ) && filesize( $dest ) > 0;
	}

	/** Copy a staged file and hand the copy to media_handle_sideload. */
	private static function sideloadFile( $path, $post_id, $filename, $mime ) {
		if ( ! file_exists( $path ) ) {
			return new WP_Error( 'partyline_video_nofile', 'File to sideload is missing.' );
		}
		$tmp = wp_tempnam( $filename );
		if ( ! $tmp || ! @copy( $path, $tmp ) ) {
			@unlink( $tmp );
			return new WP_Error( 'partyline_video_copy', 'Could not stage the file for the media library.' );
		}
		$file_array = array( 'name' => $filename, 'tmp_name' => $tmp, 'type' => $mime );
		$id         = media_handle_sideload( $file_array, (int) $post_id );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			return $id;
		}
		return (int) $id;
	}

	/** A tidy attachment filename derived from the original upload name. */
	private static function niceName( $orig, $ext ) {
		$base = $orig ? preg_replace( '/\.[^.]+$/', '', $orig ) : '';
		$base = sanitize_title( $base );
		if ( '' === $base ) {
			$base = 'partyline-video';
		}
		return $base . '.' . $ext;
	}

	/** The final embed that replaces the placeholder. Uses core's [video]. */
	private static function embedHtml( $url, $poster = '' ) {
		$atts = 'mp4="' . esc_url( $url ) . '"';
		if ( '' !== $poster ) {
			$atts .= ' poster="' . esc_url( $poster ) . '"';
		}
		return "\n[video " . $atts . "]\n";
	}

	/** Swap the marked placeholder block for final HTML (or remove it). */
	private static function replacePlaceholder( $post_id, $html ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$marker  = preg_quote( self::MARKER, '/' );
		$pattern = '/\n?<!--\s*' . $marker . '\s*-->.*?<!--\s*\/' . $marker . '\s*-->\n?/s';
		$content = $post->post_content;
		if ( preg_match( $pattern, $content ) ) {
			$content = preg_replace( $pattern, $html, $content, 1 );
		} elseif ( '' !== $html ) {
			$content .= "\n" . $html;
		}
		wp_update_post( array( 'ID' => (int) $post_id, 'post_content' => $content ) );
	}

	/** Transient failure: back off and retry, or give up after MAX_ATTEMPTS. */
	private static function retryOrFail( $post_id, $attempts, $msg ) {
		if ( $attempts < self::MAX_ATTEMPTS ) {
			update_post_meta( $post_id, self::STATUS_META, 'pending' );
			wp_schedule_single_event( time() + 120, self::CRON_HOOK, array( (int) $post_id ) );
			self::log( 'video retry ' . $attempts . '/' . self::MAX_ATTEMPTS . ' post ' . $post_id . ': ' . $msg );
			return;
		}
		self::fail( $post_id, $msg );
	}

	/** Permanent failure: drop the placeholder, discard the source, mark failed. */
	private static function fail( $post_id, $msg ) {
		update_post_meta( $post_id, self::STATUS_META, 'failed' );
		self::replacePlaceholder( $post_id, '' );
		$src = get_post_meta( $post_id, self::SRC_META, true );
		if ( $src ) {
			@unlink( $src );
		}
		delete_post_meta( $post_id, self::SRC_META );
		self::log( 'video failed post ' . $post_id . ': ' . $msg );
	}

	/** Low-volume logging for the async path (only fires on trouble). */
	private static function log( $msg ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Partyline video] ' . $msg );
	}

	/** Parse a PHP shorthand size string ("64M", "8G") into bytes. */
	private static function parseSize( $size ) {
		$size = trim( (string) $size );
		if ( '' === $size ) {
			return 0;
		}
		$unit  = strtolower( substr( $size, -1 ) );
		$bytes = (float) $size;
		switch ( $unit ) {
			case 'g':
				$bytes *= 1024;
				// no break
			case 'm':
				$bytes *= 1024;
				// no break
			case 'k':
				$bytes *= 1024;
		}
		return (int) $bytes;
	}
}

endif;
