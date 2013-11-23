<?php
namespace Knit\Tests;

use Knit\Tests\Fixtures\Auto;
use Knit\Tests\Fixtures\NotExtending;
use Knit\Tests\Fixtures\Predefined;
use Knit\Tests\Fixtures\Standard;

use Knit\Knit;

/**
 * @covers \Knit\Knit
 */
class KnitTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructingAndRegisteringStores() {
        $defaultStore = $this->getMock('Knit\Store\StoreInterface');
        $anotherStore = $this->getMock('Knit\Store\StoreInterface');
        $dummyStore = $this->getMock('Knit\Store\StoreInterface');
        $knit = new Knit($defaultStore, array(
            'stores' => array(
                'another' => $anotherStore,
                'dummy' => $dummyStore
            )
        ));

        $this->assertSame($defaultStore, $knit->getStore('default'));
        $this->assertSame($anotherStore, $knit->getStore('another'));
        $this->assertSame($dummyStore, $knit->getStore('dummy'));

        // also make sure all default validators are registered
        $ns = 'Knit\Validators\\';
        $validators = array(
            'equals' => $ns .'EqualsValidator',
            'maxLength' => $ns .'MaxLengthValidator',
            'max' => $ns .'MaxValidator',
            'minLength' => $ns .'MinLengthValidator',
            'min' => $ns .'MinValidator',
            'required' => $ns .'RequiredValidator',
            'type' => $ns .'TypeValidator',
        );
        foreach($validators as $name => $class) {
            $this->assertInstanceOf($class, $knit->getValidator($name));
        }

        return $knit;
    }

    public function testRegisteringAndGettingStores() {
        $knit = $this->testConstructingAndRegisteringStores();

        $testStore = $this->getMock('Knit\Store\StoreInterface');
        $knit->registerStore('test', $testStore);

        $this->assertSame($testStore, $knit->getStore('test'));
    }

    /**
     * @expectedException \Knit\Exceptions\StoreDefinedException
     */
    public function testOverwritingStore() {
        $knit = $this->testConstructingAndRegisteringStores();

        $dummyStore = $this->getMock('Knit\Store\StoreInterface');
        $knit->registerStore('dummy', $dummyStore);
    }

    /**
     * @expectedException \Knit\Exceptions\NoStoreException
     */
    public function testGettingUndefinedStore() {
        $knit = $this->testConstructingAndRegisteringStores();

        $knit->getStore('undefined');
    }

    public function testRegisteringRepositories() {
        $knit = $this->testConstructingAndRegisteringStores();

        $contactRepository = $this->getMockBuilder('Knit\Entity\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $messageRepository = $this->getMockBuilder('Knit\Entity\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $knit->registerRepository('Contact', $contactRepository);
        $knit->registerRepository('Message', $messageRepository);

        $this->assertSame($contactRepository, $knit->getRepository('Contact'));
        $this->assertSame($messageRepository, $knit->getRepository('Message'));
    }

    /**
     * @expectedException \Knit\Exceptions\RepositoryDefinedException
     */
    public function testOverwritingRepository() {
        $knit = $this->testConstructingAndRegisteringStores();

        $contactRepository = $this->getMockBuilder('Knit\Entity\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $knit->registerRepository('Contact', $contactRepository);

        $invalidRepository = $this->getMockBuilder('Knit\Entity\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $knit->registerRepository('Contact', $invalidRepository);
    }

    public function testRegisteringAndGettingExtensions() {
        $knit = $this->testConstructingAndRegisteringStores();

        $extensionOne = $this->getMock('Knit\Extensions\ExtensionInterface');
        $extensionTwo = $this->getMock('Knit\Extensions\ExtensionInterface');

        $knit->registerExtension('one', $extensionOne);
        $knit->registerExtension('two', $extensionTwo);

        $this->assertSame($extensionOne, $knit->getExtension('one'));
        $this->assertSame($extensionTwo, $knit->getExtension('two'));
    }

    /**
     * @expectedException \Knit\Exceptions\ExtensionNotDefinedException
     */
    public function testGettingUndefinedExtension() {
        $knit = $this->testConstructingAndRegisteringStores();

        $knit->getExtension('undefined');
    }

    public function testRegisteringAndGettingValidators() {
        $knit = $this->testConstructingAndRegisteringStores();

        $minLengthValidator = $this->getMock('Knit\Validators\ValidatorInterface');
        $typeValidator = $this->getMock('Knit\Validators\ValidatorInterface');

        $knit->registerValidator('minLength', $minLengthValidator);
        $knit->registerValidator('type', $typeValidator);

        $this->assertSame($minLengthValidator, $knit->getValidator('minLength'));
        $this->assertSame($typeValidator, $knit->getValidator('type'));
    }

    /**
     * @expectedException \Knit\Exceptions\ValidatorNotDefinedException
     */
    public function testGettingUndefinedValidators() {
        $knit = $this->testConstructingAndRegisteringStores();

        $knit->getValidator('undefined');
    }

    public function testGettingAndSettingStoreNameForEntity() {
        $knit = $this->testConstructingAndRegisteringStores();

        $knit->setStoreNameForEntity('Contact', 'default');
        $knit->setStoreNameForEntity('Person', 'another');
        $knit->setStoreNameForEntity('Dummy', 'dummy');

        $this->assertEquals('default', $knit->getStoreNameForEntity('Contact'));
        $this->assertEquals('another', $knit->getStoreNameForEntity('Person'));
        $this->assertEquals('dummy', $knit->getStoreNameForEntity('Dummy'));
        $this->assertEquals('default', $knit->getStoreNameForEntity('Undefined'));
        $this->assertEquals('default', $knit->getStoreNameForEntity('Task'));
    }

    /**
     * @expectedException \Knit\Exceptions\StoreDefinedException
     */
    public function testOverwritingStoreNameForEntity() {
        $knit = $this->testConstructingAndRegisteringStores();

        $knit->setStoreNameForEntity('Contact', 'another');
        $knit->setStoreNameForEntity('Contact', 'default');
    }

    /**
     * @expectedException \Knit\Exceptions\NoStoreException
     */
    public function testSettingUndefinedStoreNameForEntity() {
        $knit = $this->testConstructingAndRegisteringStores();

        $knit->setStoreNameForEntity('Contact', 'undefined');
    }

    public function testSettingAndGettingRepositoryClassForEntity() {
        $knit = $this->testConstructingAndRegisteringStores();

        $knit->setRepositoryClassForEntity('Person', 'PersonRepository');
        $knit->setRepositoryClassForEntity('Contact', 'Person\ContactRepository');
        $knit->setRepositoryClassForEntity('Message', 'EverythingRepository');

        $this->assertEquals('PersonRepository', $knit->getRepositoryClassForEntity('Person'));
        $this->assertEquals('Person\ContactRepository', $knit->getRepositoryClassForEntity('Contact'));
        $this->assertEquals('EverythingRepository', $knit->getRepositoryClassForEntity('Message'));
        $this->assertEquals('Knit\Entity\Repository', $knit->getRepositoryClassForEntity('Undefined'));
        $this->assertEquals('Knit\Tests\Fixtures\DummyRepository', $knit->getRepositoryClassForEntity('Knit\Tests\Fixtures\Dummy'));
    }

    /**
     * @expectedException \Knit\Exceptions\RepositoryDefinedException
     */
    public function testOverwritingRepositoryClassForEntity() {
        $knit = $this->testConstructingAndRegisteringStores();

        $knit->setRepositoryClassForEntity('Person', 'PersonRepository');
        $knit->setRepositoryClassForEntity('Person', 'GeneralRepository');
    }

    public function testGettingRepository() {
        $knit = $this->testConstructingAndRegisteringStores();

        // get default repository
        $repository = $knit->getRepository(Standard::__class());
        $this->assertEquals('Knit\Entity\Repository', get_class($repository));

        // get predefined class
        $knit->setRepositoryClassForEntity(Predefined::__class(), 'Knit\Tests\Fixtures\DummyRepository');
        $predefinedRepository = $knit->getRepository(Predefined::__class());
        $this->assertEquals('Knit\Tests\Fixtures\DummyRepository', get_class($predefinedRepository));

        // get autonamed class
        $autoRepository = $knit->getRepository(Auto::__class());
        $this->assertEquals('Knit\Tests\Fixtures\AutoRepository', get_class($autoRepository));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGettingInvalidRepository() {
        $knit = $this->testConstructingAndRegisteringStores();

        $repository = $knit->getRepository(NotExtending::__class());
    }

}