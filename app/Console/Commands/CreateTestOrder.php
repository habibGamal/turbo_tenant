<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ExtraOption;
use App\Models\ExtraOptionItem;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemExtra;
use App\Models\Product;
use App\Models\ProductPosMapping;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WeightOption;
use App\Models\WeightOptionValue;
use App\Services\OrderPOSService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTestOrder extends Command
{
    protected $signature = 'app:create-test-order';

    protected $description = 'Create a complete test order with items, extras, variants, and weight options';

    public function handle(): int
    {
        $this->info('Creating test order...');

        DB::beginTransaction();

        try {
            // Create or get branch
            $branch = $this->createOrGetBranch();
            $this->info("✓ Branch: {$branch->name}");

            // Create or get user
            $user = $this->createOrGetUser();
            $this->info("✓ User: {$user->name}");

            // Create address
            $address = $this->createAddress($user);
            $this->info('✓ Address created');

            // Create category
            $category = $this->createCategory();
            $this->info("✓ Category: {$category->name}");

            // Create extra options
            $extraOption = $this->createExtraOptions();
            $this->info('✓ Extra options created');

            // Create weight options
            $weightOption = $this->createWeightOptions();
            $this->info('✓ Weight options created');

            // Create products with variants
            $products = $this->createProducts($category, $extraOption, $weightOption);
            $this->info('✓ Products created: '.count($products));

            // Create order
            $order = $this->createOrder($user, $branch, $address);
            $this->info("✓ Order created: {$order->order_number}");

            // Create order items with extras
            $this->createOrderItems($order, $products, $extraOption);
            $this->info('✓ Order items created');

            DB::commit();

            $this->newLine();
            $this->info('🎉 Test order created successfully!');
            $this->newLine();
            $this->table(
                ['Field', 'Value'],
                [
                    ['Order Number', $order->order_number],
                    ['Order ID', $order->id],
                    ['User', $user->name],
                    ['Branch', $branch->name],
                    ['Type', $order->type],
                    ['Status', $order->status],
                    ['Payment Status', $order->payment_status->value],
                    ['Payment Method', $order->payment_method->value],
                    ['Sub Total', number_format($order->sub_total, 2).' EGP'],
                    ['Tax', number_format($order->tax, 2).' EGP'],
                    ['Service', number_format($order->service, 2).' EGP'],
                    ['Delivery Fee', number_format($order->delivery_fee, 2).' EGP'],
                    ['Total', number_format($order->total, 2).' EGP'],
                    ['Items Count', $order->items()->count()],
                ]
            );
            app(OrderPOSService::class)->placeOrder($order);

            return self::SUCCESS;
        } catch (Exception $e) {
            DB::rollBack();
            $this->error('Failed to create test order: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function createOrGetBranch(): Branch
    {
        return Branch::query()->firstOrCreate(
            ['link' => 'http://localhost:8000'],
            [
                'name' => 'Test Branch',
                'is_active' => true,
            ]
        );
    }

    private function createOrGetUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt(value: 'password'),
            ]
        );
    }

    private function createAddress(User $user): Address
    {
        // Create governorate
        $governorate = Governorate::query()->firstOrCreate(
            ['name' => 'Cairo'],
            [
                'name' => 'Cairo',
                'name_ar' => 'القاهرة',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // Create area
        $area = Area::query()->firstOrCreate(
            [
                'name' => 'Nasr City',
                'governorate_id' => $governorate->id,
            ],
            [
                'name' => 'Nasr City',
                'name_ar' => 'مدينة نصر',
                'governorate_id' => $governorate->id,
                'branch_id' => null,
                'shipping_cost' => 20.00,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // Create address
        return Address::query()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'street' => '123 Main Street',
            'phone_number' => fake()->phoneNumber(),
            'building' => '5',
            'apartment' => '10',
            'floor' => '2',
            'notes' => 'Test address notes',
        ]);
    }

    private function createCategory(): Category
    {
        return Category::query()->firstOrCreate(
            ['name' => 'Test Category'],
            [
                'name' => 'Test Category',
                'description' => 'A test category for orders',
                'image' => 'test-category.jpg',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    private function createExtraOptions(): ExtraOption
    {
        $extraOption = ExtraOption::query()->firstOrCreate(
            ['name' => 'Toppings'],
            [
                'name' => 'Toppings',
                'allow_multiple' => true,
                'min_selections' => 0,
                'max_selections' => 10,
                'is_active' => true,
            ]
        );

        // Create extra option items
        $items = [
            ['name' => 'Extra Cheese', 'price' => 15.00],
            ['name' => 'Mushrooms', 'price' => 10.00],
            ['name' => 'Olives', 'price' => 8.00],
            ['name' => 'Pepperoni', 'price' => 20.00],
        ];

        foreach ($items as $index => $item) {
            $extraItem = ExtraOptionItem::query()->firstOrCreate(
                [
                    'extra_option_id' => $extraOption->id,
                    'name' => $item['name'],
                ],
                [
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'extra_option_id' => $extraOption->id,
                    'is_default' => false,
                    'sort_order' => $index + 1,
                    'allow_quantity' => true,
                    'pos_mapping_type' => 'pos_item',
                ]
            );

            // Create POS mapping for extra option item
            ProductPosMapping::query()->firstOrCreate(
                [
                    'extra_option_item_id' => $extraItem->id,
                ],
                [
                    'extra_option_item_id' => $extraItem->id,
                    'pos_item_id' => 'P000012',
                ]
            );
        }

        return $extraOption;
    }

    private function createWeightOptions(): WeightOption
    {
        $weightOption = WeightOption::query()->firstOrCreate(
            ['name' => 'Weight'],
            [
                'name' => 'Weight',
                'unit' => 'kg',
            ]
        );

        // Create weight option values
        $values = [
            ['value' => 0.25, 'label' => '250g'],
            ['value' => 0.5, 'label' => '500g'],
            ['value' => 1.0, 'label' => '1kg'],
            ['value' => 2.0, 'label' => '2kg'],
        ];

        foreach ($values as $index => $value) {
            WeightOptionValue::query()->firstOrCreate(
                [
                    'weight_option_id' => $weightOption->id,
                    'value' => $value['value'],
                ],
                [
                    'weight_option_id' => $weightOption->id,
                    'value' => $value['value'],
                    'label' => $value['label'],
                    'sort_order' => $index + 1,
                ]
            );
        }

        return $weightOption;
    }

    /**
     * @return array<Product>
     */
    private function createProducts(Category $category, ExtraOption $extraOption, WeightOption $weightOption): array
    {
        $products = [];

        // Product 1: Pizza with variants and extras (no weight)
        $pizza = Product::query()->create([
            'name' => 'Margherita Pizza',
            'description' => 'Classic Italian pizza with tomato sauce and mozzarella',
            'image' => 'pizza.jpg',
            'base_price' => 120.00,
            'price_after_discount' => 100.00,
            'category_id' => $category->id,
            'extra_option_id' => $extraOption->id,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        // Create POS mapping for pizza
        ProductPosMapping::query()->create([
            'product_id' => $pizza->id,
            'pos_item_id' => 'P000003',
        ]);

        // Create variants for pizza
        $smallVariant = ProductVariant::query()->create([
            'product_id' => $pizza->id,
            'name' => 'Small',
            'price' => 100.00,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        // Create POS mapping for small variant
        ProductPosMapping::query()->create([
            'product_id' => $pizza->id,
            'variant_id' => $smallVariant->id,
            'pos_item_id' => 'P000004',
        ]);

        $largeVariant = ProductVariant::query()->create([
            'product_id' => $pizza->id,
            'name' => 'Large',
            'price' => 150.00,
            'is_available' => true,
            'sort_order' => 2,
        ]);

        // Create POS mapping for large variant
        ProductPosMapping::query()->create([
            'product_id' => $pizza->id,
            'variant_id' => $largeVariant->id,
            'pos_item_id' => 'P000005',
        ]);

        $products[] = $pizza;

        // Product 2: Burger with extras (no weight, no variants)
        $burger = Product::query()->create([
            'name' => 'Beef Burger',
            'description' => 'Delicious beef burger with fresh vegetables',
            'image' => 'burger.jpg',
            'base_price' => 80.00,
            'category_id' => $category->id,
            'extra_option_id' => $extraOption->id,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        // Create POS mapping for burger
        ProductPosMapping::query()->create([
            'product_id' => $burger->id,
            'pos_item_id' => 'P000006',
        ]);

        $products[] = $burger;

        // Product 3: Chicken by weight
        $chicken = Product::query()->create([
            'name' => 'Grilled Chicken',
            'description' => 'Fresh grilled chicken sold by weight',
            'image' => 'chicken.jpg',
            'base_price' => 150.00,
            'category_id' => $category->id,
            'weight_options_id' => $weightOption->id,
            'is_active' => true,
            'sell_by_weight' => true,
        ]);

        // Create POS mapping for chicken
        ProductPosMapping::query()->create([
            'product_id' => $chicken->id,
            'pos_item_id' => 'P000007',
        ]);

        $products[] = $chicken;

        return $products;
    }

    private function createOrder(User $user, Branch $branch, Address $address): Order
    {
        $orderNumber = 'ORD-'.mb_strtoupper(Str::random(8));

        return Order::query()->create([
            'order_number' => $orderNumber,
            'merchant_order_id' => 'MERCH-'.time(),
            'transaction_id' => 'TXN-'.Str::random(12),
            'shift_id' => 1,
            'status' => 'pending',
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::COD,
            'type' => 'web_delivery',
            'sub_total' => 0, // Will be calculated
            'tax' => 0,
            'service' => 0,
            'delivery_fee' => 20.00,
            'discount' => 0,
            'total' => 0,
            'note' => 'This is a test order created by the command',
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'address_id' => $address->id,
        ]);
    }

    /**
     * @param  array<Product>  $products
     */
    private function createOrderItems(Order $order, array $products, ExtraOption $extraOption): void
    {
        $subTotal = 0;

        // Item 1: Pizza with variant (Large) and extras
        $pizza = $products[0];
        $largeVariant = $pizza->variants()->where('name', 'Large')->first();

        $pizzaPrice = $largeVariant->price;
        $pizzaQuantity = 2;
        $pizzaTotal = $pizzaPrice * $pizzaQuantity;

        $orderItem1 = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $pizza->id,
            'variant_id' => $largeVariant->id,
            'product_name' => $pizza->name,
            'variant_name' => $largeVariant->name,
            'quantity' => $pizzaQuantity,
            'unit_price' => $pizzaPrice,
            'total' => $pizzaTotal,
            'notes' => 'Well done please',
        ]);

        // Add extras to pizza
        $extraItems = $extraOption->items()->whereIn('name', ['Extra Cheese', 'Mushrooms'])->get();
        foreach ($extraItems as $extraItem) {
            $extraQuantity = 1;
            OrderItemExtra::query()->create([
                'order_item_id' => $orderItem1->id,
                'extra_option_item_id' => $extraItem->id,
                'extra_name' => $extraItem->name,
                'extra_price' => $extraItem->price,
                'quantity' => $extraQuantity,
            ]);

            $pizzaTotal += $extraItem->price * $extraQuantity * $pizzaQuantity;
        }

        $orderItem1->update(['total' => $pizzaTotal]);
        $subTotal += $pizzaTotal;

        // Item 2: Burger with extras
        $burger = $products[1];
        $burgerPrice = $burger->base_price;
        $burgerQuantity = 3;
        $burgerTotal = $burgerPrice * $burgerQuantity;

        $orderItem2 = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $burger->id,
            'product_name' => $burger->name,
            'quantity' => $burgerQuantity,
            'unit_price' => $burgerPrice,
            'total' => $burgerTotal,
        ]);

        // Add extra to burger
        $pepperoni = $extraOption->items()->where('name', 'Pepperoni')->first();
        $pepperoniQuantity = 2;
        OrderItemExtra::query()->create([
            'order_item_id' => $orderItem2->id,
            'extra_option_item_id' => $pepperoni->id,
            'extra_name' => $pepperoni->name,
            'extra_price' => $pepperoni->price,
            'quantity' => $pepperoniQuantity,
        ]);

        $burgerTotal += $pepperoni->price * $pepperoniQuantity * $burgerQuantity;
        $orderItem2->update(['total' => $burgerTotal]);
        $subTotal += $burgerTotal;

        // Item 3: Chicken by weight
        $chicken = $products[2];
        $weightValue = $chicken->weightOption->values()->where('value', 1.0)->first(); // 1kg
        $weightMultiplier = 2; // 2 units of 1kg = 2kg total

        $chickenUnitPrice = $chicken->base_price;
        $chickenQuantity = $weightValue->value * $weightMultiplier; // Total kg
        $chickenTotal = $chickenUnitPrice * $chickenQuantity;

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $chicken->id,
            'weight_option_value_id' => $weightValue->id,
            'weight_multiplier' => $weightMultiplier,
            'product_name' => $chicken->name,
            'quantity' => $chickenQuantity,
            'unit_price' => $chickenUnitPrice,
            'total' => $chickenTotal,
            'notes' => 'Medium spice level',
        ]);

        $subTotal += $chickenTotal;

        // Calculate totals
        $tax = $subTotal * 0.14; // 14% tax
        $service = $subTotal * 0.12; // 12% service charge
        $total = $subTotal + $tax + $service + $order->delivery_fee;

        // Update order totals
        $order->update([
            'sub_total' => $subTotal,
            'tax' => $tax,
            'service' => $service,
            'total' => $total,
        ]);
    }
}
