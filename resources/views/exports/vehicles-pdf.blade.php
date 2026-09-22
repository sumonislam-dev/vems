<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vehicles Export</title>
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
        .status.inactive { background-color: #f3f4f6; color: #374151; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #e5e7eb; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Vehicles Export Report</h1>
        <div class="meta">Generated on {{ $exportDate }} | Total Records: {{ $vehicles->count() }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Brand</th>
                <th>Model</th>
                <th>Type</th>
                <th>Registration</th>
                <th>Vendor</th>
                <th>Driver</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach($vehicles as $vehicle)
                <tr>
                    <td>{{ $vehicle->id }}</td>
                    <td><strong>{{ $vehicle->brand }}</strong></td>
                    <td>{{ $vehicle->model }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $vehicle->vehicle_type)) }}</td>
                    <td>{{ $vehicle->registration_number }}</td>
                    <td>{{ $vehicle->vendor->name ?? 'N/A' }}</td>
                    <td>{{ $vehicle->driver->name ?? 'N/A' }}</td>
                    <td>
                        <span class="status {{ $vehicle->is_active ? 'active' : 'inactive' }}">
                            {{ $vehicle->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>{{ $vehicle->created_at->format('M j, Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated automatically from the Vehicle Management System.</p>
    </div>
</body>
</html>
