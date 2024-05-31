<?php

namespace App\Jobs;

use App\Models\ReportJob;
use App\Models\Reports;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class GenerateReport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $reportJob;

    public function __construct(ReportJob $reportJob)
    {
        $this->reportJob = $reportJob;
    }

    public function handle()
    {
        try {

            $this->reportJob->update(['status' => 'processing']);
            Log::info("Started processing job ID: {$this->reportJob->id}");

            ini_set('memory_limit', '512M');
            set_time_limit(0);

            $filename = $this->reportJob->filename;
            $requestData = $this->reportJob->request_data;
            $requestData['IdJob'] = $this->reportJob->id;

            $header = $filename . '_HEADER';
            $data['type'] = 'pdf';
            // $data['tahun'] = $requestData['tahun'];

            if (method_exists(Reports::class, $header)) {
                $data['judul'] = Reports::$header($requestData);
                Log::info("Header method exists: {$header}");
            } else {
                $data['judul'] = null;
                Log::info("Header method does not exist: {$header}");
            }

            if (method_exists(Reports::class, $filename)) {
                try {
                    $reportData = Reports::$filename($requestData);
                    Log::info("Filename method exists: {$filename}");
                } catch (\Exception $e) {
                    $this->reportJob->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            } else {
                $errorMessage = "Laporan Dengan Nama {$filename} Tidak Tersedia";
                $this->reportJob->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);
                Log::error($errorMessage);
                throw new \Exception($errorMessage);
            }

            $chunks = array_chunk($reportData, 100);
            Log::info("Data chunked into " . count($chunks) . " chunks.");

            $pdfFiles = [];
            $options = [
                'isRemoteEnabled' => true,
            ];

            $counter = 1;

            foreach ($chunks as $index => $chunk) {
                try {
                    Log::info("Processing chunk {$index}_{$this->reportJob->id}");
                    $data['data'] = $chunk;
                    $data['counter'] = $counter;

                    $html = view('aset.' . $filename, $data)->render();

                    $pdf = PDF::loadHTML($html)
                        ->setPaper('a4', 'landscape');

                    $tempFilePath = storage_path("app/temp_chunk_{$index}_{$this->reportJob->id}.pdf");
                    $pdf->save($tempFilePath);
                    $pdfFiles[] = $tempFilePath;

                    Log::info("Chunk {$index} saved to {$tempFilePath}");

                    $counter += count($chunk);
                } catch (\Exception $e) {
                    Log::error("Error processing chunk {$index}: " . $e->getMessage());
                    // Optionally, update the job status to 'failed' and terminate if critical
                    $this->reportJob->update(['status' => 'failed']);
                    throw $e;
                }
            }

            $mergedPdfPath = storage_path('app/' . $filename . '_merged_report_' . $this->reportJob->id . '.pdf');
            $mergedPdf = new Fpdi();

            foreach ($pdfFiles as $file) {
                $pageCount = $mergedPdf->setSourceFile($file);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tplIdx = $mergedPdf->importPage($i);
                    $mergedPdf->AddPage('L');
                    $mergedPdf->useTemplate($tplIdx);
                }
            }

            $mergedPdf->Output($mergedPdfPath, 'F');
            Log::info("Merged PDF saved to {$mergedPdfPath}");

            foreach ($pdfFiles as $file) {
                unlink($file);
                Log::info("Deleted temporary file {$file}");
            }

            // Store the final PDF
            $jakartaTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
            $timestamp = $jakartaTime->format("Y-m-d h:i:s");
            $finalPdfPath = storage_path('app/' . $filename . '_final_report_' . $this->reportJob->id . '.pdf');
            Storage::put("reports/{$this->reportJob->id}_{$filename}_" . $timestamp . ".pdf", file_get_contents($mergedPdfPath));
            Log::info("Final report saved to reports/{$this->reportJob->id}_{$filename}_" . $timestamp . ".pdf");
            unlink($mergedPdfPath);

            // Update the job status and output path
            $this->reportJob->update([
                'status' => 'completed',
                'output_path' => "reports/{$this->reportJob->id}_{$filename}_" . $timestamp . ".pdf"
            ]);
            Log::info("Job ID: {$this->reportJob->id} completed successfully.");
        } catch (\Exception $e) {
            Log::error('Report generation failed: ' . $e->getMessage());

            // Update the job status to 'failed' and log the error message
            $this->reportJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function addPageNumbers($inputPath, $outputPath)
    {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($inputPath);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplIdx = $pdf->importPage($i);
            $pdf->AddPage('L');
            $pdf->useTemplate($tplIdx);
            // Add page number
            // $pdf->SetFont('Helvetica', '', 10);
            // $pdf->SetXY(-30, -15);
            // $pdf->Cell(0, 10, "Page $i of $pageCount", 0, 0, 'R');
        }

        $pdf->Output($outputPath, 'F');
    }
}
