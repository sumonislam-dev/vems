import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, ColumnFilter, DataTableColumn, Product } from '@/types';
import { Head } from '@inertiajs/react';
import { Edit, Eye, Plus, Trash2 } from 'lucide-react';

interface PaginatedProducts {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface ExportTemplate {
    id: number;
    name: string;
    description?: string;
    columns: Record<string, { enabled: boolean; order: number }>;
    is_default: boolean;
    is_public: boolean;
    user_id: number;
}

interface ColumnDefinition {
    key: string;
    label: string;
    type: string;
    required: boolean;
}

interface ProductsPageProps {
    products: PaginatedProducts;
    filterOptions: {
        categories: string[];
        statuses: string[];
    };
    stats: {
        total: number;
        active: number;
        categories: number;
        total_value: number;
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        filters?: Record<string, unknown>;
        per_page?: number;
    };
    exportTemplates: ExportTemplate[];
    availableColumns: Record<string, ColumnDefinition>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Products', href: '/products' },
];

export default function ProductsIndex({ products, filterOptions, stats, queryParams }: ProductsPageProps) {
    // Define table columns
    const columns: DataTableColumn<Product>[] = [
        {
            key: 'name',
            label: 'Product Name',
            sortable: true,
            render: (value, row) => (
                <div className="flex flex-col">
                    <span className="font-medium">{value}</span>
                    <span className="max-w-xs truncate text-sm text-muted-foreground">{row.description}</span>
                </div>
            ),
        },
        {
            key: 'category',
            label: 'Category',
            sortable: true,
            filterable: true,
            render: (value) => (
                <Badge variant="outline" className="capitalize">
                    {value}
                </Badge>
            ),
        },
        {
            key: 'price',
            label: 'Price',
            sortable: true,
            render: (value) => `$${Number(value).toFixed(2)}`,
            className: 'text-right font-mono',
            headerClassName: 'text-right',
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            filterable: true,
            render: (value) => {
                const variants = {
                    active: 'default',
                    inactive: 'secondary',
                    pending: 'outline',
                } as const;

                return <Badge variant={variants[value as keyof typeof variants]}>{value}</Badge>;
            },
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (value) =>
                new Date(value).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                }),
            className: 'text-muted-foreground',
        },
        {
            key: 'actions' as keyof Product,
            label: 'Actions',
            render: (_, row) => (
                <div className="flex items-center space-x-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            alert(`View product: ${row.name}`);
                        }}
                        title="View product"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            alert(`Edit product: ${row.name}`);
                        }}
                        title="Edit product"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (confirm(`Are you sure you want to delete ${row.name}?`)) {
                                alert(`Delete product: ${row.name}`);
                            }
                        }}
                        title="Delete product"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    // Define filters
    const filters: ColumnFilter[] = [
        {
            key: 'category',
            label: 'Category',
            type: 'multiselect',
            options: filterOptions.categories.map((category) => ({
                label: category,
                value: category,
            })),
        },
        {
            key: 'status',
            label: 'Status',
            type: 'multiselect',
            options: filterOptions.statuses.map((status) => ({
                label: status.charAt(0).toUpperCase() + status.slice(1),
                value: status,
            })),
        },
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Products" />

            <div className="space-y-6">
                <PageHeader
                    title="Products"
                    description="Manage your product inventory with advanced filtering and search."
                    actions={[
                        {
                            label: 'Add Product',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: route('products.create'),
                        },
                    ]}
                    stats={[
                        {
                            label: 'Total Products',
                            value: stats.total,
                        },
                        {
                            label: 'Active Products',
                            value: stats.active,
                        },
                        {
                            label: 'Categories',
                            value: stats.categories,
                        },
                        {
                            label: 'Total Value',
                            value: `$${Number(stats.total_value).toFixed(2)}`,
                        },
                    ]}
                />

                {/* Data Table */}
                <ServerSideDataTable
                    data={products}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={filterOptions}
                    filters={filters}
                    searchPlaceholder="Search products..."
                    exportable={true}
                    exportUrl="/products-export"
                    // onRowClick={handleRowClick}
                    emptyMessage="No products found. Add your first product to get started."
                    showSerialColumn
                />
            </div>
        </AppSidebarLayout>
    );
}
