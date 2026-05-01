<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKP_Email_Service {

	const DEFAULT_SUBJECT_NEW     = 'Your ChromaKey Pro licence key';
	const DEFAULT_SUBJECT_REISSUE = 'Your ChromaKey Pro licence key (re-issued)';

	const DEFAULT_HTML = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:15px;color:#333;max-width:600px;margin:0 auto;padding:20px;">
  <h2 style="color:#1a1a2e;">ChromaKey Pro — Your Licence Key</h2>
  <p>Hi {CUSTOMER_NAME},</p>
  <p>Thank you for using ChromaKey Pro. Below is your licence key:</p>
  <p style="background:#f4f4f4;padding:14px 20px;border-left:4px solid #1a1a2e;font-size:18px;letter-spacing:2px;">
    <code style="font-size:18px;">{KEY}</code>
  </p>
  <table style="border-collapse:collapse;margin:16px 0;">
    <tr>
      <td style="padding:4px 12px 4px 0;color:#555;">Expires:</td>
      <td style="padding:4px 0;"><strong>{EXPIRY}</strong></td>
    </tr>
    <tr>
      <td style="padding:4px 12px 4px 0;color:#555;">Activations allowed:</td>
      <td style="padding:4px 0;"><strong>{ACTIVATION_LIMIT}</strong></td>
    </tr>
  </table>
  <p>To activate, open <strong>{PRODUCT_NAME}</strong>, go to <em>Settings &rarr; Licence</em>, and enter your key along with the email address this was sent to.</p>
  <p style="color:#888;font-size:13px;margin-top:32px;">If you did not request this key, please ignore this email or contact support.</p>
</body>
</html>';

	const DEFAULT_TEXT = 'ChromaKey Pro -- Your Licence Key

Hi {CUSTOMER_NAME},

Thank you for using ChromaKey Pro. Below is your licence key:

  {KEY}

Expires:              {EXPIRY}
Activations allowed:  {ACTIVATION_LIMIT}

To activate, open {PRODUCT_NAME}, go to Settings > Licence, and enter your key along with the email address this was sent to.

If you did not request this key, please ignore this email or contact support.';

	public static function send_licence_issued( $to_email, $customer, $raw_key, $licence ) {
		return self::send( $to_email, $customer, $raw_key, $licence, 'email_subject_new', self::DEFAULT_SUBJECT_NEW );
	}

	public static function send_licence_reissued( $to_email, $customer, $raw_key, $licence ) {
		return self::send( $to_email, $customer, $raw_key, $licence, 'email_subject_reissue', self::DEFAULT_SUBJECT_REISSUE );
	}

	private static function send( $to_email, $customer, $raw_key, $licence, $subject_key, $default_subject ) {
		$placeholders = array(
			'{CUSTOMER_NAME}'     => $customer->display_name ?: $to_email,
			'{KEY}'               => $raw_key,
			'{EXPIRY}'            => date( 'd M Y', strtotime( $licence->expires_at ) ),
			'{ACTIVATION_LIMIT}'  => (int) $licence->activation_limit,
			'{PRODUCT_NAME}'      => 'ChromaKey Pro',
		);

		$html_template = CKP_Settings::get( 'email_template_html', self::DEFAULT_HTML );
		$text_template = CKP_Settings::get( 'email_template_text', self::DEFAULT_TEXT );
		$subject       = CKP_Settings::get( $subject_key, $default_subject );
		$from_name     = CKP_Settings::get( 'email_from_name', 'ChromaKey Pro' );
		$from_email    = get_option( 'admin_email' );

		$html_body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $html_template );
		$alt_body  = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $text_template );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);

		// Attach plain-text alternative via PHPMailer (bundled with WordPress).
		$set_alt = function ( $phpmailer ) use ( $alt_body ) {
			$phpmailer->AltBody = $alt_body;
		};
		add_action( 'phpmailer_init', $set_alt );

		$result = wp_mail( $to_email, $subject, $html_body, $headers );

		remove_action( 'phpmailer_init', $set_alt );

		return $result;
	}
}
