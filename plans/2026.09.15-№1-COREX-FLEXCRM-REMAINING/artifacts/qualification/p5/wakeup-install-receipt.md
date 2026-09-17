# P5.6 installed workflow/wakeup qualification

**Status:** PASS — two independent Laravel 12 consumers installed immutable local ZIP distributions.

`verify-installed.py` builds clean package archives, uses `composer update` for consumer A and
`composer install` for consumer B, then verifies equal locks, no vendor symlinks, the workflow and
wakeup ports, and no Symfony/DurableWorkflow import from the workflow base service provider.

Evidence: `evidence/installed.json`.
