# TASK.md — локальная разработка doctorofcosmetology + деплой через git

> Обновлено 2026-11-23 после уточнений: контент `/blog/` деплоится, PII не трогаем (known issue), репозиторий создан, локальная БД создана.
> Режим: architect. Ниже — план, команды и критерии приёмки.

---

## 1. Контекст (проверено)

### 1.1 Что реально в проекте

| Слой | Факт | Где |
|------|------|-----|
| Блог | **JSON-файлы** (50 постов) + 64 картинки, не MySQL | [`blog/index.php`](blog/index.php:2), [`blog/post.php`](blog/post.php:4), `blog/posts/`, `blog/images/` |
| Админка | отсутствует | — |
| MySQL | только `/budget/` (PDO singleton `getDB()`) | [`budget/config.php`](budget/config.php:13) |
| Услуги | один JSON в корне | [`uslugi.json`](uslugi.json:1) |
| Портфолио | JSON-кейсы + картинки | [`portfolio/cases/`](portfolio/cases/portfolio0001.json) |
| Вопросы клиентов | JSON с **PII** (имя/телефон/email) | [`q/process.php`](q/process.php:67), `q/questions/*.json` |
| ЧПУ блога | `/blog/00001/` → `post.php?id=` | [`blog/.htaccess`](blog/.htaccess:7) |
| ЧПУ портфолио | `/portfolio/0003/` → `case.php?id=` | [`portfolio/.htaccess`](portfolio/.htaccess:4) |

Это **не** Majesty из описания стека: здесь нет `blog/config.php`, нет `blog_posts`, нет `/admin`, услуги не разложены по `*/uslugi/*.json`. Majesty — отдельный проект в [`/Users/kovoron/Herd/majesty`](../majesty/WORKFLOW.md:1) на аккаунте `u3532113`; не смешивать.

### 1.2 Доступы к удалённой БД (`/budget/`)

[`budget/config.php`](budget/config.php:7):

| Константа | Значение |
|-----------|----------|
| `DB_HOST` | `localhost` (на сервере) |
| `DB_NAME` / `DB_USER` | `u3548935_default` |
| `DB_PASS` | секрет; из кода вынесен во внешний конфиг, в репозитории отсутствует |

Таблицы: `budget_users`, `transactions`, `expense_categories`, `income_categories`, `shopping_list`, `shopping_products`.

### 1.3 Прод, SSH и БД

- `doctorofcosmetology.ru` → `31.31.198.110` (reg.ru, nginx).
- Аккаунт по DB-юзеру — `u3548935`. В `~/.ssh/config` ключа для него нет; `ssh u3548935@31.31.198.110` → `Permission denied (publickey,password)`.
- Порт 3306 на проде **открыт**, но `mysql -h 31.31.198.110 -u u3548935_default` → `ERROR 1045 ... '@193.143.65.222'`: юзер разрешён только с `localhost`. Подтверждает: dump только по SSH.
- В истории есть попытка `ssh u3548935@doctorofcosmetology.ru` — аккаунт верный.

### 1.4 Git

- Локально `.git` нет, `deploy/` нет, `.gitignore` нет.
- `git@github.com:kovoron/doctorofcosmetology.git` существует, **пуст**, но **публичный** (`"private": false` по GitHub API).

### 1.5 Локальное окружение

- Herd: `http://doctorofcosmetology.test/` → 200; https не secured; `.htaccess`-rewrite **работает** (`/blog/00001/`, `/portfolio/portfolio0001/` → 200).
- PHP: глобальный 8.4.27, **8.3 установлен**; прод — 8.3.
- MySQL 8.0.31, `root@localhost` / `Megafon935`.
- **Создана локальная БД** `doctorofcosmetology_local` (utf8mb4 / `utf8mb4_unicode_ci`) и юзер `dc_local`@`localhost` с правами только на неё. Таблиц пока нет — их даст дамп с прода (шаг 2).

---

## 2. Решения и конфликты

### 2.1 Деплой контента `/blog/` (новое требование)

**Решение:** JSON-посты и картинки блога переходят под git и деплоятся вместе с кодом.

- В `.gitignore` **НЕ** попадают `blog/posts/*.json` и `blog/images/*` (≈876 КБ + 30 МБ).
- Источник истины для `/blog/` — локальная разработка. Правки блога напрямую на проде будут **перезаписаны** следующим деплоем. Это осознанное правило, зафиксировать в [`deploy/DEPLOY.md`](deploy/DEPLOY.md:1).
- Остальной контент вне scope: `portfolio/cases/`, `portfolio/images/`, `q/`, `uslugi.json` — не коммитим и не деплоим.
- Бэкап прод-версии `blog/` перед первым деплоем — обязателен (шаг 6).

### 2.2 PII доступны публично — не трогаем

Зафиксировано в разделе 6. Шаги 4 (запрет `q/questions/`) **из плана удалены** — по решению пользователя доработки будут отдельно. В `.gitignore` файлы `q/questions/` и `q/images/` остаются (их нельзя коммитить).

### 2.3 `budget/register.ppp` — удалить

Проверено на проде: `/budget/register.php` → **404**, `/budget/register.ppp` → **200** (отдаётся как файл), а [`budget/login.php`](budget/login.php:91) ссылается на `register.php`. То есть регистрация не работает ни локально, ни на проде — ничего рабочего не ломаем.

**Решение:** удалить [`budget/register.ppp`](budget/register.ppp:1) и убрать строку со ссылкой «Зарегистрироваться» из [`budget/login.php`](budget/login.php:91). Приложение приватное, открытая регистрация не нужна.

Альтернатива (если позже понадобится): переименовать в `register.php` и включить только на локальном окружении флагом из внешнего конфига.

### 2.4 Схема деплоя — S1 (простая, по SSH)

| Вариант | Плюсы | Минусы |
|---------|-------|--------|
| **S1. bare-репо + `post-receive` (~10 строк)** — рекомендую | одна команда `git push prod main`, откат по SHA, `.git` не в docroot, контент блога приезжает штатно | не удаляет файлы, убранные из репо |
| S2. `rsync` по SSH | полный контроль `--delete` | не привязан к коммиту |
| S3. `git pull` в docroot | без инфраструктуры | `.git` в docroot, правки на проде ломают pull |

Для «маленького проекта» **S1** — минимум инфраструктуры и максимум предсказуемости.

---

## 3. Чек-лист

> Порядок критичен: секреты выносятся (шаг 3) **до** первого `git commit`, иначе пароль БД попадёт в историю **публичного** GitHub-репозитория.

### Шаг 0. [Блокер] SSH-доступ `u3548935`
Файлы вне проекта: `~/.ssh/doctorofcosmetology_deploy`, `~/.ssh/config`.

1. `ssh-keygen -t ed25519 -f ~/.ssh/doctorofcosmetology_deploy -C "doctorofcosmetology-deploy-2026" -N ""`
2. Отдельный Host-блок (ключи проектов не пересекаются, чужие блоки не трогаем):
   ```
   Host doctorofcosmetology-prod
       HostName doctorofcosmetology.ru
       User u3548935
       IdentityFile ~/.ssh/doctorofcosmetology_deploy
       IdentitiesOnly yes
       StrictHostKeyChecking accept-new
   ```
3. Пользователь добавляет `doctorofcosmetology_deploy.pub` в ISPmanager аккаунта `u3548935`.
4. Проверка: `ssh doctorofcosmetology-prod "whoami; pwd; ls ~; ls ~/www"`.

Без шага 0 заблокированы: дамп БД (шаг 2), бэкап прод-блога и первый деплой (шаг 6–7).

### Шаг 1. Разведка прода (чтение)
```
ssh doctorofcosmetology-prod "ls -la ~/www; php -v | head -1; git --version; which mysqldump"
```
Зафиксировать точный `SITE_DIR` (ожидается `/var/www/u3548935/data/www/doctorofcosmetology.ru`).

### Шаг 2. Перенос БД прод → `doctorofcosmetology_local`
Файлы: `data/backups/db/` (gitignored). БД-контейнер уже создан, импорт — после шага 0.

1. Проверить состав БД (нет ли таблиц других приложений):
   ```
   ssh doctorofcosmetology-prod "mysql -h localhost -u u3548935_default -p'***' -e 'SHOW TABLES;' u3548935_default"
   ```
2. Дамп с gzip в `~/backups/db/` на проде (флаги `--single-transaction --quick --routines --no-tablespaces --default-character-set=utf8mb4`).
3. `scp doctorofcosmetology-prod:~/backups/db/doctorofcosmetology-*.sql.gz data/backups/db/`
4. Проверить дамп без импорта: `gunzip -c data/backups/db/*.sql.gz | head -40` (видно `CREATE TABLE budget_users` и др.).
5. `gunzip -c data/backups/db/*.sql.gz | mysql -udc_local -p'Megafon935' doctorofcosmetology_local`
6. Сверить count'ы по 6 таблицам с продом.

### Шаг 3. Вынос конфигов (до первого коммита)

**3.1 БД** — [`budget/config.php`](budget/config.php:1) (блок констант заменён загрузкой внешнего конфига; `session_start()` и функции остаются):
- определение окружения и загрузка конфигов вынесены в [`config/env.php`](config/env.php:1) (`siteIsProduction()`, `loadExternalConfig()`);
- локально: `/Users/kovoron/Herd/.doctorofcosmetology.local.php` — файл **вне проекта**;
- на проде: `/var/www/u3548935/data/.doctorofcosmetology.prod.php` — **вне docroot**, `chmod 600`;
- если `DB_*` не определены — `RuntimeException` с понятным текстом;
- **секретов в репозитории нет ни на одном этапе** (fallback с прод-паролем не используется).

Файлы:
- `/Users/kovoron/Herd/.doctorofcosmetology.local.php` — `DB_HOST=localhost`, `DB_NAME=doctorofcosmetology_local`, `DB_USER=dc_local`, пароль локальной БД, `SITE_URL=http://doctorofcosmetology.test`, `METRIKA_ENABLED=false`;
- `/var/www/u3548935/data/.doctorofcosmetology.prod.php` — боевые креды (создать до первого деплоя).

**3.2 Общие настройки** — новый [`config/site.php`](config/site.php:1): `SITE_URL`, `APP_ENV`, `METRIKA_ID=109925540`, `METRIKA_ENABLED=true` + подхват внешнего локального файла. Правки: [`sitemap.php`](sitemap.php:7) (домен из `SITE_URL`), [`footer.php`](footer.php:107) (счётчик и `<noscript>`-пиксель под `if (METRIKA_ENABLED)`).

**3.3 [`.gitignore`](.gitignore:1)** (создан до `git add`) — игнорируются `data/`, `*.sql*`, секреты, `q/questions/`, `q/images/`, `portfolio/cases/`, `portfolio/images/`, `uslugi.json`, мусор.
`blog/posts/` и `blog/images/` **не игнорируются** — они коммитятся и деплоятся.

### Шаг 4. Чистка бюджета
Файлы: [`budget/login.php`](budget/login.php:91) (убрать ссылку), [`budget/register.ppp`](budget/register.ppp:1) (удалить).
Проверка: `/budget/login.php` — 200, ссылки на регистрацию нет, `/budget/register.ppp` — 404 локально и на проде после деплоя.

### Шаг 5. Git: инициализация, ветки, первый коммит
1. `git init -b main` (после `.gitignore`) → `git add -A` → проверить `git status`: нет `q/`, нет `data/`, нет секретов.
2. `git commit -m "chore: prod snapshot 2026-11-15 (code + blog content)"`.
3. `git remote add origin git@github.com:kovoron/doctorofcosmetology.git`; `git branch develop`; `git push -u origin main`; `git push -u origin develop`.
4. Перед push решить вопрос публичности: **private** (рекомендуется) или public. Пароль БД к этому моменту уже вынесен из кода.

### Шаг 6. Прод: бэкап блога + деплой-инфраструктура S1
Файлы на сервере: `~/repo/doctorofcosmetology.git` (bare), `hooks/post-receive`.

1. Бэкап прод-контента блога (обязательно, т.к. деплой его перезапишет):
   `ssh doctorofcosmetology-prod "mkdir -p ~/backups/blog && tar -czf ~/backups/blog/blog-\$(date +%Y%m%d-%H%M%S).tar.gz -C <SITE_DIR>/blog ."`
2. `ssh doctorofcosmetology-prod "mkdir -p ~/repo && git init --bare ~/repo/doctorofcosmetology.git"`
3. `hooks/post-receive`:
   ```
   #!/bin/sh
   set -e
   SITE=/var/www/u3548935/data/www/doctorofcosmetology.ru
   BARE=/var/www/u3548935/data/repo/doctorofcosmetology.git
   mkdir -p ~/backups/deploy
   tar -czf ~/backups/deploy/code-$(date +%Y%m%d-%H%M%S).tar.gz -C "$SITE" --exclude='.git' .
   ls -1t ~/backups/deploy/code-*.tar.gz | tail -n +21 | xargs -r rm -f
   git --work-tree="$SITE" --git-dir="$BARE" checkout -f main
   echo "$(date '+%F %T') deployed $(git --git-dir="$BARE" rev-parse --short main)" >> ~/backups/deploy/deploy.log
   echo "DEPLOY_OK $(git --git-dir="$BARE" rev-parse --short main)"
   ```
4. Локально: `git remote add prod doctorofcosmetology-prod:/var/www/u3548935/data/repo/doctorofcosmetology.git`.
5. Создать `/var/www/u3548935/data/.doctorofcosmetology.prod.php` (`chmod 600`) **до** push.

### Шаг 7. Первый деплой и откат
1. `git checkout main && git push prod main` → `remote: DEPLOY_OK <sha>`.
2. Откат: `git push prod <prev-sha>:main`.
3. Ограничение S1: файлы, удалённые из репозитория, на проде не удаляются — чистить вручную (`ssh ... rm`), не задевая `q/`, `data/`.

### Шаг 8. Локальное окружение
1. `herd isolate 8.3` (прод PHP 8.3, сейчас 8.4.27).
2. `herd secure doctorofcosmetology.test`.
3. Проверить бюджет: вход существующим юзером, добавление операции, `/budget/api/*`.

### Шаг 9. Документация
[`deploy/DEPLOY.md`](deploy/DEPLOY.md:1): ветки (`develop` → `main` → `git push prod main`), что деплоится (код + `/blog/`), что живёт только на проде (`q/`, `portfolio/`, `uslugi.json`), правило «правки блога на проде перезаписываются», пути секретов, бэкапы, откат, ограничение S1.

---

## 4. Критерии приёмки

**БД**
- `mysql -udc_local -p'Megafon935' -N -e "SHOW TABLES;" doctorofcosmetology_local` → 6 таблиц budget.
- `SELECT COUNT(*)` по `budget_users`, `transactions`, `expense_categories`, `income_categories`, `shopping_list`, `shopping_products` совпадает с продом.
- Локально: вход в `/budget/`, добавление операции → запись в `transactions`.

**Конфиги и секреты**
- `git grep -n -E "define\('DB_PASS', '[^']+'"` → пусто (значения пароля в репозитории нет).
- `grep -rn "u3548935_default" --exclude-dir=.git --exclude-dir=data .` → пусто.
- На локальных страницах не грузится `mc.yandex.ru`; на проде счётчик работает.
- `ssh doctorofcosmetology-prod "ls -l /var/www/u3548935/data/.doctorofcosmetology.prod.php"` → `-rw-------`.

**Страницы локально** — 200: `/`, `/about/`, `/blog/`, `/blog/00001/`, `/portfolio/`, `/portfolio/portfolio0001/`, `/q/`, `/budget/login.php`; `/budget/register.ppp` → 404.

**Деплой контента `/blog/`** (главный критерий задачи)
- `git ls-files blog/ | wc -l` ≥ 50 + 64 (посты + картинки).
- После `git push prod main`: `curl -s https://doctorofcosmetology.ru/blog/00051/ | head` отдаёт локальный пост; `md5` любого изменённого локально поста и картинки совпадает с продом.
- В прод-логе деплоя есть `DEPLOY_OK`; `~/backups/blog/blog-*.tar.gz` создан до первого деплоя.

**Git**
- `git branch -vv` → `main` и `develop` с upstream `origin/*`; `git log --oneline` содержит коммит снапшота.
- `git show --stat HEAD` не содержит `q/`, `data/`, секретов.
- Откат: `git push prod <prev-sha>:main` → `DEPLOY_OK`, сайт 200.

---

## 5. Нужно от пользователя

1. Добавить `doctorofcosmetology_deploy.pub` в ISPmanager `u3548935` — разблокирует дамп БД, бэкап прод-блога и деплой.
2. Решить по GitHub-репозиторию: private (рекомендую) или оставить public.

---

## 6. Known issues (напомнить позже, сейчас НЕ трогаем)

1. **PII публично доступны**: `https://doctorofcosmetology.ru/q/questions/q00001.json` → 200 (имя, телефон, email), `q/images/*` тоже открыты. Нужен запрет прямого доступа к `q/questions/` и `q/images/` (`.htaccess` + проверка, что `/q/` продолжает работать). Пока не трогаем по решению пользователя.
2. `budget/register.ppp` отдавался как файл (исходник) — исправляется в шаге 4.
3. Регистрация в бюджете сломана на проде (`/budget/register.php` → 404) — закрываем удалением формы/ссылки.
4. GitHub-репозиторий публичный — если останется public, код и контент блога видны всем.
5. Пароль прод-БД был в коде (`budget/config.php`) и в переписке — после выноса конфигов **ротировать** и обновить `.doctorofcosmetology.prod.php`.
6. Счётчик Метрики `109925540` срабатывает на локальных страницах — отключается флагом `METRIKA_ENABLED` в шаге 3.2.
7. У блога нет админки: контент правится файлами; после перехода на git-деплой источник истины — репозиторий.
8. S1 не удаляет с прода файлы, убранные из репозитория — нужен ручной контроль.
