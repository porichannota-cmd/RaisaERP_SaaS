<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiErrorFormatTest extends TestCase
{
    public function test_it_returns_json_for_api_routes()
    {
        Route::get('/api/test-error', function () {
            abort(403, 'Unauthorized access attempt.');
        });

        $response = $this->get('/api/test-error', ['Accept' => 'application/json']);

        $response->assertStatus(403);
        $response->assertJsonStructure([
            'error',
            'message',
            'correlation_id'
        ]);
        $response->assertJsonFragment(['error' => 'HttpException']);
    }

    public function test_it_formats_validation_errors()
    {
        Route::post('/api/test-validation', function (\Illuminate\Http\Request $request) {
            $request->validate(['name' => 'required']);
        });

        $response = $this->postJson('/api/test-validation', []);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error',
            'message',
            'errors' => ['name'],
            'correlation_id'
        ]);
        $response->assertJsonFragment(['error' => 'Validation Failed']);
    }
}
