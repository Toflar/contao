<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Runtime;

use Contao\CoreBundle\Csp\WysiwygProcessor;
use Contao\CoreBundle\Routing\ResponseContext\Csp\CspHandler;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContext;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Runtime\CspRuntime;
use Nelmio\SecurityBundle\ContentSecurityPolicy\DirectiveSet;
use Nelmio\SecurityBundle\ContentSecurityPolicy\PolicyManager;

class CspRuntimeTest extends TestCase
{
    public function testRetrievesNonceFromCspBuilder(): void
    {
        $directives = new DirectiveSet(new PolicyManager());
        $directives->setDirective('script-src', "'self'");

        $cspHandler = new CspHandler($directives);
        $responseContext = (new ResponseContext())->add($cspHandler);

        $responseContextAccessor = $this->createMock(ResponseContextAccessor::class);
        $responseContextAccessor
            ->expects($this->once())
            ->method('getResponseContext')
            ->willReturn($responseContext)
        ;

        $runtime = new CspRuntime($responseContextAccessor, new WysiwygProcessor());

        $this->assertNotNull($runtime->getNonce('script-src'));
    }

    public function testAddsCspSource(): void
    {
        $directives = new DirectiveSet(new PolicyManager());
        $directives->setDirective('script-src', "'self'");

        $cspHandler = new CspHandler($directives);
        $responseContext = (new ResponseContext())->add($cspHandler);

        $responseContextAccessor = $this->createMock(ResponseContextAccessor::class);
        $responseContextAccessor
            ->expects($this->once())
            ->method('getResponseContext')
            ->willReturn($responseContext)
        ;

        $runtime = new CspRuntime($responseContextAccessor, new WysiwygProcessor());

        $runtime->addSource('script-src', 'https://example.com/files/foo/foobar.js');

        $this->assertSame("'self' https://example.com/files/foo/foobar.js", $directives->getDirective('script-src'));
    }

    public function testCallsWysiwygProcessor(): void
    {
        $directives = new DirectiveSet(new PolicyManager());
        $directives->setDirective('style-src', "'self'");

        $cspHandler = new CspHandler($directives);
        $responseContext = (new ResponseContext())->add($cspHandler);

        $responseContextAccessor = $this->createMock(ResponseContextAccessor::class);
        $responseContextAccessor
            ->expects($this->once())
            ->method('getResponseContext')
            ->willReturn($responseContext)
        ;

        $wysiwygProcessor = $this->createMock(WysiwygProcessor::class);
        $wysiwygProcessor
            ->expects($this->once())
            ->method('processStyles')
            ->with(
                'foobar',
                $this->callback(static fn (string $nonce) => $nonce === $cspHandler->getNonce('style-src')),
            )
        ;

        $runtime = new CspRuntime($responseContextAccessor, $wysiwygProcessor);
        $runtime->wysiwygStyles('foobar');
    }
}
