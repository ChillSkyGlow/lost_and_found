#requires -Version 7.0

<#
.SYNOPSIS
    lost_and_found 项目开发环境检查脚本

.DESCRIPTION
    检查：
      1. 项目目录
      2. Git
      3. PHP CLI / php.ini / PHP 扩展
      4. PHP-CGI
      5. xxfpm
      6. FastCGI 127.0.0.1:9000
      7. Composer
      8. MySQL
      9. Nginx
     10. Nginx -> PHP 实际请求
     11. PHP 文件语法
     12. .env / .gitignore
     13. Git 状态
     14. Nginx 日志

    本脚本默认：
      项目目录 = 当前脚本所在目录
      Nginx    = D:\Program_Files\WebServer\nginx
      PHP      = D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64
      FastCGI  = 127.0.0.1:9000
      Worker   = 16

    脚本不会：
      - 修改 MySQL
      - 修改项目文件
      - 修改 Nginx 配置
      - 修改 php.ini
      - 输出 .env 内容
      - 输出数据库密码
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = "Continue"

# ============================================================
# 配置
# ============================================================

$ProjectRoot = $PSScriptRoot

$NginxDir = "D:\Program_Files\WebServer\nginx"

$PhpDir = "D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64"

$PhpExe = Join-Path $PhpDir "php.exe"
$PhpCgi = Join-Path $PhpDir "php-cgi.exe"
$PhpIni = Join-Path $PhpDir "php.ini"

$XxfpmExe = Join-Path $PhpDir "xxfpm.exe"

$FastCgiHost = "127.0.0.1"
$FastCgiPort = 9000
$ExpectedWorkers = 16

$BaseUrl = "http://127.0.0.1"

# 如果项目存在 health.php，则会实际访问：
# http://127.0.0.1/health.php
#
# 不存在时不会创建文件，而是跳过该项。
$HealthPath = "/health.php"

# PHP 语法检查时排除的目录
$ExcludeDirs = @(
    "vendor",
    "node_modules",
    ".git"
)

# ============================================================
# 统计
# ============================================================

$Passed = 0
$Failed = 0
$Warnings = 0

# ============================================================
# 输出函数
# ============================================================

function Write-Section {
    param(
        [string]$Title
    )

    Write-Host ""
    Write-Host "============================================================" -ForegroundColor DarkGray
    Write-Host " $Title" -ForegroundColor Cyan
    Write-Host "============================================================" -ForegroundColor DarkGray
}

function Write-OK {
    param(
        [string]$Message
    )

    $script:Passed++
    Write-Host "  [OK]   $Message" -ForegroundColor Green
}

function Write-Warn {
    param(
        [string]$Message
    )

    $script:Warnings++
    Write-Host "  [WARN] $Message" -ForegroundColor Yellow
}

function Write-Fail {
    param(
        [string]$Message
    )

    $script:Failed++
    Write-Host "  [FAIL] $Message" -ForegroundColor Red
}

function Write-Info {
    param(
        [string]$Message
    )

    Write-Host "  [INFO] $Message" -ForegroundColor Gray
}

function Test-CommandExists {
    param(
        [string]$CommandName
    )

    return $null -ne (Get-Command $CommandName -ErrorAction SilentlyContinue)
}

function Get-ProcessCountSafe {
    param(
        [string]$Name
    )

    try {
        return @(Get-Process -Name $Name -ErrorAction SilentlyContinue).Count
    }
    catch {
        return 0
    }
}

# ============================================================
# 开始
# ============================================================

Write-Host ""
Write-Host "lost_and_found Development Environment Check" -ForegroundColor Cyan
Write-Host "Project: $ProjectRoot" -ForegroundColor Gray
Write-Host "Time:    $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor Gray

# ============================================================
# 1. 项目目录
# ============================================================

Write-Section "1. Project"

if (Test-Path $ProjectRoot -PathType Container) {
    Write-OK "Project directory exists: $ProjectRoot"
}
else {
    Write-Fail "Project directory does not exist: $ProjectRoot"
}

# ============================================================
# 2. Git
# ============================================================

Write-Section "2. Git"

if (Test-CommandExists "git") {
    try {
        $gitVersion = (& git --version 2>&1 | Select-Object -First 1).ToString()
        Write-OK "Git available: $gitVersion"

        Push-Location $ProjectRoot

        $gitRoot = (& git rev-parse --show-toplevel 2>$null)

        if ($LASTEXITCODE -eq 0 -and $gitRoot) {
            Write-OK "Git repository detected"
        }
        else {
            Write-Warn "Project is not a Git repository"
        }

        Pop-Location
    }
    catch {
        Write-Warn "Git command exists but could not query repository"
    }
}
else {
    Write-Fail "Git command not found"
}

# ============================================================
# 3. 基础项目文件
# ============================================================

Write-Section "3. Project Files"

$RequiredFiles = @(
    "composer.json",
    ".gitignore"
)

foreach ($file in $RequiredFiles) {
    $path = Join-Path $ProjectRoot $file

    if (Test-Path $path -PathType Leaf) {
        Write-OK "$file exists"
    }
    else {
        Write-Fail "$file missing"
    }
}

$envPath = Join-Path $ProjectRoot ".env"

if (Test-Path $envPath -PathType Leaf) {
    Write-OK ".env exists (contents hidden)"
}
else {
    Write-Warn ".env does not exist"
}

$envExamplePath = Join-Path $ProjectRoot ".env.example"

if (Test-Path $envExamplePath -PathType Leaf) {
    Write-OK ".env.example exists"
}
else {
    Write-Warn ".env.example missing"
}

# ============================================================
# 4. PHP CLI
# ============================================================

Write-Section "4. PHP CLI"

if (Test-Path $PhpExe -PathType Leaf) {

    Write-OK "PHP executable exists: $PhpExe"

    try {
        $phpVersionOutput = & $PhpExe -v 2>&1

        if ($LASTEXITCODE -eq 0) {
            $phpVersionLine = $phpVersionOutput |
                Where-Object { $_ -match "^PHP " } |
                Select-Object -First 1

            Write-OK "PHP CLI: $phpVersionLine"
        }
        else {
            Write-Fail "PHP CLI exists but could not execute"
        }
    }
    catch {
        Write-Fail "Failed to execute PHP CLI: $($_.Exception.Message)"
    }

}
elseif (Test-CommandExists "php") {

    Write-Warn "Configured PHP path not found, using php from PATH"

    try {
        $phpVersionLine = (& php -v 2>&1 |
            Where-Object { $_ -match "^PHP " } |
            Select-Object -First 1)

        Write-OK "PHP CLI from PATH: $phpVersionLine"
    }
    catch {
        Write-Fail "PHP CLI failed"
    }

}
else {
    Write-Fail "PHP CLI not found"
}

# ============================================================
# 5. php.ini
# ============================================================

Write-Section "5. PHP Configuration"

if (Test-Path $PhpIni -PathType Leaf) {
    Write-OK "php.ini exists: $PhpIni"

    try {
        $loadedIni = & $PhpExe --ini 2>&1

        $loadedIniLine = $loadedIni |
            Where-Object { $_ -match "Loaded Configuration File:" } |
            Select-Object -First 1

        if ($loadedIniLine) {
            Write-Info $loadedIniLine.ToString().Trim()
        }

        $extensionDir = & $PhpExe -i 2>&1 |
            Where-Object { $_ -match "^extension_dir" } |
            Select-Object -First 1

        if ($extensionDir) {
            Write-Info $extensionDir.ToString().Trim()
        }
    }
    catch {
        Write-Warn "Could not query PHP configuration"
    }
}
else {
    Write-Fail "php.ini not found: $PhpIni"
}

# ============================================================
# 6. PHP 扩展
# ============================================================

Write-Section "6. PHP Extensions"

$RequiredExtensions = @(
    "curl",
    "fileinfo",
    "mbstring",
    "openssl",
    "pdo_mysql",
    "mysqli",
    "zip"
)

try {

    $phpModules = @(
        & $PhpExe -m 2>&1 |
        ForEach-Object {
            $_.ToString().Trim()
        }
    )

    foreach ($extension in $RequiredExtensions) {

        $found = $phpModules |
            Where-Object {
                $_ -ieq $extension
            }

        if ($found) {
            Write-OK "PHP extension: $extension"
        }
        else {
            Write-Warn "PHP extension missing: $extension"
        }
    }

}
catch {
    Write-Fail "Could not query PHP extensions"
}

# ============================================================
# 7. PHP-CGI
# ============================================================

Write-Section "7. PHP-CGI"

if (Test-Path $PhpCgi -PathType Leaf) {

    Write-OK "php-cgi.exe exists: $PhpCgi"

    try {
        $cgiVersion = & $PhpCgi -v 2>&1

        if ($LASTEXITCODE -eq 0) {

            $cgiVersionLine = $cgiVersion |
                Where-Object { $_ -match "^PHP " } |
                Select-Object -First 1

            Write-OK "PHP-CGI: $cgiVersionLine"
        }
        else {
            Write-Warn "php-cgi.exe exists but version test failed"
        }
    }
    catch {
        Write-Warn "Could not execute php-cgi.exe"
    }

}
else {
    Write-Fail "php-cgi.exe not found: $PhpCgi"
}

# ============================================================
# 8. xxfpm
# ============================================================

Write-Section "8. xxfpm FastCGI Manager"

if (Test-Path $XxfpmExe -PathType Leaf) {
    Write-OK "xxfpm.exe exists: $XxfpmExe"
}
else {
    Write-Warn "xxfpm.exe not found at configured path: $XxfpmExe"
}

$xxfpmCount = Get-ProcessCountSafe "xxfpm"

if ($xxfpmCount -gt 0) {
    Write-OK "xxfpm process running: $xxfpmCount"
}
else {
    Write-Fail "xxfpm process not running"
}

# ============================================================
# 9. PHP-CGI Worker
# ============================================================

Write-Section "9. PHP-CGI Workers"

$phpCgiCount = Get-ProcessCountSafe "php-cgi"

if ($phpCgiCount -gt 0) {

    Write-OK "php-cgi workers running: $phpCgiCount"

    if ($ExpectedWorkers -gt 0) {

        if ($phpCgiCount -eq $ExpectedWorkers) {
            Write-OK "php-cgi worker count matches configured -n $ExpectedWorkers"
        }
        elseif ($phpCgiCount -lt $ExpectedWorkers) {
            Write-Warn "php-cgi workers $phpCgiCount < configured $ExpectedWorkers"
        }
        else {
            Write-Warn "php-cgi workers $phpCgiCount > configured $ExpectedWorkers"
        }
    }

}
else {
    Write-Fail "No php-cgi worker process found"
}

# ============================================================
# 10. FastCGI 9000
# ============================================================

Write-Section "10. FastCGI Socket"

try {

    $connections = @(
        Get-NetTCPConnection `
            -LocalAddress $FastCgiHost `
            -LocalPort $FastCgiPort `
            -State Listen `
            -ErrorAction SilentlyContinue
    )

    if ($connections.Count -gt 0) {
        Write-OK "FastCGI listening on ${FastCgiHost}:${FastCgiPort}"
    }
    else {

        # 有些 Windows 环境 LocalAddress 可能显示 0.0.0.0
        $connectionsAny = @(
            Get-NetTCPConnection `
                -LocalPort $FastCgiPort `
                -State Listen `
                -ErrorAction SilentlyContinue
        )

        if ($connectionsAny.Count -gt 0) {
            Write-Warn "Port $FastCgiPort is listening, but not explicitly on $FastCgiHost"
        }
        else {
            Write-Fail "Nothing is listening on port $FastCgiPort"
        }
    }

}
catch {
    Write-Warn "Could not inspect TCP port $FastCgiPort"
}

# ============================================================
# 11. Composer
# ============================================================

Write-Section "11. Composer"

if (Test-CommandExists "composer") {

    try {
        $composerVersion = (& composer --version 2>&1 |
            Select-Object -First 1)

        Write-OK "Composer: $composerVersion"

        $composerJson = Join-Path $ProjectRoot "composer.json"

        if (Test-Path $composerJson) {

            Push-Location $ProjectRoot

            $composerValidate = & composer validate --no-check-publish 2>&1

            if ($LASTEXITCODE -eq 0) {
                Write-OK "composer.json validation passed"
            }
            else {
                Write-Fail "composer.json validation failed"

                $composerValidate |
                    Select-Object -Last 5 |
                    ForEach-Object {
                        Write-Info $_.ToString()
                    }
            }

            Pop-Location
        }

    }
    catch {
        Write-Fail "Composer check failed: $($_.Exception.Message)"
    }

}
else {
    Write-Fail "Composer command not found"
}

# ============================================================
# 12. MySQL
# ============================================================

Write-Section "12. MySQL"

if (Test-CommandExists "mysql") {

    try {
        $mysqlVersion = (& mysql --version 2>&1 |
            Select-Object -First 1)

        Write-OK "MySQL CLI: $mysqlVersion"
    }
    catch {
        Write-Warn "MySQL CLI exists but version check failed"
    }

}
else {

    $mysqlCandidates = @(
        "D:\Program_Files\mysql\mysql-9.2.0-winx64\bin\mysql.exe",
        "C:\Program Files\MySQL\MySQL Server 9.2\bin\mysql.exe"
    )

    $mysqlFound = $mysqlCandidates |
        Where-Object { Test-Path $_ -PathType Leaf } |
        Select-Object -First 1

    if ($mysqlFound) {
        Write-OK "MySQL CLI found: $mysqlFound"
    }
    else {
        Write-Fail "MySQL CLI not found"
    }
}

# 检查 mysqld 是否运行
$mysqldCount = Get-ProcessCountSafe "mysqld"

if ($mysqldCount -gt 0) {
    Write-OK "mysqld process running: $mysqldCount"
}
else {
    Write-Warn "mysqld process not detected"
}

Write-Info "Database authentication is not tested to avoid exposing passwords."

# ============================================================
# 13. Nginx
# ============================================================

Write-Section "13. Nginx"

$NginxExe = Join-Path $NginxDir "nginx.exe"
$NginxConf = Join-Path $NginxDir "conf\nginx.conf"

if (Test-Path $NginxExe -PathType Leaf) {

    Write-OK "Nginx executable exists: $NginxExe"

    try {

        $nginxVersion = & $NginxExe -v 2>&1 |
            Select-Object -First 1

        Write-OK "Nginx: $nginxVersion"

    }
    catch {
        Write-Warn "Could not query Nginx version"
    }

}
else {
    Write-Fail "Nginx executable not found: $NginxExe"
}

if (Test-Path $NginxConf -PathType Leaf) {

    Write-OK "nginx.conf exists"

    try {

        Push-Location $NginxDir

        $nginxTest = & $NginxExe -t 2>&1

        if ($LASTEXITCODE -eq 0) {
            Write-OK "nginx -t passed"
        }
        else {
            Write-Fail "nginx -t failed"

            $nginxTest |
                Select-Object -Last 10 |
                ForEach-Object {
                    Write-Info $_.ToString()
                }
        }

        Pop-Location

    }
    catch {

        Pop-Location
        Write-Fail "Could not execute nginx -t"
    }

}
else {
    Write-Fail "nginx.conf not found: $NginxConf"
}

$nginxCount = Get-ProcessCountSafe "nginx"

if ($nginxCount -gt 0) {
    Write-OK "Nginx process running: $nginxCount"
}
else {
    Write-Fail "Nginx process not running"
}

# ============================================================
# 14. HTTP
# ============================================================

Write-Section "14. HTTP Server"

try {

    $response = Invoke-WebRequest `
        -Uri $BaseUrl `
        -UseBasicParsing `
        -TimeoutSec 10 `
        -ErrorAction Stop

    Write-OK "HTTP $($response.StatusCode): $BaseUrl"

    if ($response.Content.Length -gt 0) {
        Write-OK "HTTP response contains content ($($response.Content.Length) bytes)"
    }
    else {
        Write-Warn "HTTP response body is empty"
    }

}
catch {

    Write-Fail "HTTP request failed: $BaseUrl"

    if ($_.Exception.Message) {
        Write-Info $_.Exception.Message
    }
}

# ============================================================
# 15. Nginx -> PHP 实际执行
# ============================================================

Write-Section "15. Nginx -> PHP FastCGI"

$healthFile = Join-Path $ProjectRoot ($HealthPath.TrimStart("/"))

if (Test-Path $healthFile -PathType Leaf) {

    Write-Info "Health endpoint found: $HealthPath"

    try {

        $healthResponse = Invoke-WebRequest `
            -Uri ($BaseUrl + $HealthPath) `
            -UseBasicParsing `
            -TimeoutSec 10 `
            -ErrorAction Stop

        if ($healthResponse.StatusCode -eq 200) {

            Write-OK "PHP health endpoint returned HTTP 200"

            $healthContent = $healthResponse.Content

            if ($healthContent -match '"ok"\s*:\s*true') {
                Write-OK "PHP health check reports ok=true"
            }
            else {
                Write-Warn "Health endpoint returned 200 but ok=true was not found"
            }

            if ($healthContent -match '"sapi"\s*:\s*"([^"]+)"') {
                Write-OK "PHP SAPI: $($Matches[1])"
            }

            if ($healthContent -match '"pdo_mysql"\s*:\s*true') {
                Write-OK "PHP via Nginx has pdo_mysql enabled"
            }
            elseif ($healthContent -match '"pdo_mysql"\s*:\s*false') {
                Write-Warn "PHP via Nginx has pdo_mysql disabled"
            }

        }
        else {
            Write-Warn "PHP health endpoint returned HTTP $($healthResponse.StatusCode)"
        }

    }
    catch {

        Write-Fail "Nginx -> PHP health request failed"

        if ($_.Exception.Message) {
            Write-Info $_.Exception.Message
        }
    }

}
else {

    Write-Info "No $HealthPath found; Nginx -> PHP runtime test skipped"
    Write-Info "No health.php is required for normal development."
}
# ============================================================
# 16. PHP 语法检查
# ============================================================

Write-Section "16. PHP Syntax"

if (Test-Path $ProjectRoot -PathType Container) {

    $phpFiles = Get-ChildItem `
        -Path $ProjectRoot `
        -Recurse `
        -File `
        -Filter "*.php" `
        -ErrorAction SilentlyContinue |
        Where-Object {

            $fullPath = $_.FullName

            $excluded = $false

            foreach ($dir in $ExcludeDirs) {
                $pattern = [regex]::Escape(
                    [IO.Path]::DirectorySeparatorChar + $dir +
                    [IO.Path]::DirectorySeparatorChar
                )

                if ($fullPath -match $pattern) {
                    $excluded = $true
                    break
                }
            }

            -not $excluded
        }

    $phpFileCount = @($phpFiles).Count

    if ($phpFileCount -eq 0) {

        Write-Warn "No PHP files found"

    }
    else {

        Write-Info "PHP files to check: $phpFileCount"

        $syntaxFailed = 0
        $syntaxChecked = 0

        foreach ($file in $phpFiles) {

            $syntaxChecked++

            $result = & $PhpExe `
                -l `
                $file.FullName `
                2>&1

            if ($LASTEXITCODE -eq 0) {

                # 正常情况下不逐个输出，避免刷屏

            }
            else {

                $syntaxFailed++

                Write-Fail "PHP syntax error: $($file.FullName)"

                $result |
                    ForEach-Object {
                        Write-Info $_.ToString()
                    }
            }
        }

        if ($syntaxFailed -eq 0) {
            Write-OK "All $syntaxChecked PHP files passed syntax check"
        }
        else {
            Write-Fail "$syntaxFailed / $syntaxChecked PHP files failed syntax check"
        }
    }

}
else {
    Write-Fail "Project directory unavailable for PHP syntax check"
}

# ============================================================
# 17. Nginx 日志
# ============================================================

Write-Section "17. Nginx Logs"

$NginxLogs = @(
    (Join-Path $NginxDir "logs\error.log"),
    (Join-Path $NginxDir "logs\access.log")
)

foreach ($log in $NginxLogs) {

    if (Test-Path $log -PathType Leaf) {

        try {

            $item = Get-Item $log
            $sizeMB = [Math]::Round(
                $item.Length / 1MB,
                2
            )

            Write-OK "$($item.Name): $sizeMB MB"

        }
        catch {
            Write-Warn "Could not inspect log: $log"
        }

    }
    else {
        Write-Warn "Log does not exist: $log"
    }
}

# ============================================================
# 18. Git 状态
# ============================================================

Write-Section "18. Git Status"

if (Test-CommandExists "git") {

    try {

        Push-Location $ProjectRoot

        $status = & git status --short 2>&1

        if ($LASTEXITCODE -eq 0) {

            if (-not $status) {
                Write-OK "Git working tree clean"
            }
            else {

                Write-Info "Git working tree has changes:"

                $status |
                    Select-Object -First 30 |
                    ForEach-Object {
                        Write-Host "         $_"
                    }

                $statusCount = @($status).Count

                if ($statusCount -gt 30) {
                    Write-Info "... and more ($statusCount total entries)"
                }
            }

        }
        else {
            Write-Warn "Could not read Git status"
        }

        Pop-Location

    }
    catch {

        Pop-Location
        Write-Warn "Git status check failed"
    }
}

# ============================================================
# 19. 环境摘要
# ============================================================

Write-Section "19. Environment Summary"

Write-Info "Project root : $ProjectRoot"
Write-Info "PHP dir      : $PhpDir"
Write-Info "PHP ini      : $PhpIni"
Write-Info "PHP-CGI      : $PhpCgi"
Write-Info "xxfpm        : $XxfpmExe"
Write-Info "FastCGI      : ${FastCgiHost}:${FastCgiPort}"
Write-Info "Workers      : $ExpectedWorkers"
Write-Info "Nginx dir    : $NginxDir"
Write-Info "Base URL     : $BaseUrl"

# ============================================================
# 20. 最终结果
# ============================================================

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host " CHECK SUMMARY" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

Write-Host ""
Write-Host "  Passed   : $Passed" -ForegroundColor Green
Write-Host "  Failed   : $Failed" -ForegroundColor Red
Write-Host "  Warnings : $Warnings" -ForegroundColor Yellow

Write-Host ""

if ($Failed -eq 0 -and $Warnings -eq 0) {

    Write-Host "  RESULT: PASS" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Development environment looks healthy." -ForegroundColor Green

}
elseif ($Failed -eq 0) {

    Write-Host "  RESULT: PASS WITH WARNINGS" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  No critical failure detected, but some warnings need attention." -ForegroundColor Yellow

}
else {

    Write-Host "  RESULT: FAILED" -ForegroundColor Red
    Write-Host ""
    Write-Host "  One or more critical checks failed." -ForegroundColor Red
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor DarkGray
Write-Host ""