<?php

namespace App\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    /**
     * Stream a CSV file with UTF-8 BOM and a French-friendly separator.
     */
    private function csvResponse(string $filename, callable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // BOM UTF-8 pour un rendu correct dans Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            $rows(function (array $row) use ($handle): void {
                fputcsv($handle, $row, ';');
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
