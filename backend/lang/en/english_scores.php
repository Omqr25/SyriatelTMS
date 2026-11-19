<?php

return [
    'select_program' => 'Select a valid training program.',
    'file_size' => 'The workbook must be 5 MB or smaller.',
    'upload_xlsx' => 'Upload a valid .xlsx workbook.',
    'single_sheet' => 'Upload a workbook with exactly one worksheet.',
    'sheet_limits' => 'The workbook exceeds the worksheet size or row limit.',
    'invalid_workbook' => 'The file is not a readable Excel workbook.',
    'unsupported_content' => 'Upload a plain worksheet without drawings, attachments, macros, or external links.',
    'headers' => 'The first row must contain one Email column and one English test score column.',
    'empty_sheet' => 'The worksheet contains no score rows.',
    'expired' => 'This preview has expired. Upload the workbook again.',
    'report_headers' => ['Excel row', 'Email', 'Previous score', 'Workbook score', 'Result'],
    'reasons' => [
        'replacement_skipped' => 'Existing score kept',
        'missing_email' => 'Email is missing',
        'invalid_email' => 'Email is invalid',
        'unmatched' => 'Email not found in this program',
        'ambiguous' => 'Multiple applicants share this email',
        'conflict' => 'Workbook rows for this email disagree',
        'missing_score' => 'Score is missing',
        'invalid_score' => 'Use a plain number with up to two decimal places',
        'out_of_range' => 'Score must be between 0 and 100',
        'stale' => 'Match or score changed after preview',
        'score_changed' => 'The saved score changed after preview',
        'match_changed' => 'The applicant match changed after preview',
    ],
];
