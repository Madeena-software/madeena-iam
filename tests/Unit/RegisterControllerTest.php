<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    public function test_show_registration_form_returns_register_view(): void
    {
        $controller = new RegisterController();
        $request = Request::create('/register', 'GET');

        $view = $controller->showRegistrationForm($request);

        $this->assertInstanceOf(View::class, $view);
        $this->assertEquals('auth.register', $view->name());
    }
}
