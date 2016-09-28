<?php

require_once( ABSPATH . '/wp-includes/class-phpmailer.php' );

class GES_Mock_Mailer extends BP_PHPMailer {
	/**
	 * Send email(s).
	 *
	 * @since 2.5.0
	 *
	 * @param BP_Email $email Email to send.
	 * @return bool|WP_Error Returns true if email send, else a descriptive WP_Error.
	 */
	public function bp_email( BP_Email $email ) {
		global $ges_mockmailer;

		if ( empty( $ges_mockmailer ) ) {
			if ( ! class_exists( 'PHPMailer' ) ) {
				require_once ABSPATH . WPINC . '/class-phpmailer.php';
				require_once ABSPATH . WPINC . '/class-smtp.php';
			}

			$ges_mockmailer = new MockPHPMailer( true );
		}


		/*
		 * Resets.
		 */

		$ges_mockmailer->clearAllRecipients();
		$ges_mockmailer->clearAttachments();
		$ges_mockmailer->clearCustomHeaders();
		$ges_mockmailer->clearReplyTos();
		$ges_mockmailer->Sender = '';


		/*
		 * Set up.
		 */

		$ges_mockmailer->IsMail();
		$ges_mockmailer->CharSet = bp_get_option( 'blog_charset' );


		/*
		 * Content.
		 */

		$ges_mockmailer->Subject = $email->get_subject( 'replace-tokens' );
		$content_plaintext  = MockPHPMailer::normalizeBreaks( $email->get_content_plaintext( 'replace-tokens' ) );

		if ( $email->get( 'content_type' ) === 'html' ) {
			$ges_mockmailer->msgHTML( $email->get_template( 'add-content' ) );
			$ges_mockmailer->AltBody = $content_plaintext;

		} else {
			$ges_mockmailer->IsHTML( false );
			$ges_mockmailer->Body = $content_plaintext;
		}

		$recipient = $email->get_from();
		try {
			$ges_mockmailer->SetFrom( $recipient->get_address(), $recipient->get_name(), false );
		} catch ( phpmailerException $e ) {
		}

		$recipient = $email->get_reply_to();
		try {
			$ges_mockmailer->addReplyTo( $recipient->get_address(), $recipient->get_name() );
		} catch ( phpmailerException $e ) {
		}

		$recipients = $email->get_to();
		foreach ( $recipients as $recipient ) {
			try {
				$ges_mockmailer->AddAddress( $recipient->get_address(), $recipient->get_name() );
			} catch ( phpmailerException $e ) {
			}
		}

		$recipients = $email->get_cc();
		foreach ( $recipients as $recipient ) {
			try {
				$ges_mockmailer->AddCc( $recipient->get_address(), $recipient->get_name() );
			} catch ( phpmailerException $e ) {
			}
		}

		$recipients = $email->get_bcc();
		foreach ( $recipients as $recipient ) {
			try {
				$ges_mockmailer->AddBcc( $recipient->get_address(), $recipient->get_name() );
			} catch ( phpmailerException $e ) {
			}
		}

		$headers = $email->get_headers();
		foreach ( $headers as $name => $content ) {
			$ges_mockmailer->AddCustomHeader( $name, $content );
		}


		/**
		 * Fires after PHPMailer is initialised.
		 *
		 * @since 2.5.0
		 *
		 * @param PHPMailer $ges_mockmailer The PHPMailer instance.
		 */
		do_action( 'bp_phpmailer_init', $ges_mockmailer );

		/** This filter is documented in wp-includes/pluggable.php */
		do_action_ref_array( 'phpmailer_init', array( &$ges_mockmailer ) );

		try {
			return $ges_mockmailer->Send();
		} catch ( phpmailerException $e ) {
			return new WP_Error( $e->getCode(), $e->getMessage(), $email );
		}
	}
}