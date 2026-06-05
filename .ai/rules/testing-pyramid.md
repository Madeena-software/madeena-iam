# Testing Strategy — The Test Pyramid

> **CRITICAL**: All code modifications and new features must be accompanied by automated tests following the Test Pyramid paradigm.

---

## The Pyramid Distribution

For this Laravel 13 project, the AI must enforce the following distribution of tests:

### 1. 🔺 Top: E2E / UI Tests (10%)
- **Target**: Critical user journeys in the Filament Admin panel or public frontend.
- **Tools**: Laravel Dusk (if configured) or robust Filament/Livewire testing assertions.
- **Rule**: Only write these for the most critical paths (e.g., "Admin can log in and publish a hero banner", "User can successfully submit the Inabuyer feedback form").

### 2. 🟨 Middle: Feature / Integration Tests (30%)
- **Target**: HTTP endpoints, database interactions, external service integrations (e.g., MinIO S3).
- **Tools**: PHPUnit `Feature` test suite.
- **Rule**: 
  - Always use the `RefreshDatabase` trait.
  - Test HTTP status codes, JSON responses, and database state changes.
  - Mock external APIs or use Laravel's built-in fakes (e.g., `Storage::fake('public')`, `Queue::fake()`).

### 3. 🟩 Bottom: Unit Tests (60%)
- **Target**: Isolated business logic, custom calculations, data transformations, and model methods.
- **Tools**: PHPUnit `Unit` test suite.
- **Rule**: 
  - Execution must be blazingly fast.
  - **DO NOT** hit the database. Do not boot the full framework unless absolutely necessary.
  - Test custom Artisan commands (like `CheckStorageHealth`) by mocking dependencies.

---

## AI Agent Directives

When writing code or fixing bugs, the AI must follow these behaviors:

1. **Test-Driven Bug Fixes**: If asked to fix a bug, the AI must *first* write a failing test that reproduces the bug, then apply the fix, and ensure the test passes.
2. **Bottom-Up Development**: When generating a new feature, write the Unit tests for the core logic first, then the Feature tests for the controller/Livewire component.
3. **Pest/PHPUnit Syntax**: Use standard PHPUnit 11.x attributes (e.g., `#[Test]`) and strict typing.
4. **Naming Convention**: Test methods must be descriptive. Use `it_does_something_expected` or `test_it_does_something_expected` consistently.