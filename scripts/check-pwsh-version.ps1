$v = $PSVersionTable.PSVersion
Write-Host ("PowerShell version: {0}" -f $v) -ForegroundColor Cyan
if ($v.Major -lt 7) {
    Write-Error "PowerShell 7+ required. Please upgrade."
    exit 1
}
Write-Host "PowerShell version OK (>=7)." -ForegroundColor Green
exit 0