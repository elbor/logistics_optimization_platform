# Модель даних

Погоджено 2026-10-30, з правками після рев'ю schema-reviewer. Джерело для міграцій етапу 2; короткий огляд — `docs/SPEC.md`, розділ 6.

Ядро — маршрут з упорядкованими **зупинками-магазинами**; у кожній зупинці одне або кілька замовлень цього магазину, кожне зі своїм результатом доставки.

```
users ──< routes >── vehicles
            │
            └──< route_stops >── stores ──< orders
                     │                        │
                     └──< route_stop_orders >─┘
settings (один рядок)
```

Позначення: `NN` — NOT NULL. Усі `created_at` / `updated_at` — `timestamptz` (`timestampsTz()` у Laravel).

## Таблиці

```sql
users                      -- розширюємо стандартну таблицю Laravel
  id            bigint PK
  name          text NN
  email         text NN UNIQUE          -- вхід і в Filament, і в API; у нижньому регістрі при записі
  CHECK (email = lower(email))
  email_verified_at timestamptz NULL    -- стандартне поле Laravel
  phone         text NULL               -- E.164 (+380…, +44…), CHECK формату
  password      text NN                 -- хеш
  role          text NN                 -- CHECK in ('admin','driver'); без default
  is_active     boolean NN DEFAULT true
  remember_token, created_at, updated_at
  CHECK (role <> 'driver' OR phone IS NOT NULL)

vehicles
  id            bigint PK
  name          text NN
  plate         text NN UNIQUE
  CHECK (plate = upper(regexp_replace(plate, '\s', '', 'g')) AND plate <> '')
  fuel_consumption_l_per_100km  numeric(5,2) NN  CHECK (> 0 AND < 100)
  is_active     boolean NN DEFAULT true
  created_at, updated_at

stores
  id            bigint PK
  name          text NN
  address       text NN
  lat           double precision NN     CHECK (lat BETWEEN -90 AND 90)
  lng           double precision NN     CHECK (lng BETWEEN -180 AND 180)
  location_source text NN               -- CHECK in ('geocoder','manual')
  contact_name  text NULL
  contact_phone text NULL               -- E.164, CHECK формату
  comment       text NULL               -- CHECK (comment IS NULL OR btrim(comment) <> '')
  is_archived   boolean NN DEFAULT false
  external_id_1c text NULL UNIQUE       -- CHECK (external_id_1c <> '')
  created_at, updated_at

orders
  id            bigint PK
  store_id      bigint NN → stores ON DELETE RESTRICT       -- INDEX
  delivery_date date NN
  comment       text NULL
  status        text NN DEFAULT 'new'   -- CHECK in ('new','planned','delivered','cancelled')
  external_id_1c text NULL UNIQUE       -- CHECK (external_id_1c <> '')
  created_at, updated_at
  INDEX (delivery_date, status)

routes
  id            bigint PK
  date          date NN                 -- календарна дата за часовим поясом компанії
  driver_id     bigint NN → users ON DELETE RESTRICT        -- роль driver: політика + форма Filament
  vehicle_id    bigint NN → vehicles ON DELETE RESTRICT     -- INDEX
  return_to_warehouse  boolean NN
  status        text NN                 -- CHECK in ('draft','assigned','in_progress','completed')
  distance_m    integer NN CHECK (>= 0)
  drive_s       integer NN CHECK (>= 0)
  total_s       integer NN CHECK (total_s >= drive_s)
  return_leg_distance_m  integer NULL CHECK (>= 0)
  return_leg_s           integer NULL CHECK (>= 0)
  CHECK (return_to_warehouse = (return_leg_distance_m IS NOT NULL))
  CHECK (return_to_warehouse = (return_leg_s IS NOT NULL))
  fuel_l        numeric(7,2)  NN CHECK (>= 0)
  fuel_cost     numeric(10,2) NN CHECK (>= 0)
  fuel_price_snapshot        numeric(8,2) NN CHECK (> 0)
  fuel_consumption_snapshot  numeric(5,2) NN CHECK (> 0)
  stop_minutes_snapshot      smallint     NN CHECK (>= 0)
  geometry      jsonb NN CHECK (geometry->>'type' = 'LineString')   -- GeoJSON для карти
  started_at           timestamptz NULL   -- час «Почати маршрут» на телефоні
  started_received_at  timestamptz NULL   -- коли сервер отримав старт
  completed_at         timestamptz NULL   -- час «Завершити маршрут» на телефоні
  created_at, updated_at
  CHECK ((status IN ('in_progress','completed')) = (started_at IS NOT NULL))
  CHECK ((status IN ('in_progress','completed')) = (started_received_at IS NOT NULL))
  CHECK ((status = 'completed') = (completed_at IS NOT NULL))
  CHECK (completed_at >= started_at)
  INDEX (driver_id, date)               -- «Мої маршрути»
  INDEX (date)                          -- список маршрутів, звіти
  UNIQUE (driver_id)  WHERE status = 'in_progress'
  UNIQUE (vehicle_id) WHERE status = 'in_progress'
  TRIGGER BEFORE DELETE: помилка, якщо OLD.status NOT IN ('draft','assigned')

route_stops                -- зупинка = магазин
  id            bigint PK
  route_id      bigint NN → routes ON DELETE CASCADE
  store_id      bigint NN → stores ON DELETE RESTRICT      -- INDEX
  sequence      smallint NN CHECK (sequence >= 1)
  leg_distance_m    integer NN CHECK (>= 0)   -- відрізок ДО цієї зупинки
  leg_s             integer NN CHECK (>= 0)
  arrival_offset_s  integer NN CHECK (>= 0)   -- від старту маршруту, з урахуванням зупинок
  status        text NN DEFAULT 'pending'     -- CHECK in ('pending','completed','skipped')
  completed_at           timestamptz NULL     -- час закриття зупинки на телефоні (completed або skipped)
  completed_received_at  timestamptz NULL     -- коли сервер отримав закриття
  created_at, updated_at
  CHECK ((status <> 'pending') = (completed_at IS NOT NULL))
  CHECK ((status <> 'pending') = (completed_received_at IS NOT NULL))
  UNIQUE (route_id, store_id)
  UNIQUE (route_id, sequence)

route_stop_orders          -- замовлення в зупинці та його результат
  id            bigint PK
  route_stop_id bigint NN → route_stops ON DELETE CASCADE
  order_id      bigint NN → orders ON DELETE RESTRICT      -- INDEX
  status        text NN DEFAULT 'pending'     -- CHECK in ('pending','delivered','not_delivered')
  comment       text NULL                     -- необов'язкова причина відмови (при «Пропустити» — коментар зупинки в кожному замовленні)
  created_at, updated_at
  CHECK (comment IS NULL OR (status = 'not_delivered' AND btrim(comment) <> ''))
  UNIQUE (route_stop_id, order_id)
  UNIQUE (order_id) WHERE status <> 'not_delivered'

settings                   -- рівно один рядок; відсутність рядка = «не налаштовано»
  id            smallint PK DEFAULT 1 CHECK (id = 1)
  warehouse_address  text NN
  warehouse_lat      double precision NN CHECK (BETWEEN -90 AND 90)
  warehouse_lng      double precision NN CHECK (BETWEEN -180 AND 180)
  warehouse_location_source  text NN    -- CHECK in ('geocoder','manual')
  fuel_price    numeric(8,2) NN CHECK (> 0)
  default_stop_minutes  smallint NN DEFAULT 10 CHECK (BETWEEN 0 AND 240)
  created_at, updated_at
```

## Рішення

### Координати: lat/lng, не PostGIS

- `double precision` з CHECK діапазонів. У MVP немає просторових запитів (найближчий, радіус): координати лише зберігаються, ідуть в оптимізатор і на карту. `geography` у Laravel вимагає окремого пакета або сирого SQL.
- Шлях до PostGIS без переносу даних: `location geography GENERATED ALWAYS AS (ST_MakePoint(lng, lat)::geography) STORED` + GiST-індекс. Розширення в базі залишається.
- Координати NOT NULL: магазин без піна не можна поставити в маршрут, тому не можна й зберегти.
- `location_source` — щоб зміна адреси не перезаписала ручний пін.
- Геометрія маршруту — GeoJSON у `jsonb`: дані лише для показу, MapLibre читає їх напряму.

### Гроші, пальне, метрики

| Що | Тип | Чому |
| --- | --- | --- |
| Ціна пального, вартість | `numeric(8,2)` / `numeric(10,2)` | Точне десяткове, без float |
| Норма витрати | `numeric(5,2)` л/100 км | CHECK 0–100 |
| Літри | `numeric(7,2)` | Результат формули, округлений до 2 знаків |
| Відстань, час | `integer` метри / секунди | Одиниці оптимізатора, без втрат на округленні; км і хв — лише при показі |

- Знімки на момент побудови: ціна пального, норма витрати, час зупинки.
- Формули рахуються в PHP в одному сервісі (конструктор показує метрики до збереження); `decimal` у Laravel — рядки, рахуємо через `bcmath`, округлення half-up до 2 знаків.
- Загальний час = час у дорозі + кількість **зупинок-магазинів** × час зупинки.
- Валюта не зберігається: усі суми в гривнях (одна компанія).

### Зупинка = магазин

- Конструктор групує обрані замовлення за `store_id` (не за текстом адреси). В оптимізатор ідуть зупинки-магазини. Тому окремого обмеження `UNIQUE (store_id, delivery_date)` на `orders` немає: кілька замовлень одного магазину на дату — одна зупинка.
- `UNIQUE (route_id, store_id)` — магазин у маршруті один раз.
- `UNIQUE (route_id, sequence)` — звичайне, не DEFERRABLE (відкладене обмеження не працює з `ON CONFLICT` і не перевіряється в тестах з `RefreshDatabase`, бо транзакція не комітиться). Зміна порядку чи перебудова = видалити точки маршруту і вставити заново: метрики відрізків однаково перераховуються.

### Одне замовлення — в одному активному маршруті

`UNIQUE (order_id) WHERE status <> 'not_delivered'` на `route_stop_orders`. Недоставлене замовлення повертається в `new` і може потрапити в новий маршрут; у старому залишається історія з причиною.

### Результати доставки

- **«Виконано»**: зупинка `completed`, кожне замовлення отримує `delivered` або `not_delivered` (+ необов'язковий коментар).
- **«Пропустити»** (зачинено / не доїхав): зупинка `skipped`, усі її замовлення `not_delivered`.
- `not_delivered` → `orders.status = 'new'`, `delivered` → `orders.status = 'delivered'`, у тій самій транзакції.
- Обидві дії ідемпотентні: повтор для вже закритої зупинки нічого не змінює.
- `completed_at` / `completed_received_at` — час закриття зупинки, і для `completed`, і для `skipped`.

### Скасування маршруту = видалення

Статусу `cancelled` у маршрутів немає. Видалити можна лише `draft` / `assigned` (політика); точки й їхні замовлення видаляються каскадом, замовлення повертаються в `new` у тій самій транзакції. Застосунок водія обробляє зниклий маршрут як «маршрут скасовано».

### Правила транзакцій сервісу маршрутів

- Перебудова: спершу `DELETE` старих точок (каскадом — їхні `route_stop_orders`), потім вставка нових. Інакше `UNIQUE (order_id)` спрацює на старих рядках у `pending`.
- Каскадне видалення не викликає подій Eloquent: замовлення повертаються в `new` **до** `$route->delete()`, за заздалегідь зібраними id, в одному місці (сервіс), через яке йдуть і дії Filament.
- Гонка двох адмінів за те саме замовлення ловиться як `UniqueConstraintViolationException` у вкладеній транзакції (savepoint), щоб не зламати зовнішню.

### Захист історії

Тригер `BEFORE DELETE` на `routes` відмовляє, якщо статус не `draft` / `assigned`. Політика це теж перевіряє, але тригер закриває обхідні шляхи (масові дії, tinker, майбутні команди), на яких каскад стер би історію завершеного маршруту.

### Час з телефона

`started_at`, `completed_at` маршруту й `completed_at` зупинок — час натискання на телефоні (старт і закриття можуть статися офлайн, усі з одного годинника, тож `completed_at >= started_at` не зламається через розсинхрон із сервером). Поруч — `*_received_at`, час отримання сервером, для діагностики.

### Нормалізація введення

- `email` — у нижньому регістрі при записі (мутатор). Вхід Laravel/Sanctum/Filament шукає `where email = ?`, а це чутливе до регістру; CHECK гарантує, що в базі лише нижній регістр.
- Телефони — E.164 через `propaganistas/laravel-phone` (libphonenumber): номер з `+` не змінюється, місцевий формат розбирається як UA. `+380` навсліп не дописується (тести у Великій Британії з `+44`).
- Порожні рядки → NULL у формах та імпорті; CHECK `<> ''` на `external_id_1c` (інакше другий порожній рядок впаде на UNIQUE) і `btrim(...) <> ''` на коментарях.
- Номер машини — верхній регістр без пробілів, гарантовано CHECK.
- `role` без default: фабрика й сидер задають роль явно, адміна створює власна artisan-команда (замість `make:filament-user`).

### Статуси

`text` + іменований CHECK, у PHP — backed enum. Нативний enum Postgres не беремо (значення не видалити, Laravel не підтримує), `$table->enum()` не беремо (`varchar(255)` з автоматичною назвою обмеження). Статус і часові мітки пов'язані через CHECK.

### Ідентифікатори і час

- `bigint` PK: одна компанія, ID назовні не віддаються, доступ обмежують політики, офлайн застосунок нічого не створює.
- `date` / `delivery_date` — календарні дати за часовим поясом компанії; моменти часу — `timestamptz` (UTC). У базовій міграції `users` замінюємо `timestamps()` на `timestampsTz()` (продакшену ще немає, `migrate:fresh` безпечний).

### Реалізація в Laravel

- Blueprint не вміє іменовані CHECK, часткові унікальні індекси, тригери — це `DB::statement` у тій самій міграції, одразу після `Schema::create`.
- Порядок міграцій: зміна `users` → `vehicles`, `stores`, `settings` → `orders` → `routes` → `route_stops` → `route_stop_orders`; `down()` у зворотному порядку.
- `Settings`: `$incrementing = false`. Касти дат — `date:Y-m-d`; звіти по `timestamptz` — з `AT TIME ZONE`. Списки маршрутів не вибирають `geometry`.
- PostGIS вмикає `infra/postgres/init/01-postgis.sql`; у міграціях `CREATE EXTENSION` немає (PostGIS-колонок у MVP немає).
- Тести — на PostgreSQL, база `logistics_test` (`infra/postgres/init/02-test-db.sql`, `phpunit.xml`), не SQLite і не база розробки.

## Що база не гарантує (сервіс + тести)

- Маршрут призначено користувачу з роллю `driver` (політика + форма Filament).
- `orders.status` синхронізований з `route_stop_orders` (денормалізація заради фільтра в списку замовлень).
- Магазин замовлення = магазин зупинки; дата маршруту = `delivery_date` замовлень.
- Нумерація зупинок безперервна (1..n).
- Зупинку не можна закрити, поки в ній є замовлення в `pending`; `skipped` ⇒ усі замовлення `not_delivered`.
- Маршрут завершується лише без зупинок у `pending`.
- Скасувати замовлення можна лише в статусі `new` (видалення маршруту поза `draft` / `assigned` блокує тригер).
- Порожній маршрут (без зупинок) не створюється.
