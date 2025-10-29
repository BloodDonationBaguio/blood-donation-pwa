# Uptime Monitors Setup

This repo includes a simple, reliable monitoring plan using UptimeRobot to catch outages early and pinpoint root causes.

## Monitors to Create
- Main site: `https://www.blooddonationbaguio.com/` (HTTP 200)
- Health endpoint: `https://www.blooddonationbaguio.com/health.php` (HTTP 200)

Recommended config
- Interval: `1–5 minutes` (start at 1 minute for the health endpoint)
- Alert contacts: project email(s), SMS optional
- SSL errors: alert enabled
- Timeout: 10 seconds

## Optional Keyword Check
If using keyword monitoring, check response contains `"healthy": true`.

## Quick Manual Setup (UI)
1. Go to https://uptimerobot.com → create/log in.
2. “Add New Monitor” → Type: `HTTP(s)`.
3. Friendly Name: `Main Site`, URL: `https://www.blooddonationbaguio.com/`, Interval: `5 min`.
4. “Add New Monitor” → Type: `HTTP(s)`.
5. Friendly Name: `Health`, URL: `https://www.blooddonationbaguio.com/health.php`, Interval: `1 min`.
6. Attach alert contacts.

## API Automation (PowerShell)
Use `monitoring/setup-uptimerobot.ps1` to create both monitors via API.
- Set env var `UPTIMEROBOT_API_KEY` to your read-write key.
- Optional: set `UPTIMEROBOT_ALERT_CONTACTS` to a comma-separated list of alert contact IDs.

```
pwsh -File monitoring/setup-uptimerobot.ps1
```

## Cloudflare Compatibility Notes
- If Cloudflare is enabled (orange cloud): allow monitor IPs or create a firewall rule to allow path `health.php`.
- Cache bypass rule for `health.php` (edge caching can serve stale 200s).
- SSL mode: prefer `Full (strict)`; ensure origin certificate valid.
- Browser Integrity Check/Rate Limiting: do not challenge `health.php`.

## Alert Policy
- Critical alert if health endpoint fails >2 times consecutively.
- High alert if main site fails >2 times consecutively.
- Pause alerts during maintenance windows via UptimeRobot schedules.

## Runbook on Alert
1. Check `https://www.blooddonationbaguio.com/health.php`.
2. If `db_connected: false` → fix DB connectivity (credentials, network, provider status).
3. If health 200 but main site down → web server/app routing issue.
4. If both down → DNS/Cloudflare/origin server issue. See `docs/CLOUDFLARE_DNS_CHECKLIST.md`.