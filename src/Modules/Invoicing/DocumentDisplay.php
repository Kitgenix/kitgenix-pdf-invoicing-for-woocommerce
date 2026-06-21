<?php

namespace Kitgenix\PDF_Invoicing\Modules\Invoicing;

defined( 'ABSPATH' ) || exit;

final class DocumentDisplay {

    public static function is_enabled( array $settings, string $setting_key, bool $default = false ): bool {
        $value = $settings[ $setting_key ] ?? $default;

        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_numeric( $value ) ) {
            return (bool) absint( (string) $value );
        }

        return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on' ], true );
    }

    public static function get_localization_pack_options(): array {
        $options = [];

        foreach ( self::get_localization_packs() as $pack_id => $pack ) {
            $options[ $pack_id ] = (string) ( $pack['label'] ?? $pack_id );
        }

        return $options;
    }

    public static function get_document_title( string $document_type, array $settings ): string {
        $pack = self::get_localization_pack( $settings );

        if ( isset( $pack['document_titles'][ $document_type ] ) ) {
            $title = trim( (string) $pack['document_titles'][ $document_type ] );
            if ( '' !== $title ) {
                return $title;
            }
        }

        return DocumentTypes::get_label( $document_type );
    }

    public static function get_tax_term( array $settings ): string {
        $override = trim( (string) ( $settings['tax_label_override'] ?? '' ) );
        if ( '' !== $override ) {
            return $override;
        }

        $pack = self::get_localization_pack( $settings );

        return (string) ( $pack['tax_term'] ?? __( 'VAT', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
    }

    public static function get_tax_registration_label( array $settings ): string {
        $override = trim( (string) ( $settings['tax_registration_label_override'] ?? '' ) );
        if ( '' !== $override ) {
            return $override;
        }

        $pack = self::get_localization_pack( $settings );

        return (string) ( $pack['tax_registration_label'] ?? __( 'VAT Number', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
    }

    public static function format_tax_registration( string $tax_id, array $settings ): string {
        $tax_id = trim( wp_strip_all_tags( $tax_id ) );
        if ( '' === $tax_id ) {
            return '';
        }

        return sprintf(
            /* translators: 1: tax/VAT registration label, 2: stored tax/VAT value. */
            __( '%1$s: %2$s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            self::get_tax_registration_label( $settings ),
            $tax_id
        );
    }

    public static function get_tax_display_mode( array $settings ): string {
        $mode = sanitize_key( (string) ( $settings['tax_display_mode'] ?? 'exclusive' ) );

        return in_array( $mode, [ 'exclusive', 'inclusive' ], true ) ? $mode : 'exclusive';
    }

    public static function format_timestamp( int $timestamp, array $settings ): string {
        if ( $timestamp <= 0 ) {
            return '';
        }

        $format = trim( (string) ( $settings['document_date_format'] ?? '' ) );
        if ( '' === $format ) {
            $format = wc_date_format();
        }

        return date_i18n( $format, $timestamp );
    }

    public static function format_order_created_date( \WC_Order $order, array $settings ): string {
        return $order->get_date_created()
            ? self::format_timestamp( $order->get_date_created()->getTimestamp(), $settings )
            : '';
    }

    public static function format_amount( float $amount, \WC_Order $order, array $settings ): string {
        $formatted = wc_price( $amount, [ 'currency' => $order->get_currency() ] );

        if ( ! self::is_enabled( $settings, 'show_currency_code', false ) ) {
            return (string) $formatted;
        }

        return $formatted . ' <span class="kitgenix-pdf-currency-code">' . esc_html( (string) $order->get_currency() ) . '</span>';
    }

    public static function get_order_totals( \WC_Order $order, array $settings ): array {
        $tax_display = 'inclusive' === self::get_tax_display_mode( $settings ) ? 'incl' : 'excl';
        $totals      = [];

        if ( is_callable( [ $order, 'get_order_item_totals' ] ) ) {
            $totals = $order->get_order_item_totals( $tax_display );
        } elseif ( function_exists( 'wc_get_order_item_totals' ) ) {
            $totals = wc_get_order_item_totals(
                $order,
                [
                    'tax_display' => $tax_display,
                ]
            );
        }

        $totals = is_array( $totals ) ? $totals : [];
        $totals = self::localize_total_labels( $totals, $settings );

        return self::get_visible_totals( $totals, $settings );
    }

    public static function get_line_item_unit_amount( \WC_Order_Item $item, array $settings ): float {
        $qty = (int) $item->get_quantity();
        if ( $qty <= 0 ) {
            return 0.0;
        }

        if ( 'inclusive' === self::get_tax_display_mode( $settings ) ) {
            return ( (float) $item->get_subtotal() + (float) $item->get_subtotal_tax() ) / $qty;
        }

        return (float) $item->get_subtotal() / $qty;
    }

    public static function get_line_item_total_amount( \WC_Order_Item $item, array $settings ): float {
        if ( 'inclusive' === self::get_tax_display_mode( $settings ) ) {
            return (float) $item->get_total() + (float) $item->get_total_tax();
        }

        return (float) $item->get_total();
    }

    public static function get_price_column_label( array $settings ): string {
        return self::append_tax_context_to_label(
            __( 'Price', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            $settings
        );
    }

    public static function get_total_column_label( array $settings ): string {
        return self::append_tax_context_to_label(
            __( 'Total', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            $settings
        );
    }

    public static function get_credited_amount_label( array $settings ): string {
        return self::append_tax_context_to_label(
            __( 'Amount Credited', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            $settings
        );
    }

    public static function get_order_data_rows( \WC_Order $order, array $settings ): array {
        $rows = [];

        if ( self::is_enabled( $settings, 'show_payment_method', true ) && $order->get_payment_method_title() ) {
            $rows[] = [
                'label' => __( 'Payment Method', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'value' => (string) $order->get_payment_method_title(),
            ];
        }

        if ( self::is_enabled( $settings, 'show_transaction_id', true ) && $order->get_transaction_id() ) {
            $rows[] = [
                'label' => __( 'Transaction ID', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'value' => (string) $order->get_transaction_id(),
            ];
        }

        if ( self::is_enabled( $settings, 'show_shipping_method', true ) && $order->get_shipping_method() ) {
            $rows[] = [
                'label' => __( 'Shipping Method', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'value' => (string) $order->get_shipping_method(),
            ];
        }

        foreach ( self::get_custom_order_fields( $order, $settings ) as $field ) {
            $rows[] = $field;
        }

        return $rows;
    }

    public static function get_custom_order_fields( \WC_Order $order, array $settings ): array {
        $rows = [];
        $raw  = isset( $settings['custom_order_fields'] ) ? (string) $settings['custom_order_fields'] : '';

        foreach ( self::parse_custom_order_fields( $raw ) as $field ) {
            $value = $order->get_meta( $field['key'], true );

            if ( '' === self::normalize_value( $value ) ) {
                continue;
            }

            $rows[] = [
                'label' => $field['label'],
                'value' => self::normalize_value( $value ),
            ];
        }

        return $rows;
    }

    public static function get_line_item_details( \WC_Order_Item $item, ?\WC_Product $product, \WC_Order $order, array $settings ): array {
        $details = [];

        if ( self::is_enabled( $settings, 'show_item_sku', true ) && $product instanceof \WC_Product ) {
            $sku = trim( (string) $product->get_sku() );
            if ( '' !== $sku ) {
                $details[] = sprintf(
                    '<p class="sku"><span class="label">%s</span> %s</p>',
                    esc_html__( 'SKU:', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    esc_html( $sku )
                );
            }
        }

        if ( self::is_enabled( $settings, 'show_item_meta', true ) ) {
            $meta_html = wc_display_item_meta( $item, [ 'echo' => false ] );
            if ( is_string( $meta_html ) && '' !== trim( $meta_html ) ) {
                $details[] = $meta_html;
            }
        }

        if ( self::is_enabled( $settings, 'show_item_tax', false ) ) {
            $tax_total = abs( (float) $item->get_total_tax() );
            if ( $tax_total > 0 ) {
                $details[] = sprintf(
                    '<p class="line-tax"><span class="label">%s</span> %s</p>',
                    esc_html( self::get_tax_term( $settings ) . ':' ),
                    wp_kses_post( self::format_amount( $tax_total, $order, $settings ) )
                );
            }
        }

        return $details;
    }

    public static function get_note_blocks( \WC_Order $order, array $settings ): array {
        $blocks = [];
        $document_notes = trim( (string) ( $settings['document_notes'] ?? '' ) );

        if ( '' !== $document_notes ) {
            $blocks[] = [
                'title'   => __( 'Notes', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'content' => $document_notes,
            ];
        }

        if ( self::is_enabled( $settings, 'show_customer_note', true ) ) {
            $customer_note = trim( (string) $order->get_customer_note() );
            if ( '' !== $customer_note ) {
                $blocks[] = [
                    'title'   => __( 'Customer Note', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    'content' => $customer_note,
                ];
            }
        }

        if ( self::is_enabled( $settings, 'show_internal_note', false ) ) {
            $internal_note = self::get_latest_internal_note( $order );
            if ( '' !== $internal_note ) {
                $blocks[] = [
                    'title'   => __( 'Internal Note', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    'content' => $internal_note,
                ];
            }
        }

        return $blocks;
    }

    public static function get_visible_totals( array $totals, array $settings ): array {
        if ( self::is_enabled( $settings, 'show_tax_totals', true ) ) {
            return $totals;
        }

        $tax_term = strtolower( trim( self::get_tax_term( $settings ) ) );

        return array_filter(
            $totals,
            static function ( $total, $key ) use ( $tax_term ): bool {
                $label = is_array( $total ) ? wp_strip_all_tags( (string) ( $total['label'] ?? '' ) ) : '';
                $key   = (string) $key;

                $normalized_label = strtolower( $label );

                return false === strpos( strtolower( $key ), 'tax' )
                    && false === strpos( $normalized_label, 'tax' )
                    && false === strpos( $normalized_label, 'vat' )
                    && ( '' === $tax_term || false === strpos( $normalized_label, $tax_term ) );
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    private static function get_localization_pack( array $settings ): array {
        $packs   = self::get_localization_packs();
        $pack_id = sanitize_key( (string) ( $settings['localization_pack'] ?? 'standard' ) );

        return $packs[ $pack_id ] ?? $packs['standard'];
    }

    private static function get_localization_packs(): array {
        return [
            'standard' => [
                'label'                  => __( 'Standard / VAT', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_term'               => __( 'VAT', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_registration_label' => __( 'VAT Number', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'document_titles'        => [],
            ],
            'eu_vat' => [
                'label'                  => __( 'EU VAT', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_term'               => __( 'VAT', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_registration_label' => __( 'VAT Number', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'document_titles'        => [],
            ],
            'uk_vat' => [
                'label'                  => __( 'United Kingdom VAT', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_term'               => __( 'VAT', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_registration_label' => __( 'VAT Registration Number', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'document_titles'        => [],
            ],
            'us_sales_tax' => [
                'label'                  => __( 'United States Sales Tax', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_term'               => __( 'Sales Tax', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_registration_label' => __( 'Tax ID', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'document_titles'        => [
                    DocumentTypes::CREDIT_NOTE => __( 'Credit Memo', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                ],
            ],
            'canada_gst_hst' => [
                'label'                  => __( 'Canada GST / HST', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_term'               => __( 'GST/HST', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_registration_label' => __( 'GST/HST Number', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'document_titles'        => [],
            ],
            'australia_gst' => [
                'label'                  => __( 'Australia GST', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_term'               => __( 'GST', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tax_registration_label' => __( 'ABN', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'document_titles'        => [
                    DocumentTypes::INVOICE           => __( 'Tax Invoice', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    DocumentTypes::PRO_FORMA_INVOICE => __( 'Pro Forma Tax Invoice', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    DocumentTypes::CREDIT_NOTE       => __( 'Adjustment Note', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                ],
            ],
        ];
    }

    private static function localize_total_labels( array $totals, array $settings ): array {
        $tax_term = self::get_tax_term( $settings );

        foreach ( $totals as $key => $total ) {
            if ( ! is_array( $total ) ) {
                continue;
            }

            $label = wp_strip_all_tags( (string) ( $total['label'] ?? '' ) );
            if ( '' === $label ) {
                continue;
            }

            $label = preg_replace( '/\bVAT\b/i', $tax_term, $label ) ?: $label;
            $label = preg_replace( '/\bTax\b/i', $tax_term, $label ) ?: $label;

            if ( in_array( (string) $key, [ 'cart_subtotal', 'order_total' ], true ) ) {
                $label = self::append_tax_context_to_label( $label, $settings );
            }

            $totals[ $key ]['label'] = $label;
        }

        return $totals;
    }

    private static function append_tax_context_to_label( string $label, array $settings ): string {
        $mode     = self::get_tax_display_mode( $settings );
        $tax_term = self::get_tax_term( $settings );
        $context  = 'inclusive' === $mode
            ? sprintf(
                /* translators: %s: tax label such as VAT, GST, or Sales Tax. */
                __( 'Incl. %s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                $tax_term
            )
            : sprintf(
                /* translators: %s: tax label such as VAT, GST, or Sales Tax. */
                __( 'Excl. %s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                $tax_term
            );

        return $label . ' (' . $context . ')';
    }

    private static function parse_custom_order_fields( string $raw ): array {
        $fields = [];
        $lines  = preg_split( '/\r\n|\r|\n/', $raw );

        if ( ! is_array( $lines ) ) {
            return $fields;
        }

        foreach ( $lines as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }

            $parts = array_map( 'trim', explode( '|', $line, 2 ) );
            $key   = $parts[0] ?? '';
            $label = $parts[1] ?? '';

            if ( '' === $key ) {
                continue;
            }

            if ( '' === $label ) {
                $label = ucwords( str_replace( [ '_', '-' ], ' ', $key ) );
            }

            $fields[] = [
                'key'   => $key,
                'label' => $label,
            ];
        }

        return $fields;
    }

    private static function get_latest_internal_note( \WC_Order $order ): string {
        if ( ! function_exists( 'wc_get_order_notes' ) ) {
            return '';
        }

        $notes = wc_get_order_notes(
            [
                'order_id' => $order->get_id(),
                'type'     => 'internal',
                'limit'    => 1,
            ]
        );

        if ( ! is_array( $notes ) || empty( $notes ) ) {
            return '';
        }

        $note = reset( $notes );
        if ( ! is_object( $note ) || empty( $note->content ) ) {
            return '';
        }

        return trim( wp_strip_all_tags( (string) $note->content ) );
    }

    private static function normalize_value( $value ): string {
        if ( is_array( $value ) ) {
            $value = array_map( [ self::class, 'normalize_value' ], $value );
            $value = array_filter( $value, static fn( string $item ): bool => '' !== $item );

            return implode( ', ', $value );
        }

        if ( is_object( $value ) ) {
            return wp_json_encode( $value ) ?: '';
        }

        if ( is_bool( $value ) ) {
            return $value ? __( 'Yes', 'kitgenix-pdf-invoicing-for-woocommerce' ) : __( 'No', 'kitgenix-pdf-invoicing-for-woocommerce' );
        }

        return trim( wp_strip_all_tags( (string) $value ) );
    }
}