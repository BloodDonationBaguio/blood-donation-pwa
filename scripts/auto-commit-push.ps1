Param(
  [int]$IntervalSeconds = 5
)

$ErrorActionPreference = 'SilentlyContinue'
$repo = (Get-Location).Path
Write-Host "[auto-commit] Watcher running in $repo (interval: $IntervalSeconds s)"

function HasChanges {
  $status = git status --porcelain
  return -not [string]::IsNullOrWhiteSpace($status)
}

while ($true) {
  if (HasChanges) {
    git add -A | Out-Null
    $msg = "auto: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    git commit -m $msg 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) {
      Write-Host "[auto-commit] Pushing..."
      git push | Out-Null
    } else {
      Write-Host "[auto-commit] Nothing to commit."
    }
  }
  Start-Sleep -Seconds $IntervalSeconds
}