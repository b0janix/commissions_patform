FROM php:8.2-cli

COPY . /usr/src/calc_commissions_platform

WORKDIR /usr/src/calc_commissions_platform

CMD php script.php input.csv;php vendor/bin/phpunit