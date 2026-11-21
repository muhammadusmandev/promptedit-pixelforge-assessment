<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminReportRequest;
use App\Http\Resources\AdminReportResource;
use App\Services\AdminReportService;
use Inertia\Inertia;

class AdminReportController extends Controller
{
    public function __construct(
        private AdminReportService $reportService
    ) {}

    /**
     * Render admin dashboard.
     */
    public function index(AdminReportRequest $request)
    {
        $filters = $request->validated();

        $report = $this->reportService->generate($filters);

        $users = AdminReportResource::collection(
            $report['users']
        )->response()->getData(true);

        return Inertia::render('Admin/Reports/Index', [
            'filters' => $filters,

            'summary' => $report['summary'],

            'daily' => AdminReportResource::collection(
                collect($report['daily'])
            )->resolve(),

            'users' => $users,
        ]);
    }

    /**
     * Return report as JSON.
     */
    public function data(AdminReportRequest $request)
    {
        $report = $this->reportService->generate(
            $request->validated()
        );

        return response()->json([
            'success' => true,

            'summary' => $report['summary'],

            'daily' => AdminReportResource::collection(
                collect($report['daily'])
            )->resolve(),

            'users' => AdminReportResource::collection(
                $report['users']
            )->response()->getData(true),
        ]);
    }
}