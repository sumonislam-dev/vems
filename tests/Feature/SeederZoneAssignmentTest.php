<?php

use App\Models\Factory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Support\Facades\Artisan;

/**
 * A fresh `migrate:fresh --seed` should come up with the Dhaka/Chattogram
 * zones already in place and seeded Users/Vehicles/Vendors/Factories already
 * assigned to one, so the zone feature is usable immediately rather than
 * requiring an admin to manually backfill a brand-new environment.
 */
it('seeds zones and assigns them across dependent seeders', function () {
    Artisan::call('db:seed');

    $dhaka = Zone::where('code', 'DHK')->first();
    $ctg = Zone::where('code', 'CTG')->first();

    expect($dhaka)->not->toBeNull();
    expect($ctg)->not->toBeNull();

    // Factory: derived from its `city` field.
    $dhakaFactory = Factory::where('account_id', '9178')->first(); // A.K.M. KNIT WEAR LTD., city Dhaka
    $ctgFactory = Factory::where('account_id', '10190')->first(); // PACIFIC JEANS LTD., city Chittagong
    expect($dhakaFactory->zone_id)->toBe($dhaka->id);
    expect($ctgFactory->zone_id)->toBe($ctg->id);

    // Vendor: derived from its free-text `address`.
    $dhakaVendor = Vendor::where('email', 'info@autocare.com.bd')->first(); // Dhaka-1000 address
    $ctgVendor = Vendor::where('email', 'contact@metrovehicle.com')->first(); // Chittagong-4000 address
    $sylhetVendor = Vendor::where('email', 'service@expressauto.bd')->first(); // Sylhet-3100, no matching zone
    expect($dhakaVendor->zone_id)->toBe($dhaka->id);
    expect($ctgVendor->zone_id)->toBe($ctg->id);
    expect($sylhetVendor->zone_id)->toBeNull();

    // Vehicle: no location data of its own, deterministically split.
    expect(Vehicle::where('zone_id', $dhaka->id)->count())->toBeGreaterThan(0);
    expect(Vehicle::where('zone_id', $ctg->id)->count())->toBeGreaterThan(0);
    expect(Vehicle::whereNull('zone_id')->count())->toBe(0);

    // User: derived from present_address/address.
    $admin = User::where('email', 'admin@vems.com')->first();
    $transportManager = User::where('email', 'transport.manager@vems.com')->first();
    expect($admin->zone_id)->toBe($dhaka->id);
    expect($transportManager->zone_id)->toBe($ctg->id);
});
