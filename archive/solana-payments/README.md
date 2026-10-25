# Retired Solana payment prototype

This is historical source, not a capability of the presentation MVP.

- The old route definitions are retained in `routes.php` here but are not registered.
- The controller, `DemoOrder`, payment/RPC services, transaction parser and migration remain in their original paths for record preservation and reference. Presentation guards prevent controller actions, submissions, verification and RPC calls.
- The old `resources/js/demo-payment.js` and payment Blade partials are unreferenced. Vite builds only the connection and marketplace JavaScript.
- Historical payment tests are retained in `tests/Archive/`; they are outside the current test suites and are not the current application contract. No existing order is deleted by archiving these tests.
- The previous transaction SDK was `@solana/web3.js` 1.98.4 with a Jayson 5.0.0 override. Those dependencies are no longer needed by the running app and were removed. Historical tests/fixture generators require them if deliberately restored for future work.

Restoring payments would require an explicit future implementation/review, routes, dependencies, test updates and appropriate safeguards. An environment flag alone cannot enable payments. Never roll back or drop the `demo_orders` migration to disable this feature: existing records must be retained.

No Git repository was available in this workspace, so historical source is retained as files rather than being committed or deleted. No wallet setting, wallet authorization, private key or recovery phrase was changed.
