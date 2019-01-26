@echo off
:loop
curl -s -k -d "@%~2" -X POST %1/upload.php
timeout /t 3 /nobreak > NUL
goto loop