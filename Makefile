.PHONY: setup test cs stan psalm all bash

setup:
	docker compose up -d
	docker compose exec php composer install

test:
	docker compose exec php vendor/bin/phpunit Asm/EprelApiClient/Tests

cs:
	docker compose exec php vendor/bin/phpcs Asm/EprelApiClient

stan:
	docker compose exec php vendor/bin/phpstan analyse Asm/EprelApiClient --level=max

psalm:
	docker compose exec php vendor/bin/psalm

bash:
	docker compose exec php bash

all: cs stan psalm test
