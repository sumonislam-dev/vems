<?php

use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'create-products', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('create-products');
});

describe('Product Creation', function () {
    it('can display the create product form', function () {
        $response = $this->actingAs($this->user)
            ->get(route('products.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => 
            $page->component('products/create')
                ->has('categories')
                ->has('statusOptions')
        );
    });

    it('can create a product with valid data', function () {
        $productData = [
            'name' => 'Test Product',
            'description' => 'This is a test product description that is long enough to pass validation.',
            'price' => '29.99',
            'category' => 'Electronics',
            'status' => 'active'
        ];

        $response = $this->actingAs($this->user)
            ->post(route('products.store'), $productData);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success', 'Product created successfully!');

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'description' => 'This is a test product description that is long enough to pass validation.',
            'price' => 29.99,
            'category' => 'Electronics',
            'status' => 'active'
        ]);
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->user)
            ->post(route('products.store'), []);

        $response->assertSessionHasErrors([
            'name',
            'description',
            'price',
            'category',
            'status'
        ]);
    });

    it('validates product name uniqueness', function () {
        Product::factory()->create(['name' => 'Existing Product']);

        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Existing Product',
                'description' => 'This is a test product description that is long enough to pass validation.',
                'price' => '29.99',
                'category' => 'Electronics',
                'status' => 'active'
            ]);

        $response->assertSessionHasErrors(['name']);
    });

    it('validates price is numeric and positive', function () {
        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Test Product',
                'description' => 'This is a test product description that is long enough to pass validation.',
                'price' => 'invalid-price',
                'category' => 'Electronics',
                'status' => 'active'
            ]);

        $response->assertSessionHasErrors(['price']);

        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Test Product',
                'description' => 'This is a test product description that is long enough to pass validation.',
                'price' => '-10.00',
                'category' => 'Electronics',
                'status' => 'active'
            ]);

        $response->assertSessionHasErrors(['price']);
    });

    it('validates status is one of allowed values', function () {
        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Test Product',
                'description' => 'This is a test product description that is long enough to pass validation.',
                'price' => '29.99',
                'category' => 'Electronics',
                'status' => 'invalid-status'
            ]);

        $response->assertSessionHasErrors(['status']);
    });

    it('validates description length', function () {
        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Test Product',
                'description' => 'Short',
                'price' => '29.99',
                'category' => 'Electronics',
                'status' => 'active'
            ]);

        $response->assertSessionHasErrors(['description']);

        $longDescription = str_repeat('a', 1001);
        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Test Product',
                'description' => $longDescription,
                'price' => '29.99',
                'category' => 'Electronics',
                'status' => 'active'
            ]);

        $response->assertSessionHasErrors(['description']);
    });

    it('cleans price input by removing currency symbols', function () {
        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Test Product',
                'description' => 'This is a test product description that is long enough to pass validation.',
                'price' => '$29.99',
                'category' => 'Electronics',
                'status' => 'active'
            ]);

        $response->assertRedirect(route('products.index'));
        
        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'price' => 29.99
        ]);
    });
});

describe('Product Creation Authorization', function () {
    it('requires authentication to access create form', function () {
        $response = $this->get(route('products.create'));
        
        $response->assertRedirect(route('login'));
    });

    it('requires authentication to create product', function () {
        $response = $this->post(route('products.store'), [
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => '29.99',
            'category' => 'Electronics',
            'status' => 'active'
        ]);
        
        $response->assertRedirect(route('login'));
    });
});

describe('Product Categories', function () {
    it('provides existing categories as options', function () {
        Product::factory()->create(['category' => 'Electronics']);
        Product::factory()->create(['category' => 'Books']);
        Product::factory()->create(['category' => 'Electronics']); // Duplicate

        $response = $this->actingAs($this->user)
            ->get(route('products.create'));

        $response->assertInertia(fn ($page) => 
            $page->has('categories', 2) // Should have unique categories
                ->where('categories.0', 'Books')
                ->where('categories.1', 'Electronics')
        );
    });

    it('provides default categories when none exist', function () {
        $response = $this->actingAs($this->user)
            ->get(route('products.create'));

        $response->assertInertia(fn ($page) =>
            $page->has('categories')
                ->where('categories', fn ($categories) =>
                    $categories->contains('Electronics') &&
                    $categories->contains('Books') &&
                    $categories->count() === 10
                )
        );
    });
});
