<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Drivers Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4F46E5; padding-bottom: 15px; }
        .header h1 { color: #4F46E5; margin: 0; font-size: 24px; font-weight: bold; }
        .header .meta { margin-top: 10px; color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px; }
        th { background-color: #4F46E5; color: white; padding: 8px 6px; text-align: left; font-weight: bold; font-size: 10px; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .status { padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .status.active { background-color: #dcfce7; color: #166534; }
        .status.inactive, .status.suspended { background-color: #f3f4f6; color: #374151; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #e5e7eb; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Drivers Export Report</h1>
        <div class="meta">Generated on {{ $exportDate }} | Total Records: {{ $drivers->count() }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Type</th>
                <th>Status</th>
                <th>Department</th>
                <th>Vendor</th>
                <th>License No</th>
                <th>License Expiry</th>
            </tr>
        </thead>
        <tbody>
            @foreach($drivers as $driver)
                <tr>
                    <td>{{ $driver->id }}</td>
                    <td><strong>{{ $driver->name }}</strong></td>
                    <td>{{ $driver->email }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $driver->user_type)) }}</td>
                    <td>
                        <span class="status {{ $driver->status }}">{{ ucfirst($driver->status) }}</span>
                    </td>
                    <td>{{ $driver->department->name ?? 'N/A' }}</td>
                    <td>{{ $driver->vendor->name ?? 'In-house' }}</td>
                    <td>{{ $driver->driving_license_no ?? 'N/A' }}</td>
                    <td>{{ $driver->license_expiry_date?->format('M j, Y') ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated automatically from the Vehicle Management System.</p>
    </div>
</body>
</html>
