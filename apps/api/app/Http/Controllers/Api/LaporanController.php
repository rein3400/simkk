<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PdfService;
use App\Services\ExcelService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class LaporanController extends Controller
{
    public function export(
        Request $request,
        string $report,
        PdfService $pdf,
        ExcelService $excel,
    ): Response {
        $reportId = strtolower($report);

        if (!in_array($reportId, ['finance', 'stock', 'commission'], true)) {
            return response()->json(['message' => 'Report tidak ditemukan.'], 404);
        }

        if ($reportId === 'finance') {
            $pdfContent = $pdf->generateFinanceReport();
            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="finance.pdf"');
        }

        $file = $excel->generate($reportId);
        return response($file)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename=\"{$reportId}.xlsx\"");
    }
}
