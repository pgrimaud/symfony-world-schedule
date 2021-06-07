box: ## Compiles the project into a PHAR archive
	composer dump-env prod
	./bin/console cache:clear
	./bin/console cache:warmup
	docker run --rm --interactive $${TTY} --volume="$$(pwd):/app:delegated" ajardin/humbug-box compile -vvv
	rm .env.local.php
.PHONY: box

help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' \
		| sed -e 's/\[32m##/[33m/'
.DEFAULT_GOAL := help