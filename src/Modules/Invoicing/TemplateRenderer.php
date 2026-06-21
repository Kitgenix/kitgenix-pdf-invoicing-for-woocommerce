<?php

namespace Kitgenix\PDF_Invoicing\Modules\Invoicing;

use Kitgenix\PDF_Invoicing\Modules\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class TemplateRenderer {

    /**
     * Render any document type as HTML.
     */
    public function render_document( \WC_Order $order, string $type ): string {
        $settings = Settings::get_all();

        $style = isset( $settings['template_style'] ) ? sanitize_key( (string) $settings['template_style'] ) : 'standard';
        $allowed_styles = [ 'standard', 'simple', 'modern', 'business' ];
        if ( ! in_array( $style, $allowed_styles, true ) ) {
            $style = 'standard';
        }

        // Allow full override of template path.
        $custom_path = apply_filters(
            'kitgenix_pdf_document_template_path',
            '',
            $order,
            $type,
            $settings
        );

        if ( $custom_path && file_exists( $custom_path ) ) {
            $template = $custom_path;
        } else {
            // Map type → slug (invoice, packing-slip, credit-note etc.).
            $slug      = str_replace( '_', '-', $type );
            $base_slug = str_replace( '_', '-', DocumentTypes::get_template_base_type( $type ) );

            // 1) Theme override (new preferred location):
            //    `kitgenix-pdf-invoicing-for-woocommerce/{style}/{slug}.php`
            // 2) Theme override (legacy location):
            //    `kitgenix-pdf-invoicing-for-woocommerce/{slug}.php`
            $template = locate_template(
                [
                    'kitgenix-pdf-invoicing-for-woocommerce/' . $style . '/' . $slug . '.php',
                    'kitgenix-pdf-invoicing-for-woocommerce/' . $style . '/' . $base_slug . '.php',
                    'kitgenix-pdf-invoicing-for-woocommerce/standard/' . $slug . '.php',
                    'kitgenix-pdf-invoicing-for-woocommerce/standard/' . $base_slug . '.php',
                    'kitgenix-pdf-invoicing-for-woocommerce/' . $slug . '.php',
                    'kitgenix-pdf-invoicing-for-woocommerce/' . $base_slug . '.php',
                ]
            );

            if ( ! $template ) {
                // Plugin templates: prefer selected style, fallback to standard, then legacy root.
                $candidates = [
                    KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH . 'templates/' . $style . '/' . $slug . '.php',
                    KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH . 'templates/' . $style . '/' . $base_slug . '.php',
                    KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH . 'templates/standard/' . $slug . '.php',
                    KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH . 'templates/standard/' . $base_slug . '.php',
                    KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH . 'templates/' . $slug . '.php',
                    KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH . 'templates/' . $base_slug . '.php',
                ];

                foreach ( $candidates as $candidate ) {
                    if ( $candidate && file_exists( $candidate ) ) {
                        $template = $candidate;
                        break;
                    }
                }

                if ( ! $template ) {
                    // Final fallback: use plugin standard invoice template.
                    $template = KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH . 'templates/standard/invoice.php';
                }
            }
        }

        // 1) Render inner template into $content.
        ob_start();

        // Expose $order, $settings, $document_type in the template.
        $document_type = $type; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $order         = $order; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $settings      = $settings; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        include $template;

        $content = (string) ob_get_clean();

        // 2) Wrap it using html-document-wrapper.php in the same templates directory, if available.
        $wrapper = rtrim( dirname( $template ), '/\\' ) . '/html-document-wrapper.php';
        if ( ! file_exists( $wrapper ) ) {
            $html = $content; // fallback to previous behaviour
        } else {
            ob_start();
            // wrapper expects $content, $document_type, $order, $settings
            include $wrapper;
            $html = (string) ob_get_clean();
        }

        /**
         * Filter HTML for any document before PDF engine sees it.
         */
        $html = (string) apply_filters(
            'kitgenix_pdf_document_html',
            $html,
            $order,
            $type,
            $settings
        );

        // Backwards-compat filter for invoice only.
        if ( DocumentTypes::INVOICE === $type ) {
            $html = (string) apply_filters(
                'kitgenix_pdf_invoice_html',
                $html,
                $order,
                $settings
            );
        }

        return $html;
    }

    /**
     * Backwards-compatible helper for invoices.
     */
    public function render_invoice( \WC_Order $order ): string {
        return $this->render_document( $order, DocumentTypes::INVOICE );
    }
}
