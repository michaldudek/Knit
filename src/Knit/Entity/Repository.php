<?php
/**
 * Generic repository class for entities.
 * 
 * @package Knit
 * @subpackage Entity
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Entity;

use RuntimeException;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Utils\ObjectUtils;
use MD\Foundation\Utils\StringUtils;

use Splot\EventManager\EventManager;

use Knit\Criteria\CriteriaExpression;
use Knit\Entity\AbstractEntity;
use Knit\Exceptions\StructureNotDefinedException;
use Knit\Store\StoreInterface;
use Knit\KnitOptions;
use Knit\Knit;

use Knit\Events\WillAddEntity;
use Knit\Events\DidAddEntity;
use Knit\Events\WillBindDataToEntity;
use Knit\Events\DidBindDataToEntity;
use Knit\Events\WillCreateEntity;
use Knit\Events\DidCreateEntity;
use Knit\Events\WillDeleteEntity;
use Knit\Events\DidDeleteEntity;
use Knit\Events\WillReadFromStore;
use Knit\Events\DidReadFromStore;
use Knit\Events\WillSaveEntity;
use Knit\Events\DidSaveEntity;
use Knit\Events\WillUpdateEntity;
use Knit\Events\DidUpdateEntity;

class Repository
{

    /**
     * Reference to Knit instance.
     * 
     * @var Knit
     */
    protected $knit;

    /**
     * Class name of the assigned entity.
     * 
     * @var string
     */
    protected $entityClass;

    /**
     * Store to use with this repository.
     * 
     * @var StoreInterface
     */
    protected $store;

    /**
     * Name of the collection/table in the persistent store
     * where objects from this repository are stored.
     * 
     * Collection name can come from the constructor of repository
     * but also from a static variable $_collection in the entity itself.
     * 
     * @var string
     */
    protected $collection;

    /**
     * Name of the ID / Primary Key property for the entity.
     * 
     * @var string
     */
    protected $idProperty = 'id';

    /**
     * Information about properties and structure of the entity.
     * 
     * @var array
     */
    protected $entityStructure = array();

    /**
     * Event manager.
     * 
     * @var EventManager
     */
    protected $eventManager;

    /**
     * Constructor.
     * 
     * @param string $entityClass Class name of the assigned entity that this repository will be managing.
     * @param StoreInterface $store Store to use with this repository.
     * @param string $collection [optional] Name of the collection/table in the persistent store.
     */
    public function __construct($entityClass, StoreInterface $store, Knit $knit, $collection = null) {
        $this->knit = $knit;
        $this->entityClass = $entityClass;
        $this->store = $store;
        $this->collection = (isset($entityClass::$_collection) && !empty($entityClass::$_collection)) ? $entityClass::$_collection : $collection;

        if (empty($this->collection)) {
            throw new RuntimeException('No collection defined for entity "'. $entityClass .'". Either pass it as 3rd argument of repository constructor or set static "'. $entityClass .'::$_collection" variable.');
        }

        // instantiate event manager for this repository
        $this->eventManager = new EventManager();

        // load info about the entity structure
        $this->getEntityStructure();
    }

    /*****************************************************
     * FACTORY METHODS
     *****************************************************/
    /**
     * Return an array of entities based on the given criteria and parameters.
     * 
     * @param array $criteria [optional] Array of criteria.
     * @param array $params [optional] Array of optional parameters (like start, limit, etc).
     * @return array Array of requested entities.
     */
    public function find(array $criteria = array(), array $params = array()) {
        $event = new WillReadFromStore($criteria, $params);
        if ($this->getEventManager()->trigger($event)) {
            return array();
        }

        $criteria = $event->getCriteria();
        $params = $event->getParams();
        $result = $this->store->find($this->collection, $this->parseCriteriaArray($criteria), $params);
        
        $entities = array();
        foreach($result as $item) {
            $entities[] = $this->instantiateWithData($item);
        }

        $this->getEventManager()->trigger(new DidReadFromStore($entities));
        
        return $entities;
    }
    
    /**
     * Will find one instance of the entity based on passed criteria.
     * 
     * @param array $criteria [optional] Array of criteria.
     * @param array $params [optional] Array of optional parameters.
     * @return AbstractEntity|null
     */
    public function findOne(array $criteria = array(), array $params = array()) {
        $entities = $this->find($criteria, array_merge($params, array(
            'limit' => 1
        )));

        if (empty($entities)) {
            return null;
        }

        $entity = array_shift($entities);

        return $entity;
    }
    
    /**
     * Will find one instance of the entity found by the given id property.
     * 
     * @param mixed $id Value of the ID. Can also be an array of ID's.
     * @return object|null
     */
    public function findById($id) {
        $idProperty = $this->idProperty;

        // if ID is an array then search for multiple entities
        if (is_array($id)) {
            return $this->find(array(
                $idProperty => $id
            ));
        }

        return $this->findOne(array(
            $idProperty => $id
        ));
    }
    
    /**
     * Provides an instance of the entity based on the given data.
     * 
     * If none found in store then it will create it using this data.
     * 
     * @param array $data Array of criteria/data.
     * @return object
     */
    public function provide(array $data) {
        $object = $this->findOne($data);
        if (!$object) {
            $object = $this->createWithData($data);
            $this->save($object);
        }

        return $object;
    }

    /**
     * Counts how many persisted objects of the entity match the given criteria (if any).
     * 
     * @param array $criteria [optional] Array of optional criteria.
     * @param array $params [optional] Array of optional params.
     * @return mixed
     */
    public function count(array $criteria = array(), array $params = array()) {
        return $this->store->count($this->collection, $this->parseCriteriaArray($criteria), $params);
    }

    /**
     * Joins one collection of entities (fetched from a store) into another (provided in 1st argument) by doing 1:1 join.
     * 
     * Example usage:
     *     $repository->joinOne($users, Profile::__class(), 'userId', 'id', 'profile', array(), KnitOptions::EXCLUDE_EMPTY);
     * This will fetch profiles from their store and join them into $users based on "profile.userId = user.id" and store the profiles in user's "profile" property.
     * It will also remove all users that don't have profiles created from the $users collection.
     * 
     * @param array $entities Array collection of entities that new entities will be joined into. Passed via reference.
     * @param Repository|string $joinEntity Name of an entity class that will be joined into the collection of entities. Can also be a repository instance.
     * @param string $joinEntityProperty Name of a property from $joinEntity on which an "equals" check will be done, ie. "$joinEntityProperty = $entityProperty".
     * @param string $entityProperty Name of a property from $entities that the $joinEntity will be checked against, ie. "$joinEntityProperty = $entityProperty".
     * @param string $intoProperty Name of a property that the joined entities will be saved to in the parent property.
     * @param array $criteria [optional] Array of any additional criteria on which the join entities will be fetched from their store.
     * @param mixed $exclude [optional] Pass KnitOptions::EXCLUDE_EMPTY here if you want parent entities that don't have a joined entity to be removed from the collection. Default: will not be removed.
     * @return array
     */
    public function joinOne(array &$entities, $joinEntity, $joinEntityProperty, $entityProperty, $intoProperty, array $criteria = array(), $exclude = null) {
        // if empty collection then don't even bother
        if (empty($entities)) {
            return $entities;
        }

        // fetch the entities that we want to join
        $joinEntityRepository = ($joinEntity instanceof self) ? $joinEntity : $this->knit->getRepository($joinEntity);

        $joinEntities = $joinEntityRepository->find(array_merge($criteria, array(
            $joinEntityProperty => ObjectUtils::keyFilter($entities, $entityProperty)
        )));
        $joinEntities = ObjectUtils::keyExplode($joinEntities, $joinEntityProperty);

        // now that we have them, let's do the programmatic join
        foreach($entities as $entity) {
            if (isset($joinEntities[$entity->$entityProperty])) {
                $entity->$intoProperty = $joinEntities[$entity->$entityProperty];
            } else {
                $entity->$intoProperty = null;
            }
        }

        // remove those entities that couldn't be joined with anything
        if ($exclude === KnitOptions::EXCLUDE_EMPTY) {
            foreach($entities as $i => $entity) {
                if (!isset($entity->$intoProperty) || empty($entity->$intoProperty)) {
                    unset($entities[$i]);
                }
            }
        }

        return $entities;
    }

    /**
     * Joins one collection of entities (fetched from a store) into another (provided in 1st argument) by doing 1:n join.
     * 
     * Example usage:
     *     $repository->joinMany($users, Message::__class(), 'userId', 'id', 'messages', array('deleted' => false), array('orderBy' => 'date'));
     * This will fetch all messages (that haven't been deleted) for the given users based on "message.userId = user.id" and store the messages in user's "messages" property.
     * The messages will be ordered by "date" property.
     * 
     * @param array $entities Array collection of entities that new entities will be joined into. Passed via reference.
     * @param Repository|string $joinEntity Name of an entity class that will be joined into the collection of entities. Can also be a repository instance.
     * @param string $joinEntityProperty Name of a property from $joinEntity on which an "equals" check will be done, ie. "$joinEntityProperty = $entityProperty".
     * @param string $entityProperty Name of a property from $entities that the $joinEntity will be checked against, ie. "$joinEntityProperty = $entityProperty".
     * @param string $intoProperty Name of a property that the joined entities will be saved to in the parent property.
     * @param array $criteria [optional] Array of any additional criteria on which the join entities will be fetched from their store.
     * @param array $params [optional] Array of any additional params (like "orderBy") that will be used for fetching the join entities.
     * @param mixed $exclude [optional] Pass KnitOptions::EXCLUDE_EMPTY here if you want parent entities that don't have a joined entity to be removed from the collection. Default: will not be removed.
     * @return array
     */
    public function joinMany(array &$entities, $joinEntity, $joinEntityProperty, $entityProperty, $intoProperty, array $criteria = array(), array $params = array(), $exclude = null) {
        // if empty collection then don't even bother
        if (empty($entities)) {
            return $entities;
        }

        // fetch the entities that we want to join
        $joinEntityRepository = ($joinEntity instanceof self) ? $joinEntity : $this->knit->getRepository($joinEntity);

        $joinEntities = $joinEntityRepository->find(array_merge($criteria, array(
            $joinEntityProperty => ObjectUtils::keyFilter($entities, $entityProperty)
        )), $params);
        $joinEntities = ObjectUtils::categorizeByKey($joinEntities, $joinEntityProperty);

        foreach($entities as $entity) {
            if (isset($joinEntities[$entity->$entityProperty])) {
                $entity->$intoProperty = $joinEntities[$entity->$entityProperty];
            } else {
                $entity->$intoProperty = array();
            }
        }

        // remove those entities that couldn't be joined with anything
        if ($exclude === KnitOptions::EXCLUDE_EMPTY) {
            foreach($entities as $i => $entity) {
                if (!isset($entity->$intoProperty) || empty($entity->$intoProperty)) {
                    unset($entities[$i]);
                }
            }
        }

        return $entities;
    }

    /**
     * Create "magic" functions for findBy* and fineOneBy*
     * 
     * @param string $name Method name
     * @param array $arguments Array of arguments.
     * @return AbstractEntity|array|null
     */
    public function __call($methodName, $arguments) {
        $structure = $this->getEntityStructure();

        // is it "findBy*" magic function?
        if (stripos($methodName, 'findBy') === 0) {
            // require at least one argument
            if (!isset($arguments[0])) {
                $trace = debug_backtrace();
                $file = isset($trace[0]['file']) ? $trace[0]['file'] : 'unknown';
                $line = isset($trace[0]['line']) ? $trace[0]['line'] : 'unknown';
                trigger_error('Function "'. get_called_class() .'::'. $methodName .'()" requires one argument, none given in '. $file .' on line '. $line, E_USER_ERROR);
            }

            $property = lcfirst(substr($methodName, 6));
            $property = (isset($structure[$property])) ? $property : StringUtils::toSeparated($property, '_');

            // redirect to the "find" method
            return $this->find(array(
                $property => $arguments[0]
            ));
        
        // or is it "fineOneBy*" magic function?
        } else if (stripos($methodName, 'findOneBy') === 0) {
            // require at least one argument
            if (!isset($arguments[0])) {
                $trace = debug_backtrace();
                $file = isset($trace[0]['file']) ? $trace[0]['file'] : 'unknown';
                $line = isset($trace[0]['line']) ? $trace[0]['line'] : 'unknown';
                trigger_error('Function "'. get_called_class() .'::'. $methodName .'()" requires one argument, none given in '. $file .' on line '. $line, E_USER_ERROR);
            }

            $property = lcfirst(substr($methodName, 9));
            $property = (isset($structure[$property])) ? $property : StringUtils::toSeparated($property, '_');

            // redirect to the "findOne" method
            return $this->findOne(array(
                $property => $arguments[0]
            ));
        }

        // undefined method called!
        $trace = debug_backtrace();
        trigger_error('Call to undefined method '. get_called_class() .'::'. $methodName .'() in '. $trace[0]['file'] .' on line '. $trace[0]['line'], E_USER_ERROR);
        return null;
    }

    /*****************************************************
     * PERSISTENCE
     *****************************************************/
    /**
     * Will save the entity to its persistent store.
     * 
     * @param AbstractEntity $entity
     */
    public function save(AbstractEntity $entity) {
        $this->checkEntityOwnership($entity);

        if ($this->getEventManager()->trigger(new WillSaveEntity($entity))) {
            return;
        }

        if ($entity->_getId()) {
            $this->update($entity);
        } else {
            $this->add($entity);
        }

        $this->getEventManager()->trigger(new DidSaveEntity($entity));
    }
    
    /**
     * Will insert the entity as a new instance in its persistent store.
     * 
     * @param AbstractEntity $entity
     * 
     * @throws \RuntimeException When trying to add an entity that has to persistent properties.
     */
    public function add(AbstractEntity $entity) {
        $this->checkEntityOwnership($entity);
        
        if ($this->getEventManager()->trigger(new WillAddEntity($entity))) {
            return;
        }

        $properties = $this->getPropertiesForStore($entity);
        if (empty($properties)) {
            throw new \RuntimeException('Cannot add an entity "'. $this->entityClass .'" with no persistent properties to a persistent store.');
        }

        $id = $this->store->add($this->collection, $properties);
        $entity->_setId($id);

        $this->getEventManager()->trigger(new DidAddEntity($entity));
    }
    
    /**
     * Will update the entity in the persistent store.
     * 
     * @param AbstractEntity $entity
     */
    public function update(AbstractEntity $entity) {
        $this->checkEntityOwnership($entity);

        if ($this->getEventManager()->trigger(new WillUpdateEntity($entity))) {
            return;
        }

        $idProperty = $this->getIdProperty();
        $criteria = $this->parseCriteriaArray(array(
            $idProperty => $entity->_getId()
        ));
        
        $this->store->update($this->collection, $criteria, $this->getPropertiesForStore($entity));

        $this->getEventManager()->trigger(new DidUpdateEntity($entity));
    }
    
    /**
     * Will remove the entity from the persistent store.
     * 
     * @param AbstractEntity $entity
     */
    public function delete(AbstractEntity $entity) {
        $this->checkEntityOwnership($entity);

        if ($this->getEventManager()->trigger(new WillDeleteEntity($entity))) {
            return;
        }

        $idProperty = $this->getIdProperty();
        $criteria = $this->parseCriteriaArray(array(
            $idProperty => $entity->_getId()
        ));

        $this->store->delete($this->collection, $criteria);

        $this->getEventManager()->trigger(new DidDeleteEntity($entity));
    }

    /**
     * Will remove instances of the given entities from the persistent store.
     * 
     * @param array $entities Array collection of entities.
     */
    public function deleteMulti(array $entities) {
        $idProperty = $this->getIdProperty();
        $entitiesIds = array();

        foreach($entities as $entity) {
            $this->checkEntityOwnership($entity);

            if ($this->getEventManager()->trigger(new WillDeleteEntity($entity))) {
                continue;
            }

            $entitiesIds[] = $entity->_getId();
        }

        $this->deleteOnCriteria(array(
            $idProperty => $entitiesIds
        ));

        foreach($entities as $entity) {
            $entity->_setId(null);
            $this->getEventManager()->trigger(new DidDeleteEntity($entity));
        }
    }

    /**
     * Will remove any instances of the entity that match the given criteria.
     * 
     * @param array $criteria Criteria on which to delete objects. Same as criteria passed to any other factory methods.
     */
    public function deleteOnCriteria(array $criteria) {
        $criteria = $this->parseCriteriaArray($criteria);
        $this->store->delete($this->collection, $criteria);
    }

    /*****************************************************
     * MAPPING
     *****************************************************/
    /**
     * Returns information about the entity's structure,
     * either from the entity class (if defined programmatically) or from the store (e.g. SHOW TABLE).
     * 
     * @return array
     * 
     * @throws StructureNotDefinedException When couldn't find any definition of the structure.
     */
    public function getEntityStructure() {
        if (!empty($this->entityStructure)) {
            return $this->entityStructure;
        }

        // check for structure definition inside the entity class
        $entityClass = $this->entityClass;
        $structure = $entityClass::_getStructure();
        if (!empty($structure)) {
            // store the structure
            $this->entityStructure = $structure;
            return $structure;
        }

        // no structure defined in the entity class so let's get it from the store
        $structure = $this->store->structure($this->collection);
        if (!empty($structure)) {
            // store the structure
            $this->entityStructure = $structure;
            return $structure;
        }

        // if couldn't find non-empty entity structure then throw an exception
        throw new StructureNotDefinedException('Structure not defined for entity "'. $this->entityClass .'". If you are using NoSQL store then you have to specify structure in "'. $this->entityClass .'::$_structure" variable (must conform to structure array), otherwise it should be automatically read from the database.');
    }

    /**
     * Returns only those properties of the entity that can be saved directly in the persistent store.
     * It filters out all properties that have been added by the user.
     * 
     * @return array Array of filtered properties.
     */
    public function getPropertiesForStore(AbstractEntity $entity) {
        $this->checkEntityOwnership($entity);

        $properties = $entity->_getProperties();
        $structure = $this->getEntityStructure();
        $storeProperties = array();

        foreach($properties as $var => $value) {
            if (isset($structure[$var])) {
                $storeProperties[$var] = $value;
            }
        }
        
        return $storeProperties;
    }

    /**
     * Instantiate the entity with the given parameters. Usually called straight after getting results from the store.
     * 
     * @param array $data [optional] Properties of the entity.
     * @return object Instance of the entity.
     */
    protected function instantiateWithData(array $data = array()) {
        $event = new WillBindDataToEntity($data);
        $this->getEventManager()->trigger($event);
        $data = $event->getData();

        $entityClass = $this->entityClass;
        $entity = new $entityClass();

        // store reference to this repository
        $entity->_setRepository($this);

        $entity->_setProperties($data);

        $this->getEventManager()->trigger(new DidBindDataToEntity($entity));

        return $entity;
    }

    /**
     * Will create an instance of the entity and fill its properties with the passed data.
     * 
     * @param array $data [optional] Array of properties for the entity to have.
     * @return AbstractEntity
     */
    public function createWithData(array $data = array()) {
        $event = new WillCreateEntity($data);
        $this->getEventManager()->trigger($event);
        $data = $event->getData();

        $entityClass = $this->entityClass;
        $entity = new $entityClass();

        // store reference to this repository
        $entity->_setRepository($this);
        
        foreach($data as $var => $value) {
            call_user_func_array(array($entity, ObjectUtils::setter($var)), array($value));
        }

        $this->getEventManager()->trigger(new DidCreateEntity($entity));

        return $entity;
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns the name of the entity property that is it's ID.
     * 
     * @return string
     */
    public function getIdProperty() {
        return $this->idProperty;
    }

    /**
     * Sets the name of the entity property that is it's ID.
     * 
     * @param string $idProperty
     */
    public function setIdProperty($idProperty) {
        $this->idProperty = $idProperty;
    }

    /**
     * Returns the event manager for this repository.
     * 
     * @return EventManager
     */
    public function getEventManager() {
        return $this->eventManager;
    }

    /*****************************************************
     * HELPERS
     *****************************************************/
    /**
     * Check if the given entity belongs to this repository and if not throw an exception.
     * 
     * @param AbstractEntity $entity Entity to be verified.
     * 
     * @throws \InvalidArgumentException When the given entity does not belong to this repository.
     */
    protected function checkEntityOwnership(AbstractEntity $entity) {
        // check if this entity belongs to this repository
        if (Debugger::getClass($entity) !== $this->entityClass) {
            throw new \InvalidArgumentException('The given entity does not belong to repository "'. Debugger::getClass($this) .'". Entity should be of class "'. $this->entityClass .'", "'. Debugger::getClass($entity) .'" given.');
        }
    }

    /**
     * Parses criteria array which is used in factory methods.
     * 
     * @param array $criteria Array of criteria to be parsed.
     * @return CriteriaExpression
     */
    protected function parseCriteriaArray(array $criteria = array()) {
        // cast proper types on the values
        $criteria = $this->castCriteriaArrayTypes($criteria);
        return new CriteriaExpression($criteria);
    }

    /**
     * Casts proper types onto criteria array.
     * 
     * @param array $criteria Array of criteria that need their types fixed.
     * @return array
     */
    protected function castCriteriaArrayTypes(array $criteria = array()) {
        $entityClass = $this->entityClass;
        $dummyEntity = new $entityClass();
        $dummyEntity->_setRepository($this);

        foreach($criteria as $key => $value) {
            // if value is an array and the key is a logic key then we have a sub property
            if (is_array($value) && ($key === KnitOptions::LOGIC_OR || $key === KnitOptions::LOGIC_AND)) {
                $criteria[$key] = $this->castCriteriaArrayTypes($value);
                continue;
            }

            $keyArray = explode(':', $key);
            $property = $keyArray[0];

            // if value is an array then we also need to cast proper types on it
            if (is_array($value)) {
                foreach($value as $k => $val) {
                    $value[$k] = $dummyEntity->_castPropertyType($property, $val);
                }
                $criteria[$key] = $value;
            } else {
                $criteria[$key] = $dummyEntity->_castPropertyType($property, $value);
            }
        }

        return $criteria;
    }

}