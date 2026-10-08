# reCAPTCHA configuration status

Lodgix currently has no centralized reCAPTCHA provider integration or administrator settings. It is intentionally not enabled by this hardening pass: enabling a CAPTCHA without provider credentials, hostname/action policy, privacy wording, and deterministic test fixtures would either break legitimate forms or create a false security signal.

## Required implementation contract

When approved for a follow-up phase, implement one server-side service with:

- provider mode and enabled state
- public site key and encrypted server-side secret
- hostname and action validation
- score threshold for score-based verification
- short timeout and fail-closed behavior for enabled protected forms
- replay/expiry handling
- non-sensitive configuration audit events
- key replacement without revealing the existing secret

The service should be applied to browser-authenticated human forms only. APIs, signed webhooks, payment callbacks, and queue workers must continue using their own authentication controls.

## Required deployment inputs

- Provider account and site/secret keys, delivered through the secret manager
- Exact production hostnames
- Privacy/consent wording approved for the public site
- Form-by-form enforcement policy
- Test keys or isolated test strategy

No secret values belong in this repository or in this document.

