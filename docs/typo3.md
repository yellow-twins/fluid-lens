# Using fluid-lens in a TYPO3 project

fluid-lens is a standalone tool and needs no TYPO3 instance to run, but it is
built for TYPO3's Fluid templates and fits naturally into a TYPO3 project.

## The standalone binary (recommended)

Because fluid-lens is a Composer package, its binary lands in `vendor/bin/` of
your project and works immediately — no extension setup, no configuration:

```bash
# Scan one extension's templates
vendor/bin/fluid-lens analyze packages/my_ext/Resources/Private

# Scan every extension at once
vendor/bin/fluid-lens analyze packages/

# Near-duplicates across the whole project
vendor/bin/fluid-lens similar packages/
```

Inside DDEV:

```bash
ddev exec "php vendor/bin/fluid-lens analyze packages/my_ext/Resources/Private"
```

### As a Composer script

Add a shortcut to your project's `composer.json`:

```json
{
    "scripts": {
        "lint:fluid": "fluid-lens analyze packages/",
        "lint:fluid-similar": "fluid-lens similar packages/"
    }
}
```

### In CI

```yaml
- name: Fluid duplication check
  run: vendor/bin/fluid-lens analyze packages/ --baseline=fluid-lens-baseline.json
```

The command exits non-zero when it finds duplication, so the job fails on new
duplication while the baseline keeps existing findings from blocking the build.

## Native TYPO3 commands (optional)

If you prefer to run the analysers through the TYPO3 binary, fluid-lens ships a
`Configuration/Commands.php` that registers them:

```bash
vendor/bin/typo3 fluidlens:analyze packages/my_ext/Resources/Private/Templates
vendor/bin/typo3 fluidlens:similar packages/my_ext/Resources/Private/Templates
```

These become available when the package is loaded as a TYPO3 extension (the
commands build their own dependencies, so no `Services.yaml` is required). For a
plain project, prefer the standalone binary above — the file is then ignored.
