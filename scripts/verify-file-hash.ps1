Param(
    [Parameter(Mandatory=$true)][string]$FilePath,
    [Parameter(Mandatory=$true)][string]$ExpectedHash,
    [string]$Algorithm = 'SHA256'
)

if (-not (Test-Path -Path $FilePath)) {
    Write-Error "File not found: $FilePath"
    exit 1
}

try {
    $result = Get-FileHash -Path $FilePath -Algorithm $Algorithm
} catch {
    Write-Error "Failed to compute hash: $($_.Exception.Message)"
    exit 1
}

$actual = $result.Hash.ToUpper()
$expected = $ExpectedHash.Trim().ToUpper()

if ($actual -eq $expected) {
    Write-Host "Hash matches ($Algorithm): $actual" -ForegroundColor Green
    exit 0
} else {
    Write-Error "Hash mismatch! Expected: $expected; Actual: $actual"
    exit 2
}