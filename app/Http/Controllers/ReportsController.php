<?php

namespace App\Http\Controllers;

use App\Exports\ReportsExportExcel;
use App\Jobs\GenerateReport;
use App\Models\ReportJob;
use App\Models\Reports;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $filename = $request->get('file');

        // Create a job record
        $reportJob = ReportJob::create([
            'filename' => $filename,
            'request_data' => $request->all(),
        ]);

        // Return a loading view immediately
        return view('loading', ['jobId' => $reportJob->id]);
    }

    public function startJob($jobId)
    {
        // Find the job record
        $reportJob = ReportJob::find($jobId);

        if (!$reportJob) {
            return response()->json(['status' => 'error', 'message' => 'Job not found'], 404);
        }

        // Dispatch the job
        $job = new GenerateReport($reportJob);
        dispatch($job);

        $laravelPath = base_path();
        chdir($laravelPath);
        // Start the queue worker in the background without --once
        exec('php artisan queue:work --tries=3 > /dev/null &');

        return response()->json(['status' => 'started']);
    }

    public function checkStatus($jobId)
    {
        $reportJob = ReportJob::findOrFail($jobId);

        if ($reportJob->status === 'completed') {
            return response()->json(['status' => 'completed', 'downloadUrl' => route('reports.download', ['jobId' => $jobId])]);
        } else {
            return response()->json(['status' => 'processing']);
        }
    }

    public function download($jobId)
    {
        $reportJob = ReportJob::findOrFail($jobId);

        if ($reportJob->status !== 'completed' || !$reportJob->output_path) {
            abort(404);
        }

        return response()->file(storage_path("app/{$reportJob->output_path}"), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $reportJob->output_path . '"',
        ]);
    }

    public function ExportExcel(Request $request)
    {
        $filename = $request->get('file');
        $requestData = $request->all();
        $title = $filename;
        if (!method_exists(Reports::class, $filename)) {
            return response("Laporan Dengan Nama " . $filename . " Tidak Tersedia", 404);
        }

        return Excel::download(new ReportsExportExcel($filename, $requestData, $title), $filename . '.xlsx');
    }
}
