<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Gère le stockage privé des portraits et leur lecture après suppression des métadonnées.
 */
class ProfilePhoto
{
    private string $directory;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir, #[Autowire('%kernel.environment%')] string $environment, private PhotoNormalizer $normalizer = new PhotoNormalizer())
    {
        $this->directory = $projectDir.'/var/profile-photos/'.$environment;
    }

    public function store(UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Format de photo non autorisé.'),
        };
        $name = bin2hex(random_bytes(24)).'.'.$extension;
        if ($file->getSize() > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('La photo dépasse 2 Mo.');
        }
        $bytes = $this->normalizer->normalize($file->getPathname());
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Impossible de stocker la photo.');
        }
        $this->write($this->directory.'/'.$name, $bytes);

        return $name;
    }

    public function dataUri(?string $name): ?string
    {
        $path = $this->path($name);
        if (!$path || !is_file($path) || is_link($path)) {
            return null;
        }
        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        };

        try {
            // Also protects legacy files before the one-off storage cleanup is run.
            return 'data:'.$mime.';base64,'.base64_encode($this->normalizer->normalize($path));
        } catch (\InvalidArgumentException|\RuntimeException $error) {
            // Never fall back to publishing the original bytes.
            return null;
        }
    }

    public function cleanStored(bool $dryRun): array
    {
        $result = ['processed' => 0, 'failed' => 0];
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            if ($this->path(basename($path)) !== $path || !is_file($path) || is_link($path)) { continue; }
            try {
                $bytes = $this->normalizer->normalize($path);
                if (!$dryRun) { $this->write($path, $bytes); }
                ++$result['processed'];
            } catch (\InvalidArgumentException|\RuntimeException $error) {
                ++$result['failed'];
            }
        }

        return $result;
    }

    private function write(string $path, string $bytes): void
    {
        $temporary = tempnam($this->directory, '.clean-');
        if ($temporary === false) { throw new \RuntimeException('Impossible de préparer la photo.'); }
        try {
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !chmod($temporary, 0600) || !rename($temporary, $path)) {
                throw new \RuntimeException('Impossible d’enregistrer la photo nettoyée.');
            }
        } finally {
            if (is_file($temporary)) { unlink($temporary); }
        }
    }

    public function delete(?string $name): void
    {
        $path = $this->path($name);
        if ($path && is_file($path)) {
            unlink($path);
        }
    }

    private function path(?string $name): ?string
    {
        return $name && preg_match('/\A[a-f0-9]{48}\.(?:jpg|png|webp)\z/', $name) ? $this->directory.'/'.$name : null;
    }
}
