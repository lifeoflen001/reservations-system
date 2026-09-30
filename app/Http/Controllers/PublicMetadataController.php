<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class PublicMetadataController extends Controller
{
    public function sitemap(): Response
    {
        return response()
            ->view('public.sitemap', ['publicUrl' => url('/')], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        return response()
            ->view('public.robots', ['sitemapUrl' => url('/sitemap.xml')], 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
