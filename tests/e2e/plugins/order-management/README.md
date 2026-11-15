# Paymob sandbox browser test

`19-paymob-sandbox.live.spec.ts` creates an order and submits a test card to
Paymob's hosted checkout. It is skipped unless `E2E_PAYMOB_SANDBOX=1` is set.
Use a dedicated test tenant with Paymob test credentials and a public HTTPS
address that Paymob can reach. The selected product must be available without
mandatory options. Test orders and sandbox payment events remain in that tenant.

Set these environment variables before running the test:

- `E2E_BASE_URL`: public HTTPS tenant URL.
- `E2E_PAYMOB_SANDBOX`: set to `1` to opt in.
- `E2E_PAYMOB_PRODUCT_ID`: ID of an available simple product.
- `E2E_PAYMOB_TEST_PHONE`: test guest phone number.
- `E2E_PAYMOB_TEST_CARD_NUMBER`, `E2E_PAYMOB_TEST_CARD_EXPIRY` (`MMYY`),
  `E2E_PAYMOB_TEST_CARD_CVV`, and `E2E_PAYMOB_TEST_CARDHOLDER`: Paymob test card values.

Run `npx playwright test tests/e2e/plugins/order-management/19-paymob-sandbox.live.spec.ts`.
The test verifies that the hosted checkout uses Paymob test keys before entering
card details. Traces, screenshots, and video are disabled for this test.
