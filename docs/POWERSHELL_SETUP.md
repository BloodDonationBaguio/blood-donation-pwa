# PowerShell Setup and Verification (Windows)

Use these steps to install/upgrade PowerShell, verify integrity with SHA256, and prepare environment for our monitoring automation.

## Check Current Version
Run in PowerShell:

```
$PSVersionTable.PSVersion
```

Expected: `Major >= 7`. Our scripts are tested on 7.5+.

## Install/Upgrade Options
- Windows Store / Winget:
  - Install: `winget install --id Microsoft.PowerShell`
  - Upgrade: `winget upgrade --id Microsoft.PowerShell`

- MSI (manual):
  1. Download the appropriate MSI from the official release.
     - Example (Windows x64): `PowerShell-7.5.4-win-x64.msi`
  2. Verify SHA256 before installing (see next section).
  3. Install: double-click the MSI or run: `msiexec /i PowerShell-7.5.4-win-x64.msi`

## Verify Installer Hash (SHA256)
Use this to prevent tampering or corrupted downloads.

Option A — Quick one-liner:
```
Get-FileHash .\PowerShell-7.5.4-win-x64.msi -Algorithm SHA256 | Format-List
```
Compare with the official release hash.

Option B — Helper script:
```
pwsh -File scripts\verify-file-hash.ps1 -FilePath .\PowerShell-7.5.4-win-x64.msi -ExpectedHash 7066AFBB029979DA067D110CB1D426AC77175590652DF1F066FDAFD86BAE0219 -Algorithm SHA256
```

If the script prints `Hash matches`, you’re good to install.

## Post-Install Check
Launch PowerShell and confirm version:
```
$PSVersionTable.PSVersion
```
Optionally:
```
pwsh -File scripts\check-pwsh-version.ps1
```

## Environment for Monitoring Script
Set your UptimeRobot API key and (optional) alert contact IDs:
```
setx UPTIMEROBOT_API_KEY "<your_read_write_api_key>"
setx UPTIMEROBOT_ALERT_CONTACTS "<contact_id1>,<contact_id2>"
```
Open a new PowerShell window to pick up env changes, then run:
```
pwsh -File monitoring\setup-uptimerobot.ps1
```

## Troubleshooting
- If `Invoke-RestMethod` fails, ensure TLS 1.2/1.3 is enabled and proxy settings aren’t blocking requests.
- If health endpoint shows `db_connected: false`, fix DB credentials/connectivity and retest.