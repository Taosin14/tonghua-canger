@echo off
rem tonghua-canger one-click start
rem 1) kill leftover tunnel process to avoid URL conflict
taskkill /F /IM hsk-cli-windows-amd64-v0.7.23.exe >nul 2>&1
taskkill /F /IM node.exe >nul 2>&1
timeout /t 2 /nobreak >nul
rem 2) database
start "canger-mysql" "D:\xampp\mysql\bin\mysqld.exe" --defaults-file="D:\xampp\mysql\bin\my.ini" --console
timeout /t 4 /nobreak >nul
rem 3) php backend
start "canger-php" cmd /k "cd /d C:\Users\Lenovo\Desktop\Í¯»°²Ô¶ý\backend && D:\xampp\php\php.exe -S 127.0.0.1:8080 -t public public\router.php"
rem 4) vite frontend
start "canger-vite" cmd /k "cd /d C:\Users\Lenovo\Desktop\Í¯»°²Ô¶ý\frontend && npm run dev"
timeout /t 10 /nobreak >nul
rem 5) hsk tunnel (public URL is printed in this window; KEEP IT OPEN)
start "canger-hsk-¹«ÍøËíµÀ´°¿Ú" cmd /k "hsk-cli tunnel --ip 127.0.0.1 --port 5173 --detach --format json --context task_goal=expose_service,agent_channel=rich"
timeout /t 10 /nobreak >nul
start http://localhost:5173
echo.
echo All services started.
echo The public URL is printed in the window titled canger-hsk.
echo DO NOT close that window, otherwise the public link will change.
pause
