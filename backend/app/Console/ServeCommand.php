<?php

namespace App\Console;

use Illuminate\Foundation\Console\ServeCommand as LaravelServeCommand;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ServeCommand extends LaravelServeCommand
{
    protected function serverCommand()
    {
        $uploadDirectory = storage_path('framework/uploads');
        File::ensureDirectoryExists($uploadDirectory, 0700);
        if (! is_writable($uploadDirectory)) {
            throw new RuntimeException("The PHP upload directory is not writable: {$uploadDirectory}");
        }

        $command = parent::serverCommand();

        // PHP parses multipart bodies before Laravel can handle the request.
        array_splice($command, 1, 0, [
            '-d', "upload_tmp_dir={$uploadDirectory}",
            '-d', 'upload_max_filesize=50M',
            '-d', 'post_max_size=50M',
            '-d', 'max_file_uploads=20',
            '-d', 'display_errors=0',
            '-d', 'log_errors=1',
        ]);

        return $command;
    }
}
