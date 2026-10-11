<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Vehicle Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; padding: 14px; color: #222; }
        .header { margin-bottom: 10px; border-bottom: 2px solid #0f766e; padding-bottom: 6px; }
        .header h1 { margin: 0; color: #0f766e; font-size: 18px; }
        .meta { margin-top: 4px; color: #555; font-size: 10px; }
        h2 { color: #0f766e; font-size: 12px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th { background: #0f766e; color: #fff; padding: 3px; text-align: center; font-weight: bold; }
        td { border: 1px solid #e5e7eb; padding: 3px; text-align: center; vertical-align: top; }
        td.label, th.label { text-align: left; }
        tr.total td { font-weight: bold; background: #f1f5f9; }
        tr.group td { font-weight: bold; background: #ecfdf5; text-align: left; }
        .note { color: #666; font-size: 8px; }
        .page-break { page-break-before: always; }
        .narrow { width: auto; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RMG Sustainability Council (RSC) — No. of Vehicle for {{ $report['period']['label'] }}</h1>
        <div class="meta">
            {{ $report['period']['from'] }} to {{ $report['period']['to'] }} · Generated {{ $exportDate }}
        </div>
    </div>

    <h2>Daily vehicles by purpose</h2>
    <table>
        <thead>
            <tr>
                <th class="label">Purpose</th>
                @foreach ($report['days'] as $day)
                    <th>{{ substr($day['weekday'], 0, 2) }}<br>{{ $day['day'] }}</th>
                @endforeach
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['daily'] as $row)
                <tr class="{{ $row['kind'] === 'total' ? 'total' : '' }}">
                    <td class="label">{{ $row['label'] }}</td>
                    @foreach ($row['values'] as $value)
                        <td>{{ $value ?: '' }}</td>
                    @endforeach
                    <td>{{ $row['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="note">Purpose rows count distinct vehicles per day. Staff Pick &amp; Drop is shown separately. Total vehicles counts each vehicle once per day.</div>

    <table class="narrow" style="margin-top: 10px;">
        <tr>
            <td style="border: none; width: 50%; padding-right: 10px;">
                <h2>Trips by purpose</h2>
                <table>
                    <thead><tr><th class="label">Purpose</th><th>Vehicle-days</th><th>Trips</th><th>Share %</th></tr></thead>
                    <tbody>
                        @foreach ($report['purposes'] as $p)
                            <tr><td class="label">{{ $p['label'] }}</td><td>{{ $p['vehicle_days'] }}</td><td>{{ $p['trips'] }}</td><td>{{ $p['share'] ?? '-' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>

                <h2>Ad-hoc vehicles needed in a day</h2>
                <table>
                    <thead><tr><th class="label">Ad-hoc vehicles</th><th>Working days</th><th>Share %</th><th>Ad-hoc vehicle-days</th></tr></thead>
                    <tbody>
                        @foreach ($report['adhoc_bands'] as $b)
                            <tr><td class="label">{{ $b['label'] }}</td><td>{{ $b['working_days'] }}</td><td>{{ $b['share'] }}</td><td>{{ $b['adhoc_vehicle_days'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>

                <h2>By office</h2>
                <table>
                    <thead><tr><th class="label">Office</th><th>Trips</th><th>Vehicle-days</th><th>Ad-hoc vehicle-days</th></tr></thead>
                    <tbody>
                        @forelse ($report['offices'] as $o)
                            <tr><td class="label">{{ $o['zone'] }}</td><td>{{ $o['trips'] }}</td><td>{{ $o['vehicle_days'] }}</td><td>{{ $o['adhoc_vehicle_days'] }}</td></tr>
                        @empty
                            <tr><td colspan="4">No trips</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td style="border: none; width: 50%;">
                <h2>Key numbers</h2>
                <table>
                    <thead><tr><th class="label">Measure</th><th>This period</th><th>Previous period</th></tr></thead>
                    <tbody>
                        @foreach ($report['key_numbers'] as $k)
                            <tr><td class="label">{{ $k['label'] }}</td><td>{{ $k['current'] }}</td><td>{{ $k['previous'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>

                <h2>Service quality</h2>
                <table>
                    <thead><tr><th class="label">Measure</th><th>This period</th><th>Previous period</th></tr></thead>
                    <tbody>
                        @foreach ($report['service'] as $s)
                            <tr><td class="label">{{ $s['label'] }}</td><td>{{ $s['current'] ?? '-' }}</td><td>{{ $s['previous'] ?? '-' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>

                <h2>Vendor scorecard</h2>
                <table>
                    <thead><tr><th class="label">Vendor</th><th>Trips</th><th>Vehicle-days</th><th>Late</th><th>Complaints</th><th>Open</th><th>Per 100 trips</th></tr></thead>
                    <tbody>
                        @forelse ($report['vendors'] as $v)
                            <tr class="{{ $v['vendor'] === 'All vendors' ? 'total' : '' }}">
                                <td class="label">{{ $v['vendor'] }}</td><td>{{ $v['trips'] }}</td><td>{{ $v['vehicle_days'] }}</td><td>-</td>
                                <td>{{ $v['complaints'] }}</td><td>{{ $v['complaints_open'] }}</td><td>{{ $v['issues_per_100'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No trips</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="note">Late arrivals are not tracked yet.</div>
            </td>
        </tr>
    </table>

    <div class="page-break"></div>
    <h2>Details — vehicle by day ((n) = trips that day)</h2>
    <table>
        <thead>
            <tr>
                <th>Sl#</th>
                <th class="label">Driver</th>
                <th class="label">Vendor</th>
                <th>Type</th>
                <th class="label">Vehicle#</th>
                @foreach ($report['days'] as $day)
                    <th>{{ substr($day['weekday'], 0, 2) }}<br>{{ $day['day'] }}</th>
                @endforeach
                <th>Trips</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['details']['groups'] as $group)
                <tr class="group"><td colspan="{{ count($report['days']) + 7 }}">{{ $group['label'] }}</td></tr>
                @foreach ($group['rows'] as $v)
                    <tr>
                        <td>{{ $v['sl'] }}</td>
                        <td class="label">{{ $v['driver'] }}</td>
                        <td class="label">{{ $v['vendor'] }}</td>
                        <td>{{ ucfirst((string) $v['vehicle_type']) }}</td>
                        <td class="label">{{ $v['registration_number'] }}</td>
                        @foreach ($v['cells'] as $cell)
                            <td>{{ $cell ? $cell['text'].' ('.$cell['trips'].')' : '' }}</td>
                        @endforeach
                        <td>{{ $v['total_trips'] }}</td>
                        <td>{{ $v['remarks'] }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="5" class="label">Vehicles used</td>
                    @foreach ($group['used_per_day'] as $n)
                        <td>{{ $n }}</td>
                    @endforeach
                    <td colspan="2"></td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="5" class="label"># of vehicles assigned per day</td>
                @foreach ($report['details']['assigned_per_day'] as $n)
                    <td>{{ $n }}</td>
                @endforeach
                <td colspan="2"></td>
            </tr>
            <tr class="total">
                <td colspan="5" class="label"># of vehicles remaining idle per day</td>
                @foreach ($report['details']['idle_per_day'] as $n)
                    <td>{{ $n }}</td>
                @endforeach
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
    <div class="note">Idle includes vehicles off for repair (downtime is not recorded yet). Fleet size: {{ $report['details']['fleet_size'] }}.</div>
</body>
</html>
