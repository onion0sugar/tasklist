# System Zadań QR

Lekki system do zarządzania codziennymi zadaniami z wykorzystaniem kodów QR. Każde zadanie ma unikalny kod QR, który po zeskanowaniu telefonem oznacza wykonanie zadania. Idealny do obiegów porządkowych, list kontrolnych, codziennych obowiązków w firmie, lokalu gastronomicznym, magazynie itp.

## Funkcje

- Lista zadań z generowanymi kodami QR
- Oznaczanie wykonania przez skan QR (bez logowania)
- Panel administratora do zarządzania zadaniami
- Automatyczny reset statusów każdej nocy (cron)
- Codzienny raport e-mail z wykonanych zadań (SMTP / PHPMailer)
- Podgląd logów wykonań
- Wydruk listy zadań z kodami QR (PDF)

## Wymagania

- Linux (Ubuntu 20.04/22.04, Debian 11/12)
- Apache 2.4+
- PHP 7.4+ (`php-mysql`, `php-mbstring`)
- MariaDB 10.4+ / MySQL 8.0+
- Composer (PHPMailer)

## Instalacja

Pełna instrukcja krok po kroku znajduje się w [INSTALACJA.md](INSTALACJA.md).

Skrót:

```bash
# 1. Wgraj pliki do /var/www/html/tasklist
# 2. Zainstaluj PHPMailer
composer require phpmailer/phpmailer

# 3. Skonfiguruj config.php (baza, SMTP, hasła)
# 4. Utwórz tabele
#    → http://ADRES/tasklist/setup.php
# 5. Dodaj zadania w panelu admina
#    → http://ADRES/tasklist/
```

## Struktura

```
tasklist/
├── config.php       konfiguracja (DB, SMTP, hasła)
├── index.php        lista zadań z QR
├── admin.php        zarządzanie zadaniami
├── scan.php         publiczny endpoint skanu QR
├── login.php        logowanie admina
├── logs.php         podgląd logów
├── print.php        wydruk PDF
├── report.php       raport e-mail (cron)
├── cron_reset.php   reset dzienny (cron)
└── setup.php        tworzenie tabel (usuń po instalacji)
```

## Bezpieczeństwo

- Po instalacji usuń `setup.php` i `migrate.php`
- Ustaw silne hasła dla `ADMIN_PASS` i bazy danych
- Dla Gmail SMTP użyj „hasła do aplikacji”, nie hasła konta
- Plik `config.php` nie powinien być commitowany — wzorcuj na `config.example.php`

## Licencja

MIT
