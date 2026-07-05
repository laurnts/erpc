# Tasks: remove-article-detail-page

- [x] 1. Remove `/articles/{article}` route, `ArticleDetail` component, and view; unlink grid cards
- [x] 2. Update catalog tests (detail route returns 404; price/stock/no-leak assertions via the grid; cart accumulation from the grid card)
- [x] 3. `php vendor/bin/pint --dirty`; targeted catalog + public suites green; `openspec validate remove-article-detail-page --strict`
