<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class ApiDocumentationController extends Controller
{
    public function __invoke(): Response
    {
        return response()->make(file_get_contents(base_path('docs/openapi.yaml')), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="lodgix-api-v1.yaml"',
        ]);
    }
}
