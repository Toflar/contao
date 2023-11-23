<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller;

use Contao\PageModel;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController as SymfonyRedirectController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class RedirectController extends SymfonyRedirectController
{
    public function urlRedirectAction(Request $request, string $path, bool $permanent = false, string $scheme = null, int $httpPort = null, int $httpsPort = null, bool $keepRequestMethod = false): Response
    {
        $response = parent::urlRedirectAction($request, $path, $permanent, $scheme, $httpPort, $httpsPort, $keepRequestMethod);
        $pageModel = $request->attributes->get('pageModel');

        if (
            $pageModel instanceof PageModel
            && !$pageModel->useSSL
            && $request->isSecure()
        ) {
            $response->headers->set('Strict-Transport-Security', 'max-age=0');
        }

        return $response;
    }

}
