<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGLDC_Settings {

	const OPTION_KEY = 'agldc_settings';

	/**
	 * Returns the default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'completion_percentage' => 70,
			'certificate_image_id'  => 0,
			'font_family'           => 'helvetica',
			'font_size'             => 32,
			'font_color'            => '#1f2937',
			'name_position_x'       => 50,
			'name_position_y'       => 56,
			'name_alignment'        => 'center',
		);
	}

	/**
	 * Returns the option key for course-specific certificate settings.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private static function course_option_key( $course_id ) {
		return 'agldc_course_' . absint( $course_id ) . '_certificate';
	}

	/**
	 * Gets the certificate settings for a specific course.
	 *
	 * @param int $course_id Course ID.
	 * @return array<string, mixed>
	 */
	public static function get_course_certificate( $course_id ) {
		$course_id = absint( $course_id );

		if ( ! $course_id ) {
			return self::get();
		}

		$stored = get_option( self::course_option_key( $course_id ), array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = self::get();

		return wp_parse_args( $stored, $defaults );
	}

	/**
	 * Saves the certificate settings for a specific course.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $settings  Settings array.
	 * @return bool
	 */
	public static function save_course_certificate( $course_id, $settings ) {
		$course_id = absint( $course_id );

		if ( ! $course_id || ! is_array( $settings ) ) {
			return false;
		}

		$defaults   = self::get();
		$sanitized  = self::sanitize_course_settings( $settings, $defaults );

		return update_option( self::course_option_key( $course_id ), $sanitized );
	}

	/**
	 * Sanitizes course-specific settings.
	 *
	 * @param array $raw      Raw settings.
	 * @param array $defaults Default settings.
	 * @return array<string, mixed>
	 */
	private static function sanitize_course_settings( $raw, $defaults ) {
		$settings = array();

		$settings['certificate_image_id'] = self::sanitize_image_id( $raw['certificate_image_id'] ?? $defaults['certificate_image_id'] );
		$settings['font_family']          = self::sanitize_font_family( $raw['font_family'] ?? $defaults['font_family'] );
		$settings['font_size']            = max( 10, min( 120, intval( $raw['font_size'] ?? $defaults['font_size'] ) ) );
		$settings['font_color']           = self::sanitize_font_color( $raw['font_color'] ?? $defaults['font_color'] );
		$settings['name_position_x']      = self::sanitize_percentage_float( $raw['name_position_x'] ?? $defaults['name_position_x'] );
		$settings['name_position_y']      = self::sanitize_percentage_float( $raw['name_position_y'] ?? $defaults['name_position_y'] );
		$settings['name_alignment']       = self::sanitize_alignment( $raw['name_alignment'] ?? $defaults['name_alignment'] );

		return $settings;
	}

	/**
	 * Deletes the course-specific certificate settings.
	 *
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function delete_course_certificate( $course_id ) {
		return delete_option( self::course_option_key( absint( $course_id ) ) );
	}

	/**
	 * Builds the option key for group-specific settings.
	 *
	 * @param int $group_id Group ID.
	 * @return string
	 */
	private static function group_option_key( $group_id ) {
		return 'agldc_group_' . absint( $group_id ) . '_certificate';
	}

	/**
	 * Gets certificate settings for a specific group.
	 * Falls back to course settings, then global settings if none exist.
	 *
	 * @param int $group_id  Group ID.
	 * @param int $course_id Course ID for fallback.
	 * @return array<string, mixed>
	 */
	public static function get_group_certificate( $group_id, $course_id = 0 ) {
		$group_id = absint( $group_id );

		if ( ! $group_id ) {
			return $course_id ? self::get_course_certificate( $course_id ) : self::get();
		}

		$stored = get_option( self::group_option_key( $group_id ), array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		// Fall back to course settings, then global
		$fallback = $course_id ? self::get_course_certificate( $course_id ) : self::get();

		return wp_parse_args( $stored, $fallback );
	}

	/**
	 * Saves certificate settings for a specific group.
	 *
	 * @param int   $group_id  Group ID.
	 * @param int   $course_id Course ID for fallback defaults.
	 * @param array $settings  Settings array.
	 * @return bool
	 */
	public static function save_group_certificate( $group_id, $course_id, $settings ) {
		$group_id = absint( $group_id );

		if ( ! $group_id || ! is_array( $settings ) ) {
			return false;
		}

		$defaults  = $course_id ? self::get_course_certificate( $course_id ) : self::get();
		$sanitized = self::sanitize_course_settings( $settings, $defaults );

		return update_option( self::group_option_key( $group_id ), $sanitized );
	}

	/**
	 * Deletes the group-specific certificate settings.
	 *
	 * @param int $group_id Group ID.
	 * @return bool
	 */
	public static function delete_group_certificate( $group_id ) {
		return delete_option( self::group_option_key( absint( $group_id ) ) );
	}

	/**
	 * Gets the stored settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Sanitizes the settings before saving them.
	 *
	 * @param mixed $raw Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $raw ) {
		$defaults = self::defaults();
		$raw      = is_array( $raw ) ? $raw : array();

		$settings                           = array();
		$settings['completion_percentage']  = max( 1, min( 100, absint( $raw['completion_percentage'] ?? $defaults['completion_percentage'] ) ) );
		$settings['certificate_image_id']   = self::sanitize_image_id( $raw['certificate_image_id'] ?? 0 );
		$settings['font_family']            = self::sanitize_font_family( $raw['font_family'] ?? $defaults['font_family'] );
		$settings['font_size']              = max( 10, min( 120, intval( $raw['font_size'] ?? $defaults['font_size'] ) ) );
		$settings['font_color']             = self::sanitize_font_color( $raw['font_color'] ?? $defaults['font_color'] );
		$settings['name_position_x']        = self::sanitize_percentage_float( $raw['name_position_x'] ?? $defaults['name_position_x'] );
		$settings['name_position_y']        = self::sanitize_percentage_float( $raw['name_position_y'] ?? $defaults['name_position_y'] );
		$settings['name_alignment']         = self::sanitize_alignment( $raw['name_alignment'] ?? $defaults['name_alignment'] );

		return $settings;
	}

	/**
	 * Registers the settings object.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			'agldc_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Checks whether the saved certificate image is usable.
	 *
	 * @param int $attachment_id Image attachment ID.
	 * @return int
	 */
	private static function sanitize_image_id( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return 0;
		}

		$mime = get_post_mime_type( $attachment_id );

		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return 0;
		}

		return $attachment_id;
	}

	/**
	 * Restricts the font selection to the bundled core PDF fonts.
	 *
	 * @param string $font_family Raw font family.
	 * @return string
	 */
	private static function sanitize_font_family( $font_family ) {
		$font_family = sanitize_key( $font_family );
		$allowed     = self::font_family_options();

		if ( ! isset( $allowed[ $font_family ] ) ) {
			return 'helvetica';
		}

		return $font_family;
	}

	/**
	 * Converts a raw color string into a normalized hex value.
	 *
	 * @param string $font_color Raw font color.
	 * @return string
	 */
	private static function sanitize_font_color( $font_color ) {
		$font_color = sanitize_hex_color( $font_color );

		if ( ! $font_color ) {
			return self::defaults()['font_color'];
		}

		return $font_color;
	}

	/**
	 * Sanitizes a floating percentage between 0 and 100.
	 *
	 * @param mixed $value Raw percentage.
	 * @return float
	 */
	private static function sanitize_percentage_float( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 0.0;

		if ( $value < 0 ) {
			$value = 0.0;
		}

		if ( $value > 100 ) {
			$value = 100.0;
		}

		return round( $value, 2 );
	}

	/**
	 * Sanitizes the name alignment option.
	 *
	 * @param string $alignment Raw alignment.
	 * @return string
	 */
	private static function sanitize_alignment( $alignment ) {
		$alignment = sanitize_key( $alignment );
		$allowed   = self::alignment_options();

		if ( ! isset( $allowed[ $alignment ] ) ) {
			return 'center';
		}

		return $alignment;
	}

	/**
	 * Label options for the name alignment.
	 *
	 * @return array<string, string>
	 */
	public static function alignment_options() {
		return array(
			'left'   => __( 'Esquerda', 'ag-learndash-certificates' ),
			'center' => __( 'Centro', 'ag-learndash-certificates' ),
			'right'  => __( 'Direita', 'ag-learndash-certificates' ),
		);
	}

	/**
	 * Label options for built-in core PDF fonts.
	 *
	 * @return array<string, string>
	 */
	public static function font_family_options() {
		return array(
			'helvetica' => 'Helvetica',
			'times'     => 'Times',
			'courier'   => 'Courier',
		);
	}
}
