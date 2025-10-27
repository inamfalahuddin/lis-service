@echo off
REM ==============================================
REM Script: connect-db-tunnel.bat
REM Deskripsi: Membuat SSH tunnel ke MySQL server
REM untuk digunakan Laravel (di Laragon)
REM ==============================================

SET SSH_USER=root
SET SSH_HOST=192.168.2.10
SET REMOTE_DB_HOST=127.0.0.1
SET REMOTE_DB_PORT=3306
SET LOCAL_TUNNEL_PORT=3067

ECHO 🔐 Membuat SSH tunnel ke %SSH_HOST% ...
ECHO (localhost:%LOCAL_TUNNEL_PORT% -> %REMOTE_DB_HOST%:%REMOTE_DB_PORT%)

REM Jalankan tunnel di background
start "SSH Tunnel" cmd /c ssh -N -L %LOCAL_TUNNEL_PORT%:%REMOTE_DB_HOST%:%REMOTE_DB_PORT% %SSH_USER%@%SSH_HOST%

timeout /t 2 >nul
ECHO ✅ Tunnel aktif! Laravel bisa akses DB via 127.0.0.1:%LOCAL_TUNNEL_PORT%
ECHO.
ECHO Tekan [Ctrl + C] di jendela tunnel untuk menutup koneksi.
ECHO ==============================================

pause
