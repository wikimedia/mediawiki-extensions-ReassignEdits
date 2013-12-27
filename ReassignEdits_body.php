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

class SpecialReassignEdits extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ReassignEdits', 'reassignedits' );
	}

	/**
	 * Show the special page
	 *
	 * @param mixed $par Parameter passed to the page
	 * @throws UserBlockedError
	 */
	public function execute( $par ) {
		global $wgContLang;

		$this->setHeaders();
		$out = $this->getOutput();
		$out->addWikiMsg( 'reassignedits-summary' );

		// If the user doesn't have 'reassignedits' permission, display an error
		$user = $this->getUser();
		if ( !$user->isAllowed( 'reassignedits' ) ) {
			$this->displayRestrictionError();
			return;
		}

		// Show a message if the database is in read-only mode
		if ( wfReadOnly() ) {
			$out->readOnlyPage();
			return;
		}

		// If user is blocked, s/he doesn't need to access this page
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $this->getUser()->mBlock );
		}

		$request = $this->getRequest();
		$oldnamePar = trim( str_replace( '_', ' ', $request->getText( 'oldusername', $par ) ) );
		$oldusername = Title::makeTitle( NS_USER, $oldnamePar );
		// Force uppercase of newusername, otherwise wikis with wgCapitalLinks=false can create lc usernames
		$newusername = Title::makeTitleSafe( NS_USER, $wgContLang->ucfirst( $request->getText( 'newusername' ) ) );
		$oun = is_object( $oldusername ) ? $oldusername->getText() : '';
		$nun = is_object( $newusername ) ? $newusername->getText() : '';
		$token = $user->getEditToken();

		$updatelogging_user = $request->getBool( 'updatelogginguser', !$request->wasPosted() );
		$updatelogging_title = $request->getCheck( 'updateloggingtitle' );

		$out->addHTML(
			Xml::openElement( 'form', array(
				'method' => 'post',
				'action' => $this->getPageTitle()->getLocalUrl(),
				'id' => 'reassignedits'
			) ) .
				Xml::openElement( 'fieldset' ) .
				Xml::element( 'legend', null, $this->msg( 'reassignedits' )->text() ) .
				Xml::openElement( 'table', array( 'id' => 'mw-reassignedits-table' ) ) .
				"<tr>
				<td class='mw-label'>" .
				Xml::label( $this->msg( 'reassignedits-old' )->text(), 'oldusername' ) .
				"</td>
				<td class='mw-input'>" .
				Xml::input( 'oldusername', 20, $oun, array( 'type' => 'text', 'tabindex' => '1' ) ) . ' ' .
				"</td>
			</tr>
			<tr>
				<td class='mw-label'>" .
				Xml::label( $this->msg( 'reassignedits-new' )->text(), 'newusername' ) .
				"</td>
				<td class='mw-input'>" .
				Xml::input( 'newusername', 20, $nun, array( 'type' => 'text', 'tabindex' => '2' ) ) .
				"</td>
			</tr>"
		);
		$out->addHTML( "
			<tr>
				<td>&#160;
				</td>
				<td class='mw-input'>" .
				Xml::checkLabel( $this->msg( 'reassignedits-updatelog-user' )->text(),
					'updatelogginguser', 'updatelogginguser', $updatelogging_user,
					array( 'tabindex' => '3' ) ) .
				"</td>
			</tr>"
		);
		$out->addHTML( "
			<tr>
				<td>&#160;
				</td>
				<td class='mw-input'>" .
				Xml::checkLabel(
					$this->msg( 'reassignedits-updatelog-title' )->text(), 'updateloggingtitle',
					'updateloggingtitle', $updatelogging_title, array( 'tabindex' => '4' ) ) .
				"</td>
			</tr>"
		);
		$out->addHTML( "
			<tr>
				<td>&#160;
				</td>
				<td class='mw-submit'>" .
				Xml::submitButton( $this->msg( 'reassignedits-submit' )->text(),
					array( 'name' => 'submit', 'tabindex' => '5', 'id' => 'submit' ) ) .
				"</td>
			</tr>" .
			Xml::closeElement( 'table' ) .
			Xml::closeElement( 'fieldset' ) .
			Html::hidden( 'token', $token ) .
			Xml::closeElement( 'form' ) . "\n"
		);

		if ( $request->getText( 'token' ) === '' ) {
			// They probably haven't even submitted the form, so don't go further
			return;
		} elseif ( !$request->wasPosted() || !$user->matchEditToken( $request->getVal( 'token' ) ) ) {
			$out->addWikiText( "<div class=\"errorbox\">" .
				$this->msg( 'reassignedits-error-request' )->text() . "</div>" );
			return;
		} elseif ( !is_object( $oldusername ) ) {
			$out->addWikiText(
				"<div class=\"errorbox\">"
					. $this->msg( 'reassignedits-error-invalid', $request->getText( 'oldusername' ) )->text()
					. "</div>"
			);
			return;
		} elseif ( !is_object( $newusername ) ) {
			$out->addWikiText(
				"<div class=\"errorbox\">"
					. $this->msg( 'reassignedits-error-invalid', $request->getText( 'newusername' ) )->text()
					. "</div>"
			);
			return;
		}

		// Get usernames by id
		$newuser = User::newFromName( $newusername->getText() );

		// It won't be an object if for instance "|" is supplied as a value
		if ( !is_string( $oldusername->getText() ) ) {
			$out->addWikiText( "<div class=\"errorbox\">" . $this->msg( 'reassignedits-error-invalid',
				$oldusername->getText() )->text() . "</div>" );
			return;
		}
		if ( !is_object( $newuser ) ) {
			$out->addWikiText( "<div class=\"errorbox\">" . $this->msg( 'reassignedits-error-invalid',
				$newusername->getText() )->text() . "</div>" );
			return;
		}

		$settings = array();
		// Update user in logging table if checkbox is true
		$settings['updatelogginguser'] = $request->getCheck( 'updatelogginguser' );
		// Update title in logging table if checkbox is true
		$settings['updatelogginguser'] = $request->getCheck( 'updateloggingtitle' );

		// Do the heavy lifting...
		$reassign = new ReassignEditsSQL(
			$oldusername->getText(),
			$newusername->getText(),
			$settings
		);
		if ( !$reassign->reassign() ) {
			return;
		}

		// Output success message
		$out->addWikiText( "<div class=\"successbox\">" . $this->msg( 'reassignedits-success',
			$oldusername->getText(), $newusername->getText() )->text() .
			"</div><br style=\"clear:both\" />" );
	}
}

class ReassignEditsSQL {
	/**
	 * The old username
	 *
	 * @var string
	 * @access private
	 */
	var $old;

	/**
	 * The new username
	 *
	 * @var string
	 * @access private
	 */
	var $new;

	/**
	 * @var array of settings.
	 */
	var $settings;

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
		wfProfileIn( __METHOD__ );

		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();

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
		$dbw->commit();

		wfProfileOut( __METHOD__ );
		return true;
	}
}
