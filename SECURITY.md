# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

If you discover a security vulnerability in Alumni Career Map, please report it privately:

1. Use GitHub's **[Private Vulnerability Reporting](https://github.com/Alex247Git/alumni-career-map/security/advisories/new)** (preferred), or
2. Email the repository owner directly (see profile: [@Alex247Git](https://github.com/Alex247Git)).

Include as much of the following as you can:

- Type of issue (e.g. SQL injection, broken authz, XSS)
- Affected endpoint/file and step-by-step reproduction
- Potential impact
- Suggested fix, if you have one

### What to expect

- **Acknowledgement:** within 72 hours
- **Initial assessment:** within 7 days
- **Fix or mitigation:** security issues are prioritized over all other work

Please allow a reasonable time for a patch before any public disclosure. We will credit reporters in the release notes unless anonymity is requested.

## Security Design Notes

The API follows defense-in-depth practices: parameterized SQL everywhere, bcrypt password hashing, JWT authentication with role/ownership authorization middlewares, and a live integration test suite (auto-skips without the Docker stack) that exercises both happy and unhappy paths.

## Scope

- The PHP (Slim 4) API (`api/`) and frontend (`frontend/`) as shipped in this repository
- The Docker Compose stack configuration

Out of scope: the host system, deployment platform, and any third-party services.
