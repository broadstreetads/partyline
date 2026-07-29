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

	private static $bins = array();

	/** Can this server accept and transcode video? */
	public static function isSupported() {
		if ( ! self::canExec() ) {
			return false;
		}
		return '' !== self::binPath( 'ffmpeg' ) && '' !== self::binPath( 'ffprobe' );
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
