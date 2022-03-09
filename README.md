Selected technology stack (only those that I installed for the purpose of completing the task):

* Docker (php8, mysql, caddy)
* Symfonyapi-platform/core - udostępnienie zasobu pod Website DB 
    * doctrine/orm - database
    * stof/doctrine-extensions-bundle - timestamable, always uses in entities
    * faker - generating sample data
    * imagine - creating image thumbnails
    * symfony/messenger - asynchronous queue
    * symfony/monolog-bundle - writing logs for debugging
    * league/flysystem-bundle - file storage

Commits representing the test task:

* https://github.com/bagsiur/scaling-our-importers/commit/31e62ea730f8dd37abd0e53770e4f4576aafc7d5
* https://github.com/bagsiur/scaling-our-importers/commit/520e8098062e4c5c0cdf516ca89fe858d8adb7bb

Installation::

* git clone https://github.com/bagsiur/scaling-our-importers.git ./
* docker-compose build --pull --no-cache
* docker-compose up -d 

Clarification:

* At the beginning, I prepared a fake set of input data, i.e. tours from a non-existent importer in JSON format: https://github.com/bagsiur/scaling-our-importers/blob/main/api/src/Controller/imporetController.php
    * Sample data is generated using the Faker\Factory and is available on the endpoint: [GET] /importer/fake-tours
    * The dataset includes random photos from https://picsum.photos and pdf (I haven't found lorem ipsum for pdfs)
* The import of sample data starts with calling the script:
    * in console: docker-compose exec php bin/console importer: import
    * the console command calls the import function: https://github.com/bagsiur/scaling-our-importers/blob/520e8098062e4c5c0cdf516ca89fe858d8adb7bb/api/src/Manager/ProcessManager.php#L122 nd its task is:
        * Downloading the json resource from the docker host: caddy/importer/fake-tours: https://github.com/bagsiur/scaling-our-importers/blob/520e8098062e4c5c0cdf516ca89fe858d8adb7bb/api/src/Manager/ProcessManager.php#L116
        * Adding a log through a monologue about the start of the import and about each imported trip
        * Transforming the tour from the resource to the TourRadar format (here I changed the names a bit to show that there is some kind of transformation)
        * Adding a transformed JSON file to tour storage (league/flysystem-bundle saved in /var/storage/tours the file name is tour uuid)
        * Sending a message to the messenger queue about the new trip: https://github.com/bagsiur/scaling-our-importers/blob/main/api/src/Message/TourMessage.php
            * The queue is a table in the database named: messenger_messages
        * Import completion log added
* The next step is to call the queue asynchronously:
    * in console: docker-compose exec php bin/console messenger: consume async
        * Each time the messenger will call the class: https://github.com/bagsiur/scaling-our-importers/blob/main/api/src/MessageHandler/TourMessageHandler.php which in turn will call the proccessTourMessage () function: https://github.com/bagsiur/scaling-our-importers/blob/main/api/src/Manager/ProcessManager.php#L165 his job is
            * Will read the tour JSON file from tour storage based on its id
            * Saves the assets files (including photos and pdfs) to assets storage (in this case /api/assets: https://github.com/bagsiur/scaling-our-importers/tree/main/api/assets)
            * Will create photo thumbnails: https://github.com/bagsiur/scaling-our-importers/blob/main/api/src/Manager/ProcessManager.php#L216
            * Save the tour to database (Tour.php entity: https://github.com/bagsiur/scaling-our-importers/blob/main/api/src/Entity/Tour.php)
            * Finally, it will delete the tour file from tour storage
* Finally, the resources of the trips are exposed to the front through api-platform (swagger is running by default):
    * Endpoint: [GET] https://localhost/tours
