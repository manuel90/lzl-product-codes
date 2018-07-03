<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
do_action( 'woocommerce_email_header', $email_heading, $email );

echo $message;

do_action( 'woocommerce_email_footer', $email );
