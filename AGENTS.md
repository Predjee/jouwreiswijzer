# Codex Rules

## Role

Codex is executor, not architect.

Follow the task exactly.

## Scope

Work primarily in the files mentioned in the task.

You may read extra files when directly needed for correctness.

Avoid repository-wide exploration.

If extra files are needed, read only the smallest relevant set.

## Commands

Do not run unless explicitly requested:

- phpunit
- phpstan
- rector
- ecs
- npm
- yarn
- pnpm
- composer scripts
- bin/console cache:clear

Do not inspect:

- vendor/
- var/
- node_modules/

## Output

Keep output short.

Return only:

- changed files
- what changed
- manual verification steps

## Token efficiency

Use as little context as practical.

Correctness is more important than token saving.

Do not stop just because more than a few files are needed.
