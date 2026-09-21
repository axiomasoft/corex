# P11-Q2 — primary-source verification

**Accessed:** 2026-09-20  
**Method:** direct official documentation search/open after the Perplexity response

## Symfony

Source: https://symfony.com/doc/current/service_container/compiler_passes.html

- Symfony first compiles configuration from enabled extensions, checks and optimizes it, then
  caches the compiled container before normal service use.
- Compiler passes operate on definitions before services are instantiated.
- Definition mutation is permitted only before the container is compiled.
- Compiler-pass type and priority order compiler passes, but this does not define identity,
  duplicate or replacement semantics for an application-specific extension registry.

## Laravel queue workers

Source: https://laravel.com/docs/12.x/queues

- `queue:work` is long-lived and retains the booted application and application static state.
- Workers do not see deployed code changes until restart.
- `queue:restart` asks workers to exit gracefully after the current job; a process manager starts
  replacements, and the restart signal uses the configured cache.
- This supports a job-boundary adoption rule. It does not specify a fleet-wide atomic switch.

## Laravel Octane

Source: https://laravel.com/docs/12.x/octane

- Octane boots the application once and retains the same application instance across requests.
- Application file changes require restart/reload; `octane:reload` gracefully restarts workers so
  subsequent requests use the new deployment.
- Octane resets first-party framework state but cannot generally reset application-created global
  state.
- This supports immutable per-request use plus deployment reload. It does not specify one atomic
  cutover instant across all workers.

## Design consequence

The frameworks support compile-before-use and explicit long-worker refresh boundaries. They do not
provide CoreX with registry identity, duplicate, fingerprint, durable replay or cluster-wide atomic
publication semantics. Those are explicit project decisions and must be tested locally.
