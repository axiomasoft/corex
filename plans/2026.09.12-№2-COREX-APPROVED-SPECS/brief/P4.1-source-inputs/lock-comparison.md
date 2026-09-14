# P4.1 — Exact lock startup comparison

D10 scopes this compact initial read only. Verify whole live lock hashes before mutation; machine-read full locks for graph/delta checks. All original bytes remain available; the package source snapshot is brief/P4.1-source-inputs/tenancy-composer.lock.

## 1. composer.lock

Whole-file SHA256: `0e684efe63f66caa57b1cd182a6d2b38e3891e59114539c27cf35b46178f12b1`.
Full package graph count: 151; canonical name/version/require/source/dist graph SHA256: `3a4e4cbb5498d4ef201c0fadcfcd766b391de03b337c3b48c808e1ccab98c3d8`.

```json
{
  "content-hash": "7a95f9c65b0c093440eba9b26f8a7d4a",
  "aliases": [],
  "minimum-stability": "dev",
  "stability-flags": {
    "corex/core": 20,
    "corex/modules": 20,
    "corex/tasks": 20,
    "corex/tenancy": 20,
    "flex/crm": 20
  },
  "packages": [
    {
      "name": "stancl/jobpipeline",
      "version": "v2.0.0-rc7",
      "source": {
        "type": "git",
        "url": "https://github.com/archtechx/jobpipeline.git",
        "reference": "07dc54a49cf9e2df1238ee4a347af333260519a6"
      },
      "dist": {
        "type": "zip",
        "url": "https://api.github.com/repos/archtechx/jobpipeline/zipball/07dc54a49cf9e2df1238ee4a347af333260519a6",
        "reference": "07dc54a49cf9e2df1238ee4a347af333260519a6",
        "shasum": ""
      },
      "require": {
        "illuminate/support": "^10.0|^11.0|^12.0|^13.0",
        "php": "^8.0"
      },
      "require-dev": {
        "ext-redis": "*",
        "orchestra/testbench": "^8.0|^9.0|^10.0|^11.0",
        "spatie/valuestore": "^1.2"
      },
      "type": "library",
      "autoload": {
        "psr-4": {
          "Stancl\\JobPipeline\\": "src/"
        }
      },
      "notification-url": "https://packagist.org/downloads/",
      "license": [
        "MIT"
      ],
      "authors": [
        {
          "name": "Samuel Štancl",
          "email": "samuel.stancl@gmail.com"
        }
      ],
      "description": "Turn any series of jobs into Laravel listeners.",
      "support": {
        "issues": "https://github.com/archtechx/jobpipeline/issues",
        "source": "https://github.com/archtechx/jobpipeline/tree/v2.0.0-rc7"
      },
      "time": "2026-03-18T16:50:56+00:00"
    },
    {
      "name": "stancl/tenancy",
      "version": "dev-master",
      "source": {
        "type": "git",
        "url": "https://github.com/archtechx/tenancy.git",
        "reference": "553f57a8adb62ff33e54c99281e7fafd516aab4f"
      },
      "dist": {
        "type": "zip",
        "url": "https://api.github.com/repos/archtechx/tenancy/zipball/553f57a8adb62ff33e54c99281e7fafd516aab4f",
        "reference": "553f57a8adb62ff33e54c99281e7fafd516aab4f",
        "shasum": ""
      },
      "require": {
        "ext-json": "*",
        "illuminate/support": "^12.0|^13.0",
        "laravel/prompts": "0.*",
        "laravel/tinker": "^2.0|^3.0",
        "php": "^8.4",
        "ramsey/uuid": "^4.7.3",
        "spatie/invade": "*",
        "stancl/jobpipeline": "2.0.0-rc7",
        "stancl/virtualcolumn": "^1.5.0"
      },
      "require-dev": {
        "doctrine/dbal": "^3.6.0",
        "larastan/larastan": "^3.0",
        "laravel/framework": "^13.0",
        "league/flysystem-aws-s3-v3": "^3.12.2",
        "league/flysystem-path-prefixing": "^3.0",
        "orchestra/testbench": "^10.0|^11.0",
        "pestphp/pest": "^4.0",
        "spatie/valuestore": "^1.2.5"
      },
      "default-branch": true,
      "type": "library",
      "extra": {
        "laravel": {
          "aliases": {
            "Tenancy": "Stancl\\Tenancy\\Facades\\Tenancy",
            "GlobalCache": "Stancl\\Tenancy\\Facades\\GlobalCache"
          },
          "providers": [
            "Stancl\\Tenancy\\TenancyServiceProvider"
          ]
        }
      },
      "autoload": {
        "files": [
          "src/helpers.php"
        ],
        "psr-4": {
          "Stancl\\Tenancy\\": "src/"
        }
      },
      "notification-url": "https://packagist.org/downloads/",
      "license": [
        "MIT"
      ],
      "authors": [
        {
          "name": "Samuel Štancl",
          "email": "samuel@archte.ch"
        }
      ],
      "description": "Automatic multi-tenancy for your Laravel application.",
      "keywords": [
        "laravel",
        "multi-database",
        "multi-tenancy",
        "multitenancy",
        "tenancy"
      ],
      "support": {
        "issues": "https://github.com/archtechx/tenancy/issues",
        "source": "https://github.com/archtechx/tenancy/tree/master"
      },
      "funding": [
        {
          "url": "https://tenancyforlaravel.com/donate",
          "type": "custom"
        },
        {
          "url": "https://github.com/stancl",
          "type": "github"
        }
      ],
      "time": "2026-07-23T07:26:04+00:00"
    }
  ]
}
```

## 2. packages/tenancy/composer.lock

Whole-file SHA256: `518ee0151af7d47d4711534a293dafb181f25faa9f364003e6661dd3dc50424a`.
Full package graph count: 143; canonical name/version/require/source/dist graph SHA256: `50acdd3b9cf0612e7e93f2075c4f8eafc5ee753a4b3489c65c18196c14d5b06b`.

```json
{
  "content-hash": "843166e0d20646dc51848e5900c15b9a",
  "aliases": [
    {
      "package": "stancl/tenancy",
      "version": "9999999-dev",
      "alias": "4.0.0",
      "alias_normalized": "4.0.0.0"
    }
  ],
  "minimum-stability": "dev",
  "stability-flags": {
    "corex/core": 20,
    "stancl/tenancy": 20
  },
  "packages": [
    {
      "name": "stancl/jobpipeline",
      "version": "v2.0.0-rc7",
      "source": {
        "type": "git",
        "url": "https://github.com/archtechx/jobpipeline.git",
        "reference": "07dc54a49cf9e2df1238ee4a347af333260519a6"
      },
      "dist": {
        "type": "zip",
        "url": "https://api.github.com/repos/archtechx/jobpipeline/zipball/07dc54a49cf9e2df1238ee4a347af333260519a6",
        "reference": "07dc54a49cf9e2df1238ee4a347af333260519a6",
        "shasum": ""
      },
      "require": {
        "illuminate/support": "^10.0|^11.0|^12.0|^13.0",
        "php": "^8.0"
      },
      "require-dev": {
        "ext-redis": "*",
        "orchestra/testbench": "^8.0|^9.0|^10.0|^11.0",
        "spatie/valuestore": "^1.2"
      },
      "type": "library",
      "autoload": {
        "psr-4": {
          "Stancl\\JobPipeline\\": "src/"
        }
      },
      "notification-url": "https://packagist.org/downloads/",
      "license": [
        "MIT"
      ],
      "authors": [
        {
          "name": "Samuel Štancl",
          "email": "samuel.stancl@gmail.com"
        }
      ],
      "description": "Turn any series of jobs into Laravel listeners.",
      "support": {
        "issues": "https://github.com/archtechx/jobpipeline/issues",
        "source": "https://github.com/archtechx/jobpipeline/tree/v2.0.0-rc7"
      },
      "time": "2026-03-18T16:50:56+00:00"
    },
    {
      "name": "stancl/tenancy",
      "version": "dev-master",
      "source": {
        "type": "git",
        "url": "https://github.com/archtechx/tenancy.git",
        "reference": "76e5f9655924271c6c9da54e2d4ccb82c6f71ea0"
      },
      "dist": {
        "type": "zip",
        "url": "https://api.github.com/repos/archtechx/tenancy/zipball/76e5f9655924271c6c9da54e2d4ccb82c6f71ea0",
        "reference": "76e5f9655924271c6c9da54e2d4ccb82c6f71ea0",
        "shasum": ""
      },
      "require": {
        "ext-json": "*",
        "illuminate/support": "^12.0|^13.0",
        "laravel/prompts": "0.*",
        "laravel/tinker": "^2.0|^3.0",
        "php": "^8.4",
        "ramsey/uuid": "^4.7.3",
        "spatie/invade": "*",
        "stancl/jobpipeline": "2.0.0-rc7",
        "stancl/virtualcolumn": "^1.5.0"
      },
      "require-dev": {
        "doctrine/dbal": "^3.6.0",
        "larastan/larastan": "^3.0",
        "laravel/framework": "^13.0",
        "league/flysystem-aws-s3-v3": "^3.12.2",
        "league/flysystem-path-prefixing": "^3.0",
        "orchestra/testbench": "^10.0|^11.0",
        "pestphp/pest": "^4.0",
        "spatie/valuestore": "^1.2.5"
      },
      "default-branch": true,
      "type": "library",
      "extra": {
        "laravel": {
          "aliases": {
            "Tenancy": "Stancl\\Tenancy\\Facades\\Tenancy",
            "GlobalCache": "Stancl\\Tenancy\\Facades\\GlobalCache"
          },
          "providers": [
            "Stancl\\Tenancy\\TenancyServiceProvider"
          ]
        }
      },
      "autoload": {
        "files": [
          "src/helpers.php"
        ],
        "psr-4": {
          "Stancl\\Tenancy\\": "src/"
        }
      },
      "notification-url": "https://packagist.org/downloads/",
      "license": [
        "MIT"
      ],
      "authors": [
        {
          "name": "Samuel Štancl",
          "email": "samuel@archte.ch"
        }
      ],
      "description": "Automatic multi-tenancy for your Laravel application.",
      "keywords": [
        "laravel",
        "multi-database",
        "multi-tenancy",
        "multitenancy",
        "tenancy"
      ],
      "support": {
        "issues": "https://github.com/archtechx/tenancy/issues",
        "source": "https://github.com/archtechx/tenancy/tree/master"
      },
      "funding": [
        {
          "url": "https://tenancyforlaravel.com/donate",
          "type": "custom"
        },
        {
          "url": "https://github.com/stancl",
          "type": "github"
        }
      ],
      "time": "2026-07-23T07:26:04+00:00"
    }
  ]
}
```

