# Pełna Instrukcja Instalacji – System Zadań QR

Ten przewodnik zawiera kompletny zestaw instrukcji niezbędnych do uruchomienia aplikacji od zera na czystym systemie operacyjnym Ubuntu (20.04 lub 22.04 LTS) lub Debian.

---

## 1. Wymagania wstępne
Przed rozpoczęciem upewnij się, że posiadasz:
- Serwer VPS lub serwer dedykowany z zainstalowanym systemem Linux Ubuntu/Debian.
- Dostęp administratora root (przez `sudo`).
- Zarejestrowaną domenę lub stały adres IP serwera.

---

## 2. Instalacja środowiska (Apache, PHP, MySQL/MariaDB)

Wykonaj poniższe polecenia w terminalu swojego serwera (SSH):

```bash
# Aktualizacja systemu operacyjnego
sudo apt update && sudo apt upgrade -y

# Instalacja serwera WWW Apache
sudo apt install apache2 -y

# Włączenie modułu rewrite w Apache i restart usługi
sudo a2enmod rewrite
sudo systemctl restart apache2

# Instalacja interpretera PHP z wymaganymi bibliotekami
sudo apt install php libapache2-mod-php php-mysql php-mbstring php-curl php-gd -y

# Instalacja silnika bazy danych MariaDB (zastępstwo dla MySQL)
sudo apt install mariadb-server -y

# Zabezpieczenie instalacji bazy danych
sudo mysql_secure_installation
```
*Podczas uruchamiania `mysql_secure_installation` postępuj zgodnie ze wskazówkami: ustaw bezpieczne hasło roota, usuń anonimowych użytkowników, zablokuj zdalne logowanie roota oraz usuń testową bazę danych.*

---

## 3. Instalacja menedżera Composer

Composer służy do pobrania i instalacji biblioteki PHPMailer:

```bash
# Pobranie instalatora i instalacja globalna
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Sprawdzenie wersji
composer --version
```

---

## 4. Konfiguracja Bazy Danych w MySQL/MariaDB

Zaloguj się do bazy danych:
```bash
sudo mysql -u root -p
```

Wykonaj kolejno poniższe polecenia (zmień `SILNE_HASLO_BAZY` na własne bezpieczne hasło):
```sql
CREATE DATABASE tasklist CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'tasklist_user'@'localhost' IDENTIFIED BY 'SILNE_HASLO_BAZY';

GRANT ALL PRIVILEGES ON tasklist.* TO 'tasklist_user'@'localhost';

FLUSH PRIVILEGES;

EXIT;
```

---

## 5. Pobranie i wdrożenie plików aplikacji

```bash
# Tworzenie katalogu aplikacji
sudo mkdir -p /var/www/html/tasklist

# Ustawienie uprawnień własnościowych dla Apache (www-data)
sudo chown -R www-data:www-data /var/www/html/tasklist
sudo chmod -R 755 /var/www/html/tasklist
```

Umieść wszystkie pliki kodu źródłowego systemu w katalogu `/var/www/html/tasklist/`. Następnie zainstaluj bibliotekę PHPMailer z poziomu użytkownika serwera www:

```bash
cd /var/www/html/tasklist
sudo -u www-data composer require phpmailer/phpmailer
```

---

## 6. Konfiguracja pliku `config.php`

Skopiuj przykładowy plik konfiguracyjny (jeśli istnieje) lub edytuj bezpośrednio:
```bash
sudo nano /var/www/html/tasklist/config.php
```

Wprowadź prawidłowe parametry:
- `DB_PASS`: Hasło ustawione w Kroku 4.
- `APP_URL`: Pełny adres URL do aplikacji bez ukośnika `/` na końcu (np. `http://192.168.1.100/tasklist` lub `https://twojadomena.pl/tasklist`).
- `ADMIN_PASS` i `MANAGER_PASS`: Hasła dostępowe odpowiednio dla Admina i Kierownika.
- Sekcja `SMTP`: Wprowadź adres serwera pocztowego, port, login i hasło nadawcy. Skonfiguruj też adres e-mail odbiorcy w stałej `REPORT_TO`.

---

## 7. Inicjalizacja bazy danych (tabele systemowe)

Wejdź w przeglądarce pod adres:
```
http://TWOJ_ADRES_SERWERA/tasklist/setup.php
```
Strona automatycznie wygeneruje tabele oraz połączy je odpowiednimi kluczami obcymi.

---

## 8. Konfiguracja harmonogramu zadań (Cron)

Edytuj zadania crontab dla użytkownika Apache, aby system resetował zadania w nocy i wysyłał raporty:

```bash
sudo crontab -u www-data -e
```

Wklej na końcu poniższe wiersze:
```cron
# Reset statusów zadań na kolejny dzień o 00:01
1 0 * * * php /var/www/html/tasklist/cron_reset.php >> /var/log/tasklist.log 2>&1

# Wysyłanie dziennego raportu e-mail o 23:00
0 23 * * * php /var/www/html/tasklist/report.php >> /var/log/tasklist_report.log 2>&1
```

---

## 9. Usunięcie plików instalacyjnych (Bezpieczeństwo)

Usuń skrypty, które mogłyby posłużyć do ponownego zresetowania bazy danych przez osoby nieuprawnione:

```bash
sudo rm /var/www/html/tasklist/setup.php
sudo rm -f /var/www/html/tasklist/migrate.php
```

System jest gotowy do użytku!
