<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; padding: 14px; color: #222; }
        .header { margin-bottom: 10px; border-bottom: 2px solid #0f766e; padding-bottom: 6px; }
        .header h1 { margin: 0; color: #0f766e; font-size: 18px; }
        .meta { margin-top: 4px; color: #555; font-size: 10px; }
        h2 { color: #0f766e; font-size: 12px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th { background: #0f766e; color: #fff; padding: 3px; text-align: left; font-weight: bold; }
        td { border: 1px solid #e5e7eb; padding: 3px; vertical-align: top; }
        .right { text-align: right; }
        tr.danger td { background: #fef2f2; }
        tr.warning td { background: #fffbeb; }
        .summary td { width: 25%; border: 1px solid #e5e7eb; padding: 6px; }
        .summary .label { color: #555; }
        .summary .value { font-size: 14px; font-weight: bold; }
        .note { color: #666; font-size: 8px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RMG Sustainability Council (RSC): {{ $title }}</h1>
        <div class="meta">{{ $period }} · Generated {{ $exportDate }}</div>
    </div>

    <table class="summary">
        @foreach (array_chunk($report['summary'], 4) as $chunk)
            <tr>
                @foreach ($chunk as $item)
                    <td>
                        <div class="label">{{ $item['label'] }}@if (! empty($item['hint'])) ({{ $item['hint'] }})@endif</div>
                        <div class="value">{{ $item['value'] ?? '—' }}</div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @foreach ($report['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        @if (! empty($section['description']))
            <div class="note">{{ $section['description'] }}</div>
        @endif
        <table>
            <thead>
                <tr>
                    @foreach ($section['columns'] as $column)
                        <th class="{{ $column['align'] === 'right' ? 'right' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr class="{{ $row['_tone'] ?? '' }}">
                        @foreach ($section['columns'] as $column)
                            <td class="{{ $column['align'] === 'right' ? 'right' : '' }}">{{ $row[$column['key']] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section['columns']) }}">Nothing to show.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
