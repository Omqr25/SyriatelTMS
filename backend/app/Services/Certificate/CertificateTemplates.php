<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CertificateTemplates
{
    public function __construct(private CertificateStorage $storage) {}

    public function create(User $actor, string $name, string $background, array $configuration): CertificateTemplateVersion
    {
        Gate::forUser($actor)->authorize('manage', Certificate::class);
        $name = Validator::validate(['name' => trim($name)], ['name' => 'required|string|max:255'])['name'];

        return DB::transaction(function () use ($actor, $name, $background, $configuration) {
            $template = CertificateTemplate::create(['name' => $name, 'created_by' => $actor->id]);

            return $this->append($actor, $template, $background, $configuration);
        }, 3);
    }

    public function addVersion(User $actor, CertificateTemplate $template, string $background, array $configuration, ?int $baseVersion = null, ?string $name = null): CertificateTemplateVersion
    {
        Gate::forUser($actor)->authorize('manage', Certificate::class);

        return DB::transaction(function () use ($actor, $template, $background, $configuration, $baseVersion, $name) {
            $template = CertificateTemplate::lockForUpdate()->findOrFail($template->id);

            if ($baseVersion !== null && $template->latestVersion()->first()?->id !== $baseVersion) {
                throw ValidationException::withMessages(['template' => __('certificates.template_changed')]);
            }
            if ($name !== null) {
                $template->update(['name' => Validator::validate(['name' => trim($name)], ['name' => 'required|string|max:255'])['name']]);
            }

            return $this->append($actor, $template, $background, $configuration);
        }, 3);
    }

    public function archive(User $actor, CertificateTemplate $template): void
    {
        Gate::forUser($actor)->authorize('manage', Certificate::class);

        DB::transaction(function () use ($template) {
            $template = CertificateTemplate::lockForUpdate()->findOrFail($template->id);
            if ($template->archived_at === null) {
                $template->update(['archived_at' => now()]);
            }
        }, 3);
    }

    private function append(User $actor, CertificateTemplate $template, string $background, array $configuration): CertificateTemplateVersion
    {
        if ($template->archived_at !== null) {
            throw ValidationException::withMessages(['template' => __('certificates.template_archived')]);
        }

        $this->validateConfiguration($configuration);
        $hash = $this->storage->backgroundHash($background);
        if (CertificateTemplateVersion::where('background_path', $background)->where('background_sha256', '!=', $hash)->exists()) {
            throw ValidationException::withMessages(['background' => __('certificates.invalid_background')]);
        }

        return $template->versions()->create([
            'version' => ((int) $template->versions()->max('version')) + 1,
            'background_path' => $background, 'background_sha256' => $hash,
            'configuration' => $configuration, 'created_by' => $actor->id,
        ]);
    }

    public function validateConfiguration(array $configuration): void
    {
        $configuration = Validator::validate(['config' => $configuration], [
            'config' => 'required|array:page,boxes',
            'config.page' => 'required|array:width,height',
            'config.page.width' => 'required|numeric|min:25|max:1000',
            'config.page.height' => 'required|numeric|min:25|max:1000',
            'config.boxes' => 'present|array|list|max:30',
            'config.boxes.*' => 'required|array:x,y,w,h,font,size,min,fit,leading,align,color,paragraphs',
            'config.boxes.*.x' => 'required|numeric|min:0|max:1000',
            'config.boxes.*.y' => 'required|numeric|min:0|max:1000',
            'config.boxes.*.w' => 'required|numeric|min:1|max:1000',
            'config.boxes.*.h' => 'required|numeric|min:1|max:1000',
            'config.boxes.*.font' => ['required', Rule::in(['sans', 'serif'])],
            'config.boxes.*.size' => 'required|numeric|min:6|max:72',
            'config.boxes.*.min' => 'required|numeric|min:6|max:72',
            'config.boxes.*.fit' => 'required|boolean:strict',
            'config.boxes.*.leading' => 'required|numeric|min:1|max:3',
            'config.boxes.*.align' => ['required', Rule::in(['left', 'center', 'right', 'justify'])],
            'config.boxes.*.color' => 'required|regex:/\\A#[a-f0-9]{6}\\z/i',
            'config.boxes.*.paragraphs' => 'required|array|list|max:20',
            'config.boxes.*.paragraphs.*' => 'present|array|list|max:120',
            'config.boxes.*.paragraphs.*.*' => 'required|array:text,token,break,bold,italic',
            'config.boxes.*.paragraphs.*.*.text' => 'sometimes|string|max:2048',
            'config.boxes.*.paragraphs.*.*.token' => ['sometimes', 'string', Rule::in(CertificatePlaceholders::ACTIVE)],
            'config.boxes.*.paragraphs.*.*.break' => 'sometimes|accepted',
            'config.boxes.*.paragraphs.*.*.bold' => 'sometimes|boolean:strict',
            'config.boxes.*.paragraphs.*.*.italic' => 'sometimes|boolean:strict',
        ])['config'];

        $layout = new CertificateLayout;
        $metrics = CertificateFontAssets::metrics();
        if (strlen(json_encode($configuration, JSON_THROW_ON_ERROR)) > 131072) {
            $this->invalidConfiguration();
        }
        foreach ($configuration['boxes'] as $box) {
            if ($box['min'] > $box['size'] || $configuration['page']['width'] + 0.000001 < $box['x'] + $box['w'] ||
                $configuration['page']['height'] + 0.000001 < $box['y'] + $box['h']) {
                $this->invalidConfiguration();
            }
            foreach ($box['paragraphs'] as $paragraph) {
                foreach ($paragraph as $node) {
                    $kinds = array_intersect_key($node, array_flip(['text', 'token', 'break']));
                    if (count($kinds) !== 1 ||
                        (array_key_exists('break', $node) && $node !== ['break' => true]) ||
                        (isset($node['text']) && ! CertificateText::isEnglishText($node['text']))) {
                        $this->invalidConfiguration();
                    }
                }
            }
            try {
                // A template can be saved with an overflow warning. Unsupported glyphs cannot be saved.
                $layout->box($box, CertificateFontAssets::SAMPLES['long'], $metrics);
            } catch (\DomainException) {
                $this->invalidConfiguration();
            }
        }
    }

    private function invalidConfiguration(): never
    {
        throw ValidationException::withMessages(['configuration' => __('certificates.invalid_configuration')]);
    }
}
