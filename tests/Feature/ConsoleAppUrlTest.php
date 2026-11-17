<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConsoleAppUrlTest extends TestCase
{
    #[DataProvider('applicationUrls')]
    public function test_artisan_bootstraps_with_surrounding_url_whitespace(string $url): void
    {
        $process = $this->artisanProcess($url);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        $routes = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertContains('up', array_column($routes, 'uri'));
    }

    public static function applicationUrls(): array
    {
        return [
            'unchanged valid URL' => ['https://boutiq-app.onrender.com'],
            'pasted trailing newline' => ["https://boutiq-app.onrender.com\r\n"],
            'surrounding spaces and tabs' => ["\t https://boutiq-app.onrender.com \t"],
        ];
    }

    public function test_embedded_url_control_characters_remain_rejected(): void
    {
        $process = $this->artisanProcess("https://boutiq-app.onrender.com/invalid\npath");
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('A URI cannot contain CR/LF/TAB', $process->getOutput().$process->getErrorOutput());
    }

    private function artisanProcess(string $url): Process
    {
        $basePath = dirname(__DIR__, 2);

        return new Process([PHP_BINARY, 'artisan', 'route:list', '--path=up', '--json'], $basePath, [
            'APP_ENV' => 'testing',
            'APP_URL' => $url,
            'APP_CONFIG_CACHE' => $basePath.'/storage/framework/testing/console-url-'.bin2hex(random_bytes(8)).'.php',
        ]);
    }
}
