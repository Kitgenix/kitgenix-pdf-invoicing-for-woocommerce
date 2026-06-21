<?php

namespace Kitgenix\PDF_Invoicing\Modules\Settings;

use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes;

defined( 'ABSPATH' ) || exit;

class Settings {

    public const OPTION_KEY = 'kitgenix_pdf_invoicing_settings';

    /**
     * Default email attachment mapping.
     *
     * Keys are WooCommerce email IDs, values are arrays of document type => bool.
     * Document types: 'invoice', 'packing_slip', 'credit_note'.
     */
    public static function get_default_email_attachments(): array {
        return [
            'customer_processing_order' => [
                'invoice'      => true,
                'packing_slip' => false,
                'credit_note'  => false,
                'receipt'      => false,
            ],
            'customer_completed_order'  => [
                'invoice'      => true,
                'packing_slip' => false,
                'credit_note'  => false,
                'receipt'      => true,   // default: send receipt on completed
            ],
            // Attach credit note on refund notification by default.
            // WooCommerce refund email id is `customer_refunded_order`.
            'customer_refunded_order'  => [
                'invoice'      => false,
                'packing_slip' => false,
                'credit_note'  => true,
                'receipt'      => false,
            ],
            'new_order'                 => [
                'invoice'      => false,
                'packing_slip' => true,
                'credit_note'  => false,
                'receipt'      => false,
            ],
        ];
    }

    /**
     * Default document generation rules.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function get_default_document_generation_rules(): array {
        return [
            DocumentTypes::INVOICE => [
                'enabled'             => true,
                'allowed_statuses'    => '',
                'payment_requirement' => 'any',
            ],
            DocumentTypes::RECEIPT => [
                'enabled'             => true,
                'allowed_statuses'    => '',
                'payment_requirement' => 'any',
            ],
            DocumentTypes::PACKING_SLIP => [
                'enabled'             => true,
                'allowed_statuses'    => '',
                'payment_requirement' => 'any',
            ],
            DocumentTypes::CREDIT_NOTE => [
                'enabled'             => true,
                'allowed_statuses'    => '',
                'payment_requirement' => 'any',
            ],
            DocumentTypes::PRO_FORMA_INVOICE => [
                'enabled'             => true,
                'allowed_statuses'    => '',
                'payment_requirement' => 'unpaid',
            ],
            DocumentTypes::DELIVERY_NOTE => [
                'enabled'             => true,
                'allowed_statuses'    => 'processing,completed',
                'payment_requirement' => 'any',
            ],
            DocumentTypes::STATEMENT => [
                'enabled'             => true,
                'allowed_statuses'    => '',
                'payment_requirement' => 'any',
            ],
        ];
    }

    /**
     * Return settings merged with defaults.
     */
    public static function get_all(): array {
        $defaults = [
            'company_name'      => get_bloginfo( 'name' ),
            'company_address'   => '',
            'company_email'     => get_bloginfo( 'admin_email' ),
            'company_phone'     => '',
            'tax_id'            => '',
            // Template pack (folder name under templates/).
            'template_style'    => 'standard',
            'invoice_prefix'    => '',
            'receipt_prefix'    => '',
            'packing_slip_prefix' => 'PS-',
            'credit_note_prefix'=> 'CN-',
            'invoice_number_format' => '{prefix}{order_number}',
            'receipt_number_format' => '{prefix}{order_number}',
            'packing_slip_number_format' => '{prefix}{order_number}',
            'credit_note_number_format' => '{prefix}{order_number}-{refund_sequence}',
            'number_sequence_padding' => 4,
            'number_sequence_reset' => 'none',
            'fiscal_year_start_month' => 1,
            'localization_pack'  => 'standard',
            'tax_display_mode'   => 'exclusive',
            'document_date_format' => '',
            'show_currency_code' => false,
            'tax_label_override' => '',
            'tax_registration_label_override' => '',
            'show_item_sku'      => true,
            'show_item_meta'     => true,
            'show_item_tax'      => false,
            'show_payment_method'=> true,
            'show_transaction_id'=> true,
            'show_shipping_method' => true,
            'show_tax_totals'    => true,
            'show_customer_note' => true,
            'show_internal_note' => false,
            'custom_order_fields'=> '',
            'document_notes'    => '',
            'logo_id'           => 0,
            'footer_text'       => '',
            'footer_bg_color'   => '#ffffff',
            'footer_text_color' => '#333333',
            // Styling / brand colors.
            'primary_color'     => '#111827', // Dark heading / primary color.
            'accent_color'      => '#2563eb', // Accent (lines, highlights).
            'text_color'        => '#111827', // Main text color.
            'muted_text_color'  => '#6b7280', // Secondary labels.
            'border_color'      => '#e5e7eb', // Table / section borders.
            'table_header_bg'   => '#f3f4f6', // Table header background.
            'background_color'  => '#ffffff', // Document background.
            'designer_header_alignment' => 'left',
            'designer_logo_scale'       => 'medium',
            'designer_density'          => 'comfortable',
            'designer_panel_style'      => 'minimal',
            'designer_table_style'      => 'clean',
            'designer_totals_emphasis'  => 'standard',
            'designer_footer_alignment' => 'left',
            'document_generation_rules' => self::get_default_document_generation_rules(),
            'email_attachments' => self::get_default_email_attachments(),
        ];

        $stored = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        // Ensure email_attachments structure always exists and is merged with defaults.
        if ( ! isset( $stored['email_attachments'] ) || ! is_array( $stored['email_attachments'] ) ) {
            $stored['email_attachments'] = [];
        }

        $stored['email_attachments'] = wp_parse_args(
            $stored['email_attachments'],
            self::get_default_email_attachments()
        );

        $default_document_rules = self::get_default_document_generation_rules();
        $stored_document_rules  = isset( $stored['document_generation_rules'] ) && is_array( $stored['document_generation_rules'] )
            ? $stored['document_generation_rules']
            : [];

        $normalized_document_rules = [];
        foreach ( $default_document_rules as $document_type => $rule_defaults ) {
            $rule_value = isset( $stored_document_rules[ $document_type ] ) && is_array( $stored_document_rules[ $document_type ] )
                ? $stored_document_rules[ $document_type ]
                : [];

            $normalized_document_rules[ $document_type ] = wp_parse_args( $rule_value, $rule_defaults );
        }

        $stored['document_generation_rules'] = $normalized_document_rules;

        return wp_parse_args( $stored, $defaults );
    }
}
