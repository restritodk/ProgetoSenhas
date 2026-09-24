<?php

namespace Tests\Unit;

use App\Support\SmartTvBrowser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmartTvBrowserTest extends TestCase
{
    #[DataProvider('smartTvAgents')]
    public function test_detects_smart_tv_user_agents(string $userAgent): void
    {
        $this->assertTrue(SmartTvBrowser::matches($userAgent));
    }

    #[DataProvider('nonSmartTvAgents')]
    public function test_rejects_desktop_and_mobile_agents(string $userAgent): void
    {
        $this->assertFalse(SmartTvBrowser::matches($userAgent));
    }

    public function test_empty_agent_is_not_smart_tv(): void
    {
        $this->assertFalse(SmartTvBrowser::matches(null));
        $this->assertFalse(SmartTvBrowser::matches(''));
        $this->assertFalse(SmartTvBrowser::matches('   '));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function smartTvAgents(): array
    {
        return [
            'samsung_tizen' => [
                'Mozilla/5.0 (SMART-TV; Linux; Tizen 6.0) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/4.0 Chrome/76.0.3809.146 TV Safari/537.36',
            ],
            'tizen_tv_safari' => [
                'Mozilla/5.0 (Linux; Tizen 2.3) AppleWebKit/538.1 (KHTML, like Gecko) Version/2.3 TV Safari/538.1',
            ],
            'smart_tv_token' => [
                'Mozilla/5.0 (Linux; SMART-TV) AppleWebKit/537.36 Chrome/90.0.0.0 Safari/537.36',
            ],
            'hbbtv' => [
                'Opera/9.80 (Linux mips ; U; HbbTV/1.1.1 (; Philips; ; ; ; ) CE-HTML/1.0 NETTV/2.0.2; en) Presto/2.6.33 Version/10.70',
            ],
            'web0s' => [
                'Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.79 Safari/537.36',
            ],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonSmartTvAgents(): array
    {
        return [
            'chrome_desktop' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
            'edge_desktop' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
            ],
            'samsung_mobile_browser' => [
                'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/110.0.5481.154 Mobile Safari/537.36',
            ],
            'android_chrome' => [
                'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            ],
        ];
    }
}
