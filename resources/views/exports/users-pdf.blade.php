<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Users Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4F46E5; padding-bottom: 15px; }
        .header h1 { color: #4F46E5; margin: 0; font-size: 24px; font-weight: bold; }
        .header .meta { margin-top: 10px; color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px; }
        th { background-color: #4F46E5; color: white; padding: 8px 6px; text-align: left; font-weight: bold; font-size: 10px; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #e5e7eb; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Users Export Report</h1>
        <div class="meta">Generated on {{ $exportDate }} | Total Records: {{ count($rows) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated automatically from the Vehicle Management System.</p>
    </div>
</body>
</html>
