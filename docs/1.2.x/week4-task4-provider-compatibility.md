# Week 4 Task 4: Third-Party Provider Compatibility

Date: 2026-04-13
Scope: `TKT-P1-W4-04`
Source files:

- `simula-friendly-slugs-for-arabic-sites.php`
- `docs/1.2.x/week4-execution-plan.md`

## Objective

Harden provider extension behavior so third-party providers added through the canonical filter can participate in admin rendering and settings save without requiring hard-coded plugin changes.

## Compatibility Changes Implemented

1. Provider runtime setup now instantiates only classes that both exist and implement `Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Interface`.
2. Provider definitions can declare `validation_key` per field.
3. Selected-provider validation now builds its payload from declared provider fields instead of assuming only `key` and `endpoint`.
4. When a selected provider has no runtime validator instance, declared fields are still sanitized and stored safely.
5. Invalid or incomplete provider definitions are ignored during normalization and do not break the settings screen.

## Supported Provider Definition Shape

```php
add_filter(
    'simula_friendly_slugs_for_arabic_sites_translation_providers',
    static function( $providers ) {
        $providers['mock_service'] = [
            'label' => 'Mock Service',
            'description' => 'Example external provider',
            'class' => 'My_Mock_Service_Provider',
            'fields' => [
                [
                    'type' => 'text',
                    'option_path' => [ 'provider_tokens', 'mock_service' ],
                    'validation_key' => 'token',
                    'label' => 'Mock Token',
                ],
                [
                    'type' => 'url',
                    'option_path' => [ 'provider_endpoint', 'mock_service' ],
                    'validation_key' => 'endpoint',
                    'label' => 'Mock Endpoint',
                ],
            ],
        ];

        return $providers;
    }
);
```

## Expected Behavior

1. The provider appears automatically in the Translation Service selector.
2. Its declared fields appear automatically in Provider Settings.
3. Saving settings for another provider does not validate or require the mock provider fields.
4. Saving settings with the mock provider selected passes its declared payload to `validate_settings()`.
5. If the class is missing or not a valid provider implementation, the admin UI remains usable and the selected provider fields still sanitize safely on save.

## Manual Verification Checklist

1. Register a mock provider through the filter with one `text` field and one `url` field.
2. Open the settings screen and confirm the mock provider appears in the selector.
3. Switch between `google` and the mock provider and confirm the Provider Settings panel changes immediately.
4. Save with `google` selected while the mock provider fields are empty and confirm no validation failure occurs.
5. Save with the mock provider selected and confirm submitted values persist in the declared option paths.
6. Change the mock provider class to a non-existent class name and confirm the settings screen still renders without a fatal error.
7. Change the mock provider definition to omit `label` or `class` and confirm it is ignored safely.

## Verification In This Workspace

1. `php -l simula-friendly-slugs-for-arabic-sites.php` passes after the compatibility changes.
2. No automated provider-extension tests exist in this repository, so Task 4 verification remains a manual QA artifact for a WordPress runtime.
