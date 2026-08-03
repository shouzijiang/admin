# Pack only changed files for Baota deploy.
# Frontend: rebuilds if source changed (same as pack.ps1).
# Backend:  only packs files changed since last commit (git diff).
# Usage:
#   .\scripts\pack-diff.ps1
#   .\scripts\pack-diff.ps1 -OutFile E:\php\admin\dist\custom-name.zip

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
    Write-Host "=== Cleaning old frontend assets ==="
    $publicDir = Join-Path $Backend "public"
    $oldAssets = Join-Path $publicDir "assets"
    $oldHtml   = Join-Path $publicDir "index.html"
    if (Test-Path -LiteralPath $oldAssets) {
        Remove-Item -LiteralPath $oldAssets -Recurse -Force
        Write-Host "  removed assets/"
    }
    if (Test-Path -LiteralPath $oldHtml) {
        Remove-Item -LiteralPath $oldHtml -Force
        Write-Host "  removed index.html"
    }
    Write-Host ""

    Write-Host "=== Building frontend ==="
    cmd /c "cd /d `"$Frontend`" && pnpm run build"
    if ($LASTEXITCODE -ne 0) { throw "Frontend build failed" }
    Write-Host ""
}

# ── 收集变更文件 ──────────────────────────────────────
Write-Host "=== Collecting changed files ==="

# tracked files: added / copied / modified / renamed (exclude deleted)
$tracked = cmd /c "git -C `"$Root`" diff --name-only --diff-filter=ACMRT HEAD -- backend/ 2>nul"
if ($LASTEXITCODE -ne 0) {
    Write-Host "  (no HEAD commit or not a git repo, falling back to full pack)"
    # Fallback: pack everything (same as pack.ps1 behavior)
    $tracked = @()
    $fallback = $true
} else {
    $fallback = $false
}

# untracked files under backend/
$untracked = cmd /c "git -C `"$Root`" ls-files --others --exclude-standard -- backend/ 2>nul"

$allChanged = @()
if (-not $fallback) {
    # Merge tracked + untracked, normalize paths, dedupe
    $seen = @{}
    foreach ($f in ($tracked + $untracked)) {
        $normalized = $f -replace '\\', '/'  # git always uses /
        if ($seen.ContainsKey($normalized)) { continue }
        $seen[$normalized] = $true
        $allChanged += $normalized
    }
}

$ExcludePrefixes = @("backend/runtime/", "backend/vendor/")
$ExcludeFiles = @("backend/.env")

if (-not $fallback -and $allChanged.Count -eq 0) {
    Write-Host "  No backend files changed."
} else {
    if ($fallback) {
        Write-Host "  (full pack mode)"
    } else {
        Write-Host "  Found $($allChanged.Count) changed file(s):"
        foreach ($f in $allChanged) {
            Write-Host "    $f"
        }
    }
}

# ── 生成输出路径 ──────────────────────────────────────
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

# ── 打包 ──────────────────────────────────────────────
Write-Host ""
Write-Host "=== Packing ==="
Write-Host "Source : $Backend"
Write-Host "Output : $OutFile"

$tempRoot = "$env:TEMP\admin-pack-$([guid]::NewGuid().ToString('N'))"
$stage = "$tempRoot\backend"
New-Item -ItemType Directory -Path $stage -Force | Out-Null

try {
    if ($fallback) {
        # ── 全量模式 ──────────────────────────────────
        $items = Get-ChildItem -LiteralPath $Backend -Force
        foreach ($item in $items) {
            $name = $item.Name
            if ($item.PSIsContainer) {
                if ($name -eq "runtime" -or $name -eq "vendor") {
                    Write-Host "  skip dir  $name"
                    continue
                }
                Copy-Item -LiteralPath $item.FullName -Destination "$stage\$name" -Recurse -Force
            } else {
                if ($name -eq ".env" -or $name -like ".env.*") {
                    Write-Host "  skip file $name"
                    continue
                }
                Copy-Item -LiteralPath $item.FullName -Destination "$stage\$name" -Force
            }
        }
    } else {
        # ── 增量模式 ──────────────────────────────────
        $copied = 0
        $skipped = 0
        foreach ($relPath in $allChanged) {
            # relPath like "backend/app/controller/Admin.php"
            # Strip "backend/" prefix to get path relative to backend/
            $innerPath = $relPath -replace '^backend/', ''

            # skip excluded dirs (runtime, vendor)
            $skip = $false
            foreach ($prefix in $ExcludePrefixes) {
                if ($relPath.StartsWith($prefix) -or $innerPath.StartsWith($prefix.Replace("backend/", ""))) {
                    Write-Host "  skip      $relPath"
                    $skipped++
                    $skip = $true
                    break
                }
            }
            if ($skip) { continue }

            # skip excluded files
            $fileName = Split-Path -Leaf $innerPath
            if ($fileName -eq ".env" -or $fileName -like ".env.*") {
                Write-Host "  skip      $relPath"
                $skipped++
                continue
            }

            $srcPath = Join-Path $Root $relPath
            $dstPath = Join-Path $stage $innerPath

            if (-not (Test-Path -LiteralPath $srcPath)) {
                Write-Host "  gone      $relPath"
                $skipped++
                continue
            }

            # ensure parent dir exists
            $dstDir = Split-Path -Parent $dstPath
            if (-not (Test-Path -LiteralPath $dstDir)) {
                New-Item -ItemType Directory -Path $dstDir -Force | Out-Null
            }

            Copy-Item -LiteralPath $srcPath -Destination $dstPath -Force
            Write-Host "  copy      $relPath"
            $copied++
        }
        Write-Host "  Copied $copied file(s), skipped $skipped"
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
