<?php
/**
 * ReassignEdits
 *
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @author: Tim 'SVG' Weyer <SVG@Wikiunity.com>
 *
 * @copyright Copyright (C) 2011 Tim Weyer, Wikiunity
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 *
 */

class ReassignEditsSQL {
	/**
	 * The old username
	 *
	 * @var string
	 * @access private
	 */
	public $old;

	/**
	 * The new username
	 *
	 * @var string
	 * @access private
	 */
	public $new;

	/**
	 * @var array of settings.
	 */
	public $settings;

	/**
	 * Constructor
	 *
	 * @param string $old The old username
	 * @param string $new The new username
	 * @param array $settings Associative array ("setting" => bool ). Available: updatelogginguser,
	 * updateloggingtitle
	 */
	function __construct( $old, $new, $settings ) {
		$this->old = $old;
		$this->new = $new;
		$this->settings = $settings;
	}

	/**
	 * Do the reassign operation
	 */
	function reassign() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );

		$newname = $this->new;
		$newid = User::idFromName( $this->new );
		$oldname = $this->old;

		// Update archive table (deleted revisions)
		$dbw->update( 'archive',
			array( 'ar_user_text' => $newname, 'ar_user' => $newid ),
			array( 'ar_user_text' => $oldname ),
			__METHOD__ );

		if ( $this->settings['updatelogginguser'] ) {
			$dbw->update( 'logging',
				array( 'log_user_text' => $newname, 'log_user' => $newid ),
				array( 'log_user_text' => $oldname ),
				__METHOD__ );
		}

		if ( $this->settings['updateloggingtitle'] ) {
			$oldTitle = Title::makeTitle( NS_USER, $this->old );
			$newTitle = Title::makeTitle( NS_USER, $this->new );
			$dbw->update( 'logging',
				array( 'log_title' => $newTitle->getDBkey() ),
				array( 'log_type' => array( 'block', 'rights' ),
					'log_namespace' => NS_USER,
					'log_title' => $oldTitle->getDBkey() ),
				__METHOD__ );
		}

		// Update revision table
		$dbw->update( 'revision',
			array( 'rev_user_text' => $newname, 'rev_user' => $newid ),
			array( 'rev_user_text' => $oldname ),
			__METHOD__ );

		// Commit the transaction
		$dbw->endAtomic( __METHOD__ );

		return true;
	}
}
