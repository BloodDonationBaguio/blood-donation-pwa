Param(
  [string]$InputDir = "$PSScriptRoot/../docs/diagrams",
  [string]$OutputDir = "$PSScriptRoot/../docs/images",
  [string]$Format = "png",
  [string]$KrokiUrl = "https://kroki.io",
  [ValidateSet('auto','kroki','ink')] [string]$Backend = 'auto',
  [int]$Throttle = 8
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# Resolve provided paths relative to the current working directory if needed
function Resolve-UserPath([string]$path) {
  if ([IO.Path]::IsPathRooted($path)) { return (Resolve-Path -LiteralPath $path).Path }
  return (Resolve-Path -LiteralPath (Join-Path (Get-Location).Path $path)).Path
}

try {
  $InputDir = Resolve-UserPath $InputDir
} catch { }
try {
  $OutputDir = Resolve-UserPath $OutputDir
} catch { }

if (-not (Test-Path $InputDir)) { New-Item -ItemType Directory -Path $InputDir | Out-Null }
if (-not (Test-Path $OutputDir)) { New-Item -ItemType Directory -Path $OutputDir | Out-Null }

$files = Get-ChildItem -Path $InputDir -Filter *.mmd -File -Recurse
if (-not $files) { Write-Host "No .mmd files found in $InputDir"; exit 0 }

$files | ForEach-Object -Parallel {
  $diagramType = 'mermaid'
  $format = ${using:Format}
  $krokiUrl = ${using:KrokiUrl}
  $backend = ${using:Backend}
  $outName = [IO.Path]::GetFileNameWithoutExtension($_.Name) + ".${format}"
  $outPath = Join-Path ${using:OutputDir} $outName
  $body = Get-Content -Path $_.FullName -Raw
  $rendered = $false

  if ($backend -eq 'kroki' -or $backend -eq 'auto') {
    $uri = "$krokiUrl/$diagramType/$format"
    for ($i = 0; $i -lt 2 -and -not $rendered; $i++) {
      try {
        if ($format -eq 'svg') {
          $svg = Invoke-RestMethod -Uri $uri -Method Post -Body $body -ContentType 'text/plain; charset=utf-8'
          [IO.File]::WriteAllText($outPath, $svg)
        } else {
          Invoke-WebRequest -Uri $uri -Method Post -Body $body -ContentType 'text/plain; charset=utf-8' -OutFile $outPath | Out-Null
        }
        $rendered = $true
      } catch {
        Start-Sleep -Milliseconds (250 * [Math]::Pow(2, $i))
      }
    }
  }

  if (-not $rendered -and ($backend -eq 'ink' -or $backend -eq 'auto')) {
    try {
      $bytes = [Text.Encoding]::UTF8.GetBytes($body)
      $b64 = [Convert]::ToBase64String($bytes)
      $b64enc = [System.Uri]::EscapeDataString($b64)
      if ($format -eq 'svg') { $inkUri = "https://mermaid.ink/svg/$b64enc" } else { $inkUri = "https://mermaid.ink/img/$b64enc" }
      Invoke-WebRequest -Uri $inkUri -OutFile $outPath | Out-Null
      $rendered = $true
    } catch {
      Write-Warning "Failed to render $(Split-Path -Leaf $_.FullName): $($_.Exception.Message)"
    }
  }

  if ($rendered) { Write-Host "Rendered $(Split-Path -Leaf $_.Name) -> $outPath" } else { Write-Host "Skipped $(Split-Path -Leaf $_.Name) (render failed)" }
} -ThrottleLimit $Throttle


