<?php
/**
 * Plugin Name:       PRC Term Data Store
 * Description:       Loads Composer autoload for the prc/term-data-store library (test / wp-env entry).
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Pew Research Center
 * License:           GPL-2.0-or-later
 * Text Domain:       prc-term-data-store
 *
 * @package PRC\TDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prc_term_data_store_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $prc_term_data_store_autoload ) ) {
	require_once $prc_term_data_store_autoload;
}
unset( $prc_term_data_store_autoload );
