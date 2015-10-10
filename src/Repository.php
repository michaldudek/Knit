<?php
namespace Knit;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Utils\ObjectUtils;
use MD\Foundation\Utils\StringUtils;

use Knit\Criteria\CriteriaExpression;
use Knit\DataMapper\DataMapperInterface;
use Knit\Events\CriteriaEvent;
use Knit\Events\ObjectEvent;
use Knit\Events\ResultsEvent;
use Knit\Store\StoreInterface;
use Knit\Knit;

/**
 * Repository is the centre of Knit and is the bridge between stores and PHP objects. Generally, you should only work
 * with repositories by injecting them in appropriate places.
 *
 * @package    Knit
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 */
class Repository
{
    /**
     * Name of the class that this repository manages.
     *
     * @var string
     */
    protected $objectClass;

    /**
     * Name of the collection (if any) in which objects of this repository are stored.
     *
     * @var string
     */
    protected $collection;

    /**
     * Store driver.
     *
     * @var StoreInterface
     */
    protected $store;

    /**
     * Data mapper that is responsible for converting arrays from stores into PHP objects and vice versa.
     *
     * @var DataMapperInterface
     */
    protected $dataMapper;

    /**
     * Event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * Constructor.
     *
     * @param string                   $objectClass     Name of the class that this repository manages.
     * @param string                   $collection      Name of the collection (if any) in which objects
     *                                                  of this repository are stored.
     * @param StoreInterface           $store           Store driver.
     * @param DataMapperInterface      $dataMapper      Data mapper that is responsible for converting arrays
     *                                                  from stores into PHP objects and vice versa.
     * @param EventDispatcherInterface $eventDispatcher Event dispatcher.
     */
    public function __construct(
        $objectClass,
        $collection,
        StoreInterface $store,
        DataMapperInterface $dataMapper,
        EventDispatcherInterface $eventDispatcher
    ) {
        // normalize the class to be sure its prefixed with namespace separator
        $objectClass = ltrim($objectClass, '\\');

        if (!class_exists($objectClass)) {
            throw new \InvalidArgumentException(sprintf(
                '%s cannot manage objects of class %s because it does not exist.',
                __CLASS__,
                $objectClass
            ));
        }

        if (!$dataMapper->supports($objectClass)) {
            throw new \InvalidArgumentException(sprintf(
                'The passed DataMapper (%s) cannot manage objects of class %s',
                get_class($dataMapper),
                $objectClass
            ));
        }

        $this->objectClass = $objectClass;
        $this->collection = $collection;
        $this->store = $store;
        $this->dataMapper = $dataMapper;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Finds objects in the repository by the given criteria.
     *
     * @param array $criteria Search criteria.
     * @param array $params   Search parameters, like `orderBy`.
     *
     * @return array
     */
    public function find(array $criteria = [], array $params = [])
    {
        // dispatch an event with the criteria, so they can be modified
        $criteriaEvent = new CriteriaEvent($criteria, $params);
        $this->eventDispatcher->dispatch(Knit::EVENT_WILL_READ, $criteriaEvent);
        
        // read the potentially updated criteria
        $criteria = $criteriaEvent->getCriteria();
        $params = $criteriaEvent->getParams();

        // read form the store
        $results = $this->store->find($this->collection, new CriteriaExpression($criteria), $params);

        $objects = $this->mapResults($results);
        
        // dispatch an event with the results so they can be modified
        $resultsEvent = new ResultsEvent($objects);
        $this->eventDispatcher->dispatch(Knit::EVENT_DID_READ, $resultsEvent);

        return $resultsEvent->getResults();
    }

    /**
     * Finds objects in the repository by their identifiers.
     *
     * @param array $identifiers Set of identifiers to search by.
     * @param array $params      Search parameters.
     *
     * @return array
     */
    public function findByIdentifiers(array $identifiers, array $params = [])
    {
        $identifierName = $this->dataMapper->identifier($this->objectClass);

        $criteria = is_string($identifierName)
            ? [$identifierName => $identifiers]
            : [Knit::LOGIC_OR => $identifiers];

        return $this->find($criteria, $params);
    }

    /**
     * Finds a single object in the repository by the given criteria.
     *
     * @param array $criteria Search criteria.
     * @param array $params   Search parameters.
     *
     * @return object|null
     */
    public function findOne(array $criteria = [], array $params = [])
    {
        $results = $this->find($criteria, array_merge($params, ['limit' => 1]));
        return count($results) === 0 ? null : current($results);
    }

    /**
     * Finds a single object in the repository by its identifier.
     *
     * @param mixed $identifier Identifier to search for.
     *
     * @return object|null
     */
    public function findOneByIdentifier($identifier)
    {
        $identifierName = $this->dataMapper->identifier($this->objectClass);

        $criteria = is_string($identifierName)
            ? [$identifierName => $identifier]
            : $identifier;

        return $this->findOne($criteria);
    }

    /**
     * Handles invoking magic methods `::findBy*` and `::findOneBy*`.
     *
     * @param string $method    Called method name.
     * @param array  $arguments Array of arguments the method was called with.
     *
     * @return array|object|null
     *
     * @throws \InvalidArgumentException If any of the arguments is invalid.
     * @throws \BadMethodCallException When couldn't resolve to a valid method.
     */
    public function __call($method, array $arguments)
    {
        if (!isset($arguments[0])) {
            throw new \InvalidArgumentException(sprintf(
                'Missing 1st argument for method ::%s',
                $method
            ));
        }

        $params = [];
        if (isset($arguments[1])) {
            if (!is_array($arguments[1])) {
                throw new \InvalidArgumentException(sprintf(
                    '2nd argument for method ::%s must be an array, %s given.',
                    $method,
                    Debugger::getType($arguments[1])
                ));
            }

            $params = $arguments[1];
        }

        if (strpos($method, 'findBy') === 0) {
            $property = StringUtils::toSeparated(substr($method, 6), '_');
            return $this->find([$property => $arguments[0]], $params);
        }

        if (strpos($method, 'findOneBy') === 0) {
            $property = StringUtils::toSeparated(substr($method, 9), '_');
            return $this->findOne([$property => $arguments[0]], $params);
        }

        throw new \BadMethodCallException(sprintf(
            'Call to undefined method %s::%s',
            __CLASS__,
            $method
        ));
    }

    /**
     * Counts objects in the repository that match the given criteria.
     *
     * @param array $criteria Search criteria.
     * @param array $params   Search params.
     *
     * @return integer
     */
    public function count(array $criteria = [], array $params = [])
    {
        // dispatch an event with the criteria, so they can be modified
        $criteriaEvent = new CriteriaEvent($criteria, $params);
        $this->eventDispatcher->dispatch(Knit::EVENT_WILL_READ, $criteriaEvent);
        
        // read the potentially updated criteria
        $criteria = $criteriaEvent->getCriteria();
        $params = $criteriaEvent->getParams();

        // and just execute the query on the store.
        return $this->store->count($this->collection, new CriteriaExpression($criteria), $params);
    }

    /**
     * Saves the object in the store.
     *
     * @param object $object Object to be saved.
     */
    public function save($object)
    {
        if (isset($object->{Knit::KEY_STORED}) && $object->{Knit::KEY_STORED} === true) {
            $this->update($object);
        } else {
            $this->add($object);
        }
    }

    /**
     * Adds the object to the store.
     *
     * @param object $object Object to be added.
     */
    public function add($object)
    {
        $this->checkObjectOwnership($object);

        // dispatch events
        $this->eventDispatcher->dispatch(Knit::EVENT_WILL_SAVE, new ObjectEvent($object));
        $this->eventDispatcher->dispatch(Knit::EVENT_WILL_ADD, new ObjectEvent($object));

        $identifier = $this->store->add($this->collection, $this->dataMapper->toArray($object));
        
        // if an identifier was returned, then make sure its set on the object
        if ($identifier) {
            $this->dataMapper->identifyWith($object, $identifier);
        }

        // mark as stored now
        $object->{Knit::KEY_STORED} = true;

        // dispatch events
        $this->eventDispatcher->dispatch(Knit::EVENT_DID_ADD, new ObjectEvent($object));
        $this->eventDispatcher->dispatch(Knit::EVENT_DID_SAVE, new ObjectEvent($object));
    }

    /**
     * Updates the object in the store.
     *
     * @param object $object Object to be updated.
     */
    public function update($object)
    {
        $this->checkObjectOwnership($object);

        // dispatch events
        $this->eventDispatcher->dispatch(Knit::EVENT_WILL_SAVE, new ObjectEvent($object));
        $this->eventDispatcher->dispatch(Knit::EVENT_WILL_UPDATE, new ObjectEvent($object));

        // figure out how to identify this object in the store
        $identifierValue = $this->dataMapper->identify($object);
        if (is_array($identifierValue)) {
            $criteria = $identifierValue;
        } else {
            $identifier = $this->dataMapper->identifier($this->objectClass);
            $criteria = [$identifier => $identifierValue];
        }

        // query the store
        $this->store->update($this->collection, new CriteriaExpression($criteria), $this->dataMapper->toArray($object));

        // also make sure that this object is marked as stored
        $object->{Knit::KEY_STORED} = true;

        // dispatch events
        $this->eventDispatcher->dispatch(Knit::EVENT_DID_UPDATE, new ObjectEvent($object));
        $this->eventDispatcher->dispatch(Knit::EVENT_DID_SAVE, new ObjectEvent($object));
    }

    /**
     * Deletes an object from the repository.
     *
     * @param object $object Object to be deleted.
     */
    public function delete($object)
    {
        $this->checkObjectOwnership($object);

        // dispatch an event that can even stop this
        $event = $this->eventDispatcher->dispatch(Knit::EVENT_WILL_DELETE, new ObjectEvent($object));
        if ($event->isPropagationStopped()) {
            return;
        }

        // figure out how to identify this object in the store
        $identifierValue = $this->dataMapper->identify($object);
        if (is_array($identifierValue)) {
            $criteria = $identifierValue;
        } else {
            $identifier = $this->dataMapper->identifier($this->objectClass);
            $criteria = [$identifier => $identifierValue];
        }

        // query the store
        $this->store->remove($this->collection, new CriteriaExpression($criteria));

        $this->eventDispatcher->dispatch(Knit::EVENT_DID_DELETE, new ObjectEvent($object));
    }

    /**
     * Do a programmatic 1:1 join between the given objects and objects retrieved from the passed repository.
     *
     * Example:
     *
     *      $productRepository->joinOne($products, $categoryRepository, 'category_id', 'id', 'category');
     *
     * The above code will query `$categoryRepository` for all categories with `id`'s found in `category_id` properties
     * of the `$products`. Then it will match `$products` and the fetched categories and call `::setCategory()` on each
     * product with the related category.
     *
     * @param array      $objects        Objects to which associated objects will be joined.
     * @param Repository $withRepository Repository from which associated objects should be fetched.
     * @param string     $leftProperty   Property of `$objects` under which the relation is stored.
     * @param string     $rightProperty  Property on which the fetched objects can be identified.
     * @param string     $targetProperty Property of `$objects` on which the associated object will be set. This is
     *                                   converted to a camelCase setter.
     * @param array      $criteria       [optional] Any additional criteria for finding the associated objects.
     * @param boolean    $excludeEmpty   [optional] Should objects that didn't find the match be removed
     *                                   from the results set? Set as `Knit::EXCLUDE_EMPTY` constant. Default: `false`.
     *
     * @return array
     */
    public function joinOne(
        array $objects,
        Repository $withRepository,
        $leftProperty,
        $rightProperty,
        $targetProperty,
        array $criteria = [],
        $excludeEmpty = false
    ) {
        // if empty collection then don't even waste time :)
        if (empty($objects)) {
            return $objects;
        }

        // select all objects for the right side of the join
        $criteria = array_merge($criteria, [
            $rightProperty => ObjectUtils::pluck($objects, $leftProperty)
        ]);
        $withObjects = $withRepository->find($criteria);
        $withObjects = ObjectUtils::indexBy($withObjects, $rightProperty);

        // do the programmatic join
        $getter = ObjectUtils::getter($leftProperty);
        $setter = ObjectUtils::setter($targetProperty);

        foreach ($objects as $i => $object) {
            $match = $object->{$getter}();

            if (isset($withObjects[$match])) {
                $object->{$setter}($withObjects[$match]);
            } elseif ($excludeEmpty === Knit::EXCLUDE_EMPTY) {
                unset($objects[$i]);
            }
        }

        return array_values($objects);
    }

    /**
     * Do a programmatic 1:n join between the given objects and objects retrieved from the passed repository.
     *
     * Example:
     *
     *      $productRepository->joinMany($products, $tagsRepository, 'id', 'product_id', 'tags');
     *
     * The above code will query `$tagsRepository` for all tags that have `id`'s of the `$products` in `product_id`
     * property. Then it will match `$products` and the fetched tags and set all related tags on each product by
     * calling `::setTags()` with the related tags.
     *
     * @param array      $objects        Objects to which associated objects will be joined.
     * @param Repository $withRepository Repository from which associated objects should be fetched.
     * @param string     $leftProperty   Property of `$objects` under which the relation is stored.
     * @param string     $rightProperty  Property on which the fetched objects can be identified.
     * @param string     $targetProperty Property of `$objects` on which the associated object will be set. This is
     *                                   converted to a camelCase setter.
     * @param array      $criteria       [optional] Any additional criteria for finding the associated objects.
     * @param array      $params         [optional] Any additional search parameters for finding the associated objects.
     * @param boolean    $excludeEmpty   [optional] Should objects that didn't find the match be removed
     *                                   from the results set? Set as `Knit::EXCLUDE_EMPTY` constant. Default: `false`.
     *
     * @return array
     */
    public function joinMany(
        array $objects,
        Repository $withRepository,
        $leftProperty,
        $rightProperty,
        $targetProperty,
        array $criteria = [],
        array $params = [],
        $excludeEmpty = false
    ) {
        // if empty collection then don't even waste time :)
        if (empty($objects)) {
            return $objects;
        }

        // select all objects for the right side of the join
        $criteria = array_merge($criteria, [
            $rightProperty => ObjectUtils::pluck($objects, $leftProperty)
        ]);
        $withObjects = $withRepository->find($criteria, $params);
        $withObjects = ObjectUtils::groupBy($withObjects, $rightProperty);

        // do the programmatic join
        $getter = ObjectUtils::getter($leftProperty);
        $setter = ObjectUtils::setter($targetProperty);

        foreach ($objects as $i => $object) {
            $match = $object->{$getter}();

            if (isset($withObjects[$match])) {
                $object->{$setter}($withObjects[$match]);
            } elseif ($excludeEmpty === Knit::EXCLUDE_EMPTY) {
                unset($objects[$i]);
            }
        }

        return array_values($objects);
    }

    /**
     * Maps multiple results from the store to objects.
     *
     * @param array $results Results data.
     *
     * @return array
     */
    protected function mapResults(array $results)
    {
        $objects = [];
        foreach ($results as $result) {
            $objects[] = $this->mapResult($result);
        }
        return $objects;
    }

    /**
     * Maps a result retrieved from the store to an object and marks is as manged and stored by Knit.
     *
     * @param array $data Result data.
     *
     * @return object
     */
    protected function mapResult(array $data)
    {
        $object = $this->createWithData($data);
        $object->{Knit::KEY_STORED} = true;
        return $object;
    }

    /**
     * Creates an object managed by this repository and fills it with the given data.
     *
     * @param array  $data [optional] Data to fill the new object.
     *
     * @return object
     */
    public function createWithData(array $data = [])
    {
        $object = new $this->objectClass;
        $this->dataMapper->fromArray($object, $data);
        return $object;
    }

    /**
     * Updates the object with the given data.
     *
     * @param object $object Object to fill with data.
     * @param array  $data   Data to fill the object.
     */
    public function updateWithData($object, array $data = [])
    {
        $this->checkObjectOwnership($object);
        $this->dataMapper->fromArray($object, $data);
    }

    /**
     * Checks if the given object can be managed by this repository. Throws an exception if not.
     *
     * @param object $object Object to be verified.
     *
     * @return boolean
     *
     * @throws \LogicException When the check failed.
     */
    protected function checkObjectOwnership($object)
    {
        $class = ltrim(get_class($object), '\\');
        if ($class !== $this->objectClass) {
            throw new \LogicException(sprintf(
                'Cannot use %s repository to manage objects of class %s',
                $this->objectClass,
                $class
            ));
        }

        return true;
    }

    /**
     * Returns name of the class that this repository manages.
     *
     * @return string
     */
    public function getObjectClass()
    {
        return $this->objectClass;
    }

    /**
     * Returns name of the collection (if any) in which objects of this repository are stored.
     *
     * @return string
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Returns the store driver.
     *
     * @return StoreInterface
     */
    public function getStore()
    {
        return $this->store;
    }

    /**
     * Returns the data mapper.
     *
     * @return DataMapperInterface
     */
    public function getDataMapper()
    {
        return $this->dataMapper;
    }

    /**
     * Returns the event dispatcher.
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }
}
