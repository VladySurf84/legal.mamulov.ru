param(
    [string] $Message = "",
    [string] $UiMessage = "",
    [switch] $SkipTests,
    [switch] $SkipComposer,
    [switch] $SkipAssets,
    [switch] $SkipMigrate,
    [switch] $NoPush
)

$ErrorActionPreference = "Stop"

$Php = "C:\tools\php85\php.exe"
$Remote = "eugene@legal.mamulov.ru"
$RemotePath = "/var/www/eugene/data/www/legal.mamulov.ru"
$RemotePhp = "/opt/php85/bin/php"
$RemoteComposer = "composer.phar"
$UiPath = "vendor/mamulov/ui"
$UiPackage = "mamulov/ui"

function Run-Step {
    param(
        [string] $Title,
        [scriptblock] $Command
    )

    Write-Host ""
    Write-Host "==> $Title" -ForegroundColor Cyan
    $global:LASTEXITCODE = 0
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Title failed with exit code $LASTEXITCODE"
    }
}

if (-not (Test-Path $Php)) {
    throw "PHP not found at $Php"
}

function Has-GitChanges {
    param([string] $Path = ".")

    $status = git -C $Path status --porcelain
    return $null -ne $status
}

function Require-CommitMessage {
    if ($Message -eq "") {
        throw "There are local changes. Pass -Message `"Your commit message`" or commit manually."
    }
}

if (Test-Path $UiPath) {
    Run-Step "mamulov-ui status" {
        git -C $UiPath status --short
    }

    if (Has-GitChanges $UiPath) {
        Require-CommitMessage

        if ($NoPush) {
            throw "mamulov-ui has local changes. Deploy needs to push mamulov-ui first so Composer can install it on the server. Remove -NoPush or commit/push mamulov-ui manually."
        }

        if ($SkipComposer) {
            throw "mamulov-ui has local changes. Do not use -SkipComposer: legal composer.lock and server vendor must be updated."
        }

        if ($SkipAssets) {
            throw "mamulov-ui has local changes. Do not use -SkipAssets: Tailwind CSS must be rebuilt with the updated UI components."
        }

        $effectiveUiMessage = $UiMessage

        if ($effectiveUiMessage -eq "") {
            $effectiveUiMessage = $Message
        }

        Run-Step "Commit mamulov-ui changes" {
            git -C $UiPath add .
            git -C $UiPath commit -m $effectiveUiMessage
        }

        Run-Step "Push mamulov-ui to GitHub" {
            git -C $UiPath push origin main
        }

        Run-Step "Update legal lock for mamulov-ui" {
            & $Php C:\tools\composer.phar update $UiPackage --no-install --no-interaction
        }
    }
}

Run-Step "Git status" {
    git status --short
}

if (-not $SkipTests) {
    Run-Step "Local tests" {
        & $Php artisan test
    }
}

$hasChanges = Has-GitChanges

if ($hasChanges) {
    Require-CommitMessage

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

if (-not $SkipAssets) {
    $remoteCommands += "npm install --no-package-lock --no-audit --no-fund"
    $remoteCommands += "npm run build"
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
