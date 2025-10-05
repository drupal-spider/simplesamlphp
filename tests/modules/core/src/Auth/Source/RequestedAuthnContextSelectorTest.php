<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\core\Auth\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\{Auth, Configuration};
use SimpleSAML\Assert\AssertionFailedException;
use SimpleSAML\Error\Exception;
use SimpleSAML\Module\core\Auth\Source\AbstractSourceSelector;
use SimpleSAML\Module\core\Auth\Source\RequestedAuthnContextSelector;
use SimpleSAML\SAML2\Exception\Protocol\NoAuthnContextException;
use Symfony\Component\HttpFoundation\{Request, Response};

/**
 */
#[CoversClass(AbstractSourceSelector::class)]
#[CoversClass(RequestedAuthnContextSelector::class)]
class RequestedAuthnContextSelectorTest extends TestCase
{
    /** @var \SimpleSAML\Configuration */
    private Configuration $config;

    /** @var \SimpleSAML\Configuration */
    private Configuration $sourceConfig;


    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        $this->config = Configuration::loadFromArray(
            ['module.enable' => ['core' => true]],
            '[ARRAY]',
            'simplesaml',
        );
        Configuration::setPreLoadedConfig($this->config, 'config.php');

        $this->sourceConfig = Configuration::loadFromArray([
            'selector' => [
                'core:RequestedAuthnContextSelector',

                'contexts' => [
                    10 => [
                        'identifier' => 'urn:x-simplesamlphp:loa1',
                        'source' => 'loa1',
                    ],

                    20 => [
                        'identifier' => 'urn:x-simplesamlphp:loa2',
                        'source' => 'loa2',
                    ],

                    30 => [
                        'identifier' => 'urn:x-simplesamlphp:loa3',
                        'source' => 'loa3',
                    ],

                    'default' => 'loa1',
                ],
            ],

            'loa1' => [
                'core:AdminPassword',
            ],

            'loa2' => [
                'core:AdminPassword',
            ],

            'loa3' => [
                'core:AdminPassword',
            ],
        ]);

        Configuration::setPreLoadedConfig($this->sourceConfig, 'authsources.php');
    }


    /**
     * No RequestedAuthnContext
     * @throws \Exception
     * @throws \SimpleSAML\Error\Exception
     * @throws \Throwable
     */
    public function testAuthenticationVariant1(): void
    {
        $info = ['AuthId' => 'selector'];
        $config = $this->sourceConfig->getArray('selector');

        $selector = new RequestedAuthnContextSelector($info, $config);
        $state = ['saml:RequestedAuthnContext' => ['AuthnContextClassRef' => null]];

        $request = Request::createFromGlobals();
        $selector->authenticate($request, $state);
        $this->assertArrayNotHasKey('saml:AuthnContextClassRef', $state);
    }


    /**
     * Specific RequestedAuthnContext
     * @throws \Exception
     * @throws \SimpleSAML\Error\Exception
     * @throws \Throwable
     */
    public function testAuthenticationVariant2(): void
    {
        $info = ['AuthId' => 'selector'];
        $config = $this->sourceConfig->getArray('selector');

        $selector = new RequestedAuthnContextSelector($info, $config);
        $state = ['saml:RequestedAuthnContext' => ['AuthnContextClassRef' => ['urn:x-simplesamlphp:loa1']]];

        $request = Request::createFromGlobals();
        $selector->authenticate($request, $state);
        $this->assertArrayHasKey('saml:AuthnContextClassRef', $state);
        $this->assertEquals('urn:x-simplesamlphp:loa1', $state['saml:AuthnContextClassRef']);
    }


    /**
     * Specific RequestedAuthnContext with comparison=exact
     * @throws \Exception
     * @throws \SimpleSAML\Error\Exception
     * @throws \Throwable
     */
    public function testAuthenticationVariant3(): void
    {
        $info = ['AuthId' => 'selector'];
        $config = $this->sourceConfig->getArray('selector');

        $selector = new RequestedAuthnContextSelector($info, $config);
        $state = [
            'saml:RequestedAuthnContext' => [
                'AuthnContextClassRef' => ['urn:x-simplesamlphp:loa1'],
                'Comparison' => 'exact',
            ],
        ];

        $request = Request::createFromGlobals();
        $selector->authenticate($request, $state);
        $this->assertArrayHasKey('saml:AuthnContextClassRef', $state);
        $this->assertEquals('urn:x-simplesamlphp:loa1', $state['saml:AuthnContextClassRef']);
    }


    /**
     * Array-syntax
     * @throws \Exception
     * @throws \Throwable
     */
    public function testArraySyntaxWorks(): void
    {
        $sourceConfig = Configuration::loadFromArray([
            'selector' => [
                'core:RequestedAuthnContextSelector',

                'contexts' => [
                    20 => [
                        'identifier' => 'urn:x-simplesamlphp:loa2',
                        'source' => 'loa2',
                    ],
                    'default' => [
                        'identifier' => 'urn:x-simplesamlphp:loa1',
                        'source' => 'loa1',
                    ],
                ],
            ],

            'loa1' => [
                'core:AdminPassword',
            ],
        ]);

        Configuration::setPreLoadedConfig($sourceConfig, 'authsources.php');

        $info = ['AuthId' => 'selector'];
        $config = $sourceConfig->getArray('selector');

        $selector = new class ($info, $config) extends RequestedAuthnContextSelector {
            /**
             * @param \Symfony\Component\HttpFoundation\Request $request
             * @param \SimpleSAML\Auth\Source $as
             * @param array $state
             * @return \Symfony\Component\HttpFoundation\Response|null
             */
            public static function doAuthentication(Request $request, Auth\Source $as, array &$state): ?Response
            {
                // Dummy
                return null;
            }
        };

        $state = [
            'saml:RequestedAuthnContext' => [
                'AuthnContextClassRef' => ['urn:x-simplesamlphp:loa1'],
                'Comparison' => 'exact',
            ],
        ];

        $request = Request::createFromGlobals();
        $selector->authenticate($request, $state);
        $this->assertArrayHasKey('saml:AuthnContextClassRef', $state);
        $this->assertEquals('urn:x-simplesamlphp:loa1', $state['saml:AuthnContextClassRef']);
    }


    /**
     * Missing source
     * @throws \Exception
     */
    public function testIncompleteConfigurationThrowsExceptionVariant1(): void
    {
        $sourceConfig = Configuration::loadFromArray([
            'selector' => [
                'core:RequestedAuthnContextSelector',

                'contexts' => [
                    10 => [
                        'identifier' => 'urn:x-simplesamlphp:loa1',
                    ],
                    'default' => 'phpunit',
                ],
            ],
        ]);

        Configuration::setPreLoadedConfig($this->sourceConfig, 'authsources.php');

        $info = ['AuthId' => 'selector'];
        $config = $sourceConfig->getArray('selector');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Incomplete context '10' due to missing `source` key.");

        new RequestedAuthnContextSelector($info, $config);
    }


    /**
     * Missing identifier
     * @throws \Exception
     */
    public function testIncompleteConfigurationThrowsExceptionVariant2(): void
    {
        $sourceConfig = Configuration::loadFromArray([
            'selector' => [
                'core:RequestedAuthnContextSelector',

                'contexts' => [
                    10 => [
                        'source' => 'loa1',
                    ],
                    'default' => 'phpunit',
                ],
            ],
        ]);

        Configuration::setPreLoadedConfig($this->sourceConfig, 'authsources.php');

        $info = ['AuthId' => 'selector'];
        $config = $sourceConfig->getArray('selector');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Incomplete context '10' due to missing `identifier` key.");

        new RequestedAuthnContextSelector($info, $config);
    }


    /**
     * Missing default
     * @throws \SimpleSAML\Error\Exception
     * @throws \Exception
     */
    public function testIncompleteConfigurationThrowsExceptionVariant3(): void
    {
        $sourceConfig = Configuration::loadFromArray([
            'selector' => [
                'core:RequestedAuthnContextSelector',

                'contexts' => [
                    10 => [
                        'source' => 'loa1',
                    ],
                ],
            ],
        ]);

        Configuration::setPreLoadedConfig($this->sourceConfig, 'authsources.php');

        $info = ['AuthId' => 'selector'];
        $config = $sourceConfig->getArray('selector');

        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage('Expected the key "default" to exist.');

        new RequestedAuthnContextSelector($info, $config);
    }


    /**
     * @param array $requestedAuthnContext  The RequestedAuthnContext
     * @param string $expected  The expected authsource
     * @throws \Exception
     */
    #[DataProvider('provideRequestedAuthnContext')]
    public function testSelectAuthSource(array $requestedAuthnContext, string $expected): void
    {
        $info = ['AuthId' => 'selector'];
        $config = $this->sourceConfig->getArray('selector');

        $selector = new class ($info, $config) extends RequestedAuthnContextSelector {
            public function selectAuthSource(array &$state): string
            {
                return parent::selectAuthSource($state);
            }
        };

        $state = ['saml:RequestedAuthnContext' => $requestedAuthnContext];

        try {
            $source = $selector->selectAuthSource($state);
        } catch (AssertionFailedException | NoAuthnContextException | Exception $e) {
            $source = $e::class;
        }

        $this->assertEquals($expected, $source);
    }


    /**
     * @return array
     */
    public static function provideRequestedAuthnContext(): array
    {
        return [
            // Normal use-case - No RequestedAuthnContext provided
            [
                ['AuthnContextClassRef' => null],
                'loa1',
            ],

            // Normal use-case
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa1',
                    ],
                    'Comparison' => 'exact',
                ],
                'loa1',
            ],

            // Order is important - see specs
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa1',
                        'urn:x-simplesamlphp:loa2',
                    ],
                    'Comparison' => 'exact',
                ],
                'loa1',
            ],
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa2',
                        'urn:x-simplesamlphp:loa1',
                    ],
                    'Comparison' => 'exact',
                ],
                'loa2',
            ],
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa30',
                        'urn:x-simplesamlphp:loa20',
                        'urn:x-simplesamlphp:loa2',
                        'urn:x-simplesamlphp:loa10',
                    ],
                    'Comparison' => 'exact',
                ],
                'loa2',
            ],

            // Unknown context requested
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa4',
                    ],
                    'Comparison' => 'exact',
                ],
                NoAuthnContextException::class,
            ],

            // Unknown comparison requested
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa2',
                    ],
                    'Comparison' => 'phpunit',
                ],
                AssertionFailedException::class,
            ],

            // Non-implemented comparison requested
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa2',
                    ],
                    'Comparison' => 'minimum',
                ],
                Exception::class,
            ],
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa2',
                    ],
                    'Comparison' => 'maximum',
                ],
                Exception::class,
            ],
            [
                [
                    'AuthnContextClassRef' => [
                        'urn:x-simplesamlphp:loa2',
                    ],
                    'Comparison' => 'better',
                ],
                Exception::class,
            ],
        ];
    }
}
