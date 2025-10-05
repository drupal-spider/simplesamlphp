<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\core\Auth\Process;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SimpleSAML\{Configuration, Utils};
use SimpleSAML\Module\core\Auth\Process\TargetedID;
use SimpleSAML\SAML2\Constants as C;
use SimpleSAML\SAML2\XML\saml\NameID;

/**
 * Test for the core:TargetedID filter.
 */
#[CoversClass(TargetedID::class)]
class TargetedIDTest extends TestCase
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Utils\Config */
    protected static Utils\Config $configUtils;

    /**
     * Set up for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::$configUtils = new class () extends Utils\Config {
            public function getSecretSalt(): string
            {
                // stub
                return 'secretsalt';
            }
        };
    }


    /**
     * Helper function to run the filter with a given configuration.
     *
     * @param array $config  The filter configuration.
     * @param array $request  The request state.
     * @return array  The state array after processing.
     * @throws Exception
     */
    private static function processFilter(array $config, array $request): array
    {
        $filter = new TargetedID($config, null);
        $filter->setConfigUtils(self::$configUtils);
        $filter->process($request);
        return $request;
    }


    /**
     * Test the most basic functionality
     * @throws Exception
     */
    public function testBasic(): void
    {
        $config = ['identifyingAttribute' => 'uid'];
        $request = [
            'Attributes' => ['uid' => ['user2@example.org']],
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('eduPersonTargetedID', $attributes);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $attributes['eduPersonTargetedID'][0]);
    }


    /**
     * Test with src and dst entityIds.
     * Make sure to overwrite any present eduPersonTargetedId
     * @throws Exception
     */
    public function testWithSrcDst(): void
    {
        $config = ['identifyingAttribute' => 'uid'];
        $request = [
            'Attributes' => [
                'eduPersonTargetedID' => ['dummy'],
                'uid' => ['user2@example.org'],
            ],
            'Source' => [
                'metadata-set' => 'saml20-idp-hosted',
                'entityid' => 'urn:example:src:id',
            ],
            'Destination' => [
                'metadata-set' => 'saml20-sp-remote',
                'entityid' => 'joe',
            ],
        ];

        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];

        $this->assertArrayHasKey('eduPersonTargetedID', $attributes);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $attributes['eduPersonTargetedID'][0]);
    }


    /**
     * Test with nameId config option set.
     * @throws Exception
     */
    public function testNameIdGeneration(): void
    {
        $nameid = new NameID(
            value: 'joe',
            Format: C::NAMEID_PERSISTENT,
            NameQualifier: 'urn:example:src:id',
            SPNameQualifier: 'joe',
        );

        $config = [
            'nameId' => true,
            'identifyingAttribute' => 'eduPersonPrincipalName',
        ];

        $request = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['joe'],
                'eduPersonTargetedID' => [$nameid->toXML()->ownerDocument?->saveXML()],
            ],
            'Source' => [
                'metadata-set' => 'saml20-idp-hosted',
                'entityid' => 'urn:example:src:id',
            ],
            'Destination' => [
                'metadata-set' => 'saml20-sp-remote',
                'entityid' => 'joe',
            ],
        ];

        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];

        $this->assertArrayHasKey('eduPersonTargetedID', $attributes);
        $this->assertMatchesRegularExpression(
            '#^<saml:NameID xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" NameQualifier="urn:example:src:id"' .
            ' SPNameQualifier="joe"' .
            ' Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">[0-9a-f]{40}</saml:NameID>$#',
            strval($attributes['eduPersonTargetedID'][0]),
        );
    }


    /**
     * Test the outcome to make sure the algorithm remains unchanged
     * @throws Exception
     */
    public function testOutcome(): void
    {
        $config = ['identifyingAttribute' => 'uid'];
        $request = [
            'Attributes' => ['uid' => ['user2@example.org']],
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('eduPersonTargetedID', $attributes);
        $this->assertEquals('c1ae2c2ef77b73f7c47b700e42617117b6ec4adc', $attributes['eduPersonTargetedID'][0]);
    }


    /**
     * Test the outcome when multiple values are given
     * @throws Exception
     */
    public function testOutcomeMultipleValues(): void
    {
        $config = ['identifyingAttribute' => 'uid'];
        $request = [
            'Attributes' => ['uid' => ['user2@example.org', 'donald@duck.org']],
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('eduPersonTargetedID', $attributes);
        $this->assertEquals('c1ae2c2ef77b73f7c47b700e42617117b6ec4adc', $attributes['eduPersonTargetedID'][0]);
    }


    /**
     * Test that Id is the same for subsequent invocations with same input.
     * @throws Exception
     */
    public function testIdIsPersistent(): void
    {
        $config = ['identifyingAttribute' => 'uid'];
        $request = [
            'Attributes' => [
                'eduPersonTargetedID' => ['dummy'],
                'uid' => ['user2@example.org'],
            ],
            'Source' => [
                'metadata-set' => 'saml20-idp-hosted',
                'entityid' => 'urn:example:src:id',
            ],
            'Destination' => [
                'metadata-set' => 'saml20-sp-remote',
                'entityid' => 'joe',
            ],
        ];

        for ($i = 0; $i < 10; ++$i) {
            $result = self::processFilter($config, $request);
            $attributes = $result['Attributes'];
            $tid = $attributes['eduPersonTargetedID'][0];
            if (isset($prevtid)) {
                $this->assertEquals($prevtid, $tid);
            }
            $prevtid = $tid;
        }
    }


    /**
     * Test that Id is different for two different usernames and two different sp's
     * @throws Exception
     */
    public function testIdIsUnique(): void
    {
        $config = ['identifyingAttribute' => 'uid'];
        $request = [
            'Attributes' => ['uid' => ['user2@example.org']],
            'Source' => [
                'metadata-set' => 'saml20-idp-hosted',
                'entityid' => 'urn:example:src:id',
            ],
            'Destination' => [
                'metadata-set' => 'saml20-sp-remote',
                'entityid' => 'joe',
            ],
        ];

        $result = self::processFilter($config, $request);
        $tid1 = $result['Attributes']['eduPersonTargetedID'][0];

        $request['Attributes']['uid'][0] = 'user3@example.org';
        $result = self::processFilter($config, $request);
        $tid2 = $result['Attributes']['eduPersonTargetedID'][0];

        $this->assertNotEquals($tid1, $tid2);

        $request['Destination']['entityid'] = 'urn:example.org:another-sp';
        $result = self::processFilter($config, $request);
        $tid3 = $result['Attributes']['eduPersonTargetedID'][0];

        $this->assertNotEquals($tid2, $tid3);
    }


    /**
     * Test no userid set
     */
    public function testNoUserID(): void
    {
        $this->expectException(Exception::class);
        $config = [];
        $request = [
            'Attributes' => [],
        ];
        self::processFilter($config, $request);
    }


    /**
     * Test with specified attribute not set
     */
    public function testAttributeNotExists(): void
    {
        $this->expectException(Exception::class);
        $config = [
            'attributename' => 'uid',
        ];
        $request = [
            'Attributes' => [
                'displayName' => ['Jack Student'],
            ],
        ];
        self::processFilter($config, $request);
    }


    /**
     * Test with configuration error 1
     */
    public function testConfigInvalidAttributeName(): void
    {
        $this->expectException(Exception::class);
        $config = [
            'attributename' => 5,
        ];
        $request = [
            'Attributes' => [
                'displayName' => ['Jack Student'],
            ],
        ];
        self::processFilter($config, $request);
    }


    /**
     * Test with configuration error 2
     */
    public function testConfigInvalidNameId(): void
    {
        $this->expectException(Exception::class);
        $config = [
            'nameId' => 'persistent',
        ];
        $request = [
            'Attributes' => [
                'displayName' => ['Jack Student'],
            ],
        ];
        self::processFilter($config, $request);
    }
}
