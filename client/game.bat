@echo off
curl -s -k -i -d "@%~2" -X POST %1/game.php
