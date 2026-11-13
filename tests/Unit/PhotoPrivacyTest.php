<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\CleanPhotosCommand;
use App\Service\{PhotoNormalizer, ProfilePhoto};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PhotoPrivacyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2).'/.runtime/photo-privacy-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/var/profile-photos/test', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/var/profile-photos/test/*') ?: [] as $file) { unlink($file); }
        foreach (glob($this->root.'/*.*') ?: [] as $file) { unlink($file); }
        rmdir($this->root.'/var/profile-photos/test');
        rmdir($this->root.'/var/profile-photos');
        rmdir($this->root.'/var');
        rmdir($this->root);
    }

    /** @dataProvider orientations */
    public function testJpegLosesPrivateExifAndPreservesOrientation(int $orientation, int $corner): void
    {
        $file = $this->jpeg($orientation);
        self::assertSame('PRIVATE-CAMERA', exif_read_data($file)['Make']);
        self::assertSame('48/1', exif_read_data($file)['GPSLatitude'][0]);
        $clean = (new PhotoNormalizer())->normalize($file);
        self::assertStringNotContainsString('PRIVATE-CAMERA', $clean);
        self::assertStringNotContainsString('PRIVATE-XMP', $clean);
        $image = imagecreatefromstring($clean);
        self::assertSame($orientation >= 5 ? 60 : 40, imagesx($image));
        self::assertSame($orientation >= 5 ? 40 : 60, imagesy($image));
        $color = imagecolorsforindex($image, imagecolorat($image, 5, 5));
        $expected = [[240, 20, 20], [20, 240, 20], [20, 20, 240], [240, 240, 20]][$corner];
        foreach (['red', 'green', 'blue'] as $i => $channel) { self::assertEqualsWithDelta($expected[$i], $color[$channel], 25); }
        file_put_contents($this->root.'/clean.jpg', $clean);
        $exif = exif_read_data($this->root.'/clean.jpg');
        self::assertArrayNotHasKey('Make', $exif ?: []);
        self::assertArrayNotHasKey('GPSLatitude', $exif ?: []);
    }

    public static function orientations(): array
    {
        return [[1, 0], [2, 1], [3, 3], [4, 2], [5, 0], [6, 2], [7, 3], [8, 1]];
    }

    public function testPngAndWebpRemoveTextMetadataAndPreserveTransparency(): void
    {
        $image = imagecreatetruecolor(12, 10);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 20, 80, 180, 127));
        foreach (['png', 'webp'] as $type) {
            $file = $this->root.'/input.'.$type;
            $type === 'png' ? imagepng($image, $file) : imagewebp($image, $file, 90);
            $bytes = file_get_contents($file);
            if ($type === 'png') {
                $chunk = 'tEXt'."Author\0PRIVATE-XMP-GPS";
                $extra = pack('N', strlen($chunk) - 4).$chunk.pack('N', crc32($chunk));
                $bytes = substr($bytes, 0, -12).$extra.substr($bytes, -12);
            } else {
                $text = 'PRIVATE-XMP-GPS';
                $chunk = 'XMP '.pack('V', strlen($text)).$text.(strlen($text) % 2 ? "\0" : '');
                $bytes = substr($bytes, 0, 4).pack('V', strlen($bytes) + strlen($chunk) - 8).substr($bytes, 8).$chunk;
            }
            file_put_contents($file, $bytes);
            $clean = (new PhotoNormalizer())->normalize($file);
            self::assertStringNotContainsString('PRIVATE-XMP-GPS', $clean);
            $decoded = imagecreatefromstring($clean);
            self::assertSame(12, imagesx($decoded));
            self::assertSame(127, imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha']);
        }
    }

    public function testNewUploadsAndLegacyRenderingAreCleanAndStorageCleanupHasDryRun(): void
    {
        $input = $this->jpeg(6);
        $photos = new ProfilePhoto($this->root, 'test');
        $name = $photos->store(new UploadedFile($input, 'private-name.jpg', 'image/jpeg', test: true));
        $stored = $this->root.'/var/profile-photos/test/'.$name;
        self::assertStringNotContainsString('PRIVATE-CAMERA', file_get_contents($stored));
        // Simulate a file written by the old application, with the same opaque name.
        copy($input, $stored);
        $original = file_get_contents($stored);
        $uri = $photos->dataUri($name);
        self::assertNotNull($uri);
        self::assertStringNotContainsString('PRIVATE-CAMERA', base64_decode(explode(',', $uri, 2)[1]));
        self::assertSame($original, file_get_contents($stored));
        $events = [];
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::exactly(3))->method('info')->willReturnCallback(static function (string $action, array $context) use (&$events): void {
            $events[] = $action;
            self::assertSame(['actor_id', 'request_id'], array_keys($context));
            self::assertNull($context['actor_id']);
        });
        $audit = new \App\Service\SecurityAudit($logger, new \App\Service\AuditContext(new \Symfony\Component\HttpFoundation\RequestStack(), new \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage()));
        $tester = new CommandTester(new CleanPhotosCommand($photos, $audit));
        self::assertSame(0, $tester->execute(['--dry-run' => true]));
        self::assertSame($original, file_get_contents($stored));
        self::assertSame(0, $tester->execute([]));
        self::assertStringNotContainsString('PRIVATE-CAMERA', file_get_contents($stored));
        self::assertFileExists($stored);
        file_put_contents($stored, 'corrupt');
        self::assertNull($photos->dataUri($name));
        self::assertSame(1, $tester->execute([]));
        self::assertSame('corrupt', file_get_contents($stored));
        self::assertNull($photos->dataUri('../input.jpg'));
        self::assertSame(['photos.clean_simulated', 'photos.cleaned', 'photos.clean_failed'], $events);
    }

    public function testLargeTransparentImageIsReducedProportionally(): void
    {
        $image = imagecreatetruecolor(2000, 100);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        $file = $this->root.'/large.png';
        imagepng($image, $file);
        $clean = imagecreatefromstring((new PhotoNormalizer())->normalize($file));
        self::assertSame(1600, imagesx($clean));
        self::assertSame(80, imagesy($clean));
        self::assertSame(127, imagecolorsforindex($clean, imagecolorat($clean, 0, 0))['alpha']);
    }

    public function testDimensionsOutsideTheUploadContractAreRejected(): void
    {
        $file = $this->root.'/too-wide.png';
        imagepng(imagecreatetruecolor(4001, 1), $file);
        $this->expectException(\InvalidArgumentException::class);
        (new PhotoNormalizer())->normalize($file);
    }

    private function jpeg(int $orientation): string
    {
        $image = imagecreatetruecolor(40, 60);
        foreach ([[0, 0, 19, 29, 240, 20, 20], [20, 0, 39, 29, 20, 240, 20], [0, 30, 19, 59, 20, 20, 240], [20, 30, 39, 59, 240, 240, 20]] as [$x1, $y1, $x2, $y2, $r, $g, $b]) {
            imagefilledrectangle($image, $x1, $y1, $x2, $y2, imagecolorallocate($image, $r, $g, $b));
        }
        $file = $this->root.'/input.jpg';
        imagejpeg($image, $file, 95);
        $make = "PRIVATE-CAMERA\0";
        $gpsOffset = 50 + strlen($make);
        $tiff = 'II'.pack('vVv', 42, 8, 3)
            .pack('vvV', 0x0112, 3, 1).pack('v', $orientation)."\0\0"
            .pack('vvVV', 0x010f, 2, strlen($make), 50)
            .pack('vvVV', 0x8825, 4, 1, $gpsOffset).pack('V', 0).$make
            .pack('v', 2).pack('vvV', 1, 2, 2)."N\0\0\0"
            .pack('vvVV', 2, 5, 3, $gpsOffset + 30).pack('V', 0).pack('VVVVVV', 48, 1, 0, 1, 0, 1);
        $exif = "Exif\0\0".$tiff;
        $xmp = "http://ns.adobe.com/xap/1.0/\0PRIVATE-XMP";
        $bytes = file_get_contents($file);
        file_put_contents($file, substr($bytes, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif."\xff\xe1".pack('n', strlen($xmp) + 2).$xmp.substr($bytes, 2));

        return $file;
    }
}
