<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProfilePhoto
{
    private string $directory;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir, #[Autowire('%kernel.environment%')] string $environment)
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
        $file->move($this->directory, $name);

        return $name;
    }

    public function dataUri(?string $name): ?string
    {
        $path = $this->path($name);
        if (!$path || !is_file($path)) {
            return null;
        }
        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        };

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
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
