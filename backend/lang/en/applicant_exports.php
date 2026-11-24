<?php

return [
    'cohort_limit' => 'This program exceeds the configured applicant export limit.',
    'document_busy' => 'Another export for this program is preparing document archives. Try again after it finishes.',
    'cancelling' => 'The worker is still stopping. Try again shortly.',
    'report_pending' => 'The report will be available when document preparation finishes.',
    'report' => [
        'coverage' => 'Report coverage', 'complete' => 'Document preparation complete', 'partial' => 'Partial report: document preparation did not complete',
        'snapshot' => 'Export reference', 'documents' => 'Unavailable documents', 'exceptions' => 'Export exceptions',
        'headers' => ['Application ID', 'Applicant full name', 'Document category', 'Reason', 'ZIP part'],
        'exception_headers' => ['Stage', 'ZIP part', 'Reason'],
        'categories' => ['cv' => 'CV', 'national_id' => 'National ID', 'academic_qualification' => 'Academic qualification', 'personal_photo' => 'Personal photo'],
        'stages' => ['capture' => 'Document preparation', 'plan' => 'Archive planning', 'zip' => 'ZIP archive', 'excel' => 'Excel'],
        'reasons' => [
            'missing' => 'The original could not be found.', 'invalid_path' => 'The original is outside the approved storage location.',
            'unsupported' => 'The file format is unsupported.', 'unreadable' => 'The original could not be read.',
            'storage_exhausted' => 'There is insufficient storage capacity.', 'cancelled' => 'The operation was cancelled.',
            'processing_failed' => 'The operation could not be completed. Retry or contact support.',
            'document_changed' => 'The original bytes changed after preparation.',
            'source_storage_unavailable' => 'The original document volume is unavailable.',
            'storage_unavailable' => 'The export volume is unavailable.',
        ],
    ],
];
