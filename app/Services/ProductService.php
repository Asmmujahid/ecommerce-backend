<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\ProductOutOfStockNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Get all products for admin.
     */
    public function adminProducts(Request $request)
    {
        $query = Product::with([
            'category',
            'brand',
            'vendor',
            'images',
            'variants',
        ]);

        if ($request->filled('search')) {
            $keyword = $request->input('search');

            if (is_array($keyword)) {
                $keyword =
                    $keyword['keyword']
                    ?? $keyword['search']
                    ?? '';
            }

            $keyword = trim((string) $keyword);

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->where(
                        'name',
                        'like',
                        "%{$keyword}%"
                    )->orWhere(
                        'sku',
                        'like',
                        "%{$keyword}%"
                    );
                });
            }
        }

        if ($request->filled('category')) {
            $query->whereHas(
                'category',
                function ($q) use ($request) {
                    $q->where(
                        'name',
                        $request->input('category')
                    );
                }
            );
        }

        if ($request->filled('brand')) {
            $query->whereHas(
                'brand',
                function ($q) use ($request) {
                    $q->where(
                        'name',
                        $request->input('brand')
                    );
                }
            );
        }

        if ($request->filled('status')) {
            $status = $request->input('status');

            if (
                $status === 'active' ||
                $status === '1' ||
                $status === 1
            ) {
                $query->where('status', true);
            } elseif (
                $status === 'inactive' ||
                $status === '0' ||
                $status === 0
            ) {
                $query->where('status', false);
            }
        }

        if ($request->filled('featured')) {
            $featured = $request->input('featured');

            if (
                $featured === '1' ||
                $featured === 1 ||
                $featured === true ||
                $featured === 'true'
            ) {
                $query->where('featured', true);
            } elseif (
                $featured === '0' ||
                $featured === 0 ||
                $featured === false ||
                $featured === 'false'
            ) {
                $query->where('featured', false);
            }
        }

        if ($request->filled('stock')) {
            switch ($request->input('stock')) {
                case 'in_stock':
                    $query->where('stock', '>', 5);
                    break;

                case 'low_stock':
                    $query->whereBetween('stock', [1, 5]);
                    break;

                case 'out_of_stock':
                    $query->where('stock', '<=', 0);
                    break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Owner Type Filter
        |--------------------------------------------------------------------------
        |
        | This filter is optional for ADMIN.
        |
        | ?owner_type=admin
        | ?owner_type=vendor
        |
        */

        if ($request->filled('owner_type')) {
            $ownerType = $request->input('owner_type');

            if (
                in_array(
                    $ownerType,
                    ['admin', 'vendor'],
                    true
                )
            ) {
                $query->where(
                    'owner_type',
                    $ownerType
                );
            }
        }

        if ($request->filled('sort')) {
            switch ($request->input('sort')) {
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;

                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;

                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;

                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;

                default:
                    $query->latest();
                    break;
            }
        } else {
            $query->latest();
        }

        return $query->get();
    }

    /**
     * Get products available in a Vendor Store.
     *
     * IMPORTANT:
     *
     * vendor_id identifies the Vendor Store that is
     * selling/handling the product.
     *
     * Therefore BOTH product ownership types are returned:
     *
     * 1. owner_type = vendor
     *    Vendor owns the product.
     *
     * 2. owner_type = admin
     *    Admin owns the product, but assigned it to
     *    this Vendor Store.
     *
     * Seller visibility is based ONLY on vendor_id.
     *
     * Seller editing/deleting permissions are handled
     * separately in show(), update(), and destroy().
     */
    public function sellerProducts(
        $vendorId,
        Request $request
    ) {
        $query = Product::with([
            'category',
            'brand',
            'vendor',
            'images',
            'variants',
        ])
            ->where(
                'vendor_id',
                $vendorId
            );

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $keyword = $request->input('search');

            if (is_array($keyword)) {
                $keyword =
                    $keyword['keyword']
                    ?? $keyword['search']
                    ?? '';
            }

            $keyword = trim((string) $keyword);

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->where(
                        'name',
                        'like',
                        "%{$keyword}%"
                    )->orWhere(
                        'sku',
                        'like',
                        "%{$keyword}%"
                    );
                });
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        if ($request->filled('category')) {
            $query->where(
                'category_id',
                $request->input('category')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Stock
        |--------------------------------------------------------------------------
        */

        if ($request->filled('stock')) {
            switch ($request->input('stock')) {
                case 'in_stock':
                    $query->where(
                        'stock',
                        '>',
                        0
                    );
                    break;

                case 'out_of_stock':
                    $query->where(
                        'stock',
                        '<=',
                        0
                    );
                    break;

                case 'low_stock':
                    $query->whereBetween(
                        'stock',
                        [1, 5]
                    );
                    break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | OPTIONAL Owner Type Filter
        |--------------------------------------------------------------------------
        |
        | Normally the seller receives BOTH types.
        |
        | If frontend explicitly sends:
        |
        | ?owner_type=vendor
        |
        | only vendor-owned products are returned.
        |
        | If frontend sends:
        |
        | ?owner_type=admin
        |
        | only admin-owned products are returned.
        |
        | If no owner_type is sent:
        | BOTH are returned.
        |
        */

        if ($request->filled('owner_type')) {
            $ownerType = $request->input('owner_type');

            if (
                in_array(
                    $ownerType,
                    ['admin', 'vendor'],
                    true
                )
            ) {
                $query->where(
                    'owner_type',
                    $ownerType
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */

        $allowedSorts = [
            'name',
            'price',
            'stock',
            'created_at',
            'updated_at',
        ];

        if ($request->filled('sort')) {
            $sort = $request->input('sort');

            if (
                in_array(
                    $sort,
                    $allowedSorts,
                    true
                )
            ) {
                $direction = strtolower(
                    $request->input(
                        'direction',
                        'asc'
                    )
                );

                if (
                    !in_array(
                        $direction,
                        ['asc', 'desc'],
                        true
                    )
                ) {
                    $direction = 'asc';
                }

                $query->orderBy(
                    $sort,
                    $direction
                );
            } else {
                $query->latest();
            }
        } else {
            $query->latest();
        }

        return $query->paginate(10);
    }

    /**
     * Get all active customer products.
     */
    public function customerProducts()
    {
        return Product::with([
            'category',
            'brand',
            'vendor',
            'images',
            'variants',
            'reviews.user',
        ])
            ->where('status', true)
            ->latest()
            ->get();
    }

    /**
     * Search active products.
     */
    public function searchProducts($keyword = null)
    {
        if (is_array($keyword)) {
            $keyword =
                $keyword['keyword']
                ?? $keyword['search']
                ?? $keyword['q']
                ?? '';
        }

        $keyword = trim((string) $keyword);

        return Product::with([
            'category',
            'brand',
            'vendor',
            'images',
            'variants',
            'reviews.user',
        ])
            ->where('status', true)
            ->when(
                $keyword !== '',
                function ($query) use ($keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where(
                            'name',
                            'like',
                            "%{$keyword}%"
                        )
                            ->orWhere(
                                'sku',
                                'like',
                                "%{$keyword}%"
                            )
                            ->orWhere(
                                'short_description',
                                'like',
                                "%{$keyword}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$keyword}%"
                            );
                    });
                }
            )
            ->latest()
            ->get();
    }

    /**
     * Filter active products.
     */
    public function filterProducts(array $filters)
    {
        $query = Product::with([
            'category',
            'brand',
            'vendor',
            'images',
            'variants',
            'reviews.user',
        ])
            ->where('status', true);

        if (!empty($filters['category_id'])) {
            $query->where(
                'category_id',
                $filters['category_id']
            );
        }

        if (!empty($filters['brand_id'])) {
            $query->where(
                'brand_id',
                $filters['brand_id']
            );
        }

        if (
            isset($filters['featured']) &&
            $filters['featured'] !== '' &&
            $filters['featured'] !== null
        ) {
            $query->where(
                'featured',
                $filters['featured']
            );
        }

        if (
            isset($filters['min_price']) &&
            $filters['min_price'] !== ''
        ) {
            $query->where(
                'price',
                '>=',
                $filters['min_price']
            );
        }

        if (
            isset($filters['max_price']) &&
            $filters['max_price'] !== ''
        ) {
            $query->where(
                'price',
                '<=',
                $filters['max_price']
            );
        }

        if (
            isset($filters['in_stock']) &&
            filter_var(
                $filters['in_stock'],
                FILTER_VALIDATE_BOOLEAN
            )
        ) {
            $query->where(
                'stock',
                '>',
                0
            );
        }

        return $query
            ->latest()
            ->get();
    }

    /**
     * Show active product.
     */
    public function showProduct($id)
    {
        return Product::with([
            'category',
            'brand',
            'vendor',
            'images',
            'variants',
            'reviews.user',
        ])
            ->where('status', true)
            ->find($id);
    }

    /**
     * Create product.
     *
     * owner_type:
     *
     * vendor = Vendor owns product
     * admin  = Admin owns product
     */
    public function createProduct(
        array $data,
        $vendorId = null,
        string $ownerType = 'vendor'
    ) {
        if (
            !in_array(
                $ownerType,
                ['admin', 'vendor'],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Invalid product owner type.'
            );
        }

        if (!$vendorId) {
            throw new \InvalidArgumentException(
                'Vendor ID is required.'
            );
        }

        return DB::transaction(
            function () use (
                $data,
                $vendorId,
                $ownerType
            ) {
                $product = Product::create([
                    'vendor_id' =>
                        $vendorId,

                    'owner_type' =>
                        $ownerType,

                    'category_id' =>
                        $data['category_id'],

                    'brand_id' =>
                        $data['brand_id'],

                    'name' =>
                        $data['name'],

                    'slug' =>
                        $this->generateUniqueSlug(
                            $data['name']
                        ),

                    'sku' =>
                        $data['sku'],

                    'short_description' =>
                        $data['short_description']
                        ?? null,

                    'description' =>
                        $data['description']
                        ?? null,

                    'price' =>
                        $data['price'],

                    'discount_price' =>
                        $data['discount_price']
                        ?? null,

                    'stock' =>
                        (int) $data['stock'],

                    'thumbnail' =>
                        $data['thumbnail']
                        ?? null,

                    'status' =>
                        $data['status']
                        ?? true,

                    'featured' =>
                        $data['featured']
                        ?? false,
                ]);

                if (
                    !empty($data['images']) &&
                    is_array($data['images'])
                ) {
                    foreach (
                        $data['images']
                        as $image
                    ) {
                        if (
                            is_array($image) &&
                            !empty(
                                $image['image']
                            )
                        ) {
                            ProductImage::create([
                                'product_id' =>
                                    $product->id,

                                'image' =>
                                    $image['image'],
                            ]);
                        }
                    }
                }

                if (
                    !empty($data['variants']) &&
                    is_array($data['variants'])
                ) {
                    foreach (
                        $data['variants']
                        as $variant
                    ) {
                        if (
                            !is_array($variant)
                        ) {
                            continue;
                        }

                        ProductVariant::create([
                            'product_id' =>
                                $product->id,

                            'sku' =>
                                $variant['sku'],

                            'size' =>
                                $variant['size']
                                ?? null,

                            'color' =>
                                $variant['color']
                                ?? null,

                            'price' =>
                                $variant['price']
                                ?? null,

                            'stock' =>
                                (int) (
                                    $variant['stock']
                                    ?? 0
                                ),
                        ]);
                    }
                }

                $product->load([
                    'category',
                    'brand',
                    'vendor',
                    'images',
                    'variants',
                ]);

                if (
                    (int) $product->stock <= 0
                ) {
                    $this->notifyOutOfStock(
                        $product
                    );
                }

                return $product;
            }
        );
    }

    /**
     * Generate unique product slug.
     */
    protected function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name);

        $slug = $baseSlug;

        $counter = 1;

        while (
            Product::where(
                'slug',
                $slug
            )
                ->when(
                    $ignoreId,
                    fn ($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $ignoreId
                        )
                )
                ->exists()
        ) {
            $slug =
                $baseSlug .
                '-' .
                $counter;

            $counter++;
        }

        return $slug;
    }

    /**
     * Update product.
     *
     * Ownership is NOT changed here.
     */
    public function updateProduct(
        Product $product,
        array $data
    ) {
        return DB::transaction(
            function () use (
                $product,
                $data
            ) {
                $oldStock =
                    (int) $product->stock;

                /*
                 * Never allow owner_type to be changed
                 * through normal product update.
                 */
                unset(
                    $data['owner_type']
                );

                /*
                 * Admin may change vendor_id.
                 *
                 * Seller controller does not send vendor_id.
                 */
                if (isset($data['vendor_id'])) {
                    $data['vendor_id'] =
                        (int) $data['vendor_id'];
                }

                if (isset($data['name'])) {
                    $data['slug'] =
                        $this->generateUniqueSlug(
                            $data['name'],
                            $product->id
                        );
                }

                $product->update($data);

                $product = $product
                    ->fresh()
                    ->load([
                        'category',
                        'brand',
                        'vendor',
                        'images',
                        'variants',
                    ]);

                $newStock =
                    (int) $product->stock;

                if (
                    $oldStock > 0 &&
                    $newStock <= 0
                ) {
                    $this->notifyOutOfStock(
                        $product
                    );
                }

                return $product;
            }
        );
    }

    /**
     * Update product stock.
     */
    public function updateStock(
        Product $product,
        int $newStock
    ) {
        return DB::transaction(
            function () use (
                $product,
                $newStock
            ) {
                $oldStock =
                    (int) $product->stock;

                $newStock =
                    max(0, $newStock);

                $product->update([
                    'stock' =>
                        $newStock,
                ]);

                $product = $product
                    ->fresh()
                    ->load([
                        'vendor.user',
                    ]);

                if (
                    $oldStock > 0 &&
                    $newStock <= 0
                ) {
                    $this->notifyOutOfStock(
                        $product
                    );
                }

                return $product;
            }
        );
    }

    /**
     * Decrease product stock.
     */
    public function decreaseStock(
        Product $product,
        int $quantity
    ) {
        return DB::transaction(
            function () use (
                $product,
                $quantity
            ) {
                $product = Product::where(
                    'id',
                    $product->id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldStock =
                    (int) $product->stock;

                $quantity =
                    max(0, $quantity);

                $newStock = max(
                    0,
                    $oldStock - $quantity
                );

                $product->update([
                    'stock' =>
                        $newStock,
                ]);

                $product = $product
                    ->fresh()
                    ->load([
                        'vendor.user',
                    ]);

                if (
                    $oldStock > 0 &&
                    $newStock <= 0
                ) {
                    $this->notifyOutOfStock(
                        $product
                    );
                }

                return $product;
            }
        );
    }

    /**
     * Notify admins and seller when product
     * is out of stock.
     */
    public function notifyOutOfStock(
        Product $product
    ): void {
        $product->loadMissing([
            'vendor.user',
        ]);

        $admins = User::whereHas(
            'roles',
            function ($query) {
                $query->where(
                    'name',
                    'admin'
                );
            }
        )->get();

        $seller = $product
            ->vendor
            ?->user;

        $recipients = $admins
            ->push($seller)
            ->filter()
            ->unique('id');

        foreach (
            $recipients
            as $recipient
        ) {
            $alreadyNotified =
                $recipient
                    ->unreadNotifications()
                    ->where(
                        'type',
                        ProductOutOfStockNotification::class
                    )
                    ->where(
                        'data->product_id',
                        $product->id
                    )
                    ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $recipient->notify(
                new ProductOutOfStockNotification(
                    $product
                )
            );
        }
    }

    /**
     * Delete product.
     */
    public function deleteProduct(
        Product $product
    ) {
        return DB::transaction(
            function () use (
                $product
            ) {
                ProductImage::where(
                    'product_id',
                    $product->id
                )->delete();

                ProductVariant::where(
                    'product_id',
                    $product->id
                )->delete();

                $product->delete();

                return true;
            }
        );
    }

    /**
     * Add product image.
     */
    public function addImage(
        Product $product,
        string $image
    ) {
        return ProductImage::create([
            'product_id' =>
                $product->id,

            'image' =>
                $image,
        ]);
    }

    /**
     * Update product image.
     */
    public function updateImage(
        ProductImage $image,
        string $path
    ) {
        $image->update([
            'image' =>
                $path,
        ]);

        return $image->fresh();
    }

    /**
     * Delete product image.
     */
    public function deleteImage(
        ProductImage $image
    ) {
        return $image->delete();
    }

    /**
     * Add product variant.
     */
    public function addVariant(
        Product $product,
        array $data
    ) {
        $variant = ProductVariant::create([
            'product_id' =>
                $product->id,

            'sku' =>
                $data['sku'],

            'size' =>
                $data['size']
                ?? null,

            'color' =>
                $data['color']
                ?? null,

            'price' =>
                $data['price']
                ?? null,

            'stock' =>
                (int) (
                    $data['stock']
                    ?? 0
                ),
        ]);

        $product = $product
            ->fresh()
            ->load([
                'vendor.user',
            ]);

        if (
            (int) $product->stock <= 0
        ) {
            $this->notifyOutOfStock(
                $product
            );
        }

        return $variant;
    }

    /**
     * Update product variant.
     */
    public function updateVariant(
        ProductVariant $variant,
        array $data
    ) {
        $updateData = [];

        if (
            array_key_exists(
                'sku',
                $data
            )
        ) {
            $updateData['sku'] =
                $data['sku'];
        }

        if (
            array_key_exists(
                'size',
                $data
            )
        ) {
            $updateData['size'] =
                $data['size'];
        }

        if (
            array_key_exists(
                'color',
                $data
            )
        ) {
            $updateData['color'] =
                $data['color'];
        }

        if (
            array_key_exists(
                'price',
                $data
            )
        ) {
            $updateData['price'] =
                $data['price'];
        }

        if (
            array_key_exists(
                'stock',
                $data
            )
        ) {
            $updateData['stock'] =
                (int) $data['stock'];
        }

        $variant->update(
            $updateData
        );

        $product = $variant
            ->fresh()
            ->product
            ?->load([
                'vendor.user',
            ]);

        if (
            $product &&
            (int) $product->stock <= 0
        ) {
            $this->notifyOutOfStock(
                $product
            );
        }

        return $variant->fresh();
    }

    /**
     * Delete product variant.
     */
    public function deleteVariant(
        ProductVariant $variant
    ) {
        return $variant->delete();
    }
}

