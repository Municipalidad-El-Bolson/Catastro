<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DriveCachePrune extends Command
{
    protected $signature = 'drive:cache-prune {--days=7}';
    protected $description = 'Borra archivos cacheados de Drive que superen X días';

    public function handle()
    {
        $days = (int)$this->option('days');
        $ttl  = $days * 86400;

        $disk = Storage::disk('local');
        $dir = 'drive-cache';

        if (!$disk->exists($dir)) {
            $this->info('No existe drive-cache.');
            return 0;
        }

        $files = $disk->files($dir);
        $now = time();
        $deleted = 0;

        foreach ($files as $f) {
            if (!str_ends_with($f, '.json')) continue;

            $meta = json_decode($disk->get($f), true) ?: [];
            $cachedAt = (int)($meta['cached_at'] ?? 0);

            if ($cachedAt && ($now - $cachedAt) > $ttl) {
                $base = substr($f, 0, -5); // sin .json
                $bin = $base . '.bin';

                $disk->delete($f);
                if ($disk->exists($bin)) $disk->delete($bin);
                $deleted++;
            }
        }

        $this->info("Pruned: {$deleted}");
        return 0;
    }
}
