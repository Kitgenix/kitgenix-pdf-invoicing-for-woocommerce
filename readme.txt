=== Kitgenix PDF Invoicing for WooCommerce ===
Contributors: kitgenix
Donate link: https://buymeacoffee.com/kitgenix
Tags: woocommerce, invoices, pdf, receipts, packing-slips
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.1.3
Requires Plugins: woocommerce
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Plugin URI: https://wordpress.org/plugins/kitgenix-pdf-invoicing-for-woocommerce/
Author: Kitgenix
Author URI: https://kitgenix.com/
Author Plugin URI: https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce
Documentation URI: https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce/documentation
Support URI: https://wordpress.org/support/plugin/kitgenix-pdf-invoicing-for-woocommerce/
Author Support URI: https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce/support
Feature Request URI: https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce/feature-request

Generate WooCommerce PDF invoices, receipts, packing slips, and credit notes with secure downloads, templates, and email attachments.

== Description ==

Kitgenix PDF Invoicing for WooCommerce helps stores generate professional PDF documents from WooCommerce orders without a heavy stack.

It supports core document workflows out of the box:
- Invoices
- Receipts
- Packing Slips
- Credit Notes
- Pro Forma Invoices
- Delivery Notes
- Statements

Key capabilities include:
- Secure admin and customer downloads with nonce and capability checks
- Batch ZIP exports from the WooCommerce orders list
- Optional archived PDF reuse for stable re-downloads
- Numbering controls with prefixes, padding, and reset periods
- Rule-based document availability by status/payment state
- Email attachment support for selected document types
- Bundled templates with style and branding controls

The plugin is built for practical operations: support teams can quickly re-deliver documents, fulfilment teams can work with packing slips and delivery notes, and finance teams can keep consistent invoice/credit-note references.

For setup details and advanced options, see the bundled documentation and support links in this readme.

== Secure Dompdf PDF rendering ==
- Renders HTML templates into PDF (A4 portrait by default)
- Remote fetching disabled by default
- Dompdf “chroot” restricts filesystem access to allowed paths
- PHP in templates disabled by default (advanced opt-in only)

== Template overrides (theme and agency friendly) ==
Templates can be overridden without editing plugin files.

Template resolution order:
1) Full override via filter
2) Theme override path:
  kitgenix-pdf-invoicing-for-woocommerce/{style}/
3) Plugin templates fallback:
  templates/{style}/

Where `{style}` is the active template pack selected in settings: `standard`, `simple`, `modern`, or `business`.
For compatibility, the resolver also checks `.../standard/` and legacy root locations if a file is not found.

== Template packs ==

Choose between four bundled design packs:
- Standard
- Simple
- Modern
- Business

Each pack has its own document templates and stylesheet, while still using the same company details, logo, footer, notes, and colour settings from the plugin options.

== Visual template designer ==

Non-technical stores can now reshape the bundled document layouts without editing PHP templates.

The Brand & Styling tab includes no-code controls for:
- header alignment across the logo, company block, and document title
- logo scale for compact or brand-led layouts
- document density for tighter or more spacious tables and notes
- boxed or tinted information panels around address/order-data sections
- clean, striped, or grid-style item tables
- boxed totals panels or highlighted final totals
- footer alignment for legal text, bank details, or closing notes

These controls layer on top of the bundled Standard, Simple, Modern, and Business packs, so stores can fine-tune the presentation without creating a theme override.

== Customer downloads (My Account) ==
Optional customer-facing downloads for the order owner:
- Order details page buttons:
  - Download Invoice (PDF)
  - Download Credit Note (PDF) (only when refunds exist)
- My Account → Orders table actions:
  - View Invoice
  - View Credit Note (when refunds exist)

Customer downloads are nonce-protected and/or can be validated by order key for guest access (see “Download permissions”).

== Download permissions & security ==
PDF rendering supports secure query-arg requests:
- kitgenix_pdf=1
- kitgenix_doc={type}
- order_id={id}
- _wpnonce=...

Guest access (without a nonce) is permitted only when a valid WooCommerce order key is provided:
- key= or order_key= must match the order’s key

Default per-document rules:
- Invoice + Receipt + Pro Forma Invoice + Statement:
  - order owner OR shop staff OR valid order key
- Packing Slip + Delivery Note:
  - shop staff only (by default)
- Credit Note:
  - staff OR order owner (only if refunds exist) OR valid order key (only if refunds exist)

Final permission gate is filterable, and document availability can also be narrowed through the built-in rules table or the `kitgenix_pdf_document_enabled` filter.

== Email attachments (configurable) ==
Attach PDFs to WooCommerce emails using settings and filters.
The plugin hooks WooCommerce’s email attachment pipeline, generates PDFs as temporary files for each email, attaches them, and cleans up automatically.

Sensible defaults (customisable):
- Invoice: Processing + Completed
- Receipt: Completed
- Credit Note: Refunded
- Packing Slip: New Order (admin email)

Email workflow tools in settings:
- Preview the currently mapped document set against a specific WooCommerce order
- Open live PDF previews for each eligible mapped document from the email settings tab
- Send an attachment-only test email to a safe inbox before relying on the live WooCommerce workflow
- Reuse the same mapping, eligibility checks, and PDF-generation pipeline as production email attachments

== Filenames, streaming vs download ==
- Default filename: {type}-{order_number}.pdf (filterable)
- Streams inline by default (Attachment=false), unless filtered to force download
- Reuses archived immutable PDFs when available before falling back to fresh rendering
- Generates temporary files for email attachments, cleaned up automatically
- Generates temporary ZIP archives for batch exports, cleaned up automatically
- Tracks simple generation metrics in an option (counts successful generations)

== Customisation hooks (HTML/CSS/output) ==
- Full HTML filter
- Wrapper hooks before/after document
- Custom CSS injection hook
- Language attribute filter
- Document title/body class filters
- “Show shipping address” toggle filter

== Quick Start ==

1. Install and activate the plugin (WooCommerce required).
2. Open any WooCommerce order in wp-admin.
3. In the Kitgenix PDF Invoicing meta box, click “Download Invoice (PDF)” to confirm output.
4. Configure settings:
   - branding + company details
  - prefixes, numbering tokens, and fiscal-year reset rules
  - field visibility, tax detail, and custom fulfilment/order-meta rows
   - email attachments mapping
5. Generate a document once, then reuse the same archived PDF and stored document number for later admin/customer downloads when you need immutable record keeping.
6. Use the WooCommerce orders list plus the batch ZIP bulk actions when you need invoices, packing slips, receipts, or credit notes for a filtered set of orders.
7. Optional: enable customer downloads and order table actions.

To customise layout, copy templates into your theme override folder and edit them.

== Installation ==

1. Install via Plugins → Add New (search for “Kitgenix PDF Invoicing”), or upload the ZIP file.
2. Activate the plugin.
3. Ensure WooCommerce is active.
4. Go to WooCommerce → Orders and open an order.
5. Use the meta box to preview/download documents.
6. Configure branding, numbering, email attachments, and the email preview/test workflow in settings.

== Template Overrides ==

1. Copy templates from:
  templates/{style}/

2. Paste into your theme at:
  kitgenix-pdf-invoicing-for-woocommerce/{style}/

3. Edit the theme copies.

The plugin will automatically use your theme templates instead of bundled templates.

== Frequently Asked Questions ==

= Does this plugin generate PDF invoices automatically? =
PDFs are generated on demand and can be generated automatically at send-time by attaching them to WooCommerce emails.

= Are PDFs stored permanently in uploads? =
Yes. The plugin can retain immutable archived PDF copies in uploads for future re-delivery, while still using temporary working files for email attachments and batch ZIP creation. Stored document identifiers remain aligned with those archived copies.

= Can customers download invoices from My Account? =
Yes. The plugin can show customer download buttons on the order details screen and add “View Invoice”/“View Credit Note” actions in the Orders table. Credit notes only appear when refunds exist.

= Can I export a ZIP for multiple orders at once? =
Yes. From the WooCommerce orders list you can select orders, keep any status/date filters you already use, and run a batch ZIP export for invoices, packing slips, receipts, credit notes, pro forma invoices, delivery notes, or statements.

= Can I show tracking numbers, warehouse references, or other custom order fields on PDFs? =
Yes. In the settings page you can add custom order meta rows using one entry per line in the format `meta_key|Label`. For example, `_tracking_number|Tracking Number` or `_warehouse_pick_ref|Warehouse Pick Ref`.

= Can I switch documents between VAT, GST, or Sales Tax wording? =
Yes. The settings page now includes localization/tax/legal formatting packs for VAT, GST, and Sales Tax styles, plus manual overrides for the displayed tax term and tax-registration label.

= Can I show tax-inclusive prices, custom date formats, or ISO currency codes? =
Yes. You can switch invoices, receipts, and credit notes between tax-exclusive and tax-inclusive display, choose a custom document date format, and append ISO currency codes such as EUR, USD, GBP, or AUD after formatted amounts.

= Will repeat downloads regenerate a different PDF later? =
No. Once an archived document copy exists, later downloads and batch exports can reuse that immutable file instead of regenerating a different version.

= Can guests download documents with an order key? =
Yes. Invoice, receipt, pro forma invoice, and statement downloads can be authorised with a valid WooCommerce order key. Credit notes can also use the order key when refunds exist. Packing slips and delivery notes remain staff-only by default.

= How do credit notes work? =
Credit notes are refund-aware. When an order has refunds, credit note documents become available (admin and optionally customer).

= Is Dompdf bundled and safe? =
Dompdf is used for rendering. The plugin configures strict defaults: chroot-limited paths, remote fetching disabled by default, and PHP execution inside templates disabled by default (advanced opt-in only).

= Can I choose different document styles without editing code? =
Yes. The settings page includes four built-in style packs: Standard, Simple, Modern, and Business. It also now includes a visual template designer for header alignment, logo scale, spacing density, panel styles, totals emphasis, and footer alignment.

= How do I override templates? =
Copy templates from templates/{style}/ into your theme at kitgenix-pdf-invoicing-for-woocommerce/{style}/ and edit them. `{style}` is the active template pack selected in settings (standard/simple/modern/business). You can also override template resolution via filters.

= Can I attach PDFs to WooCommerce emails? =
Yes. You can map different documents to different WooCommerce email types. Attachments are generated as temporary files and cleaned up automatically.

= Can I preview or test email attachments before going live? =
Yes. The Email Attachments tab now includes an order-based preview tool that shows which PDFs are mapped to a WooCommerce email, opens live document previews for eligible files, and can send an attachment-only test email to a safe recipient before you rely on the live workflow.

= Will this work with HPOS? =
Yes. Order integrations are designed to work with modern WooCommerce order storage and admin workflows.

= Can I change invoice numbering, prefixes or filenames? =
Yes. Prefixes, token-based number formats, sequence padding, reset rules, and the fiscal year start month are configurable in settings. Existing issued documents keep their stored identifiers. Filenames and document behaviour can also be filtered by developers.

= Can I hide SKU, payment details, or customer/internal notes without editing templates? =
Yes. The settings page now includes field controls for SKU, item meta, line-item tax, payment method, transaction ID, shipping method, customer notes, internal notes, and tax-total visibility.

= Does the plugin support fiscal-year or country-based numbering rules? =
Yes. Numbering formats support tokens such as {sequence}, {refund_sequence}, {year}, {yy}, {month}, {country}, {billing_country}, {shipping_country}, {fiscal_year}, {fiscal_year_short}, {fiscal_year_start}, and {fiscal_year_end}. This makes it possible to implement fiscal-year resets and country-aware numbering rules without editing templates.

= Can I disable certain document types? =
Yes. Each built-in document type can be enabled or disabled in settings, limited to selected WooCommerce order statuses, and restricted to paid or unpaid orders only. Developers can also override availability per order via filters.

= Does the plugin support custom document types? =
Yes. The document registry can be extended via filters to add custom document types, and the plugin now includes built-in support for pro forma invoices, delivery notes, and statements in addition to invoices, receipts, packing slips, and credit notes.

== Screenshots ==

1. Settings: branding, company details, prefixes, styles, colours, and email attachment mapping.
2. WooCommerce order screen: admin meta box with download actions.
3. Generated PDF invoice streamed in the browser.
4. Credit note actions shown when refunds exist.
5. Customer order view: Download Invoice / Credit Note buttons (when enabled).

== Developers ==

Text domain:
kitgenix-pdf-invoicing-for-woocommerce

Architecture:
- Modular plugin with Admin/Settings/Invoicing/Email/Frontend modules
- Document types registry (extendable) with built-in invoice, receipt, packing slip, credit note, pro forma invoice, delivery note, and statement types
- Template system with theme overrides + HTML/CSS filters
- Secure download endpoints with nonce/capability checks and optional order key validation
- Token-based numbering engine for invoices, receipts, packing slips, and credit notes using stored order meta and a shared sequence-counter option
- Rule-based document availability controls by type, order status, and payment state
- Shared template display helper for configurable order rows, note blocks, and line-item metadata across bundled document templates

Numbering tokens available in settings:
- {prefix}
- {order_number}
- {order_id}
- {sequence}
- {refund_sequence}
- {year}
- {yy}
- {month}
- {day}
- {country}
- {billing_country}
- {shipping_country}
- {fiscal_year}
- {fiscal_year_short}
- {fiscal_year_start}
- {fiscal_year_end}

Key filters:
- kitgenix_pdf_invoicing_modules
- kitgenix_pdf_document_types
- kitgenix_pdf_document_enabled
- kitgenix_pdf_document_user_can_download
- kitgenix_pdf_document_filename
- kitgenix_pdf_batch_archive_filename
- kitgenix_pdf_persistent_archive_enabled
- kitgenix_pdf_archive_relative_root
- kitgenix_pdf_invoice_filename (back-compat)
- kitgenix_pdf_document_attachment (inline vs download)
- kitgenix_pdf_document_template_path
- kitgenix_pdf_document_html
- kitgenix_pdf_invoice_html (back-compat)
- kitgenix_pdf_document_custom_css
- kitgenix_pdf_document_lang
- kitgenix_pdf_document_title
- kitgenix_pdf_document_body_class
- kitgenix_pdf_show_shipping_address
- kitgenix_pdf_email_document_map
- kitgenix_pdf_email_attach_document
- kitgenix_dompdf_enable_php (advanced; default false)

Key actions:
- kitgenix_before_stream_pdf_document
- kitgenix_after_stream_pdf_document
- kitgenix_before_stream_pdf_invoice (back-compat)
- kitgenix_after_stream_pdf_invoice (back-compat)
- kitgenix_pdf_document_archived
- Template hooks:
  - kitgenix_pdf_before_document / kitgenix_pdf_after_document
  - kitgenix_pdf_before_document_wrapper / kitgenix_pdf_after_document_wrapper
  - kitgenix_pdf_after_notes
  - kitgenix_pdf_after_order_data_rows

== Data Handling ==

- Plugin settings stored in a single option: `kitgenix_pdf_invoicing_settings`.
- Anonymous generation metrics stored in: `kitgenix_pdf_invoicing_for_woocommerce_metrics`.
- Sequence counters for resettable numbering stored in: `kitgenix_pdf_invoicing_for_woocommerce_number_sequences`.
- Document identifiers/history stored on the order to keep documents stable:
  - `_kitgenix_pdf_invoicing_for_woocommerce_invoice_number`
  - `_kitgenix_pdf_invoicing_for_woocommerce_invoice_date`
  - `_kitgenix_pdf_invoicing_for_woocommerce_packing_slip_number`
  - `_kitgenix_pdf_invoicing_for_woocommerce_packing_slip_date`
  - `_kitgenix_pdf_invoicing_for_woocommerce_receipt_number`
  - `_kitgenix_pdf_invoicing_for_woocommerce_receipt_date`
  - `_kitgenix_pdf_invoicing_for_woocommerce_credit_note_count`
  - `_kitgenix_pdf_invoicing_for_woocommerce_credit_note_history`
- `_kitgenix_pdf_invoicing_for_woocommerce_archived_documents`
- Archived PDFs stored in uploads and reused as immutable document copies when available.
- Batch ZIP exports generated on demand (temporary files).
- Email attachments generated as temp files and cleaned up automatically.
- No custom database tables created.

== Security & Privacy ==

- All admin actions protected with nonces and capability checks.
- Inputs sanitised; outputs escaped appropriately.
- Dompdf PHP execution disabled by default. Enable only if you understand the risk:
  add_filter( 'kitgenix_dompdf_enable_php', '__return_true' );

Security identifiers (exact):
- Admin meta box downloads use `admin-post.php` actions `kitgenix_admin_stream_invoice`, `kitgenix_admin_stream_receipt`, `kitgenix_admin_stream_packing_slip`, `kitgenix_admin_stream_credit_note`, `kitgenix_admin_stream_pro_forma_invoice`, `kitgenix_admin_stream_delivery_note`, and `kitgenix_admin_stream_statement`, protected by query arg `nonce` created/verified with nonce action `kitgenix_admin_pdf`.
- WordPress action hooks for those admin-post actions: `admin_post_kitgenix_admin_stream_invoice`, `admin_post_kitgenix_admin_stream_receipt`, `admin_post_kitgenix_admin_stream_packing_slip`, `admin_post_kitgenix_admin_stream_credit_note`, `admin_post_kitgenix_admin_stream_pro_forma_invoice`, `admin_post_kitgenix_admin_stream_delivery_note`, and `admin_post_kitgenix_admin_stream_statement`.
- Frontend document downloads use the optional `_wpnonce` value created/verified with nonce action `kitgenix_download_{doc_type}_{order_id}`.
- WooCommerce order action key: `kitgenix_download_pdf_invoice` (hook: `woocommerce_order_action_kitgenix_download_pdf_invoice`).
- WooCommerce bulk action keys: `kitgenix_pdf_batch_export_invoice`, `kitgenix_pdf_batch_export_packing_slip`, `kitgenix_pdf_batch_export_receipt`, `kitgenix_pdf_batch_export_credit_note`, `kitgenix_pdf_batch_export_pro_forma_invoice`, `kitgenix_pdf_batch_export_delivery_note`, and `kitgenix_pdf_batch_export_statement`.

Admin page hook suffix:
- `kitgenix_page_kitgenix-pdf-invoicing-settings`

PDF generation is performed locally on your server using Dompdf. This plugin does not send customer data to a third-party PDF generation API.

== External Services ==

This plugin includes a shared “Kitgenix hub” component in wp-admin which may fetch publicly available plugin metadata from WordPress.org using WordPress core’s `plugins_api()` function.

Caching:
- Transient: `kitgenix_hub_wporg_active_installs_v1`
- Transient: `kitgenix_hub_wporg_ratings_v1`
- Transient: `kitgenix_hub_wporg_media_v1`

== Uninstall ==

Uninstall removes the plugin settings option (`kitgenix_pdf_invoicing_settings`), metrics option (`kitgenix_pdf_invoicing_for_woocommerce_metrics`), and numbering sequence option (`kitgenix_pdf_invoicing_for_woocommerce_number_sequences`) when uninstalled via WordPress.
It also deletes the activation redirect transient: `kitgenix_pdf_invoicing_for_woocommerce_do_activation_redirect`.
Order meta, archived document metadata, and archived PDF files are intentionally preserved to avoid accidental loss of invoice, receipt, packing slip, or credit note history.

== Support Development ==

If this plugin helps you generate clean WooCommerce PDFs and reduces admin work, you can support ongoing development here:
https://buymeacoffee.com/kitgenix

== Credits ==
Built with ❤︎ by @kitgenix - https://kitgenix.com
Bundled library: Dompdf (see vendor/ for licenses)

== Upgrade Notice ==

= 1.1.3 =
Recommended for all websites.

== Changelog ==
= 1.1.3 (26 May 2026) =
* Compatibility: Confirmed compatibility with WordPress 7.0 and WooCommerce 10.x.
* New: Added a Log tab to the admin settings page. Records recent plugin activity (settings saves and key operations) with timestamps, context labels, and plain-English notes to aid troubleshooting.
* Fix: Activity log data is now fully cleaned up when the plugin is uninstalled.

= 1.1.2 (26 May 2026) =
* Dev: Skipped to be in line with other Kitgenix Plugins

= 1.1.1 (7 May 2026) =
* Update: Composer dependencies

= 1.1.0 (7 May 2026) =
* New: Added localization, tax, and legal formatting packs for VAT, GST, and Sales Tax terminology, including regional document-title variations such as Tax Invoice and Adjustment Note in the bundled templates.
* New: Added no-code controls for tax-inclusive versus tax-exclusive amount display, custom document date formats, ISO currency-code suffixes, and tax-label overrides across invoices, receipts, credit notes, and packing slips.
* Improvement: Bundled document templates now use a shared localization/display helper so tax labels, document titles, date formatting, and amount presentation stay consistent across all built-in styles.
* New: Added built-in Pro Forma Invoice, Delivery Note, and Statement document types that plug into the existing registry, admin downloads, batch ZIP exports, and template system.
* New: Added document-generation rules in settings so each built-in document type can be enabled or disabled, limited to specific WooCommerce order statuses, and restricted by paid/unpaid state.
* Improvement: Invoice-style and packing-slip-style template packs now render document-type-aware titles, references, and dates so aliased document types display correctly without duplicate template stacks.
* New: Added admin order-screen download actions and secured admin-post routes for Pro Forma Invoice, Delivery Note, and Statement PDFs.
* New: Added a visual template designer with no-code controls for header alignment, logo scale, layout density, information-panel styling, item-table presentation, totals emphasis, and footer alignment across the bundled PDF template packs.
* New: Added an Email Attachments preview and test-send workflow so admins can inspect mapped PDFs for a specific order, open live document previews, and send attachment-only test emails before using live WooCommerce email flows.
* New: Added field controls for SKU visibility, item meta, per-line tax details, payment/shipping metadata, tax totals, customer notes, and the latest internal WooCommerce order note across bundled PDF templates.
* New: Added settings-based custom order field rows using `meta_key|Label`, so stores can surface tracking numbers, fulfilment references, warehouse picks, and other order meta without overriding templates.
* Improvement: Receipt and credit note templates now support the same configurable line-item detail controls used by invoices and packing slips.
* New: Added advanced numbering controls for invoices, receipts, packing slips, and credit notes with configurable prefixes, token-based formats, sequence padding, and calendar-year or fiscal-year resets.
* New: Added fiscal-year and country-aware numbering tokens so stores can build region-specific compliance formats without editing plugin code.
* Improvement: Credit notes now support refund-aware numbering formats while issued documents keep their first generated identifiers for immutable re-delivery and audit consistency.
* Improvement: Packing slip templates and the WooCommerce admin meta box now show stored document identifiers instead of assuming order-number based document numbers.
* New: Added persistent archived PDF storage so generated documents can be reused as immutable copies for later downloads, emails, and batch exports.
* Improvement: Added packing slip archive identifiers and timestamps so stored packing slips stay stable after first generation.
* Dev: Added archive metadata on orders and developer hooks for controlling archive retention paths and off-site archive workflows.
* New: Added WooCommerce order-list bulk actions for exporting invoice, packing slip, receipt, and credit note ZIP batches.
* Improvement: Added shared batch archive generation on top of the existing on-demand PDF renderer so batch exports reuse the same document templates, numbering, and security rules.
* Improvement: Credit note batch exports now skip non-refunded orders automatically instead of generating empty credit note downloads.
* Dev: Added a developer filter for customizing generated batch ZIP filenames.

= 1.0.6 (19 March 2026) =
* UI: Improved the Kitgenix admin header layout for better alignment and less clutter.
* UI: Social links in admin headers now render as compact icon buttons (with accessible labels).
* UI: Added responsive header helpers so titles/description and actions/links lay out consistently.
* Fix: Admin notices now display above the Kitgenix header using the WordPress standard notice area.
* Fix: Added defensive notice normalization to prevent notices being relocated into the header by other scripts.
* Fix: Restored consistent spacing between settings tabs and section cards.
* UI: Admin tables inside Kitgenix pages now use Kitgenix styling for a more consistent branded look.
* Fix: Added spacing between adjacent action links/buttons (e.g., Edit/Delete).
* Maintenance: Updated the plugin Author URI to the public Kitgenix WordPress.org profile and replaced the old custom admin-menu icon CSS with the native Dashicons icon.

= 1.0.5 (18 February 2026) =
* Docs: Overhauled readme.txt.
* Docs: Updated WordPress.org screenshots.
* UI: Updated the Kitgenix hub cards (added Stock Sync for WooCommerce).
* Fix: Normalised Kitgenix hub card output for consistent layout and navigation.
* Dev: Regenerated /languages/kitgenix-pdf-invoicing-for-woocommerce.pot translation template.

= 1.0.4 (27 January 2026) =
* New: Added additional template packs (Simple, Modern, Business) and a setting to choose the active template style.
* New: Added Receipt and Packing Slip actions to the admin order meta box (download + generate).
* Improvement: Translation loading added (plugin text domain now loads from /languages).
* Improvement: Minor fixes and translation loading improvements.
* Change: Declared PHP requirement as 8.1 to match bundled dependency requirements.
* Change: Harmonised admin hub enqueue checks and admin branding; shortened readme/header strings to conform to WordPress.org limits.
* Cleanup: PHPCS/i18n/security fixes across plugin files (output escaping, translator comments, optional nonce checks) applied.
* Cleanup: Uninstall routine now also removes anonymous PDF generation metrics option.
* Fix: Fixed Email Attachments settings not persisting when saving other settings tabs (multi-form settings page could overwrite email attachment mapping).
* Fix: Fixed public document download permissions to allow guest access via valid WooCommerce order_key links (matching documented behaviour).
* Fix: Fixed CSS injection for PDF rendering so valid CSS is not HTML-escaped (prevents broken selectors); hardened by stripping tags and neutralising closing </style> sequences.
* Fix: Regenerated Composer autoload to resolve missing generated file mapping for thecodingmachine/safe and verified vendor autoload mappings are correct.
* Fix: Resolved edge-case settings and template issues affecting PDF generation.
* UI: Added a label for the refunded email row in the Email Attachments table.

= 1.0.3 (06 January 2026) =
* Maintenance: Updated Composer dependencies to the latest compatible versions.

= 1.0.2 (06 January 2025) =
* Fix: Fixed a WooCommerce compatibility issue that could trigger a fatal error during PDF generation (receipt/invoice templates) when wc_get_order_item_totals() is not available.
* Fix: Totals now use the order API (WC_Order::get_order_item_totals()) with safe fallbacks to prevent admin order saves and transactional emails from failing.

= 1.0.1 (01 January 2025) =
* New: Added a shared top-level “Kitgenix” admin menu (hub) and moved PDF Invoicing settings under it.
* New: Added privacy-safe PDF generation counters (totals + by document type) and display them in the Support tab.
* Improvement: Redesigned the settings UI with a new header and tabbed navigation (Settings, Brand & Styling, Email Attachments, Preview, Support).
* Improvement: Improved admin styling (including dark-mode friendly variables) and updated Kitgenix brand assets used in the admin.
* Improvement: Improved settings behaviour — initialise the WordPress color picker only when relevant tabs are visible.
* Improvement: Improved logo upload UI (cleaner preview markup + consistent show/hide behaviour for the remove button).
* Improvement: Hardened admin asset loading to be scoped to the plugin settings page (hook suffix tracking with safe fallbacks).

= 1.0.0 (19 December 2025) =
* New: Initial release — generate PDF invoices, receipts, packing slips, and credit notes for WooCommerce orders.
* New: Included a standard template set and HTML wrapper with theme override support.
* New: Secure Dompdf configuration (chrooted paths, remote fetching disabled by default, PHP evaluation opt-in).
* New: Email attachments — attach PDFs to WooCommerce emails with automatic temporary file cleanup.
* New: Admin order meta box with preview, download/stream, and generate actions (protected by nonces and capability checks).
* New: Settings UI for branding, company details, prefixes, and email attachment mapping.
* New: Stable invoice numbering stored on first generation; credit notes tied to refund history.
* New: Developer-friendly filters and actions for templates, filenames, HTML, enablement, and module registration.
* New: Translation-ready with localisation support.
