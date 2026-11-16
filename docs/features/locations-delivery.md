# Delivery areas, branches and addresses

## Overview

Defines where the store delivers (governorates → areas, each with a delivery fee and a serving branch), which branches exist (and their POS URL), and the customer's saved address book.

**Status:** live · **Plugin:** `locations-delivery` ([plugin.json](../../app/Plugins/LocationsDelivery/plugin.json)) · **Depends on:** store-settings · **Related:** [Checkout](checkout-and-orders.md) (delivery fee pipe), [Coupons](coupons.md) (location conditions), [POS integration](pos-integration.md) (branch link)

## How to use

### Store admin

Under **التوصيل والمناطق** (Delivery & areas):

1. **الفروع** (Branches): name, **link** (POS base URL, used by [POS integration](pos-integration.md)), active flag. With POS integration active the form also shows the POS API key (`pos_api_key`, contributed through `admin.form.locations-delivery.branch`).
2. **المحافظات** (Governorates): English/Arabic name, order, active flag.
3. **المناطق** (Areas): English/Arabic name, governorate, serving branch, **shipping cost** (delivery fee), order, active flag.
4. **العناوين** (Addresses, in the Locations group): view customer addresses.

### Customer

1. At checkout for delivery, select a saved address or add one: area, phone, street, building, floor, apartment, notes. The delivery fee for that area is added to the total.
2. Manage addresses from the profile page (slot `profile.addresses`).
3. Guests type the address and pick an area at checkout ([Guest checkout](guest-checkout.md)).

### Rules customers notice

- Only active areas in active governorates are offered.
- Delivery fee = the area's shipping cost; takeaway orders have none; a free-shipping coupon can remove it.

## How it works

1. **Entry:** [`LocationsDeliveryApiController`](../../app/Plugins/LocationsDelivery/src/Http/Controllers/Api/LocationsDeliveryApiController.php): public `index`, `governorates`, `branches`; `auth` address CRUD validated by [`AddressRequest`](../../app/Plugins/LocationsDelivery/src/Http/Requests/AddressRequest.php) and authorized by [`AddressPolicy`](../../app/Plugins/LocationsDelivery/src/Policies/AddressPolicy.php) (owner only).
2. **Contracts for other plugins:** [`DeliveryQuote`](../../app/Plugins/LocationsDelivery/src/Contracts/DeliveryQuote.php) (`feeForArea`, `feeForAddress`, implemented by [`EloquentDeliveryQuote`](../../app/Plugins/LocationsDelivery/src/Services/EloquentDeliveryQuote.php)) and [`AddressBook`](../../app/Plugins/LocationsDelivery/src/Contracts/AddressBook.php) (address snapshots and area labels for orders, [`EloquentAddressBook`](../../app/Plugins/LocationsDelivery/src/Services/EloquentAddressBook.php)).
3. **Checkout:** `PlaceOrderRequest` requires the address to belong to the user and lie in an active area; [`CalculateDeliveryFeePipe`](../../app/Plugins/OrderManagement/src/Pipelines/Pipes/Pricing/CalculateDeliveryFeePipe.php) adds the fee; the order stores an address snapshot with area and governorate names.

### Data

`governorates`, `areas` (`shipping_cost`, `branch_id`), `branches` (`link`), `addresses`; see [Data model › Locations](../data-model.md#locations).

## Configuration

No settings beyond the records above. There is no per-area minimum order; the minimum order amount is a store-wide setting ([Checkout](checkout-and-orders.md#configuration)). [`KofeAreasSeeder`](../../database/seeders/KofeAreasSeeder.php) seeds a sample set of governorates and areas.

## Testing

- Automated: [LocationsDeliveryApiTest](../../tests/Feature/Api/LocationsDeliveryApiTest.php), [AreaUxTest](../../tests/Feature/Filament/Resources/AreaUxTest.php), [BranchResourceTest](../../tests/Feature/Filament/Resources/BranchResourceTest.php), [GovernorateUxTest](../../tests/Feature/Filament/Resources/GovernorateUxTest.php), [AddressBookTest](../../tests/Unit/Services/LocationsDelivery/AddressBookTest.php), [DeliveryQuoteTest](../../tests/Unit/Services/LocationsDelivery/DeliveryQuoteTest.php).
- Browser: [locations-delivery.live.spec.ts](../../tests/e2e/plugins/locations-delivery.live.spec.ts), [addresses.live.spec.ts](../../tests/e2e/plugins/auth/addresses.live.spec.ts).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Area missing at checkout | Area or its governorate inactive | Activate both |
| Address rejected at order | Area deactivated after the address was saved | Pick another address or reactivate |
| Wrong delivery fee | Area `shipping_cost` | Edit the area |
| "Please select a branch" | No active branch | Activate a branch |
| 403 editing an address | Not the owner | Expected |

## History

- 2026-02: Arabic names for governorates/areas, sample seeder.
- 2025-11: initial locations, branches and address book.
