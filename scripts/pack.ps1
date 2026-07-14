# Pack backend/ for Baota deploy.
# Auto-detects frontend changes, rebuilds if needed, then packs backend/ into dist/.
# Usage:
#   .\scripts\pack.ps1
#   .\scripts\pack.ps1 -OutFile E:\php\admin\dist\custom-name.zip

param(
    [string]$OutFile = ""
)

$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$Frontend = Join-Path $Root "frontend"

if (-not (Test-Path -LiteralPath $Backend)) { throw "backend not found: $Backend" }
if (-not (Test-Path -LiteralPath $Frontend)) { throw "frontend not found: $Frontend" }

# ── 构建前端 ──────────────────────────────────────────
$builtHtml = Join-Path $Backend "public\index.html"
$needBuild = $false

if (-not (Test-Path -LiteralPath $builtHtml)) {
    Write-Host "[frontend] no prior build, will build"
    $needBuild = $true
} else {
    $builtTime = (Get-Item $builtHtml).LastWriteTime
    $srcDir = Join-Path $Frontend "src"
    $newerSrc = Get-ChildItem -LiteralPath $srcDir -Recurse -File -ErrorAction SilentlyContinue |
        Where-Object { $_.LastWriteTime -gt $builtTime }
    if ($newerSrc) {
        Write-Host "[frontend] source changes detected, will rebuild"
        $needBuild = $true
    } else {
        Write-Host "[frontend] no changes, skip build"
    }
}

if ($needBuild) {
    Write-Host "=== Building frontend ==="
    Push-Location $Frontend
    try {
        pnpm run build
        if ($LASTEXITCODE -ne 0) { throw "Frontend build failed" }
    } finally { Pop-Location }
    Write-Host ""
}

# ── 打包后端 ──────────────────────────────────────────
if ([string]::IsNullOrWhiteSpace($OutFile)) {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $OutFile = Join-Path (Join-Path $Root "dist") "admin-deploy-$stamp.zip"
}
$OutFile = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($OutFile)
$OutDir = Split-Path -Parent $OutFile
if (-not (Test-Path -LiteralPath $OutDir)) {
    New-Item -ItemType Directory -Path $OutDir -Force | Out-Null
}
if (Test-Path -LiteralPath $OutFile) {
    Remove-Item -LiteralPath $OutFile -Force
}

$ExcludeDirs = @("runtime", "vendor")
$ExcludeFiles = @(".env")

Write-Host "=== Packing backend ==="
Write-Host "Source : $Backend"
Write-Host "Output : $OutFile"
Write-Host "Exclude: dirs=runtime,vendor files=.env .env.*"

$tempRoot = "$env:TEMP\admin-pack-$([guid]::NewGuid().ToString('N'))"
$stage = "$tempRoot\backend"
New-Item -ItemType Directory -Path $stage -Force | Out-Null

try {
    # Copy files into stage
    $items = Get-ChildItem -LiteralPath $Backend -Force
    foreach ($item in $items) {
        $name = $item.Name
        if ($item.PSIsContainer) {
            if ($ExcludeDirs -contains $name) {
                Write-Host "  skip dir  $name"
                continue
            }
            Copy-Item -LiteralPath $item.FullName -Destination "$stage\$name" -Recurse -Force
        } else {
            if ($ExcludeFiles -contains $name -or $name -like ".env.*") {
                Write-Host "  skip file $name"
                continue
            }
            Copy-Item -LiteralPath $item.FullName -Destination "$stage\$name" -Force
        }
    }

    # ensure runtime dir with .gitignore
    $runtimeDir = "$stage\runtime"
    if (-not (Test-Path -LiteralPath $runtimeDir)) {
        New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null
    }
    Set-Content -Path "$runtimeDir\.gitignore" -Value "*`r`n!.gitignore`r`n" -Encoding ASCII

    Compress-Archive -Path "$stage\*" -DestinationPath $OutFile -CompressionLevel Optimal

    $sizeMb = [math]::Round((Get-Item -LiteralPath $OutFile).Length / 1MB, 2)
    Write-Host ""
    Write-Host "Done: $OutFile ($sizeMb MB)"
    Write-Host "After upload: composer install --no-dev ; keep existing .env and runtime"
} finally {
    if (Test-Path -LiteralPath $tempRoot) {
        Remove-Item -LiteralPath $tempRoot -Recurse -Force
    }
}
