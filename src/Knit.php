<?php
namespace Knit;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use MD\Foundation\Debug\Debugger;

use Knit\DataMapper\DataMapperInterface;
use Knit\Store\StoreInterface;
use Knit\Repository;

/**
 * Main Knit class.
 *
 * @package   Knit
 * @author    Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright 2015 Michał Pałys-Dudek
 * @license   https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class Knit
{
    /**
     * Order constants.
     */
    const ORDER_ASC = 1;
    const ORDER_DESC = -1;

    /**
     * Logic constants.
     */
    const LOGIC_OR = '__OR__';
    const LOGIC_AND = '__AND__';

    /**
     * Join options.
     */
    const EXCLUDE_EMPTY = true;

    /**
     * Event names.
     */
    const EVENT_WILL_READ = 'knit.will_read';
    const EVENT_DID_READ = 'knit.did_read';
    const EVENT_WILL_ADD = 'knit.will_add';
    const EVENT_DID_ADD = 'knit.did_add';
    const EVENT_WILL_UPDATE = 'knit.will_update';
    const EVENT_DID_UPDATE = 'knit.did_update';
    const EVENT_WILL_SAVE = 'knit.will_save';
    const EVENT_DID_SAVE = 'knit.did_save';
    const EVENT_WILL_DELETE = 'knit.will_delete';
    const EVENT_DID_DELETE = 'knit.did_delete';

    /**
     * Property key set on an object that is marked as stored.
     */
    const KEY_STORED = '__knitStored';

    /**
     * Default data store.
     *
     * @var StoreInterface
     */
    protected $defaultStore;

    /**
     * Default data mapper.
     *
     * @var DataMapperInterface
     */
    protected $defaultDataMapper;

    /**
     * Event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * Registered repositories.
     *
     * @var array
     */
    protected $repositories = [];

    /**
     * Constructor.
     *
     * @param StoreInterface           $store           Default data store.
     * @param DataMapperInterface      $dataMapper      Default data mapper.
     * @param EventDispatcherInterface $eventDispatcher Event dispatcher.
     */
    public function __construct(
        StoreInterface $store,
        DataMapperInterface $dataMapper,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->defaultStore = $store;
        $this->defaultDataMapper = $dataMapper;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Gets a repository for the given object class.
     *
     * @param string                   $objectClass     Class name of the object that will be managed by the repository.
     * @param string                   $collection      Name of the collection / table in which objects are stored in
     *                                                  the store.
     * @param StoreInterface|null      $store           [optional] Store in which the objects are stored,
     *                                                  if not the default.
     * @param DataMapperInterface|null $dataMapper      [optional] DataMapper for the repository if not the default.
     * @param string|null              $repositoryClass [optional] Custom repository class.
     *
     * @return Repository
     */
    public function getRepository(
        $objectClass,
        $collection,
        StoreInterface $store = null,
        DataMapperInterface $dataMapper = null,
        $repositoryClass = null
    ) {
        $objectClass = ltrim($objectClass, '\\');

        // if this repository already created then just return it
        if (isset($this->repositories[$objectClass])) {
            return $this->repositories[$objectClass];
        }

        // fill defaults if necessary
        $store = $store === null ? $this->defaultStore : $store;
        $dataMapper = $dataMapper === null ? $this->defaultDataMapper : $dataMapper;

        $repositoryClass = $this->findRepositoryClass($objectClass, $repositoryClass);

        // create the repository and store it for the future
        $repository = new $repositoryClass(
            $objectClass,
            $collection,
            $store,
            $dataMapper,
            $this->eventDispatcher
        );

        $this->repositories[$objectClass] = $repository;
        return $repository;
    }

    /**
     * Attempts to find and verify a repository class for the given object class.
     *
     * @param string $objectClass     Object class.
     * @param string $repositoryClass [optional] Suggested repository class, if any.
     *
     * @return string
     */
    protected function findRepositoryClass($objectClass, $repositoryClass = null)
    {
        // figure out a repository class if none given
        if ($repositoryClass === null) {
            $repositoryClass = $objectClass .'Repository';
            if (!class_exists($repositoryClass)) {
                // return already and don't bother checking, as we know it
                return Repository::class;
            }
        }

        // verify the repository class
        if (!Debugger::isExtending($repositoryClass, Repository::class, true)) {
            throw new \RuntimeException(
                sprintf(
                    'An object repository class must extend %s, but %s given.',
                    Repository::class,
                    $repositoryClass
                )
            );
        }

        return $repositoryClass;
    }
}
