<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Genera los informes PDF con la base común reports.layout. DomPDF no resuelve counter(pages) en CSS,
 * así que la numeración «Página X de Y» se dibuja en el lienzo después de maquetar.
 */
final class PdfReport
{
    public static function available(): bool
    {
        return app()->bound('dompdf.wrapper');
    }

    public static function download(string $view, array $data, string $filename): Response
    {
        return self::build($view, $data)->download($filename);
    }

    /** El PDF ya maquetado, como contenido binario. */
    public static function render(string $view, array $data): string
    {
        return self::build($view, $data)->output();
    }

    private static function build(string $view, array $data): \Barryvdh\DomPDF\PDF
    {
        // Un informe grande (una sala con muchos proyectos y docentes) supera los límites habituales de PHP.
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $pdf = app('dompdf.wrapper');
        // Sin recursos remotos: el logo va incrustado y las gráficas se dibujan en HTML.
        $pdf->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHTML(view($view, $data)->render());
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 82, $canvas->get_height() - 21, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 5.6, [0.39, 0.45, 0.55]);

        return $pdf;
    }
}
