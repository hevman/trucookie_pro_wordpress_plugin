param(
    [string]$OutputZip = ""
)

$ErrorActionPreference = "Stop"

$pluginDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $pluginDir

if ([string]::IsNullOrWhiteSpace($OutputZip)) {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $OutputZip = Join-Path $rootDir ("trucookie-release-" + $stamp + ".zip")
}

$stageRoot = Join-Path $env:TEMP ("trucookie-release-stage-" + [guid]::NewGuid().ToString("N"))
$stagePlugin = Join-Path $stageRoot "trucookie"

New-Item -ItemType Directory -Path $stagePlugin -Force | Out-Null
Copy-Item -Path (Join-Path $pluginDir "*") -Destination $stagePlugin -Recurse -Force

$removePaths = @(
    (Join-Path $stagePlugin "tests"),
    (Join-Path $stagePlugin "tools"),
    (Join-Path $stagePlugin "build-release.ps1")
)

foreach ($p in $removePaths) {
    if (Test-Path $p) {
        Remove-Item $p -Recurse -Force
    }
}

if (Test-Path $OutputZip) {
    Remove-Item $OutputZip -Force
}

Compress-Archive -Path (Join-Path $stageRoot "*") -DestinationPath $OutputZip -CompressionLevel Optimal
Remove-Item $stageRoot -Recurse -Force

Write-Output ("Release package: " + $OutputZip)
