<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\ProductStoreRequest;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-products', only: ['index', 'show']),
            new Middleware('permission:export-products', only: ['export']),
            new Middleware('permission:create-products', only: ['create', 'store']),
            new Middleware('permission:edit-products', only: ['edit', 'update']),
            new Middleware('permission:delete-products', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ProductIndexRequest $request)
    {
        $validated = $request->validated();
        $query = Product::query();

        // Apply search
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if (!empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (!empty($filters['category'])) {
                $query->whereIn('category', $filters['category']);
            }

            if (!empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'];
        $sortDirection = $validated['direction'];
        $query->orderBy($sortColumn, $sortDirection);

        // Get pagination data
        $products = $query->paginate($validated['per_page'])
            ->withQueryString(); // Preserve query parameters in pagination links

        // Get filter options for the frontend
        $filterOptions = [
            'categories' => Product::distinct()->pluck('category')->sort()->values(),
            'statuses' => ['active', 'inactive', 'pending'],
        ];

        // Get stats with a single aggregate query
        $productStats = Product::selectRaw(
            'COUNT(*) as total, ' .
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active, ' .
            'COUNT(DISTINCT category) as categories, ' .
            'COALESCE(SUM(price), 0) as total_value',
            ['active']
        )->first();

        $stats = [
            'total' => (int) ($productStats->total ?? 0),
            'active' => (int) ($productStats->active ?? 0),
            'categories' => (int) ($productStats->categories ?? 0),
            'total_value' => (float) ($productStats->total_value ?? 0),
        ];

        return Inertia::render('products/index', [
            'products' => $products,
            'filterOptions' => $filterOptions,
            'stats' => $stats,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'filters', 'per_page']),
        ]);



    }



    /**
     * Export products in various formats
     */
    public function export(ProductIndexRequest $request)
    {
        $validated = $request->validated();
        $format = $validated['format'] ?? 'csv';
        $templateId = $request->input('template_id');

        // Get export template if specified
        $template = null;
        // TODO: Implement ExportTemplate model if needed
        // if ($templateId) {
        //     $template = ExportTemplate::accessibleBy(auth()->id())
        //         ->where('id', $templateId)
        //         ->first();
        // }

        $query = Product::query();

        // Apply the same filters as index method
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (!empty($filters['category'])) {
                $query->whereIn('category', $filters['category']);
            }

            if (!empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'];
        $sortDirection = $validated['direction'];
        $query->orderBy($sortColumn, $sortDirection);

        // Generate filename with timestamp
        $timestamp = now()->format('Y-m-d_H-i-s');

        switch ($format) {
            case 'excel':
                return $this->exportExcel($query, $timestamp, $template);
            case 'pdf':
                return $this->exportPdf($query, $timestamp, $template);
            case 'csv':
            default:
                return $this->exportCsv($query, $timestamp, $template);
        }
    }

    /**
     * Export to CSV format
     */
    private function exportCsv($query, $timestamp, $template = null)
    {
        $products = $query->get();
        $csvContent = $this->generateCsv($products, $template);
        $filename = "products_export_{$timestamp}.csv";

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Export to Excel format
     */
    private function exportExcel($query, $timestamp, $template = null)
    {
        $filename = "products_export_{$timestamp}.xlsx";

        return Excel::download(new ProductsExport($query, $template), $filename);
    }

    /**
     * Export to PDF format
     */
    private function exportPdf($query, $timestamp, $template = null)
    {
        $products = $query->get();
        $filename = "products_export_{$timestamp}.pdf";

        // Generate PDF using DomPDF
        $pdf = Pdf::loadView('exports.products-pdf', [
            'products' => $products,
            'exportDate' => now()->format('F j, Y \a\t g:i A'),
            'totalRecords' => $products->count(),
            'template' => $template,
        ]);

        // Set PDF options for better rendering
        $pdf->setPaper('A4', 'landscape')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => public_path(),
            ]);

        return $pdf->download($filename);
    }

    /**
     * Generate CSV content from products collection
     */
    private function generateCsv($products, $template = null)
    {
        $output = fopen('php://temp', 'r+');


        $enabledColumns = ['id', 'name', 'description', 'price', 'category', 'status', 'created_at', 'updated_at'];
        $headers = ['ID', 'Name', 'Description', 'Price', 'Category', 'Status', 'Created At', 'Updated At'];

        // Add CSV headers
        fputcsv($output, $headers);

        // Add product data
        foreach ($products as $product) {
            $row = [];
            foreach ($enabledColumns as $columnKey) {
                switch ($columnKey) {
                    case 'id':
                        $row[] = $product->id;
                        break;
                    case 'name':
                        $row[] = $product->name;
                        break;
                    case 'description':
                        $row[] = $product->description;
                        break;
                    case 'price':
                        $row[] = $product->price;
                        break;
                    case 'category':
                        $row[] = $product->category;
                        break;
                    case 'status':
                        $row[] = $product->status;
                        break;
                    case 'created_at':
                        $row[] = $product->created_at->format('Y-m-d H:i:s');
                        break;
                    case 'updated_at':
                        $row[] = $product->updated_at->format('Y-m-d H:i:s');
                        break;
                    default:
                        $row[] = $product->{$columnKey} ?? '';
                        break;
                }
            }
            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get available categories from existing products
        $categories = Product::distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        // Add some common categories if none exist
        if (empty($categories)) {
            $categories = [
                'Electronics',
                'Clothing',
                'Books',
                'Home & Garden',
                'Sports',
                'Toys',
                'Beauty',
                'Automotive',
                'Food',
                'Health'
            ];
        }

        $statusOptions = [
            ['label' => 'Active', 'value' => 'active'],
            ['label' => 'Inactive', 'value' => 'inactive'],
            ['label' => 'Pending', 'value' => 'pending'],
        ];

        return Inertia::render('products/create', [
            'categories' => $categories,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductStoreRequest $request)
    {
        try {
            Product::create($request->validated());
            return redirect()->route('products.index')
                ->with('success', 'Product created successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create product.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        //
    }
}
