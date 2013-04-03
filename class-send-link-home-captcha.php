<?php
/**
 * Class for generating CAPTCHA code
 *
 * Usage:
 *
 * Use an instance to generate the html code to place the image:
 * <code>
 * $cap = new Captcha();
 * $cap->width  = 456;
 * $cap->height = 123;
 * $cap->length = 7;
 * echo $cap->get_html();
 * </code>
 * This outputs the HTML for the captcha image, and remembers the captcha code
 * in the $_SESSION variable
 *
 * When processing the form:
 * <code>
 * $is_human = Captcha::validate_code( $posted_code );
 * </code>
 *
 */
class Send_Link_Home_Captcha {
	/**
	 * Image width, in pixels
	 * @var int
	 */
	public $width = 250;
	/**
	 * Image height, in pixels
	 * @var int
	 */
	public $height = 100;
	/**
	 * Code length, in characters
	 * @var int
	 */
	public $length = 5;

	/**
	 * Captcha filename
	 * @var string
	 */
	const CAPTCHA_FILE = 'captcha.php';

	/**
	 * Captcha font filename
	 * @var string
	 */
	const FONT_FILE = 'monofont.ttf';

	/**
	 * Characters to use in code
	 *
	 * Picked for being easy to type on mobile device _and_ being unsimilar to
	 * other characters
	 *
	 * @var string
	 */
	private static $code_chars = 'abcdefghijkmnpqrstuvwxyz';

	/**
	 * Colours to use for image generation
	 *
	 * Array of named array(red, green, blue) values, with values of 0-255
	 *
	 * @var array
	 */
	private static $colours = array(
			'background' => array( 255, 255, 255 ),
			'text'       => array( 20,  40,  100 ),
			'noise'      => array( 190, 199, 224 ),
		);

	/**
	 * Error contents to throw when session initialization fails
	 *
	 * @var string
	 */
	const ERROR_SESSION = "Unable to Initialize session data.  Make sure to call session_start() before any output, e.g. in the WP init.";

	/**
	 * Constructor
	 */
	public function __construct() {
		self::session_init();
	}

	/**
	 * Generate captcha image HTML
	 *
	 * @return string HTML to place CAPTCHA image
	 */
	public function get_html() {
		// generate the code now, because we need to know the length
		$this->set_code();

		$url = plugins_url( self::CAPTCHA_FILE, __FILE__ );
		$params = array('width'  => $this->width,
						'height' => $this->height,
						'time'   => time(),        // cache-prevention
					);
		$url .= '?' . build_query( $params );
		$url = htmlspecialchars( $url );

		return "<img src='$url' alt='CAPTCHA'"
				. " width='$this->width' height='$this->height' />";
	}

	/**
	 * Set the CAPTCHA code, if it was not set yet
	 *
	 * If code was already remembered, do nothing
	 * else generate a new code and remember it
	 */
	public function set_code() {
		if (empty( $_SESSION[ __CLASS__ ] )
				|| strlen( $_SESSION[ __CLASS__ ]) != $this->length )
			$_SESSION[ __CLASS__ ] = $this->generate_code();
	}

	/**
	 * Generate a CAPTCHA code
	 *
	 * @return string CAPTCHA code
	 */
	protected function generate_code( ) {
		$charnum = strlen( self::$code_chars );
		$code = '';
		for ( $char = 0; $char < $this->length; $char++ )
			$code .= substr( self::$code_chars, mt_rand( 0, $charnum - 1 ), 1 );

		return $code;
	}


	///////////////////////
	// Static methods, for image output
	///////////////////////
	/**
	 * Output CAPTCHA image
	 *
	 * Image dimensions must be passed as query parameters:
	 * @param int width  Image width, in pixels
	 * @param int height Image height, in pixels
	 */
	public static function show_image() {
		list( $width, $height ) = self::get_image_params();
		$code = self::get_code();
		// seed the random number generator with the code
		// thus producing exactly the same noise, as long as the code doesn't
		// change (e.g. for multiple forms on the same page)
		mt_srand( hexdec( bin2hex ( $code ) ) );

		$font_size = $height * 0.75;

		$image = @imagecreate( $width, $height )
				or die('Cannot initialize new GD image stream');

		/* set the colours */
		list($r, $g, $b) = self::$colours['background'];
		$background_color = imagecolorallocate( $image, $r, $g, $b );
		list($r, $g, $b) = self::$colours['text'];
		$text_color       = imagecolorallocate( $image, $r, $g, $b );
		list($r, $g, $b) = self::$colours['noise'];
		$noise_color      = imagecolorallocate( $image, $r, $g, $b );


		/* generate CAPTCHA text */
		$textbox = imagettfbbox( $font_size, 0, self::FONT_FILE, $code )
				or die('Error in imagettfbbox function');
		$x = ( $width - $textbox[4]  ) / 2;
		$y = ( $height - $textbox[5] ) / 2;
		imagettftext( $image, $font_size, 0, $x, $y, $text_color,
					self::FONT_FILE , $code )
				or die('Error in imagettftext function');

		/* generate random dots */
		for( $d = 0; $d < ( $width * $height ) / 3; $d++ )
			imagesetpixel( $image,
					mt_rand( 0, $width ), mt_rand( 0, $height ),
					$noise_color );


		/* generate random lines */
		for( $l = 0; $l < ( $width * $height ) / 250; $l++ )
			imageline( $image,
					mt_rand( 0 , $width ), mt_rand( 0 , $height ),
					mt_rand( 0 , $width ), mt_rand( 0 , $height ),
					$noise_color );


		/* output image to browser */
		header( 'Content-Type: image/png' );
		// never cache CAPTCHA image
		header( 'Cache-Control: no-cache, must-revalidate' ); // HTTP/1.1
		header( 'Expires: Wed, 6 nov 2012 12:00:00 GMT' ); // Date in the past
		imagepng( $image );
		imagedestroy( $image );
	}

	/**
	 * Get captcha image parameters from query, and validate them
	 *
	 * Die()s on invalid parameters
	 *
	 * @return array ( $width, $height )
	 */
	protected static function get_image_params() {
		$params = array('width', 'height');
		$values = array();
		foreach ($params as $param) {
			if ( empty( $_GET[$param] ) )
				die( "$param not set!" );

			$value = $_GET[$param];
			if ( !ctype_digit($value) )
				die( "Invalid $param!" );

			$values[] = $value;
		}

		return $values;
	}

	/**
	 * Get the CAPTCHA code
	 *
	 * Die()s if code not set
	 *
	 * @return string CAPTCHA code, or false if not set
	 */
	protected static function get_code( ) {
		self::session_init();

		if ( empty( $_SESSION[ __CLASS__ ] ) )
			die( 'Code not generated!' );

		return $_SESSION[ __CLASS__ ];
	}

	/**
	 * Validate CAPTCHA code
	 *
	 * Check if given code is correct, and forget stored code
	 *
	 * @param string   $code  Code to validate against stored value
	 * @return boolean        True if code matches, false otherwise
	 */
	public static function validate_code( $code ) {
		// code is stored in session
		self::session_init();

		$match = false;
		if ( $code && !empty( $_SESSION[ __CLASS__ ] ) )
			$match = ( $_SESSION[ __CLASS__ ] == $code );

		// forget code: you only get one try
		unset( $_SESSION[ __CLASS__ ] );

		return $match;
	}

	/**
	 * Make sure the session is initialized
	 *
	 * @throws Exception When session data has not been initialized, and we are
	 *                    unable to do so now (e.g. because output has started)
	 */
	protected static function session_init() {
		if ( !session_id() && !@session_start() )
			throw new Exception( self::ERROR_SESSION );
	}
}