@echo off
cd /d C:\Users\HomePC\Desktop\rms
php artisan queue:work --tries=3 --timeout=60
