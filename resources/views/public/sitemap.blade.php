<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach(['/', '/product', '/operations', '/pos', '/finance', '/security', '/integrations', '/pricing', '/contact'] as $path)
        <url><loc>{{ url($path) }}</loc></url>
    @endforeach
</urlset>
