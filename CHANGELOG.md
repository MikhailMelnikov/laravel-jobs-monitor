# Changelog

## 1.1.0 — 2026-10-14

- Use case handlers are no longer `final`, their properties and helpers are `protected`:
  an application can extend a handler and bind the subclass in the container.

## 1.0.1 — 2026-10-14

- README translated into Russian; step-by-step installation guide.

## 1.0.0 — 2026-10-14

- Initial release: scheduler task list and manual run (queue / sync), database queue
  overview, queued jobs list and delete, failed jobs list with notification recipients,
  retry / forget / flush. English and Russian translations.
