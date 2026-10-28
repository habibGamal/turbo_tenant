# Live-Only E2E Test Suite for Frontend React, Plugins & Multi-Theme Architecture

## Goal Description
Build a production-grade, high-speed End-to-End (E2E) test suite targeting all React storefront pages and all 13 decoupled plugins in **Live Mode Only**. 

All test scenarios execute pure end-to-end user journeys through the browser UI against the live Laravel multi-tenant backend (`http://kofe.localhost:8000`) and MySQL database—without mock data or API shortcuts.

The architecture enforces a strict **Theme Contract Specification** using standardized `data-testid` attributes documented in `docs/THEME_TEST_IDS_SPEC.md`. Any current theme (`default`) or future themes (`modern`, `minimal`, etc.) adhering to this specification will automatically be 100% verified by the exact same test suite.
