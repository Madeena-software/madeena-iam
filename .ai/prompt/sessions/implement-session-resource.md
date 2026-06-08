# Task Specification: Active Sessions Management Resource

## Goal
Implement a comprehensive active session management feature in the Filament Admin Panel (Standalone Sessions Resource + Relation Manager inside User Resource). This will allow administrators to globally inspect all logged-in device sessions and terminate them instantly.

---

## 📂 Target Files

### Standalone Resource
- `[NEW] app/Filament/Resources/SessionResource.php` - Standalone navigation menu resource.
- `[NEW] app/Filament/Resources/SessionResource/Pages/ListSessions.php` - Listing page for the global Sessions resource.

### User Relation Manager
- `[NEW] app/Filament/Resources/Users/RelationManagers/SessionsRelationManager.php` - Relation manager showing active sessions for a specific User.
- `[MODIFY] app/Filament/Resources/Users/UserResource.php` - Register the `SessionsRelationManager` in `getRelations()`.

### Models
- `[MODIFY] app/Models/User.php` - Add the `sessions()` HasMany relationship.

---

## 🛠️ Detailed Specifications

### 1. Standalone SessionResource
- **Model**: `App\Models\Session`
- **Navigation Icon**: `Heroicon\Outlined\CpuChip` or `Heroicon\Outlined\ComputerDesktop`
- **Navigation Group**: `System` or same navigation hierarchy.
- **Access Policies**:
  - `canCreate() => false` (No manual session creation).
  - `canEdit() => false` (No manual session edits).
  - `canDelete() => true` (Allows session termination).
  - `canDeleteAny() => true` (Allows bulk session termination).
- **Table Schema Columns**:
  - **User**: `TextColumn` linked to `user.name` relationship, searchable and sortable.
  - **IP Address**: `TextColumn` for `ip_address`, searchable and sortable.
  - **Device**: `TextColumn` for `device_details.description` (resolves custom accessor from `Session` model). Note: Do NOT make this sortable as it is a custom accessor, which will cause SQL errors.
  - **Last Activity**: `TextColumn` for `last_activity` formatted from UNIX timestamp (e.g., `formatStateUsing(fn ($state) => \Carbon\Carbon::createFromTimestamp($state)->diffForHumans())`), sortable.
- **Table Actions**:
  - `DeleteAction` (Terminates/revokes the session).
  - `DeleteBulkAction` (Allows bulk session revocation).

### 2. SessionsRelationManager (for User Detail Page)
- **Relationship**: `sessions` (User has many Sessions).
- **Table Columns**:
  - **IP Address**
  - **Device**
  - **Last Activity**
- **Table Actions**:
  - `DeleteAction` (Deletes the session record. Do NOT use `DetachAction` since this is a `HasMany` relationship).
  - `DeleteBulkAction`.

---

## 🧪 Verification & Testing Plan

*(Must follow `@[.ai/rules/testing-pyramid.md]`)*

### 1. Unit Tests (`tests/Unit`)
- Test the custom accessor `getDeviceDetailsAttribute` in the `Session` model.
- Ensure it correctly parses various user-agent strings.

### 2. Feature Tests (`tests/Feature`)
- Test the `SessionResource` authorization policies.
- Test deleting a session via the Standalone Resource `DeleteAction` and `DeleteBulkAction`.
- Test deleting a session via the `SessionsRelationManager`.
- Verify the session record is correctly deleted from the database.

### 3. E2E Verification (Playwright)
Create/update E2E spec to:
1. Log in as admin.
2. Navigate to the global **Sessions** resource page.
3. Assert that active session details (IP address, device description, last activity) are rendered.
4. Go to **Users** -> Edit user page, and verify the **Sessions** relation manager renders the user's active session.
5. Perform deletion on a session and verify it is removed.

---

## 📝 State & Documentation Updates
- Watch and update the `@[.ai]` tracking files (e.g., history/state logs) to document the creation and testing of the Sessions Resource.
