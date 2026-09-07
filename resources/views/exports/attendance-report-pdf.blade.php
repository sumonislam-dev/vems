<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 20px;
            color: #222;
        }

        .header {
            margin-bottom: 16px;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            color: #0f766e;
            font-size: 22px;
        }

        .meta {
            margin-top: 6px;
            color: #555;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 8px;
        }

        th {
            background: #0f766e;
            color: #fff;
            text-align: left;
            padding: 5px 4px;
            white-space: nowrap;
        }

        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 4px;
            vertical-align: top;
            white-space: nowrap;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        .footer {
            margin-top: 16px;
            font-size: 10px;
            color: #555;
            border-top: 1px solid #d1d5db;
            padding-top: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>Attendance Report</h1>
    <div class="meta">
        Generated: {{ $exportDate }} | Records: {{ $totalRecords }}
    </div>
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
    VEMS Attendance Report Export
</div>
</body>
</html>
