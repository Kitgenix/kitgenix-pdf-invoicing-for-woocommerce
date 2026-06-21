<?php

namespace Kitgenix\PDF_Invoicing\Modules\Invoicing;

use Kitgenix\PDF_Invoicing\Core\ModuleInterface;
use Kitgenix\PDF_Invoicing\Modules\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class InvoicingModule implements ModuleInterface {

    protected PdfGenerator $pdf;

    public function __construct( PdfGenerator $pdf ) {
        $this->pdf = $pdf;
    }

    public function get_id(): string {
        return 'invoicing';
    }

    public function register(): void {
        // Central endpoint handler for all documents.
        add_action( 'template_redirect', [ $this, 'maybe_download_document' ] );
        add_filter( 'kitgenix_pdf_document_custom_css', [ $this, 'filter_document_custom_css' ], 10, 4 );
        add_filter( 'kitgenix_pdf_document_enabled', [ $this, 'filter_document_enabled' ], 10, 3 );
    }

    public function filter_document_custom_css( string $custom_css, string $document_type, \WC_Order $order, array $settings ): string {
        $designer_css = $this->get_visual_designer_css( $settings );

        if ( '' === $designer_css ) {
            return $custom_css;
        }

        return trim( $custom_css . "\n" . $designer_css );
    }

    public function filter_document_enabled( bool $enabled, \WC_Order $order, string $type ): bool {
        if ( ! $enabled ) {
            return false;
        }

        $settings = Settings::get_all();
        $rules    = isset( $settings['document_generation_rules'] ) && is_array( $settings['document_generation_rules'] )
            ? $settings['document_generation_rules']
            : [];

        $rule = isset( $rules[ $type ] ) && is_array( $rules[ $type ] )
            ? $rules[ $type ]
            : [
                'enabled'             => true,
                'allowed_statuses'    => '',
                'payment_requirement' => 'any',
            ];

        if ( empty( $rule['enabled'] ) ) {
            return false;
        }

        $allowed_statuses = $this->parse_rule_statuses( (string) ( $rule['allowed_statuses'] ?? '' ) );
        if ( ! empty( $allowed_statuses ) ) {
            $order_status = sanitize_key( (string) $order->get_status() );
            if ( ! in_array( $order_status, $allowed_statuses, true ) ) {
                return false;
            }
        }

        $payment_requirement = sanitize_key( (string) ( $rule['payment_requirement'] ?? 'any' ) );
        $is_paid             = is_callable( [ $order, 'is_paid' ] ) ? (bool) $order->is_paid() : in_array( sanitize_key( (string) $order->get_status() ), [ 'processing', 'completed' ], true );

        if ( 'paid' === $payment_requirement && ! $is_paid ) {
            return false;
        }

        if ( 'unpaid' === $payment_requirement && $is_paid ) {
            return false;
        }

        return true;
    }

    /**
     * Handle ?kitgenix_pdf=1&kitgenix_doc=invoice|packing_slip|credit_note&order_id=XX&_wpnonce=YY
     *
     * Notes:
     * - If `_wpnonce` is present it must verify for the request to continue (hard fail).
     * - If no `_wpnonce` is supplied, guest access is allowed only for invoices/receipts
     *   when a valid `order_key` (or `key`) matching the order is provided.
     */
    public function maybe_download_document(): void {
        // Require kitgenix_pdf and order_id. `_wpnonce` is optional to allow
        // guest downloads using a valid order key (see below).
        if ( empty( $_GET['kitgenix_pdf'] ) || empty( $_GET['order_id'] ) ) {
            return;
        }

        $order_id = absint( wp_unslash( $_GET['order_id'] ) );
        $doc_type = isset( $_GET['kitgenix_doc'] )
            ? sanitize_key( wp_unslash( $_GET['kitgenix_doc'] ) )
            : DocumentTypes::INVOICE;

        // Validate doc type.
        $valid_types = DocumentTypes::all();
        if ( ! in_array( $doc_type, $valid_types, true ) ) {
            wp_die( esc_html__( 'Unknown document type.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        $nonce_action = 'kitgenix_download_' . $doc_type . '_' . $order_id;
        $has_valid_nonce = false;

        // If a nonce is present, verify it and abort on failure.
        if ( isset( $_GET['_wpnonce'] ) ) {
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
                wp_die( esc_html__( 'Invalid document download link.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
            }
            $has_valid_nonce = true;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( esc_html__( 'Order not found.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        // When no nonce is supplied, require a valid WooCommerce order key.
        // This supports guest downloads and prevents unauthenticated guessing
        // of order IDs for sensitive document access.
        $provided_key = '';
        if ( isset( $_GET['key'] ) ) {
            $provided_key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
        } elseif ( isset( $_GET['order_key'] ) ) {
            $provided_key = sanitize_text_field( wp_unslash( $_GET['order_key'] ) );
        }

        $has_valid_order_key = ( $provided_key && hash_equals( (string) $order->get_order_key(), (string) $provided_key ) );

        if ( ! $has_valid_nonce && ! $has_valid_order_key ) {
            wp_die( esc_html__( 'Invalid document download link.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        $current_user_id = get_current_user_id();

        // Permissions per type:
        $allowed = false;

        // Invoices, statements, receipts, and pro forma invoices: customer who owns order OR shop manager.
        if ( in_array( $doc_type, [ DocumentTypes::INVOICE, DocumentTypes::RECEIPT, DocumentTypes::PRO_FORMA_INVOICE, DocumentTypes::STATEMENT ], true ) ) {
            $allowed = current_user_can( 'edit_shop_orders' )
                || ( is_user_logged_in() && (int) $order->get_user_id() === (int) $current_user_id )
                || $has_valid_order_key;
        }

        // Packing slips and delivery notes: staff only by default (can be filtered).
        if ( in_array( $doc_type, [ DocumentTypes::PACKING_SLIP, DocumentTypes::DELIVERY_NOTE ], true ) ) {
            $allowed = current_user_can( 'edit_shop_orders' );
        }

        // Credit notes: allow shop staff, and also allow the customer who owns
        // the order to download a credit note when at least one refund exists.
        if ( DocumentTypes::CREDIT_NOTE === $doc_type ) {
            $has_refunds = false;
            $refunds = $order->get_refunds();
            $refund_count = is_array( $refunds ) ? count( $refunds ) : 0;
            if ( $refund_count > 0 ) {
                $has_refunds = true;
            }

            $allowed = current_user_can( 'edit_shop_orders' )
                || ( is_user_logged_in() && (int) $order->get_user_id() === (int) $current_user_id && $has_refunds )
                || ( $has_valid_order_key && $has_refunds );
        }

        /**
         * Filter final permission check.
         */
        $allowed = (bool) apply_filters(
            'kitgenix_pdf_document_user_can_download',
            $allowed,
            $order,
            $doc_type
        );

        if ( ! $allowed ) {
            wp_die( esc_html__( 'You are not allowed to download this document.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        $this->pdf->stream_document( $order_id, $doc_type );
        exit;
    }

    protected function get_visual_designer_css( array $settings ): string {
        $header_alignment = $this->sanitize_choice(
            (string) ( $settings['designer_header_alignment'] ?? 'left' ),
            [ 'left', 'center', 'right' ],
            'left'
        );
        $logo_scale = $this->sanitize_choice(
            (string) ( $settings['designer_logo_scale'] ?? 'medium' ),
            [ 'small', 'medium', 'large' ],
            'medium'
        );
        $density = $this->sanitize_choice(
            (string) ( $settings['designer_density'] ?? 'comfortable' ),
            [ 'compact', 'comfortable', 'spacious' ],
            'comfortable'
        );
        $panel_style = $this->sanitize_choice(
            (string) ( $settings['designer_panel_style'] ?? 'minimal' ),
            [ 'minimal', 'boxed', 'tinted' ],
            'minimal'
        );
        $table_style = $this->sanitize_choice(
            (string) ( $settings['designer_table_style'] ?? 'clean' ),
            [ 'clean', 'striped', 'grid' ],
            'clean'
        );
        $totals_emphasis = $this->sanitize_choice(
            (string) ( $settings['designer_totals_emphasis'] ?? 'standard' ),
            [ 'standard', 'boxed', 'highlight' ],
            'standard'
        );
        $footer_alignment = $this->sanitize_choice(
            (string) ( $settings['designer_footer_alignment'] ?? 'left' ),
            [ 'left', 'center', 'right' ],
            'left'
        );

        $primary_color    = $this->normalize_hex_color( (string) ( $settings['primary_color'] ?? '#111827' ), '#111827' );
        $accent_color     = $this->normalize_hex_color( (string) ( $settings['accent_color'] ?? '#2563eb' ), '#2563eb' );
        $border_color     = $this->normalize_hex_color( (string) ( $settings['border_color'] ?? '#e5e7eb' ), '#e5e7eb' );
        $background_color = $this->normalize_hex_color( (string) ( $settings['background_color'] ?? '#ffffff' ), '#ffffff' );

        $panel_tint       = $this->mix_hex_colors( $accent_color, $background_color, 0.08 );
        $row_tint         = $this->mix_hex_colors( $accent_color, $background_color, 0.05 );
        $totals_highlight = $this->mix_hex_colors( $accent_color, $background_color, 0.16 );

        $logo_heights = [
            'small'  => 42,
            'medium' => 64,
            'large'  => 92,
        ];

        $density_map = [
            'compact' => [
                'font_size'  => 11,
                'row_v'      => 5,
                'row_h'      => 7,
                'panel_pad'  => 8,
                'note_pad'   => 10,
                'footer_pad' => 8,
                'title_gap'  => 14,
            ],
            'comfortable' => [
                'font_size'  => 12,
                'row_v'      => 8,
                'row_h'      => 10,
                'panel_pad'  => 12,
                'note_pad'   => 12,
                'footer_pad' => 10,
                'title_gap'  => 18,
            ],
            'spacious' => [
                'font_size'  => 13,
                'row_v'      => 11,
                'row_h'      => 14,
                'panel_pad'  => 16,
                'note_pad'   => 16,
                'footer_pad' => 14,
                'title_gap'  => 24,
            ],
        ];

        $density_values = $density_map[ $density ];
        $logo_height    = $logo_heights[ $logo_scale ];

        $css = [
            'body{font-size:' . $density_values['font_size'] . 'px;line-height:1.55;}',
            '.head .header,.head .shop-info,.document-type-label{text-align:' . $header_alignment . ';}',
            '.kitgenix-pdf-invoicing-for-woocommerce-logo{text-align:' . $header_alignment . ';}',
            '.kitgenix-pdf-invoicing-for-woocommerce-logo img{max-height:' . $logo_height . 'px;width:auto;}',
            '.document-type-label{margin-bottom:' . $density_values['title_gap'] . 'px;}',
            '.order-details th,.order-details td,.order-data table th,.order-data table td,.notes-totals .totals th,.notes-totals .totals td{padding:' . $density_values['row_v'] . 'px ' . $density_values['row_h'] . 'px;}',
            '.document-notes{padding:' . $density_values['note_pad'] . 'px;}',
            '#footer .footer-inner,#footer .footer-cell{text-align:' . $footer_alignment . ';padding:' . $density_values['footer_pad'] . 'px ' . $density_values['row_h'] . 'px;}',
        ];

        if ( 'boxed' === $panel_style || 'tinted' === $panel_style ) {
            $panel_background = 'tinted' === $panel_style ? $panel_tint : $background_color;

            $css[] = '.order-data-addresses .address,.order-data-addresses .order-data,.document-notes{background:' . $panel_background . ';border:1px solid ' . $border_color . ';padding:' . $density_values['panel_pad'] . 'px;}';
        }

        if ( 'striped' === $table_style ) {
            $css[] = '.order-details tbody tr:nth-child(even){background:' . $row_tint . ';}';
        } elseif ( 'grid' === $table_style ) {
            $css[] = '.order-details{border-collapse:collapse;}.order-details th,.order-details td{border:1px solid ' . $border_color . ';}';
        }

        if ( 'boxed' === $totals_emphasis ) {
            $css[] = '.notes-totals .totals{border:1px solid ' . $border_color . ';background:' . $background_color . ';}';
        } elseif ( 'highlight' === $totals_emphasis ) {
            $css[] = '.notes-totals .totals{border-top:2px solid ' . $accent_color . ';}.notes-totals .totals tfoot tr:last-child th,.notes-totals .totals tfoot tr:last-child td{background:' . $totals_highlight . ';color:' . $primary_color . ';font-weight:700;}';
        }

        return implode( "\n", $css );
    }

    protected function sanitize_choice( string $value, array $allowed, string $default ): string {
        $value = sanitize_key( $value );

        return in_array( $value, $allowed, true ) ? $value : $default;
    }

    protected function normalize_hex_color( string $value, string $default ): string {
        $value = sanitize_hex_color( $value );

        return is_string( $value ) && '' !== $value ? $value : $default;
    }

    protected function mix_hex_colors( string $foreground, string $background, float $foreground_ratio ): string {
        $foreground_rgb = $this->hex_to_rgb( $foreground );
        $background_rgb = $this->hex_to_rgb( $background );

        if ( null === $foreground_rgb || null === $background_rgb ) {
            return $background;
        }

        $foreground_ratio = max( 0.0, min( 1.0, $foreground_ratio ) );
        $background_ratio = 1 - $foreground_ratio;

        $red   = (int) round( ( $foreground_rgb['r'] * $foreground_ratio ) + ( $background_rgb['r'] * $background_ratio ) );
        $green = (int) round( ( $foreground_rgb['g'] * $foreground_ratio ) + ( $background_rgb['g'] * $background_ratio ) );
        $blue  = (int) round( ( $foreground_rgb['b'] * $foreground_ratio ) + ( $background_rgb['b'] * $background_ratio ) );

        return sprintf( '#%02x%02x%02x', $red, $green, $blue );
    }

    /**
     * @return array{r:int,g:int,b:int}|null
     */
    protected function hex_to_rgb( string $hex ): ?array {
        $hex = ltrim( $hex, '#' );

        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            return null;
        }

        return [
            'r' => hexdec( substr( $hex, 0, 2 ) ),
            'g' => hexdec( substr( $hex, 2, 2 ) ),
            'b' => hexdec( substr( $hex, 4, 2 ) ),
        ];
    }

    /**
     * @return string[]
     */
    protected function parse_rule_statuses( string $raw_statuses ): array {
        $parts = preg_split( '/[\s,]+/', $raw_statuses );
        if ( ! is_array( $parts ) ) {
            return [];
        }

        $statuses = [];
        foreach ( $parts as $part ) {
            $part = sanitize_key( str_replace( 'wc-', '', (string) $part ) );
            if ( '' !== $part ) {
                $statuses[] = $part;
            }
        }

        return array_values( array_unique( $statuses ) );
    }
}
