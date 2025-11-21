# POS API Documentation

## Overview

This document describes the API endpoints that the POS (Point of Sale) system must implement to integrate with the Sraya website. The website acts as a client that sends orders and queries to the POS system.

## Base URL

The base URL for each branch is stored in the `branches` table under the `link` column. All endpoints should be accessed via HTTPS.

## Security Configuration

- The system uses a custom HTTPS agent with `rejectUnauthorized: false` to handle self-signed certificates
- All requests should be made over HTTPS

---

## Endpoints

### 1. Get Shift ID

**Endpoint:** `GET /api/get-shift-id`

**Description:** Retrieves the current shift ID from the POS system.

**Request Parameters:** None

**Response:**
```json
{
  "shift_id": 123
}
```

**Response Fields:**
- `shift_id` (number): The current shift identifier in the POS system

**Error Handling:**
- If the endpoint fails, the website throws: "حدث خطاء اثناء الاستعلام عن رقم الوردية"

**Usage:**
- Called before placing an order to get the current shift
- Used by the job queue to update order shift information

---

### 2. Check Order Acceptance Status

**Endpoint:** `GET /api/can-accept-order`

**Description:** Checks whether the branch can currently accept new orders.

**Request Parameters:** None

**Response:**
```json
{
  "can_accept": true
}
```

**Response Fields:**
- `can_accept` (boolean): Whether the branch can accept orders at this time

**Error Handling:**
- If the endpoint fails, the website throws: "حدث خطاء اثناء الاستعلام عن قبول الطلبات"
- Error code: `branch_not_accepting_orders`

**Usage:**
- Can be used to check if a branch is available before allowing customers to place orders
- Currently bypassed when `IS_ALWAYS_ACCEPTING_ORDERS` is set to `true`

---

### 3. Place Web Order

**Endpoint:** `POST /api/web-orders/place-order`

**Description:** Submits a new order from the website to the POS system.

**Request Body:**
```json
{
  "user": {
    "name": "Ahmed Ali",
    "phone": "01234567890",
    "area": "Nasr City",
    "address": "15 Street Name, Building 10, Apartment 5"
  },
  "order": {
    "type": "web_delivery",
    "shiftId": 123,
    "orderNumber": "WEB-123-001",
    "subTotal": 100.00,
    "tax": 14.00,
    "service": 5.00,
    "discount": 10.00,
    "total": 109.00,
    "note": "Extra napkins please",
    "webPreferences": {
      "payment_method": "card",
      "transaction_id": "TXN-123456789"
    },
    "items": [
      {
        "quantity": 2,
        "notes": "No onions",
        "posRefObj": [
          {
            "productRef": "PROD-001",
            "quantity": 1
          },
          {
            "productRef": "ADDON-001",
            "quantity": 1
          }
        ]
      }
    ]
  }
}
```

**Request Body Structure:**

#### User Object
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Customer full name |
| phone | string | Yes | Customer phone number |
| area | string | Yes | Customer area/district |
| address | string | Yes | Full delivery address |

#### Order Object
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| type | string | Yes | Order type: `web_delivery` or `web_takeaway` |
| shiftId | number | Yes | Current shift ID from the POS system |
| orderNumber | string | Yes | Unique order identifier |
| subTotal | number | Yes | Order subtotal before tax and service |
| tax | number | Yes | Tax amount |
| service | number | Yes | Service charge amount |
| discount | number | Yes | Discount amount |
| total | number | Yes | Final total amount |
| note | string | No | Customer notes/instructions |
| webPreferences | object | No | Additional payment information |

#### Web Preferences Object (Optional)
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| payment_method | string | No | Payment method: `cod`, `card`, `wallet`, or `kiosk` |
| transaction_id | string | No | Paymob transaction ID for online payments |

#### Order Items Array
Each item in the array contains:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| quantity | number | Yes | Quantity of this item |
| notes | string | No | Special instructions for this item |
| posRefObj | array | Yes | Array of product references for this item |

#### POS Reference Object
Each object in posRefObj contains:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| productRef | string | Yes | Product reference ID in the POS system |
| quantity | number | Yes | Quantity of this specific product/component |

**Success Response:**
- HTTP Status: 200 OK
- The POS system should process the order and return success

**Error Responses:**

1. **Product Not Found:**
```json
{
  "message": "Product not found",
  "notFoundProducts": ["PROD-001", "PROD-002"]
}
```

When this error occurs, the website will:
- Query the master products repository to get product names
- Display error: "المنتجات التالية غير موجودة بهذا الفرع: [product names]"

2. **General Error:**
- Any other error will result in: "لا يمكن التواصل مع الفرع في الوقت الحالي"

**Retry Logic:**
- Orders are processed via a job queue with retry mechanism
- Retries: 100 attempts (approximately 25 hours total)
- Retry interval: 15 minutes between attempts (currently set to 1 second in development)
- Queue name: `pos_orders`

**Order Number Format:**
- Generated as: Shift ID + Branch-specific sequence
- Updated with actual shift ID before sending to POS

---

## Master Products Repository Endpoints

These endpoints are called on the master products repository system (not individual branches) to sync product data.

### Base URL
Retrieved from settings table with key: `products_repo_link`

---

### 4. Get Products by IDs

**Endpoint:** `GET /api/get-products-master?ids=,{id1},{id2},{id3}`

**Description:** Retrieves product details by their IDs from the master repository.

**Request Parameters:**
- `ids`: Comma-separated list of product IDs (note: starts with a comma)

**Response:**
```json
[
  {
    "id": 1,
    "name": "Product Name",
    "price": 50.00,
    "type": "manifactured",
    "productRef": "PROD-001",
    "category": {
      "id": 1,
      "name": "Category Name"
    }
  }
]
```

**Usage:**
- Import new products from master to local database
- Creates categories if they don't exist
- Creates products with default image and initial configuration

---

### 5. Get All Product References

**Endpoint:** `GET /api/all-products-refs-master`

**Description:** Retrieves all product references from the master repository.

**Request Parameters:** None

**Response:**
```json
[
  {
    "id": 1,
    "name": "Category Name",
    "products": [
      {
        "id": 1,
        "name": "Product Name",
        "productRef": "PROD-001",
        "type": "manifactured"
      }
    ]
  }
]
```

**Product Types:**
- `manifactured` (note: typo in code, should be "manufactured")
- `manufactured` (correct spelling also accepted)
- `consumable`

**Usage:**
- Finds new products that don't exist locally
- Filters by product type to only show relevant products
- Compares with local database to identify missing products

---

### 6. Get All Product Prices

**Endpoint:** `GET /api/all-products-prices-master`

**Description:** Retrieves all product prices from the master repository.

**Request Parameters:** None

**Response:**
```json
[
  {
    "id": 1,
    "name": "Category Name",
    "products": [
      {
        "id": 1,
        "name": "Product Name",
        "productRef": "PROD-001",
        "price": 50.00,
        "cost": 30.00,
        "type": "manifactured"
      }
    ]
  }
]
```

**Usage:**
- Identifies products with price changes
- Compares master prices with local database
- Returns only products where prices differ

---

### 7. Get Products Prices by IDs

**Endpoint:** `GET /api/get-products-prices-master?ids=,{id1},{id2},{id3}`

**Description:** Retrieves product prices by their IDs.

**Request Parameters:**
- `ids`: Comma-separated list of product IDs (note: starts with a comma)

**Response:**
```json
[
  {
    "id": 1,
    "name": "Product Name",
    "price": 50.00,
    "type": "manifactured",
    "productRef": "PROD-001",
    "category": {
      "id": 1,
      "name": "Category Name"
    }
  }
]
```

**Usage:**
- Update local product prices from master
- Matches products by `single_pos_ref` field
- Updates both `price` and `priceAfterDiscount` fields

---

### 8. Get Products by References

**Endpoint:** `GET /api/get-products-master-by-refs?refs=,{ref1},{ref2},{ref3}`

**Description:** Retrieves product details by their product references.

**Request Parameters:**
- `refs`: Comma-separated list of product references (note: starts with a comma)

**Response:**
```json
[
  {
    "id": 1,
    "name": "Product Name",
    "price": 50.00,
    "type": "manifactured",
    "productRef": "PROD-001",
    "category": {
      "id": 1,
      "name": "Category Name"
    }
  }
]
```

**Usage:**
- Used when POS returns "Product not found" error
- Retrieves product names to display in error messages
- Helps identify which products need to be synced

---

## Data Flow

### Order Placement Flow

1. Customer places order on website
2. Website validates order and creates Order record
3. If `IS_ALWAYS_ACCEPTING_ORDERS = true`:
   - Order is enqueued to job queue (`pos_orders`)
   - Job worker processes order asynchronously
   - Job fetches current shift ID from POS
   - Job updates order with shift ID and new order number
   - Job sends order to POS `/api/web-orders/place-order`
4. If `IS_ALWAYS_ACCEPTING_ORDERS = false`:
   - Order is sent immediately to POS
   - If product not found, queries master repository for product names
   - Returns error to user

### Product Sync Flow

1. **Import New Products:**
   - Query master for all product references
   - Compare with local database
   - Identify missing products
   - Import products with categories

2. **Update Prices:**
   - Query master for all product prices
   - Compare with local database prices
   - Identify products with price differences
   - Update local product prices

---

## Error Codes and Messages

| Error Type | Arabic Message | English Translation | Code |
|------------|----------------|---------------------|------|
| Shift Query Failed | حدث خطاء اثناء الاستعلام عن رقم الوردية | Error occurred while querying shift number | - |
| Branch Not Accepting | حدث خطاء اثناء الاستعلام عن قبول الطلبات | Error occurred while querying order acceptance | `branch_not_accepting_orders` |
| Products Not Found | المنتجات التالية غير موجودة بهذا الفرع: {names} | The following products are not found in this branch | - |
| Communication Error | لا يمكن التواصل مع الفرع في الوقت الحالي | Cannot communicate with branch at this time | - |
| Master Connection Failed | لا يمكن الاتصال بالنقطة الرئيسية | Cannot connect to master point | - |

---

## Implementation Notes

1. **HTTPS Configuration:**
   - All endpoints should support HTTPS
   - Self-signed certificates are accepted

2. **Shift Management:**
   - Shift ID must be current and valid
   - Orders are associated with shift IDs for tracking

3. **Product References:**
   - Products are identified by `productRef` field
   - Must match exactly between systems
   - Case-sensitive

4. **Order Numbers:**
   - Format: Generated from shift ID and branch sequence
   - Must be unique per branch
   - Updated before sending to POS

5. **Retry Mechanism:**
   - Orders retry automatically on failure
   - 100 retry attempts over ~25 hours
   - Logs all failures for debugging

6. **Payment Information:**
   - `webPreferences.payment_method`: Payment method used
   - `webPreferences.transaction_id`: Transaction ID from payment gateway
   - Only included for paid orders

7. **Order Types:**
   - `web_delivery`: Delivery order
   - `web_takeaway`: Takeaway/pickup order

8. **Product Types Handled:**
   - `manifactured` (typo, should be manufactured)
   - `manufactured` (correct spelling)
   - `consumable`

---

## Testing Checklist

- [ ] GET /api/get-shift-id returns valid shift ID
- [ ] GET /api/can-accept-order returns boolean response
- [ ] POST /api/web-orders/place-order accepts valid order
- [ ] POST /api/web-orders/place-order returns product not found error with correct format
- [ ] All endpoints handle HTTPS requests
- [ ] Shift IDs are correctly associated with orders
- [ ] Product references match between systems
- [ ] Order totals calculate correctly
- [ ] Payment information is properly received

---

## Contact

For issues or questions regarding POS API integration, please refer to the development team.
