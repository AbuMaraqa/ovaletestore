<?php

/*
 * carlos-meneses/laravel-mpdf. Invoices and shipping labels (App\Support\Pdf\OrderDocuments).
 * Arabic/Hebrew: mPDF's OTL shaping + automatic script detection; XB Riyaz (bundled with mPDF) is the
 * Arabic face and covers Latin digits, DejaVu Sans handles Hebrew/Latin runs.
 */
return [
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font_size' => '10',
    'default_font' => 'xbriyaz',
    'margin_left' => 12,
    'margin_right' => 12,
    'margin_top' => 12,
    'margin_bottom' => 14,
    'margin_header' => 0,
    'margin_footer' => 6,
    'orientation' => 'P',
    'title' => 'Ovalete Store',
    'subject' => '',
    'author' => 'Ovalete Store',
    'watermark' => '',
    'show_watermark' => false,
    'show_watermark_image' => false,
    'watermark_font' => 'xbriyaz',
    'display_mode' => 'fullpage',
    'watermark_text_alpha' => 0.08,
    'watermark_image_path' => '',
    'watermark_image_alpha' => 0.2,
    'watermark_image_size' => 'D',
    'watermark_image_position' => 'P',
    'custom_font_dir' => '',
    'custom_font_data' => [],
    'auto_language_detection' => true,
    'temp_dir' => storage_path('app/mpdf'),
    'pdfa' => false,
    'pdfaauto' => false,
    'use_active_forms' => false,
];
