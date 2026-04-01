<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGLDC_PDF_Generator {

	/**
	 * Generates a PDF string containing the background image and the student name.
	 *
	 * @param string $image_path Background image path.
	 * @param string $student_name Student full name.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string
	 */
	public function build_certificate_pdf( $image_path, $student_name, array $settings ) {
		$image = $this->prepare_image_for_pdf( $image_path );

		if ( empty( $image['jpeg_data'] ) || empty( $image['width'] ) || empty( $image['height'] ) ) {
			return '';
		}

		$page_size     = $this->calculate_page_size( (int) $image['width'], (int) $image['height'] );
		$text_position = $this->calculate_text_position( $student_name, $page_size, $settings );
		$text          = $this->normalize_pdf_text( $student_name );
		$font_name     = $this->map_font_name( $settings['font_family'] ?? 'helvetica' );
		$font_size     = max( 10, (int) ( $settings['font_size'] ?? 32 ) );
		$color         = $this->hex_to_rgb_components( $settings['font_color'] ?? '#000000' );

		$contents = array();
		$contents[] = 'q';
		$contents[] = sprintf( '%.2F 0 0 %.2F 0 0 cm', $page_size['width'], $page_size['height'] );
		$contents[] = '/Im0 Do';
		$contents[] = 'Q';
		$contents[] = 'BT';
		$contents[] = sprintf( '/F1 %d Tf', $font_size );
		$contents[] = sprintf( '%.4F %.4F %.4F rg', $color['r'], $color['g'], $color['b'] );
		$contents[] = sprintf( '1 0 0 1 %.2F %.2F Tm', $text_position['x'], $text_position['y'] );
		$contents[] = sprintf( '(%s) Tj', $this->escape_pdf_text( $text ) );
		$contents[] = 'ET';

		$content_stream = implode( "\n", $contents ) . "\n";
		$image_stream   = $image['jpeg_data'];
		$image_length   = $this->byte_length( $image_stream );
		$content_length = $this->byte_length( $content_stream );
		$objects        = array();

		$objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
		$objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
		$objects[] = sprintf(
			"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /ProcSet [/PDF /Text /ImageC] /Font << /F1 5 0 R >> /XObject << /Im0 6 0 R >> >> /Contents 4 0 R >>",
			$page_size['width'],
			$page_size['height']
		);
		$objects[] = "<< /Length {$content_length} >>\nstream\n{$content_stream}endstream";
		$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /{$font_name} /Encoding /WinAnsiEncoding >>";
		$objects[] = sprintf(
			"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n",
			$image['width'],
			$image['height'],
			$image_length
		) . $image_stream . "\nendstream";

		return $this->compile_pdf( $objects );
	}

	/**
	 * Converts the uploaded background image to a JPEG stream for PDF embedding.
	 *
	 * @param string $image_path Image path.
	 * @return array<string, mixed>
	 */
	private function prepare_image_for_pdf( $image_path ) {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagejpeg' ) ) {
			return array();
		}

		if ( ! file_exists( $image_path ) || ! is_readable( $image_path ) ) {
			return array();
		}

		$image_data = file_get_contents( $image_path );

		if ( false === $image_data ) {
			return array();
		}

		$image_resource = imagecreatefromstring( $image_data );

		if ( ! $image_resource ) {
			return array();
		}

		$width  = imagesx( $image_resource );
		$height = imagesy( $image_resource );
		$canvas = imagecreatetruecolor( $width, $height );

		imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 255, 255, 255 ) );
		imagecopy( $canvas, $image_resource, 0, 0, 0, 0, $width, $height );

		ob_start();
		imagejpeg( $canvas, null, 92 );
		$jpeg_data = (string) ob_get_clean();

		imagedestroy( $canvas );
		imagedestroy( $image_resource );

		return array(
			'jpeg_data' => $jpeg_data,
			'width'     => $width,
			'height'    => $height,
		);
	}

	/**
	 * Normalizes the page size using the certificate aspect ratio.
	 *
	 * @param int $image_width Image width in pixels.
	 * @param int $image_height Image height in pixels.
	 * @return array<string, float>
	 */
	private function calculate_page_size( $image_width, $image_height ) {
		$max_dimension = 842.0;
		$aspect_ratio  = $image_width / max( 1, $image_height );

		if ( $aspect_ratio >= 1 ) {
			return array(
				'width'  => $max_dimension,
				'height' => round( $max_dimension / $aspect_ratio, 2 ),
			);
		}

		return array(
			'width'  => round( $max_dimension * $aspect_ratio, 2 ),
			'height' => $max_dimension,
		);
	}

	/**
	 * Calculates the text position on the PDF page based on the configured percentages.
	 *
	 * @param string $text Student name.
	 * @param array<string, float> $page_size Page size.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return array<string, float>
	 */
	private function calculate_text_position( $text, array $page_size, array $settings ) {
		$x_percentage = isset( $settings['name_position_x'] ) ? (float) $settings['name_position_x'] : 50.0;
		$y_percentage = isset( $settings['name_position_y'] ) ? (float) $settings['name_position_y'] : 56.0;
		$font_size    = max( 10, (int) ( $settings['font_size'] ?? 32 ) );
		$alignment    = isset( $settings['name_alignment'] ) ? sanitize_key( $settings['name_alignment'] ) : 'center';
		$text_width   = $this->estimate_text_width( $text, $font_size, $settings['font_family'] ?? 'helvetica' );
		$x            = $page_size['width'] * ( $x_percentage / 100 );
		$y            = $page_size['height'] - ( $page_size['height'] * ( $y_percentage / 100 ) );

		if ( 'center' === $alignment ) {
			$x -= $text_width / 2;
		} elseif ( 'right' === $alignment ) {
			$x -= $text_width;
		}

		$x = max( 0.0, min( $x, $page_size['width'] - 10 ) );
		$y = max( 10.0, min( $y, $page_size['height'] - 10 ) );

		return array(
			'x' => round( $x, 2 ),
			'y' => round( $y, 2 ),
		);
	}

	/**
	 * Provides a rough width estimate for the core fonts.
	 *
	 * @param string $text The text being rendered.
	 * @param int $font_size Font size.
	 * @param string $font_family Font family.
	 * @return float
	 */
	private function estimate_text_width( $text, $font_size, $font_family ) {
		$character_count = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		$character_count = max( 1, (int) $character_count );
		$factor          = 'courier' === $font_family ? 0.60 : 0.52;

		return round( $character_count * $font_size * $factor, 2 );
	}

	/**
	 * Maps internal font names to the PDF core fonts.
	 *
	 * @param string $font_family Requested font family.
	 * @return string
	 */
	private function map_font_name( $font_family ) {
		switch ( $font_family ) {
			case 'times':
				return 'Times-Roman';
			case 'courier':
				return 'Courier';
			case 'helvetica':
			default:
				return 'Helvetica';
		}
	}

	/**
	 * Converts a hex color into PDF RGB fractions.
	 *
	 * @param string $hex_color Hex color.
	 * @return array<string, float>
	 */
	private function hex_to_rgb_components( $hex_color ) {
		$hex_color = ltrim( (string) $hex_color, '#' );

		if ( 3 === strlen( $hex_color ) ) {
			$hex_color = $hex_color[0] . $hex_color[0] . $hex_color[1] . $hex_color[1] . $hex_color[2] . $hex_color[2];
		}

		if ( 6 !== strlen( $hex_color ) ) {
			$hex_color = '000000';
		}

		return array(
			'r' => hexdec( substr( $hex_color, 0, 2 ) ) / 255,
			'g' => hexdec( substr( $hex_color, 2, 2 ) ) / 255,
			'b' => hexdec( substr( $hex_color, 4, 2 ) ) / 255,
		);
	}

	/**
	 * Converts UTF-8 text into a core-font compatible string.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function normalize_pdf_text( $text ) {
		$text = trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $text ) );

		if ( ! function_exists( 'iconv' ) ) {
			return preg_replace( '/[^\x20-\x7E]/', '', $text );
		}

		$converted = iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text );

		if ( false === $converted ) {
			return preg_replace( '/[^\x20-\x7E]/', '', $text );
		}

		return $converted;
	}

	/**
	 * Escapes PDF-reserved characters inside a text string.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function escape_pdf_text( $text ) {
		return str_replace(
			array( '\\', '(', ')', "\r", "\n" ),
			array( '\\\\', '\\(', '\\)', ' ', ' ' ),
			$text
		);
	}

	/**
	 * Gets the byte length of a binary-safe string.
	 *
	 * @param string $data Raw string.
	 * @return int
	 */
	private function byte_length( $data ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $data, '8bit' );
		}

		return strlen( $data );
	}

	/**
	 * Compiles an array of raw PDF objects into a binary PDF string.
	 *
	 * @param array<int, string> $objects Raw PDF objects.
	 * @return string
	 */
	private function compile_pdf( array $objects ) {
		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array( 0 );

		foreach ( $objects as $index => $object_body ) {
			$offsets[ $index + 1 ] = $this->byte_length( $pdf );
			$pdf                  .= ( $index + 1 ) . " 0 obj\n" . $object_body . "\nendobj\n";
		}

		$xref_position = $this->byte_length( $pdf );
		$pdf          .= "xref\n";
		$pdf          .= '0 ' . ( count( $objects ) + 1 ) . "\n";
		$pdf          .= "0000000000 65535 f \n";

		for ( $i = 1; $i <= count( $objects ); $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}

		$pdf .= "trailer\n";
		$pdf .= '<< /Size ' . ( count( $objects ) + 1 ) . ' /Root 1 0 R >>' . "\n";
		$pdf .= "startxref\n";
		$pdf .= $xref_position . "\n";
		$pdf .= "%%EOF";

		return $pdf;
	}
}
