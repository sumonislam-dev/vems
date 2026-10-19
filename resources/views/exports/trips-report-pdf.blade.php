<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Trips Report Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
            font-size: 10px;
        }

        th {
            background: #0f766e;
            color: #fff;
            text-align: left;
            padding: 7px 5px;
        }

        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 5px;
            vertical-align: top;
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
    <h1>Trips Report</h1>
    <div class="meta">
        Generated: {{ $exportDate }} | Date Range: {{ $fromDate }} to {{ $toDate }} | Records: {{ $totalRecords }}
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>Trip Number</th>
        <th>Date</th>
        <th>Status</th>
        <th>Schedule Type</th>
        <th>Route</th>
        <th>Vehicle</th>
        <th>Driver</th>
        <th>Department</th>
        <th>Passengers</th>
    </tr>
    </thead>
    <tbody>
    @foreach($trips as $trip)
        <tr>
            <td>{{ $trip->trip_number }}</td>
            <td>{{ $trip->scheduled_date }}</td>
            <td>{{ $trip->status }}</td>
            <td>{{ $trip->schedule_type }}</td>
            <td>{{ $trip->vehicleRoute?->name }}</td>
            <td>{{ $trip->vehicle?->registration_number }}</td>
            <td>{{ $trip->driver_name }}</td>
            <td>{{ $trip->department?->name }}</td>
            <td>{{ $trip->passengers_count }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="footer">
    VEMS Trips Report Export
</div>
</body>
</html>
