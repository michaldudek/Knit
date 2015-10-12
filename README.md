Knit
====

Knit ties your PHP objects with your database of choice in a simple way.

[![Build Status](https://travis-ci.org/michaldudek/Knit.svg)](https://travis-ci.org/michaldudek/Knit)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/7a399ff9-a1b9-4b67-99ad-c3865eae8050/mini.png)](https://insight.sensiolabs.com/projects/7a399ff9-a1b9-4b67-99ad-c3865eae8050)

Knit is not a full-blown ORM/ODM, but rather an extended "data mapper" that abstracts
away a persistent store behind a "repository" pattern.

Features overview:

- Repository pattern (with methods: `find`, `findOne`, `add`, `update`, `remove`, `save`
as well as magic `findByPropertyName` and `findOneByPropertyName`).
- Building queries in form of "criteria" arrays.
- Swappable data stores.
- Various events dispatched (will read, will write, will update, will remove, did read,
did update, did remove).
- Easy programmatic joins (which means joins between stores).
- Swappable data mappers.

# Quick Demo:

    <?php

    // gets a repository to manage object of class MyApp\News
    $repository = $knit->getRepository(MyApp\News::class, 'news');

    // gets top 20 news from the last week that have a cover photo set
    // and were in one of the three country sections
    $news = $repository->find([
        'cover_photo:not' => null,
        'published_at:gt' => strtotime('1 week ago'),
        'country' => ['US','UK','PL']
    ], [
        'orderBy' => 'views',
        'orderDir' => Knit\Knit::ORDER_DESC,
        'limit' => 20
    ]);

    // joins photos to the news items based on `news.id = photo.news_id`
    // into `$photos` property on news object (by calling `::setPhotos()` method)
    $news = $repository->joinMany(
        $news,
        $knit->getRepository(MyApp\Photo::class, 'photos'),
        'id',
        'news_id',
        'photos'
    );

    foreach ($news as $i => $item) {
        $featured = $i < 5 && count($item->getPhotos()) > 3;
        $item->setFeatured($featured);

        $repository->save($item);
    }

# Criteria expressions

As you can see in the quick demo above, Knit repositories allow simple yet quite 
powerful filtering through criteria arrays. You don't need to worry about building
database queries and if you ever want to switch the underlying database you can do so
without changing any of your existing code.

The pattern to build criteria is as follows:

    '[property_name]:[operator]' => [value_to_match]

You can put multiple criteria in an array and by default all will need to match.

For more complex queries you can use logical operators like this:

    [
        Knit\Knit::LOGIC_OR => [
            'active' => 1,
            Knit\Knit::LOGIC_AND => [
                'active' => 0,
                'registered_at:gt' => strtotime('1 day ago')
            ]
        ]
    ]

This will translate to an SQL WHERE statement `WHERE active = 1 OR (active = 0 AND
registered_at > 1234567890)` or MongoDB query
`{"$or":[{"active":1},{"$and":[{"active":0},{"registered_at":{"$gt":1234567890}}]}]}`.

### Available operators

The following operators are available. Please note that there may be some small inconsistencies in how they work between various stores.

- `:eq` (default, can be ommitted) - equals (for array values it will switch to `:in` operator - but only in DoctrineDBAL Store)
- `:not` - not equals (for array values it will switch to `:not_in` operator - but only in DoctrineDBAL Store)
- `:gt` - greater than
- `:gte` - greater than or equal
- `:lt` - lower than
- `:lte` - lower than or equal
- `:regex` - matches a regular expression
- `:like` - allows `%` to escape one or more characters
- `:not_like` - allows `%` to escape one or more characters (only in DoctrineDBAL Store)
- `:exists` - property exists (only in MongoDB)

# Usage

Knit is available through Packagist:

    $ composer require michaldudek/knit:0.2.*

The main core class of Knit is `Knit\Repository` and you will interact with it the most.
`Knit\Knit` class serves as a convenient factory for repositories.

Knit consists of three main building blocks:

- **Repository** - which is the facade for working with objects. Each object class has
its own repository instance and all objects are managed through it.
- **Store** - "driver" for a persistent (or not necesserily) store like MySQL or MongoDB.
- **DataMapper** - responsible for mapping arrays to objects (hydration) and vice versa
(extraction).

In order to work, Knit requires default store and default data mapper, as well as an
event dispatcher. For example:

    <?php

    // this will be used as the default store for all repositories
    $store = new Knit\Store\DoctrineDBAL\Store([
        'driver' => 'pdo_mysql',
        'user' => '[username]',
        'password' => '[password]',
        'host' => '[database_host]',
        'port' => [database_port],
        'dbname' => '[database_name]'
    ]);

    $knit = new Knit\Knit(
        $store,
        // this will be used as the default data mapper for all repositories
        new Knit\DataMapper\ArraySerializable\ArraySerializer(),
        // this will be injected to all repositories
        new Symfony\Component\EventDispatcher\EventDispatcher()
    );

To get a repository for an object you can now use

    $repository = $knit->getRepository(MyApp\News::class, 'news');

where first argument is the class name of objects that will be managed by the repository
and the second argument is collection name within the underlying store in which the
objects are stored (tables in MySQL). Additional arguments are optional, but you can
pass a custom store for the created repository as 3rd argument, a custom data mapper
as 4th argument and even a custom repository class as 5th argument.

By default, all created repositories will be instances of `Knit\Repository`, but you can
your custom class if you create it right next to object you want to manage and add
a `*Repository` suffix. The lookup is done this way:

    // for given class:
    $objectClass = MyApp\MyObject::class;

    // do the lookup
    if (class_exists($objectClass .'Repository')) {
        $repositoryClass = $objectClass .'Repository';
    }

Or you can pass your custom class name as 5th argument to `::getRepository()` as
mentioned above.

Note that all repositories must extend the core `Knit\Repository` class.

## Available Repository Methods:

- `::find(array $criteria = [], array $params = []): array`
- `::findByIdentifiers(array $identifiers, array $params = []): array`
- `::findOne(array $criteria = [], array $params = []): object|null`
- `::findOneByIdentifier($identifier): object|null`
- `::findBy*($value, array $params = []): array`
- `::findOneBy*($value, array $params = []): object|null`
- `::count(array $criteria = [], array $params = []): integer`
- `::save($object)` - proxies to `::add($object)` or `::update($object)`
- `::add($object)`
- `::update($object)`
- `::delete($object)`
- `::joinOne(array $objects, Repository $withRepository, $leftProperty, $rightProperty, $targetProperty, array $criteria = [], $excludeEmpty = false): array`
- `::public function joinMany(array $objects, Repository $withRepository, $leftProperty, $rightProperty, $targetProperty, array $criteria = [], array $params = [], $excludeEmpty = false): array`
- `::createWithData(array $data = []): object`
- `::updateWithData($object, array $data = [])`

## Events

You can listen on various events dispatched by a repository:

- `knit.will_read` with `Knit\Events\CriteriaEvent` allows to alter criteria before read
- `knit.did_read` with `Knit\Events\ResultsEvent` allows to alter results after read
- `knit.will_save` with `Knit\Events\ObjectEvent` allows to alter object before save
- `knit.did_save` with `Knit\Events\ObjectEvent` allows to alter object after save
- `knit.will_add` with `Knit\Events\ObjectEvent` allows to alter object only before insert
- `knit.did_add` with `Knit\Events\ObjectEvent` allows to alter object only after insert
- `knit.will_update` with `Knit\Events\ObjectEvent` allows to alter object only before update
- `knit.did_update` with `Knit\Events\ObjectEvent` allows to alter object only after update
- `knit.will_delete` with `Knit\Events\ObjectEvent` allows to prevent delete
- `knit.did_delete` with `Knit\Events\ObjectEvent`

You can get a reference to the event dispatcher either on repository:
`$repository->getEventDispatcher()` or on Knit instance `$knit->getEventDispatcher()`.

Note that the event dispatcher is global for all repositories so your listeners should
always check what type of data is included in the event.

## Available Stores

At the moment the following stores are available:

### DoctrineDBALStore

[Knit\Store\DoctrineDBAL\Store](src/Store/DoctrineDBAL/Store.php)

This store is based on [Doctrine DBAL](https://github.com/doctrine/dbal) and therefore
supports all the databases Doctrine supports, namely MySQL or PgSQL and more.

It accepts the same configuration options as DoctrineDBAL `Connection` class
as the first argument. See
[Configuration](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html)
for details.

You can access Doctrine Query Builder in a repository that uses this class by calling

    $queryBuilder = $this->store->getConnection()->createQueryBuilder();

but please remember to hydrate all the results later by calling

    $results = $this->mapResults($results);

### MongoDBStore

[Knit\Store\MongoDb\Store](src/Store/MongoDb/Store.php)

Store that uses `MongoDB` PHP extension to communicate with MongoDB.

First constructor argument is a configuration array and it must contain at least
`hostname` and `database` keys. It also accepts the following options:

- username
- password
- replica_set
- port
- hosts - an array of additional hosts to connect to (elements must be arrays with key
`hostname` and optionally `port`)

## Available Data Mappers

At the moment there is only one simple data mapper available:
[Knit\DataMapper\ArraySerializable\ArraySerializer](src/DataMapper/ArraySerializable/ArraySerializer.php).

It simply hydrates objects by calling `::fromArray(array $data)` and `::toArray(): array`
methods on them and therefore requires all objects to implement
`Knit\DataMapper\ArraySerializable\ArraySerializableInterface`.

# Contributing

PR's and issues are most welcome.

Before submitting a Pull Request please run

    $ make qa

in order to run all unit and lint tests.

Please make sure your changes are fully tested and covered.

If you need to test against a real database (like the currently imlemented stores are),
there is a Vagrant VM config in `resources/tests`. It will create a box with PHP 5.6,
MySQL and MongoDB and some env vars which guide the tests that they can be ran (see
`::setUp()` methods in [DoctrineDBALTest](tests/Store/DoctrineDBALTest) and [MongoDbTest]
(tests/Store/MongoDbTest)). Feel free to customize / extend this setup.

    $ cd resources/tests
    $ vagrant up
    $ vagrant ssh
    $ cd /knit
    $ make qa

The above will run the full tests suite.

To generate a code coverage report run

    $ make report

It will save the HTML report in `resources/coverage` directory.

# License

MIT, see [LICENSE.md](LICENSE.md).

Copyright (c) 2015 Michał Pałys-Dudek
