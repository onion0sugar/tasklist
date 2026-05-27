# Instrukcja instalacji – System Zadań QR

## Wymagania

- Linux (Ubuntu 20.04 / 22.04 lub Debian 11/12)
- Apache 2.4+
- PHP 7.4+ z rozszerzeniami: `php-mysql`, `php-mbstring`
- MariaDB 10.4+ lub MySQL 8.0+
- Composer (do instalacji PHPMailer)
- Dostęp SSH do serwera

---

## 1. Instalacja Apache, PHP, MariaDB

```bash
sudo apt update && sudo apt upgrade -y

# Apache
sudo apt install apache2 -y

# PHP + moduł Apache + rozszerzenia
sudo apt install php libapache2-mod-php php-mysql php-mbstring -y

# MariaDB
sudo apt install mariadb-server -y

# Zabezpieczenie MariaDB (ustaw hasło root, usuń testowe dane)
sudo mysql_secure_installation
```

Sprawdź czy wszystko działa:

```bash
sudo systemctl status apache2
sudo systemctl status mariadb
php -v
```

---

## 2. Instalacja Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## 3. Tworzenie bazy danych MySQL

Zaloguj się do MariaDB jako root:

```bash
sudo mysql -u root -p
```

Wykonaj poniższe komendy:

```sql
-- Utwórz bazę danych
CREATE DATABASE tasklist CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Utwórz użytkownika z dostępem lokalnym
CREATE USER 'tasklist_user'@'localhost' IDENTIFIED BY 'TWOJE_HASLO_DB';

-- Nadaj uprawnienia
GRANT ALL ON tasklist.* TO 'tasklist_user'@'localhost';

-- Przeładuj uprawnienia
FLUSH PRIVILEGES;

-- Sprawdź
SELECT user, host FROM mysql.user WHERE user = 'tasklist_user';

EXIT;
```

> Jeśli potrzebujesz dostępu zdalnego do bazy (np. z zewnętrznego narzędzia):
> ```sql
> CREATE USER 'tasklist_user'@'%' IDENTIFIED BY 'TWOJE_HASLO_DB';
> GRANT ALL ON tasklist.* TO 'tasklist_user'@'%';
> FLUSH PRIVILEGES;
> ```
> Dodatkowo odblokuj port w firewallu: `sudo ufw allow 3306/tcp`
> Oraz zmień `bind-address = 0.0.0.0` w `/etc/mysql/mariadb.conf.d/50-server.cnf`

---

## 4. Wgranie plików aplikacji

```bash
# Utwórz katalog aplikacji
sudo mkdir /var/www/html/tasklist

# Nadaj uprawnienia
sudo chown -R www-data:www-data /var/www/html/tasklist
sudo chmod -R 755 /var/www/html/tasklist
```

Wgraj wszystkie pliki projektu do `/var/www/html/tasklist/`.

Następnie zainstaluj PHPMailer przez Composer:

```bash
cd /var/www/html/tasklist
sudo composer require phpmailer/phpmailer
sudo chown -R www-data:www-data /var/www/html/tasklist/vendor
```

---

## 5. Konfiguracja aplikacji

Edytuj plik `config.php`:

```bash
sudo nano /var/www/html/tasklist/config.php
```

Uzupełnij:

```php
define('DB_PASS',   'TWOJE_HASLO_DB');          // hasło do bazy (z kroku 3)
define('APP_URL',   'http://ADRES_IP/tasklist'); // adres serwera

define('ADMIN_PASS', 'HASLO_PANELU');            // hasło do panelu admina

define('SMTP_HOST',       'smtp.gmail.com');     // serwer SMTP
define('SMTP_PORT',       587);                  // port: 587 (TLS) lub 465 (SSL)
define('SMTP_ENCRYPTION', 'tls');                // 'tls' lub 'ssl'
define('SMTP_USER',       'twoj@gmail.com');     // login skrzynki
define('SMTP_PASS',       'haslo_aplikacji');    // hasło skrzynki (Gmail: hasło do aplikacji)
define('SMTP_FROM_NAME',  'System Zadań');
define('REPORT_TO',       'odbiorca@example.com'); // adres docelowy raportu
```

> **Gmail:** włącz weryfikację dwuetapową, wygeneruj "Hasło do aplikacji" w ustawieniach
> konta Google. Użyj go jako `SMTP_PASS` — nie używaj hasła do konta Google.

---

## 6. Tworzenie tabel w bazie

Wejdź w przeglądarce na:

```
http://ADRES_IP/tasklist/setup.php
```

Powinien pojawić się komunikat: **"Tabele utworzone."**

---

## 7. Konfiguracja cron – reset zadań i raport e-mail

```bash
sudo crontab -u www-data -e
```

Dodaj linie:

```
# Reset zadań każdej nocy o 00:01
1 0 * * * php /var/www/html/tasklist/cron_reset.php >> /var/log/tasklist.log 2>&1

# Raport e-mail codziennie o 23:00
0 23 * * * php /var/www/html/tasklist/report.php >> /var/log/tasklist_report.log 2>&1
```

> Jeśli wolisz raport rano za poprzedni dzień, ustaw cron na `5 0 * * *`
> i w pliku `report.php` zmień linię z datą na:
> `$date = date('Y-m-d', strtotime('yesterday'));`

---

## 8. Porządki po instalacji

Po zakończeniu konfiguracji usuń zbędne pliki:

```bash
sudo rm /var/www/html/tasklist/setup.php
sudo rm /var/www/html/tasklist/migrate.php   # tylko jeśli istnieje
```

---

## 9. Weryfikacja

| URL | Opis |
|-----|------|
| `http://ADRES_IP/tasklist/` | Panel admina (wymaga logowania) |
| `http://ADRES_IP/tasklist/scan.php?task_id=1` | Przykładowy skan (publiczny) |

Ręczny test wysyłki raportu:

```bash
php /var/www/html/tasklist/report.php
```

Ręczny reset zadań:

```bash
php /var/www/html/tasklist/cron_reset.php
```

---

## Struktura plików

```
tasklist/
├── config.php          ← konfiguracja (DB, SMTP, hasła)
├── login.php           ← logowanie admina
├── logout.php          ← wylogowanie
├── index.php           ← lista zadań z kodami QR
├── admin.php           ← zarządzanie zadaniami
├── scan.php            ← publiczny – obsługa skanowania QR
├── logs.php            ← podgląd logów
├── print.php           ← wydruk / zapis do PDF
├── report.php          ← wysyłka raportu e-mail (cron)
├── cron_reset.php      ← reset dzienny (cron)
├── setup.php           ← tworzenie tabel (usuń po instalacji)
├── .htaccess           ← zabezpieczenia Apache
└── vendor/             ← PHPMailer (Composer)
```

---

## Reset do czystej instalacji (usunięcie danych)

```sql
sudo mysql -u root -p tasklist

DROP TABLE IF EXISTS logs;
DROP TABLE IF EXISTS daily_tasks;
DROP TABLE IF EXISTS tasks;
EXIT;
```

Następnie uruchom ponownie `setup.php`.
