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
    - `docker-compose run wp cli wp ...`
	- `docker-compose run wpcli wp db reset`


In the local development container, some constants are set:

- `DOING_TL_VENDOR_TESTS`
	- true
- `WP_DEBUG`
	- true
- `TRUSTEDLOGIN_API_URL`
	- http://web:80/api/v1/

You can edit these variables in docker-compose.yml. You must rebuild containers after editting.

### Running PHPUnit In Docker

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
### Server To Server HTTP Requests
If the ecommerce app is also running in docker-compose, this WordPress and the "web" service of app should be in "tl-dev" network. This allows you to make an HTTP request to the eCommerce app like this:


```php
$r = wp_remote_get('http://web:80',['sslverify' => false]);
```

If this doesn't work, make sure a "tl-dev" network exists:

```bash
docker network ls
```

If it does not, create one:

```bash
docker network create tl-dev
```
