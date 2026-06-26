param(
    [string] $Message = "Kassa page updates",
    [string] $UiMessage = "Limit date range width",
    [switch] $SkipTests,
    [switch] $SkipMigrate,
    [switch] $NoPush
)

$ErrorActionPreference = "Stop"

$arguments = @(
    "-Message", $Message,
    "-UiMessage", $UiMessage
)

if ($SkipTests) {
    $arguments += "-SkipTests"
}

if ($SkipMigrate) {
    $arguments += "-SkipMigrate"
}

if ($NoPush) {
    $arguments += "-NoPush"
}

& "$PSScriptRoot\deploy.ps1" @arguments
