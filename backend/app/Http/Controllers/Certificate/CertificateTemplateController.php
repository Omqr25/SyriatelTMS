<?php

namespace App\Http\Controllers\Certificate;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateVersion;
use App\Services\Certificate\CertificateBackgrounds;
use App\Services\Certificate\CertificateFontAssets;
use App\Services\Certificate\CertificatePlaceholders;
use App\Services\Certificate\CertificateTemplates;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CertificateTemplateController extends Controller implements HasMiddleware
{
    public function __construct(private CertificateTemplates $templates, private CertificateBackgrounds $backgrounds) {}

    public static function middleware(): array
    {
        return [function (Request $request, \Closure $next) {
            Gate::authorize('manage', Certificate::class);

            return $next($request);
        }];
    }

    public function index(Request $request)
    {
        $input = $request->validate(['search' => 'nullable|string|max:255', 'archived' => 'nullable|boolean']);
        $query = CertificateTemplate::with('latestVersion')->latest('id');
        if ($input['archived'] ?? false) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }
        if ($search = trim($input['search'] ?? '')) {
            $query->whereLike('name', '%'.$search.'%');
        }

        return $query->paginate(12)->through(fn (CertificateTemplate $template) => $this->summary($template));
    }

    public function show(CertificateTemplate $template)
    {
        $version = $template->latestVersion()->firstOrFail();

        return response()->json(['data' => $this->summary($template) + [
            'configuration' => $version->configuration,
            'mime' => $this->backgrounds->stored($version)['mime'],
        ]]);
    }

    public function assets()
    {
        return response()->json(['data' => [
            'fonts' => CertificateFontAssets::metrics(), 'samples' => CertificateFontAssets::SAMPLES,
            'placeholders' => CertificatePlaceholders::ACTIVE,
        ]], 200, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function font(string $family, string $style)
    {
        $face = CertificateFontAssets::metrics()[$family][$style] ?? abort(404);

        return response()->file(CertificateFontAssets::path($face), [
            'Content-Type' => 'font/ttf', 'Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|max:5120']);

        return response()->json(['data' => $this->backgrounds->upload($request->user(), $request->file('file'))], 201);
    }

    public function draft(Request $request)
    {
        $token = $request->validate(['background_token' => 'required|string|max:2048'])['background_token'];

        return $this->backgrounds->read($this->backgrounds->draft($request->user(), $token));
    }

    public function background(CertificateTemplateVersion $version)
    {
        return $this->backgrounds->read($this->backgrounds->stored($version));
    }

    public function store(Request $request)
    {
        [$input, $background] = $this->editorInput($request, ['name' => 'required|string|max:255', 'background_token' => 'required|string|max:2048']);
        $version = $this->backgrounds->withPermanent($background, fn ($path) => $this->templates->create($request->user(), $input['name'], $path, $input['configuration']));

        return $this->saved($version, 201);
    }

    public function update(Request $request, CertificateTemplate $template)
    {
        [$input, $background] = $this->editorInput($request, ['name' => 'required|string|max:255', 'base_version' => 'required|integer|min:1'], $template);
        $version = $this->backgrounds->withPermanent($background, fn ($path) => $this->templates->addVersion($request->user(), $template, $path, $input['configuration'], (int) $input['base_version'], $input['name']));

        return $this->saved($version);
    }

    public function duplicate(Request $request, CertificateTemplate $template)
    {
        $name = $request->validate(['name' => 'required|string|max:255'])['name'];
        $version = $template->latestVersion()->firstOrFail();
        $source = $this->backgrounds->stored($version);

        return $this->saved($this->templates->create($request->user(), $name, $source['path'], $version->configuration), 201);
    }

    public function archive(Request $request, CertificateTemplate $template)
    {
        $this->templates->archive($request->user(), $template);

        return response()->noContent();
    }

    public function proof(Request $request)
    {
        [$input, $background] = $this->editorInput($request, ['sample' => ['required', Rule::in(array_keys(CertificateFontAssets::SAMPLES))]]);

        return $this->backgrounds->proof($background, $input['configuration'], $input['sample']);
    }

    private function editorInput(Request $request, array $rules, ?CertificateTemplate $template = null): array
    {
        if (strlen($request->getContent()) > 150000) {
            throw ValidationException::withMessages(['configuration' => __('certificates.invalid_configuration')]);
        }
        $input = $request->validate($rules + [
            'background_token' => 'required_without:version_id|prohibits:version_id|string|max:2048',
            'version_id' => 'required_without:background_token|integer|min:1',
            'configuration' => 'required|array',
        ]);
        $background = isset($input['background_token'])
            ? $this->backgrounds->draft($request->user(), $input['background_token'])
            : $this->backgrounds->stored(CertificateTemplateVersion::when($template, fn ($query) => $query->where('certificate_template_id', $template->id))->findOrFail($input['version_id']));
        // The physical page is derived from the selected background, not supplied by a browser.
        $input['configuration']['page'] = $background['page'];
        $this->templates->validateConfiguration($input['configuration']);

        return [$input, $background];
    }

    private function saved(CertificateTemplateVersion $version, int $status = 200)
    {
        $template = $version->template;
        $template->setRelation('latestVersion', $version);

        return response()->json(['data' => $this->summary($template) + [
            'configuration' => $version->configuration,
            'mime' => $this->backgrounds->stored($version)['mime'],
        ]], $status);
    }

    private function summary(CertificateTemplate $template): array
    {
        return [
            'id' => $template->id, 'name' => $template->name, 'archived' => $template->archived_at !== null,
            'version_id' => $template->latestVersion?->id, 'version' => $template->latestVersion?->version,
            'page' => $template->latestVersion?->configuration['page'] ?? null,
        ];
    }
}
