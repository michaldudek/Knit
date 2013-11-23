<?php
/**
 * Knit ORM.
 * 
 * @package Knit
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit;

use MD\Foundation\Debug\Debugger;

use Knit\Exceptions\ExtensionNotDefinedException;
use Knit\Exceptions\RepositoryDefinedException;
use Knit\Exceptions\StoreDefinedException;
use Knit\Exceptions\NoStoreException;
use Knit\Exceptions\ValidatorNotDefinedException;
use Knit\Entity\Repository;
use Knit\Extensions\ExtensionInterface;
use Knit\Store\StoreInterface;
use Knit\Validators\AllowedValuesValidator;
use Knit\Validators\EmailValidator;
use Knit\Validators\EqualsValidator;
use Knit\Validators\MaxLengthValidator;
use Knit\Validators\MaxValidator;
use Knit\Validators\MinLengthValidator;
use Knit\Validators\MinValidator;
use Knit\Validators\RequiredValidator;
use Knit\Validators\TypeValidator;
use Knit\Validators\UniqueValidator;
use Knit\Validators\ValidatorInterface;

class Knit
{

    /**
     * Registry of all defined stores.
     * 
     * @var array
     */
    protected $stores = array();

    /**
     * Map of all instantiated repositories assigned to entity classes.
     * 
     * @var array
     */
    protected $repositories = array();

    /**
     * Map of entities and their custom store names.
     * 
     * @var array
     */
    protected $entityStores = array();

    /**
     * Map of entities and their custom repository classes.
     * 
     * @var array
     */
    protected $entityRepositories = array();

    /**
     * Registry of all registered validators.
     * 
     * @var array
     */
    protected $validators = array();

    /**
     * Constructor.
     * 
     * @param StoreInterface $defaultStore Default store that will be used with all repositories (if no other store defined).
     * @param array $options [optional] Array of options.
     */
    public function __construct(StoreInterface $defaultStore, array $options = array()) {
        $this->registerStore('default', $defaultStore);

        $options = array_merge(array(
            'stores' => array()
        ), $options);

        // register other stores passed in options
        foreach($options['stores'] as $storeName => $store) {
            $this->registerStore($storeName, $store);
        }

        // register the default validators
        $validators = array(
            'allowedValues' => new AllowedValuesValidator(),
            'email' => new EmailValidator(),
            'equals' => new EqualsValidator(),
            'maxLength' => new MaxLengthValidator(),
            'max' => new MaxValidator(),
            'minLength' => new MinLengthValidator(),
            'min' => new MinValidator(),
            'required' => new RequiredValidator(),
            'type' => new TypeValidator(),
            'unique' => new UniqueValidator()
        );
        foreach($validators as $name => $validator) {
            $this->registerValidator($name, $validator);
        }
    }

    /**
     * Registers a repository for the given entity class.
     * 
     * @param string $entityClass Name of the entity class to use this repository with.
     * @param Repository $repository Repository to register.
     * 
     * @throws RepositoryDefinedException When trying to overwrite already defined entity class repository.
     */
    public function registerRepository($entityClass, Repository $repository) {
        if (isset($this->repositories[$entityClass])) {
            throw new RepositoryDefinedException('Cannot overwrite already assigned repository. Tried to set repository for "'. $entityClass .'".');
        }

        $this->repositories[$entityClass] = $repository;
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Registers an extension for future use.
     * 
     * @param string $name Name of the extension.
     * @param ExtensionInterface $extension Extension instance.
     */
    public function registerExtension($name, ExtensionInterface $extension) {
        $this->extensions[$name] = $extension;
    }

    /**
     * Returns the requested extension.
     * 
     * @param string $name Name of the extension.
     * @return ExtensionInterface
     * 
     * @throws ExtensionNotDefinedException When extension could not be found.
     */
    public function getExtension($name) {
        if (!isset($this->extensions[$name])) {
            throw new ExtensionNotDefinedException('Could not find extension registered under the name "'. $name .'".');
        }

        return $this->extensions[$name];
    }

    /**
     * Registers a validator for future use.
     * 
     * @param string $name Name of the validator.
     * @param ValidatorInterface $validator Validator instance.
     */
    public function registerValidator($name, ValidatorInterface $validator) {
        $this->validators[$name] = $validator;
    }

    /**
     * Returns the requested validator.
     * 
     * @param string $name Name of the validator.
     * @return ValidatorInterface
     * 
     * @throws ValidatorNotDefinedException When validator could not be found.
     */
    public function getValidator($name) {
        if (!isset($this->validators[$name])) {
            throw new ValidatorNotDefinedException('Could not find validator registered under the name "'. $name .'".');
        }

        return $this->validators[$name];
    }

    /**
     * Registers a persistent store to be later used with repositories.
     * 
     * @param string $name Name of the store to register. Has to be unique.
     * @param StoreInterface $store Persistent store to be registered.
     * 
     * @throws StoreDefinedException When trying to overwrite already defined store.
     */
    public function registerStore($name, StoreInterface $store) {
        if (isset($this->stores[$name])) {
            throw new StoreDefinedException('Cannot overwrite already defined store. Tried to set store "'. $name .'".');
        }

        $this->stores[$name] = $store;
    }

    /**
     * Returns store registered under the given name.
     * 
     * @param string $name [optional] Name of the store to get. If no name given then default store will be returned.
     * @return StoreInterface
     * 
     * @throws NoStoreException When no such store is defined.
     */
    public function getStore($name = 'default') {
        if (!isset($this->stores[$name])) {
            throw new NoStoreException('No store called "'. $name .'" defined.');
        }

        return $this->stores[$name];
    }

    /**
     * Returns name of a registered store that should be used with the given entity.
     * 
     * If no store for entity has been previously registered then it will return 'default'.
     * 
     * @param string $entityClass Class name of the entity.
     * @return string
     */
    public function getStoreNameForEntity($entityClass) {
        return isset($this->entityStores[$entityClass]) ? $this->entityStores[$entityClass] : 'default';
    }

    /**
     * Sets a registered store name that should be used with the given entity.
     * 
     * @param string $entityClass Class name of the entity.
     * @param string $store Name of a registered store.
     * 
     * @throws StoreDefinedException When trying to overwrite a store already defined for an entity.
     * @throws NoStoreException When no such store has been defined yet.
     */
    public function setStoreNameForEntity($entityClass, $store) {
        if (isset($this->entityStores[$entityClass])) {
            throw new StoreDefinedException('Cannot overwrite already defined store for an entity. Tried to set store called "'. $store .'" for "'. $entityClass .'".');
        }

        // store with this name has to be defined as well
        if (!isset($this->stores[$store])) {
            throw new NoStoreException('No store called "'. $store .'" defined. Register it in Knit before setting it for an entity.');
        }

        $this->entityStores[$entityClass] = $store;
    }

    /**
     * Sets repository class name for the given entity.
     * 
     * @param string $entityClass Class name of the entity.
     * @param string $repositoryClass Class name of the repository for that entity.
     * 
     * @throws RepositoryDefinedException When repository class has already been defined for this entity.
     */
    public function setRepositoryClassForEntity($entityClass, $repositoryClass) {
        if (isset($this->entityRepositories[$entityClass])) {
            throw new RepositoryDefinedException('Cannot overwrite already defined repository for an entity. Tried to set repository "'. $repositoryClass .'" for "'. $entityClass .'".');
        }

        $this->entityRepositories[$entityClass] = $repositoryClass;
    }

    /** 
     * Returns repository class for the given entity class.
     * 
     * @param string $entityClass Class name of the entity.
     * @return string
     */
    public function getRepositoryClassForEntity($entityClass) {
        // check if there was a repository class for this entity defined
        if (isset($this->entityRepositories[$entityClass])) {
            return $this->entityRepositories[$entityClass];
        }

        // look for repository by appending 'Repository' to the end of class name
        // or if not found, use the generic Repository class
        return class_exists('\\'. $entityClass .'Repository') ? $entityClass .'Repository' : 'Knit\Entity\Repository';
    }

    /**
     * Returns repository for the given entity.
     * 
     * @param string $entityClass Class name (full, with namespace) of the entity.
     * @return Repository
     * 
     * @throws \RuntimeException When the entity repository doesn't extend Knit\Entity\Repository class.
     */
    public function getRepository($entityClass) {
        if (isset($this->repositories[$entityClass])) {
            return $this->repositories[$entityClass];
        }

        $repositoryClass = $this->getRepositoryClassForEntity($entityClass);

        // must extend Repository
        if (!Debugger::isExtending($repositoryClass, 'Knit\Entity\Repository', true)) {
            throw new \RuntimeException('Entity repository "'. $repositoryClass .'" for "'. $entityClass .'" must extend "Knit\Entity\Repository".');
        }

        // get store for this repository
        $entityStore = $this->getStoreNameForEntity($entityClass);
        $store = $this->getStore($entityStore);

        // instantiate it
        $repository = new $repositoryClass($entityClass, $store, $this);

        $this->registerRepository($entityClass, $repository);
        return $repository;
    }

}