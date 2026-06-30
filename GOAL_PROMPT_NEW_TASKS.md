# Universal Goal Prompt

```text
Прочитай PROJECT_MAP.md, ARCHITECTURE_PLAN.md, MIGRATION_PLAN.md, REWRITE_TASKS.md.

Работай по REWRITE_TASKS.md как по единому источнику истины.

Цель:
Последовательно выполнить все незавершенные задачи из REWRITE_TASKS.md и довести проект до состояния, где каждая выполненная задача проверена, закоммичена и запушена отдельным commit.

Как выбрать задачу:
- Найди первую задачу в REWRITE_TASKS.md со статусом Status: pending.
- Начинай именно с нее.
- Не переделывай задачи со статусом Status: done без явной причины.
- Если в задаче есть зависимости или условия старта, сначала проверь их.
- После успешного завершения текущей задачи переходи к следующей задаче со статусом Status: pending.
- Если pending-задач не осталось, остановись и сообщи, что workflow завершен.

Правила выполнения:
- Перед стартом каждой задачи проверь REWRITE_TASKS.md и git status.
- Делай только scope текущей задачи.
- Не смешивай несколько задач в одном diff.
- Не переходи к следующей задаче, пока текущая не завершена успешно.
- После выполнения обнови статус текущей task в REWRITE_TASKS.md.
- Если проверки не прошли или есть blocker, не коммить, не пушь, не переходи дальше.
- Если задача завершена успешно, сделай отдельный commit и push в origin master.
- Один task = один commit.
- После каждой задачи пиши: current task, changed files, verification, blockers, commit/push status.

Testing policy:
- Permanent tests в repo не добавлять.
- Временные проверки можно писать только в tmp/.
- tmp/ не stage, не commit, не push.
- После задачи временные test scripts удалить или оставить ignored.
- Обязательные проверки: php -l, docker compose config, real smoke checklist.

Запрещено:
- Не запускай make u.
- Не запускай make r.
- Не запускай docker compose up.
- Не коммить tmp/.
- Не добавляй новые permanent tests в repo.
- Не коммить secrets, tokens, IPs, private keys, sensitive screenshots.
- Не удаляй и не откатывай чужие изменения без явной команды.

Разрешенные безопасные проверки:
- php -l
- docker compose config
- bin/migrate.php на tmp DB, если задача трогает DB
- bin/import-legacy.php на tmp DB, если задача трогает import
- temporary scripts under tmp/ only, без commit/push
- другие проверки только если они явно указаны в текущей задаче и не запускают весь stack

Работа с временными проверками:
- Если для задачи нужен быстрый script/check, создай его в tmp/.
- Не добавляй tmp/ в git.
- Не stage tmp/.
- Не push tmp/.
- В отчете укажи, какие tmp checks запускались.

Commit/push:
- Перед commit выполни git status и проверь, что в commit попадает только scope текущей задачи.
- Commit message бери из текущей задачи, если указан.
- Если commit message не указан, используй Conventional Commits.
- После commit сделай push в origin master.

Финальный стоп:
- Когда pending-задач больше нет, остановись.
- Напиши: workflow завершен, последняя выполненная задача, verification summary, blockers none/список.

Сейчас найди первую pending task в REWRITE_TASKS.md и выполни только ее.
После успешного завершения автоматически продолжай следующие pending tasks по тем же правилам.
```
