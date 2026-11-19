<?php

namespace App\Http\Controllers\Training;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\PreviewEnglishScoreImportRequest;
use App\Http\Resources\Training\EnglishScoreImportResource;
use App\Http\Resources\Training\EnglishScoreImportRowResource;
use App\Models\EnglishScoreImport;
use App\Services\Training\EnglishScoreImportService;
use App\Services\Training\EnglishScoreWorkbook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnglishScoreImportController extends Controller
{
    use ApiResponse;

    public function __construct(private EnglishScoreImportService $imports) {}

    public function index(): JsonResponse
    {
        $history = EnglishScoreImport::whereNotNull('confirmed_at')->latest('confirmed_at')->orderByDesc('id')->paginate(10);

        return $this->successWithPagination(EnglishScoreImportResource::collection($history));
    }

    public function template(EnglishScoreWorkbook $workbook): StreamedResponse
    {
        return response()->streamDownload(
            fn () => $workbook->writeTemplate('php://output'),
            'english-score-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function preview(PreviewEnglishScoreImportRequest $request): JsonResponse
    {
        $batch = $this->imports->preview($request->user(), (int) $request->validated('training_program_id'), $request->file('file'));

        return $this->success(EnglishScoreImportResource::make($batch), code: 201);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $request->validate(['replace_existing' => 'required|boolean']);
        $batch = $this->imports->confirm($request->user(), $id, $request->boolean('replace_existing'));

        return $this->success(EnglishScoreImportResource::make($batch));
    }

    public function rows(Request $request, int $id): JsonResponse
    {
        $batch = $this->visibleBatch($request, $id);
        $rows = $batch->rows()
            ->when($request->boolean('exceptions'), fn ($query) => $query->whereIn('status', EnglishScoreImportService::EXCEPTIONS))
            ->orderBy('row_number')->paginate(25);

        return $this->successWithPagination(EnglishScoreImportRowResource::collection($rows));
    }

    public function downloadExceptions(Request $request, int $id, EnglishScoreWorkbook $workbook): StreamedResponse
    {
        $batch = $this->visibleBatch($request, $id);
        $rows = $batch->rows()->whereIn('status', EnglishScoreImportService::EXCEPTIONS)->orderBy('row_number');
        $locale = app()->getLocale();

        return response()->streamDownload(
            fn () => $workbook->writeExceptions($rows->cursor(), 'php://output', $locale),
            'english-score-exceptions-'.$batch->id.'.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function visibleBatch(Request $request, int $id): EnglishScoreImport
    {
        return EnglishScoreImport::where(fn ($query) => $query->whereNotNull('confirmed_at')
            ->orWhere(fn ($draft) => $draft->where('created_by', $request->user()->id)->where('expires_at', '>', now())))
            ->findOrFail($id);
    }
}
