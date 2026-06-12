<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    public function test_cache_failure_does_not_break_authenticated_request(): void
    {
        $user = new User();
        $user->id = 'TEST001';
        $user->activo = true;

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->once()->andReturn($user);

        Auth::shouldReceive('guard')
            ->once()
            ->with('api')
            ->andReturn($guard);

        Cache::shouldReceive('store')
            ->once()
            ->with('file')
            ->andThrow(new \RuntimeException('Cache unavailable.'));

        Log::shouldReceive('warning')->once();

        $response = (new EnsureUserIsActive())->handle(
            Request::create('/api/test'),
            fn () => response()->json(['ok' => true])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
    }
}
