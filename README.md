# UserCrudApi

Proste API CRUD (Create, Read, Update, Delete) z wysyłką maili w Laravel.

---

## Wymagania

* **PHP:** Wersja 8.2 lub nowsza.
* **Composer:** Menedżer pakietów PHP.
* **SQLite:** Domyślna baza danych używana w tym projekcie (można łatwo zmienić na MySQL/PostgreSQL).

---

## Instalacja

    ```bash
    git clone https://github.com/MaciejSypulski/UserCrudApi.git
    cd UserCrudApi
    composer install
    ```
---

## Konfiguracja

    ```bash
    cp .env.example .env
    php artisan key:generate
    php artisan migrate
    ```
---

## Uruchamianie

```bash
php artisan serve
```
## Testowanie 

### PHPUnit

```bash
php artisan test
```

### Postman

Można użyć Postmana.

1. **Kolekcja Postman:**
    [Plik kolekcji JSON Postman](https://gist.github.com/MaciejSypulski/4c83e3d75e6859be050ee21e8f3a7a86)

2. **Dostępne Endpointy:**

    > POST /api/users
    - Tworzy nowego użytkownika wraz z jego adresami e-mail.
        
    - Body (JSON):
            
        ```JSON
        {
            "first_name": "Jan",
            "last_name": "Kowalski",
            "phone_number": "+48123456789",
            "emails": [
                { "email": "jan.kowalski@example.com" },
                { "email": "jan.k@innyadres.pl" }
            ]
        }
        ```

    > GET /api/users 

    - Pobiera listę wszystkich użytkowników.

    > GET /api/users/{id}

    - Pobiera szczegóły użytkownika o danym ID.
    - http://localhost:8000/api/users/1

    > PUT /api/users/{id}

    - Aktualizuje dane użytkownika i jego adresy e-mail. Maile niepodane w żądaniu zostaną usunięte. Aby zaktualizować istniejący adres, podaj jego id.
    - Body (JSON):
        ```JSON

        {
            "first_name": "Anna",
            "last_name": "Nowak",
            "phone_number": "+48987654321",
            "emails": [
                { "id": 1, "email": "anna.nowak.updated@example.com" },
                { "email": "nowy.mail@example.com" }
            ]
        }
        ```

    > DELETE /api/users/{id}
    - Usuwa użytkownika o danym ID wraz z jego adresami e-mail.
    - Przykład: http://localhost:8000/api/users/1

    > POST /api/users/{id}/send-welcome-email

    - Wysyła e-mail powitalny do użytkownika na wszystkie jego adresy e-mail. Zadania wysyłki trafiają do kolejki.
    - Przykład: http://localhost:8000/api/users/1/send-welcome-email

## Kolejka Maili

API używa kolejek do wysyłki e-maili. Uruchomienie w oddzielnej konsoli:

```Bash

php artisan queue:work

```
Aby maile wychodziły trzeba ustawić konfiguracje poczty w pliku .env 
(MAIL_MAILER, MAIL_HOST, MAIL_PORT,...itd).
