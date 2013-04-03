<?php
/**
 * Contains the Send_Link_Home class
 *
 */

/**
 * Class that handles most of the Send Link Home plugin
 *
 */
class Send_Link_Home {
	// We put everything in this class, so we don't have to
	// bother with silly prefix_ names for everything

	/**
	 * Plugin name, used as title
	 * @var string
	 */
	const NAME = 'Send Link Home';

	/**
	 * Plugin description, used in widget
	 * @var string
	 */
	const DESCRIPTION = 'Allows people to e-mail themselves a link to the current page';

	/**
	 * Plugin main filename
	 * @var string
	 */
	const PLUGIN_FILE = 'send-link-home.php';

	/**
	 * File containing the captcha class
	 * @var string
	 */
	const CAPTCHA_FILE = 'class-send-link-home-captcha.php';

	/**
	 * Front-end javascript file
	 * @var string
	 */
	const SCRIPT_FILE = 'script.js';

	/**
	 * Front-end stylesheet file
	 * @var string
	 */
	const STYLE_FILE = 'style.css';

	/**
	 * Options page title in admin
	 * @var string
	 */
	const OPTIONS_TITLE = 'Send Link Home Options';

	/**
	 * Short code that can be used to insert the plugin on a page
	 * @var string
	 */
	const SHORT_CODE  = 'send-link-home';
	/**
	 * Method name to include in templates to insert the plugin
	 * @var string
	 */
	const FUNCTION_NAME = 'show_form';

	/**
	 * Default options
	 *
	 * These are only retrieved through self::get_default_options(), to special-case sprintf some values
	 * Consists of array with ( <option name> => <default value> )
	 *
	 * @var array
	 */
	private static $default_options = array(
		// activation
		'act_frontpage'   => false,
		'act_pages'       => true,
		'act_posts'       => false,
		'act_search'      => false,
		'act_archive'     => false,
		// layout
		'css_class'       => '',
		'wgt_title'       => self::NAME,
		'rcpt_label'      => 'Recipient address',
		'msg_label'       => 'Additional message',
		'cpt_width'       => 150,
		'cpt_height'      => 50,
		'cpt_length'      => 5,
		'cpt_label'       => 'Validation Code',
		'cpt_description' => 'This captcha prevents abuse of the form for spam purposes.',
		'send_label'      => 'Send',
		// feedback
		'fb_success'      => 'Success.',
		'fb_fail_captcha' => "Your code was invalid.\nPlease reload the page and try again.",
		'fb_fail_address' => "The provided e-mail address is invalid.\nPlease try again.",
		'fb_fail_send'    => "There was a problem sending the e-mail.\nPlease try again later.",
		// email
		'email_subject'   => 'Recommended page on %s', // sprintf'd with get_option('home')
		'email_sender'    => '%s',                     // sprintf'd with get_option('blogname')
		'email_from'      => '%s',                     // sprintf'd with get_option('admin_email')
		// email contents
		'message_1'       => "Hello,\n\nYou recently sent this link to yourself:",
		'message_2'       => 'You added this message:',
		'message_3'       => "Thanks for your interest in our information!\nWe hope you will contact us in the near future.\n\nKind regards,\n%s", // sprintf'd with get_option('blogname')
	);

	/////////////////////////////
	// GENERAL
	/////////////////////////////

	/**
	 * Initialize plugin
	 */
	public static function init() {
		$options = get_option( __CLASS__ );

		// general
		register_activation_hook(
			dirname(__FILE__) . DIRECTORY_SEPARATOR . self::PLUGIN_FILE,
			array( __CLASS__, 'activate' ));
		register_deactivation_hook(
			dirname(__FILE__) . DIRECTORY_SEPARATOR . self::PLUGIN_FILE,
			array( __CLASS__, 'deactivate' ));

		// admin-side
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );

		// front-end
		// shortcode
		add_shortcode( self::SHORT_CODE, array( __CLASS__, 'shortcode' ) );
		// widget
		wp_register_sidebar_widget(
			__CLASS__,
			self::NAME,
			array( __CLASS__, 'widget' ),
			array( 'description' => self::DESCRIPTION, 'title' )
		);

		// javascript
		add_action( 'wp_enqueue_scripts',
			array( __CLASS__, 'enqueue_scripts' ) );
		// stylesheet
		add_action( 'wp_enqueue_scripts',
			array( __CLASS__, 'enqueue_styles' ) );
		// ajax handler
		add_action( 'wp_ajax_' . self::SHORT_CODE,
			array( __CLASS__, 'send_link' ) );
		add_action( 'wp_ajax_nopriv_' . self::SHORT_CODE,
			array( __CLASS__, 'send_link' ) );

		// session handling
		add_action( 'init',
			array( __CLASS__, 'session_init' ) );
	}


	/**
	 * Run on activation of the plugin
	 */
	public static function activate() {
		// set default options
		add_option( __CLASS__ , self::get_default_options() );
	}

	/**
	 * Run on deactivation of plugin
	 */
	public static function deactivate() {
		// remove our options from the system
		delete_option( __CLASS__ );
	}

	/**
	 * Run on initialization, makes sure sessions are available
	 */
	public static function session_init() {
		if( !session_id() )
			session_start();
	}


	/////////////////////////////
	// BACK-END
	/////////////////////////////
	/**
	 * Run on admin_init action
	 *
	 * Create our settings
	 */
	public static function admin_init() {
		register_setting( __CLASS__, __CLASS__, array( __CLASS__, 'sanitize' ) );

		// register admin style-sheet
		wp_register_style( __CLASS__ . '_admin_style',
			plugins_url( 'admin-style.css', __FILE__ ) );

		// create setting page, containing these sections:
		self::section_activation();
		self::section_layout();
		self::section_feedback();
		self::section_email();
		self::section_message();
	}

	/**
	 * Run on admin_menu action
	 *
	 * Adds our settings page to the list
	 */
	public static function admin_menu() {
		// Add our settings page to the list
		$page = add_options_page( self::OPTIONS_TITLE, self::NAME,
				'manage_options',
				__CLASS__, array( __CLASS__, 'options_page' ) );

		add_action( "admin_print_styles-$page" ,
			array( __CLASS__ , 'admin_style' ) );
	}

	/**
	 * Run on admin_print_Styles action
	 *
	 * Enqueue our admin style sheet
	 */
	public static function admin_style() {
		wp_enqueue_style( __CLASS__ . '_admin_style' );
	}

	/**
	 * Create activation settings section
	 */
	private static function section_activation() {
		add_settings_section( 'activation', 'Activation',
			array( __CLASS__, 'section' ), __CLASS__ );
		/**
		 * List of places where the plugin can be active, ( <option name> => <label> )
		 * @var array
		 */
		$activation_places = array(
				'act_frontpage' => 'Front Page',
				'act_pages'     => 'Pages',
				'act_posts'     => 'Posts',
				'act_search'    => 'Search',
				'act_archive'   => 'Archives',
		);
		foreach ( $activation_places as $option => $label ) {
			self::add_checkbox( $option, $label , 'activation' );
		}
	}

	/**
	 * Create layout settings section
	 */
	private static function section_layout() {
		add_settings_section( 'layout', 'Layout',
			array( __CLASS__, 'section' ), __CLASS__ );

		$layout_options = array(
				'css_class'  => "CSS classes<br />(added after 'send-link-home')",
				'wgt_title'  => 'Widget title',
				'rcpt_label' => 'Recipient label',
				'msg_label'  => 'Message label',
				'cpt_width'  => 'Captcha width',
				'cpt_height' => 'Captcha height',
				'cpt_length' => 'Captcha length',
				'cpt_label'  => 'Captcha label',
		);
		foreach ( $layout_options as $option => $label ) {
			self::add_textbox( $option, $label, 'layout' );
		}
		// textarea spoils the loop :(
		self::add_textarea( 'cpt_description',
			'Captcha description', 'layout' );
		self::add_textbox( 'send_label',
			'Send button text',    'layout' );
	}

	/**
	 * Create feedback settings section
	 */
	private static function section_feedback() {
		add_settings_section( 'feedback', 'Feedback',
			array( __CLASS__, 'section' ), __CLASS__ );

		self::add_textarea( 'fb_success',      'Success',         'feedback' );
		self::add_textarea( 'fb_fail_captcha', 'Captcha failure', 'feedback' );
		self::add_textarea( 'fb_fail_address', 'Invalid address', 'feedback' );
		self::add_textarea( 'fb_fail_send',    'Send Failure',    'feedback' );
	}

	/**
	 * Create email settings section
	 */
	private static function section_email() {
		add_settings_section( 'email', 'E-mail Settings',
			array( __CLASS__, 'section' ), __CLASS__ );

		self::add_textbox( 'email_subject', 'E-mail subject', 'email' );
		self::add_textbox( 'email_sender',  'Sender name',    'email' );
		self::add_textbox( 'email_from',    'Sender e-mail',  'email' );
	}

	/**
	 * Create message contents settings section
	 */
	private static function section_message() {
		add_settings_section( 'message', 'Message',
			array( __CLASS__, 'section' ), __CLASS__ );

		self::add_textarea( 'message_1', 'Part 1', 'message' );
		self::add_textarea( 'message_2', 'Part 2<br />(Only shown if the sender added a message)', 'message' );
		self::add_textarea( 'message_3', 'Part 3', 'message' );
	}

	/**
	 * Callback for creating a section
	 */
	public static function section() {
		/* do nothing (Wordpress already prints the title) */
	}

	/**
	 * Add a setting field
	 *
	 * @param string $option  Option id
	 * @param ustring $title  Option title
	 * @param string $type    Option type: 'checkbox', 'textbox', or 'textarea'
	 * @param string $section Section to add option field to
	 */
	private static function add_field( $option, $title, $type, $section ) {
		// to implement more types implement a method with the type name
		$id = __CLASS__ . "[$option]";

		add_settings_field( $id, $title,
				array( __CLASS__, $type), __CLASS__, $section,
				// 'id':        used to get the current value when outputing the element
				// 'label_for': used by the API to link the label to our input element
				array( 'id' => $option, 'label_for' => $id ) );
	}

	// some shortcut functions
	/**
	 * Add a checkbox field, for boolean options
	 *
	 * @param string $option  Option id
	 * @param string $title   Option title
	 * @param string $section Section to add option field to
	 */
	private static function add_checkbox( $option, $title, $section ) {
		self::add_field( $option, $title, 'checkbox', $section );
	}

	/**
	 * Add a textbox field, for simple text values
	 *
	 * @param string $option  Option id
	 * @param string $title   Option title
	 * @param string $section Section to add option field to
	 */
	private static function add_textbox( $option, $title, $section ) {
		self::add_field( $option, $title, 'textbox', $section );
	}

	/**
	 * Add a textarea field, for longer text values
	 *
	 * @param string $option  Option id
	 * @param string $title   Option title
	 * @param string $section Section to add option field to
	 */
	private static function add_textarea( $option, $title, $section ) {
		self::add_field( $option, $title, 'textarea', $section );
	}

	// callback functions to output form elements
	/**
	 * Output an option checkbox
	 *
	 * @param array $args array containing 'id' element with option id
	 */
	public static function checkbox( $args ) {
		$options = get_option( __CLASS__ );
		// we pass the id in this way because that's what the API wants
		// ($args also contains 'label_for', which we don't use, but the API does)
		$id = $args['id'];
		$checked = checked( $options[ $id ], true, false );
		$id = esc_attr( $id );
		$name = esc_attr( __CLASS__ . "[$id]" );
		echo "<input type='checkbox' id='$id' name='$name' value='1' $checked />";
	}

	/**
	 * Output an option textbox
	 *
	 * @param array $args array containing 'id' element with option id
	 */
	public static function textbox( $args ) {
		$options = get_option( __CLASS__ );
		$id = $args['id'];
		$value = esc_attr( $options[ $id ] );
		$id = esc_attr( $id );
		$name = esc_attr( __CLASS__ . "[$id]" );
		echo "<input type='text' id='$id' name='$name' value='$value' />";

		// some options need units behind the textbox
		switch( $args['id'] ) {
			case 'cpt_width':
			case 'cpt_height':
				echo ' px';
				break;
			case 'cpt_length':
				echo ' characters';
				break;
		}
	}

	/**
	 * Output an option textarea
	 *
	 * @param array $args array containing 'id' element with option id
	 */
	public static function textarea( $args ) {
		$options = get_option( __CLASS__ );
		$id = $args['id'];
		$value = esc_textarea( $options[ $id ] );
		$id = esc_attr( $id );
		$name = esc_attr( __CLASS__ . "[$id]" );
		echo "<textarea id='$id' name='$name'>$value</textarea>";

		// clarification of what goes where in the final message
		switch( $args['id'] ) {
			case 'message_1':
				echo '<br /><i>(The link will be displayed here.)</i>';
				break;
			case 'message_2':
				echo '<br /><i>(The additional message will be displayed here.)</i>';
				break;
		}
	}



	/**
	 * Outputs options page
	 */
	public static function options_page() {
		?>
		<div class="wrap">
			<h2><?php echo esc_html( self::OPTIONS_TITLE ); ?></h2>
			<h3>Plugin Usage</h3>
			<ul>
				<li>Drag and drop the widget into any widget area</li>
				<li>
					Add the shortcode
					<code>[<?php echo esc_html( self::SHORT_CODE ); ?>]</code>
					to your homepage, pages, posts, archives and such.<br />
					Make sure to activate each type for usage.
				</li>
				<li>
					Add the plugin call
					<code><?php echo esc_html( self::template_code() );?></code>
					directly in your theme's PHP.
				</li>
				<li>
					Adjust your theme's CSS to customize the look of your form.<br />
					You can use the CSS class defined below in your theme's CSS.
				</li>
			</ul>
			<form method="post" action="options.php">
				<?php settings_fields( __CLASS__ ); ?>
				<?php do_settings_sections( __CLASS__ ); ?>
				<p class="submit">
					<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Generate PHP code to include in templates to show the plugin
	 *
	 * @return string PHP code that can be copy-pasted in template
	 */
	private static function template_code() {
		$code = "<?php ";                                             // open PHP tag
		$code .= "if ( method_exists( '";                             // check for function existance
		$code .= __CLASS__ . "', '" . self::FUNCTION_NAME . "' ) ) "; // class, method name to check
		$code .= __CLASS__ . "::" . self::FUNCTION_NAME . "(); ";     // actual function call
		$code .= "?>";                                                // and close the tag

		return $code;
	}


	/**
	 * Sanitize options posted by admin user
	 *
	 * @param array $input  Options to sanitize
	 * @return arra         Sanitized options
	 */
	public static function sanitize( $input ) {
		// basically, default to default options
		// check if any of them are set and overwrite them with the posted value
		// this way, any input values that don't correspond to an actual option get discarded
		$output = self::get_default_options();
		foreach ($output as $option => $default) {
			if ( is_bool( $default ) ) {
				// unchecked checkboxes might just be missing completely from $input
				$output[ $option ] = ! empty( $input[ $option ] );
			} elseif ( isset( $input[$option] ) ) {
				// CAPTCHA options (empty string would still be present in $input, just empty)
				if ( is_int ( $default) ) {
					// convert to positive integer (1 is still a silly small value)
					$output[ $option ] = max( intval( $input[ $option ] ), 1 );
				} else {
					// regular text
					$output[ $option ] = $input[ $option ];
				}
			}
		}

		return $output;
	}


	/**
	 * Returns default options
	 *
	 * Basically returns self::$default_options, except that some fields get special treatment
	 *
	 * @return array default options
	 */
	private static function get_default_options() {
		$options = self::$default_options;
		$options['email_subject'] = sprintf( $options['email_subject'], get_option( 'home' ) );
		$options['email_sender']  = sprintf( $options['email_sender'],  get_option( 'blogname' ) );
		$options['email_from']    = sprintf( $options['email_from'],    get_option( 'admin_email' ) );
		$options['message_3']     = sprintf( $options['message_3'],     get_option( 'blogname' ) );
		return $options;
	}

	////////////////////////////
	// FRONT-END
	////////////////////////////
	/**
	 * Return HTML for inclusion at shortcode location
	 *
	 * @return string  HTML to include
	 */
	public static function shortcode() {
		return ( self::check_display() ? self::get_form_html() : '' );
	}

	/**
	 * Ouput HTML for inclusion at widget location
	 */
	public static function widget( $args ) {
		if ( self::check_display() ) {
			$options = get_option( __CLASS__ );
			extract($args);
			echo $before_widget;
			echo $before_title . $options['wgt_title'] . $after_title;
			echo self::get_form_html();
			echo $after_widget;
		}
	}

	/**
	 * Ouput HTML for inclusion by direct method call
	 */
	public static function show_form() {
		echo ( self::check_display() ? self::get_form_html() : '' );
	}

	/**
	 * Check if we really want to display our form here
	 *
	 * @return bool True if we should display the form
	 */
	protected static function check_display() {
		$options = get_option( __CLASS__ );

		if ( is_home()    && $options['act_frontpage'] ) return true;
		if ( is_single()  && $options['act_posts'] )     return true;
		if ( is_page()    && $options['act_pages'] )     return true;
		if ( is_archive() && $options['act_archive'] )   return true;
		if ( is_search()  && $options['act_search'] )    return true;

		return false;
	}

	/**
	 * Run when enqueueing javascript
	 */
	public static function enqueue_scripts() {
		$options = get_option( __CLASS__ );

		// enqueue our script
		wp_enqueue_script( __CLASS__ . '_script',
				plugins_url( self::SCRIPT_FILE, __FILE__ ), array( 'jquery' ) );

		// pass some variables
		wp_localize_script( __CLASS__ . '_script', 'slh_info',
			array(
				'action'        => self::SHORT_CODE,
				'classname'     => self::SHORT_CODE,
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'error_message' => $options['fb_fail_send'],
			) );
	}

	/**
	 * Run when enqueueing stylesheets
	 */
	public static function enqueue_styles() {
		$options = get_option( __CLASS__ );

		wp_enqueue_style( __CLASS__ . '_style',
				plugins_url( self::STYLE_FILE, __FILE__ ) );
	}

	/**
	 * Get the HTML code for the front-end form
	 *
	 * @return string HTML for inclusion on page
	 */
	protected static function get_form_html() {
		static $form_number = 0;
		$options = get_option( __CLASS__ );

		// asterisk for mandatory options
		$man = '<span class="mandatory">*</span>';

		$form_number++; // count the forms generated on this page
		$id = self::SHORT_CODE . "_form_$form_number";

		$html = "<form id='$id' class='"
					. self::SHORT_CODE . " {$options['css_class']}'>";
		$html .= '<fieldset>';
		$html .= '<legend></legend>';
		$html .= '<ol>';

		// recipient address
		$html .= '<li>';
		$html .= "<label>{$options['rcpt_label']}$man</label>";
		$html .= '<input type="text" name="recipient" />';
		$html .= '</li>';

		// additional message
		$html .= '<li>';
		$html .= "<label>{$options['msg_label']}</label>";
		$html .= '<textarea name="message"></textarea>';
		$html .= '</li>';

		// CAPTCHA
		$html .= '<li>';
		require_once( self::CAPTCHA_FILE );
		$captcha = new Send_Link_Home_Captcha();
		$captcha->width  = $options['cpt_width'];
		$captcha->height = $options['cpt_height'];
		$captcha->length = $options['cpt_length'];
		$html .= $captcha->get_html();
		$html .= "<label>{$options['cpt_label']}$man</label>";
		$html .= '<input type="text" name="captcha" />';
		$html .= "<span class='explanation'>{$options['cpt_description']}</span>";
		$html .= '</li>';

		// submit button
		$html .= '<li>';
		$html .= "<input type='submit' value='{$options['send_label']}' />";
		$html .= '</li>';

		$html .= '</ol>';
		$html .= '</fieldset>';
		$html .= '</form>';

		return $html;
	}


	////////////////////////
	// AJAX
	////////////////////////
	/**
	 * Send the link as requested by AJAX
	 *
	 * Handles the request, then outputs JSON and exits
	 * JSON output is in the form  { 'success':bool; 'message':string }
	 */
	public static function send_link() {
		self::validate_request();
		$options = get_option( __CLASS__ );

		$link = self::uri_to_url( $_POST['uri'] );

		$from = "\"{$options['email_sender']}\" <{$options['email_from']}>";
		$headers = "From: $from";

		$to = $_POST['to'];

		$subject = $options['email_subject'];

		$message = $options['message_1'];
		$message .= "\n\n$link\n\n";
		if ( !empty( $_POST['message'] ) ) {
			$message .= $options['message_2'] . "\n";
			$message .= $_POST['message'] . "\n\n";
		}
		$message .= $options['message_3'];


		$success = wp_mail( $to, $subject, $message, $headers );

		if ( $success )
			self::respond( $options['fb_success'], true );
		else
			self::respond( $options['fb_fail_send'] );
	}

	/**
	 * Send AJAX response and exit
	 *
	 * @param string $message Message to display to user
	 * @param bool $success   Was the request successful?
	 */
	protected static function respond( $message, $success = false ) {
		$response = array (
						'success' => (bool)$success,
						'message' => $message
						);
		die( json_encode( $response ) );
	}

	/**
	 * Validate the ajax request
	 *
	 * Checks CAPTCHA, if email-address was valid, and that URI is not empty
	 * Die()s with the appropriate error message if errors are detected
	 *
	 */
	protected static function validate_request() {
		$options = get_option( __CLASS__ );

		if ( empty( $_POST['to'] )
				|| !is_email( $_POST['to'] ))
			self::respond( $options['fb_fail_address'] );

		if ( empty( $_POST['uri'] ) )
			self::respond( $options['fb_fail_send'] );

		// check CAPTCHA last: if another field fails it won't be invalidated
		require_once( self::CAPTCHA_FILE );
		if ( empty( $_POST['code'] )
				|| !Send_Link_Home_Captcha::validate_code( $_POST['code'] ))
			self::respond( $options['fb_fail_captcha'] );
	}

	/**
	 * Returns full URL for URI
	 *
	 * @param string $uri URI of page on server
	 * @return string     Complete URL to page
	 */
	protected static function uri_to_url( $uri ) {
		$url = 'http';
		if ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' )
			$url .= 's';

		$url .= '://';
		$url .= $_SERVER['SERVER_NAME'];
		if ( $_SERVER['SERVER_PORT'] != '80' )
			$url .= ":{$_SERVER['SERVER_PORT']}";

		$url .= $uri;

		return $url;
	}
}
