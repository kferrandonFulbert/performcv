<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Réencode les pixels du portrait en tenant compte de son orientation et en supprimant les métadonnées.
 */
final class PhotoNormalizer
{
    public function normalize(string $path): string
    {
        if (!extension_loaded('gd') || !extension_loaded('exif')) {
            throw new \RuntimeException('Le traitement sécurisé des photos nécessite GD et EXIF.');
        }
        $size = filesize($path);
        $info = @getimagesize($path);
        if ($size === false || $size > 32 * 1024 * 1024 || !$info || $info[0] > 4000 || $info[1] > 4000
            || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new \InvalidArgumentException('La photo ne peut pas être traitée. Utilisez un JPEG, PNG ou WebP valide.');
        }
        $image = @imagecreatefromstring(file_get_contents($path));
        if (!$image) {
            throw new \InvalidArgumentException('La photo ne peut pas être décodée.');
        }
        // Limit decoded output and rotation memory; the upload limit stays 4000 × 4000.
        $ratio = min(1, 1600 / max(imagesx($image), imagesy($image)));
        if ($ratio < 1) {
            $scaled = imagescale($image, max(1, (int) round(imagesx($image) * $ratio)), max(1, (int) round(imagesy($image) * $ratio)));
            if (!$scaled) { throw new \RuntimeException('Impossible de redimensionner la photo.'); }
            $image = $scaled;
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $exif = @exif_read_data($path, 'IFD0', true);
            $orientation = (int) ($exif['IFD0']['Orientation'] ?? 1);
            $angle = match ($orientation) { 3, 4 => 180, 5, 6 => 270, 7, 8 => 90, default => 0 };
            if ($angle) {
                $rotated = imagerotate($image, $angle, 0);
                if (!$rotated) { throw new \RuntimeException('Impossible d’orienter la photo.'); }
                $image = $rotated;
            }
            if (in_array($orientation, [2, 4, 5, 7], true)) { imageflip($image, IMG_FLIP_HORIZONTAL); }
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        try {
            $ok = match ($info[2]) {
                IMAGETYPE_JPEG => imagejpeg($image, null, 90),
                IMAGETYPE_PNG => imagepng($image, null, 6),
                IMAGETYPE_WEBP => imagewebp($image, null, 90),
            };
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (!$ok || !$bytes) { throw new \RuntimeException('Impossible de nettoyer la photo.'); }

        return $bytes;
    }
}
