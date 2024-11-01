<?php
/*
Plugin Name: bbPress User Notes
Plugin URI: http://www.clorith.net/
Description: Let moderators apply notes to individual users on your bbPress forums.
Version: 1.0.1
Author: Clorith
Author URI: http://www.clorith.net
License: GPLv2 or later
Text Domain: user-notes-for-bbpress
*/

/**
 * Class User_Notes_For_bbPress
 *
 * @since 1.0.0
 */
class User_Notes_For_bbPress {
	/**
	 * An array of authors who have written notes.
	 *
	 * These are stored to avoid looking up user IDs for every note.
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 *
	 * @var array $note_authors
	 */
	private $note_authors = array();

	/**
	 * An array of all notes for each user.
	 *
	 * As users may have written multiple replies, in a thread, this will save us needing to look up all notes multiple times.
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 *
	 * @var array $user_notes
	 */
	private $user_notes   = array();

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_action( 'bbp_theme_after_reply_author_details', array( $this, 'user_note_link' ) );
		add_action( 'bbp_theme_after_reply_content', array( $this, 'user_note_fields' ) );

		add_action( 'init', array( $this, 'add_note' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Load plugin text domains for translation files to take effect.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function load_textdomain() {
		load_plugin_textdomain( 'user-notes-for-bbpress', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Check if a note is added to a post and add it to the relevant users meta data
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function add_note() {
		// Make sure a note is added, and that the current user has capabilities to add them
		if ( ! isset( $_POST['bbp-user-notes-new-note'] ) || empty( $_POST['bbp-user-notes-new-note'] ) || ! current_user_can( 'moderate' ) ) {
			return;
		}

		// Make sure our nonces are in order
		if ( ! check_admin_referer( sprintf( 'bbp_user_note-add-%d', $_POST['bbp-user-note-user-id'] ), '_bbp_user_note_nonce' ) ) {
			wp_die();
		}

		// Ensure the ID userd is an integer, and a legitimate user
		$check_user = get_user_by( 'ID', intval( $_POST['bbp-user-note-user-id'] ) );
		if ( ! $check_user ) {
			return;
		}

		// Get an array of existing notes, or create an array if there are none
		$user_notes = get_user_meta( $check_user->ID, '_bbp_user_note', true );
		if ( ! $user_notes ) {
			$user_notes = array();
		}

		// Add the new note to the array of notes
		$user_notes[] = (object) array(
			'note'   => wp_kses( $_POST['bbp-user-notes-new-note'], array( 'a' => array( 'href' => array() ) ) ),
			'time'   => date( "c" ),
			'post'   => bbp_get_reply_url( intval( $_POST['bbp-user-note-original-reply'] ) ),
			'author' => get_current_user_id()
		);

		update_user_meta( $check_user->ID, '_bbp_user_note', $user_notes );
	}

	/**
	 * Register scripts and styles that plugin relies on.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function enqueue_scripts() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		wp_enqueue_style( 'bbpress-user-notes', plugins_url( '/assets/css/bbpress-user-notes.css', __FILE__ ) );

		wp_enqueue_script( 'bbpress-user-notes', plugins_url( '/assets/js/bbpress-user-notes.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );

		wp_localize_script( 'bbpress-user-notes', 'bbp_user_notes', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		) );
	}

	/**
	 * Add the toggle link for notes to the author area of a post
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function user_note_link() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		printf(
			'<span class="bbpress-user-notes-toggle" data-bbp-user-note-toggle="#bbpress-user-notes-%d">%s</span>',
			esc_attr( get_the_ID() ),
			esc_html__( 'Toggle user notes', 'user-notes-for-bbpress' )
		);

	}

	/**
	 * Output all existing users notes, and the form for adding new ones to a hidden area in the post content.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function user_note_fields() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$lookup_user = get_the_author_meta( 'ID' );

		if ( ! isset( $this->user_notes[ $lookup_user ] ) ) {
			$user_notes = get_user_meta( $lookup_user, '_bbp_user_note', true );

			if ( is_array( $user_notes ) ) {
				foreach ( $user_notes AS $user_note ) {
					if ( ! isset( $this->note_authors[ $user_note->author ] ) ) {
						$this->note_authors[ $user_note->author ] = get_user_by( 'ID', $user_note->author );
					}

					$this->user_notes[ $lookup_user ][] = sprintf(
						'<div class="bbpress-user-note-single">%s <span class="bbpress-user-note-meta">by %s @ <a href="%s">%s</a></span></div>',
						wp_kses( $user_note->note, array( 'a' => array( 'href' => array() ) ) ),
						esc_html( $this->note_authors[ $user_note->author ]->display_name ),
						esc_url( $user_note->post ),
						date_i18n( "Y-m-d H:i:s", strtotime( $user_note->time ) )
					);
				}
			}
		}

		printf(
			'<div class="bbpress-user-notes" id="bbpress-user-notes-%d">',
			esc_attr( get_the_ID() )
		);

		printf(
			'<h2>%s</h2>',
			esc_html__( 'User notes', 'user-notes-for-bbpress' )
		);

		if ( isset( $this->user_notes[ $lookup_user ] ) && ! empty( $this->user_notes[ $lookup_user ] ) ) {
			echo implode( '', $this->user_notes[ $lookup_user ] );
		}

		$this->add_note_form();

		echo '</div>';
	}

	/**
	 * Generate the form for adding new notes
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function add_note_form() {
?>
		<form action="<?php echo esc_url( sprintf( '%s#post-%d', bbp_get_topic_permalink(), get_the_ID() ) ); ?>" method="post" class="bbp-add-user-note">
			<input type="hidden" name="bbp-user-note-user-id" value="<?php echo esc_attr( get_the_author_meta( 'ID' ) ); ?>">
			<input type="hidden" name="action" value="bbpress-user-notes-add">
			<input type="hidden" name="bbp-user-note-original-reply" value="<?php echo esc_attr( get_the_ID() ); ?>">

			<?php wp_nonce_field( sprintf( 'bbp_user_note-add-%d', get_the_author_meta( 'ID' ) ), '_bbp_user_note_nonce' ); ?>

			<label for="bbp-user-notes-new-note" class="screen-reader-text"><?php esc_html_e( 'New note:', 'user-notes-for-bbpress' ); ?></label>
			<textarea name="bbp-user-notes-new-note" id="bbp-user-notes-new-note"></textarea>

			<button type="submit"><?php esc_html_e( 'Add your note', 'user-notes-for-bbpress' ); ?></button>
		</form>
<?php
	}
}

// Start up the plugin
new User_Notes_For_bbPress();