<?php

namespace Clue\Tests\React\SshProxy;

use Clue\React\SshProxy\SshProcessConnector;

class SshProcessConnectorTest extends TestCase
{
    /**
     * @after
     */
    public function tearDownSSHClientProcess()
    {
        // Skip timer-based teardown for PHP 5.3 where React\Promise\Timer is not available
        if (!class_exists('React\\Promise\\Timer\\TimeoutException')) {
            return;
        }

        // run loop in order to shut down SSH client process again
        \React\Async\await(\React\Promise\Timer\sleep(0.001));
    }

    // Helper method to check if Timer functions are available
    private function hasTimerSupport()
    {
        return class_exists('React\\Promise\\Timer\\TimeoutException');
    }

    public function testConstructWithoutLoopAssignsLoopAutomatically()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'loop');
        $ref->setAccessible(true);
        $loop = $ref->getValue($connector);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $loop);
    }

    public function testConstructorAcceptsUri()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshProcessConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec ssh -vv -o BatchMode=yes \'host\'', $ref->getValue($connector));
    }

    public function testConstructorAcceptsUriWithDefaultPortWillNotBeAddedToCommand()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshProcessConnector('host:22', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec ssh -vv -o BatchMode=yes \'host\'', $ref->getValue($connector));
    }

    public function testConstructorAcceptsUriWithUserAndCustomPort()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshProcessConnector('user@host:2222', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec ssh -vv -o BatchMode=yes -p 2222 \'user@host\'', $ref->getValue($connector));
    }

    public function testConstructorAcceptsUriWithPasswordWillPrefixSshCommandWithSshpassAndWithoutBatchModeOption()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshProcessConnector('user:pass@host', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec sshpass -p \'pass\' ssh -vv \'user@host\'', $ref->getValue($connector));
    }

    public function testConstructorThrowsForInvalidUri()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new SshProcessConnector('///', $loop);
    }

    public function testConstructorThrowsForInvalidUser()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new SshProcessConnector('-invalid@host', $loop);
    }

    public function testConstructorThrowsForInvalidPass()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new SshProcessConnector('user:-invalid@host', $loop);
    }

    public function testConstructorThrowsForInvalidHost()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new SshProcessConnector('-host', $loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testConstructorAcceptsHostWithLeadingDashWhenPrefixedWithUser()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshProcessConnector('user@-host', $loop);
    }

    public function testConnectReturnsRejectedPromiseForInvalidUri()
    {
        if (!$this->hasTimerSupport()) {
            $this->markTestSkipped('No Timer support available');
            return;
        }

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshProcessConnector('host', $loop);

        $promise = $connector->connect('///');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('InvalidArgumentException')));
    }

    public function testConnectReturnsRejectedPromiseForInvalidHost()
    {
        if (!$this->hasTimerSupport()) {
            $this->markTestSkipped('No Timer support available');
            return;
        }

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshProcessConnector('host', $loop);

        $promise = $connector->connect('-host:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('InvalidArgumentException')));
    }
}
