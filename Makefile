# Makefile
# 
# Common Makefile for web projects.
# 
# @author		Michał Pałys-Dudek <michal@michaldudek.pl>
# @link			TBD
# @version		0.1.1
# @date			04.10.2015
# ---------------------------------------------------------------------------

# Targets
# ---------------------------------------------------------------------------

help:
	@echo ""
	@echo "Following commands are available:"
	@echo "(this is summary of the main commands,"
	@echo " but for more fine-grained commands see the Makefile)"
	@echo ""
	@echo "     make help           : This info."
	@echo ""
	@echo " Installation:"
	@echo "     make install        : Installs all dependencies."
	@echo ""
	@echo " Quality Assurance:"
	@echo "     make test           : Run tests."
	@echo "     make lint           : Lint the code."
	@echo "     make qa             : Run tests, linters and any other quality assurance tool."
	@echo "     make report         : Build reports about the code / the project / the app."
	@echo ""

# alias for help
all: help

# Installation
# ---------------------------------------------------------------------------

# Installs all dependencies
install: composer

# Updates all dependencies
update: composer_update

# Quality Assurance
# ---------------------------------------------------------------------------

# Run tests.
test: phpunit

# Lint the code.
lint: phpcs phpcs_test phpmd

# Run tests, linters and any other quality assurance tool.
qa: test lint

# Build reports about the code / the project / the app.
report: phpunit_report

# ---------------------------------------------------------------------------
# Lib specific commands
# ---------------------------------------------------------------------------

# install Composer dependencies for production
composer:
	composer install --no-interaction --prefer-dist

# update Composer dependencies
composer_update:
	composer update

# run the PHPUnit tests
phpunit:
	php ./vendor/bin/phpunit

phpunit_report:
	php ./vendor/bin/phpunit --coverage-html resources/coverage

# run PHPCS on the source code and show any style violations
phpcs:
	php ./vendor/bin/phpcs --standard="phpcs.xml" src

# run PHPCBF to auto-fix code style violations
phpcs_fix:
	php ./vendor/bin/phpcbf --standard="phpcs.xml" src

# run PHPCS on the test code and show any style violations
phpcs_test:
	php ./vendor/bin/phpcs --standard="phpcs.xml" tests

# run PHPCBF on the test code to auto-fix code style violations
phpcs_test_fix:
	php ./vendor/bin/phpcbf --standard="phpcs.xml" tests

# Run PHP Mess Detector on the source code
phpmd:
	php ./vendor/bin/phpmd src text ./phpmd.xml
