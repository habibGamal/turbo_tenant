@echo off
php -d opcache.enable=0 artisan %*
