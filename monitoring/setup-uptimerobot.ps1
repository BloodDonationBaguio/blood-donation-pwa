Param(
    [string]$ApiKey = $env:UPTIMEROBOT_API_KEY,
    [string]$AlertContacts = $env:UPTIMEROBOT_ALERT_CONTACTS
)

if (-not $ApiKey -or $ApiKey.Trim().Length -eq 0) {
    Write-Error "Set UPTIMEROBOT_API_KEY environment variable with a read-write API key."
    exit 1
}

$Endpoint = "https://api.uptimerobot.com/v2/newMonitor"

function New-UptimeMonitor {
    param(
        [string]$FriendlyName,
        [string]$Url,
        [int]$Interval = 300,
        [int]$Type = 1, # HTTP(s)
        [string]$HttpMethod = "GET"
    )

    $body = @{
        api_key       = $ApiKey
        format        = "json"
        type          = $Type
        friendly_name = $FriendlyName
        url           = $Url
        interval      = $Interval
        http_method   = $HttpMethod
    }

    if ($AlertContacts -and $AlertContacts.Trim().Length -gt 0) {
        $body.alert_contacts = $AlertContacts
    }

    try {
        $resp = Invoke-RestMethod -Method Post -Uri $Endpoint -Body $body -ContentType 'application/x-www-form-urlencoded'
        if ($resp.stat -ne 'ok') {
            Write-Error "Failed to create monitor '$FriendlyName': $($resp | ConvertTo-Json -Depth 5)"
        } else {
            Write-Host "Created monitor '$FriendlyName' (ID: $($resp.monitor.id))" -ForegroundColor Green
        }
    } catch {
        Write-Error "Error creating monitor '$FriendlyName': $($_.Exception.Message)"
    }
}

# Define monitors
$Monitors = @(
    @{ Name = 'Main Site'; Url = 'https://www.blooddonationbaguio.com/'; Interval = 300 },
    @{ Name = 'Health';    Url = 'https://www.blooddonationbaguio.com/health.php'; Interval = 60 }
)

foreach ($m in $Monitors) {
    New-UptimeMonitor -FriendlyName $m.Name -Url $m.Url -Interval $m.Interval
}

Write-Host "Done. Verify monitors in UptimeRobot dashboard." -ForegroundColor Cyan