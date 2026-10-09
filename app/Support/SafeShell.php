<?php

namespace App\Support;

class SafeShell
{
    public static function exec(string $command): ?string
    {
        if (! \function_exists('shell_exec')) {
            return null;
        }

        $output = @\shell_exec($command);

        return $output === null ? null : (string) $output;
    }

    public static function run(string $command): int
    {
        if (! \function_exists('exec')) {
            return 127;
        }

        $code = 1;
        @\exec($command, $output, $code);

        return (int) $code;
    }
}
