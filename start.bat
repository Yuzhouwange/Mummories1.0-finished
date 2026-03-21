@echo off
chcp 65001 >nul
setlocal
title Mummories

echo.
echo  ========================================
echo       Mummories - One Click Deploy
echo  ========================================
echo.

cd /d "%~dp0"

:: ========== 1. Docker ==========
echo [1/6] Checking Docker...
docker --version >nul 2>&1
if errorlevel 1 goto :NO_DOCKER
for /f "tokens=3" %%v in ('docker --version 2^>nul') do set DOCKER_VER=%%v
echo      Docker %DOCKER_VER% - OK
goto :CHECK_COMPOSE

:NO_DOCKER
echo.
echo  [X] Docker not found
echo      Install: https://docs.docker.com/desktop/install/windows-install/
echo.
pause
exit /b 1

:CHECK_COMPOSE
docker compose version >nul 2>&1
if errorlevel 1 goto :NO_COMPOSE
echo      Docker Compose - OK
goto :CHECK_DAEMON

:NO_COMPOSE
echo  [X] Docker Compose not found, please update Docker Desktop
pause
exit /b 1

:CHECK_DAEMON
docker info >nul 2>&1
if errorlevel 1 goto :NO_DAEMON
echo      Docker daemon - running
goto :CHECK_PORTS

:NO_DAEMON
echo.
echo  [X] Docker daemon not running
echo      Please start Docker Desktop and wait until it is ready
echo.
pause
exit /b 1

:: ========== 2. Ports ==========
:CHECK_PORTS
echo.
echo [2/6] Checking ports...
set HTTP_PORT=8080
set PMA_PORT=9888

if exist ".env" (
    for /f "tokens=1,2 delims==" %%a in (.env) do (
        if "%%a"=="HTTP_PORT" set HTTP_PORT=%%b
        if "%%a"=="PHPMYADMIN_PORT" set PMA_PORT=%%b
    )
)

netstat -ano | findstr ":%HTTP_PORT% " | findstr "LISTENING" >nul 2>&1
if not errorlevel 1 goto :PORT_BUSY
echo      Port %HTTP_PORT% - available
goto :CHECK_PMA_PORT

:PORT_BUSY
echo  [X] Port %HTTP_PORT% is in use
echo      Edit HTTP_PORT in .env or close the program using that port
pause
exit /b 1

:CHECK_PMA_PORT
netstat -ano | findstr ":%PMA_PORT% " | findstr "LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo  [~] Port %PMA_PORT% in use - phpMyAdmin may not start, blog unaffected
) else (
    echo      Port %PMA_PORT% - available
)

:: ========== 3. ENV ==========
echo.
echo [3/6] Checking config...

if exist ".env" goto :ENV_EXISTS
echo      .env not found, generating...

:: Generate .env with random passwords via PowerShell (avoids batch parsing issues)
powershell -NoProfile -Command ^
  "$pw = -join(1..16|%%{[char](Get-Random -InputObject (48..57+65..90+97..122))});" ^
  "$ak = -join(1..20|%%{[char](Get-Random -InputObject (48..57+65..90+97..122))});" ^
  "@('# Mummories env - auto generated'," ^
  "'DB_HOST=db','DB_NAME=mummories','DB_USER=root',\"DB_PASSWORD=$pw\"," ^
  "'APP_NAME=Mummories','APP_URL=http://localhost:8080'," ^
  "'HTTP_PORT=8080','PHPMYADMIN_PORT=9888',\"HOMEPAGE_API_KEY=$ak\"" ^
  ") | Set-Content -Path '.env' -Encoding UTF8"

if not exist ".env" goto :ENV_FAIL
echo      .env generated (random password)
goto :CHECK_CHAT_ENV

:ENV_FAIL
echo  [X] Failed to generate .env
pause
exit /b 1
echo      .env - exists

:CHECK_CHAT_ENV
:: Read main .env values via PowerShell

if exist "chat\.env" goto :CHAT_ENV_EXISTS
echo      chat\.env not found, generating...

powershell -NoProfile -Command ^
  "$m = (Get-Content '.env' | Where-Object {$_ -match '^DB_PASSWORD='}) -replace 'DB_PASSWORD=','';" ^
  "$k = (Get-Content '.env' | Where-Object {$_ -match '^HOMEPAGE_API_KEY='}) -replace 'HOMEPAGE_API_KEY=','';" ^
  "@('# Chat env - auto generated'," ^
  "'DB_HOST=db','DB_NAME=chat','DB_USER=root',\"DB_PASS=$m\",\"DB_PASSWORD=$m\"," ^
  "'APP_NAME=Mummories','APP_URL=http://localhost:8080/chat'," ^
  "'TRUSTED_PROXIES=',\"HOMEPAGE_API_KEY=$k\"" ^
  ") | Set-Content -Path 'chat\.env' -Encoding UTF8"

echo      chat\.env generated
goto :CHECK_FILES

:CHAT_ENV_EXISTS
echo      chat\.env - exists

:: ========== 4. Files ==========
:CHECK_FILES
echo.
echo [4/6] Checking project files...
set MISSING=0

if not exist "frontend\index.html"      (echo  [X] Missing frontend\index.html & set MISSING=1)
if not exist "backend\homepage_api.php"  (echo  [X] Missing backend\homepage_api.php & set MISSING=1)
if not exist "backend\Dockerfile"        (echo  [X] Missing backend\Dockerfile & set MISSING=1)
if not exist "nginx\default.conf"        (echo  [X] Missing nginx\default.conf & set MISSING=1)
if not exist "db\init.sql"               (echo  [X] Missing db\init.sql & set MISSING=1)
if not exist "docker-compose.yml"        (echo  [X] Missing docker-compose.yml & set MISSING=1)

if "%MISSING%"=="1" goto :FILES_MISSING
echo      All files OK
goto :BUILD

:FILES_MISSING
echo.
echo  [X] Project incomplete, please re-download
pause
exit /b 1

:BUILD
if not exist "backend\avatars" mkdir backend\avatars
if not exist "backend\uploads" mkdir backend\uploads

:: ========== 5. Build ==========
echo.
echo [5/6] Building and starting (first run takes 2-5 min)...
echo.

:: Try full deploy first; if PMA port is busy, retry without phpMyAdmin
docker compose up -d --build 2>"%TEMP%\mummories_build.log"
if errorlevel 1 (
    findstr /i "port is already allocated" "%TEMP%\mummories_build.log" >nul 2>&1
    if not errorlevel 1 (
        echo.
        echo  [~] phpMyAdmin port conflict, starting without it...
        docker compose up -d --build --no-deps db app nginx
        if errorlevel 1 goto :BUILD_FAIL
    ) else (
        type "%TEMP%\mummories_build.log"
        goto :BUILD_FAIL
    )
)
goto :HEALTH_CHECK

:BUILD_FAIL
echo.
echo  [X] Docker build failed, check errors above
pause
exit /b 1

:: ========== 6. Health check ==========
:HEALTH_CHECK
echo.
echo [6/6] Waiting for services...

:: Read DB password from .env
for /f "tokens=1,2 delims==" %%a in (.env) do (
    if "%%a"=="DB_PASSWORD" set "MAIN_DB_PWD=%%b"
)

set MAX_WAIT=60
set /a WAITED=0

:WAIT_LOOP
if %WAITED% geq %MAX_WAIT% goto :WAIT_TIMEOUT

docker compose exec -T db mysqladmin ping -h localhost -u root -p%MAIN_DB_PWD% >nul 2>&1
if not errorlevel 1 goto :DB_READY

set /a WAITED+=5
echo      Waiting for database... (%WAITED%s / %MAX_WAIT%s)
timeout /t 5 /nobreak >nul
goto :WAIT_LOOP

:WAIT_TIMEOUT
echo.
echo  [~] Timeout - services may still be starting
echo      Try visiting http://localhost:%HTTP_PORT% shortly
goto :SHOW_RESULT

:DB_READY
timeout /t 3 /nobreak >nul
echo      All services ready!

:SHOW_RESULT
for /f %%a in ('powershell -NoProfile -Command "(Get-Content '.env' | Where-Object {$_ -match '^HOMEPAGE_API_KEY='}) -replace 'HOMEPAGE_API_KEY=',''"') do set "FINAL_API_KEY=%%a"

echo.
echo  ========================================
echo       Deploy Complete!
echo  ========================================
echo.
echo   Blog:        http://localhost:%HTTP_PORT%
echo   Chat:        http://localhost:%HTTP_PORT%/chat
echo   Admin:       http://localhost:%HTTP_PORT%/admin
echo   phpMyAdmin:  http://localhost:%PMA_PORT%
echo.
echo   API Key:     %FINAL_API_KEY%
echo   (saved in .env)
echo.
echo  ----------------------------------------
echo   First time? Register at the blog page.
echo   Admin panel uses the API Key above.
echo  ----------------------------------------
echo.

choice /c YN /t 5 /d Y /m "Open browser? (Y/N, auto-open in 5s)"
if %errorlevel% equ 1 start http://localhost:%HTTP_PORT%

pause

