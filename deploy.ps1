# Publishes the Buzlook project into the dedicated XAMPP htdocs folder.
# Usage:  powershell -ExecutionPolicy Bypass -File deploy.ps1
# Runtime data (data/*.sqlite*, uploads/*) on the target is never overwritten or deleted.

param(
    [string]$Target = 'D:\Xampp\htdocs'
)

$ErrorActionPreference = 'Stop'
$Source = $PSScriptRoot

if (-not (Test-Path $Target)) { throw "Target not found: $Target" }

# One-time backup of the stock XAMPP landing page that we are replacing.
$backup = Join-Path (Split-Path $Target -Parent) 'htdocs_original_backup'
if (-not (Test-Path $backup)) {
    New-Item -ItemType Directory -Path $backup | Out-Null
    foreach ($f in 'index.php', 'applications.html') {
        $p = Join-Path $Target $f
        if (Test-Path $p) { Copy-Item $p $backup }
    }
}

# Code directories: mirror (removes files deleted from the project).
foreach ($dir in 'assets', 'src', 'templates', 'bin', 'lang') {
    robocopy (Join-Path $Source $dir) (Join-Path $Target $dir) /MIR /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed for $dir (exit $LASTEXITCODE)" }
}

# Root files.
foreach ($f in 'index.php', 'config.php', '.htaccess') {
    Copy-Item (Join-Path $Source $f) (Join-Path $Target $f) -Force
}

# Runtime dirs: create if missing, copy only the tracked placeholders/.htaccess, never data.
foreach ($dir in 'data', 'uploads') {
    New-Item -ItemType Directory -Force -Path (Join-Path $Target $dir) | Out-Null
}
foreach ($f in 'uploads\.htaccess', 'uploads\.gitkeep', 'data\.htaccess', 'data\.gitkeep') {
    $s = Join-Path $Source $f
    if (Test-Path $s) { Copy-Item $s (Join-Path $Target $f) -Force }
}

Write-Host "Buzlook deployed to $Target at $(Get-Date -Format s)"
