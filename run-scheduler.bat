@echo off
cd /d C:\Users\HomePC\Desktop\rms
php artisan schedule:run >> NUL 2>&1
