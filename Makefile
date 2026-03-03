.PHONY: setup test test-all cs cs-all stan stan-all psalm psalm-all analyze analyze-all smoke bash all all-versions

# Target PHP version for single-version commands (default: 83)
PHP_VERSION ?= 83
SVC = php$(PHP_VERSION)

# ── Setup ────────────────────────────────────────────────────────────────────
setup:
	docker compose up -d
	docker compose exec php83 composer install

# ── Tests ────────────────────────────────────────────────────────────────────
test:
	docker compose exec $(SVC) vendor/bin/phpunit Tests

test-all:
	@$(MAKE) test PHP_VERSION=83
	@$(MAKE) test PHP_VERSION=84
	@$(MAKE) test PHP_VERSION=85

# ── Code style ───────────────────────────────────────────────────────────────
cs:
	docker compose exec $(SVC) vendor/bin/phpcs Asm/EprelApiClient

cs-all:
	@$(MAKE) cs PHP_VERSION=83
	@$(MAKE) cs PHP_VERSION=84
	@$(MAKE) cs PHP_VERSION=85

# ── PHPStan ──────────────────────────────────────────────────────────────────
stan:
	docker compose exec $(SVC) vendor/bin/phpstan analyse Asm/EprelApiClient --level=max

stan-all:
	@$(MAKE) stan PHP_VERSION=83
	@$(MAKE) stan PHP_VERSION=84
	@$(MAKE) stan PHP_VERSION=85

# ── Psalm ────────────────────────────────────────────────────────────────────
psalm:
	docker compose exec $(SVC) vendor/bin/psalm

psalm-all:
	@$(MAKE) psalm PHP_VERSION=83
	@$(MAKE) psalm PHP_VERSION=84
	@$(MAKE) psalm PHP_VERSION=85

# ── Combined analysis (cs + stan + psalm) ────────────────────────────────────
analyze: cs stan psalm

analyze-all: cs-all stan-all psalm-all

# ── Smoke test ───────────────────────────────────────────────────────────────
smoke:
	docker compose exec $(SVC) php test-client.php $(ARGS)

# ── Shell access ─────────────────────────────────────────────────────────────
bash:
	docker compose exec $(SVC) bash

# ── Convenience aggregates ───────────────────────────────────────────────────
all: cs stan psalm test

all-versions: cs-all stan-all psalm-all test-all
