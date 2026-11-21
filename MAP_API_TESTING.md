# Safe map API testing

The map endpoint opens its own PDO connection. For HTTP integration tests, use a disposable database instead of relying on a transaction in the test runner's separate connection.

1. Clone the schema to a database such as `blood_donate_test` and apply the required migrations there.
2. Set `BLIFE_DB_NAME=blood_donate_test` in the PHP web-server environment before starting the server. `config/database.php` uses this value when present and validates its characters; without it, the normal `blood_donate` database remains the default.
3. Run API fixtures and assertions only against that isolated database. Do not insert test fixtures into the shared development database.
4. If a test calls database logic using the same PDO connection, wrap fixture inserts and assertions in a transaction and always roll it back in a `finally` block.
5. Teardown the isolated database or its fixtures after HTTP tests, then confirm no `map-api-test-` content remains.

The public request, donation-offer, and map queries exclude content containing the reserved `map-api-test-` marker as a second safeguard.
