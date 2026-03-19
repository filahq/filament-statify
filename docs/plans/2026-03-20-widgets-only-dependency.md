# Widgets-Only Dependency Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor `statify` so its hard Filament dependency is `filament/widgets` instead of `filament/filament`, while preserving `StatifyPlugin` as an optional integration documented for full Filament installs.

**Architecture:** The change is primarily a package-contract refactor: Composer metadata becomes narrower, README language becomes explicit about optional plugin support, and the plugin class remains available but is no longer implied to be part of the minimal dependency path. Verification combines a failing metadata test with Composer validation and dry-run resolution.

**Tech Stack:** PHP, Pest, Composer, Laravel, Filament

---

### Task 1: Add a failing metadata test

**Files:**
- Create: `/Volumes/DevDisk/code/filament5/packages/statify/tests/Unit/ComposerMetadataTest.php`

**Step 1: Write the failing test**

Add a unit test that reads `composer.json` and asserts:

- `require.filament/widgets` exists
- `require.filament/filament` does not exist
- `suggest.filament/filament` explains the optional plugin integration

**Step 2: Run the test to verify it fails**

Run from `/Volumes/DevDisk/code/filament5`:

```bash
php artisan test --compact --filter=ComposerMetadata
```

Expected: FAIL because the current package still requires `filament/filament`.

### Task 2: Narrow the package contract

**Files:**
- Modify: `/Volumes/DevDisk/code/filament5/packages/statify/composer.json`
- Modify: `/Volumes/DevDisk/code/filament5/packages/statify/README.md`

**Step 1: Update Composer metadata**

Replace the hard Filament dependency with `filament/widgets` and add a `suggest` entry for `filament/filament` describing `StatifyPlugin` as optional.

**Step 2: Update README**

Explain that basic usage depends on Filament widgets, and panel plugin usage requires the full Filament package.

### Task 3: Verify the change

**Files:**
- Verify only

**Step 1: Re-run the targeted test**

```bash
cd /Volumes/DevDisk/code/filament5 && php artisan test --compact --filter=ComposerMetadata
```

Expected: PASS.

**Step 2: Re-run Composer validation and dry-run resolution**

```bash
cd /Volumes/DevDisk/code/filament5/packages/statify && composer validate --strict && composer install --dry-run
```

Expected: valid manifest and resolvable dependency graph.
