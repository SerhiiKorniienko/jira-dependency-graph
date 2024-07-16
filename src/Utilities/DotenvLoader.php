<?php

namespace App\Utilities;

use Dotenv\Dotenv;

class DotenvLoader
{
    public static function load(string $directory): void
    {
        $dotenv = Dotenv::createImmutable($directory);
        $dotenv->load();
    }
}
