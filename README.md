# TrustedLogin Vendor Plugin

Plugin to interact with TrustedLogin's encrypted storage infrastructure to redirect support staff into an authenticated session on client installations.

## To compile

The repo lacks the `/vendor/` directory; you'll need to build first. Here's how:

1. Change directories to the plugin directory (`cd /path/to/directory`)
1. Run `composer install --no-dev`

## Code Standards Installation

1. Change directories to the plugin directory (`cd /path/to/directory`)
1. Run `composer install` - this will also install the code standards directory
1. Run `./vendor/bin/phpcs`

## Local Development Environment

A [docker-compose](https://docs.docker.com/samples/wordpress/)-based local development environment is provided.

- Start server
    - `docker-compose up -d`
- Acess Site
    - [http://localhost:6300](http://localhost:6100)
- Run WP CLI command:
    - `
	`
	- `docker-compose run wpcli wp db reset`


In the local development container, the constant `DOING_TL_VENDOR_TESTS` is set to true, as is `WP_DEBUG`.


There is a special phpunit container for running WordPress tests, with WordPress and MySQL configured.

- Enter container
    - `docker-compose run phpunit`
- Test
    - `phpunit`

## Admin Settings Page

- Install
	- `yarn`
- Build
	- `yarn build`
- Run watcher
	- `yarn watch`
- Run JavaScript Test
	- `yarn test`
