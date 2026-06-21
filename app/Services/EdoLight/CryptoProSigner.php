<?php

namespace App\Services\EdoLight;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class CryptoProSigner
{
    public function sign(string $data, string $thumbprint, ?string $password = null, bool $detached = false): string
    {
        $workDir = (string) config('edo_light.cryptcp_work_dir');
        File::ensureDirectoryExists($workDir);

        $prefix = $workDir.DIRECTORY_SEPARATOR.'cryptcp_'.bin2hex(random_bytes(8));
        $inputPath = $prefix.'.in';
        $outputPath = $prefix.'.out';

        File::put($inputPath, $data);

        $command = [
            (string) config('edo_light.cryptcp_path'),
            '-sign',
        ];

        if ($detached) {
            $command[] = '-detached';
        }

        array_push(
            $command,
            $inputPath,
            $outputPath,
            '-nochain',
            '-norev',
            '-thumbprint',
            $thumbprint,
        );

        try {
            $pending = Process::timeout(60);

            if ($password !== null && $password !== '') {
                $pending = $pending->input($password.PHP_EOL);
            }

            $result = $pending->run($command);

            if (! $result->successful()) {
                throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'CryptoPro signing failed.');
            }

            if (! File::exists($outputPath)) {
                throw new RuntimeException('CryptoPro signing finished without output file.');
            }

            return str_replace(["\r", "\n"], '', File::get($outputPath));
        } finally {
            File::delete([$inputPath, $outputPath]);
        }
    }
}
