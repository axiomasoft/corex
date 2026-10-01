# pplx-02 — слои валидации конфигурации и production-guards

- Tool: perplexity-web `search` · Date: 2026-10-01
- Query: "Framework/package design: fail-fast configuration validation at application boot versus build-time commands (composer install, artisan package:discover, config:cache in a Docker build stage without runtime secrets). Best practice: validate shape at boot but defer secret presence to first use? And production safety guards for dangerous configuration flags: hard failure at boot versus explicit per-flag acknowledgement/opt-in (examples: Django check --deploy and SECURE_* settings, Symfony, Rails production checks, Kubernetes feature gates, Elasticsearch bootstrap checks). Which pattern do mature platforms use and why?"
- Status: provenance (synthesized answer; not a primary source).

## Answer (verbatim)

The strongest pattern is layered validation: validate configuration shape and deterministic invariants during build or deployment, validate runtime-dependent requirements when the application boots, and resolve credentials or contact external services only when the relevant feature is actually used. For safety-critical production settings, mature systems generally fail startup when a required invariant is violated; explicit acknowledgements are best reserved for intentional, documented exceptions—not as a general substitute for checks.

## Separate validation by what it needs

- Build time: Check syntax, types, allowed values, required structural fields, and relationships that don’t depend on deployment secrets or live infrastructure. These checks can run during composer install, package discovery, config compilation, or a dedicated validation command.
- Runtime boot: Check the effective configuration for the actual environment, including required values that are injected at runtime. Don’t require build-stage secrets merely to cache or compile configuration.
- Feature use: Defer network connections, authentication handshakes, and other operational checks until the feature needs them—unless the application cannot safely serve any requests without that dependency.
A Docker image build should not need production secrets just to discover a package or produce a config artifact. Laravel’s docs describe config:cache as combining configuration for faster loading, and warn that once configuration is cached, .env is no longer loaded during requests or Artisan commands. That argues for keeping config construction independent from live service access, and for injecting runtime values through the environment in the deployed process.
For a required secret, “validate at first use” is usually too late if the application is expected to serve requests that depend on it: workers may start, accept work, then fail only on a particular job or request. Prefer a boot-time presence and basic-format check when the feature is enabled and needed by that process. Do not print the value, and don’t test whether it works by making a remote call unless that dependency is intentionally part of readiness.

## Production safety checks

Separate two categories:
- Hard invariants: A setting combination that makes the system unsafe, incompatible, or likely to lose data should block startup in production. Examples include debug mode in production, an invalid security prerequisite, or unsafe cluster bootstrap configuration.
- Context-dependent recommendations: A setting may be inappropriate for some deployments but valid for others. Report it as a warning or deployment-check finding, and allow CI or operators to promote the warning to an error where their deployment policy requires it.
Django illustrates the second category: check --deploy runs additional checks against production settings, and its deployment checklist explains that some security settings are optional because they are not appropriate for every site or are inconvenient in development. Django also states that production DEBUG must be off and that SECRET_KEY must be secret.
Elasticsearch illustrates the first: bootstrap-check failures are warnings in development mode but prevent startup in production mode; some checks are always enforced to prevent incompatible configurations. It also provides a way to force checks for certain single-node production setups that might otherwise evade production-mode detection.
So, for a package or framework, I’d make a dangerous flag’s unsafe state fail by default in production. If there is a legitimate exception, require an explicit, narrowly scoped opt-in with a clear name, documentation, and an audit-friendly warning. Avoid a generic “I acknowledge all risks” escape hatch: it weakens the invariant and makes accidental unsafe deployment easier.

## Practical package policy

- Make config parsing and schema validation deterministic and safe to run in build tooling.
- Validate production-only safety invariants against the effective runtime configuration during application boot.
- Require enabled features to have their necessary credentials at boot when that process must serve those features; defer credential use and connectivity to feature invocation.
- Treat optional or environment-dependent recommendations as checks with severity levels, so CI can enforce stricter policy without making every deployment share one assumption.
- Keep errors actionable and redacted: identify the missing or unsafe key and the remedy, never reveal secret values.
The goal is not “fail as early as technically possible” in every context. It is “fail before the affected process can accept work,” without making image construction depend on deployment-only secrets or external services.

## Sources

1. https://laravel.com/framework/docs/configuration
2. https://docs.djangoproject.com/en/6.1/howto/deployment/checklist/
3. https://www.elastic.co/docs/deploy-manage/deploy/self-managed/bootstrap-checks
