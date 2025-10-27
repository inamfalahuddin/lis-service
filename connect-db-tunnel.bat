@echo off
REM ==============================================
REM Script: connect-db-tunnel-hidden.bat
REM Deskripsi: Membuat SSH tunnel ke MySQL server
REM berjalan sepenuhnya di background
REM ==============================================

SET SSH_USER=rsababelan
SET SSH_HOST=192.168.2.10
SET REMOTE_DB_HOST=127.0.0.1
SET REMOTE_DB_PORT=3306
SET LOCAL_TUNNEL_PORT=3067

ECHO 🔐 Membuat SSH tunnel ke %SSH_HOST% ...
ECHO (localhost:%LOCAL_TUNNEL_PORT% -> %REMOTE_DB_HOST%:%REMOTE_DB_PORT%)

REM Jalankan SSH tunnel tanpa jendela (background)
powershell -WindowStyle Hidden -Command ^
    "Start-Process 'ssh' '-N -L %LOCAL_TUNNEL_PORT%:%REMOTE_DB_HOST%:%REMOTE_DB_PORT% %SSH_USER%@%SSH_HOST%' -WindowStyle Hidden"

timeout /t 2 >nul
ECHO ✅ Tunnel aktif secara background tanpa jendela.
ECHO Laravel bisa akses DB via 127.0.0.1:%LOCAL_TUNNEL_PORT%
ECHO ==============================================

pause
