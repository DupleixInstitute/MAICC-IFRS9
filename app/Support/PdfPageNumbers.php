<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

/**
 * DomPDF does not resolve counter(pages) in CSS generated content, so the
 * running footer's "Page N of M" is stamped onto every page after layout,
 * the same way the Dupleix manuals do it. Coordinates are in points on A4
 * portrait and sit inside the footer band defined by the manual templates.
 */
class PdfPageNumbers
{
    public static function stamp(PDF $pdf, string $label = 'Page {PAGE_NUM} of {PAGE_COUNT}'): PDF
    {
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $size = 7.5;

        // Right-aligned inside the 34pt page margin, on the footer baseline.
        $textWidth = $fontMetrics->getTextWidth('Page 000 of 000', $font, $size);
        $canvas->page_text($width - 34 - $textWidth, $height - 34, $label, $font, $size, [0.61, 0.64, 0.69]);

        return $pdf;
    }
}
