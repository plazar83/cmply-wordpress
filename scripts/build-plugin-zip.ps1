param(
	[string] $OutputPath = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repoRoot = Split-Path -Parent $PSScriptRoot
if ( '' -eq $OutputPath ) {
	$OutputPath = Join-Path $repoRoot 'output\cmply.zip'
} elseif ( -not [System.IO.Path]::IsPathRooted( $OutputPath ) ) {
	$OutputPath = Join-Path $repoRoot $OutputPath
}

$outputDirectory = Split-Path -Parent $OutputPath
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null

$packagePaths = @(
	'assets',
	'includes',
	'cmply.php',
	'LICENSE',
	'readme.txt',
	'uninstall.php'
)

$requiredEntries = @(
	'assets/admin.css',
	'includes/class-cmply.php',
	'cmply.php',
	'LICENSE',
	'readme.txt',
	'uninstall.php'
)

$pluginSource = Get-Content -Raw ( Join-Path $repoRoot 'cmply.php' )
$readmeSource = Get-Content -Raw ( Join-Path $repoRoot 'readme.txt' )
$pluginVersion = [regex]::Match( $pluginSource, '(?m)^ \* Version:\s*(\S+)' ).Groups[1].Value
$constantVersion = [regex]::Match( $pluginSource, "CMPLY_COOKIE_CONSENT_VERSION', '([^']+)'" ).Groups[1].Value
$stableTag = [regex]::Match( $readmeSource, '(?m)^Stable tag:\s*(\S+)' ).Groups[1].Value

if ( '' -eq $pluginVersion -or $constantVersion -ne $pluginVersion -or $stableTag -ne $pluginVersion ) {
	throw "Version mismatch: header=$pluginVersion constant=$constantVersion stable=$stableTag"
}

$tar = ( Get-Command 'tar.exe' -ErrorAction SilentlyContinue )
if ( $null -eq $tar ) {
	$tar = Get-Command 'tar' -ErrorAction Stop
}

Push-Location $repoRoot
try {
	& $tar.Source -a -c -f $OutputPath @packagePaths
	if ( 0 -ne $LASTEXITCODE ) {
		throw "Archive creation failed with exit code $LASTEXITCODE"
	}

	$entries = @( & $tar.Source -tf $OutputPath )
	if ( 0 -ne $LASTEXITCODE ) {
		throw "Archive inspection failed with exit code $LASTEXITCODE"
	}
} finally {
	Pop-Location
}

$windowsEntries = @( $entries | Where-Object { $_ -match '\\' } )
if ( 0 -lt $windowsEntries.Count ) {
	throw "Archive contains Windows path separators: $($windowsEntries -join ', ')"
}

foreach ( $entry in $requiredEntries ) {
	if ( $entries -notcontains $entry ) {
		throw "Archive is missing required entry: $entry"
	}
}

$hash = Get-FileHash -Algorithm SHA256 -LiteralPath $OutputPath
Write-Output "Built CMPly ${pluginVersion}: $OutputPath"
Write-Output "SHA256 $($hash.Hash)"
Write-Output 'Verified root layout and Linux-compatible archive paths.'
