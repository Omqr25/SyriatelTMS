<?php

namespace App\Console;

use Illuminate\Foundation\Console\ServeCommand as LaravelServeCommand;

class ServeCommand extends LaravelServeCommand
{
    protected function serverCommand()
    {
        $command = parent::serverCommand();

        // PHP parses multipart bodies before Laravel can handle the request.
        array_splice($command, 1, 0, [
            '-d', 'upload_max_filesize=50M',
            '-d', 'post_max_size=50M',
            '-d', 'max_file_uploads=20',
            '-d', 'display_errors=0',
            '-d', 'log_errors=1',
        ]);

        return $command;
    }
}
