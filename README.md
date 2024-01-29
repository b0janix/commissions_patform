## About the project

I've written the code on the Ubuntu 22.04 platform. Locally I also have installed PHP 8.2, so in order to test it locally, 
you will have to have this version of PHP installed on your machine because the code contains a php 8.2 syntax. 
If you do have php 8.2 installed then run:

``1. composer install``
``2. php script.php input.csv``

if you want to run the tests:

``php vendor/bin/phpunit``

If you don't have php 8.2 installed locally, then you would have to give it a try trough docker:

``1. composer install``
``2. docker build -t platform/calc_commissions .``
``3. docker run -it --rm platform/calc_commission``

So from within the container I'm running 2 commands. The first is the is the execution of the php script, 
and the second one is the execution of the tests. This is the output in the terminal, you can notice that my results
are very close to those provided by your side

[](https://freeimage.host/i/JcOBXMG)

In order for my code to comply with PSR-12 I've installed PHP CS Fixer through composer. In order to run it,
it has to be installed by composer first. Because I didn't write a config file, if you are planning to change some of the code,
you can run it like this:

``PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src``

or

``PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix script.php``