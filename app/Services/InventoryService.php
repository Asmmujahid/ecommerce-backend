<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\ProductOutOfStockNotification;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * =====================================================
     * ADMIN INVENTORIES
     * =====================================================
     */
    public function adminInventories()
    {
        return Inventory::with([
            'product',
            'productVariant',
        ])
            ->latest()
            ->get();
    }

    /**
     * =====================================================
     * SELLER INVENTORIES
     * =====================================================
     */
    public function sellerInventories(
        $vendorId
    ) {
        return Inventory::with([
            'product',
            'productVariant',
        ])
            ->whereHas(
                'product',
                function ($query) use ($vendorId) {
                    $query->where(
                        'vendor_id',
                        $vendorId
                    );
                }
            )
            ->latest()
            ->get();
    }

    /**
     * =====================================================
     * FIND INVENTORY
     * =====================================================
     */
    public function findInventory(
        $id
    ) {
        return Inventory::with([
            'product',
            'productVariant',
        ])->find($id);
    }

    /**
     * =====================================================
     * FIND SELLER INVENTORY
     * =====================================================
     */
    public function findSellerInventory(
        $vendorId,
        $id
    ) {
        return Inventory::with([
            'product',
            'productVariant',
        ])
            ->whereHas(
                'product',
                function ($query) use ($vendorId) {
                    $query->where(
                        'vendor_id',
                        $vendorId
                    );
                }
            )
            ->find($id);
    }

    /**
     * =====================================================
     * CREATE ADMIN INVENTORY
     * =====================================================
     */
    public function createInventory(
        array $data
    ) {
        return DB::transaction(
            function () use ($data) {

                $product = null;
                $variant = null;

                /**
                 * -----------------------------------------
                 * Find product
                 * -----------------------------------------
                 */
                if (!empty($data['product_id'])) {

                    $product =
                        Product::lockForUpdate()
                            ->find(
                                $data['product_id']
                            );

                    if (!$product) {
                        throw new \Exception(
                            'Product not found.'
                        );
                    }
                }

                /**
                 * -----------------------------------------
                 * Find variant
                 * -----------------------------------------
                 */
                if (!empty(
                    $data['product_variant_id']
                )) {

                    $variant =
                        ProductVariant::lockForUpdate()
                            ->find(
                                $data[
                                    'product_variant_id'
                                ]
                            );

                    if (!$variant) {
                        throw new \Exception(
                            'Product variant not found.'
                        );
                    }

                    /**
                     * Variant must belong to
                     * selected product.
                     */
                    if (
                        $product &&
                        $variant->product_id
                            != $product->id
                    ) {
                        throw new \Exception(
                            'The selected variant does not belong to the selected product.'
                        );
                    }

                    /**
                     * If only variant is provided,
                     * automatically use its product.
                     */
                    if (!$product) {

                        $product =
                            Product::lockForUpdate()
                                ->find(
                                    $variant->product_id
                                );

                        if (!$product) {
                            throw new \Exception(
                                'Variant product not found.'
                            );
                        }
                    }
                }

                /**
                 * At least product or variant
                 * should be provided.
                 */
                if (!$product && !$variant) {
                    throw new \Exception(
                        'Product or product variant is required.'
                    );
                }

                /**
                 * -----------------------------------------
                 * Validate stock for OUT
                 * -----------------------------------------
                 */
                if (
                    $data['type'] === 'out'
                ) {

                    if (
                        $variant &&
                        $variant->stock
                            < $data['quantity']
                    ) {
                        throw new \Exception(
                            'Insufficient variant stock.'
                        );
                    }

                    /**
                     * If inventory belongs to
                     * variant, variant stock is
                     * the source of truth.
                     *
                     * Otherwise use product stock.
                     */
                    if (
                        !$variant &&
                        $product->stock
                            < $data['quantity']
                    ) {
                        throw new \Exception(
                            'Insufficient product stock.'
                        );
                    }
                }

                /**
                 * -----------------------------------------
                 * Create inventory record
                 * -----------------------------------------
                 */
                $inventory =
                    Inventory::create([
                        'product_id' =>
                            $product?->id,

                        'product_variant_id' =>
                            $variant?->id,

                        'quantity' =>
                            $data['quantity'],

                        'type' =>
                            $data['type'],

                        'note' =>
                            $data['note'] ?? null,
                    ]);

                /**
                 * -----------------------------------------
                 * Update stock
                 * -----------------------------------------
                 */
                if ($variant) {

                    $oldStock =
                        (int) $variant->stock;

                    if (
                        $data['type'] === 'in'
                    ) {
                        $variant->increment(
                            'stock',
                            $data['quantity']
                        );
                    } else {
                        $variant->decrement(
                            'stock',
                            $data['quantity']
                        );
                    }

                    $variant->refresh();

                    /**
                     * Notify when variant
                     * reaches zero.
                     *
                     * The notification is sent
                     * to the product owner.
                     */
                    if (
                        $oldStock > 0 &&
                        (int) $variant->stock === 0
                    ) {
                        $this->notifyProductOutOfStock(
                            $product
                        );
                    }

                } else {

                    $oldStock =
                        (int) $product->stock;

                    if (
                        $data['type'] === 'in'
                    ) {
                        $product->increment(
                            'stock',
                            $data['quantity']
                        );
                    } else {
                        $product->decrement(
                            'stock',
                            $data['quantity']
                        );
                    }

                    $product->refresh();

                    /**
                     * Notify when product
                     * reaches zero.
                     */
                    if (
                        $oldStock > 0 &&
                        (int) $product->stock === 0
                    ) {
                        $this->notifyProductOutOfStock(
                            $product
                        );
                    }
                }

                return $inventory->load([
                    'product',
                    'productVariant',
                ]);
            }
        );
    }

    /**
     * =====================================================
     * CREATE SELLER INVENTORY
     * =====================================================
     */
    public function createSellerInventory(
        array $data,
        $vendorId
    ) {
        return DB::transaction(
            function () use (
                $data,
                $vendorId
            ) {

                /**
                 * -----------------------------------------
                 * Product must belong to seller
                 * -----------------------------------------
                 */
                $product =
                    Product::where(
                        'vendor_id',
                        $vendorId
                    )
                        ->lockForUpdate()
                        ->find(
                            $data['product_id']
                        );

                if (!$product) {
                    throw new \Exception(
                        'Product not found or does not belong to this seller.'
                    );
                }

                $variant = null;

                /**
                 * -----------------------------------------
                 * Variant
                 * -----------------------------------------
                 */
                if (!empty(
                    $data['product_variant_id']
                )) {

                    $variant =
                        ProductVariant::where(
                            'product_id',
                            $product->id
                        )
                            ->lockForUpdate()
                            ->find(
                                $data[
                                    'product_variant_id'
                                ]
                            );

                    if (!$variant) {
                        throw new \Exception(
                            'Product variant not found or does not belong to this product.'
                        );
                    }
                }

                /**
                 * -----------------------------------------
                 * Check stock
                 * -----------------------------------------
                 */
                if (
                    $data['type'] === 'out'
                ) {

                    if ($variant) {

                        if (
                            $variant->stock
                                < $data['quantity']
                        ) {
                            throw new \Exception(
                                'Insufficient variant stock.'
                            );
                        }

                    } else {

                        if (
                            $product->stock
                                < $data['quantity']
                        ) {
                            throw new \Exception(
                                'Insufficient product stock.'
                            );
                        }
                    }
                }

                /**
                 * -----------------------------------------
                 * Create inventory
                 * -----------------------------------------
                 */
                $inventory =
                    Inventory::create([
                        'product_id' =>
                            $product->id,

                        'product_variant_id' =>
                            $variant?->id,

                        'quantity' =>
                            $data['quantity'],

                        'type' =>
                            $data['type'],

                        'note' =>
                            $data['note'] ?? null,
                    ]);

                /**
                 * -----------------------------------------
                 * Update variant stock
                 * -----------------------------------------
                 */
                if ($variant) {

                    $oldStock =
                        (int) $variant->stock;

                    if (
                        $data['type'] === 'in'
                    ) {

                        $variant->increment(
                            'stock',
                            $data['quantity']
                        );

                    } else {

                        $variant->decrement(
                            'stock',
                            $data['quantity']
                        );
                    }

                    $variant->refresh();

                    if (
                        $oldStock > 0 &&
                        (int) $variant->stock === 0
                    ) {
                        $this->notifyProductOutOfStock(
                            $product
                        );
                    }

                } else {

                    /**
                     * -------------------------------------
                     * Update product stock
                     * -------------------------------------
                     */
                    $oldStock =
                        (int) $product->stock;

                    if (
                        $data['type'] === 'in'
                    ) {

                        $product->increment(
                            'stock',
                            $data['quantity']
                        );

                    } else {

                        $product->decrement(
                            'stock',
                            $data['quantity']
                        );
                    }

                    $product->refresh();

                    if (
                        $oldStock > 0 &&
                        (int) $product->stock === 0
                    ) {
                        $this->notifyProductOutOfStock(
                            $product
                        );
                    }
                }

                return $inventory->load([
                    'product',
                    'productVariant',
                ]);
            }
        );
    }

    /**
     * =====================================================
     * UPDATE INVENTORY
     * =====================================================
     *
     * Important:
     *
     * Existing inventory:
     *
     * OUT 10
     *
     * changed to
     *
     * OUT 20
     *
     * means another 10 must be removed
     * from stock.
     */
    public function updateInventory(
        Inventory $inventory,
        array $data
    ) {
        return DB::transaction(
            function () use (
                $inventory,
                $data
            ) {

                $inventory =
                    Inventory::lockForUpdate()
                        ->find(
                            $inventory->id
                        );

                if (!$inventory) {
                    throw new \Exception(
                        'Inventory not found.'
                    );
                }

                $oldQuantity =
                    (int) $inventory->quantity;

                $oldType =
                    $inventory->type;

                $newQuantity =
                    array_key_exists(
                        'quantity',
                        $data
                    )
                        ? (int) $data['quantity']
                        : $oldQuantity;

                $newType =
                    $data['type']
                    ?? $oldType;

                /**
                 * Get product.
                 */
                $product = null;

                if ($inventory->product_id) {

                    $product =
                        Product::lockForUpdate()
                            ->find(
                                $inventory->product_id
                            );
                }

                /**
                 * Get variant.
                 */
                $variant = null;

                if (
                    $inventory
                        ->product_variant_id
                ) {

                    $variant =
                        ProductVariant::lockForUpdate()
                            ->find(
                                $inventory
                                    ->product_variant_id
                            );
                }

                if (!$product && !$variant) {
                    throw new \Exception(
                        'Inventory product not found.'
                    );
                }

                /**
                 * -----------------------------------------
                 * Reverse old stock movement
                 * -----------------------------------------
                 */
                $oldMovement =
                    $oldType === 'in'
                        ? $oldQuantity
                        : -$oldQuantity;

                /**
                 * -----------------------------------------
                 * Apply reverse movement
                 * -----------------------------------------
                 */
                if ($variant) {

                    $currentStock =
                        (int) $variant->stock;

                    $reversedStock =
                        $currentStock
                        - $oldMovement;

                    if ($reversedStock < 0) {
                        throw new \Exception(
                            'Cannot update inventory because the resulting stock would be negative.'
                        );
                    }

                    $variant->stock =
                        $reversedStock;

                    $variant->save();

                } else {

                    $currentStock =
                        (int) $product->stock;

                    $reversedStock =
                        $currentStock
                        - $oldMovement;

                    if ($reversedStock < 0) {
                        throw new \Exception(
                            'Cannot update inventory because the resulting stock would be negative.'
                        );
                    }

                    $product->stock =
                        $reversedStock;

                    $product->save();
                }

                /**
                 * -----------------------------------------
                 * Check new OUT movement
                 * -----------------------------------------
                 */
                if ($newType === 'out') {

                    $availableStock =
                        $variant
                            ? (int) $variant->stock
                            : (int) $product->stock;

                    if (
                        $availableStock
                            < $newQuantity
                    ) {
                        throw new \Exception(
                            'Insufficient stock for the updated inventory record.'
                        );
                    }
                }

                /**
                 * -----------------------------------------
                 * Apply new movement
                 * -----------------------------------------
                 */
                $newMovement =
                    $newType === 'in'
                        ? $newQuantity
                        : -$newQuantity;

                if ($variant) {

                    $beforeNewMovement =
                        (int) $variant->stock;

                    $newStock =
                        $beforeNewMovement
                        + $newMovement;

                    if ($newStock < 0) {
                        throw new \Exception(
                            'Stock cannot be negative.'
                        );
                    }

                    $variant->stock =
                        $newStock;

                    $variant->save();

                    /**
                     * Notify if product reaches zero.
                     */
                    if (
                        $beforeNewMovement > 0 &&
                        $newStock === 0
                    ) {
                        $this->notifyProductOutOfStock(
                            $product
                        );
                    }

                } else {

                    $beforeNewMovement =
                        (int) $product->stock;

                    $newStock =
                        $beforeNewMovement
                        + $newMovement;

                    if ($newStock < 0) {
                        throw new \Exception(
                            'Stock cannot be negative.'
                        );
                    }

                    $product->stock =
                        $newStock;

                    $product->save();

                    if (
                        $beforeNewMovement > 0 &&
                        $newStock === 0
                    ) {
                        $this->notifyProductOutOfStock(
                            $product
                        );
                    }
                }

                /**
                 * -----------------------------------------
                 * Update inventory record
                 * -----------------------------------------
                 */
                $inventory->update([
                    'quantity' =>
                        $newQuantity,

                    'type' =>
                        $newType,

                    'note' =>
                        $data['note']
                            ?? $inventory->note,
                ]);

                return $inventory
                    ->fresh()
                    ->load([
                        'product',
                        'productVariant',
                    ]);
            }
        );
    }

    /**
     * =====================================================
     * DELETE INVENTORY
     * =====================================================
     *
     * Deleting a movement must reverse
     * its stock effect.
     */
    public function deleteInventory(
        Inventory $inventory
    ) {
        return DB::transaction(
            function () use ($inventory) {

                $inventory =
                    Inventory::lockForUpdate()
                        ->find(
                            $inventory->id
                        );

                if (!$inventory) {
                    throw new \Exception(
                        'Inventory not found.'
                    );
                }

                $quantity =
                    (int) $inventory->quantity;

                $type =
                    $inventory->type;

                /**
                 * -----------------------------------------
                 * Find product
                 * -----------------------------------------
                 */
                $product = null;

                if ($inventory->product_id) {

                    $product =
                        Product::lockForUpdate()
                            ->find(
                                $inventory->product_id
                            );
                }

                /**
                 * -----------------------------------------
                 * Find variant
                 * -----------------------------------------
                 */
                $variant = null;

                if (
                    $inventory
                        ->product_variant_id
                ) {

                    $variant =
                        ProductVariant::lockForUpdate()
                            ->find(
                                $inventory
                                    ->product_variant_id
                            );
                }

                /**
                 * -----------------------------------------
                 * Reverse movement
                 * -----------------------------------------
                 */
                if ($variant) {

                    $currentStock =
                        (int) $variant->stock;

                    /**
                     * If original movement was IN,
                     * deleting it means stock goes DOWN.
                     */
                    if ($type === 'in') {

                        $newStock =
                            $currentStock
                            - $quantity;

                        if ($newStock < 0) {
                            throw new \Exception(
                                'Cannot delete this inventory record because stock would become negative.'
                            );
                        }

                        $variant->stock =
                            $newStock;

                    } else {

                        /**
                         * Original movement was OUT,
                         * so deleting it adds stock back.
                         */
                        $variant->stock =
                            $currentStock
                            + $quantity;
                    }

                    $variant->save();

                } elseif ($product) {

                    $currentStock =
                        (int) $product->stock;

                    if ($type === 'in') {

                        $newStock =
                            $currentStock
                            - $quantity;

                        if ($newStock < 0) {
                            throw new \Exception(
                                'Cannot delete this inventory record because stock would become negative.'
                            );
                        }

                        $product->stock =
                            $newStock;

                    } else {

                        $product->stock =
                            $currentStock
                            + $quantity;
                    }

                    $product->save();

                } else {

                    throw new \Exception(
                        'Inventory product no longer exists.'
                    );
                }

                /**
                 * Delete movement.
                 */
                $inventory->delete();

                return true;
            }
        );
    }

    /**
     * =====================================================
     * OUT OF STOCK NOTIFICATION
     * =====================================================
     */
    protected function notifyProductOutOfStock(
        ?Product $product
    ): void {

        if (!$product) {
            return;
        }

        /**
         * Product must have a vendor.
         */
        if (!$product->vendor_id) {
            return;
        }

        /**
         * Load vendor.
         */
        $product->loadMissing('vendor');

        $vendor = $product->vendor;

        if (!$vendor) {
            return;
        }

        /**
         * Depending on your Vendor model,
         * you may need:
         *
         * $vendor->user
         *
         * if Vendor belongsTo User.
         */
        $vendor->loadMissing('user');

        if (
            $vendor->user
        ) {
            $vendor->user->notify(
                new ProductOutOfStockNotification(
                    $product
                )
            );
        }
    }
}