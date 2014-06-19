<?php


namespace Oro\Bundle\NoteBundle\Tests\Unit\Entity\Manager;

use Oro\Bundle\NoteBundle\Entity\Manager\NoteManager;
use Oro\Bundle\NoteBundle\Entity\Note;
use Oro\Bundle\UserBundle\Entity\User;

class NoteManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $em;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $securityFacade;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $aclHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $nameFormatter;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $imageCacheManager;

    /** @var NoteManager */
    protected $manager;

    protected function setUp()
    {
        $this->em                = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->securityFacade    = $this->getMockBuilder('Oro\Bundle\SecurityBundle\SecurityFacade')
            ->disableOriginalConstructor()
            ->getMock();
        $this->aclHelper         = $this->getMockBuilder('Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper')
            ->disableOriginalConstructor()
            ->getMock();
        $this->nameFormatter     = $this->getMockBuilder('Oro\Bundle\LocaleBundle\Formatter\NameFormatter')
            ->disableOriginalConstructor()
            ->getMock();
        $this->imageCacheManager = $this->getMockBuilder('Liip\ImagineBundle\Imagine\Cache\CacheManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->manager = new NoteManager(
            $this->em,
            $this->securityFacade,
            $this->aclHelper,
            $this->nameFormatter,
            $this->imageCacheManager
        );
    }

    public function testGetList()
    {
        $entityClass = 'Test\Entity';
        $entityId    = 123;
        $sorting     = 'DESC';
        $result      = ['result'];

        $qb    = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();
        $query = $this->getMockBuilder('Doctrine\ORM\AbstractQuery')
            ->disableOriginalConstructor()
            ->setMethods(['getResult'])
            ->getMockForAbstractClass();
        $repo  = $this->getMockBuilder('Oro\Bundle\NoteBundle\Entity\Repository\NoteRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $this->em->expects($this->once())
            ->method('getRepository')
            ->with('OroNoteBundle:Note')
            ->will($this->returnValue($repo));
        $repo->expects($this->once())
            ->method('getAssociatedNotesQueryBuilder')
            ->with($entityClass, $entityId)
            ->will($this->returnValue($qb));
        $qb->expects($this->once())
            ->method('orderBy')
            ->with('note.createdAt', $sorting)
            ->will($this->returnSelf());
        $this->aclHelper->expects($this->once())
            ->method('apply')
            ->with($this->identicalTo($qb))
            ->will($this->returnValue($query));
        $query->expects($this->once())
            ->method('getResult')
            ->will($this->returnValue($result));

        $this->assertEquals(
            $result,
            $this->manager->getList($entityClass, $entityId, $sorting)
        );
    }

    public function testGetEntityViewModels()
    {
        $createdBy = $this->getMockBuilder('Oro\Bundle\UserBundle\Entity\User')
            ->disableOriginalConstructor()
            ->getMock();
        $createdBy->expects($this->once())->method('getId')->will($this->returnValue(100));
        $createdBy->expects($this->once())->method('getImagePath')->will($this->returnValue('image1'));
        $updatedBy = $this->getMockBuilder('Oro\Bundle\UserBundle\Entity\User')
            ->disableOriginalConstructor()
            ->getMock();
        $updatedBy->expects($this->once())->method('getId')->will($this->returnValue(100));
        $updatedBy->expects($this->once())->method('getImagePath')->will($this->returnValue(null));

        $note = new Note();
        $this->setId($note, 123);
        $note
            ->setMessage('test message')
            ->setCreatedAt(new \DateTime('2014-01-20 10:30:40', new \DateTimeZone('UTC')))
            ->setUpdatedAt(new \DateTime('2014-01-21 10:30:40', new \DateTimeZone('UTC')))
            ->setOwner($createdBy)
            ->setUpdatedBy($updatedBy);

        $this->securityFacade->expects($this->at(0))
            ->method('isGranted')
            ->with('EDIT', $this->identicalTo($note))
            ->will($this->returnValue(true));
        $this->securityFacade->expects($this->at(1))
            ->method('isGranted')
            ->with('DELETE', $this->identicalTo($note))
            ->will($this->returnValue(false));
        $this->securityFacade->expects($this->at(2))
            ->method('isGranted')
            ->with('VIEW', $this->identicalTo($createdBy))
            ->will($this->returnValue(true));
        $this->securityFacade->expects($this->at(3))
            ->method('isGranted')
            ->with('VIEW', $this->identicalTo($updatedBy))
            ->will($this->returnValue(false));

        $this->nameFormatter->expects($this->at(0))
            ->method('format')
            ->with($this->identicalTo($createdBy))
            ->will($this->returnValue('User1'));
        $this->nameFormatter->expects($this->at(1))
            ->method('format')
            ->with($this->identicalTo($updatedBy))
            ->will($this->returnValue('User2'));

        $this->imageCacheManager->expects($this->once())
            ->method('getBrowserPath')
            ->with('image1', 'avatar_xsmall')
            ->will($this->returnValue('image1_xsmall'));

        $this->assertEquals(
            [
                [
                    'id'                 => 123,
                    'message'            => 'test message',
                    'createdAt'          => '2014-01-20T10:30:40+00:00',
                    'updatedAt'          => '2014-01-21T10:30:40+00:00',
                    'hasUpdate'          => true,
                    'editable'           => true,
                    'removable'          => false,
                    'createdBy'          => 'User1',
                    'createdBy_id'       => 100,
                    'createdBy_viewable' => true,
                    'createdBy_avatar'   => 'image1_xsmall',
                    'updatedBy'          => 'User2',
                    'updatedBy_id'       => 100,
                    'updatedBy_viewable' => false,
                    'updatedBy_avatar'   => null,
                ]
            ],
            $this->manager->getEntityViewModels([$note])
        );
    }

    /**
     * @param mixed $obj
     * @param mixed $val
     */
    protected function setId($obj, $val)
    {
        $class = new \ReflectionClass($obj);
        $prop  = $class->getProperty('id');
        $prop->setAccessible(true);

        $prop->setValue($obj, $val);
    }
}
