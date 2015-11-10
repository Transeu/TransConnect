<?php

class JSONRPC2ClientSecureTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $curl;

    /**
     * @var \JSONRPC2ClientSecure
     */
    protected $jsonRpc2ClientSecure;

    public function setUp()
    {
        $this->curl = $this->getMockBuilder('\Curl\Curl')->disableOriginalConstructor()->getMock();

        $this->jsonRpc2ClientSecure = $this->getMockBuilder('\JSONRPC2ClientSecure')
            ->setConstructorArgs(array('http://api.trans.eu', 'api_key', 'secret_key', null, $this->curl))
            ->setMethods(array('_getTimestamp', '_generateNonce'))
            ->getMock();

        $this->jsonRpc2ClientSecure->expects($this->once())
            ->method('_getTimestamp')
            ->willReturn(1447145676);

        $this->jsonRpc2ClientSecure->expects($this->once())
            ->method('_generateNonce')
            ->willReturn('12447055465641b0cc15a0e0.73308763');
    }

    public function testSuccessResponse()
    {
        $this->mockCurl('ok', null, 1);

        $this->jsonRpc2ClientSecure->setClass('Test');
        $data = $this->jsonRpc2ClientSecure->test(array('foo' => 'bar'));
        $this->assertSame('ok', $data);
    }

    /**
     * @expectedException \ApiException
     * @expectedExceptionMessage Invalid data
     * @expectedExceptionCode 1
     */
    public function testErrorResponse()
    {
        $this->mockCurl(null, array(
            'message' => 'Invalid data',
            'code' => 1,
            'data' => null,
        ), 2);

        $this->jsonRpc2ClientSecure->setClass('Test');

        $this->jsonRpc2ClientSecure->test(array('foo' => 'bar'));
    }

    protected function mockCurl($result = null, $error = null, $id = 1)
    {
        $response = array(
            'id' => $id,
            "jsonrpc" => "2.0",
        );

        if ($result !== null) {
            $response['result'] = $result;
        }

        if ($error !== null) {
            $response['error'] = $error;
        }

        $this->curl->expects($this->once())
            ->method('post')
            ->with('http://api.trans.eu/?class=Test', array(
                'jsonrpc' => '2.0',
                'method' => 'test',
                'params' => array(
                    array(
                        'auth_apikey' => 'api_key',
                        'auth_timestamp' => 1447145676,
                        'auth_nonce' => '12447055465641b0cc15a0e0.73308763',
                        'auth_signature' => 'YhvNTYtMQY%2FWFjbToDKjaH3j7SU%3D'),
                    array(
                        'foo' => 'bar'
                    ),
                ),
                'id' => $id,
            ))
            ->willReturn($response);
    }
}