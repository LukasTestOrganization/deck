app_name=$(notdir $(CURDIR))
build_tools_directory=$(CURDIR)/build/tools
build_dir=$(CURDIR)/build/artifacts
source_build_directory=$(CURDIR)/build/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

sign_dir=$(build_dir)/sign
cert_dir=$(HOME)/.nextcloud/certificates


default: build

clean-dist:
	rm -rf node_modules/

install-deps: install-deps-js
	composer install

install-deps-nodev: install-deps-js
	composer install --no-dev

install-deps-js:
	npm ci

build: clean-dist install-deps build-js

release: clean-dist install-deps-nodev build-js

build-js: install-deps-js
	npm run build

build-js-dev: install-deps
	npm run dev

watch:
	npm run watch

test: test-unit test-integration

test-unit:
	mkdir -p build/
ifeq (, $(shell which phpunit 2> /dev/null))
	@echo "No phpunit command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sSL https://phar.phpunit.de/phpunit-8.2.phar -o $(build_tools_directory)/phpunit.phar
	php $(build_tools_directory)/phpunit.phar -c tests/phpunit.xml --coverage-clover build/php-unit.coverage.xml
	php $(build_tools_directory)/phpunit.phar -c tests/phpunit.integration.xml --coverage-clover build/php-integration.coverage.xml
else
	phpunit -c tests/phpunit.integration.xml --testsuite=integration-database --coverage-clover build/php-integration.coverage.xml
endif

test-integration:
	cd tests/integration && ./run.sh

test-js: install-deps
	npm run test
