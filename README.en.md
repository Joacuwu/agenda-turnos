# Online appointment booking

🇦🇷 [Leer en español](README.md)

Appointment booking for service businesses (the demo is a barbershop), built in **framework-free PHP** (compatible with PHP 5.6 to 8.x), **MySQL**, **Bootstrap 5**, **jQuery** and **Ajax**. The UI and code identifiers are in Spanish, since it targets Argentine businesses.

## Features
**For customers**
- Booking on a single screen: service, staff member (or "no preference"), day and time. Free slots load via Ajax whenever an option changes, without reloading.
- Only **truly free** slots are offered: within business hours, not overlapping other appointments, and at least 1 hour ahead.
- On confirmation they get a 6-character **booking code**.
- **My appointment:** with the code and phone number they can view the appointment and cancel it themselves, up to 2 hours before.

**For the business (panel with login)**
- Daily schedule grouped by staff member, with day-by-day navigation.
- Status changes via Ajax: booked, attended, no-show or cancelled.
- Daily summary: appointments, cancellations, no-shows and estimated revenue.
- Direct WhatsApp link to each customer.

## How free slots are computed
Each staff member has **weekly working blocks** (the `horarios` table; morning and afternoon blocks are allowed). For a service and a day, `horarios_disponibles()`:
1. Walks each block in 30-minute steps (`SLOT_MINUTOS`).
2. Drops start times where the service **would not finish inside the block** (a 90-minute color cannot start at 12:00 if the block closes at 13:00).
3. Drops slots that **overlap** a non-cancelled appointment. Two ranges overlap when each starts before the other ends: `start_a < end_b AND end_a > start_b`.
4. Drops slots that break the minimum notice.
5. With "no preference", each time is assigned to the first free staff member.

## Design decisions
| Decision | Why |
|---|---|
| Availability is recomputed when booking, inside a transaction with the staff member's row locked | If two people pick the same slot at once, only one gets it; the other sees "That time is no longer available" and the list refreshes itself. |
| Each appointment stores `start` and `end` | Changing a service's duration later does not alter existing appointments, and overlap checks become a simple comparison. |
| 6-character code without `0/O` or `1/I` | Easy to read over the phone and hard to guess (32⁶ ≈ one billion combinations). |
| Looking up or cancelling requires the code **and** the phone, with an attempt limit | With the code alone, someone could try random codes and cancel other people's appointments. |
| Phone numbers stored as digits only | Customers can type them any way (`11 5555-1234`, `(11) 5555 1234`) and lookups still work. |
| Rules as constants (`SLOT_MINUTOS`, `MINUTOS_ANTICIPACION`, `DIAS_MAXIMOS`, `HORAS_CANCELACION`) | Each business tunes them in `includes/config.php` without touching the logic. |

## Security
| Risk | How it is handled |
|---|---|
| SQL injection | Every query is a PDO prepared statement. |
| XSS | All output goes through `htmlspecialchars`; JavaScript builds the slot buttons with `.text()`. |
| CSRF | Per-session token on forms and on every Ajax request. |
| Mass bookings | At most 3 bookings per hour from the same session. |
| Panel | Login with `password_hash`, session regeneration and lockout after 5 attempts; panel endpoints return a JSON 401 without a session. |
| Internal files | `.htaccess` denies `includes/`, `data/`, `sql/` and `tests/`. |

## Documented use cases
| # | Case | Expected result |
|---|---|---|
| 1 | Pick Color (90 min) with Lucas on a Monday | Last morning slot offered is 11:30 and last afternoon slot is 17:30. |
| 2 | Pick "no preference" on a Tuesday | Slots run from 9:00 to 18:30 with no gaps; Martina covers midday. |
| 3 | Two people book the same slot | The first gets a code; the second sees "That time is no longer available" and an updated list. |
| 4 | Book a 45-minute service at 9:30 with an appointment at 10:00 | 9:30 is not offered; 9:00 and 10:30 are. |
| 5 | Cancel from "My appointment" 1 h 30 min ahead | Not cancelled; asks to message on WhatsApp. |
| 6 | Cancel 2 hours or more ahead | Cancelled, and the slot becomes free again. |
| 7 | Look up the appointment with the phone typed differently | Still found. |
| 8 | The business marks "attended" in the panel | Updates without reloading; the daily revenue reflects it on the next load. |

Cases 1 to 7 are automated in `tests/pruebas.php` (26 tests), using a fixed date so results do not depend on the day they run.

## Structure
```
index.php            Booking (public)
mi-turno.php         Look up and cancel (public)
agenda.php           Daily schedule (panel)
login.php / logout.php
api/horarios.php     Free slots (JSON)
api/reservar.php     Confirm booking (JSON)
api/estado.php       Change appointment status (JSON, panel)
includes/
  config.php         Business details, scheduling rules and database
  db.php             PDO connection (MySQL, or SQLite in demo mode)
  funciones.php      Session, CSRF, JSON, flash messages
  turnos.php         Availability, bookings, cancellations and statuses
  layout.php         Shared header and footer
assets/js/           reserva.js, panel.js, general.js
sql/schema.sql       MySQL tables and seed data
sql/crear_usuario.php  Create panel users from the command line
tests/pruebas.php    Dependency-free tests
```

## Running it
**Local (demo mode, no MySQL needed):**
```bash
php -S localhost:8000
```
Booking at `http://localhost:8000`, panel at `http://localhost:8000/agenda.php` with `admin` / `admin1234`. It creates `data/demo.sqlite` with sample appointments for the coming days.

**Tests:**
```bash
php tests/pruebas.php
```

**Server with MySQL (e.g. InfinityFree):**
1. Run `sql/schema.sql` in phpMyAdmin and adjust staff, services and hours.
2. Copy `includes/config.local.ejemplo.php` to `includes/config.local.php` and fill it in.
3. Create the panel user: `php sql/crear_usuario.php admin <password> "Name"`.
4. Set the business name, address and WhatsApp in `includes/config.php` and upload the files to `htdocs/`.

Requires PHP 5.6+ with PDO (`pdo_mysql`, or `pdo_sqlite` for demo mode).

## Screenshots
| Booking | Confirmation | Daily schedule |
|---|---|---|
| ![](docs/capturas/1-reserva.png) | ![](docs/capturas/2-confirmacion.png) | ![](docs/capturas/3-agenda.png) |
