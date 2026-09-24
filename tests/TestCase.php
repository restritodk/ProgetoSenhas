<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function assertAuthenticatedLoginFeedback(TestResponse $response, string $redirectUrl, ?string $firstName = null): TestResponse
    {
        $response->assertOk()
            ->assertSee('Acesso autorizado', false)
            ->assertSee('Preparando seu ambiente...', false)
            ->assertSee('data-redirect-url="'.$redirectUrl.'"', false)
            ->assertDontSee('Sessão encerrada', false);

        if ($firstName !== null) {
            $response->assertSee('Bem-vindo, '.$firstName, false);
        }

        return $response;
    }

    protected function assertLoggedOutFeedback(TestResponse $response): TestResponse
    {
        return $response->assertOk()
            ->assertSee('Sessão encerrada', false)
            ->assertSee('Você saiu com segurança.', false)
            ->assertSee('data-redirect-url="'.route('login').'"', false)
            ->assertDontSee('Acesso autorizado', false);
    }
}
