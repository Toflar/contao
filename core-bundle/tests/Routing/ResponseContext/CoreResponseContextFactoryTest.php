<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Routing\ResponseContext;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ResponseContext\CoreResponseContextFactory;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\ContaoPageSchema;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\JsonLdManager;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\Environment;
use Contao\PageModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CoreResponseContextFactoryTest extends ContaoTestCase
{
    public function testResponseContext(): void
    {
        $responseAccessor = $this->createMock(ResponseContextAccessor::class);
        $responseAccessor
            ->expects($this->once())
            ->method('setResponseContext')
        ;

        $factory = new CoreResponseContextFactory(
            $responseAccessor,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(TokenChecker::class),
            new HtmlDecoder(),
            $this->createMock(ContaoFramework::class)
        );

        $responseContext = $factory->createResponseContext();

        $this->assertInstanceOf(ResponseHeaderBag::class, $responseContext->getHeaderBag());
    }

    public function testWebpageResponseContext(): void
    {
        $responseAccessor = $this->createMock(ResponseContextAccessor::class);
        $responseAccessor
            ->expects($this->once())
            ->method('setResponseContext')
        ;

        $factory = new CoreResponseContextFactory(
            $responseAccessor,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(TokenChecker::class),
            new HtmlDecoder(),
            $this->createMock(ContaoFramework::class)
        );

        $responseContext = $factory->createWebpageResponseContext();

        $this->assertInstanceOf(HtmlHeadBag::class, $responseContext->get(HtmlHeadBag::class));
        $this->assertTrue($responseContext->has(JsonLdManager::class));
        $this->assertFalse($responseContext->isInitialized(JsonLdManager::class));

        /** @var JsonLdManager $jsonLdManager */
        $jsonLdManager = $responseContext->get(JsonLdManager::class);

        $this->assertSame(
            [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebPage',
                    ],
                ],
            ],
            $jsonLdManager->getGraphForSchema(JsonLdManager::SCHEMA_ORG)->toArray()
        );

        $this->assertTrue($responseContext->isInitialized(JsonLdManager::class));
    }

    public function testContaoWebpageResponseContext(): void
    {
        System::setContainer($this->getContainerWithContaoConfiguration());

        $responseAccessor = $this->createMock(ResponseContextAccessor::class);
        $responseAccessor
            ->expects($this->once())
            ->method('setResponseContext')
        ;

        $controllerAdapter = $this->mockAdapter(['replaceInsertTags']);
        $controllerAdapter
            ->expects($this->once())
            ->method('replaceInsertTags')
            ->with('{{link_url::42}}')
            ->willReturn('de/foobar.html')
        ;

        $environmentAdapter = $this->mockAdapter(['get']);
        $environmentAdapter
            ->expects($this->once())
            ->method('get')
            ->with('base')
            ->willReturn('https://example.com/')
        ;

        $contaoFramework = $this->mockContaoFramework([
            Controller::class => $controllerAdapter,
            Environment::class => $environmentAdapter,
        ]);

        /** @var PageModel $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class);
        $pageModel->title = 'My title';
        $pageModel->description = 'My description';
        $pageModel->robots = 'noindex,nofollow';
        $pageModel->enableCanonical = true;
        $pageModel->canonicalLink = '{{link_url::42}}';

        $factory = new CoreResponseContextFactory(
            $responseAccessor,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(TokenChecker::class),
            new HtmlDecoder(),
            $contaoFramework
        );

        $responseContext = $factory->createContaoWebpageResponseContext($pageModel);

        $this->assertInstanceOf(HtmlHeadBag::class, $responseContext->get(HtmlHeadBag::class));
        $this->assertSame('My title', $responseContext->get(HtmlHeadBag::class)->getTitle());
        $this->assertSame('My description', $responseContext->get(HtmlHeadBag::class)->getMetaDescription());
        $this->assertSame('noindex,nofollow', $responseContext->get(HtmlHeadBag::class)->getMetaRobots());
        $this->assertSame('https://example.com/de/foobar.html', $responseContext->get(HtmlHeadBag::class)->getCanonicalUriForRequest(new Request()));

        $this->assertTrue($responseContext->has(JsonLdManager::class));
        $this->assertTrue($responseContext->isInitialized(JsonLdManager::class));

        /** @var JsonLdManager $jsonLdManager */
        $jsonLdManager = $responseContext->get(JsonLdManager::class);

        $this->assertSame(
            [
                '@context' => 'https://schema.contao.org/',
                '@type' => 'Page',
                'title' => 'My title',
                'pageId' => 0,
                'noSearch' => false,
                'protected' => false,
                'groups' => [],
                'fePreview' => false,
            ],
            $jsonLdManager->getGraphForSchema(JsonLdManager::SCHEMA_CONTAO)->get(ContaoPageSchema::class)->toArray()
        );
    }

    public function testDecodingAndCleanupOnContaoResponseContext(): void
    {
        System::setContainer($this->getContainerWithContaoConfiguration());

        /** @var PageModel $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class);
        $pageModel->title = 'We went from Alpha &#62; Omega';
        $pageModel->description = 'My description <strong>contains</strong> HTML<br>.';

        $factory = new CoreResponseContextFactory(
            $this->createMock(ResponseContextAccessor::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(TokenChecker::class),
            new HtmlDecoder(),
            $this->createMock(ContaoFramework::class)
        );

        $responseContext = $factory->createContaoWebpageResponseContext($pageModel);

        $this->assertSame('We went from Alpha > Omega', $responseContext->get(HtmlHeadBag::class)->getTitle());
        $this->assertSame('My description contains HTML.', $responseContext->get(HtmlHeadBag::class)->getMetaDescription());

        /** @var JsonLdManager $jsonLdManager */
        $jsonLdManager = $responseContext->get(JsonLdManager::class);

        $this->assertSame(
            [
                '@context' => 'https://schema.contao.org/',
                '@type' => 'Page',
                'title' => 'We went from Alpha > Omega',
                'pageId' => 0,
                'noSearch' => false,
                'protected' => false,
                'groups' => [],
                'fePreview' => false,
            ],
            $jsonLdManager->getGraphForSchema(JsonLdManager::SCHEMA_CONTAO)->get(ContaoPageSchema::class)->toArray()
        );
    }
}
