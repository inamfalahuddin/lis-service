#!/bin/bash

# Script ini menggunakan variabel lingkungan:
# DB_USERNAME, DB_PASSWORD, dan DB_DATABASE (dari .env Anda)

# Menjalankan perintah SQL untuk membuat database pengujian.
# Variabel -u, -p, dan database diisi oleh Docker/Bash.

mysql --user="${DB_USERNAME}" --password="${DB_PASSWORD}" --execute="CREATE DATABASE IF NOT EXISTS ${DB_DATABASE}_test;"