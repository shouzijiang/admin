# Pack backend/ excluding .env, runtime, vendor
# Usage:
#   powershell -File scripts\pack-backend.ps1
#   powershell -File scripts\pack-backend.ps1 -OutFile E:\php\admin\backend.zip

param(
    [string]$OutFile = ""
)

$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"

if (-not (Test-Path -LiteralPath $Backend)) {
    throw "backend not found: $Backend"
}

if ([string]::IsNullOrWhiteSpace($OutFile)) {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $OutFile = Join-Path $Root "backend-$stamp.zip"
}

$OutFile = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($OutFile)
$OutDir = Split-Path -Parent $OutFile
if (-not (Test-Path -LiteralPath $OutDir)) {
    New-Item -ItemType Directory -Path $OutDir | Out-Null
}
if (Test-Path -LiteralPath $OutFile) {
    Remove-Item -LiteralPath $OutFile -Force
}

$ExcludeDirs = @("runtime", "vendor")
$ExcludeFiles = @(".env")

Write-Host "Source : $Backend"
Write-Host "Exclude: dirs=$($ExcludeDirs -join ',') files=$($ExcludeFiles -join ',') .env.*"
Write-Host "Output : $OutFile"

$tempRoot = Join-Path $env:TEMP ("admin-backend-pack-" + [guid]::NewGuid().ToString("N"))
$stage = Join-Path $tempRoot "backend"
New-Item -ItemType Directory -Path $stage -Force | Out-Null

try {
    $items = Get-ChildItem -LiteralPath $Backend -Force
    foreach ($item in $items) {
        $name = $item.Name
        if ($item.PSIsContainer) {
            if ($ExcludeDirs -contains $name) {
                Write-Host "  skip dir  $name"
                continue
            }
            Copy-Item -LiteralPath $item.FullName -Destination (Join-Path $stage $name) -Recurse -Force
        }
        else {
            if ($ExcludeFiles -contains $name -or $name -like ".env.*") {
                Write-Host "  skip file $name"
                continue
            }
            Copy-Item -LiteralPath $item.FullName -Destination (Join-Path $stage $name) -Force
        }
    }

    foreach ($d in $ExcludeDirs) {
        $p = Join-Path $stage $d
        if (Test-Path -LiteralPath $p) {
            Remove-Item -LiteralPath $p -Recurse -Force
        }
    }
    foreach ($f in $ExcludeFiles) {
        $p = Join-Path $stage $f
        if (Test-Path -LiteralPath $p) {
            Remove-Item -LiteralPath $p -Force
        }
    }

    Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $OutFile -CompressionLevel Optimal

    $sizeMb = [math]::Round((Get-Item -LiteralPath $OutFile).Length / 1MB, 2)
    Write-Host "Done: $OutFile ($sizeMb MB)"
    Write-Host "After unzip on server: composer install --no-dev ; keep existing .env and runtime"
}
finally {
    if (Test-Path -LiteralPath $tempRoot) {
        Remove-Item -LiteralPath $tempRoot -Recurse -Force
    }
}
