<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Support\PrivateDocument;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves private documents (see PrivateDocument) after a permission check.
 * Route model binding applies the zone scope, so users can only open
 * documents of records in their own zones. `?download=1` forces a download.
 */
class DocumentController extends Controller
{
    public const USER_FIELDS = ['nid_file', 'driving_license_file'];

    public const VEHICLE_FIELDS = ['tax_token_file', 'fitness_certificate_file', 'insurance_policy_file', 'registration_certificate_file'];

    public const VENDOR_FIELDS = ['trade_license_file', 'tin_file', 'bin_file', 'tax_return_file'];

    public function user(Request $request, User $user, string $field): StreamedResponse
    {
        $viewer = $request->user();
        $isDriver = in_array($user->user_type, ['driver', 'transport_manager']);

        abort_unless(
            $viewer->is($user) || $viewer->can('view-users') || ($isDriver && $viewer->can('view-drivers')),
            403
        );

        return PrivateDocument::response($user->{$field}, $request->boolean('download'));
    }

    public function vehicle(Request $request, Vehicle $vehicle, string $field): StreamedResponse
    {
        abort_unless($request->user()->can('view-vehicles'), 403);

        return PrivateDocument::response($vehicle->{$field}, $request->boolean('download'));
    }

    public function vendor(Request $request, Vendor $vendor, string $field): StreamedResponse
    {
        abort_unless($request->user()->can('view-vendors'), 403);

        return PrivateDocument::response($vendor->{$field}, $request->boolean('download'));
    }
}
