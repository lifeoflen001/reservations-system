<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} export</title>
    <style>
        @page { margin: 28px 22px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 8px; }
        h1 { color: #c7661a; font-size: 18px; margin: 0 0 4px; }
        p { color: #6b7280; margin: 0 0 14px; font-size: 8px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #f3f4f6; color: #374151; font-size: 7px; text-transform: uppercase; }
        th, td { border: 1px solid #d1d5db; padding: 5px 4px; vertical-align: top; word-wrap: break-word; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>{{ $title }} export</h1>
    <p>Generated {{ now()->format('Y-m-d H:i') }} · {{ count($rows) }} records</p>
    <table>
        <thead><tr>@foreach($headers as $header)<th>{{ str($header)->replace('_', ' ')->title() }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>@foreach($headers as $header)<td>{{ $row[$header] ?? '' }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($headers) }}">No records found.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
