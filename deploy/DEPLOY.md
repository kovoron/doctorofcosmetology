# Деплой doctorofcosmetology

## Модель веток

| Ветка | Назначение |
|-------|-----------|
| `develop` | разработка и локальные проверки |
| `main` | прод-состояние; `git push prod main` = деплой |

`origin` — GitHub (`kovoron/doctorofcosmetology`), `prod` — bare-репозиторий на хостинге.

## Обычный цикл

```bash
git add -A && git commit -m "feat: ..."
git push origin develop

# когда проверено локально
git checkout main && git merge --ff-only develop
git push origin main && git push prod main
git checkout develop
```

В конце push видно `remote: DEPLOY_OK <sha>`.

## Как устроен деплой (вариант S1)

На сервере: `/var/www/u3548935/data/repo/doctorofcosmetology.git` (bare) и хук `hooks/post-receive` ([`post-receive`](post-receive:1)).
Хук делает tar-бэкап текущего кода в `~/backups/deploy/` (ротация 20 файлов) и `git --work-tree=<docroot> checkout -f main`.

## Что деплоится, а что нет

**Деплоится:** весь код (`*.php`, `*.css`, `*.js`, `.htaccess`, `*.html`, `*.txt`, `*.ico`), дизайн-картинки `images/`, а также **контент блога**: `blog/posts/*.json` и `blog/images/*`.

**Не коммитится и не деплоится (живёт только на проде):**
- `q/questions/`, `q/images/` — персональные данные клиентов;
- `portfolio/cases/`, `portfolio/images/`, `uslugi.json` — контент вне git;
- `data/` — локальные бэкапы и дампы.

**Источник истины для `/blog/` — репозиторий.** Правки блога напрямую на проде будут перезаписаны следующим деплоем.

## Секреты

В репозитории секретов нет. Конфиги читаются из файлов вне docroot:

| Окружение | Файл |
|-----------|------|
| локально | `/Users/kovoron/Herd/.doctorofcosmetology.local.php` (вне проекта) |
| прод | `/var/www/u3548935/data/.doctorofcosmetology.prod.php` (`chmod 600`) |

Шаблон прод-файла — [`prod-config.example.php`](prod-config.example.php:1). Загрузка — [`config/env.php`](../config/env.php:1).

**Порядок первого деплоя:** сначала создать и проверить прод-файл секретов, только потом `git push prod main` (иначе `/budget/` упадёт с `RuntimeException`).

## Первая настройка прода (один раз)

```bash
# 1) бэкап прод-контента блога (деплой его перезапишет)
ssh doctorofcosmetology-prod "mkdir -p ~/backups/blog && tar -czf ~/backups/blog/blog-\$(date +%Y%m%d-%H%M%S).tar.gz -C /var/www/u3548935/data/www/doctorofcosmetology.ru/blog ."

# 2) секреты вне docroot
ssh doctorofcosmetology-prod "cp /dev/null /var/www/u3548935/data/.doctorofcosmetology.prod.php && chmod 600 /var/www/u3548935/data/.doctorofcosmetology.prod.php"
# заполнить файл по шаблону deploy/prod-config.example.php

# 3) bare-репозиторий и хук
ssh doctorofcosmetology-prod "mkdir -p ~/repo && git init --bare ~/repo/doctorofcosmetology.git"
scp deploy/post-receive doctorofcosmetology-prod:/var/www/u3548935/data/repo/doctorofcosmetology.git/hooks/post-receive
ssh doctorofcosmetology-prod "chmod +x ~/repo/doctorofcosmetology.git/hooks/post-receive"

# 4) локально
git remote add prod doctorofcosmetology-prod:/var/www/u3548935/data/repo/doctorofcosmetology.git
```

## Откат

```bash
git push prod <previous-sha>:main
```

Код до деплоя — в `~/backups/deploy/code-*.tar.gz`.

## Ограничение варианта S1

`checkout -f` не удаляет файлы, убранные из репозитория. Удалять их с прода нужно вручную, не задевая `q/`, `portfolio/`, `uslugi.json`.
