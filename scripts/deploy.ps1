param(
    [string] $Message = "",
    [switch] $SkipTests,
    [switch] $SkipComposer,
    [switch] $SkipMigrate,
    [switch] $NoPush
)

$ErrorActionPreference = "Stop"

$Php = "C:\tools\php85\php.exe"
$Remote = "eugene@legal.mamulov.ru"
$RemotePath = "/var/www/eugene/data/www/legal.mamulov.ru"
$RemotePhp = "/opt/php85/bin/php"
$RemoteComposer = "/usr/bin/composer"

function Run-Step {
    param(
        [string] $Title,
        [scriptblock] $Command
    )

    Write-Host ""
    Write-Host "==> $Title" -ForegroundColor Cyan
    & $Command
}

if (-not (Test-Path $Php)) {
    throw "PHP not found at $Php"
}

Run-Step "Git status" {
    git status --short
}

if (-not $SkipTests) {
    Run-Step "Local tests" {
        & $Php artisan test
    }
}

$hasChanges = (git status --porcelain) -ne $null

if ($hasChanges) {
    if ($Message -eq "") {
        throw "There are local changes. Pass -Message `"Your commit message`" or commit manually."
    }

    Run-Step "Commit local changes" {
        git add .
        git commit -m $Message
    }
}

if (-not $NoPush) {
    Run-Step "Push to GitHub" {
        git push origin main
    }
}

$remoteCommands = @(
    "set -e",
    "cd $RemotePath",
    "git pull origin main"
)

if (-not $SkipComposer) {
    $remoteCommands += "$RemotePhp $RemoteComposer install --no-dev --optimize-autoloader"
}

if (-not $SkipMigrate) {
    $remoteCommands += "$RemotePhp artisan migrate --force"
}

$remoteCommands += @(
    "$RemotePhp artisan config:clear",
    "$RemotePhp artisan route:clear",
    "$RemotePhp artisan view:clear",
    "$RemotePhp artisan schedule:list"
)

Run-Step "Deploy on server" {
    ssh $Remote ($remoteCommands -join " && ")
}

Write-Host ""
Write-Host "Deploy complete." -ForegroundColor Green
