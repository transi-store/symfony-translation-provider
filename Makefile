.DEFAULT_GOAL = help

## ———— Ticketing Makefile ——————————————————————————————————————————————————————————
.PHONY: help
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^## —)' $(MAKEFILE_LIST) | sed -e 's/Makefile://' | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'
	@printf "$(if $(APP_HELP_ASIDE),$(APP_HELP_ASIDE)\n\n)"

## ———— App —————————————————————————————————————————————————————————————————————————
.PHONY: install
install: vendor  ## Install PHP dependencies

vendor: composer.lock
	composer install
	## change the date of vendor dir to avoid regerate it each time with make
	touch -d "now" vendor

## ———— Testing —————————————————————————————————————————————————————————————————
.PHONY: test
test: phpunit ## Launch all tests

.PHONY: phpunit
phpunit: ## Launch php unit tests
	 phpunit $(ARGS)


## ———— Quality ———————————————————————————————————————————————————————————————————
.PHONY: lint
lint: phpstan

.PHONY: baseline
baseline: phpstan-baseline

.PHONY: phpstan
phpstan: ## Run PHPStan analysis
	bin/phpstan analyse
