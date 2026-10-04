<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Scenario;

use ImpressCMS\Module\Protector\Tests\Functional\Http\Response;
use ImpressCMS\Module\Protector\Tests\Functional\KnownDefect;
use ImpressCMS\Module\Protector\Tests\Functional\Scenario;
use ImpressCMS\Module\Protector\Tests\Functional\SiteTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('uploads')]
final class UploadTest extends SiteTestCase
{
    private const BLOCK_MESSAGE = 'Protector detects attacking actions';

    private const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/protector_upload_' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        file_put_contents($this->directory . '/real.png', base64_decode(self::ONE_PIXEL_PNG));
        file_put_contents($this->directory . '/script.php', '<?php echo 1;');
        file_put_contents($this->directory . '/not-an-image.jpg', '<?php echo 1;');
        file_put_contents($this->directory . '/image-named-jpg.jpg', base64_decode(self::ONE_PIXEL_PNG));
        file_put_contents($this->directory . '/double.png', base64_decode(self::ONE_PIXEL_PNG));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
        parent::tearDown();
    }

    #[Scenario('UPL-01', 'default preferences', 'a genuine PNG is uploaded as real.png', 'the upload reaches the page untouched')]
    public function testGenuineImageIsAccepted(): void
    {
        $response = $this->upload('real.png', 'real.png');

        $this->assertSame('real.png', $response->json()['files']['upfile']['name']);
        $this->assertSame(0, $response->json()['files']['upfile']['error']);
    }

    #[Scenario('UPL-02', 'default preferences', 'a PHP script is uploaded', 'the log write needs the database, so the page dies with "No DB connection" and the upload is not served')]
    #[KnownDefect('D9', 'events raised before the database service exists cannot be logged')]
    public function testScriptUploadAtDefaultLogLevelDiesWithoutDatabase(): void
    {
        $response = $this->upload('script.php', 'script.php');

        $this->assertStringContainsString('No DB connection', $response->body);
    }

    #[Scenario('UPL-03', 'logging off', 'a PHP script is uploaded', 'the request is terminated with the Protector message')]
    public function testScriptUploadIsBlocked(): void
    {
        $this->configure(['log_level' => 0]);

        $response = $this->upload('script.php', 'script.php');

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $response->body);
    }

    #[Scenario('UPL-04', 'logging off', 'a file with two dots in its name (double.sneaky.png) is uploaded', 'the request is terminated with the Protector message')]
    public function testMultipleDotFileNameIsBlocked(): void
    {
        $this->configure(['log_level' => 0]);

        $response = $this->upload('double.png', 'double.sneaky.png');

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $response->body);
    }

    #[Scenario('UPL-05', 'logging off', 'a text file claiming to be a JPEG is uploaded', 'the request is terminated with the Protector message (the PHP warning it emits first is defect D6 and is not asserted)')]
    public function testCamouflagedImageIsBlocked(): void
    {
        $this->configure(['log_level' => 0]);

        $response = $this->upload('not-an-image.jpg', 'not-an-image.jpg');

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $response->body);
    }

    #[Scenario('UPL-06', 'logging off', 'a PNG is uploaded under a .jpg name', 'the request is terminated with the Protector message because the extension does not match the content')]
    public function testImageWithMismatchingExtensionIsBlocked(): void
    {
        $this->configure(['log_level' => 0]);

        $response = $this->upload('image-named-jpg.jpg', 'image-named-jpg.jpg');

        $this->assertStringContainsString(self::BLOCK_MESSAGE, $response->body);
    }

    #[Scenario('UPL-07', '"die on bad extensions" off', 'a PHP script is uploaded', 'the upload reaches the page untouched')]
    public function testUploadCheckCanBeSwitchedOff(): void
    {
        $this->configure(['die_badext' => 0]);

        $response = $this->upload('script.php', 'script.php');

        $this->assertSame('script.php', $response->json()['files']['upfile']['name']);
    }

    #[Scenario('UPL-08', 'the visitor\'s address matches "reliable IPs"', 'a PHP script is uploaded', 'the upload reaches the page untouched')]
    public function testReliableAddressesSkipTheUploadCheck(): void
    {
        $this->configure(['reliable_ips' => ['^127\.0\.0\.2$']]);

        $response = $this->upload('script.php', 'script.php', '127.0.0.2');

        $this->assertSame('script.php', $response->json()['files']['upfile']['name']);
    }

    private function upload(string $file, string $asName, string $ip = self::ATTACKER): Response
    {
        return $this->client($ip)->post('/probe.php', [
            'upfile' => new \CURLFile($this->directory . '/' . $file, 'application/octet-stream', $asName),
        ]);
    }
}
