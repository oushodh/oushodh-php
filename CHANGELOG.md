# Changelog

## 0.1.0 (unreleased)

- First version: a client for the public `/v1` API.
- `meta()`, `listMedicines()`, `medicines()` (pages to the end), `search()`, `getMedicine()`, `imageUrl()` and
  `getImage()`.
- Typed models for every object in the API, each with `raw` for fields added later.
- One exception per error family, with the HTTP status and the error code.
- Retries after `429 rate_limited` (it waits `Retry-After`), a `5xx` or a network error.
