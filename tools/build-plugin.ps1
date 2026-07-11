param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\dist')
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$stagingRoot = Join-Path ([System.IO.Path]::GetTempPath()) 'cmply-wordpress-package'
$pluginRoot = Join-Path $stagingRoot 'cmply'
$archivePath = Join-Path $OutputDirectory 'cmply.zip'

if (Test-Path -LiteralPath $stagingRoot) {
    Remove-Item -LiteralPath $stagingRoot -Recurse -Force
}

New-Item -ItemType Directory -Path $pluginRoot -Force | Out-Null
New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null

$packageEntries = @(
    'assets',
    'includes',
    'cmply.php',
    'LICENSE',
    'readme.txt',
    'uninstall.php'
)

foreach ($entry in $packageEntries) {
    Copy-Item -LiteralPath (Join-Path $repositoryRoot $entry) -Destination $pluginRoot -Recurse -Force
}

if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

Compress-Archive -Path $pluginRoot -DestinationPath $archivePath -CompressionLevel Optimal
Remove-Item -LiteralPath $stagingRoot -Recurse -Force

Write-Output $archivePath
