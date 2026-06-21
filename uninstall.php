<?php
/**
 * Uninstall handler for Kitgenix PDF Invoicing for WooCommerce.
 *
 * This file is executed when the plugin is uninstalled via the WordPress
 * admin. It removes plugin options stored in the database. We do NOT
 * remove order meta or user data to avoid accidental data loss.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options from single-site and network options.
delete_option( 'kitgenix_pdf_invoicing_settings' );
delete_site_option( 'kitgenix_pdf_invoicing_settings' );
delete_option( 'kitgenix_pdf_invoicing_event_log' );
delete_site_option( 'kitgenix_pdf_invoicing_event_log' );

// Remove anonymous metrics.
delete_option( 'kitgenix_pdf_invoicing_for_woocommerce_metrics' );
delete_site_option( 'kitgenix_pdf_invoicing_for_woocommerce_metrics' );

// Remove numbering sequence counters.
delete_option( 'kitgenix_pdf_invoicing_for_woocommerce_number_sequences' );
delete_site_option( 'kitgenix_pdf_invoicing_for_woocommerce_number_sequences' );

// Remove plugin-only transients.
delete_transient( 'kitgenix_pdf_invoicing_for_woocommerce_do_activation_redirect' );
