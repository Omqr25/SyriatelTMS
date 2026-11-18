<?php

// Local PHP server router. Laravel's router expects the public directory as its working directory.
chdir(__DIR__.'/public');

return require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
