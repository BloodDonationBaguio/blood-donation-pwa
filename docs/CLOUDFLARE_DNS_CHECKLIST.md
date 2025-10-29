# Cloudflare/DNS Checklist

Actionable checks to keep `blooddonationbaguio.com` fast and reliable.

## DNS Records
- `A` record → points to origin IPv4; `AAAA` if IPv6 is used.
- Proxied (orange cloud) ON for `www` and apex if using Cloudflare; OFF temporarily for deep troubleshooting.
- TTL: `Auto` (recommended).

## SSL/TLS
- Mode: `Full (strict)` (recommended). If not available, use `Full` temporarily.
- Origin certificate valid and not expired; renew via Cloudflare Origin Certificates or Let’s Encrypt.
- Enable `Always Use HTTPS` and `Automatic HTTPS Rewrites`.

## Firewall Rules
- Allow monitors: create rule to `Allow` when `URI Path` `contains` `health.php`.
- Avoid challenges on health endpoint: disable `JS Challenge` for path `health.php`.
- If rate limiting enabled, exclude `health.php`.

## Caching
- Page Rule: `Cache Level: Bypass` for `*blooddonationbaguio.com/health.php*`.
- Turn off `Cache Everything` for API/admin routes (including `health.php`).

## Network
- Ensure origin server ports open for HTTP/HTTPS (80/443).
- If using non-standard ports, confirm Cloudflare supports them.

## Bot Management / Security
- Browser Integrity Check: keep ON globally; add path exemption for `health.php`.
- Security Level: not higher than `Medium` for admin operations; use rule-based exemptions.

## Diagnostic Commands (Windows PowerShell)
- DNS resolution: `Resolve-DnsName blooddonationbaguio.com` and `Resolve-DnsName www.blooddonationbaguio.com`.
- Port reachability: `Test-NetConnection blooddonationbaguio.com -Port 443`.
- HTTP status: `curl -I https://www.blooddonationbaguio.com/`.
- Health: `curl -s https://www.blooddonationbaguio.com/health.php` (expect `db_connected: true`).

## Rollback Plan
- If outage persists, set DNS records to `DNS only` (grey cloud) and retest.
- Temporarily disable WAF rules impacting admin/health paths.
- Re-enable protections once stable.

## Maintenance Windows
- Pause monitors during planned changes; resume after validation.
- Document changes in repo PRs referencing the time window.